@extends('layouts.app')
@section('title', $user->exists ? 'ویرایش کاربر: '.$user->name : 'ایجاد کاربر')
@section('content')
<div class="row g-3">
<div class="col-lg-7">
<div class="card"><div class="card-header">{{ $user->exists ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' }}</div><div class="card-body">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">نام و نام خانوادگی <span class="text-danger">*</span></label><input name="name" class="form-control" required value="{{ old('name', $user->name) }}"></div>
            <div class="col-md-6"><label class="form-label">نام کاربری (یکتا، لاتین) <span class="text-danger">*</span></label><input name="username" class="form-control ltr" required pattern="[A-Za-z0-9_.\-]+" value="{{ old('username', $user->username) }}"></div>
            <div class="col-md-6"><label class="form-label">موبایل</label><input name="mobile" class="form-control ltr" value="{{ old('mobile', $user->mobile) }}"></div>
            <div class="col-md-6"><label class="form-label">ایمیل</label><input name="email" type="email" class="form-control ltr" value="{{ old('email', $user->email) }}"></div>
            <div class="col-md-4"><label class="form-label">نقش</label><select name="role" class="form-select">@foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(old('role', $user->role) == $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">وضعیت</label><select name="status" class="form-select">@foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $user->status) == $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">تاریخ عضویت</label>@include('partials.date-input', ['name' => 'joined_at', 'value' => $user->joined_at ? jdate($user->joined_at) : null, 'required' => false])</div>
            <div class="col-md-6"><label class="form-label">تعهد ماهانه اختصاصی</label>@include('partials.money-input', ['name' => 'monthly_commitment', 'value' => money_input($user->monthly_commitment_irr), 'required' => false])<div class="form-text">خالی = تعهد پیش‌فرض صندوق</div></div>
            @unless ($user->exists)
                <div class="col-md-6"><label class="form-label">رمز اولیه</label><input name="password" type="text" class="form-control ltr" autocomplete="new-password"><div class="form-text">خالی = تولید خودکار رمز امن. تغییر رمز در اولین ورود اجباری است.</div></div>
            @endunless
            <div class="col-12"><label class="form-label">توضیحات مدیر</label><textarea name="admin_note" class="form-control" rows="2">{{ old('admin_note', $user->admin_note) }}</textarea></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">ذخیره</button><a href="{{ route('admin.users.index') }}" class="btn btn-light">بازگشت</a></div>
    </form>
</div></div>
</div>
@if ($user->exists)
<div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">امنیت حساب</div><div class="card-body d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.users.resetPassword', $user) }}" data-confirm="رمز این کاربر بازنشانی شود؟">@csrf<button class="btn btn-outline-warning" type="submit"><i class="bi bi-key"></i> بازنشانی رمز</button></form>
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="حذف کاربر؟ (فقط کاربر بدون سابقه مالی قابل حذف است)">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> حذف</button></form>
    </div><div class="card-footer small text-muted bg-white">عضو دارای سابقه مالی حذف نمی‌شود؛ وضعیت را «غیرفعال» کنید.</div></div>
    <div class="card"><div class="card-header">تعهد ماهانه و معافیت</div><div class="card-body">
        <form method="POST" action="{{ route('admin.users.commitment', $user) }}" class="row g-2 mb-3">
            @csrf
            @php [$cy, $cm] = \App\Support\Jalali::currentYearMonth(); @endphp
            <div class="col-6"><select name="month" class="form-select form-select-sm">@foreach (\App\Support\Jalali::MONTHS as $i => $n)<option value="{{ $i }}" @selected($cm == $i)>{{ $n }}</option>@endforeach</select></div>
            <div class="col-6"><input name="year" type="number" class="form-control form-control-sm" value="{{ $cy }}"></div>
            <div class="col-6"><input name="commitment" class="form-control form-control-sm money-input" placeholder="تعهد ({{ currency_label() }})" value="{{ money_input($user->effectiveMonthlyCommitment()) }}" required></div>
            <div class="col-6"><input name="exemption" class="form-control form-control-sm money-input" placeholder="معافیت ({{ currency_label() }})"></div>
            <div class="col-9"><input name="note" class="form-control form-control-sm" placeholder="یادداشت"></div>
            <div class="col-3"><button class="btn btn-sm btn-primary w-100" type="submit">ثبت</button></div>
        </form>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>ماه</th><th>تعهد</th><th>پرداخت</th><th>وضعیت</th></tr></thead><tbody>
            @foreach ($commitments as $s)<tr><td>{{ $s['label'] }}</td><td class="money">{{ money($s['due']) }}</td><td class="money">{{ money($s['paid']) }}</td><td>{!! status_badge($s['status']) !!}</td></tr>@endforeach
        </tbody></table></div>
    </div></div>
</div>
@endif
</div>
@endsection
