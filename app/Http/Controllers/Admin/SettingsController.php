<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContributionType;
use App\Models\FundSetting;
use App\Rules\Amount;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['s' => FundSetting::current(), 'types' => ContributionType::all()]);
    }

    public function update(Request $request)
    {
        $s = FundSetting::current();
        $data = $request->validate([
            'fund_name' => ['required', 'string', 'max:255'],
            'fund_description' => ['nullable', 'string', 'max:2000'],
            'display_currency' => ['required', Rule::in(['IRT', 'IRR'])],
            'default_monthly_commitment' => ['required', new Amount(true)],
            'monthly_contribution_cap' => ['nullable', new Amount(true)],
            'cap_scope' => ['required', Rule::in(array_keys(FundSetting::CAP_SCOPES))],
            'cap_mode' => ['required', Rule::in(array_keys(FundSetting::CAP_MODES))],
            'due_day' => ['required', 'integer', 'between:1,29'],
            'allow_partial_payment' => ['nullable', 'boolean'],
            'allow_early_payment' => ['nullable', 'boolean'],
            'allow_late_payment' => ['nullable', 'boolean'],
            'min_contribution' => ['nullable', new Amount(true)],
            'member_dashboard_visibility' => ['required', Rule::in(array_keys(FundSetting::VISIBILITY))],
            'withdrawal_enabled' => ['nullable', 'boolean'],
            'withdrawal_fee_percent' => ['required', 'numeric', 'between:0,100'],
            'withdrawal_min' => ['nullable', new Amount(true)],
            'max_upload_kb' => ['required', 'integer', 'between:100,20480'],
            'valuation_stale_days' => ['required', 'integer', 'between:1,365'],
            'timezone' => ['required', 'timezone'],
        ]);

        // Amounts are typed in the CURRENT display currency; convert before switching unit.
        $toIrr = fn ($v) => $v === null || $v === '' ? null : (string) Money::fromInput($v);

        $old = $s->toArray();
        $s->fill([
            'fund_name' => $data['fund_name'],
            'fund_description' => $data['fund_description'] ?? null,
            'default_monthly_commitment_irr' => $toIrr($data['default_monthly_commitment']),
            'monthly_contribution_cap_irr' => $toIrr($data['monthly_contribution_cap'] ?? null),
            'cap_scope' => $data['cap_scope'],
            'cap_mode' => $data['cap_mode'],
            'due_day' => $data['due_day'],
            'allow_partial_payment' => $request->boolean('allow_partial_payment'),
            'allow_early_payment' => $request->boolean('allow_early_payment'),
            'allow_late_payment' => $request->boolean('allow_late_payment'),
            'min_contribution_irr' => $toIrr($data['min_contribution'] ?? null) ?? '0',
            'member_dashboard_visibility' => $data['member_dashboard_visibility'],
            'withdrawal_enabled' => $request->boolean('withdrawal_enabled'),
            'withdrawal_fee_percent' => $data['withdrawal_fee_percent'],
            'withdrawal_min_irr' => $toIrr($data['withdrawal_min'] ?? null) ?? '0',
            'max_upload_kb' => $data['max_upload_kb'],
            'valuation_stale_days' => $data['valuation_stale_days'],
            'timezone' => $data['timezone'],
            'updated_by' => $request->user()->id,
        ]);
        $s->display_currency = $data['display_currency'];
        $s->save();
        [$o, $n] = AuditLogger::changes($s);
        if ($n) {
            AuditLogger::log('settings.updated', $s, $o, $n);
        }

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
