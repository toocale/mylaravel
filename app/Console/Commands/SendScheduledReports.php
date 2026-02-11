<?php

namespace App\Console\Commands;

use App\Mail\DailyOeeReportMail;
use App\Mail\ShiftReportMail;
use App\Models\ReportSchedule;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:send-scheduled {--force : Force send all active schedules regardless of time}';

    /**
     * The console command description.
     */
    protected $description = 'Send scheduled email reports based on their configured frequency and time.';

    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i');
        $currentDayOfWeek = $now->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        $currentDayOfMonth = $now->day;
        
        $this->info("Checking for scheduled reports at {$currentTime}...");

        $query = ReportSchedule::active()
            ->with(['plant', 'line', 'machine']);

        if (!$this->option('force')) {
            // Filter by send time (within 5-minute window)
            $query->where(function ($q) use ($now) {
                $startWindow = $now->copy()->subMinutes(2)->format('H:i:s');
                $endWindow = $now->copy()->addMinutes(2)->format('H:i:s');
                $q->whereBetween('send_time', [$startWindow, $endWindow]);
            });
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules to process at this time.');
            return Command::SUCCESS;
        }

        $sentCount = 0;
        $errorCount = 0;

        foreach ($schedules as $schedule) {
            // Check frequency
            if (!$this->option('force') && !$this->shouldSendToday($schedule, $currentDayOfWeek, $currentDayOfMonth)) {
                continue;
            }

            try {
                $this->sendReport($schedule);
                $schedule->update(['last_sent_at' => now()]);
                $sentCount++;
                $this->info("✓ Sent: {$schedule->name}");
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("✗ Failed: {$schedule->name} - {$e->getMessage()}");
                Log::error("Failed to send scheduled report", [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Completed: {$sentCount} sent, {$errorCount} failed.");

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Determine if the schedule should be sent today based on frequency.
     */
    protected function shouldSendToday(ReportSchedule $schedule, int $dayOfWeek, int $dayOfMonth): bool
    {
        return match($schedule->frequency) {
            'daily' => true,
            'weekly' => $dayOfWeek === 1, // Monday
            'monthly' => $dayOfMonth === 1, // First day of month
            'shift_end' => true, // Shift end is handled differently by event listeners
            default => true,
        };
    }

    /**
     * Send the report for a schedule.
     */
    protected function sendReport(ReportSchedule $schedule): void
    {
        $reportData = $this->reportService->generateReport($schedule);
        
        $mail = match($schedule->report_type) {
            'shift' => new ShiftReportMail(
                $reportData,
                $schedule->machine?->name ?? 'All Machines',
                now()->toDateString()
            ),
            default => new DailyOeeReportMail(
                $reportData,
                now()->toDateString(),
                $schedule->plant?->name
            ),
        };

        foreach ($schedule->recipients as $recipient) {
            Mail::to($recipient)->queue($mail);
        }
    }
}
