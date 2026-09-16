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
