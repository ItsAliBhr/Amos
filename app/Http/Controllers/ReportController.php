<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\NavService;
use App\Domain\Members\CommitmentService;
use App\Models\Asset;
use App\Models\Contribution;
use App\Models\InvestmentTransaction;
use App\Models\InvestmentUnit;
use App\Models\NavSnapshot;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private NavService $nav, private CommitmentService $commitments)
    {
    }

    public function index(Request $request)
    {
        return view('reports.index');
    }

    public function fund()
    {
        $state = $this->nav->state();
        $accounts = $this->nav->allAccountValues($state);
        $first = NavSnapshot::orderBy('snapshot_date')->first();
        $initial = $first ? Money::of($first->nav_per_unit_irr) : null;
        $initialPrice = Money::of(\App\Models\FundSetting::current()->initial_unit_price_irr);
        $performance = $initialPrice->isPositive()
            ? $state->navPerUnit->minus($initialPrice)->multipliedBy(100)->dividedBy($initialPrice, 4, \Brick\Math\RoundingMode::HalfUp)
            : null;

        return view('reports.fund', [
            'state' => $state,
            'accounts' => $accounts,
            'assets' => Asset::with('category')->get()->keyBy('id'),
            'cashAccounts' => \App\Models\CashAccount::all()->keyBy('id'),
            'performance' => $performance,
            'snapshots' => NavSnapshot::orderByDesc('snapshot_date')->limit(30)->get(),
            'initialPrice' => $initialPrice,
        ]);
    }

    public function members()
    {
        $state = $this->nav->state();
        $rows = [];
        foreach (User::withTrashed()->orderBy('name')->get() as $u) {
            $acc = OwnershipAccount::forMember($u);
            $units = $this->nav->unitsOf($acc);
            $value = Money::irr($units->multipliedBy($state->navPerUnit));
            $net = $this->nav->netCapitalOf($acc);
            [$y, $m] = Jalali::currentYearMonth();
            $rows[] = [
                'user' => $u,
                'units' => $units,
                'value' => $value,
                'net' => $net,
                'pnl' => $value->minus($net),
                'commitment' => $this->commitments->statusFor($u, $y, $m),
            ];
        }

        return view('reports.members', ['rows' => $rows, 'state' => $state]);
    }

    public function member(Request $request, ?User $user = null)
    {
        $viewer = $request->user();
        $user ??= $viewer;
        // Members may only see their own report (prevents IDOR).
        abort_unless($viewer->isAdmin() || $viewer->id === $user->id, 403);

        $state = $this->nav->state();
        $acc = OwnershipAccount::forMember($user);
        $units = $this->nav->unitsOf($acc);
        $value = Money::irr($units->multipliedBy($state->navPerUnit));
        $net = $this->nav->netCapitalOf($acc);

        return view('reports.member', [
            'user' => $user,
            'state' => $state,
            'units' => $units,
            'value' => $value,
            'net' => $net,
            'pnl' => $value->minus($net),
            'unitHistory' => InvestmentUnit::where('ownership_account_id', $acc->id)->orderByDesc('id')->limit(100)->get(),
            'contributions' => Contribution::with('type')->where('member_id', $user->id)->orderByDesc('contribution_date')->limit(100)->get(),
            'withdrawals' => WithdrawalRequest::where('member_id', $user->id)->latest('requested_at')->get(),
            'commitments' => $this->commitments->history($user, 24),
            'members' => $viewer->isAdmin() ? User::withTrashed()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function pool()
    {
        $state = $this->nav->state();
        $pool = OwnershipAccount::pool();
        $units = $this->nav->unitsOf($pool);
        $value = Money::irr($units->multipliedBy($state->navPerUnit));
        $net = $this->nav->netCapitalOf($pool);

        return view('reports.pool', [
            'state' => $state,
            'units' => $units,
            'value' => $value,
            'net' => $net,
            'pnl' => $value->minus($net),
            'donations' => Contribution::with(['member', 'type'])->where('ownership_account_id', $pool->id)->orderByDesc('contribution_date')->paginate(30),
            'unitHistory' => InvestmentUnit::where('ownership_account_id', $pool->id)->orderByDesc('id')->limit(100)->get(),
            'withdrawals' => WithdrawalRequest::where('ownership_account_id', $pool->id)->latest('requested_at')->get(),
            'byDonor' => Contribution::with('member')->where('ownership_account_id', $pool->id)->where('status', 'approved')
                ->selectRaw('member_id, SUM(amount_irr) AS total, COUNT(*) AS cnt')->groupBy('member_id')->get(),
        ]);
    }

    public function assets()
    {
        $state = $this->nav->state();
        $assets = Asset::with('category')->orderBy('name')->get();
        $realized = InvestmentTransaction::where('status', 'approved')->where('type', 'sell')
            ->groupBy('asset_id')->selectRaw('asset_id, SUM(realized_pnl_irr) AS r')->pluck('r', 'asset_id');

        return view('reports.assets', ['assets' => $assets, 'state' => $state, 'realized' => $realized]);
    }

    public function commitments(Request $request)
    {
        $viewer = $request->user();
        [$cy, $cm] = Jalali::currentYearMonth();
        $y = $request->integer('year') ?: $cy;
        $m = $request->integer('month') ?: $cm;
        $users = $viewer->isAdmin() ? User::active()->orderBy('name')->get() : collect([$viewer]);
        $rows = $users->map(fn ($u) => ['user' => $u, 's' => $this->commitments->statusFor($u, $y, $m)]);

        if ($viewer->isAdmin() && $request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['عضو', 'ماه', 'تعهد (ریال)', 'معافیت (ریال)', 'پرداخت‌شده (ریال)', 'وضعیت']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['user']->name, $r['s']['label'], $r['s']['commitment'], $r['s']['exemption'], $r['s']['paid'], $r['s']['status']]);
                }
                fclose($out);
            }, "amos-commitments-{$y}-{$m}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        // Overdue: unpaid/partial commitments in the last 6 months for active members.
        $overdue = [];
        if ($viewer->isAdmin()) {
            foreach ($users as $u) {
                for ($i = 1; $i <= 6; $i++) {
                    [$yy, $mm] = Jalali::addMonths($cy, $cm, -$i);
                    $s = $this->commitments->statusFor($u, $yy, $mm);
                    $joined = $u->joined_at ? Jalali::fromDate($u->joined_at) : null;
                    if ($joined && ($yy * 12 + $mm) < ($joined[0] * 12 + $joined[1])) {
                        continue;
                    }
                    if (in_array($s['status'], ['unpaid', 'partial'], true)) {
                        $overdue[] = ['user' => $u, 's' => $s];
                    }
                }
            }
        }

        return view('reports.commitments', ['rows' => $rows, 'year' => $y, 'month' => $m, 'overdue' => $overdue]);
    }
}
