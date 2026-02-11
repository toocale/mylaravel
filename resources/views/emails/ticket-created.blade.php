<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Ticket Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px 20px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .ticket-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
        }
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .priority-critical { background: #fee2e2; color: #991b1b; }
        .priority-high { background: #ffedd5; color: #9a3412; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-low { background: #dbeafe; color: #1e40af; }
        .btn {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎫 New Ticket Created</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Ticket #{{ $ticket->id }}</p>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>A new support ticket has been created and requires attention.</p>
        
        <div class="ticket-info">
            <div class="info-row">
                <span class="info-label">Subject:</span>
                <span>{{ $ticket->subject }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Priority:</span>
                <span class="priority-badge priority-{{ $ticket->priority }}">
                    {{ strtoupper($ticket->priority) }}
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span>{{ ucfirst($ticket->category) }}</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Created By:</span>
                <span>{{ $ticket->creator->name }}</span>
            </div>
            
            @if($ticket->plant)
            <div class="info-row">
                <span class="info-label">Plant:</span>
                <span>{{ $ticket->plant->name }}</span>
            </div>
            @endif
            
            @if($ticket->machine)
            <div class="info-row">
                <span class="info-label">Machine:</span>
                <span>{{ $ticket->machine->name }}</span>
            </div>
            @endif
            
            @if($ticket->assignee)
            <div class="info-row">
                <span class="info-label">Assigned To:</span>
                <span>{{ $ticket->assignee->name }}</span>
            </div>
            @endif
        </div>
        
        <h4 style="margin-top: 25px; color: #1f2937;">Description:</h4>
        <p style="background: #f9fafb; padding: 15px; border-radius: 6px; white-space: pre-wrap;">{{ $ticket->description }}</p>
        
        <div style="text-align: center;">
            <a href="{{ $ticketUrl }}" class="btn">View Ticket</a>
        </div>
    </div>
    
    <div class="footer">
        <p>This is an automated notification from {{ config('app.name', 'OEE System') }}</p>
        <p style="margin-top: 5px;">© {{ date('Y') }} All rights reserved.</p>
    </div>
</body>
</html>
