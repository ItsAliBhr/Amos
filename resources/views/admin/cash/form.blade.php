@extends('layouts.app')
@section('title', $account->exists ? 'ویرایش حساب نقدی' : 'حساب نقدی جدید')
@section('content')
@include('admin._settings_nav')
<div class="row justify-content-center"><div class="col-lg-6"><div class="card"><div class="card-body">
    <form method="POST" action="{{ $account->exists ? route('admin.cash.update', $account) : route('admin.cash.store') }}">
        @csrf @if ($account->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">نام حساب</label><input name="name" class="form-control" required value="{{ old('name', $account->name) }}"></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">نوع</label><select name="account_type" class="form-select">@foreach (\App\Models\CashAccount::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('account_type', $account->account_type) == $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">بانک</label><input name="bank_name" class="form-control" value="{{ old('bank_name', $account->bank_name) }}"></div>
        </div>
        <div class="mb-3"><label class="form-label">شماره حساب / شبا (رمزنگاری‌شده)</label><input name="account_number" class="form-control ltr" placeholder="{{ $account->exists && $account->maskedNumber() ? $account->maskedNumber().' — برای تغییر وارد کنید' : '' }}"></div>
        @unless ($account->exists)
            <div class="mb-3"><label class="form-label">موجودی افتتاحیه</label>@include('partials.money-input', ['name' => 'opening_balance', 'required' => false])
                <div class="form-text">موجودی افتتاحیه یک بار با سند دفتر کل ثبت و به سبد مشترک Pool تخصیص می‌یابد (مالک فردی ندارد). اگر این مبلغ متعلق به اعضاست، آن را صفر بگذارید و به‌صورت واریزی اعضا ثبت کنید.</div></div>
        @else
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" @checked($account->is_active)><label class="form-check-label" for="ia">فعال</label></div>
        @endunless
        <button class="btn btn-primary" type="submit">ذخیره</button> <a href="{{ route('admin.cash.index') }}" class="btn btn-light">انصراف</a>
    </form>
</div></div></div></div>
@endsection
