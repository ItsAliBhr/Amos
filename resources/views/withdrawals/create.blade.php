@extends('layouts.app')
@section('title', $pool ? 'برداشت از Pool' : 'درخواست برداشت')
@section('content')
<div class="row justify-content-center"><div class="col-lg-6">
<div class="card"><div class="card-header">{{ $pool ? 'ثبت برداشت از سبد مشترک Pool' : 'ثبت درخواست برداشت' }}</div><div class="card-body">
    @if (! $pool && ! $settings->withdrawal_enabled)
        <div class="alert alert-warning">امکان ثبت درخواست برداشت در حال حاضر غیرفعال است.</div>
    @endif
    <div class="alert alert-light border small">
        ارزش فعلی {{ $pool ? 'Pool' : 'سبد شما' }}: <strong class="money">{{ money($value) }}</strong><br>
        @if ((float) $settings->withdrawal_fee_percent)کارمزد برداشت: {{ num($settings->withdrawal_fee_percent, 2) }}٪<br>@endif
        درخواست به‌تنهایی مانده را کم نمی‌کند. پس از تأیید مدیر و هنگام پرداخت، واحدهای معادل مبلغ با ارزش روز ابطال می‌شوند. در صورت کمبود نقدینگی، درخواست در انتظار تأمین نقدینگی می‌ماند.
    </div>
    <form method="POST" action="{{ $pool ? route('withdrawals.pool.store') : route('withdrawals.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', idem_key()) }}">
        <div class="mb-3"><label class="form-label">مبلغ</label>@include('partials.money-input', ['name' => 'amount'])</div>
        <div class="mb-3"><label class="form-label">{{ $pool ? 'دلیل برداشت' : 'توضیح' }} @if($pool)<span class="text-danger">*</span>@endif</label><textarea name="note" class="form-control" rows="3" {{ $pool ? 'required' : '' }}>{{ old('note') }}</textarea></div>
        <button class="btn btn-primary" type="submit" @disabled(! $pool && ! $settings->withdrawal_enabled)>ثبت</button>
        <a href="{{ route('withdrawals.index') }}" class="btn btn-light">انصراف</a>
    </form>
</div></div>
</div></div>
@endsection
