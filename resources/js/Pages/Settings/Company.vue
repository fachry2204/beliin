<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import AppModal from "@/Components/UI/AppModal.vue";
interface Company {
    id?: number;
    company_name?: string;
    logo_url?: string;
    favicon_url?: string;
    address?: string;
    city?: string;
    province?: string;
    postal_code?: string;
    phone?: string;
    whatsapp?: string;
    email?: string;
    website?: string;
    tax_number?: string;
    bank_name?: string;
    bank_account_number?: string;
    bank_account_name?: string;
    invoice_footer?: string;
    invoice_prefix?: string;
    default_tax_percentage?: string;
    tax_enabled?: boolean;
    discount_enabled?: boolean;
    commission_margin_warning_percentage?: string;
    printer_type?: string;
    printer_paper_size?: string;
    printer_orientation?: string;
    backup_auto_enabled?: boolean;
    backup_auto_type?: string;
    backup_auto_frequency?: string;
    backup_auto_time?: string;
    backup_retention_count?: number;
    backup_last_run_at?: string | null;
    backup_last_error?: string | null;
}
interface BackupItem {
    filename: string;
    type: "full" | "database";
    automatic: boolean;
    size: number;
    created_at: string;
}
interface PermissionItem {
    name: string;
    label: string;
    description: string;
}
interface PermissionGroup {
    label: string;
    items: PermissionItem[];
}
interface RoleAccess {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
}
const props = defineProps<{
    setting: Company | null;
    roleAccess: { groups: PermissionGroup[]; roles: RoleAccess[] };
    canDeleteData: boolean;
    cleanupCounts: Record<string, number>;
    backups: BackupItem[];
}>();
const c = props.setting ?? {};
const activeTab = ref<"company" | "printer" | "roles" | "backup" | "cleanup">("company");
const savingRole = ref<number | null>(null);
const rolePermissions = reactive<Record<number, string[]>>(
    Object.fromEntries(
        props.roleAccess.roles.map((role) => [
            role.id,
            role.permissions.map((permission) => permission.name),
        ]),
    ),
);
const selectedRoleId = ref(props.roleAccess.roles[0]?.id ?? 0);
const selectedRole = computed(() =>
    props.roleAccess.roles.find((role) => role.id === selectedRoleId.value),
);
const permissionParents: Record<string, string> = {
    "customers.manage": "customers.view",
    "products.manage": "products.view",
    "couriers.manage": "couriers.view",
    "invoices.create": "invoices.view",
    "invoices.issue": "invoices.view",
    "invoices.cancel": "invoices.view",
    "invoices.delete": "invoices.view",
    "invoices.print": "invoices.view",
    "payments.manage": "payments.view",
    "cash.manage": "cash.view",
    "reports.export": "reports.view",
};
const form = useForm({
    company_name: c.company_name ?? "",
    address: c.address ?? "",
    city: c.city ?? "",
    province: c.province ?? "",
    postal_code: c.postal_code ?? "",
    phone: c.phone ?? "",
    whatsapp: c.whatsapp ?? "",
    email: c.email ?? "",
    website: c.website ?? "",
    tax_number: c.tax_number ?? "",
    bank_name: c.bank_name ?? "",
    bank_account_number: c.bank_account_number ?? "",
    bank_account_name: c.bank_account_name ?? "",
    invoice_footer: c.invoice_footer ?? "",
    invoice_prefix: c.invoice_prefix ?? "INV",
    default_tax_percentage: String(
        Math.round(Number(c.default_tax_percentage ?? 11)),
    ),
    tax_enabled: c.tax_enabled ?? true,
    discount_enabled: c.discount_enabled ?? true,
    commission_margin_warning_percentage: String(
        Math.round(Number(c.commission_margin_warning_percentage ?? 10)),
    ),
    printer_type: c.printer_type ?? "dot_matrix",
    printer_paper_size: c.printer_paper_size ?? "a5",
    printer_orientation: c.printer_orientation ?? "portrait",
    logo: null as File | null,
    favicon: null as File | null,
});
const submit = () =>
    form
        .transform((data) => ({ ...data, _method: "put" }))
        .post(route("company.update"), { forceFormData: true });
