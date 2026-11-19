<?php

use App\Http\Controllers\Demo\EmailVerificationController;
use App\Livewire\Demo\Register;
use App\Livewire\Demo\VerifyEmailNotice;
use Illuminate\Support\Facades\Route;

Route::middleware(['demo.enabled', 'guest:web'])->group(function () {
    Route::get('register', Register::class)->name('demo.register');
});

Route::middleware(['demo.enabled', 'auth:web'])->group(function () {
    Route::get('email/verify', VerifyEmailNotice::class)->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
