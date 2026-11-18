<?php

namespace App\Providers;

use App\Models\FundSetting;
use App\Models\InvestmentUnit;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (config('amos.force_https') && $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Posted ledger data and unit movements are append-only. Corrections use reversal documents.
        LedgerEntry::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        LedgerEntry::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
        LedgerTransaction::updating(fn () => throw new LogicException('Ledger transactions are immutable.'));
        LedgerTransaction::deleting(fn () => throw new LogicException('Ledger transactions are immutable.'));
        InvestmentUnit::updating(fn () => throw new LogicException('Unit movements are immutable.'));
        InvestmentUnit::deleting(fn () => throw new LogicException('Unit movements are immutable.'));

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('unreadCount', $user ? $user->appNotifications()->whereNull('read_at')->count() : 0);
            try {
                $view->with('fundName', FundSetting::current()->fund_name);
            } catch (Throwable) {
                $view->with('fundName', 'AMOS');
            }
        });

        if (amos_installed() && ! $this->app->runningUnitTests()) {
            try {
                $tz = FundSetting::current()->timezone;
                if ($tz) {
                    config(['app.display_timezone' => $tz]);
                }
            } catch (Throwable) {
                // database not reachable yet; keep the default
            }
        }
    }
}
