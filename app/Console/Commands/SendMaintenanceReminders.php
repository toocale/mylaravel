<?php

namespace App\Console\Commands;

use App\Models\MaintenanceSchedule;
use App\Models\SparePart;
use App\Models\User;
use App\Notifications\MaintenanceReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMaintenanceReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'maintenance:send-reminders 
                            {--overdue : Send only overdue reminders}
                            {--upcoming : Send only upcoming reminders}
                            {--low-stock : Send only low stock alerts}
                            {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send maintenance reminder notifications for overdue, upcoming tasks, and low stock parts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting maintenance reminder check...');
        
        $dryRun = $this->option('dry-run');
        $sendAll = !$this->option('overdue') && !$this->option('upcoming') && !$this->option('low-stock');

        if ($sendAll || $this->option('overdue')) {
            $this->sendOverdueReminders($dryRun);
        }

        if ($sendAll || $this->option('upcoming')) {
            $this->sendUpcomingReminders($dryRun);
        }

        if ($sendAll || $this->option('low-stock')) {
            $this->sendLowStockAlerts($dryRun);
        }

        $this->info('Maintenance reminder check completed.');
        
        return Command::SUCCESS;
    }

    /**
     * Send reminders for overdue maintenance tasks.
     */
    protected function sendOverdueReminders(bool $dryRun): void
    {
        $this->info('Checking for overdue maintenance tasks...');

        // Update overdue status first
        $this->updateOverdueStatus();

        // Get overdue tasks grouped by assigned user
        $overdueTasks = MaintenanceSchedule::with(['machine', 'assignedTo'])
            ->overdue()
            ->active()
            ->get()
            ->groupBy('assigned_to_user_id');

        if ($overdueTasks->isEmpty()) {
            $this->line('No overdue tasks found.');
            return;
        }

        $this->info("Found {$overdueTasks->flatten()->count()} overdue tasks.");

        foreach ($overdueTasks as $userId => $tasks) {
            $user = $userId ? User::find($userId) : null;
            
            $tasksArray = $tasks->map(fn($task) => [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'next_due_at' => $task->next_due_at?->format('M d, Y'),
                'priority' => $task->priority,
                'machine_name' => $task->machine?->name,
            ])->toArray();

            if ($user) {
                if ($dryRun) {
                    $this->warn("Would notify {$user->email} about {$tasks->count()} overdue task(s)");
                } else {
                    $user->notify(new MaintenanceReminderNotification('overdue', $tasksArray));
                    $this->line("Notified {$user->email} about {$tasks->count()} overdue task(s)");
                }
            }

            // Also notify admins
            $this->notifyAdmins('overdue', $tasksArray, $dryRun);
        }
    }

    /**
     * Send reminders for upcoming maintenance tasks (next 7 days).
     */
    protected function sendUpcomingReminders(bool $dryRun): void
    {
        $this->info('Checking for upcoming maintenance tasks...');

        $upcomingTasks = MaintenanceSchedule::with(['machine', 'assignedTo'])
            ->upcoming(7)
            ->active()
            ->get()
            ->groupBy('assigned_to_user_id');

        if ($upcomingTasks->isEmpty()) {
            $this->line('No upcoming tasks found.');
            return;
        }

        $this->info("Found {$upcomingTasks->flatten()->count()} upcoming tasks.");

        foreach ($upcomingTasks as $userId => $tasks) {
            $user = $userId ? User::find($userId) : null;
            
            $tasksArray = $tasks->map(fn($task) => [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'next_due_at' => $task->next_due_at?->format('M d, Y'),
                'priority' => $task->priority,
                'machine_name' => $task->machine?->name,
            ])->toArray();

            if ($user) {
                if ($dryRun) {
                    $this->warn("Would notify {$user->email} about {$tasks->count()} upcoming task(s)");
                } else {
                    $user->notify(new MaintenanceReminderNotification('upcoming', $tasksArray));
                    $this->line("Notified {$user->email} about {$tasks->count()} upcoming task(s)");
                }
            }
        }
    }

    /**
     * Send alerts for low stock spare parts.
     */
    protected function sendLowStockAlerts(bool $dryRun): void
    {
        $this->info('Checking for low stock spare parts...');

        $lowStockParts = SparePart::active()
            ->lowStock()
            ->with('machine')
            ->get();

        if ($lowStockParts->isEmpty()) {
            $this->line('No low stock parts found.');
            return;
        }

        $this->info("Found {$lowStockParts->count()} low stock parts.");

        $partsArray = $lowStockParts->map(fn($part) => [
            'id' => $part->id,
            'name' => $part->name,
            'part_number' => $part->part_number,
            'quantity_in_stock' => $part->quantity_in_stock,
            'minimum_stock_level' => $part->minimum_stock_level,
            'machine_name' => $part->machine?->name,
        ])->toArray();

        // Notify admins about low stock
        $this->notifyAdmins('low_stock', $partsArray, $dryRun);
    }

    /**
     * Update overdue status for all schedules.
     */
    protected function updateOverdueStatus(): void
    {
        // Mark past due tasks as overdue
        MaintenanceSchedule::where('next_due_at', '<', now())
            ->where('is_overdue', false)
            ->active()
            ->update(['is_overdue' => true]);

        // Clear overdue flag for future tasks
        MaintenanceSchedule::where('next_due_at', '>=', now())
            ->where('is_overdue', true)
            ->update(['is_overdue' => false]);
    }

    /**
     * Notify all admin users.
     */
    protected function notifyAdmins(string $type, array $items, bool $dryRun): void
    {
        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            if ($dryRun) {
                $this->warn("Would notify admin {$admin->email} about " . count($items) . " {$type} item(s)");
            } else {
                $admin->notify(new MaintenanceReminderNotification($type, $items));
                $this->line("Notified admin {$admin->email}");
            }
        }
    }
}
