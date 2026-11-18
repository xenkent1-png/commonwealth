<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinancialTransactionController as AdminFinancialTransactionController;
use App\Http\Controllers\Admin\InvestorController as AdminInvestorController;
use App\Http\Controllers\Admin\FinancialRequestController as AdminFinancialRequestController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Investor\DashboardController as InvestorDashboardController;
use App\Http\Controllers\Investor\MarketController;
use App\Http\Controllers\Investor\WatchlistController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\InformationPageController;
use App\Http\Controllers\Public\InvestmentThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/investment-themes', [InvestmentThemeController::class, 'index'])->name('themes.index');
Route::get('/investment-themes/{slug}', [InvestmentThemeController::class, 'show'])->name('themes.show');
Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
Route::get('/opportunities/{slug}', [OpportunityController::class, 'show'])->name('opportunities.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
if (config('platform.contact_form_enabled')) {
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1')->name('contact.store');
}

Route::get('/about', [InformationPageController::class, 'show'])->defaults('page', 'about')->name('about');
Route::get('/trust', [InformationPageController::class, 'show'])->defaults('page', 'trust')->name('trust');
Route::get('/how-it-works', [InformationPageController::class, 'show'])->defaults('page', 'how-it-works')->name('how-it-works');
Route::get('/payment-methods', [InformationPageController::class, 'show'])->defaults('page', 'payment-methods')->name('payment-methods');
Route::get('/faq', [InformationPageController::class, 'show'])->defaults('page', 'faq')->name('faq');
Route::get('/terms', [InformationPageController::class, 'show'])->defaults('page', 'terms')->name('terms');
Route::get('/privacy', [InformationPageController::class, 'show'])->defaults('page', 'privacy')->name('privacy');
Route::get('/risk-disclosure', [InformationPageController::class, 'show'])->defaults('page', 'risk-disclosure')->name('risk-disclosure');
Route::get('/aml-kyc', [InformationPageController::class, 'show'])->defaults('page', 'aml-kyc')->name('aml-kyc');

Route::middleware('guest')->group(function () {
    if (config('platform.investor_registration_enabled')) {
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    }
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/admin/login', [AuthenticatedSessionController::class, 'createAdmin'])->name('admin.login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'storeAdmin'])->middleware('throttle:10,1')->name('admin.login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/change-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/investor/dashboard', InvestorDashboardController::class)
        ->middleware(['verified', 'permission:dashboard.view'])->name('investor.dashboard');
    Route::get('/investor/stocks/{symbol}', [MarketController::class, 'show'])
        ->where('symbol', '[A-Za-z0-9-]+')
        ->middleware(['verified', 'permission:dashboard.view'])->name('investor.stocks.show');
    Route::post('/investor/watchlist', [WatchlistController::class, 'store'])
        ->middleware(['verified', 'permission:watchlist.manage-own'])->name('investor.watchlist.store');
    Route::patch('/investor/watchlist/{watchlistItem}', [WatchlistController::class, 'update'])
        ->whereNumber('watchlistItem')
        ->middleware(['verified', 'permission:watchlist.manage-own'])->name('investor.watchlist.update');
    Route::delete('/investor/watchlist/{watchlistItem}', [WatchlistController::class, 'destroy'])
        ->whereNumber('watchlistItem')
        ->middleware(['verified', 'permission:watchlist.manage-own'])->name('investor.watchlist.destroy');
    Route::middleware('verified')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboardController::class)
            ->middleware('permission:admin.dashboard.view')->name('dashboard');
        Route::get('/investors', [AdminInvestorController::class, 'index'])
            ->middleware('permission:investors.view')->name('investors.index');
        Route::get('/investors/{user}', [AdminInvestorController::class, 'show'])
            ->middleware('permission:investors.view')->name('investors.show');
        Route::patch('/investors/{user}/status', [AdminInvestorController::class, 'updateStatus'])
            ->middleware('permission:investors.manage')->name('investors.status');

        Route::get('/transactions', [AdminFinancialTransactionController::class, 'index'])
            ->middleware('permission:ledger.view')->name('transactions.index');
        Route::get('/transactions/{transaction}', [AdminFinancialTransactionController::class, 'show'])
            ->middleware('permission:ledger.view')->name('transactions.show');

        Route::get('/deposits', [AdminFinancialRequestController::class, 'deposits'])
            ->middleware('permission:deposits.review')->name('deposits.index');
        Route::get('/deposits/{depositRequest}', [AdminFinancialRequestController::class, 'deposit'])
            ->middleware('permission:deposits.review')->name('deposits.show');
        Route::get('/withdrawals', [AdminFinancialRequestController::class, 'withdrawals'])
            ->middleware('permission:withdrawals.review')->name('withdrawals.index');
        Route::get('/withdrawals/{withdrawalRequest}', [AdminFinancialRequestController::class, 'withdrawal'])
            ->middleware('permission:withdrawals.review')->name('withdrawals.show');
    });
});
