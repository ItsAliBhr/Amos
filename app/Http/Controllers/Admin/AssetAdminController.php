<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetAdminController extends Controller
{
    public function index()
    {
        return redirect()->route('assets.index');
    }

    public function create()
    {
        return view('admin.asset-form', ['asset' => new Asset(['is_active' => true, 'decimal_places' => 0]), 'categories' => AssetCategory::active()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $a = Asset::create($data);
        AuditLogger::log('asset.created', $a, null, $data);

        return redirect()->route('assets.show', $a)->with('success', 'دارایی تعریف شد.');
    }

    public function edit(Asset $asset)
    {
        return view('admin.asset-form', ['asset' => $asset, 'categories' => AssetCategory::all()]);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $this->validated($request, $asset);
        if ($asset->transactions()->where('status', 'approved')->exists() && (int) $data['decimal_places'] < $asset->decimal_places) {
            return back()->withInput()->with('error', 'کاهش دقت اعشار برای دارایی دارای تراکنش مجاز نیست.');
        }
        $asset->fill($data)->save();
        [$o, $n] = AuditLogger::changes($asset);
        if ($n) {
            AuditLogger::log('asset.updated', $asset, $o, $n);
        }

        return redirect()->route('assets.show', $asset)->with('success', 'دارایی به‌روزرسانی شد.');
    }

    private function validated(Request $request, ?Asset $asset): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:32', Rule::unique('assets', 'symbol')->ignore($asset?->id)],
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'unit_name' => ['required', 'string', 'max:32'],
            'decimal_places' => ['required', 'integer', 'between:0,8'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'نام', 'symbol' => 'نماد', 'asset_category_id' => 'دسته', 'unit_name' => 'واحد اندازه‌گیری', 'decimal_places' => 'دقت اعشار']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
