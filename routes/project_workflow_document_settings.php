<?php

use App\Http\Controllers\Projects\ProjectWorkflowV2Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::post(
        '/du-an/{site}/quy-trinh/{step}/cau-hinh-ho-so',
        [ProjectWorkflowV2Controller::class, 'saveDocumentSettings']
    )
        ->whereNumber('site')
        ->where(
            'step',
            'survey|proposal|contract|legal|construction|acceptance|warranty'
        )
        ->name('projects-unified.workflow.documents.settings.save');

    Route::post(
        '/du-an/{site}/quy-trinh/{step}/cau-hinh-ho-so/khoi-phuc',
        [ProjectWorkflowV2Controller::class, 'resetDocumentSettings']
    )
        ->whereNumber('site')
        ->where(
            'step',
            'survey|proposal|contract|legal|construction|acceptance|warranty'
        )
        ->name('projects-unified.workflow.documents.settings.reset');
});
