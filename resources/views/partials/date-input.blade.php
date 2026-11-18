<input type="text" name="{{ $name }}" id="{{ $id ?? $name }}" class="form-control ltr digits-input @error($name) is-invalid @enderror"
       value="{{ old($name, $value ?? \App\Support\Jalali::today()) }}" placeholder="1404/01/15" pattern="[0-9۰-۹]{4}/[0-9۰-۹]{1,2}/[0-9۰-۹]{1,2}" {{ ($required ?? true) ? 'required' : '' }}>
<div class="form-text">تاریخ شمسی (سال/ماه/روز)</div>
@error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
