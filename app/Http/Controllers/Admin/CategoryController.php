<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories', ['categories' => AssetCategory::withCount('assets')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:32', 'unique:asset_categories,code'],
        ], [], ['name' => 'نام', 'code' => 'کد']);
        $c = AssetCategory::create($data + ['is_active' => true]);
        AuditLogger::log('category.created', $c, null, $data);

        return back()->with('success', 'دسته‌بندی ایجاد شد.');
    }

    public function update(Request $request, AssetCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:32', Rule::unique('asset_categories', 'code')->ignore($category->id)],
        ]);
        $category->fill($data + ['is_active' => $request->boolean('is_active')])->save();
        [$o, $n] = AuditLogger::changes($category);
        if ($n) {
            AuditLogger::log('category.updated', $category, $o, $n);
        }

        return back()->with('success', 'دسته‌بندی به‌روزرسانی شد.');
    }
}
