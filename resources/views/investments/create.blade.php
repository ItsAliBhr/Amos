@extends('layouts.app')
@section('title', 'ثبت عملیات سرمایه‌گذاری')
@section('content')
<div class="row justify-content-center"><div class="col-xl-8">
<div class="card">
    <div class="card-header">ثبت عملیات سرمایه‌گذاری</div>
    <div class="card-body">
        <div class="alert alert-light border small"><i class="bi bi-info-circle"></i>
            پس از تأیید، سند دفتر کل ثبت می‌شود. تخصیص اقتصادی به سبدهای اعضا و Pool از طریق ارزش هر واحد (NAV/Unit) انجام می‌شود. سود/زیان فروش با روش میانگین موزون محاسبه می‌شود.
        </div>
        <form method="POST" action="{{ route('investments.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', idem_key()) }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">نوع عملیات</label>
                    <select name="type" class="form-select" data-investment-type>
                        @foreach (\App\Models\InvestmentTransaction::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', $type) == $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاریخ <span class="text-danger">*</span></label>
                    @include('partials.date-input', ['name' => 'transaction_date'])
                </div>
                <div class="col-md-6" data-show-for="buy,sell,expense,income">
                    <label class="form-label">دارایی <span data-show-for="expense,income" class="text-muted small">(اختیاری؛ خالی = کل پرتفوی)</span></label>
                    <select name="asset_id" class="form-select">
                        <option value="">—</option>
                        @foreach ($assets as $a)<option value="{{ $a->id }}" @selected(old('asset_id') == $a->id)>{{ $a->name }} ({{ $a->symbol }}) — {{ $a->category->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3" data-show-for="buy,sell">
                    <label class="form-label">مقدار</label>
                    <input name="quantity" class="form-control money-input" value="{{ old('quantity') }}" autocomplete="off">
                </div>
                <div class="col-md-3" data-show-for="buy,sell">
                    <label class="form-label">قیمت واحد</label>
                    @include('partials.money-input', ['name' => 'unit_price', 'required' => false])
                </div>
                <div class="col-md-6" data-show-for="expense,income,transfer">
                    <label class="form-label">مبلغ</label>
                    @include('partials.money-input', ['name' => 'gross_amount', 'required' => false])
                </div>
                <div class="col-md-6" data-show-for="buy,sell,transfer">
                    <label class="form-label">کارمزد</label>
                    @include('partials.money-input', ['name' => 'fee_amount', 'required' => false])
                    <div class="form-text" data-show-for="buy">مبلغ کل خرید = مبلغ ناخالص + کارمزد (به بهای تمام‌شده اضافه می‌شود).</div>
                    <div class="form-text" data-show-for="sell">مبلغ خالص فروش = مبلغ ناخالص − کارمزد.</div>
                </div>
                <div class="col-md-6" data-show-for="expense">
                    <label class="form-label">نوع هزینه</label>
                    <select name="expense_category" class="form-select">
                        @foreach (\App\Models\InvestmentTransaction::EXPENSE_CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('expense_category') == $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><span data-show-for="buy,expense">حساب پرداخت</span><span data-show-for="sell,income">حساب مقصد وجه</span><span data-show-for="transfer">حساب مبدأ</span></label>
                    <select name="cash_account_id" class="form-select" required>
                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('cash_account_id') == $a->id)>{{ $a->name }} — موجودی {{ money($cash[$a->id]) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6" data-show-for="transfer">
                    <label class="form-label">حساب مقصد</label>
                    <select name="to_cash_account_id" class="form-select">
                        <option value="">—</option>
                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('to_cash_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                @if ($ideas->count())
                <div class="col-md-6" data-show-for="buy">
                    <label class="form-label">ایده مصوب مرتبط</label>
                    <select name="idea_id" class="form-select"><option value="">—</option>@foreach ($ideas as $i)<option value="{{ $i->id }}" @selected(old('idea_id', request('idea_id')) == $i->id)>{{ $i->title }}</option>@endforeach</select>
                </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label">رسید/مستندات</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
                <div class="col-12">
                    <label class="form-label">شرح</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="approve_now" value="1" id="approve_now" @checked(old('approve_now'))><label class="form-check-label" for="approve_now">ثبت و تأیید نهایی هم‌زمان</label></div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">ثبت عملیات</button>
                <a href="{{ route('investments.index') }}" class="btn btn-light">انصراف</a>
            </div>
        </form>
    </div>
</div>
</div></div>
@endsection
