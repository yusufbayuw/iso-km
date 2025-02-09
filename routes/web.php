<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/portal');
});

Route::get('/privacy', function () {
    return view('console.privacy-policy');
});
