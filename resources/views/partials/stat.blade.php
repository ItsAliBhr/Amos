<div class="card stat-card h-100">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="icon"><i class="bi bi-{{ $icon ?? 'circle' }}"></i></div>
        <div class="min-w-0">
            <div class="label">{{ $label }}</div>
            <div class="value {{ $class ?? '' }}">{{ $value }}</div>
            @isset($hint)<div class="small text-muted">{{ $hint }}</div>@endisset
        </div>
    </div>
</div>
