<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                // 1. Completely hide the button if editing yourself
                ->hidden(fn ($record) => $record->id === auth()->id())
                
                // 2. Ironclad fallback safety net
                ->before(function (Actions\DeleteAction $action, $record) {
                    if ($record->id === auth()->id()) {
                        Notification::make()
                            ->danger()
                            ->title('Action Denied')
                            ->body('Critical Error: System prevented you from locking yourself out.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}