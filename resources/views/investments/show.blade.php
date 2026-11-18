@extends('layouts.app')
@section('title', 'عملیات سرمایه‌گذاری #'.$t->id)
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>{{ \App\Models\InvestmentTransaction::TYPES[$t->type] }} #{{ $t->id }}</span>{!! status_badge($t->status) !!}</div>
            <div class="card-body">
                <dl class="row kv mb-0">
                    <div class="col-sm-6"><dt>دارایی</dt><dd>{{ $t->asset ? $t->asset->name.' ('.$t->asset->symbol.')' : 'کل پرتفوی' }}</dd></div>
                    <div class="col-sm-6"><dt>تاریخ</dt><dd>{{ jdate($t->transaction_date) }}</dd></div>
                    @if ((float) $t->quantity)
                        <div class="col-sm-6"><dt>مقدار</dt><dd class="num">{{ num($t->quantity, $t->asset?->decimal_places ?? 8) }} {{ $t->asset?->unit_name }}</dd></div>
                        <div class="col-sm-6"><dt>قیمت واحد</dt><dd class="money">{{ money($t->unit_price_irr) }}</dd></div>
                    @endif
                    <div class="col-sm-6"><dt>مبلغ ناخالص</dt><dd class="money">{{ money($t->gross_amount_irr) }}</dd></div>
                    <div class="col-sm-6"><dt>کارمزد</dt><dd class="money">{{ money($t->fee_amount_irr) }}</dd></div>
                    <div class="col-sm-6"><dt>{{ $t->type === 'sell' ? 'مبلغ خالص دریافتی' : 'مبلغ کل' }}</dt><dd class="money fw-bold">{{ money($t->net_amount_irr) }}</dd></div>
                    <div class="col-sm-6"><dt>حساب نقدی</dt><dd>{{ $t->cashAccount?->name }} {{ $t->toCashAccount ? '← '.$t->toCashAccount->name : '' }}</dd></div>
                    @if ($t->cost_basis_irr !== null)<div class="col-sm-6"><dt>بهای تمام‌شده</dt><dd class="money">{{ money($t->cost_basis_irr) }}</dd></div>@endif
                    @if ($t->realized_pnl_irr !== null)<div class="col-sm-6"><dt>سود/زیان تحقق‌یافته</dt><dd class="money {{ $t->realized_pnl_irr < 0 ? 'negative' : 'positive' }}">{{ money($t->realized_pnl_irr) }}</dd></div>@endif
                    @if ($t->expense_category)<div class="col-sm-6"><dt>نوع هزینه</dt><dd>{{ \App\Models\InvestmentTransaction::EXPENSE_CATEGORIES[$t->expense_category] ?? '' }}</dd></div>@endif
                    @if ($t->idea)<div class="col-sm-6"><dt>ایده مرتبط</dt><dd><a href="{{ route('ideas.show', $t->idea) }}">{{ $t->idea->title }}</a></dd></div>@endif
                    @if ($admin)
                        <div class="col-sm-6"><dt>ثبت‌کننده</dt><dd>{{ $t->submitter?->name }} — {{ jdate($t->created_at, true) }}</dd></div>
                        <div class="col-sm-6"><dt>تأییدکننده</dt><dd>{{ $t->approver?->name ?? '—' }} {{ $t->approved_at ? '— '.jdate($t->approved_at, true) : '' }}</dd></div>
                        <div class="col-sm-6"><dt>مستندات</dt><dd>@if ($t->attachment)<a href="{{ route('attachments.show', $t->attachment) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> مشاهده</a>@else — @endif</dd></div>
                    @endif
                    @if ($t->description)<div class="col-12"><dt>شرح</dt><dd>{{ $t->description }}</dd></div>@endif
                    @if ($t->rejection_reason)<div class="col-12"><dt>دلیل رد</dt><dd class="text-danger">{{ $t->rejection_reason }}</dd></div>@endif
                    @if ($t->reversal_reason)<div class="col-12"><dt>دلیل برگشت</dt><dd class="text-danger">{{ $t->reversal_reason }}</dd></div>@endif
                </dl>
            </div>
        </div>
        @if ($admin && $ledger)
            <div class="card mt-3"><div class="card-header">سند دفتر کل <a href="{{ route('admin.ledger.show', $ledger) }}">#{{ $ledger->id }}</a></div>@include('partials.ledger-entries', ['entries' => $ledger->entries])</div>
        @endif
    </div>
    @if ($admin)
    <div class="col-lg-4">
        <div class="card"><div class="card-header">عملیات</div><div class="card-body d-grid gap-2">
            @if ($t->status === 'pending')
                <form method="POST" action="{{ route('investments.approve', $t) }}" data-confirm="تأیید نهایی و ثبت در دفتر کل؟">@csrf<button class="btn btn-success w-100" type="submit"><i class="bi bi-check2"></i> تأیید نهایی</button></form>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">رد</button>
                @include('partials.reason-modal', ['id' => 'rejectModal', 'action' => route('investments.reject', $t), 'title' => 'رد عملیات', 'submit' => 'رد'])
            @endif
            @if ($t->status === 'approved')
                <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#reverseModal"><i class="bi bi-arrow-counterclockwise"></i> سند برگشتی</button>
                @include('partials.reason-modal', ['id' => 'reverseModal', 'action' => route('investments.reverse', $t), 'title' => 'برگشت عملیات', 'submit' => 'ثبت سند برگشتی', 'help' => 'سند اصلی حذف نمی‌شود؛ سند معکوس ثبت می‌شود.'])
            @endif
            <a href="{{ route('investments.index') }}" class="btn btn-light">بازگشت</a>
        </div></div>
    </div>
    @endif
</div>
@endsection
