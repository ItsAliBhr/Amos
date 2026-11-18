@extends('layouts.app')
@section('title', 'درخواست برداشت #'.$w->id)
@section('content')
@php $user = auth()->user(); @endphp
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-header d-flex justify-content-between"><span>درخواست برداشت #{{ $w->id }}</span>{!! status_badge($w->status) !!}</div><div class="card-body">
            <dl class="row kv mb-0">
                <div class="col-sm-6"><dt>سبد</dt><dd>{{ $w->ownershipAccount->displayName() }}</dd></div>
                <div class="col-sm-6"><dt>تاریخ درخواست</dt><dd>{{ jdate($w->requested_at, true) }}</dd></div>
                <div class="col-sm-6"><dt>مبلغ درخواستی</dt><dd class="money">{{ money($w->requested_amount_irr) }}</dd></div>
                <div class="col-sm-6"><dt>مبلغ تأییدشده</dt><dd class="money">{{ $w->approved_amount_irr ? money($w->approved_amount_irr) : '—' }}</dd></div>
                <div class="col-sm-6"><dt>ارزش فعلی سبد</dt><dd class="money">{{ money($value) }}</dd></div>
                <div class="col-sm-6"><dt>کارمزد / خالص قابل پرداخت (برآورد)</dt><dd class="money">{{ money($fee) }} / {{ money($net) }}</dd></div>
                @if ($w->status === 'paid')
                    <div class="col-sm-6"><dt>پرداخت خالص</dt><dd class="money fw-bold">{{ money($w->net_paid_irr) }}</dd></div>
                    <div class="col-sm-6"><dt>واحد ابطال‌شده</dt><dd class="num">{{ num($w->units_redeemed, 8) }} (هر واحد {{ num($w->nav_per_unit_irr, 4) }} ریال)</dd></div>
                    <div class="col-sm-6"><dt>تاریخ پرداخت</dt><dd>{{ jdate($w->paid_at, true) }} از {{ $w->cashAccount?->name }}</dd></div>
                @endif
                @if ($w->request_note)<div class="col-12"><dt>توضیح درخواست</dt><dd>{{ $w->request_note }}</dd></div>@endif
                @if ($w->admin_note)<div class="col-12"><dt>یادداشت مدیر</dt><dd>{{ $w->admin_note }}</dd></div>@endif
                @if ($w->reviewer)<div class="col-12"><dt>بررسی‌کننده</dt><dd>{{ $w->reviewer->name }} — {{ jdate($w->reviewed_at, true) }}</dd></div>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header">عملیات</div><div class="card-body d-grid gap-3">
            @if ($user->isAdmin())
                @if ($w->status === 'pending')
                    <form method="POST" action="{{ route('withdrawals.approve', $w) }}" data-confirm="درخواست تأیید شود؟">
                        @csrf
                        <label class="form-label small">مبلغ تأییدشده (خالی = مبلغ درخواستی)</label>
                        @include('partials.money-input', ['name' => 'approved_amount', 'required' => false, 'value' => money_input($w->requested_amount_irr)])
                        <textarea name="note" class="form-control my-2" rows="2" placeholder="یادداشت (اختیاری)"></textarea>
                        <button class="btn btn-success w-100" type="submit">تأیید درخواست</button>
                    </form>
                @endif
                @if (in_array($w->status, ['approved', 'awaiting_liquidity']))
                    <div class="small text-muted">نقدینگی کل صندوق: <span class="money">{{ money($fundCash) }}</span></div>
                    <form method="POST" action="{{ route('withdrawals.pay', $w) }}" data-confirm="پرداخت ثبت و واحدها ابطال شود؟ این عملیات سند مالی صادر می‌کند.">
                        @csrf
                        <label class="form-label small">حساب پرداخت</label>
                        <select name="cash_account_id" class="form-select mb-2" required>
                            @foreach ($accounts as [$a, $bal])<option value="{{ $a->id }}">{{ $a->name }} — موجودی {{ money($bal) }}</option>@endforeach
                        </select>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cash"></i> ثبت پرداخت و ابطال واحدها</button>
                    </form>
                @endif
                @if (in_array($w->status, ['pending', 'approved', 'awaiting_liquidity']))
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">رد درخواست</button>
                    @include('partials.reason-modal', ['id' => 'rejectModal', 'action' => route('withdrawals.reject', $w), 'title' => 'رد درخواست برداشت', 'submit' => 'رد'])
                @endif
            @endif
            @if ($w->member_id === $user->id && in_array($w->status, ['pending', 'approved', 'awaiting_liquidity']))
                <form method="POST" action="{{ route('withdrawals.cancel', $w) }}" data-confirm="درخواست لغو شود؟">@csrf<button class="btn btn-light w-100" type="submit">لغو درخواست</button></form>
            @endif
            <a href="{{ route('withdrawals.index') }}" class="btn btn-light">بازگشت</a>
        </div></div>
    </div>
</div>
@endsection
