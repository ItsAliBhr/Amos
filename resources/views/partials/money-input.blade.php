<div class="input-group">
    <input type="text" name="{{ $name }}" id="{{ $id ?? $name }}" class="form-control money-input @error($name) is-invalid @enderror"
           value="{{ old($name, $value ?? '') }}" autocomplete="off" {{ ($required ?? true) ? 'required' : '' }} placeholder="{{ $placeholder ?? '0' }}">
    <span class="input-group-text">{{ $unit ?? currency_label() }}</span>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
