<?php

namespace App\Http\Controllers;

use App\Domain\Investments\InvestmentService;
use App\Models\Asset;
use App\Models\CashAccount;
use App\Models\FundSetting;
use App\Models\Idea;
use App\Models\InvestmentTransaction;
use App\Rules\Amount;
use App\Rules\JalaliDate;
use App\Services\AttachmentService;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function __construct(private InvestmentService $service)
    {
    }

    public static function memberCanViewFund(Request $request): bool
    {
        return $request->user()->isAdmin() || FundSetting::current()->member_dashboard_visibility === 'full';
    }

    public function index(Request $request)
    {
        abort_unless(self::memberCanViewFund($request), 403, 'نمایش این بخش برای اعضا غیرفعال است.');
        $q = InvestmentTransaction::with(['asset', 'cashAccount', 'toCashAccount'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->integer('asset_id')))
            ->when($request->filled('from') && Jalali::isValid($request->input('from')), fn ($q) => $q->where('transaction_date', '>=', Jalali::parse($request->input('from'))->format('Y-m-d')))
            ->when($request->filled('to') && Jalali::isValid($request->input('to')), fn ($q) => $q->where('transaction_date', '<=', Jalali::parse($request->input('to'))->format('Y-m-d')));
        if (! $request->user()->isAdmin()) {
            $q->where('status', 'approved');
        }

        return view('investments.index', [
            'transactions' => $q->orderByDesc('transaction_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'assets' => Asset::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('investments.create', [
            'assets' => Asset::active()->with('category')->orderBy('name')->get(),
            'accounts' => CashAccount::active()->get(),
            'ideas' => Idea::where('status', 'approved')->get(),
            'type' => $request->input('type', 'buy'),
            'cash' => collect(CashAccount::active()->get())->mapWithKeys(fn ($a) => [$a->id => $this->service->cashBalance($a->id)]),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $isTrade = in_array($type, ['buy', 'sell'], true);
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(InvestmentTransaction::TYPES))],
            'asset_id' => [$isTrade ? 'required' : 'nullable', 'integer', 'exists:assets,id'],
            'quantity' => [$isTrade ? 'required' : 'nullable', new Amount()],
            'unit_price' => [$isTrade ? 'required' : 'nullable', new Amount()],
            'gross_amount' => [$isTrade ? 'nullable' : 'required', new Amount()],
            'fee_amount' => ['nullable', new Amount(true)],
            'transaction_date' => ['required', new JalaliDate()],
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'to_cash_account_id' => [$type === 'transfer' ? 'required' : 'nullable', 'integer', 'exists:cash_accounts,id'],
            'expense_category' => ['nullable', 'in:'.implode(',', array_keys(InvestmentTransaction::EXPENSE_CATEGORIES))],
            'idea_id' => ['nullable', 'integer', 'exists:ideas,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachment' => AttachmentService::rules(),
            'approve_now' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], [], ['asset_id' => 'دارایی', 'quantity' => 'مقدار', 'unit_price' => 'قیمت واحد', 'gross_amount' => 'مبلغ', 'fee_amount' => 'کارمزد',
            'transaction_date' => 'تاریخ', 'cash_account_id' => 'حساب نقدی', 'to_cash_account_id' => 'حساب مقصد']);

        if (! empty($data['idempotency_key']) && ($existing = InvestmentTransaction::where('idempotency_key', $data['idempotency_key'])->first())) {
            return redirect()->route('investments.show', $existing)->with('info', 'این عملیات قبلاً ثبت شده است.');
        }

        $attachment = AttachmentService::store($request->file('attachment'), $request->user(), 'investments');
        $tx = $this->service->create([
            'type' => $data['type'],
            'asset_id' => $data['asset_id'] ?? null,
            'quantity' => $isTrade ? Money::normalizeNumber($data['quantity']) : 0,
            'unit_price_irr' => $isTrade ? Money::fromInput($data['unit_price']) : 0,
            'gross_amount_irr' => ! $isTrade ? Money::fromInput($data['gross_amount']) : null,
            'fee_amount_irr' => ! empty($data['fee_amount']) ? Money::fromInput($data['fee_amount']) : 0,
            'transaction_date' => Jalali::parse($data['transaction_date']),
            'cash_account_id' => $data['cash_account_id'],
            'to_cash_account_id' => $data['to_cash_account_id'] ?? null,
            'expense_category' => $data['expense_category'] ?? null,
            'idea_id' => $data['idea_id'] ?? null,
            'description' => $data['description'] ?? null,
            'attachment_id' => $attachment?->id,
            'approve_now' => $request->boolean('approve_now'),
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ], $request->user());
        AttachmentService::attachTo($attachment, $tx);

        return redirect()->route('investments.show', $tx)->with('success', $tx->status === 'approved' ? 'عملیات ثبت و تأیید شد.' : 'عملیات ثبت شد و در انتظار تأیید است.');
    }

    public function show(Request $request, InvestmentTransaction $investment)
    {
        abort_unless(self::memberCanViewFund($request), 403);
        abort_unless($request->user()->isAdmin() || $investment->status === 'approved', 404);
        $investment->load(['asset', 'cashAccount', 'toCashAccount', 'submitter', 'approver', 'attachment', 'idea']);
        $ledger = $investment->ledger_transaction_id ? \App\Models\LedgerTransaction::with('entries')->find($investment->ledger_transaction_id) : null;

        return view('investments.show', ['t' => $investment, 'ledger' => $ledger]);
    }

    public function approve(Request $request, InvestmentTransaction $investment)
    {
        $this->service->approve($investment, $request->user());

        return back()->with('success', 'عملیات تأیید و در دفتر کل ثبت شد.');
    }

    public function reject(Request $request, InvestmentTransaction $investment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل رد']);
        $this->service->reject($investment, $request->user(), $data['reason']);

        return back()->with('success', 'عملیات رد شد.');
    }

    public function reverse(Request $request, InvestmentTransaction $investment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل برگشت']);
        $this->service->reverse($investment, $request->user(), $data['reason']);

        return back()->with('success', 'سند برگشتی ثبت شد.');
    }
}
