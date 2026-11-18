@extends('layouts.app')
@section('title', 'رویداد #'.$log->id)
@section('content')
<div class="card mb-3"><div class="card-body"><dl class="row kv mb-0">
    <div class="col-md-3"><dt>رویداد</dt><dd class="ltr text-end">{{ $log->action }}</dd></div>
    <div class="col-md-3"><dt>کاربر</dt><dd>{{ $log->user?->name ?? 'سیستم' }}</dd></div>
    <div class="col-md-3"><dt>زمان</dt><dd>{{ jdate($log->created_at, true) }}</dd></div>
    <div class="col-md-3"><dt>موجودیت</dt><dd>{{ $log->entity_type }} #{{ $log->entity_id }}</dd></div>
    <div class="col-md-6"><dt>IP</dt><dd class="ltr text-end">{{ $log->ip_address }}</dd></div>
    <div class="col-md-6"><dt>User Agent</dt><dd class="small ltr text-end">{{ $log->user_agent }}</dd></div>
</dl></div></div>
<div class="row g-3">
    <div class="col-md-6"><div class="card"><div class="card-header">مقادیر قبل</div><div class="card-body"><pre class="json mb-0">{{ json_encode($log->old_values_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">مقادیر بعد</div><div class="card-body"><pre class="json mb-0">{{ json_encode($log->new_values_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div></div>
</div>
@endsection
