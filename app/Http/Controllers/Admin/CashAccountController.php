<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounting\CashAccountService;
use App\Domain\Accounting\NavService;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Rules\Amount;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashAccountController extends Controller
{
    public function __construct(private CashAccountService $service, private NavService $nav)
    {
    }

    public function index()
    {
        $state = $this->nav->state();

        return view('admin.cash.index', ['accounts' => CashAccount::orderBy('name')->get(), 'balances' => $state->cashByAccount]);
    }

    public function create()
    {
        return view('admin.cash.form', ['account' => new CashAccount(['is_active' => true, 'account_type' => 'bank'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in(array_keys(CashAccount::TYPES))],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'opening_balance' => ['nullable', new Amount(true)],
        ], [], ['name' => 'نام', 'opening_balance' => 'موجودی افتتاحیه']);
        $data['opening_balance_irr'] = ! empty($data['opening_balance']) ? Money::fromInput($data['opening_balance']) : 0;
        $this->service->create($data, $request->user());

        return redirect()->route('admin.cash.index')->with('success', 'حساب نقدی ایجاد شد.');
    }

    public function edit(CashAccount $cashAccount)
    {
        return view('admin.cash.form', ['account' => $cashAccount]);
    }

    public function update(Request $request, CashAccount $cashAccount)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in(array_keys(CashAccount::TYPES))],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $this->service->update($cashAccount, $data, $request->user());

        return redirect()->route('admin.cash.index')->with('success', 'حساب نقدی به‌روزرسانی شد.');
    }
}
