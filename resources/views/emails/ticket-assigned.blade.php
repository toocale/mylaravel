<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .ticket-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4F46E5;
        }
        .priority {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .priority-urgent { background-color: #fee2e2; color: #991b1b; }
        .priority-high { background-color: #fed7aa; color: #9a3412; }
        .priority-medium { background-color: #fef3c7; color: #92400e; }
        .priority-low { background-color: #dbeafe; color: #1e40af; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ticket Assigned</h1>
        </div>
        
        <div class="content">
            <p>Hello {{ $assignee->name }},</p>
            
            <p>You have been assigned a new ticket:</p>
            
            <div class="ticket-info">
                <h2 style="margin-top: 0;">{{ $ticket->subject }}</h2>
                <p><strong>Priority:</strong> <span class="priority priority-{{ $ticket->priority }}">{{ ucfirst($ticket->priority) }}</span></p>
                <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
                <p><strong>Category:</strong> {{ $ticket->category ?? 'N/A' }}</p>
                @if($ticket->plant)
                    <p><strong>Plant:</strong> {{ $ticket->plant->name }}</p>
                @endif
                @if($ticket->machine)
                    <p><strong>Machine:</strong> {{ $ticket->machine->name }}</p>
                @endif
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
                
                <p><strong>Description:</strong></p>
                <p>{{ $ticket->description }}</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{ url('/tickets/' . $ticket->id) }}" class="btn">View Ticket</a>
            </p>
            
            <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
                This ticket was created by {{ $ticket->creator->name }} and requires your attention.
            </p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>You received this email because you were assigned to a ticket.</p>
        </div>
    </div>
</body>
</html>
