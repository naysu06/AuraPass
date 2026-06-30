<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Carbon\Carbon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The default Create button
            Actions\CreateAction::make(),

            // --- CSV REPORT ACTION ---
            Actions\Action::make('print_report')
                ->label('Monthly Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
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

                    Select::make('membership_type')
                        ->label('Membership Type')
                        ->options([
                            'all'      => 'All Types',
                            'regular'  => 'Regular',
                            'discount' => 'Discount',
                            'promo'    => 'Promo',
                        ])
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $month = (int) $data['month'];
                    $year  = (int) $data['year'];
                    $type  = $data['membership_type'];

                    $query = Member::whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);

                    if ($type !== 'all') {
                        $query->where('membership_type', $type);
                    }

                    $members   = $query->orderBy('name')->get();
                    $monthName = Carbon::createFromDate($year, $month)->format('F');
                    $typeLabel = $type === 'all' ? 'All' : ucfirst($type);
                    $filename  = "Monthly_Report_{$monthName}_{$year}_{$typeLabel}.csv";

                    $headers = [
                        'Content-Type'        => 'text/csv',
                        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                        'Pragma'              => 'no-cache',
                        'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
                        'Expires'             => '0',
                    ];

                    return response()->streamDownload(function () use ($members, $monthName, $year, $typeLabel) {
                        $handle = fopen('php://output', 'w');

                        // Report meta rows
                        fputcsv($handle, ['QUADS-FURUKAWA GYM']);
                        fputcsv($handle, ['New Registered Member Report']);
                        fputcsv($handle, ['Reporting Month:', "{$monthName} {$year}"]);
                        fputcsv($handle, ['Membership Type:', $typeLabel]);
                        fputcsv($handle, ['Generated on:', now()->format('M d, Y h:i A')]);
                        fputcsv($handle, []); // blank spacer row

                        // Column headers
                        fputcsv($handle, ['Name', 'Email', 'Type', 'Date Registered', 'Membership Expiry']);

                        // Data rows
                        if ($members->isEmpty()) {
                            fputcsv($handle, ['No new registrations found for this month.']);
                        } else {
                            foreach ($members as $member) {
                                fputcsv($handle, [
                                    $member->name,
                                    $member->email,
                                    ucfirst($member->membership_type),
                                    $member->created_at->format('M d, Y'),
                                    $member->membership_expiry_date->format('M d, Y'),
                                ]);
                        }
                        }

                        // Summary row
                        fputcsv($handle, []);
                        fputcsv($handle, ['Total New Members:', $members->count()]);

                        fclose($handle);
                    }, $filename, $headers);
                })
                ->modalHeading('Download Monthly Report')
                ->modalSubmitActionLabel('Download CSV'),
        ];
    }
}