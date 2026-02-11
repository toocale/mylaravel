<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketAssigned;
use App\Mail\TicketMessageReceived;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Ticket::with(['creator', 'assignee', 'plant', 'machine'])
            ->orderBy('created_at', 'desc');
        
        // Filter based on user permissions
        if (!$user->groups()->whereIn('name', ['Admin', 'Supervisor', 'Manager'])->exists()) {
            // Regular users only see their own tickets or tickets assigned to them
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }
        
        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        
        if ($request->has('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }
        
        if ($request->has('line_id')) {
            // Filter by machines in the selected line
            $line = \App\Models\Line::find($request->line_id);
            if ($line) {
                $machineIds = $line->machines->pluck('id');
                $query->whereIn('machine_id', $machineIds);
            }
        }
        
        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }
        
        $tickets = $query->paginate(20);
        $plants = \App\Models\Plant::with('lines.machines')->get();
        
        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'plants' => $plants,
            'filters' => $request->only(['status', 'priority', 'plant_id', 'line_id', 'machine_id']),
        ]);
    }

    /**
     * Show the form for creating a new ticket
     */
    public function create()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $plants = \App\Models\Plant::with('lines.machines')->get();
        
        return Inertia::render('Tickets/Create', [
            'users' => $users,
            'plants' => $plants,
        ]);
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'category' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'plant_id' => 'nullable|exists:plants,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $ticket = Ticket::create([
            'created_by' => Auth::id(),
            'assigned_to' => $validated['assigned_to'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'category' => $validated['category'] ?? null,
            'plant_id' => $validated['plant_id'] ?? null,
            'machine_id' => $validated['machine_id'] ?? null,
            'status' => 'open',
        ]);

        // Create initial message with description
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['description'],
            'is_internal' => false,
        ]);

        // Notify assignee if assigned
        if ($ticket->assigned_to) {
            $assignee = User::find($ticket->assigned_to);
            NotificationService::notifyTicketAssigned($ticket, $assignee);
            
            // Send email
            Mail::to($assignee->email)->send(new TicketAssigned($ticket, $assignee));
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created successfully');
    }

    /**
     * Display the specified ticket
     */
    public function show(Ticket $ticket)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$ticket->canBeEditedBy($user)) {
            abort(403, 'Unauthorized access to this ticket');
        }

        $ticket->load(['creator', 'assignee', 'plant', 'machine', 'messages.user']);
        
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        
        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'users' => $users,
        ]);
    }

    /**
     * Update the specified ticket
     */
    public function update(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        
        if (!$ticket->canBeEditedBy($user)) {
            abort(403, 'Unauthorized to update this ticket');
        }

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        // Restrict closing tickets to only creator or assignee
        if (isset($validated['status']) && $validated['status'] === 'closed') {
            if ($user->id !== $ticket->created_by && $user->id !== $ticket->assigned_to) {
                abort(403, 'Only the ticket creator or assignee can close this ticket.');
            }
        }

        $oldStatus = $ticket->status;
        $oldAssignee = $ticket->assigned_to;
        
        $ticket->update($validated);

        // Handle status changes
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            if ($validated['status'] === 'resolved') {
                $ticket->update(['resolved_at' => now()]);
            } elseif ($validated['status'] === 'closed') {
                $ticket->update(['closed_at' => now()]);
            } elseif (in_array($validated['status'], ['open', 'in_progress'])) {
                $ticket->update([
                    'resolved_at' => null,
                    'closed_at' => null
                ]);
            }
            
            // Notify creator of status change
            NotificationService::notifyTicketStatusChanged(
                $ticket,
                $ticket->creator,
                $oldStatus,
                $validated['status']
            );
        }

        // Handle assignment changes
        if (isset($validated['assigned_to']) && $validated['assigned_to'] !== $oldAssignee) {
            if ($validated['assigned_to']) {
                $assignee = User::find($validated['assigned_to']);
                NotificationService::notifyTicketAssigned($ticket, $assignee);
                Mail::to($assignee->email)->send(new TicketAssigned($ticket, $assignee));
            }
        }

        return back()->with('success', 'Ticket updated successfully');
    }

    /**
     * Add a message to a ticket
     */
    public function addMessage(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        
        if (!$ticket->canBeEditedBy($user)) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        // Notify relevant parties (creator and assignee, but not the message sender)
        $recipients = collect([$ticket->creator, $ticket->assignee])
            ->filter()
            ->filter(fn($u) => $u->id !== $user->id);

        foreach ($recipients as $recipient) {
            NotificationService::notifyTicketMessage($ticket, $recipient, $message);
            
            // Send email
            try {
                Mail::to($recipient->email)->send(new TicketMessageReceived($ticket, $message, $recipient));
            } catch (\Exception $e) {
                \Log::error('Failed to send ticket message email: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Message added successfully');
    }

    /**
     * Delete a ticket
     */
    public function destroy(Ticket $ticket)
    {
        $user = Auth::user();
        
        // Only admins can delete tickets
        if (!$user->groups()->whereIn('name', ['Admin'])->exists()) {
            abort(403, 'Only administrators can delete tickets');
        }

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted successfully');
    }
}
