<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\PaymentPageController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing'));

Route::get('/pay/{invoice:public_id}', [PaymentPageController::class, 'show'])
    ->name('payment.show');

Route::middleware(['guest', SecurityHeaders::class])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('/register', [RegisterController::class, 'show'])
        ->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware(['auth', SecurityHeaders::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');

    Route::get('/email/verify', fn () => view('auth.verify-email'))
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect('/dashboard?verified=1');
    })->middleware(['signed', 'throttle:6,1'])
      ->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')
      ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'overview'])
            ->name('dashboard');

        Route::get('/dashboard/invoices', [DashboardController::class, 'invoices'])
            ->name('dashboard.invoices');

        Route::get('/dashboard/invoices/create', [DashboardController::class, 'createInvoice'])
            ->name('dashboard.invoices.create');

        Route::post('/dashboard/invoices', [DashboardController::class, 'storeInvoice'])
            ->name('dashboard.invoices.store');

        Route::get('/dashboard/invoices/{publicId}', [DashboardController::class, 'invoice'])
            ->where('publicId', '[0-9A-Za-z]{26}')
            ->name('dashboard.invoice');

        Route::get('/dashboard/payments', [DashboardController::class, 'payments'])
            ->name('dashboard.payments');

        Route::get('/dashboard/review', [DashboardController::class, 'review'])
            ->name('dashboard.review');
    });
});
