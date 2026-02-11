<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Reminder</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            padding: 24px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .header img {
            max-height: 50px;
            margin-bottom: 16px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .alert-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 12px;
        }
        .alert-overdue {
            background: #f87171;
            color: white;
        }
        .alert-upcoming {
            background: #fbbf24;
            color: #92400e;
        }
        .alert-low-stock {
            background: #60a5fa;
            color: white;
        }
        .content {
            padding: 24px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .task-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .task-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .task-name {
            font-weight: 600;
            font-size: 15px;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .task-meta {
            font-size: 13px;
            color: #6b7280;
        }
        .priority-critical { color: #dc2626; }
        .priority-high { color: #ea580c; }
        .priority-medium { color: #ca8a04; }
        .priority-low { color: #16a34a; }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin-top: 20px;
        }
        .button:hover {
            opacity: 0.9;
        }
        .footer {
            padding: 20px 24px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                @if($siteLogo)
                    <img src="{{ $message->embed($siteLogo) }}" alt="{{ $siteName }}">
                @endif
                <h1>{{ $siteName }}</h1>
                
                @if($type === 'overdue')
                    <span class="alert-badge alert-overdue">⚠️ Overdue Tasks</span>
                @elseif($type === 'upcoming')
                    <span class="alert-badge alert-upcoming">📅 Upcoming</span>
                @elseif($type === 'low_stock')
                    <span class="alert-badge alert-low-stock">📦 Low Stock</span>
                @endif
            </div>
            
            <div class="content">
                <p class="message">
                    @if($type === 'overdue')
                        The following maintenance tasks are <strong>overdue</strong> and require immediate attention:
                    @elseif($type === 'upcoming')
                        The following maintenance tasks are due within the next <strong>7 days</strong>:
                    @elseif($type === 'low_stock')
                        The following spare parts are <strong>running low</strong> on stock:
                    @endif
                </p>
                
                <ul class="task-list">
                    @foreach($tasks as $task)
                        <li class="task-item">
                            @if($type === 'low_stock')
                                <div class="task-name">{{ $task['name'] }}</div>
                                <div class="task-meta">
                                    Part #: {{ $task['part_number'] }} | 
                                    Stock: <strong>{{ $task['quantity_in_stock'] }}</strong> / Min: {{ $task['minimum_stock_level'] }}
                                    @if(!empty($task['machine_name']))
                                        | Machine: {{ $task['machine_name'] }}
                                    @endif
                                </div>
                            @else
                                <div class="task-name">{{ $task['task_name'] }}</div>
                                <div class="task-meta">
                                    Due: <strong>{{ $task['next_due_at'] }}</strong>
                                    @if(!empty($task['priority']))
                                        | Priority: <span class="priority-{{ $task['priority'] }}">{{ ucfirst($task['priority']) }}</span>
                                    @endif
                                    @if(!empty($task['machine_name']))
                                        | Machine: {{ $task['machine_name'] }}
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                
                <div style="text-align: center;">
                    <a href="{{ url('/dashboard') }}" class="button">View Dashboard</a>
                </div>
            </div>
            
            <div class="footer">
                <p>This is an automated notification from {{ $siteName }}.</p>
                <p>Thank you for keeping our equipment running smoothly!</p>
            </div>
        </div>
    </div>
</body>
</html>
