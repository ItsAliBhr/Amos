<?php

namespace App\Http\Controllers;

use App\Domain\Contributions\ContributionService;
use App\Models\CashAccount;
use App\Models\Contribution;
use App\Models\ContributionType;
use App\Models\FundSetting;
use App\Models\User;
use App\Rules\Amount;
use App\Rules\JalaliDate;
use App\Services\AttachmentService;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionController extends Controller
{
    public function __construct(private ContributionService $service)
    {
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $q = Contribution::query()->with(['member', 'type', 'ownershipAccount', 'destinationAccount']);
        if (! $user->isAdmin()) {
            $q->where(fn ($w) => $w->where('member_id', $user->id)->orWhere('submitted_by', $user->id));
        } elseif ($request->filled('member_id')) {
            $q->where('member_id', $request->integer('member_id'));
        }
        if ($request->filled('type')) {
            $q->whereHas('type', fn ($t) => $t->where('code', $request->string('type')));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('from') && Jalali::isValid($request->input('from'))) {
            $q->where('contribution_date', '>=', Jalali::parse($request->input('from'))->format('Y-m-d'));
        }
        if ($request->filled('to') && Jalali::isValid($request->input('to'))) {
            $q->where('contribution_date', '<=', Jalali::parse($request->input('to'))->format('Y-m-d'));
        }
        if ($request->filled('cy')) {
            $q->where('commitment_year', $request->integer('cy'));
        }
        if ($request->filled('cm')) {
            $q->where('commitment_month', $request->integer('cm'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('tracking_code', 'like', $term)->orWhere('description', 'like', $term));
        }
        $sort = in_array($request->input('sort'), ['contribution_date', 'amount_irr', 'created_at', 'status'], true) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $base = $this->filtered($request);
        $totals = [
            'member' => (clone $base)->reorder()->where('status', 'approved')->whereHas('ownershipAccount', fn ($a) => $a->where('owner_type', 'member'))->sum('amount_irr'),
            'pool' => (clone $base)->reorder()->where('status', 'approved')->whereHas('ownershipAccount', fn ($a) => $a->where('owner_type', 'pool'))->sum('amount_irr'),
            'pending' => (clone $base)->reorder()->where('status', 'pending')->sum('amount_irr'),
        ];

        return view('contributions.index', [
            'contributions' => $base->paginate(20)->withQueryString(),
            'types' => ContributionType::all(),
            'members' => $request->user()->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'totals' => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->get();
        $filename = 'amos-contributions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Persian text correctly
            fputcsv($out, ['شناسه', 'عضو', 'نوع', 'سبد مالکیت', 'مبلغ (ریال)', 'تاریخ پرداخت', 'ماه تعهد', 'حساب مقصد', 'کد پیگیری', 'وضعیت', 'واحد صادرشده', 'تاریخ تأیید']);
            foreach ($rows as $c) {
                fputcsv($out, [
                    $c->id,
                    $c->member?->name ?? '—',
                    $c->type->name,
                    $c->ownershipAccount?->displayName(),
                    (string) $c->amount_irr,
                    Jalali::format($c->contribution_date),
                    $c->commitment_year ? $c->commitment_year.'/'.str_pad($c->commitment_month, 2, '0', STR_PAD_LEFT) : '',
                    $c->destinationAccount?->name,
                    self::csvSafe($c->tracking_code),
                    $c->status,
                    (string) $c->units_issued,
                    $c->approved_at ? Jalali::format($c->approved_at, true) : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection. */
    public static function csvSafe(?string $v): string
    {
        $v = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    public function create(Request $request)
    {
        $user = $request->user();
        [$y, $m] = Jalali::currentYearMonth();
        $types = ContributionType::where('is_active', true)->when(! $user->isAdmin(), fn ($q) => $q->where('admin_only', false))->get();

        return view('contributions.create', [
            'types' => $types,
            'members' => $user->isAdmin() ? User::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'accounts' => CashAccount::active()->get(),
            'year' => $y,
            'month' => $m,
            'settings' => FundSetting::current(),
            'capUsage' => $this->service->capUsage($user->id, $y, $m),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'type_code' => ['required', 'string', 'exists:contribution_types,code'],
            'member_id' => [$user->isAdmin() ? 'nullable' : 'prohibited', 'integer', 'exists:users,id'],
            'target' => ['nullable', 'in:member,pool'],
            'amount' => ['required', new Amount()],
            'contribution_date' => ['required', new JalaliDate()],
            'commitment_year' => ['nullable', 'integer', 'between:1390,1500'],
            'commitment_month' => ['nullable', 'integer', 'between:1,12'],
            'destination_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'payment_method' => ['nullable', 'in:'.implode(',', array_keys(Contribution::PAYMENT_METHODS))],
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => AttachmentService::rules(),
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'as_draft' => ['nullable', 'boolean'],
        ], [], [
            'type_code' => 'نوع واریزی', 'amount' => 'مبلغ', 'contribution_date' => 'تاریخ پرداخت', 'receipt' => 'رسید',
            'member_id' => 'عضو', 'tracking_code' => 'کد پیگیری',
        ]);

        if (! empty($data['idempotency_key']) && ($existing = Contribution::where('idempotency_key', $data['idempotency_key'])->first())) {
            return redirect()->route('contributions.show', $existing)->with('info', 'این واریزی قبلاً ثبت شده است.');
        }

        $attachment = AttachmentService::store($request->file('receipt'), $user, 'receipts');
        $c = $this->service->create([
            'member_id' => $user->isAdmin() ? ($data['member_id'] ?? null) : $user->id,
            'type_code' => $data['type_code'],
            'target' => $data['target'] ?? null,
            'amount_irr' => Money::fromInput($data['amount']),
            'contribution_date' => Jalali::parse($data['contribution_date']),
            'commitment_year' => $data['commitment_year'] ?? null,
            'commitment_month' => $data['commitment_month'] ?? null,
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'tracking_code' => $data['tracking_code'] ?? null,
            'description' => $data['description'] ?? null,
            'receipt_attachment_id' => $attachment?->id,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'as_draft' => $request->boolean('as_draft'),
        ], $user);
        AttachmentService::attachTo($attachment, $c);

        $msg = $c->status === 'draft' ? 'پیش‌نویس واریزی ذخیره شد.' : 'واریزی ثبت شد و در انتظار تأیید مدیر است.';
        if ($c->exceeds_cap) {
            $msg .= ' توجه: این واریزی از سقف ماهانه عبور می‌کند و نیازمند تأیید ویژه مدیر است.';
        }

        return redirect()->route('contributions.show', $c)->with('success', $msg);
    }

    public function show(Request $request, Contribution $contribution)
    {
        Gate::authorize('view', $contribution);
        $contribution->load(['member', 'type', 'ownershipAccount', 'destinationAccount', 'receipt', 'submitter', 'approver', 'ledgerTransaction.entries']);

        return view('contributions.show', [
            'c' => $contribution,
            'accounts' => CashAccount::active()->get(),
            'exceedsCap' => $contribution->status === 'pending' ? $this->service->exceedsCap($contribution, $contribution->type) : $contribution->exceeds_cap,
        ]);
    }

    public function submit(Request $request, Contribution $contribution)
    {
        Gate::authorize('cancel', $contribution);
        $this->service->submitDraft($contribution, $request->user());

        return back()->with('success', 'واریزی برای تأیید ارسال شد.');
    }

    public function cancel(Request $request, Contribution $contribution)
    {
        Gate::authorize('cancel', $contribution);
        $this->service->cancel($contribution, $request->user());

        return redirect()->route('contributions.index')->with('success', 'واریزی لغو شد.');
    }

    public function approve(Request $request, Contribution $contribution)
    {
        $data = $request->validate([
            'destination_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'cap_override' => ['nullable', 'boolean'],
        ]);
        $this->service->approve($contribution, $request->user(), $request->boolean('cap_override'), $data['destination_account_id'] ?? null);

        return back()->with('success', 'واریزی تأیید شد و واحدهای سرمایه‌گذاری صادر شد.');
    }

    public function reject(Request $request, Contribution $contribution)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل رد']);
        $this->service->reject($contribution, $request->user(), $data['reason']);

        return back()->with('success', 'واریزی رد شد.');
    }

    public function reverse(Request $request, Contribution $contribution)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل برگشت']);
        $this->service->reverse($contribution, $request->user(), $data['reason']);

        return back()->with('success', 'سند برگشتی ثبت و واحدهای مربوط ابطال شد.');
    }
}
