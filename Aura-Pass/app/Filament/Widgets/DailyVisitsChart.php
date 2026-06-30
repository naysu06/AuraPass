<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;

// Required Traits for Forms & Actions
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class DailyVisitsChart extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $heading = 'Visits Comparison';

    public ?string $customStartDate = null;
    public ?string $customEndDate = null;

    // 1. OVERRIDE RENDER: Use our new custom wrapper view
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.widgets.chart-with-modals', [
            'chart' => parent::render(),
        ]);
    }

    // 2. THE DROPDOWN OPTIONS
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Weekly',
            '30' => 'Monthly',
            'custom' => 'Custom Range',
        ];
    }

    // 3. THE TRIGGER: Catch the dropdown change and open the modal
    public function updatedFilter($value): void
    {
        if ($value === 'custom') {
            $this->mountAction('customRange'); // Triggers the action below
        } else {
            // Reset dates if they click back to Weekly/Monthly
            $this->customStartDate = null;
            $this->customEndDate = null;
        }
    }

    // 4. THE ACTION (MODAL): Defined natively for Livewire
    public function customRangeAction(): Action
    {
        return Action::make('customRange')
            ->modalHeading('Select Custom Date Range')
            ->modalWidth('md')
            ->form([
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->default(now()->subDays(6)),
                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->default(now()),
            ])
            ->action(function (array $data): void {
                // Save the dates to update the chart
                $this->customStartDate = $data['start_date'];
                $this->customEndDate = $data['end_date'];
            });
    }

    // --- EVERYTHING BELOW HERE IS YOUR EXACT EXISTING LOGIC ---

    protected function getDateRange(): array
    {
        $activeFilter = $this->filter ?? '7';

        if ($activeFilter === 'custom' && $this->customStartDate && $this->customEndDate) {
            $start = Carbon::parse($this->customStartDate)->startOfDay();
            $end = Carbon::parse($this->customEndDate)->endOfDay();
            $days = (int) $start->diffInDays($end) + 1;
            $periodLabel = "{$days} days";
        } else {
            $days = (int) ($activeFilter === 'custom' ? 7 : $activeFilter);
            $start = now()->subDays($days - 1)->startOfDay();
            $end = now()->endOfDay();
            $periodLabel = $days === 7 ? 'week' : 'month';
        }

        $previousStart = $start->copy()->subDays($days);
        $previousEnd = $start->copy()->subSeconds(1);

        return [$start, $end, $previousStart, $previousEnd, $days, $periodLabel];
    }

    public function getDescription(): string|HtmlString|null
    {
        [$start, $end, $previousStart, $previousEnd, $days, $periodLabel] = $this->getDateRange();

        $currentCount = CheckIn::whereBetween('created_at', [$start, $end])->count();
        $previousCount = CheckIn::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        $growth = 0;
        if ($previousCount > 0) {
            $growth = (($currentCount - $previousCount) / $previousCount) * 100;
        } elseif ($currentCount > 0) {
            $growth = 100; 
        }

        $formattedGrowth = number_format(abs($growth), 1) . '%';
        $color = $growth >= 0 ? 'text-success-500' : 'text-danger-500'; 
        $word = $growth >= 0 ? 'Increase' : 'Decrease';

        return new HtmlString("
            <div class='flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400'>
                <span>Total: <span class='font-bold'>{$currentCount}</span> check-ins</span>
                <span class='{$color} font-bold flex items-center'>
                    ({$formattedGrowth} {$word})
                </span>
                <span class='text-xs text-gray-400'>vs previous {$periodLabel}</span>
            </div>
        ");
    }

    protected function getData(): array
    {
        [$start, $end, $previousStart, $previousEnd, $days, $periodLabel] = $this->getDateRange();
        
        $legendLabel = $periodLabel === 'week' ? 'Week' : ($periodLabel === 'month' ? 'Month' : $periodLabel);

        $currentData = CheckIn::select('created_at')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(fn ($date) => Carbon::parse($date->created_at)->setTimezone('Asia/Manila')->format('Y-m-d'));

        $previousData = CheckIn::select('created_at')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->get()
            ->groupBy(fn ($date) => Carbon::parse($date->created_at)->setTimezone('Asia/Manila')->format('Y-m-d'));

        $currentCounts = [];
        $previousCounts = [];
        $labels = [];

        for ($i = 0; $i < $days; $i++) {
            $dateCurrent = $start->copy()->addDays($i);
            $datePrevious = $previousStart->copy()->addDays($i);

            $cKey = $dateCurrent->format('Y-m-d');
            $pKey = $datePrevious->format('Y-m-d');

            $currentCounts[] = isset($currentData[$cKey]) ? $currentData[$cKey]->count() : 0;
            $previousCounts[] = isset($previousData[$pKey]) ? $previousData[$pKey]->count() : 0;

            $labels[] = $dateCurrent->format('M d'); 
        }

        return [
            'datasets' => [
                [
                    'label' => 'Selected Period',
                    'data' => $currentCounts,
                    'backgroundColor' => '#3B82F6', 
                    'borderColor' => '#3B82F6',
                    'barPercentage' => 0.7,
                ],
                [
                    'label' => "Prior {$legendLabel}", 
                    'data' => $previousCounts,
                    'backgroundColor' => '#9CA3AF', 
                    'borderColor' => '#9CA3AF',
                    'barPercentage' => 0.7,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'interaction' => [
                'intersect' => false,
                'mode' => 'index', 
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => true, 'borderDash' => [2, 2]],
                    'ticks' => ['stepSize' => 1],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom', 
                ],
            ],
        ];
    }
}