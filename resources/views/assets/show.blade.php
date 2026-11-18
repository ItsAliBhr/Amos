@extends('layouts.app')
@section('title', $asset->name)
@section('content')
@php $admin = auth()->user()->isAdmin(); @endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @if ($admin)
        <a href="{{ route('valuations.create', ['asset_id' => $asset->id]) }}" class="btn btn-primary"><i class="bi bi-tag"></i> ثبت ارزش‌گذاری</a>
        <a href="{{ route('admin.assets.edit', $asset) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> ویرایش مشخصات</a>
    @endif
    <a href="{{ route('assets.index') }}" class="btn btn-light">بازگشت</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3">@include('partials.stat', ['label' => 'موجودی', 'value' => $holding ? num($holding['quantity'], $asset->decimal_places).' '.$asset->unit_name : '0', 'icon' => 'box'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'بهای تمام‌شده', 'value' => money($holding['cost'] ?? 0), 'icon' => 'receipt'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'ارزش روز', 'value' => money($holding['market'] ?? 0), 'icon' => 'gem'])</div>
    <div class="col-md-3">@include('partials.stat', ['label' => 'سود/زیان تحقق‌یافته', 'value' => money($realized ?: 0), 'icon' => 'check2-circle'])</div>
</div>
<div class="card mb-3"><div class="card-body small">
    <strong>{{ $asset->name }}</strong> ({{ $asset->symbol }}) · دسته: {{ $asset->category->name }} · واحد: {{ $asset->unit_name }} · دقت اعشار: {{ $asset->decimal_places }}
    @if ($asset->description)<div class="mt-2 text-muted">{{ $asset->description }}</div>@endif
</div></div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">تاریخچه ارزش‌گذاری</div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>تاریخ</th><th>قیمت واحد</th><th>مقدار مبنا</th><th>ارزش کل</th><th>منبع</th><th>وضعیت</th></tr></thead>
                <tbody>
                @forelse ($valuations as $v)
                    <tr><td>{{ jdate($v->valuation_date) }}</td><td class="money">{{ money($v->unit_price_irr) }}</td><td class="num">{{ num($v->total_quantity, $asset->decimal_places) }}</td><td class="money">{{ money($v->total_value_irr) }}</td><td class="small">{{ $v->source }}</td><td>{!! status_badge($v->status) !!}</td></tr>
                @empty <tr><td colspan="6" class="empty-state">ارزش‌گذاری ثبت نشده است.</td></tr>
                @endforelse
                </tbody></table></div>
            <div class="card-footer bg-white">{{ $valuations->links() }}</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">تراکنش‌ها</div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>تاریخ</th><th>نوع</th><th>مقدار</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr><td><a href="{{ route('investments.show', $t) }}">{{ jdate($t->transaction_date) }}</a></td><td>{{ \App\Models\InvestmentTransaction::TYPES[$t->type] }}</td><td class="num">{{ (float) $t->quantity ? num($t->quantity, $asset->decimal_places) : '—' }}</td><td class="money">{{ money($t->net_amount_irr) }}</td><td>{!! status_badge($t->status) !!}</td></tr>
                @empty <tr><td colspan="5" class="empty-state">تراکنشی ثبت نشده است.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>
</div>
@endsection
