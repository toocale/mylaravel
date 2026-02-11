<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OEE Report</title>
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #ffffff;
            padding: 30px 20px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .summary-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .summary-value {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
        }
        .btn {
            display: inline-block;
            background: #3b82f6;
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
        @media only screen and (max-width: 600px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 OEE Performance Report</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">{{ $dateFrom }} to {{ $dateTo }}</p>
    </div>
    
    <div class="content">
        <p>Hello,</p>
        
        <p>Your OEE Performance Report is ready. Please find the detailed PDF report attached to this email.</p>
        
        @if(isset($reportData['metrics']) && $reportData['metrics']->count() > 0)
        <h3 style="color: #1f2937; margin-top: 25px;">Report Summary</h3>
        
        <div class="summary-grid">
            <div class="summary-card" style="border-left-color: #3b82f6;">
                <div class="summary-label">Avg OEE</div>
                <div class="summary-value" style="color: #3b82f6;">
                    {{ number_format($reportData['metrics']->avg('oee'), 1) }}%
                </div>
            </div>
            
            <div class="summary-card" style="border-left-color: #10b981;">
                <div class="summary-label">Availability</div>
                <div class="summary-value" style="color: #10b981;">
                    {{ number_format($reportData['metrics']->avg('availability'), 1) }}%
                </div>
            </div>
            
            <div class="summary-card" style="border-left-color: #f59e0b;">
                <div class="summary-label">Performance</div>
                <div class="summary-value" style="color: #f59e0b;">
                    {{ number_format($reportData['metrics']->avg('performance'), 1) }}%
                </div>
            </div>
            
            <div class="summary-card" style="border-left-color: #8b5cf6;">
                <div class="summary-label">Quality</div>
                <div class="summary-value" style="color: #8b5cf6;">
                    {{ number_format($reportData['metrics']->avg('quality'), 1) }}%
                </div>
            </div>
        </div>
        
        <p style="margin-top: 25px;">
            <strong>Total Days:</strong> {{ $reportData['metrics']->count() }}<br>
            <strong>Total Units Produced:</strong> {{ number_format($reportData['metrics']->sum('good_count')) }}
        </p>
        @endif
        
        <p style="margin-top: 25px;">The complete report with detailed metrics, charts, and analysis is attached as a PDF file.</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from {{ config('app.name', 'OEE System') }}</p>
        <p style="margin-top: 5px;">© {{ date('Y') }} All rights reserved.</p>
    </div>
</body>
</html>
