{{-- Reusable modal for actions that require a reason (reject / reverse). --}}
<div class="modal fade" id="{{ $id }}" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ $action }}" class="modal-content" data-confirm="{{ $confirm ?? 'آیا از انجام این عملیات اطمینان دارید؟' }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ $title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">{{ $label ?? 'دلیل' }} <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required maxlength="1000"></textarea>
                @isset($help)<div class="form-text">{{ $help }}</div>@endisset
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" class="btn btn-{{ $btn ?? 'danger' }}">{{ $submit ?? 'ثبت' }}</button>
            </div>
        </form>
    </div>
</div>
