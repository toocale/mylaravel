<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Models\MachineProductConfig;
use App\Models\Notification;
use App\Models\ProductionShift;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckAlertRulesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $rules = AlertRule::active()->get();

        if ($rules->isEmpty()) {
            return;
        }

        // Get all machines with their active shifts
        $machines = Machine::with(['line.plant'])->get();

        foreach ($machines as $machine) {
            $activeShift = ProductionShift::where('machine_id', $machine->id)
                ->where('status', 'active')
                ->with('product')
                ->first();

            foreach ($rules as $rule) {
                // Check if rule applies to this machine
                if (!$rule->appliesToMachine($machine)) {
                    continue;
                }

                // Check cooldown
                if ($rule->isInCooldown($machine->id)) {
                    continue;
                }

                $triggered = $this->evaluateRule($rule, $machine, $activeShift);

                if ($triggered) {
                    $this->createAlert($rule, $machine, $triggered);
                }
            }
        }

        // Auto-resolve alerts whose conditions are no longer true
        $this->autoResolveAlerts();
    }

    /**
     * Evaluate a single rule against a machine's current state.
     * Returns alert data array if triggered, null otherwise.
     */
    private function evaluateRule(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        return match ($rule->type) {
            'oee_below_target' => $this->checkOeeBelowTarget($rule, $machine, $shift),
            'machine_stopped' => $this->checkMachineStopped($rule, $machine, $shift),
            'excessive_downtime' => $this->checkExcessiveDowntime($rule, $machine, $shift),
            'quality_drop' => $this->checkQualityDrop($rule, $machine, $shift),
            'performance_drop' => $this->checkPerformanceDrop($rule, $machine, $shift),
            default => null,
        };
    }

    private function checkOeeBelowTarget(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        if (!$shift) return null;

        $oee = $this->calculateQuickOee($shift, $machine);
        if ($oee === null) return null;

        // Check if shift has been running long enough
        $minutesRunning = $shift->started_at->diffInMinutes(now());
        if ($minutesRunning < max($rule->duration_minutes, 15)) return null; // Need at least 15 min of data

        if ($oee < $rule->threshold) {
            return [
                'title' => "OEE Below Target on {$machine->name}",
                'message' => "Current OEE is {$oee}% (threshold: {$rule->threshold}%). Running for {$minutesRunning} minutes.",
                'data' => ['oee' => $oee, 'threshold' => $rule->threshold],
            ];
        }

        return null;
    }

    private function checkMachineStopped(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        if (!$shift) return null;

        $activeDowntime = DowntimeEvent::where('production_shift_id', $shift->id)
            ->whereNull('end_time')
            ->first();

        if (!$activeDowntime) return null;

        $downtimeMinutes = (int) $activeDowntime->start_time->diffInMinutes(now());
        $requiredMinutes = $rule->duration_minutes > 0 ? $rule->duration_minutes : 10;

        if ($downtimeMinutes >= $requiredMinutes) {
            return [
                'title' => "{$machine->name} Stopped",
                'message' => "Machine has been stopped for {$downtimeMinutes} minutes. Reason: " . ($activeDowntime->reason ?? 'Unknown'),
                'data' => ['downtime_minutes' => $downtimeMinutes, 'reason' => $activeDowntime->reason],
            ];
        }

        return null;
    }

    private function checkExcessiveDowntime(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        if (!$shift) return null;

        // Check any active downtime event that exceeds threshold
        $activeDowntime = DowntimeEvent::where('production_shift_id', $shift->id)
            ->whereNull('end_time')
            ->first();

        if (!$activeDowntime) return null;

        $downtimeMinutes = (int) $activeDowntime->start_time->diffInMinutes(now());

        if ($downtimeMinutes >= $rule->threshold) {
            return [
                'title' => "Excessive Downtime on {$machine->name}",
                'message' => "Downtime event has exceeded {$rule->threshold} minutes (current: {$downtimeMinutes} min). Reason: " . ($activeDowntime->reason ?? 'Unknown'),
                'data' => ['downtime_minutes' => $downtimeMinutes, 'threshold' => $rule->threshold],
            ];
        }

        return null;
    }

    private function checkQualityDrop(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        if (!$shift) return null;

        $totalCount = $shift->total_count ?? 0;
        $rejectCount = $shift->reject_count ?? 0;

        if ($totalCount < 10) return null; // Need minimum production

        $rejectRate = ($rejectCount / $totalCount) * 100;

        if ($rejectRate > $rule->threshold) {
            return [
                'title' => "Quality Drop on {$machine->name}",
                'message' => "Reject rate is " . round($rejectRate, 1) . "% (threshold: {$rule->threshold}%). Total: {$totalCount}, Rejects: {$rejectCount}.",
                'data' => ['reject_rate' => round($rejectRate, 1), 'threshold' => $rule->threshold],
            ];
        }

        return null;
    }

    private function checkPerformanceDrop(AlertRule $rule, Machine $machine, ?ProductionShift $shift): ?array
    {
        if (!$shift) return null;

        $minutesRunning = $shift->started_at->diffInMinutes(now());
        if ($minutesRunning < max($rule->duration_minutes, 15)) return null;

        // Calculate actual rate vs ideal rate
        $totalCount = $shift->total_count ?? 0;
        $hoursRunning = max(0.1, $minutesRunning / 60);
        $actualRate = $totalCount / $hoursRunning;

        $idealRate = $this->getIdealRate($machine->id, $shift->product_id);
        if ($idealRate <= 0) return null;

        $performancePct = ($actualRate / $idealRate) * 100;

        if ($performancePct < $rule->threshold) {
            return [
                'title' => "Performance Drop on {$machine->name}",
                'message' => "Performance at " . round($performancePct, 1) . "% of ideal rate (threshold: {$rule->threshold}%). Actual: " . round($actualRate) . "/hr, Ideal: {$idealRate}/hr.",
                'data' => ['performance' => round($performancePct, 1), 'actual_rate' => round($actualRate), 'ideal_rate' => $idealRate],
            ];
        }

        return null;
    }

    /**
     * Create an alert and notify admin users.
     */
    private function createAlert(AlertRule $rule, Machine $machine, array $alertData): void
    {
        $alert = Alert::create([
            'alert_rule_id' => $rule->id,
            'machine_id' => $machine->id,
            'severity' => $rule->severity,
            'title' => $alertData['title'],
            'message' => $alertData['message'],
            'data' => $alertData['data'] ?? null,
            'triggered_at' => now(),
        ]);

        Log::info("Andon Alert triggered: {$alertData['title']} on {$machine->name}");

        // Create in-app notifications for admin users
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'andon_alert',
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'data' => [
                    'alert_id' => $alert->id,
                    'machine_id' => $machine->id,
                    'severity' => $rule->severity,
                ],
            ]);
        }
    }

    /**
     * Auto-resolve alerts whose conditions are no longer true.
     */
    private function autoResolveAlerts(): void
    {
        $activeAlerts = Alert::active()->with(['alertRule', 'machine'])->get();

        foreach ($activeAlerts as $alert) {
            $shouldResolve = false;

            $shift = ProductionShift::where('machine_id', $alert->machine_id)
                ->where('status', 'active')
                ->first();

            // If no active shift, resolve machine-stopped and performance alerts
            if (!$shift) {
                $shouldResolve = true;
            } else {
                $shouldResolve = match ($alert->alertRule?->type) {
                    'machine_stopped', 'excessive_downtime' => $this->isDowntimeResolved($shift),
                    'oee_below_target' => $this->isOeeRecovered($alert, $shift),
                    'quality_drop' => $this->isQualityRecovered($alert, $shift),
                    default => false,
                };
            }

            if ($shouldResolve) {
                $alert->resolve();
                Log::info("Andon Alert auto-resolved: {$alert->title}");
            }
        }
    }

    private function isDowntimeResolved(ProductionShift $shift): bool
    {
        return !DowntimeEvent::where('production_shift_id', $shift->id)
            ->whereNull('end_time')
            ->exists();
    }

    private function isOeeRecovered(Alert $alert, ProductionShift $shift): bool
    {
        $oee = $this->calculateQuickOee($shift, $shift->machine);
        $threshold = $alert->alertRule?->threshold ?? 0;
        return $oee !== null && $oee >= $threshold + 5; // Need 5% buffer to avoid flapping
    }

    private function isQualityRecovered(Alert $alert, ProductionShift $shift): bool
    {
        $totalCount = $shift->total_count ?? 0;
        $rejectCount = $shift->reject_count ?? 0;
        if ($totalCount < 10) return false;

        $rejectRate = ($rejectCount / $totalCount) * 100;
        $threshold = $alert->alertRule?->threshold ?? 0;
        return $rejectRate <= $threshold - 1; // Need 1% buffer
    }

    private function calculateQuickOee(ProductionShift $shift, ?Machine $machine): ?float
    {
        $totalMinutes = max(1, $shift->started_at->diffInMinutes(now()));

        $downtimeMinutes = DowntimeEvent::where('production_shift_id', $shift->id)
            ->selectRaw("SUM(CASE WHEN end_time IS NOT NULL THEN (strftime('%s', end_time) - strftime('%s', start_time)) / 60 ELSE (strftime('%s', 'now') - strftime('%s', start_time)) / 60 END) as total")
            ->value('total') ?? 0;

        $runTime = max(1, $totalMinutes - $downtimeMinutes);
        $availability = ($totalMinutes - $downtimeMinutes) / $totalMinutes;

        $totalCount = $shift->total_count ?? 0;
        $idealRate = $this->getIdealRate($shift->machine_id, $shift->product_id);
        $idealCount = ($idealRate / 60) * $runTime;
        $performance = $idealCount > 0 ? min(1, $totalCount / $idealCount) : 0;

        $goodCount = $shift->good_count ?? 0;
        $quality = $totalCount > 0 ? $goodCount / $totalCount : 1;

        return round($availability * $performance * $quality * 100, 1);
    }

    private function getIdealRate(?int $machineId, ?int $productId): float
    {
        if (!$machineId) return 100;

        if ($productId) {
            $config = MachineProductConfig::where('machine_id', $machineId)
                ->where('product_id', $productId)
                ->first();
            if ($config && $config->ideal_rate > 0) {
                return $config->ideal_rate;
            }
        }

        $machine = Machine::find($machineId);
        return $machine?->default_ideal_rate ?? 100;
    }
}
