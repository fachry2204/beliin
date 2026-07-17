<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\AuditLogService;
use App\Support\RoleAccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompanySettingController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function edit()
    {
        $this->authorize('settings.manage');
        $catalogPermissions = RoleAccessCatalog::names();
        $setting = CompanySetting::first();

        return Inertia::render('Settings/Company', [
            'setting' => $setting ? [
                ...$setting->toArray(),
                'logo_url' => $setting->logo ? '/storage/'.ltrim($setting->logo, '/') : null,
                'favicon_url' => $setting->favicon ? '/storage/'.ltrim($setting->favicon, '/') : null,
            ] : null,
            'roleAccess' => [
                'groups' => RoleAccessCatalog::groups(),
                'roles' => Role::query()->with('permissions:id,name')->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Role $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->whereIn('name', $catalogPermissions)->values(),
                    ]),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('settings.manage');
        $data = $request->validate([
            'company_name' => 'required|max:200', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:min_width=192,min_height=192,ratio=1/1',
            'address' => 'nullable|max:2000', 'city' => 'nullable|max:100', 'province' => 'nullable|max:100', 'postal_code' => 'nullable|max:10',
            'phone' => 'nullable|max:30', 'whatsapp' => 'nullable|max:30', 'email' => 'nullable|email|max:150', 'website' => 'nullable|url|max:200',
            'tax_number' => 'nullable|max:80', 'bank_name' => 'nullable|max:100', 'bank_account_number' => 'nullable|max:100',
            'bank_account_name' => 'nullable|max:150', 'invoice_footer' => 'nullable|max:3000', 'invoice_prefix' => 'required|alpha_num|max:20',
            'default_tax_percentage' => 'required|integer|min:0|max:100',
            'commission_margin_warning_percentage' => 'required|integer|min:0|max:100',
            'tax_enabled' => 'required|boolean', 'discount_enabled' => 'required|boolean',
            'printer_type' => ['sometimes', 'required', Rule::in(['dot_matrix', 'inkjet', 'laser', 'thermal'])],
            'printer_paper_size' => ['sometimes', 'required', Rule::in(['a4', 'a5', 'letter', 'legal', 'continuous_9_5x11', 'thermal_80', 'thermal_58'])],
            'printer_orientation' => ['sometimes', 'required', Rule::in(['portrait', 'landscape'])],
        ]);
        $setting = CompanySetting::firstOrNew();
        $old = $setting->exists ? $setting->toArray() : null;
        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $data['logo'] = $request->file('logo')->store('company', 'public');
        } else {
            unset($data['logo']);
        }
        if ($request->hasFile('favicon')) {
            if ($setting->favicon) {
                Storage::disk('public')->delete($setting->favicon);
            }
            $data['favicon'] = $request->file('favicon')->store('company', 'public');
        } else {
            unset($data['favicon']);
        }
        $setting->fill($data)->save();
        $this->audit->record('update', 'company_setting', $setting, $old, $setting->fresh()->toArray());

        return back()->with('success', 'Profil perusahaan diperbarui.');
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $this->authorize('settings.manage');
        abort_if($role->name === 'Super Admin', 422, 'Akses Super Admin tidak dapat diubah.');

        $catalog = RoleAccessCatalog::names();
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($catalog)],
        ]);
        $permissions = RoleAccessCatalog::withRequiredParents($data['permissions']);
        $old = $role->permissions()->pluck('name')->all();

        $role->syncPermissions($permissions);
        $this->audit->record('update', 'role_access', $role, ['permissions' => $old], ['permissions' => $permissions]);

        return back()->with('success', "Akses role {$role->name} diperbarui.");
    }
}
