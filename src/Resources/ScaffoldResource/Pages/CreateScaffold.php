<?php

namespace Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Emmanpbarrameda\FilamentScaffold2\Models\Scaffold;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource;
use Throwable;

class CreateScaffold extends CreateRecord
{
    protected static string $resource = ScaffoldResource::class;

    public static bool $formActionsAreSticky = true;

    protected static bool $canCreateAnother = false;
    
    protected ?bool $hasDatabaseTransactions = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            ScaffoldResource::generateFiles($data);
        } catch (Throwable $e) {
            Log::error('[filament-scaffold2] Scaffold generation failed', ['exception' => $e]);

            ScaffoldResource::notify(
                Notification::make()
                    ->danger()
                    ->persistent()
                    ->title('Scaffold failed')
                    ->body($e->getMessage() . ' Files created before the error were kept; check storage/logs for details.')
            );
            throw new Halt;
        }

        return new Scaffold;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label('Create Scaffold')
            ->requiresConfirmation()
            ->modalHeading('Confirm Scaffold Creation')
            ->modalDescription(new HtmlString("Ensure all scaffold components (resource name, table name, etc.) are set and their creation <b>won't be interrupted</b>.<br><br>Are you ready to create the scaffold?"))
            ->modalSubmitActionLabel('Yes, Confirm')
            ->modalCancelActionLabel('No, Cancel')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->action(fn () => $this->create())
            ->keyBindings(['mod+s']);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
