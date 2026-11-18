@extends('layouts.app')
@section('title', 'حساب‌های نقدی')
@section('content')
@include('admin._settings_nav')
<a href="{{ route('admin.cash.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> حساب جدید</a>
<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>نام</th><th>نوع</th><th>بانک</th><th>شماره حساب</th><th>موجودی افتتاحیه</th><th>موجودی دفتر کل</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    @forelse ($accounts as $a)
        <tr><td>{{ $a->name }}</td><td>{{ \App\Models\CashAccount::TYPES[$a->account_type] ?? $a->account_type }}</td><td>{{ $a->bank_name }}</td><td class="ltr text-end">{{ $a->maskedNumber() ?? '—' }}</td>
            <td class="money">{{ money($a->opening_balance_irr) }}</td><td class="money fw-semibold">{{ money($balances[$a->id] ?? 0) }}</td><td>{!! status_badge($a->is_active ? 'active' : 'inactive') !!}</td>
            <td><a href="{{ route('admin.cash.edit', $a) }}" class="btn btn-sm btn-light">ویرایش</a></td></tr>
    @empty <tr><td colspan="8" class="empty-state">حساب نقدی تعریف نشده است. برای ثبت واریزی ابتدا حداقل یک حساب بسازید.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="card-footer small text-muted bg-white">حساب بانکی واقعی می‌تواند مشترک باشد؛ تفکیک مالکیت اقتصادی در دفتر کل و با واحدهای سرمایه‌گذاری انجام می‌شود.</div>
</div>
@endsection
