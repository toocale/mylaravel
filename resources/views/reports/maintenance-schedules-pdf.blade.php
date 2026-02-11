<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Maintenance Schedules Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .header img {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .header h2 {
            margin: 5px 0;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }
        .meta {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .machine-info {
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #444;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .priority-critical {
            color: #dc2626;
            font-weight: bold;
        }
        .priority-high {
            color: #ea580c;
            font-weight: bold;
        }
        .priority-medium {
            color: #ca8a04;
        }
    </style>
</head>
<body>
    <!-- Header with Logo and Site Name -->
    <div class="header">
        @if($site_logo && file_exists($site_logo))
            <img src="{{ $site_logo }}" alt="Logo">
        @endif
        <h2>{{ $site_name }}</h2>
    </div>

    <h1>Maintenance Schedules Report</h1>
    <div class="meta">Generated: {{ $generated_at }}</div>
    <div class="machine-info">Machine: {{ $machine->name ?? 'N/A' }}</div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 30%">Task</th>
                <th style="width: 15%">Type</th>
                <th style="width: 12%">Priority</th>
                <th style="width: 13%">Frequency</th>
                <th style="width: 15%">Next Due</th>
                <th style="width: 15%">Assigned To</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedules as $schedule)
            <tr>
                <td>{{ $schedule->task_name }}</td>
                <td>{{ ucfirst($schedule->maintenance_type) }}</td>
                <td class="priority-{{ $schedule->priority }}">{{ ucfirst($schedule->priority) }}</td>
                <td>{{ $schedule->frequency_days }} days</td>
                <td>{{ $schedule->next_due_at }}</td>
                <td>{{ $schedule->assignedTo->name ?? 'Unassigned' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
