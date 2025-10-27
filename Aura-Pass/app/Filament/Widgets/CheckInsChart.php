<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\CheckIn;
use Illuminate\Support\Facades\DB;

class CheckInsChart extends ChartWidget
{
    protected static ?string $heading = 'Check-ins (Last 14 Days)';

    protected function getData(): array
    {
        $data = CheckIn::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Check-ins',
                    'data' => $data->pluck('count'),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#9BD0F5',
                ],
            ],
            'labels' => $data->pluck('date')->map(fn ($date) => date('M d', strtotime($date))),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