const togglePermission = (roleId: number, permission: string) => {
    const selected = rolePermissions[roleId];
    if (selected.includes(permission)) {
        rolePermissions[roleId] = selected.filter(
            (name) =>
                name !== permission && permissionParents[name] !== permission,
        );
        return;
    }

    const parent = permissionParents[permission];
    rolePermissions[roleId] = Array.from(
        new Set([...selected, permission, ...(parent ? [parent] : [])]),
    );
};
const saveRole = (role: RoleAccess) => {
    savingRole.value = role.id;
    router.put(
        route("company.roles.update", role.id),
        { permissions: rolePermissions[role.id] },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (savingRole.value = null),
        },
    );
};
const cleanupOptions = [
    { key: "customers", label: "Data Client", description: "Menghapus seluruh client hanya jika tidak ada data invoice. Data barang tetap disimpan." },
    { key: "invoices", label: "Data Invoice", description: "Menghapus seluruh invoice dan data terkait. Proses diblokir jika pengiriman sudah berjalan atau invoice masih berada di Faktur." },
    { key: "factures", label: "Data Faktur", description: "Menghapus Faktur hanya jika belum memiliki pembayaran dan pengiriman invoice di dalamnya belum berjalan." },
    { key: "shipping", label: "Data Ongkir", description: "Menghapus deposito ongkir, Cash Keluar ongkir, tugas dan foto pengiriman, lalu mengosongkan kurir serta ongkir pada invoice/faktur." },
    { key: "cash_in", label: "Data Cash Masuk", description: "Menghapus pembayaran dan Cash Masuk, mengembalikan invoice menjadi belum dibayar, serta menghapus komisi faktur terkait pembayaran." },
    { key: "cash_out", label: "Data Cash Keluar", description: "Menghapus seluruh Cash Keluar. Ongkir dan komisi yang pernah dibayar dikembalikan menjadi belum dibayar." },
] as const;
const cleanupScope = ref<string | null>(null);
const selectedCleanup = computed(() => cleanupOptions.find((item) => item.key === cleanupScope.value));
const cleanupForm = useForm({ scope: "", password: "", confirmation: "", cleanup: "" });
const openCleanup = (scope: string) => {
    cleanupScope.value = scope;
    cleanupForm.reset();
    cleanupForm.clearErrors();
    cleanupForm.scope = scope;
};
const closeCleanup = () => {
    if (cleanupForm.processing) return;
    cleanupScope.value = null;
    cleanupForm.reset();
    cleanupForm.clearErrors();
};
const purgeData = () => cleanupForm.delete(route("company.data.purge"), {
    preserveScroll: true,
    onSuccess: closeCleanup,
});
const backupForm = useForm({ type: "database", backup: "" });
const createBackup = (type: "full" | "database") => {
    backupForm.type = type;
    backupForm.post(route("company.backups.store"), { preserveScroll: true });
};
const scheduleForm = useForm({
    backup_auto_enabled: c.backup_auto_enabled ?? false,
    backup_auto_type: c.backup_auto_type ?? "database",
    backup_auto_frequency: c.backup_auto_frequency ?? "daily",
    backup_auto_time: c.backup_auto_time ?? "01:00",
    backup_retention_count: c.backup_retention_count ?? 7,
});
const saveBackupSchedule = () =>
    scheduleForm.put(route("company.backups.schedule"), { preserveScroll: true });
