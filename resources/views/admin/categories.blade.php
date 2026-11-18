@extends('layouts.app')
@section('title', 'دسته‌بندی دارایی‌ها')
@section('content')
@include('admin._settings_nav')
<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-header">دسته جدید</div><div class="card-body">
        <form method="POST" action="{{ route('admin.categories.store') }}">@csrf
            <div class="mb-2"><label class="form-label">نام</label><input name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">کد (لاتین)</label><input name="code" class="form-control ltr" required></div>
            <button class="btn btn-primary" type="submit">افزودن</button>
        </form>
    </div></div></div>
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>نام</th><th>کد</th><th>دارایی‌ها</th><th>فعال</th><th></th></tr></thead>
        <tbody>
        @foreach ($categories as $c)
            <tr>
                <td><input form="cat{{ $c->id }}" name="name" class="form-control form-control-sm" value="{{ $c->name }}"></td>
                <td><input form="cat{{ $c->id }}" name="code" class="form-control form-control-sm ltr" value="{{ $c->code }}"></td>
                <td>{{ $c->assets_count }}</td>
                <td><input form="cat{{ $c->id }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($c->is_active)></td>
                <td><form id="cat{{ $c->id }}" method="POST" action="{{ route('admin.categories.update', $c) }}">@csrf @method('PUT')<button class="btn btn-sm btn-light" type="submit">ذخیره</button></form></td>
            </tr>
        @endforeach
        </tbody></table></div></div></div>
</div>
@endsection
