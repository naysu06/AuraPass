<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The default Create button
            Actions\CreateAction::make(),

            // --- PDF REPORT ACTION ---
            Actions\Action::make('print_report')
                ->label('Monthly Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                // 1. Add a Form to select the Month/Year
                ->form([
                    Select::make('month')
                        ->label('Month')
                        ->options([
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ])
                        ->default(now()->month)
                        ->required(),
                    
                    TextInput::make('year')
                        ->label('Year')
                        ->numeric()
                        ->default(now()->year)
                        ->required(),

                    // NEW: Membership Type Filter
                    Select::make('membership_type')
                        ->label('Membership Type')
                        ->options([
                            'all' => 'All Types',
                            'regular' => 'Regular',
                            'discount' => 'Discount',
                            'promo' => 'Promo',
                        ])
                        ->default('all')
                        ->required(),
                ])
                // 2. Handle the Export
                ->action(function (array $data) {
                    $month = (int) $data['month'];
                    $year = (int) $data['year'];
                    $type = $data['membership_type'];

                    // Start Query: Find members created in that specific month
                    $query = Member::whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);

                    // Apply Filter if not 'all'
                    if ($type !== 'all') {
                        $query->where('membership_type', $type);
                    }

                    // Get results sorted alphabetically
                    $members = $query->orderBy('name')->get();

                    $monthName = Carbon::createFromDate($year, $month)->format('F');
                    $typeLabel = $type === 'all' ? 'All' : ucfirst($type);

                    // Load the View and pass data
                    $pdf = Pdf::loadView('pdf.monthly-report', [
                        'members' => $members,
                        'monthName' => $monthName,
                        'year' => $year,
                        'reportType' => $typeLabel, // Pass filter type to PDF (optional use in blade)
                    ]);

                    // Download the file with descriptive name
                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, "Monthly_Report_{$monthName}_{$year}_{$typeLabel}.pdf");
                })
                ->modalHeading('Download Monthly Report')
                ->modalSubmitActionLabel('Download PDF'),
        ];
    }
}