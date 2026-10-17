<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
})->name('front.home');

Route::get('/about', function () {
    return view('front.about');
})->name('front.about');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');
});