@extends('layouts.app')
@section('title', 'ثبت ارزش‌گذاری')
@section('content')
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-header">ثبت ارزش‌گذاری روز دارایی</div><div class="card-body">
    <div class="alert alert-light border small">هر ارزش‌گذاری یک رکورد جدید است و سابقه قبلی بازنویسی نمی‌شود. آخرین ارزش‌گذاری تأییدشده مبنای محاسبه NAV است. مقدار مبنا از موجودی فعلی دارایی محاسبه می‌شود.</div>
    <form method="POST" action="{{ route('valuations.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', idem_key()) }}">
        <div class="mb-3"><label class="form-label">دارایی</label>
            <select name="asset_id" class="form-select" required>
                @foreach ($assets as $a)<option value="{{ $a->id }}" @selected(old('asset_id', $selected) == $a->id)>{{ $a->name }} ({{ $a->symbol }}) — موجودی: {{ num($holdings[$a->id], $a->decimal_places) }} {{ $a->unit_name }}</option>@endforeach
            </select></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">تاریخ ارزش‌گذاری</label>@include('partials.date-input', ['name' => 'valuation_date'])</div>
            <div class="col-md-6"><label class="form-label">قیمت هر واحد</label>@include('partials.money-input', ['name' => 'unit_price'])</div>
            <div class="col-md-6"><label class="form-label">منبع قیمت</label><input name="source" class="form-control" value="{{ old('source') }}" placeholder="مثلاً: سایت رسمی بورس، اتحادیه طلا"></div>
            <div class="col-12"><label class="form-label">توضیح</label><textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="approve_now" value="1" id="approve_now" checked><label class="form-check-label" for="approve_now">ثبت و تأیید هم‌زمان</label></div></div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">ثبت ارزش‌گذاری</button><a href="{{ route('valuations.index') }}" class="btn btn-light">انصراف</a></div>
    </form>
</div></div>
</div></div>
@endsection
