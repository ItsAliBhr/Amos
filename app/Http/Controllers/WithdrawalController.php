<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\NavService;
use App\Domain\Withdrawals\WithdrawalService;
use App\Models\CashAccount;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use App\Models\WithdrawalRequest;
use App\Rules\Amount;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $service, private NavService $nav)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = WithdrawalRequest::with(['member', 'ownershipAccount'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('member_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('requested_at');

        return view('withdrawals.index', ['requests' => $q->paginate(20)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $account = OwnershipAccount::forMember($request->user());
        $state = $this->nav->state();

        return view('withdrawals.create', [
            'value' => $this->nav->valueOf($account, $state),
            'settings' => FundSetting::current(),
            'pool' => false,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', new Amount()],
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], [], ['amount' => 'مبلغ']);
        $w = $this->service->request($request->user(), Money::fromInput($data['amount']), $data['note'] ?? null, $data['idempotency_key'] ?? null);

        return redirect()->route('withdrawals.show', $w)->with('success', 'درخواست برداشت ثبت شد و در انتظار بررسی مدیر است.');
    }

    public function createPool()
    {
        return view('withdrawals.create', [
            'value' => $this->nav->valueOf(OwnershipAccount::pool()),
            'settings' => FundSetting::current(),
            'pool' => true,
        ]);
    }

    public function storePool(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', new Amount()],
            'note' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], [], ['amount' => 'مبلغ', 'note' => 'دلیل برداشت']);
        $w = $this->service->requestPool($request->user(), Money::fromInput($data['amount']), $data['note'], $data['idempotency_key'] ?? null);

        return redirect()->route('withdrawals.show', $w)->with('success', 'برداشت از Pool ثبت شد؛ برای نهایی‌سازی، پرداخت را ثبت کنید.');
    }

    public function show(Request $request, WithdrawalRequest $withdrawal)
    {
        Gate::authorize('view', $withdrawal);
        $withdrawal->load(['member', 'ownershipAccount', 'reviewer', 'cashAccount']);
        $state = $this->nav->state();
        [$fee, $net] = $this->service->feeAndNet(Money::of($withdrawal->approved_amount_irr ?? $withdrawal->requested_amount_irr));

        return view('withdrawals.show', [
            'w' => $withdrawal,
            'value' => $this->nav->valueOf($withdrawal->ownershipAccount, $state),
            'fundCash' => $state->cash,
            'fee' => $fee,
            'net' => $net,
            'accounts' => CashAccount::active()->get()->map(fn ($a) => [$a, $state->cashByAccount[$a->id] ?? Money::zero()]),
        ]);
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawal)
    {
        Gate::authorize('cancel', $withdrawal);
        $this->service->cancel($withdrawal, $request->user());

        return back()->with('success', 'درخواست لغو شد.');
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal)
    {
        $data = $request->validate([
            'approved_amount' => ['nullable', new Amount()],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $w = $this->service->approve($withdrawal, $request->user(), ! empty($data['approved_amount']) ? Money::fromInput($data['approved_amount']) : null, $data['note'] ?? null);

        return back()->with('success', $w->status === 'awaiting_liquidity' ? 'درخواست تأیید شد اما نقدینگی صندوق کافی نیست؛ در انتظار تأمین نقدینگی.' : 'درخواست تأیید شد.');
    }

    public function reject(Request $request, WithdrawalRequest $withdrawal)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل رد']);
        $this->service->reject($withdrawal, $request->user(), $data['reason']);

        return back()->with('success', 'درخواست رد شد.');
    }

    public function pay(Request $request, WithdrawalRequest $withdrawal)
    {
        $data = $request->validate(['cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id']], [], ['cash_account_id' => 'حساب پرداخت']);
        $this->service->pay($withdrawal, $request->user(), (int) $data['cash_account_id']);

        return back()->with('success', 'پرداخت ثبت شد؛ واحدهای معادل ابطال و سند خروج وجه صادر شد.');
    }
}
