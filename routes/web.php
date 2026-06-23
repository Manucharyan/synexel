<?php

use App\Http\Controllers\Web\AuditLogPageController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\SetupController;
use App\Http\Controllers\Web\UserManagementController;
use App\Http\Controllers\Web\WebhookPageController;
use App\Http\Controllers\Web\WorkbookPageController;
use App\Support\InstallService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/api', function () {
    if (request()->wantsJson()) {
        return response()->json([
            'name' => config('app.name'),
            'version' => 'v1',
            'documentation' => url('/docs/api'),
            'openapi' => url('/docs/api.json'),
            'base_url' => url('/api/v1'),
            'auth' => url('/api/v1/auth/tokens'),
            'installed' => InstallService::isInstalled(),
        ]);
    }

    return redirect('/docs/api');
});

Route::get('/', function () {
    if (! InstallService::isInstalled()) {
        return redirect()->route('setup.show');
    }

    return Auth::check()
        ? redirect()->route('workbooks.index')
        : redirect()->route('login');
});

Route::middleware('not.installed')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware(['installed', 'guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware(['installed', 'auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/workbooks', [WorkbookPageController::class, 'index'])->name('workbooks.index');
    Route::get('/workbooks/{id}', [WorkbookPageController::class, 'show'])->name('workbooks.show');
    Route::get('/logs', [AuditLogPageController::class, 'index'])->name('audit.index');
    Route::get('/webhooks', [WebhookPageController::class, 'index'])->name('webhooks.index');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/active', [UserManagementController::class, 'toggleActive'])->name('users.toggle');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });
});
