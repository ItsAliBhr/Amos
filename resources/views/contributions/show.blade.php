@extends('layouts.app')
@section('title', 'واریزی #'.$c->id)
@section('content')
@php $user = auth()->user(); @endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>جزئیات واریزی #{{ $c->id }}</span>{!! status_badge($c->status) !!}
            </div>
            <div class="card-body">
                <dl class="row kv mb-0">
                    <div class="col-sm-6"><dt>عضو پرداخت‌کننده</dt><dd>{{ $c->member?->name ?? '—' }}</dd></div>
                    <div class="col-sm-6"><dt>نوع</dt><dd>{{ $c->type->name }}</dd></div>
                    <div class="col-sm-6"><dt>سبد مالکیت</dt><dd>{{ $c->ownershipAccount?->displayName() }}</dd></div>
                    <div class="col-sm-6"><dt>مبلغ</dt><dd class="money fw-bold">{{ money($c->amount_irr) }} <span class="text-muted small">({{ num($c->amount_irr) }} ریال)</span></dd></div>
                    <div class="col-sm-6"><dt>تاریخ پرداخت</dt><dd>{{ jdate($c->contribution_date) }}</dd></div>
                    <div class="col-sm-6"><dt>ماه تعهد</dt><dd>{{ $c->commitment_year ? \App\Support\Jalali::monthLabel($c->commitment_year, $c->commitment_month) : '—' }}</dd></div>
                    <div class="col-sm-6"><dt>حساب مقصد</dt><dd>{{ $c->destinationAccount?->name ?? '—' }}</dd></div>
                    <div class="col-sm-6"><dt>روش پرداخت</dt><dd>{{ \App\Models\Contribution::PAYMENT_METHODS[$c->payment_method] ?? '—' }}</dd></div>
                    <div class="col-sm-6"><dt>کد پیگیری</dt><dd class="ltr text-end">{{ $c->tracking_code ?? '—' }}</dd></div>
                    <div class="col-sm-6"><dt>رسید</dt><dd>@if ($c->receipt)<a href="{{ route('attachments.show', $c->receipt) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> مشاهده رسید</a>@else — @endif</dd></div>
                    <div class="col-sm-6"><dt>ثبت‌کننده</dt><dd>{{ $c->submitter?->name }} — {{ jdate($c->created_at, true) }}</dd></div>
                    <div class="col-sm-6"><dt>تأیید/ردکننده</dt><dd>{{ $c->approver?->name ?? '—' }} {{ $c->approved_at ? '— '.jdate($c->approved_at, true) : '' }}</dd></div>
                    @if ($c->units_issued)
                        <div class="col-sm-6"><dt>واحد صادرشده</dt><dd class="num">{{ num($c->units_issued, 8) }}</dd></div>
                        <div class="col-sm-6"><dt>ارزش هر واحد هنگام صدور</dt><dd class="num">{{ num($c->nav_per_unit_irr, 8) }} ریال</dd></div>
                    @endif
                    @if ($c->description)<div class="col-12"><dt>توضیح</dt><dd>{{ $c->description }}</dd></div>@endif
                    @if ($c->rejection_reason)<div class="col-12"><dt>دلیل رد</dt><dd class="text-danger">{{ $c->rejection_reason }}</dd></div>@endif
                    @if ($c->reversal_reason)<div class="col-12"><dt>دلیل برگشت</dt><dd class="text-danger">{{ $c->reversal_reason }} — {{ jdate($c->reversed_at, true) }}</dd></div>@endif
                </dl>
                @if ($exceedsCap)
                    <div class="alert alert-warning mb-0 mt-2"><i class="bi bi-exclamation-triangle"></i> این واریزی از سقف ماهانه واریزی اختصاصی عبور می‌کند.</div>
                @endif
            </div>
        </div>
        @if ($user->isAdmin() && $c->ledgerTransaction)
            <div class="card mt-3">
                <div class="card-header">سند دفتر کل <a href="{{ route('admin.ledger.show', $c->ledgerTransaction) }}">#{{ $c->ledgerTransaction->id }}</a></div>
                @include('partials.ledger-entries', ['entries' => $c->ledgerTransaction->entries])
            </div>
        @endif
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">عملیات</div>
            <div class="card-body d-grid gap-2">
                @if ($c->status === 'draft' && ($user->isAdmin() || $c->submitted_by === $user->id))
                    <form method="POST" action="{{ route('contributions.submit', $c) }}">@csrf<button class="btn btn-primary w-100" type="submit">ارسال برای تأیید</button></form>
                @endif
                @if ($user->isAdmin() && $c->status === 'pending')
                    <form method="POST" action="{{ route('contributions.approve', $c) }}" data-confirm="با تأیید، سند دفتر کل ثبت و واحد صادر می‌شود. ادامه می‌دهید؟">
                        @csrf
                        <label class="form-label small">حساب مقصد</label>
                        <select name="destination_account_id" class="form-select mb-2" required>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($c->destination_account_id == $a->id)>{{ $a->name }}</option>@endforeach
                        </select>
                        @if ($exceedsCap)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="cap_override" value="1" id="cap_override">
                                <label class="form-check-label small" for="cap_override">تأیید ویژه عبور از سقف ماهانه</label>
                            </div>
                        @endif
                        <button class="btn btn-success w-100" type="submit"><i class="bi bi-check2"></i> تأیید واریزی</button>
                    </form>
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x"></i> رد واریزی</button>
                    @include('partials.reason-modal', ['id' => 'rejectModal', 'action' => route('contributions.reject', $c), 'title' => 'رد واریزی', 'label' => 'دلیل رد', 'submit' => 'رد'])
                @endif
                @if ($user->isAdmin() && $c->status === 'approved')
                    <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#reverseModal"><i class="bi bi-arrow-counterclockwise"></i> ثبت سند برگشتی</button>
                    @include('partials.reason-modal', ['id' => 'reverseModal', 'action' => route('contributions.reverse', $c), 'title' => 'برگشت واریزی تأییدشده', 'label' => 'دلیل برگشت', 'submit' => 'ثبت سند برگشتی', 'help' => 'سند اصلی حذف نمی‌شود؛ یک سند برگشتی ثبت و واحدهای صادرشده ابطال می‌شود.'])
                @endif
                @if (in_array($c->status, ['draft', 'pending']) && ($user->isAdmin() || $c->submitted_by === $user->id))
                    <form method="POST" action="{{ route('contributions.cancel', $c) }}" data-confirm="این واریزی لغو شود؟">@csrf<button class="btn btn-light w-100" type="submit">لغو واریزی</button></form>
                @endif
                <a href="{{ route('contributions.index') }}" class="btn btn-light">بازگشت به فهرست</a>
            </div>
        </div>
    </div>
</div>
@endsection
