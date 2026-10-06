<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignExportController;
use App\Http\Controllers\CampaignMappingController;
use App\Http\Controllers\CampaignPreviewController;
use App\Http\Controllers\CampaignProgressController;
use App\Http\Controllers\CampaignRecipientController;
use App\Http\Controllers\CampaignSendController;
use App\Http\Controllers\CampaignSmtpController;
use App\Http\Controllers\CampaignTemplateController;
use App\Http\Controllers\CampaignUploadController;
use App\Http\Controllers\CampaignWorkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SmtpAccountController;
use App\Http\Controllers\SmtpTestController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// NOT Route::redirect(): it sends a root-relative "/dashboard" and drops the sub-folder (/laravel/mailbatch).
Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'active', 'throttle:240,1'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/create', [CampaignController::class, 'create'])->name('create');
        Route::post('/', [CampaignController::class, 'store'])->name('store');

        Route::prefix('{campaign}')->group(function () {
            Route::get('/', [CampaignController::class, 'show'])->name('show');
            Route::get('/edit', [CampaignController::class, 'edit'])->name('edit');
            Route::put('/', [CampaignController::class, 'update'])->name('update');
            Route::delete('/', [CampaignController::class, 'destroy'])->name('destroy');

            // ---- Added in later phases (routes are appended here) ----
            // Excel upload, preview, column mapping
            Route::get('/preview', [CampaignPreviewController::class, 'show'])->name('preview');
            Route::post('/upload', [CampaignUploadController::class, 'store'])->middleware('throttle:10,1')->name('upload');
            Route::get('/mapping', [CampaignMappingController::class, 'edit'])->name('mapping');
            Route::post('/mapping', [CampaignMappingController::class, 'update'])->name('mapping.update');
            Route::post('/mapping/preview', [CampaignMappingController::class, 'preview'])
                ->middleware('throttle:30,1')->name('mapping.preview');

            // SMTP step
            Route::get('/smtp', [CampaignSmtpController::class, 'edit'])->name('smtp');
            Route::post('/smtp', [CampaignSmtpController::class, 'update'])->name('smtp.update');

            // Email composer + test email
            Route::get('/compose', [CampaignTemplateController::class, 'edit'])->name('compose');
            Route::post('/compose', [CampaignTemplateController::class, 'update'])->name('compose.update');
            Route::post('/compose/preview', [CampaignTemplateController::class, 'preview'])
                ->middleware('throttle:60,1')->name('compose.preview');
            Route::post('/test-email', [CampaignTemplateController::class, 'sendTest'])
                ->middleware('throttle:smtp-test')->name('test-email');

            // Confirm + sending (queue-based, one batch per click)
            Route::get('/confirm', [CampaignSendController::class, 'confirm'])->name('confirm');
            Route::post('/start', [CampaignSendController::class, 'start'])->middleware('throttle:20,1')->name('start');
            Route::post('/pause', [CampaignSendController::class, 'pause'])->middleware('throttle:20,1')->name('pause');
            Route::post('/retry-failed', [CampaignSendController::class, 'retryFailed'])->middleware('throttle:20,1')->name('retry-failed');
            // Replaces `php artisan queue:work`: processes THIS campaign's own queue, called by the page in a loop
            Route::post('/work', [CampaignWorkController::class, 'run'])->middleware('throttle:120,1')->name('work');
            Route::get('/progress/status', [CampaignProgressController::class, 'status'])->name('progress.status');

            // Progress, logs, recipients, export
            Route::get('/progress', [CampaignProgressController::class, 'show'])->name('progress');
            Route::get('/logs', [CampaignProgressController::class, 'logs'])->name('logs');
            Route::get('/recipients', [CampaignRecipientController::class, 'index'])->name('recipients');
            Route::get('/recipients/state', [CampaignRecipientController::class, 'state'])->middleware('throttle:60,1')->name('recipients.state');
            Route::post('/recipients/{recipient}/retry', [CampaignRecipientController::class, 'retry'])
                ->whereNumber('recipient')->middleware('throttle:30,1')->name('recipients.retry');
            Route::get('/export', [CampaignExportController::class, 'download'])->middleware('throttle:10,1')->name('export');
        });
    });

    // Own account
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');

    // Reusable SMTP accounts + AJAX test (defined before the resource so /test is not treated as an id)
    Route::post('/smtp-accounts/test', SmtpTestController::class)->middleware('throttle:smtp-test')->name('smtp-accounts.test');
    Route::resource('smtp-accounts', SmtpAccountController::class)->except(['show']);
});

// ---- Administration (role: admin) ----
Route::middleware(['auth', 'active', 'admin', 'throttle:240,1'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');       // role, active, daily limit, password reset
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/smtp-accounts', [Admin\SmtpAccountController::class, 'index'])->name('smtp.index');
    Route::post('/smtp-accounts/{smtpAccount}/toggle', [Admin\SmtpAccountController::class, 'toggle'])->name('smtp.toggle');

    Route::get('/campaigns', [Admin\CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}', [Admin\CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/pause', [Admin\CampaignController::class, 'pause'])->name('campaigns.pause');

    Route::get('/email-logs', [Admin\EmailLogController::class, 'index'])->name('logs.index');
    Route::get('/failed-emails', [Admin\EmailLogController::class, 'failed'])->name('logs.failed');

    Route::get('/settings', [Admin\SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SystemSettingController::class, 'update'])->name('settings.update');
});
// Public, signed-token unsubscribe pages (no login). Throttled.
Route::get('/unsubscribe/{token}', [UnsubscribeController::class, 'show'])
    ->where('token', '[A-Za-z0-9._\-]+')->middleware('throttle:30,1')->name('unsubscribe.show');
Route::post('/unsubscribe/{token}', [UnsubscribeController::class, 'store'])
    ->where('token', '[A-Za-z0-9._\-]+')->middleware('throttle:30,1')->name('unsubscribe.store');

require __DIR__.'/auth.php';
