<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AuraPass KPI Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #f3b23e; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #111; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #666; font-size: 12px; }
        .section-title { background-color: #1f2937; color: #fff; padding: 8px 12px; font-size: 14px; text-transform: uppercase; margin-top: 20px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .metric-name { font-weight: bold; color: #374151; width: 70%; }
        .metric-value { text-align: right; font-weight: bold; color: #111; font-size: 16px; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; background-color: #f3f4f6; color: #374151; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .action-list { font-size: 12px; color: #6b7280; font-family: monospace; display: block; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AuraPass KPI Report</h1>
        <p>{{ $reportTitle }}</p>
        <p>Generated: {{ $date }}</p>
    </div>

    @foreach($sections as $sectionTitle => $metrics)
        <div class="section-title">{{ $sectionTitle }}</div>
        <table>
            @foreach($metrics as $label => $value)
                <tr>
                    <td class="metric-name">{{ $label }}</td>
                    <td class="metric-value">{!! $value !!}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>