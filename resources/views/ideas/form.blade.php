@extends('layouts.app')
@section('title', $idea->exists ? 'ویرایش ایده' : 'ثبت ایده سرمایه‌گذاری')
@section('content')
<div class="row justify-content-center"><div class="col-xl-8">
<div class="card"><div class="card-header">{{ $idea->exists ? 'ویرایش ایده' : 'ثبت ایده سرمایه‌گذاری' }}</div><div class="card-body">
    <form method="POST" action="{{ $idea->exists ? route('ideas.update', $idea) : route('ideas.store') }}" enctype="multipart/form-data">
        @csrf @if ($idea->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">عنوان <span class="text-danger">*</span></label><input name="title" class="form-control" required maxlength="255" value="{{ old('title', $idea->title) }}"></div>
            <div class="col-md-4"><label class="form-label">دسته</label><select name="category_id" class="form-select"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $idea->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">شرح <span class="text-danger">*</span></label><textarea name="description" class="form-control" rows="5" required>{{ old('description', $idea->description) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">مبلغ موردنیاز</label>@include('partials.money-input', ['name' => 'required_capital', 'value' => money_input($idea->required_capital_irr), 'required' => false])</div>
            <div class="col-md-6"><label class="form-label">افق زمانی</label><input name="time_horizon" class="form-control" value="{{ old('time_horizon', $idea->time_horizon) }}" placeholder="مثلاً ۶ ماه"></div>
            <div class="col-md-4"><label class="form-label">حداقل بازده مورد انتظار (٪)</label><input name="expected_return_min" type="number" step="0.01" class="form-control" value="{{ old('expected_return_min', $idea->expected_return_min) }}"></div>
            <div class="col-md-4"><label class="form-label">حداکثر بازده مورد انتظار (٪)</label><input name="expected_return_max" type="number" step="0.01" class="form-control" value="{{ old('expected_return_max', $idea->expected_return_max) }}"></div>
            <div class="col-md-4"><label class="form-label">دوره بازده</label><select name="expected_return_period" class="form-select"><option value="">—</option>@foreach (\App\Models\Idea::PERIODS as $k => $v)<option value="{{ $k }}" @selected(old('expected_return_period', $idea->expected_return_period) == $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">ریسک‌ها</label><textarea name="risk_description" class="form-control" rows="3">{{ old('risk_description', $idea->risk_description) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">فرضیات</label><textarea name="assumptions" class="form-control" rows="3">{{ old('assumptions', $idea->assumptions) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">مستندات (JPG/PNG/PDF)</label><input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ارسال برای بررسی</button>
            <button class="btn btn-outline-secondary" type="submit" name="as_draft" value="1">ذخیره پیش‌نویس</button>
            <a href="{{ route('ideas.index') }}" class="btn btn-light">انصراف</a>
        </div>
    </form>
</div></div>
</div></div>
@endsection
