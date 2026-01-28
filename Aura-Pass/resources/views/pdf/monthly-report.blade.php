<!DOCTYPE html>
<html>
<head>
    <title>Monthly Member Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 2px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .summary { margin-top: 20px; text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUADS-FURUKAWA GYM</h1>
        <h1>New Registered Member Report</h1>
        <p>Reporting Month: {{ $monthName }} {{ $year }}</p>
        <p>Generated on: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Type</th> <!-- New Column Header -->
                <th>Date Registered</th>
                <th>Membership Expiry</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ ucfirst($member->membership_type) }}</td> <!-- New Column Data -->
                    <td>{{ $member->created_at->format('M d, Y') }}</td>
                    <td>{{ $member->membership_expiry_date->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr>
                    <!-- Updated colspan to 5 to match new column count -->
                    <td colspan="5" style="text-align: center;">No new registrations found for this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        Total New Members: {{ $members->count() }}
    </div>
</body>
</html>