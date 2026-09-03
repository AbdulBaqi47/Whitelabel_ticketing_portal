<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    // return view('mail.booking');
    dd(\Carbon\Carbon::now()->toDateTimeString());
});

Route::get('access-token', function(){
    Artisan::call('app:sabre-access-token');
});