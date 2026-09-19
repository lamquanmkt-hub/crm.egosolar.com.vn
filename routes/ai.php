<?php

use App\Http\Controllers\AI\AiChatController;
use App\Http\Controllers\Admin\AiGovernanceController;
use App\Http\Controllers\Admin\AiProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:ai.use'])
    ->prefix('ai')
    ->name('ai.')
    ->controller(AiChatController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/conversations', 'create')->middleware('throttle:30,1')->name('conversations.create');
        Route::get('/conversations/{conversation}', 'show')->name('conversations.show');
        Route::delete('/conversations/{conversation}', 'destroy')->name('conversations.destroy');
        Route::post('/messages', 'send')->middleware('throttle:30,1')->name('messages.send');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('cai-dat/ai-api')
    ->name('admin.settings.ai.')
    ->group(function (): void {
        Route::controller(AiProviderController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/providers', 'store')->name('providers.store');
            Route::put('/providers/{provider}', 'update')->name('providers.update');
            Route::delete('/providers/{provider}', 'destroy')->name('providers.destroy');
            Route::post('/providers/{provider}/default', 'setDefault')->name('providers.default');
            Route::post('/providers/{provider}/test', 'test')->middleware('throttle:10,1')->name('providers.test');
        });

        Route::controller(AiGovernanceController::class)->group(function (): void {
            Route::get('/quan-tri', 'index')->name('governance.index');
            Route::put('/quan-tri/roles/{role}', 'updateRole')->name('governance.roles.update');
            Route::post('/quan-tri/ap-dung-mac-dinh', 'applyDefaults')->name('governance.defaults');
        });
    });
