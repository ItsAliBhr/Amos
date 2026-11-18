<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">تنظیمات صندوق</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.cash.*') ? 'active' : '' }}" href="{{ route('admin.cash.index') }}">حساب‌های نقدی</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">دسته‌بندی دارایی</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}">دارایی‌ها</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('admin.users.index') }}">اعضا</a></li>
</ul>
