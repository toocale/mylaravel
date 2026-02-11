<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a2e;
            background-color: #f8fafc;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 12px;
        }
        .badge-day {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .badge-night {
            background: rgba(99, 102, 241, 0.3);
            color: white;
        }
        .content {
            padding: 24px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-value.green { color: #10b981; }
        .stat-value.red { color: #ef4444; }
        .stat-value.amber { color: #f59e0b; }
        .stat-value.blue { color: #3b82f6; }
        .stat-value.orange { color: #f97316; }
        .stat-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .machine-info {
            background: #f0f9ff;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .machine-info h3 {
            color: #0369a1;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .machine-info p {
            color: #0284c7;
            font-size: 14px;
        }
        .downtime-list {
            list-style: none;
        }
        .downtime-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-radius: 8px;
            background: #fef3c7;
            margin-bottom: 8px;
        }
        .downtime-info {
            display: flex;
            flex-direction: column;
        }
        .downtime-reason {
            font-weight: 600;
            color: #92400e;
        }
        .downtime-cat {
            font-size: 10px;
            text-transform: uppercase;
            color: #92400e;
            opacity: 0.8;
            margin-top: 2px;
        }
        .downtime-duration {
            font-size: 14px;
            color: #b45309;
            font-family: monospace;
        }
        .footer {
            text-align: center;
            padding: 24px;
            color: #6b7280;
            font-size: 12px;
        }
        .no-data {
            text-align: center;
            padding: 32px;
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Shift Report</h1>
                @if($reportData['shift'])
                    <div class="subtitle">{{ $reportData['shift']['name'] }} &bull; {{ $reportData['shift']['date'] }}</div>
                    <span class="badge {{ $reportData['shift']['type'] === 'night' ? 'badge-night' : 'badge-day' }}">
                        {{ ucfirst($reportData['shift']['type']) }} Shift
                    </span>
                @else
                    <div class="subtitle">{{ $reportDate }}</div>
                @endif
            </div>

            <div class="content">
                @if($reportData['machine'])
                    <div class="machine-info">
                        <h3>{{ $reportData['machine']['name'] }}</h3>
                        <p>{{ $reportData['machine']['line'] }} &rarr; {{ $reportData['machine']['plant'] }}</p>
                    </div>
                @endif

                <div class="section-title">Production Summary</div>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value blue">{{ number_format($reportData['production']['target'] ?? 0) }}</div>
                        <div class="stat-label">
                            Target
                            @if(($reportData['production']['ideal_rate'] ?? 0) > 0)
                                <div style="font-size: 9px; opacity: 0.7;">
                                    @ {{ $reportData['production']['ideal_rate'] }}/hr
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value blue">{{ number_format($reportData['production']['total']) }}</div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value green">{{ number_format($reportData['production']['good']) }}</div>
                        <div class="stat-label">Good</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value red">{{ number_format($reportData['production']['reject']) }}</div>
                        <div class="stat-label">Reject</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value amber">
                            @php
                                $hours = floor($reportData['downtime']['total_seconds'] / 3600);
                                $mins = floor(($reportData['downtime']['total_seconds'] % 3600) / 60);
                            @endphp
                            {{ $hours }}h {{ $mins }}m
                        </div>
                        <div class="stat-label">Downtime</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value blue">
                            @php
                                $ph = floor(($reportData['downtime']['planned_seconds'] ?? 0) / 3600);
                                $pm = floor((($reportData['downtime']['planned_seconds'] ?? 0) % 3600) / 60);
                            @endphp
                            {{ $ph }}h {{ $pm }}m
                        </div>
                        <div class="stat-label">Planned</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value orange">
                            @php
                                $uh = floor(($reportData['downtime']['unplanned_seconds'] ?? 0) / 3600);
                                $um = floor((($reportData['downtime']['unplanned_seconds'] ?? 0) % 3600) / 60);
                            @endphp
                            {{ $uh }}h {{ $um }}m
                        </div>
                        <div class="stat-label">Unplanned</div>
                    </div>
                </div>

                @if(count($reportData['downtime']['events']) > 0)
                    <div class="section-title">Downtime Events ({{ $reportData['downtime']['count'] }})</div>
                    <ul class="downtime-list">
                        @foreach($reportData['downtime']['events'] as $event)
                            <li class="downtime-item">
                                <div class="downtime-info">
                                    <span class="downtime-reason">{{ $event['reason'] }}</span>
                                    <span class="downtime-cat">{{ $event['category'] ?? 'Unplanned' }}</span>
                                </div>
                                <span class="downtime-duration">
                                    @php
                                        $h = floor($event['duration'] / 3600);
                                        $m = floor(($event['duration'] % 3600) / 60);
                                    @endphp
                                    {{ $h }}h {{ $m }}m
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="no-data">No downtime events recorded.</div>
                @endif
            </div>

            <div class="footer">
                <p>This report was automatically generated by Vicoee.</p>
                <p>&copy; {{ date('Y') }} Vicoee - Manufacturing Intelligence</p>
            </div>
        </div>
    </div>
</body>
</html>
