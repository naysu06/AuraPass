<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use App\Models\Member;
use App\Jobs\ProcessQrScan; 
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder; 
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;     
use Illuminate\Support\HtmlString; 

class ListAccessLog extends ListRecords
{
    protected static string $resource = AccessLogResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('audit_log')
                ->label('Audit Log')
                ->url(fn (): string => static::getResource()::getUrl('audit-log'))
                ->color('primary')
                ->icon('heroicon-o-shield-check'),
        ];
    }
}