<?php

use App\Http\Controllers\ControlledDocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/portal');
});

Route::get('/privacy', function () {
    return view('console.privacy-policy');
});

Route::middleware('auth')->get('/controlled-documents/{distribution}/download', ControlledDocumentDownloadController::class)
    ->name('controlled-documents.download');

// Read-only, permission-checked print view for Guided Assessment.

Route::middleware('auth')->get('/portal/asesmen/{assessment}/laporan',
    \App\Http\Controllers\GuidedWizardReportController::class)
    ->name('guided.assessment.print');
