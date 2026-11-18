@extends('layouts.app')
@section('title', 'درخواست‌های برداشت')
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <div class="d-flex gap-2">
        <a href="{{ route('withdrawals.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-up-right"></i> درخواست برداشت</a>
        @if ($admin)<a href="{{ route('withdrawals.pool.create') }}" class="btn btn-outline-secondary">برداشت از Pool</a>@endif
    </div>
    <form class="d-flex gap-2" method="GET">
        <select name="status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option>@foreach (\App\Models\WithdrawalRequest::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-primary">فیلتر</button>
    </form>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>#</th>@if($admin)<th>سبد</th>@endif<th>تاریخ درخواست</th><th>مبلغ درخواستی</th><th>مبلغ تأییدشده</th><th>پرداخت خالص</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    @forelse ($requests as $w)
        <tr>
            <td class="num">{{ $w->id }}</td>
            @if($admin)<td>{{ $w->ownershipAccount->displayName() }}</td>@endif
            <td>{{ jdate($w->requested_at, true) }}</td>
            <td class="money">{{ money($w->requested_amount_irr) }}</td>
            <td class="money">{{ $w->approved_amount_irr ? money($w->approved_amount_irr) : '—' }}</td>
            <td class="money">{{ $w->net_paid_irr ? money($w->net_paid_irr) : '—' }}</td>
            <td>{!! status_badge($w->status) !!}</td>
            <td><a href="{{ route('withdrawals.show', $w) }}" class="btn btn-sm btn-light">جزئیات</a></td>
        </tr>
    @empty <tr><td colspan="8" class="empty-state">درخواستی ثبت نشده است.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="card-footer bg-white">{{ $requests->links() }}</div>
</div>
@endsection
