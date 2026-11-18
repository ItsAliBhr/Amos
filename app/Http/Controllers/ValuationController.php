<?php

namespace App\Http\Controllers;

use App\Domain\Investments\InvestmentService;
use App\Domain\Valuations\ValuationService;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Rules\Amount;
use App\Rules\JalaliDate;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Http\Request;

class ValuationController extends Controller
{
    public function __construct(private ValuationService $service, private InvestmentService $investments)
    {
    }

    public function index(Request $request)
    {
        return view('valuations.index', [
            'valuations' => AssetValuation::with(['asset', 'creator'])
                ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->integer('asset_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->orderByDesc('valuation_date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'assets' => Asset::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $assets = Asset::with('category')->orderBy('name')->get();

        return view('valuations.create', [
            'assets' => $assets,
            'holdings' => $assets->mapWithKeys(fn ($a) => [$a->id => $this->investments->holding($a->id)['quantity']]),
            'selected' => $request->integer('asset_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'valuation_date' => ['required', new JalaliDate()],
            'unit_price' => ['required', new Amount()],
            'source' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'approve_now' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], [], ['asset_id' => 'دارایی', 'valuation_date' => 'تاریخ', 'unit_price' => 'قیمت واحد']);

        $v = $this->service->create([
            'asset_id' => $data['asset_id'],
            'valuation_date' => Jalali::parse($data['valuation_date']),
            'unit_price_irr' => Money::fromInput($data['unit_price']),
            'source' => $data['source'] ?? null,
            'note' => $data['note'] ?? null,
            'approve_now' => $request->boolean('approve_now'),
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ], $request->user());

        return redirect()->route('valuations.index')->with('success', $v->status === 'approved' ? 'ارزش‌گذاری ثبت و تأیید شد.' : 'ارزش‌گذاری ثبت شد و در انتظار تأیید است.');
    }

    public function approve(Request $request, AssetValuation $valuation)
    {
        $this->service->approve($valuation, $request->user());

        return back()->with('success', 'ارزش‌گذاری تأیید شد.');
    }

    public function reject(Request $request, AssetValuation $valuation)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'دلیل رد']);
        $this->service->reject($valuation, $request->user(), $data['reason']);

        return back()->with('success', 'ارزش‌گذاری رد شد.');
    }
}
