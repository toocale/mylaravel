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
        .message-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
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
            <h1>New Message on Ticket</h1>
        </div>
        
        <div class="content">
            <p>Hello {{ $recipient->name }},</p>
            
            <p>A new message was added to ticket <strong>#{{ $ticket->id }}: {{ $ticket->subject }}</strong></p>
            
            <div class="message-box">
                <p><strong>From:</strong> {{ $message->user->name }}</p>
                <p><strong>Date:</strong> {{ $message->created_at->format('M d, Y h:i A') }}</p>
                
                <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                
                <p>{{ $message->message }}</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{ url('/tickets/' . $ticket->id) }}" class="btn">View Ticket & Reply</a>
            </p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>You received this email because you're involved in this ticket.</p>
        </div>
    </div>
</body>
</html>
