<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Listeners in app/Listeners are auto-discovered (handle* methods).
        // Do not also Event::subscribe() them — that registers every handler twice.

        Gate::define('viewApiDocs', fn ($user = null) => true);

        Scramble::afterOpenApiGenerated(function ($document) {
            $document->info->title = 'Synexel API';
            $document->info->description = 'Synexel spreadsheet platform — workbooks, formulas, XLSX import/export, and webhooks.';
        });
    }
}
