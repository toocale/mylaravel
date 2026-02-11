<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $type;
    protected $tasks;
    protected $machine;

    /**
     * Create a new notification instance.
     *
     * @param string $type 'overdue', 'upcoming', or 'low_stock'
     * @param array $tasks The maintenance tasks or parts
     * @param array|null $machine Machine info if machine-specific
     */
    public function __construct(string $type, array $tasks, ?array $machine = null)
    {
        $this->type = $type;
        $this->tasks = $tasks;
        $this->machine = $machine;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->getSubject();
        $greeting = $this->getGreeting();
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);

        if ($this->type === 'overdue') {
            $mail->line('The following maintenance tasks are overdue and require immediate attention:');
            foreach ($this->tasks as $task) {
                $mail->line("• **{$task['task_name']}** - Due: {$task['next_due_at']} (Priority: {$task['priority']})");
            }
            $mail->line('Please complete these tasks as soon as possible to maintain optimal equipment performance.');
        } elseif ($this->type === 'upcoming') {
            $mail->line('The following maintenance tasks are due within the next 7 days:');
            foreach ($this->tasks as $task) {
                $mail->line("• **{$task['task_name']}** - Due: {$task['next_due_at']}");
            }
            $mail->line('Please schedule time to complete these tasks before they become overdue.');
        } elseif ($this->type === 'low_stock') {
            $mail->line('The following spare parts are running low on stock:');
            foreach ($this->tasks as $part) {
                $mail->line("• **{$part['name']}** ({$part['part_number']}) - Stock: {$part['quantity_in_stock']}, Min: {$part['minimum_stock_level']}");
            }
            $mail->line('Please reorder these parts to avoid maintenance delays.');
        }

        if ($this->machine) {
            $mail->line("Machine: {$this->machine['name']}");
        }

        $mail->action('View Maintenance Dashboard', url('/dashboard'))
            ->line('Thank you for keeping our equipment running smoothly!');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maintenance_reminder',
            'reminder_type' => $this->type,
            'tasks' => $this->tasks,
            'machine' => $this->machine,
            'count' => count($this->tasks),
            'message' => $this->getMessage(),
        ];
    }

    /**
     * Get notification subject based on type.
     */
    protected function getSubject(): string
    {
        $siteName = config('app.name', 'OEE System');
        
        return match($this->type) {
            'overdue' => "[{$siteName}] ⚠️ Overdue Maintenance Tasks",
            'upcoming' => "[{$siteName}] 📅 Upcoming Maintenance Reminder",
            'low_stock' => "[{$siteName}] 📦 Low Stock Alert - Spare Parts",
            default => "[{$siteName}] Maintenance Notification",
        };
    }

    /**
     * Get greeting based on type.
     */
    protected function getGreeting(): string
    {
        return match($this->type) {
            'overdue' => 'Attention Required!',
            'upcoming' => 'Upcoming Maintenance',
            'low_stock' => 'Stock Alert',
            default => 'Hello!',
        };
    }

    /**
     * Get short message for in-app notification.
     */
    protected function getMessage(): string
    {
        $count = count($this->tasks);
        
        return match($this->type) {
            'overdue' => "{$count} maintenance task(s) are overdue",
            'upcoming' => "{$count} maintenance task(s) due this week",
            'low_stock' => "{$count} spare part(s) are low on stock",
            default => "You have {$count} maintenance notification(s)",
        };
    }
}
