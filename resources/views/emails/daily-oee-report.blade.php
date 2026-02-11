<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily OEE Report</title>
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
            background: linear-gradient(135deg, #0ea5e9 0%, #2dd4bf 100%);
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
        .header .scope {
            margin-top: 12px;
            font-size: 16px;
            font-weight: 600;
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
        .oee-main {
            text-align: center;
            padding: 24px;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .oee-value {
            font-size: 64px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }
        .oee-value.excellent { color: #10b981; }
        .oee-value.good { color: #22c55e; }
        .oee-value.average { color: #f59e0b; }
        .oee-value.poor { color: #ef4444; }
        .oee-label {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .target-indicator {
            margin-top: 12px;
            font-size: 13px;
            color: #9ca3af;
        }
        .target-indicator strong {
            color: #10b981;
        }
        .metrics-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .metric-box {
            flex: 1;
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .metric-value.availability { color: #3b82f6; }
        .metric-value.performance { color: #8b5cf6; }
        .metric-value.quality { color: #06b6d4; }
        .metric-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .production-summary {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .prod-box {
            flex: 1;
            padding: 16px;
            border-radius: 10px;
            text-align: center;
        }
        .prod-box.total { background: #eff6ff; }
        .prod-box.good { background: #f0fdf4; }
        .prod-box.reject { background: #fef2f2; }
        .prod-value {
            font-size: 24px;
            font-weight: 700;
        }
        .prod-box.total .prod-value { color: #2563eb; }
        .prod-box.good .prod-value { color: #16a34a; }
        .prod-box.reject .prod-value { color: #dc2626; }
        .prod-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        .breakdown-table th,
        .breakdown-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .breakdown-table th {
            background: #f8fafc;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .breakdown-table td {
            font-size: 14px;
        }
        .machine-name {
            font-weight: 600;
            color: #1e40af;
        }
        .score {
            font-family: monospace;
            font-weight: 600;
        }
        .score.high { color: #16a34a; }
        .score.medium { color: #d97706; }
        .score.low { color: #dc2626; }
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
                <h1>Daily OEE Report</h1>
                <div class="subtitle">{{ $reportDate }}</div>
                @if($plantName)
                    <div class="scope">{{ $plantName }}</div>
                @endif
            </div>

            <div class="content">
                <div class="oee-main">
                    @php
                        $oee = $reportData['overview']['oee'];
                        $oeeClass = $oee >= 85 ? 'excellent' : ($oee >= 70 ? 'good' : ($oee >= 50 ? 'average' : 'poor'));
                    @endphp
                    <div class="oee-value {{ $oeeClass }}">{{ number_format($oee, 1) }}%</div>
                    <div class="oee-label">Overall Equipment Effectiveness</div>
                    <div class="target-indicator">
                        Target: <strong>{{ $reportData['target'] ?? 85 }}%</strong>
                        @if($oee >= ($reportData['target'] ?? 85))
                            ✓ On Target
                        @else
                            ↓ {{ number_format(($reportData['target'] ?? 85) - $oee, 1) }}% below target
                        @endif
                    </div>
                </div>

                <div class="section-title">Performance Metrics</div>
                <div class="metrics-row">
                    <div class="metric-box">
                        <div class="metric-value availability">{{ number_format($reportData['overview']['availability'], 1) }}%</div>
                        <div class="metric-label">Availability</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value performance">{{ number_format($reportData['overview']['performance'], 1) }}%</div>
                        <div class="metric-label">Performance</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value quality">{{ number_format($reportData['overview']['quality'], 1) }}%</div>
                        <div class="metric-label">Quality</div>
                    </div>
                </div>

                <div class="section-title">Production Summary</div>
                <div class="production-summary">
                    <div class="prod-box total">
                        <div class="prod-value">{{ number_format($reportData['production']['total']) }}</div>
                        <div class="prod-label">Total Output</div>
                    </div>
                    <div class="prod-box good">
                        <div class="prod-value">{{ number_format($reportData['production']['good']) }}</div>
                        <div class="prod-label">Good Units</div>
                    </div>
                    <div class="prod-box reject">
                        <div class="prod-value">{{ number_format($reportData['production']['reject']) }}</div>
                        <div class="prod-label">Rejects</div>
                    </div>
                </div>

                @if(count($reportData['breakdown'] ?? []) > 0)
                    <div class="section-title">Machine Breakdown</div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>OEE</th>
                                <th>Avail.</th>
                                <th>Perf.</th>
                                <th>Quality</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['breakdown'] as $machine)
                                @php
                                    $machineOee = $machine['oee'];
                                    $scoreClass = $machineOee >= 85 ? 'high' : ($machineOee >= 60 ? 'medium' : 'low');
                                @endphp
                                <tr>
                                    <td class="machine-name">{{ $machine['machine'] }}</td>
                                    <td class="score {{ $scoreClass }}">{{ number_format($machine['oee'], 1) }}%</td>
                                    <td class="score">{{ number_format($machine['availability'], 1) }}%</td>
                                    <td class="score">{{ number_format($machine['performance'], 1) }}%</td>
                                    <td class="score">{{ number_format($machine['quality'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="no-data">No machine breakdown data available.</div>
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
