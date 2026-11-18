@extends('layouts.app')
@section('title', 'ثبت واریزی')
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<div class="row justify-content-center">
<div class="col-xl-8">
<div class="card">
    <div class="card-header">ثبت / اعلام واریزی</div>
    <div class="card-body">
        <div class="alert alert-light border small">
            <i class="bi bi-info-circle"></i>
            واریزی ثابت و اضافه به <strong>سبد اختصاصی</strong> عضو و کمک اهدایی به <strong>سبد مشترک Pool</strong> تعلق می‌گیرد. واریزی تا تأیید مدیر هیچ اثر مالی ندارد.
            @if ($settings->monthly_contribution_cap_irr)
                سقف ماهانه واریزی اختصاصی: <span class="money">{{ money($settings->monthly_contribution_cap_irr) }}</span>
                @unless($admin) (استفاده‌شده در ماه جاری: <span class="money">{{ money($capUsage) }}</span>)@endunless
            @endif
        </div>
        <form method="POST" action="{{ route('contributions.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', idem_key()) }}">
            <div class="row g-3">
                @if ($admin)
                <div class="col-md-6">
                    <label class="form-label">عضو پرداخت‌کننده</label>
                    <select name="member_id" class="form-select">
                        <option value="">— (برای Pool بدون عضو مشخص)</option>
                        @foreach ($members as $m)<option value="{{ $m->id }}" @selected(old('member_id') == $m->id)>{{ $m->name }}</option>@endforeach
                    </select>
                    <div class="form-text">مدیر می‌تواند از طرف عضو ثبت کند.</div>
                </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label">نوع واریزی <span class="text-danger">*</span></label>
                    <select name="type_code" class="form-select" data-contribution-type required>
                        @foreach ($types as $t)<option value="{{ $t->code }}" @selected(old('type_code', 'monthly') == $t->code)>{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                @if ($admin)
                <div class="col-md-6" data-contrib-show="adjustment">
                    <label class="form-label">سبد مقصد سند اصلاحی</label>
                    <select name="target" class="form-select">
                        <option value="member" @selected(old('target') === 'member')>سبد اختصاصی عضو</option>
                        <option value="pool" @selected(old('target') === 'pool')>سبد مشترک Pool</option>
                    </select>
                </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label">مبلغ <span class="text-danger">*</span></label>
                    @include('partials.money-input', ['name' => 'amount'])
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاریخ پرداخت <span class="text-danger">*</span></label>
                    @include('partials.date-input', ['name' => 'contribution_date'])
                </div>
                <div class="col-md-6" data-contrib-show="monthly,extra">
                    <label class="form-label">ماه تعهد</label>
                    <div class="input-group">
                        <select name="commitment_month" class="form-select">
                            @foreach (\App\Support\Jalali::MONTHS as $i => $name)<option value="{{ $i }}" @selected(old('commitment_month', $month) == $i)>{{ $name }}</option>@endforeach
                        </select>
                        <input type="number" name="commitment_year" class="form-control" value="{{ old('commitment_year', $year) }}" min="1390" max="1500">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">حساب مقصد</label>
                    <select name="destination_account_id" class="form-select">
                        <option value="">— انتخاب —</option>
                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('destination_account_id') == $a->id)>{{ $a->name }} {{ $a->bank_name ? '('.$a->bank_name.')' : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">روش پرداخت</label>
                    <select name="payment_method" class="form-select">
                        <option value="">—</option>
                        @foreach (\App\Models\Contribution::PAYMENT_METHODS as $k => $v)<option value="{{ $k }}" @selected(old('payment_method') == $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">کد پیگیری</label>
                    <input name="tracking_code" class="form-control ltr" value="{{ old('tracking_code') }}" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">رسید (JPG/PNG/WEBP/PDF، حداکثر {{ num($settings->max_upload_kb / 1024, 1) }} مگابایت)</label>
                    <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <div class="col-12">
                    <label class="form-label">توضیح</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="2000">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> ثبت و ارسال برای تأیید</button>
                <button type="submit" name="as_draft" value="1" class="btn btn-outline-secondary">ذخیره پیش‌نویس</button>
                <a href="{{ route('contributions.index') }}" class="btn btn-light">انصراف</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
