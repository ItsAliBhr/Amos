<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ValuationController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

// Web installer (only reachable while the application is NOT installed).
Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'store'])->middleware('throttle:10,1')->name('install.store');

// The domain's first page is the login page. No public registration exists.
Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::get('/login', fn () => redirect()->route('login'));
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.fresh')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

        Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

        // Contributions
        Route::get('/contributions', [ContributionController::class, 'index'])->name('contributions.index');
        Route::get('/contributions/create', [ContributionController::class, 'create'])->name('contributions.create');
        Route::post('/contributions', [ContributionController::class, 'store'])->name('contributions.store');
        Route::get('/contributions/export', [ContributionController::class, 'export'])->name('contributions.export');
        Route::get('/contributions/{contribution}', [ContributionController::class, 'show'])->name('contributions.show');
        Route::post('/contributions/{contribution}/submit', [ContributionController::class, 'submit'])->name('contributions.submit');
        Route::post('/contributions/{contribution}/cancel', [ContributionController::class, 'cancel'])->name('contributions.cancel');

        // Investments & assets (read-only for members depending on visibility settings)
        Route::get('/investments', [InvestmentController::class, 'index'])->name('investments.index');
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

        // Ideas
        Route::get('/ideas', [IdeaController::class, 'index'])->name('ideas.index');
        Route::get('/ideas/create', [IdeaController::class, 'create'])->name('ideas.create');
        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::get('/ideas/{idea}', [IdeaController::class, 'show'])->name('ideas.show');
        Route::get('/ideas/{idea}/edit', [IdeaController::class, 'edit'])->name('ideas.edit');
        Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::post('/ideas/{idea}/review', [IdeaController::class, 'review'])->name('ideas.review');
        Route::post('/ideas/{idea}/withdraw', [IdeaController::class, 'withdraw'])->name('ideas.withdraw');

        // Withdrawals
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
        Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::post('/withdrawals/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('withdrawals.cancel');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/member/{user?}', [ReportController::class, 'member'])->name('reports.member');
        Route::get('/reports/commitments', [ReportController::class, 'commitments'])->name('reports.commitments');

        // ---------------- Admin only ----------------
        Route::middleware('admin')->group(function () {
            Route::post('/contributions/{contribution}/approve', [ContributionController::class, 'approve'])->name('contributions.approve');
            Route::post('/contributions/{contribution}/reject', [ContributionController::class, 'reject'])->name('contributions.reject');
            Route::post('/contributions/{contribution}/reverse', [ContributionController::class, 'reverse'])->name('contributions.reverse');

            Route::get('/investments/create', [InvestmentController::class, 'create'])->name('investments.create');
            Route::post('/investments', [InvestmentController::class, 'store'])->name('investments.store');
            Route::post('/investments/{investment}/approve', [InvestmentController::class, 'approve'])->name('investments.approve');
            Route::post('/investments/{investment}/reject', [InvestmentController::class, 'reject'])->name('investments.reject');
            Route::post('/investments/{investment}/reverse', [InvestmentController::class, 'reverse'])->name('investments.reverse');

            Route::get('/valuations', [ValuationController::class, 'index'])->name('valuations.index');
            Route::get('/valuations/create', [ValuationController::class, 'create'])->name('valuations.create');
            Route::post('/valuations', [ValuationController::class, 'store'])->name('valuations.store');
            Route::post('/valuations/{valuation}/approve', [ValuationController::class, 'approve'])->name('valuations.approve');
            Route::post('/valuations/{valuation}/reject', [ValuationController::class, 'reject'])->name('valuations.reject');

            Route::post('/ideas/{idea}/decide', [IdeaController::class, 'decide'])->name('ideas.decide');

            Route::get('/withdrawals-pool/create', [WithdrawalController::class, 'createPool'])->name('withdrawals.pool.create');
            Route::post('/withdrawals-pool', [WithdrawalController::class, 'storePool'])->name('withdrawals.pool.store');
            Route::post('/withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
            Route::post('/withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
            Route::post('/withdrawals/{withdrawal}/pay', [WithdrawalController::class, 'pay'])->name('withdrawals.pay');

            Route::get('/reports/fund', [ReportController::class, 'fund'])->name('reports.fund');
            Route::get('/reports/pool', [ReportController::class, 'pool'])->name('reports.pool');
            Route::get('/reports/assets', [ReportController::class, 'assets'])->name('reports.assets');
            Route::get('/reports/members', [ReportController::class, 'members'])->name('reports.members');

            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('/approvals', [Admin\ApprovalQueueController::class, 'index'])->name('approvals');

                Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
                Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
                Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
                Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
                Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
                Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.resetPassword');
                Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
                Route::post('/users/{user}/commitments', [Admin\UserController::class, 'commitment'])->name('users.commitment');

                Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
                Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

                Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
                Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
                Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');

                Route::get('/assets', [Admin\AssetAdminController::class, 'index'])->name('assets.index');
                Route::get('/assets/create', [Admin\AssetAdminController::class, 'create'])->name('assets.create');
                Route::post('/assets', [Admin\AssetAdminController::class, 'store'])->name('assets.store');
                Route::get('/assets/{asset}/edit', [Admin\AssetAdminController::class, 'edit'])->name('assets.edit');
                Route::put('/assets/{asset}', [Admin\AssetAdminController::class, 'update'])->name('assets.update');

                Route::get('/cash-accounts', [Admin\CashAccountController::class, 'index'])->name('cash.index');
                Route::get('/cash-accounts/create', [Admin\CashAccountController::class, 'create'])->name('cash.create');
                Route::post('/cash-accounts', [Admin\CashAccountController::class, 'store'])->name('cash.store');
                Route::get('/cash-accounts/{cashAccount}/edit', [Admin\CashAccountController::class, 'edit'])->name('cash.edit');
                Route::put('/cash-accounts/{cashAccount}', [Admin\CashAccountController::class, 'update'])->name('cash.update');

                Route::get('/ledger', [Admin\LedgerController::class, 'index'])->name('ledger.index');
                Route::get('/ledger/{ledgerTransaction}', [Admin\LedgerController::class, 'show'])->name('ledger.show');

                Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
                Route::get('/audit/{auditLog}', [Admin\AuditLogController::class, 'show'])->name('audit.show');

                Route::get('/docs', [Admin\DocsController::class, 'index'])->name('docs.index');
                Route::get('/docs/{slug}', [Admin\DocsController::class, 'show'])->name('docs.show');
            });
        });

        Route::get('/investments/{investment}', [InvestmentController::class, 'show'])->name('investments.show');
    });
});
