@extends('layouts.app')
@section('title', 'سند دفتر کل #'.$tx->id)
@section('content')
<div class="card mb-3"><div class="card-body"><dl class="row kv mb-0">
    <div class="col-md-3"><dt>نوع</dt><dd>{{ \App\Models\LedgerTransaction::TYPES[$tx->type] ?? $tx->type }}</dd></div>
    <div class="col-md-3"><dt>تاریخ</dt><dd>{{ jdate($tx->transaction_date) }}</dd></div>
    <div class="col-md-3"><dt>ثبت/تأیید</dt><dd>{{ $tx->creator?->name }} — {{ jdate($tx->approved_at, true) }}</dd></div>
    <div class="col-md-3"><dt>وضعیت</dt><dd>{!! status_badge($tx->status) !!} @if($tx->reversal_of_id)<a href="{{ route('admin.ledger.show', $tx->reversal_of_id) }}">برگشت سند #{{ $tx->reversal_of_id }}</a>@endif</dd></div>
    <div class="col-12"><dt>شرح</dt><dd>{{ $tx->description }}</dd></div>
    <div class="col-12"><dt>کلید یکتایی</dt><dd class="ltr text-end small text-muted">{{ $tx->idempotency_key }}</dd></div>
</dl></div></div>
<div class="card">@include('partials.ledger-entries', ['entries' => $tx->entries])</div>
@endsection
