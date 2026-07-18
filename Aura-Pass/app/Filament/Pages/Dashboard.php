<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BasePage;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Get;
use App\Services\KpiService;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class Dashboard extends BasePage
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportKpiSummary')
                ->label('Export KPI')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->form([
                    Select::make('report_type')
                        ->label('Select Report Type')
                        ->options([
                            'daily' => 'Daily KPI',
                            'weekly' => 'Weekly KPI',
                            'monthly' => 'Monthly KPI',
                            'custom' => 'Promo Custom Date Range',
                        ])
                        ->required()
                        ->live(), // Makes the form reactive
                    
                    // These only show up if 'custom' is selected!
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->default(now()->startOfMonth())
                        ->hidden(fn (Get $get) => $get('report_type') !== 'custom')
                        ->live() 
                        // Cannot be after the End Date, OR if End Date is empty, cannot be after Today
                        ->maxDate(fn (Get $get) => $get('end_date') ?: now()),

                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->default(now()) 
                        ->hidden(fn (Get $get) => $get('report_type') !== 'custom')
                        ->live()
                        // Cannot be before the Start Date
                        ->minDate(fn (Get $get) => $get('start_date'))
                        // HARD STOP: Cannot be a date in the future
                        ->maxDate(now()),
                ])
                ->action(function (array $data) {
                    
                    // 1. Hand the form data to our Service engine
                    $kpiService = new KpiService();
                    $archivePath = $kpiService->generateReport(
                        $data['report_type'], 
                        $data['start_date'] ?? null, 
                        $data['end_date'] ?? null
                    );

                    // 2. Safety catch
                    if (!$archivePath) {
                        Notification::make()
                            ->title('Export Failed')
                            ->body('There was an error generating the report. Please check the logs.')
                            ->danger()
                            ->send();
                        return null;
                    }

                    // 3. BULLETPROOF DOWNLOAD TRIGGER
                    // By using storage_path() and response()->download(), we force 
                    // Livewire to pass the actual file stream directly to the browser
                    return response()->download(storage_path('app/private/' . $archivePath));
                }),
        ];
    }
}