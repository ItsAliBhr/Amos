@extends('layouts.app')
@section('title', $asset->exists ? 'ویرایش دارایی' : 'تعریف دارایی')
@section('content')
@include('admin._settings_nav')
<div class="row justify-content-center"><div class="col-lg-7"><div class="card"><div class="card-header">{{ $asset->exists ? 'ویرایش دارایی' : 'تعریف دارایی جدید' }}</div><div class="card-body">
    <form method="POST" action="{{ $asset->exists ? route('admin.assets.update', $asset) : route('admin.assets.store') }}">
        @csrf @if ($asset->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">نام</label><input name="name" class="form-control" required value="{{ old('name', $asset->name) }}"></div>
            <div class="col-md-6"><label class="form-label">نماد</label><input name="symbol" class="form-control ltr" required value="{{ old('symbol', $asset->symbol) }}"></div>
            <div class="col-md-6"><label class="form-label">دسته</label><select name="asset_category_id" class="form-select" required>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('asset_category_id', $asset->asset_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">واحد اندازه‌گیری</label><input name="unit_name" class="form-control" required value="{{ old('unit_name', $asset->unit_name ?? 'واحد') }}" placeholder="گرم، سهم، دلار"></div>
            <div class="col-md-3"><label class="form-label">دقت اعشار</label><input name="decimal_places" type="number" min="0" max="8" class="form-control" required value="{{ old('decimal_places', $asset->decimal_places) }}"></div>
            <div class="col-12"><label class="form-label">توضیحات</label><textarea name="description" class="form-control" rows="2">{{ old('description', $asset->description) }}</textarea></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" @checked(old('is_active', $asset->is_active))><label class="form-check-label" for="ia">فعال</label></div></div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">ذخیره</button><a href="{{ route('assets.index') }}" class="btn btn-light">انصراف</a></div>
    </form>
</div></div></div></div>
@endsection
