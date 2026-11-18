@extends('layouts.app')
@section('title', 'اطلاعات پایه و تنظیمات')
@section('content')
@include('admin._settings_nav')
<form method="POST" action="{{ route('admin.settings.update') }}">
@csrf @method('PUT')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">مشخصات صندوق</div><div class="card-body row g-3">
            <div class="col-12"><label class="form-label">نام صندوق</label><input name="fund_name" class="form-control" required value="{{ old('fund_name', $s->fund_name) }}"></div>
            <div class="col-12"><label class="form-label">توضیح</label><textarea name="fund_description" class="form-control" rows="2">{{ old('fund_description', $s->fund_description) }}</textarea></div>
            <div class="col-md-4"><label class="form-label">واحد پایه</label><input class="form-control" value="ریال (IRR)" disabled></div>
            <div class="col-md-4"><label class="form-label">واحد نمایشی</label><select name="display_currency" class="form-select"><option value="IRT" @selected($s->display_currency === 'IRT')>تومان</option><option value="IRR" @selected($s->display_currency === 'IRR')>ریال</option></select></div>
            <div class="col-md-4"><label class="form-label">منطقه زمانی</label><input name="timezone" class="form-control ltr" value="{{ old('timezone', $s->timezone) }}"></div>
            <div class="col-md-6"><label class="form-label">قیمت اولیه هر واحد</label><input class="form-control ltr" value="{{ num($s->initial_unit_price_irr, 8) }} ریال" disabled><div class="form-text">پس از نصب ثابت است.</div></div>
            <div class="col-md-6"><label class="form-label">حداکثر حجم فایل (KB)</label><input name="max_upload_kb" type="number" class="form-control" value="{{ old('max_upload_kb', $s->max_upload_kb) }}"></div>
            <div class="col-md-6"><label class="form-label">هشدار قدیمی بودن ارزش‌گذاری (روز)</label><input name="valuation_stale_days" type="number" class="form-control" value="{{ old('valuation_stale_days', $s->valuation_stale_days) }}"></div>
            <div class="col-md-6"><label class="form-label">نمایش اطلاعات کل صندوق به اعضا</label><select name="member_dashboard_visibility" class="form-select">@foreach (\App\Models\FundSetting::VISIBILITY as $k => $v)<option value="{{ $k }}" @selected($s->member_dashboard_visibility === $k)>{{ $v }}</option>@endforeach</select></div>
        </div></div>
        <div class="card"><div class="card-header">برداشت</div><div class="card-body row g-3">
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="withdrawal_enabled" value="1" id="we" @checked($s->withdrawal_enabled)><label class="form-check-label" for="we">ثبت درخواست برداشت توسط اعضا فعال باشد</label></div></div>
            <div class="col-md-6"><label class="form-label">کارمزد برداشت (درصد)</label><input name="withdrawal_fee_percent" type="number" step="0.0001" min="0" max="100" class="form-control" value="{{ old('withdrawal_fee_percent', (float) $s->withdrawal_fee_percent) }}"></div>
            <div class="col-md-6"><label class="form-label">حداقل مبلغ برداشت</label>@include('partials.money-input', ['name' => 'withdrawal_min', 'value' => money_input($s->withdrawal_min_irr), 'required' => false])</div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">واریزی و تعهد ماهانه</div><div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">تعهد ماهانه پیش‌فرض</label>@include('partials.money-input', ['name' => 'default_monthly_commitment', 'value' => money_input($s->default_monthly_commitment_irr)])</div>
            <div class="col-md-6"><label class="form-label">روز سررسید ماهانه</label><input name="due_day" type="number" min="1" max="29" class="form-control" value="{{ old('due_day', $s->due_day) }}"></div>
            <div class="col-md-6"><label class="form-label">حداقل مبلغ واریزی</label>@include('partials.money-input', ['name' => 'min_contribution', 'value' => money_input($s->min_contribution_irr), 'required' => false])</div>
            <div class="col-12">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_partial_payment" value="1" id="ap" @checked($s->allow_partial_payment)><label class="form-check-label" for="ap">پرداخت ناقص مجاز است</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_early_payment" value="1" id="ae" @checked($s->allow_early_payment)><label class="form-check-label" for="ae">پرداخت زودهنگام (ماه‌های آینده) مجاز است</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_late_payment" value="1" id="al" @checked($s->allow_late_payment)><label class="form-check-label" for="al">پرداخت با تأخیر (ماه‌های گذشته) مجاز است</label></div>
            </div>
        </div></div>
        <div class="card"><div class="card-header">سقف واریزی اختصاصی</div><div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">سقف ماهانه (خالی = بدون سقف)</label>@include('partials.money-input', ['name' => 'monthly_contribution_cap', 'value' => money_input($s->monthly_contribution_cap_irr), 'required' => false])</div>
            <div class="col-md-6"><label class="form-label">محدوده اعمال سقف</label><select name="cap_scope" class="form-select">@foreach (\App\Models\FundSetting::CAP_SCOPES as $k => $v)<option value="{{ $k }}" @selected($s->cap_scope === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">رفتار در صورت عبور از سقف</label><select name="cap_mode" class="form-select">@foreach (\App\Models\FundSetting::CAP_MODES as $k => $v)<option value="{{ $k }}" @selected($s->cap_mode === $k)>{{ $v }}</option>@endforeach</select>
                <div class="form-text">سقف روی مجموع واریزی ثابت + اضافه هر عضو اعمال می‌شود؛ کمک اهدایی مستثناست.</div></div>
        </div></div>
    </div>
</div>
<div class="mt-3"><button class="btn btn-primary" type="submit">ذخیره تنظیمات</button></div>
</form>
<div class="card mt-3"><div class="card-header">انواع واریزی</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>کد</th><th>نام</th><th>مالکیت</th><th>در سقف</th><th>در تعهد</th><th>فقط مدیر</th></tr></thead>
    <tbody>@foreach ($types as $t)<tr><td class="ltr text-end">{{ $t->code }}</td><td>{{ $t->name }}</td><td>{{ $t->ownership_type === 'pool' ? 'Pool' : 'عضو' }}</td><td>{{ $t->counts_toward_cap ? '✓' : '—' }}</td><td>{{ $t->counts_toward_commitment ? '✓' : '—' }}</td><td>{{ $t->admin_only ? '✓' : '—' }}</td></tr>@endforeach</tbody>
</table></div></div>
@endsection
