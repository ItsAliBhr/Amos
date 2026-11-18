<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\NavService;
use App\Domain\Members\CommitmentService;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\Contribution;
use App\Models\FundSetting;
use App\Models\Idea;
use App\Models\InvestmentTransaction;
use App\Models\LedgerTransaction;
use App\Models\NavSnapshot;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private NavService $nav, private CommitmentService $commitments)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $state = $this->nav->state();
        $settings = FundSetting::current();

        $personal = $this->personal($user, $state);

        if (! $user->isAdmin()) {
            return view('dashboard.member', array_merge($personal, [
                'state' => $state,
                'visibility' => $settings->member_dashboard_visibility,
                'composition' => $settings->member_dashboard_visibility === 'full' ? $this->composition($state) : [],
                'navHistory' => $settings->member_dashboard_visibility !== 'none' ? $this->navHistory() : [],
                'ideas' => Idea::whereIn('status', ['submitted', 'under_review', 'needs_information'])->latest()->limit(5)->get(),
            ]));
        }

        $accounts = $this->nav->allAccountValues($state);
        $pool = OwnershipAccount::pool();
        $poolValue = $accounts[$pool->id]['value'] ?? Money::zero();
        $memberValue = Money::zero();
        foreach ($accounts as $row) {
            if (! $row['account']->isPool()) {
                $memberValue = $memberValue->plus($row['value']);
            }
        }

        $stale = [];
        $staleDays = (int) $settings->valuation_stale_days;
        foreach ($state->holdings as $h) {
            if ($h['quantity']->isZero()) {
                continue;
            }
            $days = $h['valuation_date'] ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($h['valuation_date']), true) : null;
            if ($days === null || $days > $staleDays) {
                $stale[] = ['asset' => Asset::find($h['asset_id']), 'date' => $h['valuation_date'], 'days' => $days];
            }
        }

        $pendingCounts = [
            'contributions' => Contribution::where('status', 'pending')->count(),
            'investments' => InvestmentTransaction::where('status', 'pending')->count(),
            'valuations' => AssetValuation::where('status', 'pending')->count(),
            'withdrawals' => WithdrawalRequest::whereIn('status', ['pending', 'approved', 'awaiting_liquidity'])->count(),
        ];

        return view('dashboard.admin', array_merge($personal, [
            'state' => $state,
            'memberValue' => $memberValue,
            'poolValue' => $poolValue,
            'activeMembers' => User::active()->count(),
            'pendingCounts' => $pendingCounts,
            'pendingTotal' => array_sum($pendingCounts),
            'ideasInReview' => Idea::whereIn('status', ['submitted', 'under_review', 'needs_information'])->count(),
            'recentLedger' => LedgerTransaction::with('creator')->latest('id')->limit(8)->get(),
            'recentValuations' => AssetValuation::with('asset')->latest('id')->limit(5)->get(),
            'newIdeas' => Idea::where('status', '!=', 'draft')->latest()->limit(5)->get(),
            'pendingContributions' => Contribution::with(['member', 'type'])->where('status', 'pending')->latest()->limit(5)->get(),
            'stale' => $stale,
            'composition' => $this->composition($state),
            'navHistory' => $this->navHistory(),
            'monthly' => $this->monthlyContributions(),
            'distribution' => collect($accounts)->filter(fn ($r) => $r['value']->isPositive())
                ->map(fn ($r) => ['label' => $r['account']->displayName(), 'value' => (float) (string) $r['value']])->values()->all(),
        ]));
    }

    private function personal(User $user, $state): array
    {
        $account = OwnershipAccount::forMember($user);
        $units = $this->nav->unitsOf($account);
        $value = Money::irr($units->multipliedBy($state->navPerUnit));
        $netCapital = $this->nav->netCapitalOf($account);
        $approvedTotal = Money::of(Contribution::where('member_id', $user->id)->where('status', 'approved')
            ->where('ownership_account_id', $account->id)->sum('amount_irr') ?: 0);
        [$y, $m] = Jalali::currentYearMonth();

        return [
            'myUnits' => $units,
            'myValue' => $value,
            'myNetCapital' => $netCapital,
            'myContributed' => $approvedTotal,
            'myPnl' => $value->minus($netCapital),
            'myCommitment' => $this->commitments->statusFor($user, $y, $m),
            'myRecent' => Contribution::with('type')->where('member_id', $user->id)->latest()->limit(5)->get(),
            'myPending' => Contribution::where('member_id', $user->id)->whereIn('status', ['draft', 'pending'])->count()
                + WithdrawalRequest::where('member_id', $user->id)->whereIn('status', ['pending', 'approved', 'awaiting_liquidity'])->count(),
            'myWithdrawals' => WithdrawalRequest::where('member_id', $user->id)->whereIn('status', ['pending', 'approved', 'awaiting_liquidity'])->latest()->get(),
        ];
    }

    private function composition($state): array
    {
        $byCat = [];
        if ($state->cash->isPositive()) {
            $byCat['نقدینگی'] = (float) (string) $state->cash;
        }
        $assets = Asset::with('category')->whereIn('id', array_keys($state->holdings))->get()->keyBy('id');
        foreach ($state->holdings as $id => $h) {
            $cat = $assets[$id]->category->name ?? 'سایر';
            $byCat[$cat] = ($byCat[$cat] ?? 0) + (float) (string) $h['market'];
        }

        return collect($byCat)->filter(fn ($v) => $v > 0)->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all();
    }

    private function navHistory(): array
    {
        return NavSnapshot::orderByDesc('snapshot_date')->limit(180)->get()->reverse()->map(fn ($s) => [
            'date' => Jalali::format($s->snapshot_date),
            'nav' => (float) $s->nav_irr,
            'navPerUnit' => (float) $s->nav_per_unit_irr,
            'pnl' => (float) $s->realized_pnl_irr + (float) $s->unrealized_pnl_irr,
        ])->values()->all();
    }

    private function monthlyContributions(): array
    {
        [$y, $m] = Jalali::currentYearMonth();
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            [$yy, $mm] = Jalali::addMonths($y, $m, -$i);
            [$start, $end] = Jalali::monthRange($yy, $mm);
            $base = Contribution::where('status', 'approved')->whereBetween('contribution_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            $out[] = [
                'label' => Jalali::monthName($mm),
                'member' => (float) (clone $base)->whereHas('ownershipAccount', fn ($q) => $q->where('owner_type', 'member'))->sum('amount_irr'),
                'pool' => (float) (clone $base)->whereHas('ownershipAccount', fn ($q) => $q->where('owner_type', 'pool'))->sum('amount_irr'),
            ];
        }

        return $out;
    }
}
