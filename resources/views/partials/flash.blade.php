@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $cls)
    @if (session($key))
        <div class="alert alert-{{ $cls }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>
        </div>
    @endif
@endforeach
@if (session('temp_password'))
    <div class="alert alert-warning">
        <i class="bi bi-key"></i> رمز عبور موقت: <code class="fs-6 user-select-all ltr d-inline-block">{{ session('temp_password') }}</code>
        <div class="small mt-1">این رمز فقط یک بار نمایش داده می‌شود؛ آن را به‌صورت امن به کاربر اعلام کنید. کاربر در اولین ورود مجبور به تغییر رمز است.</div>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">لطفاً خطاهای زیر را برطرف کنید:</div>
        <ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
