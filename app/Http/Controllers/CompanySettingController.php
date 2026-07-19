<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\DatabaseCleanupService;
use App\Support\RoleAccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompanySettingController extends Controller
{
    public function __construct(private AuditLogService $audit, private DatabaseCleanupService $cleanup, private BackupService $backups) {}

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
            'canDeleteData' => auth()->user()->hasRole('Super Admin'),
            'cleanupCounts' => auth()->user()->hasRole('Super Admin') ? $this->cleanup->counts() : [],
            'backups' => auth()->user()->hasRole('Super Admin') ? $this->backups->all() : [],
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

    public function purgeData(Request $request)
    {
        $this->authorize('settings.manage');
        abort_unless($request->user()->hasRole('Super Admin'), 403);

        $data = $request->validate([
            'scope' => ['required', Rule::in(DatabaseCleanupService::SCOPES)],
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['HAPUS DATA'])],
        ], [
            'password.current_password' => 'Password akun tidak sesuai.',
            'confirmation.in' => 'Ketik HAPUS DATA untuk melanjutkan.',
        ]);

        try {
            $result = $this->cleanup->purge($data['scope']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = strtoupper(Str::random(8));
            Log::error('Database cleanup failed.', [
                'reference' => $reference,
                'scope' => $data['scope'],
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return back()->withErrors([
                'cleanup' => "Data belum dihapus karena terjadi kendala database. Referensi: {$reference}. Pastikan seluruh migrasi sudah dijalankan di server.",
            ]);
        }

        $deleted = $result['before'][$data['scope']] - $result['after'][$data['scope']];

        return back()->with('success', "Pembersihan data selesai. {$deleted} data utama telah dihapus.");
    }

    public function createBackup(Request $request)
    {
        $this->authorizeBackup($request);
        $data = $request->validate(['type' => ['required', Rule::in(BackupService::TYPES)]]);

        try {
            $backup = $this->backups->create($data['type']);
            $this->audit->record('create', 'backup', null, null, ['filename' => $backup['filename'], 'type' => $data['type']]);

            return back()->with('success', 'Backup berhasil dibuat dan siap diunduh.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => 'Backup gagal dibuat: '.$exception->getMessage()]);
        }
    }

    public function updateBackupSchedule(Request $request)
    {
        $this->authorizeBackup($request);
        $data = $request->validate([
            'backup_auto_enabled' => ['required', 'boolean'],
            'backup_auto_type' => ['required', Rule::in(BackupService::TYPES)],
            'backup_auto_frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'backup_auto_time' => ['required', 'date_format:H:i'],
            'backup_retention_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $setting = CompanySetting::firstOrNew();
        $setting->fill($data)->save();

        return back()->with('success', 'Pengaturan backup otomatis disimpan.');
    }

    public function downloadBackup(Request $request, string $filename)
    {
        $this->authorizeBackup($request);

        return response()->download($this->backups->path($filename), $filename, ['Content-Type' => 'application/zip']);
    }

    public function deleteBackup(Request $request, string $filename)
    {
        $this->authorizeBackup($request);
        $this->backups->delete($filename);

        return back()->with('success', 'Arsip backup dihapus.');
    }

    private function authorizeBackup(Request $request): void
    {
        $this->authorize('settings.manage');
        abort_unless($request->user()->hasRole('Super Admin'), 403);
    }
}
