<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public static function create(User $user, string $type, string $title, string $message, array $data = [])
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Notify user about ticket assignment
     */
    public static function notifyTicketAssigned($ticket, User $assignee)
    {
        return self::create(
            $assignee,
            'ticket_assigned',
            'New Ticket Assigned',
            "You have been assigned ticket #{$ticket->id}: {$ticket->subject}",
            [
                'ticket_id' => $ticket->id,
                'url' => "/tickets/{$ticket->id}",
            ]
        );
    }

    /**
     * Notify user about new ticket message
     */
    public static function notifyTicketMessage($ticket, User $recipient, $message)
    {
        return self::create(
            $recipient,
            'ticket_message',
            'New Message on Ticket',
            "New message on ticket #{$ticket->id}: " . substr($message->message, 0, 50) . '...',
            [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'url' => "/tickets/{$ticket->id}",
            ]
        );
    }

    /**
     * Notify user about ticket status change
     */
    public static function notifyTicketStatusChanged($ticket, User $recipient, $oldStatus, $newStatus)
    {
        return self::create(
            $recipient,
            'ticket_status_changed',
            'Ticket Status Updated',
            "Ticket #{$ticket->id} status changed from {$oldStatus} to {$newStatus}",
            [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'url' => "/tickets/{$ticket->id}",
            ]
        );
    }

    /**
     * Get unread notifications for a user
     */
    public static function getUnread(User $user)
    {
        return Notification::where('user_id', $user->id)
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead(User $user)
    {
        return Notification::where('user_id', $user->id)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);
    }
}
