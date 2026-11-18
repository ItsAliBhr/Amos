@extends('layouts.app')
@section('title', 'تغییر رمز عبور')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        @if ($forced)
            <div class="alert alert-warning"><i class="bi bi-shield-lock"></i> برای امنیت حساب، قبل از ادامه باید رمز اولیه را تغییر دهید.</div>
        @endif
        <div class="card">
            <div class="card-header">تغییر رمز عبور</div>
            <div class="card-body">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">رمز فعلی</label>
                        <input type="password" id="current_password" name="current_password" class="form-control ltr @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">رمز جدید</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control ltr @error('password') is-invalid @enderror" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password"><i class="bi bi-eye"></i></button>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">حداقل ۸ کاراکتر شامل حرف و عدد.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">تکرار رمز جدید</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control ltr" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">ذخیره رمز جدید</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