const deleteBackup = (filename: string) => {
    if (!window.confirm(`Hapus arsip backup ${filename}?`)) return;
    router.delete(route("company.backups.destroy", filename), { preserveScroll: true });
};
const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
};
const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat("id-ID", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
</script>
<template>
    <Head title="Profil Perusahaan" /><AuthenticatedLayout
        ><template #breadcrumb>Pengaturan / Profil Perusahaan</template>
        <div class="mb-6">
            <h1 class="page-title">Profil Perusahaan</h1>
            <p class="page-subtitle">
                Data ini digunakan pada invoice cetak dan pengaturan
                perhitungan.
            </p>
        </div>
        <div class="mb-5 flex gap-2 border-b border-slate-200" role="tablist">
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'company'"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'company' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                @click="activeTab = 'company'"
            >
                Profil Perusahaan
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'printer'"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'printer' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                @click="activeTab = 'printer'"
            >
                Setting Printer
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'roles'"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'roles' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800'"
                @click="activeTab = 'roles'"
            >
                Akses Role
            </button>
            <button
                v-if="props.canDeleteData"
                type="button"
                role="tab"
                :aria-selected="activeTab === 'backup'"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'backup' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-emerald-600'"
                @click="activeTab = 'backup'"
            >
                Backup Data
            </button>
            <button
                v-if="props.canDeleteData"
                type="button"
                role="tab"
                :aria-selected="activeTab === 'cleanup'"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'cleanup' ? 'border-red-500 text-red-600' : 'border-transparent text-slate-500 hover:text-red-600'"
                @click="activeTab = 'cleanup'"
            >
                Hapus Data
            </button>
        </div>
        <form v-show="activeTab === 'company'" class="space-y-5" @submit.prevent="submit">
            <section class="panel p-5">
                <h2 class="mb-5 font-bold">Identitas Perusahaan</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="label">Nama Perusahaan *</span
                        ><AppInput
                            v-model="form.company_name"
                            required /></label
                    ><label
                        ><span class="label">Logo</span
                        ><input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm"
                            @change="
                                form.logo =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            " />
                        <img
                            v-if="c.logo_url"
                            :src="c.logo_url"
                            alt="Logo perusahaan saat ini"
                            class="mt-3 h-16 w-16 rounded-xl border border-slate-200 bg-white object-contain p-1"
                        />
                    </label
                    ><label
                        ><span class="label">Favicon & Icon Aplikasi</span
                        ><input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm"
                            @change="
                                form.favicon =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                        <span class="mt-1 block text-xs leading-5 text-slate-500">
                            Gunakan gambar persegi minimal 192 x 192 px. Ikon ini dipakai pada tab browser dan aplikasi yang dipasang di Android/iOS.
                        </span>
                        <img
                            v-if="c.favicon_url"
                            :src="c.favicon_url"
                            alt="Favicon saat ini"
                            class="mt-3 h-16 w-16 rounded-xl border border-slate-200 bg-white object-contain p-1"
                        />
                    </label
                    ><label
                        ><span class="label">Email</span
                        ><AppInput v-model="form.email" type="email" /></label
                    ><label
                        ><span class="label">Telepon</span
                        ><AppInput v-model="form.phone" /></label
                    ><label
                        ><span class="label">Website</span
                        ><AppInput v-model="form.website" /></label
                    ><label
                        ><span class="label">NPWP</span
                        ><AppInput v-model="form.tax_number" /></label
                    ><label class="sm:col-span-2"
                        ><span class="label">Alamat</span
                        ><AppTextarea v-model="form.address"
                    /></label>
                </div>
            </section>
            <section class="panel p-5">
                <h2 class="mb-5 font-bold">Rekening & Invoice</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="label">Bank</span
                        ><AppInput v-model="form.bank_name" /></label
                    ><label
                        ><span class="label">Nomor Rekening</span
                        ><AppInput v-model="form.bank_account_number" /></label
                    ><label
                        ><span class="label">Nama Pemilik Rekening</span
                        ><AppInput v-model="form.bank_account_name" /></label
                    ><label
                        ><span class="label">Prefix Invoice</span
                        ><AppInput
                            v-model="form.invoice_prefix"
                            required /></label
                    ><label
                        ><span class="label">Pajak Default (%)</span
                        ><AppInput
                            v-model="form.default_tax_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="1"
                            :disabled="!form.tax_enabled"
                    /></label>
                    <label
                        ><span class="label"
                            >Ambang Peringatan Komisi dari Margin (%)</span
                        ><AppInput
                            v-model="form.commission_margin_warning_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="1"
                            required
                        /><span class="mt-1 block text-xs text-slate-500"
                            >Peringatan muncul jika komisi melampaui persentase
                            ini dari total margin Faktur.</span
                        ></label
                    >
                    <div class="sm:col-span-2 grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <span>
                                <strong class="block text-sm"
                                    >Aktifkan Pajak</strong
                                >
                                <span class="text-xs text-slate-500"
                                    >Tampilkan dan hitung pajak pada invoice
                                    baru.</span
                                >
                            </span>
                            <input
                                v-model="form.tax_enabled"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                            />
                        </label>
                        <label
                            class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <span>
                                <strong class="block text-sm"
                                    >Aktifkan Diskon</strong
                                >
                                <span class="text-xs text-slate-500"
                                    >Tampilkan pilihan diskon pada invoice
                                    baru.</span
                                >
                            </span>
                            <input
                                v-model="form.discount_enabled"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                            />
                        </label>
                    </div>
                    <label class="sm:col-span-2"
                        ><span class="label">Footer Invoice</span
                        ><AppTextarea v-model="form.invoice_footer"
                    /></label>
                </div>
            </section>
            <p
                v-if="Object.keys(form.errors).length"
                class="text-sm text-red-600"
            >
                {{ Object.values(form.errors)[0] }}
            </p>
            <div class="flex justify-end">
                <AppButton type="submit" :disabled="form.processing"
                    >Simpan Pengaturan</AppButton
                >
            </div>
        </form>

        <form v-show="activeTab === 'printer'" class="space-y-5" @submit.prevent="submit">
            <section class="panel p-5">
                <div class="mb-5">
                    <h2 class="text-lg font-bold">Setting Printer</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Jenis kertas dan orientasi ini digunakan saat mencetak maupun mengunduh PDF Invoice dan Faktur.
                    </p>
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <label>
                        <span class="label">Jenis Printer *</span>
                        <select
                            v-model="form.printer_type"
                            required
                            class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                            <option value="dot_matrix">Dot Matrix</option>
                            <option value="inkjet">Inkjet</option>
                            <option value="laser">Laser</option>
                            <option value="thermal">Thermal</option>
                        </select>
                    </label>
                    <label>
                        <span class="label">Jenis Kertas *</span>
                        <select
                            v-model="form.printer_paper_size"
                            required
                            class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                            <option value="a5">A5</option>
                            <option value="a4">A4</option>
                            <option value="letter">Letter</option>
                            <option value="legal">Legal</option>
                            <option value="continuous_9_5x11">Continuous Form 9,5 × 11 inci</option>
                            <option value="thermal_80">Thermal 80 mm</option>
                            <option value="thermal_58">Thermal 58 mm</option>
                        </select>
                    </label>
                    <label>
                        <span class="label">Orientasi *</span>
                        <select
                            v-model="form.printer_orientation"
                            required
                            class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        >
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                    Saat tombol Cetak dipilih, aplikasi langsung membuka dialog print browser tanpa halaman preview aplikasi dan tanpa tombol cetak tambahan.
                </div>
            </section>
            <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
                {{ Object.values(form.errors)[0] }}
            </p>
            <div class="flex justify-end">
                <AppButton type="submit" :disabled="form.processing">
                    Simpan Setting Printer
                </AppButton>
            </div>
        </form>

        <section v-show="activeTab === 'roles'" class="space-y-5">
            <div class="panel grid gap-5 p-5 lg:grid-cols-[1fr_320px] lg:items-end">
                <div>
                    <h2 class="text-lg font-bold">Akses Halaman Berdasarkan Role</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Pilih role, lalu tentukan akses berdasarkan kelompok menu utama. Perubahan berlaku pada menu serta akses URL langsung.
                    </p>
                </div>
                <label>
                    <span class="label">Pilih Role</span>
                    <select
                        v-model.number="selectedRoleId"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 shadow-sm focus:border-sky-500 focus:ring-sky-500"
                    >
                        <option
                            v-for="roleItem in props.roleAccess.roles"
                            :key="roleItem.id"
                            :value="roleItem.id"
                        >
                            {{ roleItem.name }}
                        </option>
                    </select>
                </label>
            </div>

            <article
                v-if="selectedRole"
                class="panel overflow-hidden"
            >
                <header class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-slate-900">Akses Role {{ selectedRole.name }}</h3>
                            <span
                                v-if="selectedRole.name === 'Super Admin'"
                                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                            >Akses penuh</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ selectedRole.name === 'Super Admin' ? 'Akses Super Admin dikunci untuk menjaga administrasi sistem.' : `${rolePermissions[selectedRole.id].length} izin dipilih` }}
                        </p>
                    </div>
                    <AppButton
                        v-if="selectedRole.name !== 'Super Admin'"
                        type="button"
                        :disabled="savingRole === selectedRole.id"
                        @click="saveRole(selectedRole)"
                    >
                        {{ savingRole === selectedRole.id ? 'Menyimpan...' : `Simpan ${selectedRole.name}` }}
                    </AppButton>
                </header>

                <div class="grid gap-0 lg:grid-cols-2">
                    <section
                        v-for="group in props.roleAccess.groups"
                        :key="`${selectedRole.id}-${group.label}`"
                        class="border-b border-slate-100 p-5 lg:border-r"
                    >
                        <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">{{ group.label }}</h4>
                        <div class="space-y-2">
                            <label
                                v-for="permission in group.items"
                                :key="permission.name"
                                class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 transition"
                                :class="rolePermissions[selectedRole.id].includes(permission.name) || selectedRole.name === 'Super Admin' ? 'bg-sky-50/70' : 'bg-white hover:bg-slate-50'"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                    :checked="selectedRole.name === 'Super Admin' || rolePermissions[selectedRole.id].includes(permission.name)"
                                    :disabled="selectedRole.name === 'Super Admin'"
                                    @change="togglePermission(selectedRole.id, permission.name)"
                                />
                                <span>
                                    <strong class="block text-sm text-slate-800">{{ permission.label }}</strong>
                                    <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ permission.description }}</span>
                                </span>
                            </label>
                        </div>
                    </section>
                </div>
            </article>
        </section>

        <section v-if="props.canDeleteData" v-show="activeTab === 'backup'" class="space-y-5">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="text-lg font-bold text-emerald-900">Backup Data</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-800">
                    Arsip disimpan sebagai ZIP di penyimpanan privat server. Unduh dan simpan salinan di lokasi lain agar data tetap aman jika server bermasalah.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <article class="panel flex flex-col justify-between gap-5 p-5">
                    <div>
                        <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 text-xl text-sky-700">▣</div>
                        <h3 class="font-bold text-slate-900">Backup Semua Data</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Berisi file aplikasi, file upload, konfigurasi, dan dump database SQL. Folder vendor, cache, log, dan backup lama tidak disalin.</p>
                    </div>
                    <AppButton type="button" class="self-end" :disabled="backupForm.processing" @click="createBackup('full')">
                        {{ backupForm.processing && backupForm.type === 'full' ? 'Membuat ZIP...' : 'Backup Seluruhnya' }}
                    </AppButton>
                </article>
                <article class="panel flex flex-col justify-between gap-5 p-5">
                    <div>
                        <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-xl text-violet-700">⌁</div>
                        <h3 class="font-bold text-slate-900">Backup Database</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Berisi dump SQL seluruh tabel dan data MySQL. Pilihan ini lebih kecil dan cepat untuk backup rutin.</p>
                    </div>
                    <AppButton type="button" class="self-end" :disabled="backupForm.processing" @click="createBackup('database')">
                        {{ backupForm.processing && backupForm.type === 'database' ? 'Membuat ZIP...' : 'Backup Database' }}
                    </AppButton>
                </article>
            </div>

            <div v-if="backupForm.errors.backup" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
                {{ backupForm.errors.backup }}
            </div>

            <form class="panel p-5" @submit.prevent="saveBackupSchedule">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900">Backup Otomatis</h3>
                        <p class="mt-1 text-sm text-slate-500">Scheduler server akan membuat backup sesuai jadwal dan menghapus arsip lama berdasarkan jumlah penyimpanan.</p>
                    </div>
                    <label class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                        <input v-model="scheduleForm.backup_auto_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                        Aktifkan backup otomatis
                    </label>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label><span class="label">Jenis Backup</span><select v-model="scheduleForm.backup_auto_type" class="w-full rounded-lg border-slate-300 text-sm"><option value="full">Seluruhnya</option><option value="database">Database</option></select></label>
                    <label><span class="label">Frekuensi</span><select v-model="scheduleForm.backup_auto_frequency" class="w-full rounded-lg border-slate-300 text-sm"><option value="daily">Setiap Hari</option><option value="weekly">Setiap 7 Hari</option><option value="monthly">Setiap Bulan</option></select></label>
                    <label><span class="label">Jam Backup</span><AppInput v-model="scheduleForm.backup_auto_time" type="time" /></label>
                    <label><span class="label">Jumlah Arsip Disimpan</span><AppInput v-model="scheduleForm.backup_retention_count" type="number" min="1" max="30" /></label>
                </div>
                <p v-if="Object.keys(scheduleForm.errors).length" class="mt-3 text-sm text-red-600">{{ Object.values(scheduleForm.errors)[0] }}</p>
                <div v-if="c.backup_last_error" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Backup otomatis terakhir gagal: {{ c.backup_last_error }}</div>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                    <strong>Plesk:</strong> pastikan Scheduled Task menjalankan <code class="rounded bg-white px-1.5 py-1">php artisan schedule:run</code> setiap 1 menit. Tanpa task ini, backup manual tetap berjalan tetapi backup otomatis tidak akan dijalankan.
                </div>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Terakhir dijalankan: {{ c.backup_last_run_at ? formatDateTime(c.backup_last_run_at) : 'Belum pernah' }}</p>
                    <AppButton type="submit" :disabled="scheduleForm.processing">{{ scheduleForm.processing ? 'Menyimpan...' : 'Simpan Backup Otomatis' }}</AppButton>
                </div>
            </form>

            <div class="panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-900">Riwayat Backup</h3><p class="mt-1 text-sm text-slate-500">Arsip hanya dapat diakses oleh Super Admin.</p></div>
                <div v-if="!props.backups.length" class="p-8 text-center text-sm text-slate-500">Belum ada file backup.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Nama File</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Ukuran</th><th class="px-5 py-3">Dibuat</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in props.backups" :key="item.filename">
                                <td class="px-5 py-4 font-medium text-slate-800">{{ item.filename }}</td>
                                <td class="px-5 py-4">{{ item.type === 'full' ? 'Seluruhnya' : 'Database' }}</td>
                                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="item.automatic ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700'">{{ item.automatic ? 'Otomatis' : 'Manual' }}</span></td>
                                <td class="px-5 py-4">{{ formatBytes(item.size) }}</td>
                                <td class="px-5 py-4">{{ formatDateTime(item.created_at) }}</td>
                                <td class="px-5 py-4"><div class="flex justify-end gap-2"><a :href="route('company.backups.download', item.filename)" class="rounded-lg border border-sky-300 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">Unduh</a><button type="button" class="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50" @click="deleteBackup(item.filename)">Hapus</button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section v-if="props.canDeleteData" v-show="activeTab === 'cleanup'" class="space-y-5">
            <div class="rounded-xl border border-red-200 bg-red-50 p-5">
                <h2 class="text-lg font-bold text-red-800">Hapus Isi Database</h2>
                <p class="mt-2 text-sm leading-6 text-red-700">
                    Tindakan ini permanen dan tidak dapat dibatalkan. Buat backup database sebelum menghapus data produksi.
                    Data perusahaan, pelanggan, barang, pengguna, role, dan pengaturan tidak ikut dihapus.
                </p>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <article v-for="item in cleanupOptions" :key="item.key" class="panel flex flex-col justify-between gap-5 p-5">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-bold text-slate-900">{{ item.label }}</h3>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ props.cleanupCounts[item.key] ?? 0 }} data
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ item.description }}</p>
                    </div>
                    <button
                        type="button"
                        class="self-end rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        @click="openCleanup(item.key)"
                    >
                        Hapus {{ item.label }}
                    </button>
                </article>
            </div>
        </section>

        <AppModal :show="cleanupScope !== null" :title="`Konfirmasi Hapus ${selectedCleanup?.label ?? 'Data'}`" @close="closeCleanup">
            <form class="space-y-4" @submit.prevent="purgeData">
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-700">
                    {{ selectedCleanup?.description }} Tindakan ini tidak dapat dibatalkan.
                </div>
                <label class="block">
                    <span class="label">Password akun Super Admin</span>
                    <AppInput v-model="cleanupForm.password" type="password" autocomplete="current-password" required />
                    <span v-if="cleanupForm.errors.password" class="mt-1 block text-xs text-red-600">{{ cleanupForm.errors.password }}</span>
                </label>
                <label class="block">
                    <span class="label">Ketik <strong>HAPUS DATA</strong> untuk melanjutkan</span>
                    <AppInput v-model="cleanupForm.confirmation" autocomplete="off" placeholder="HAPUS DATA" required />
                    <span v-if="cleanupForm.errors.confirmation" class="mt-1 block text-xs text-red-600">{{ cleanupForm.errors.confirmation }}</span>
                </label>
                <span v-if="cleanupForm.errors.scope" class="block text-xs text-red-600">{{ cleanupForm.errors.scope }}</span>
                <div v-if="cleanupForm.errors.cleanup" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ cleanupForm.errors.cleanup }}
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" :disabled="cleanupForm.processing" @click="closeCleanup">Batal</button>
                    <button
                        type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="cleanupForm.processing || cleanupForm.confirmation !== 'HAPUS DATA' || !cleanupForm.password"
                    >
                        {{ cleanupForm.processing ? 'Menghapus...' : 'Hapus Permanen' }}
                    </button>
                </div>
            </form>
        </AppModal>
        </AuthenticatedLayout
    >
</template>
