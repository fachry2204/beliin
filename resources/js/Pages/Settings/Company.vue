<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
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
}>();
const c = props.setting ?? {};
const activeTab = ref<"company" | "printer" | "roles">("company");
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
        </AuthenticatedLayout
    >
</template>
