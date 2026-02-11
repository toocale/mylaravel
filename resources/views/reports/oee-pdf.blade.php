<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OEE Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
        }
        .header-logo {
            max-width: 150px;
            max-height: 60px;
            margin-bottom: 15px;
        }
        .header-site-name {
            font-size: 14px;
            color: #1f2937;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header h1 {
            color: #3b82f6;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .summary {
            padding: 0;
            margin-bottom: 30px;
        }
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        .summary-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px 15px;
            text-align: center;
            position: relative;
            width: 24%;
            display: inline-block;
            vertical-align: top;
            margin-right: 1%;
        }
        .summary-card:last-child {
            margin-right: 0;
        }
        .summary-card-oee {
            border-left: 6px solid #3b82f6;
            background: #eff6ff;
        }
        .summary-card-availability {
            border-left: 6px solid #10b981;
            background: #ecfdf5;
        }
        .summary-card-performance {
            border-left: 6px solid #f59e0b;
            background: #fffbeb;
        }
        .summary-card-quality {
            border-left: 6px solid #8b5cf6;
            background: #f5f3ff;
        }
        .card-header {
            margin-bottom: 12px;
        }
        .card-icon {
            display: none;
        }
        .card-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .card-value {
            font-size: 36px;
            font-weight: bold;
            margin: 12px 0;
            line-height: 1;
        }
        .summary-card-oee .card-value {
            color: #3b82f6;
        }
        .summary-card-availability .card-value {
            color: #10b981;
        }
        .summary-card-performance .card-value {
            color: #f59e0b;
        }
        .summary-card-quality .card-value {
            color: #8b5cf6;
        }
        .card-footer {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 10px;
            line-height: 1.3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #3b82f6;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-warning {
            background: #f59e0b;
            color: white;
        }
        .badge-danger {
            background: #ef4444;
            color: white;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($site_logo && file_exists($site_logo))
            <img src="{{ $site_logo }}" alt="{{ $site_name }} Logo" class="header-logo">
        @endif
        <div class="header-site-name">{{ $site_name }}</div>
        <h1>OEE Performance Report</h1>
        <p>Generated on: {{ $generated_at }}</p>
        <p>Period: {{ $date_from }} to {{ $date_to }}</p>
    </div>

    <div class="summary">
        <h3 style="margin-top: 0; margin-bottom: 20px; color: #1f2937;">Summary Statistics</h3>
        <div class="summary-grid">
            <div class="summary-card summary-card-oee">
                <div class="card-header">
                    <div class="card-icon">📊</div>
                    <div class="card-label">AVG OEE</div>
                </div>
                <div class="card-value">{{ number_format($metrics->avg('oee_score'), 1) }}%</div>
                <div class="card-footer">Overall Equipment Effectiveness</div>
            </div>
            
            <div class="summary-card summary-card-availability">
                <div class="card-header">
                    <div class="card-icon">🔧</div>
                    <div class="card-label">AVAILABILITY</div>
                </div>
                <div class="card-value">{{ number_format($metrics->avg('availability_score'), 1) }}%</div>
                <div class="card-footer">Uptime vs Planned Time</div>
            </div>
            
            <div class="summary-card summary-card-performance">
                <div class="card-header">
                    <div class="card-icon">⚡</div>
                    <div class="card-label">PERFORMANCE</div>
                </div>
                <div class="card-value">{{ number_format($metrics->avg('performance_score'), 1) }}%</div>
                <div class="card-footer">Speed vs Ideal Rate</div>
            </div>
            
            <div class="summary-card summary-card-quality">
                <div class="card-header">
                    <div class="card-icon">✓</div>
                    <div class="card-label">QUALITY</div>
                </div>
                <div class="card-value">{{ number_format($metrics->avg('quality_score'), 1) }}%</div>
                <div class="card-footer">Good Units vs Total</div>
            </div>
        </div>
    </div>

    <h3>Daily Metrics</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Machine</th>
                <th>Line</th>
                <th>Plant</th>
                <th style="text-align: center">OEE %</th>
                <th style="text-align: center">A %</th>
                <th style="text-align: center">P %</th>
                <th style="text-align: center">Q %</th>
                <th style="text-align: right">Units</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics as $metric)
            <tr>
                <td>{{ $metric->date }}</td>
                <td>{{ $metric->machine->name }}</td>
                <td>{{ $metric->machine->line->name }}</td>
                <td>{{ $metric->machine->line->plant->name }}</td>
                <td style="text-align: center">
                    <span class="badge {{ $metric->oee >= 85 ? 'badge-success' : ($metric->oee >= 60 ? 'badge-warning' : 'badge-danger') }}">
                        {{ number_format($metric->oee, 1) }}%
                    </span>
                </td>
                <td style="text-align: center">{{ number_format($metric->availability, 1) }}</td>
                <td style="text-align: center">{{ number_format($metric->performance, 1) }}</td>
                <td style="text-align: center">{{ number_format($metric->quality, 1) }}</td>
                <td style="text-align: right">{{ number_format($metric->total_good) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This report was generated automatically by {{ $site_name }} | © {{ date('Y') }}</p>
    </div>
</body>
</html>
