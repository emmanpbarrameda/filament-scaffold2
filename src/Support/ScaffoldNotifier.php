<?php

namespace Emmanpbarrameda\FilamentScaffold2\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/********************************************
 * ? Notifications for the scaffold page
 * Shows the toast now and, when possible, also stores it as a database notification
 ********************************************/
final class ScaffoldNotifier
{
    public static function notify(Notification $notification): void
    {
        // STEP 1: Toast (always)
        $notification->send();

        // STEP 2: Database notification (only for a Notifiable user)
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'notify')) {
            return;
        }

        // ? detector: optional — needs the notifications table + Notifiable; a missing table is not an error
        try {
            $notification->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::debug('[filament-scaffold2] Database notification skipped', ['message' => $e->getMessage()]);
        }
    }
}
