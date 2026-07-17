<script setup lang="ts">
import { ref, watch } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";
import Pagination from "@/Components/UI/Pagination.vue";
interface LinkItem {
    url: string | null;
    label: string;
    active: boolean;
}
interface PageRows {
    data: Record<string, unknown>[];
    links: LinkItem[];
    from: number;
    to: number;
    total: number;
}
interface Option {
    id: number;
    name: string;
}
const props = withDefaults(
    defineProps<{
        title: string;
        type: "customer" | "courier" | "supplier" | "product" | "category";
        rows: PageRows;
        categories?: Option[];
        canViewCost?: boolean;
        canCreateCourierUser?: boolean;
    }>(),
    { categories: () => [], canViewCost: false, canCreateCourierUser: false },
);
const search = ref(new URLSearchParams(location.search).get("search") ?? "");
const modal = ref(false);
const editingId = ref<number | null>(null);
let timer: number;
const endpoints = {
    customer: "customers",
    courier: "couriers",
    supplier: "suppliers",
    product: "products",
    category: "categories",
} as const;
const form = useForm({
    customer_code: "",
    courier_code: "",
    supplier_code: "",
    name: "",
    company_name: "",
    phone: "",
    whatsapp: "",
    email: "",
    tax_number: "",
    address: "",
    city: "",
    province: "",
    postal_code: "",
    notes: "",
    vehicle_type: "",
    license_plate: "",
    bank_name: "",
    bank_account_number: "",
    bank_account_name: "",
    category_id: "",
    sku: "",
    barcode: "",
    description: "",
    unit: "Pcs",
    purchase_price: "0",
    selling_price: "0",
    minimum_stock: "0",
    is_active: true,
});
watch(search, () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () =>
            router.get(
                route(`${endpoints[props.type]}.index`),
                { search: search.value },
                { preserveState: true, replace: true },
            ),
        350,
    );
});
const str = (row: Record<string, unknown>, key: string) =>
    String(row[key] ?? "");
const num = (row: Record<string, unknown>, key: string) =>
    Number(row[key] ?? 0);
const categoryName = (row: Record<string, unknown>) => {
    const c = row.category as Record<string, unknown> | undefined;
    return String(c?.name ?? "-");
};
const money = (v: unknown) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v ?? 0));
const openCreate = () => {
    if (props.type === "courier") {
        router.get(route("users.index"), { role: "Kurir", create: 1 });
        return;
    }
    editingId.value = null;
    form.reset();
    form.is_active = true;
    modal.value = true;
};
const openEdit = (row: Record<string, unknown>) => {
    editingId.value = Number(row.id);
    Object.keys(form.data()).forEach((key) => {
        if (key in row) {
            const value = row[key];
            (form as unknown as Record<string, unknown>)[key] =
                typeof value === "boolean" ? value : String(value ?? "");
        }
    });
    modal.value = true;
};
const submit = () => {
    const options = {
        onSuccess: () => {
            modal.value = false;
            form.reset();
        },
    };
    if (editingId.value)
        form.put(
            route(`${endpoints[props.type]}.update`, editingId.value),
            options,
        );
    else form.post(route(`${endpoints[props.type]}.store`), options);
};
const remove = (id: number) => {
    if (confirm("Nonaktifkan/hapus data ini?"))
        router.delete(route(`${endpoints[props.type]}.destroy`, id));
};
</script>
<template>
    <Head :title="title" /><AuthenticatedLayout
        ><template #breadcrumb>Master Data / {{ title }}</template>
        <div
            class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">{{ title }}</h1>
                <p class="page-subtitle">
                    Kelola data secara aman dengan pencarian dan pagination
                    server-side.
                </p>
            </div>
            <AppButton
                v-if="type !== 'courier' || canCreateCourierUser"
                @click="openCreate"
            >
                {{
                    type === "courier"
                        ? "+ Tambah Pengguna Kurir"
                        : "+ Tambah Data"
                }}
            </AppButton>
        </div>
        <section class="panel">
            <div class="border-b border-slate-200 p-4">
                <SearchInput
                    v-model="search"
                    class="max-w-md"
                    :placeholder="`Cari ${title.toLowerCase()}...`"
                />
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <template
                                v-if="
                                    type === 'customer' || type === 'supplier'
                                "
                                ><th>Kode</th>
                                <th>Nama</th>
                                <th>Perusahaan</th>
                                <th>Kontak</th>
                                <th>Kota</th></template
                            ><template v-else-if="type === 'courier'"
                                ><th>Kode</th>
                                <th>Nama Kurir</th>
                                <th>Telepon</th>
                                <th>Kendaraan</th>
                                <th>No. Polisi</th></template
                            ><template v-else-if="type === 'product'"
                                ><th>SKU</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th v-if="canViewCost">Harga Beli</th>
                                <th>Harga Jual</th></template
                            ><template v-else
                                ><th>Nama</th>
                                <th>Deskripsi</th>
                                <th>Jumlah Barang</th></template
                            >
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="num(row, 'id')">
                            <template
                                v-if="
                                    type === 'customer' || type === 'supplier'
                                "
                                ><td class="font-semibold text-sky-700">
                                    {{
                                        str(
                                            row,
                                            type === "customer"
                                                ? "customer_code"
                                                : "supplier_code",
                                        )
                                    }}
                                </td>
                                <td>{{ str(row, "name") }}</td>
                                <td>{{ str(row, "company_name") || "-" }}</td>
                                <td>
                                    <div>{{ str(row, "phone") || "-" }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ str(row, "email") }}
                                    </div>
                                </td>
                                <td>{{ str(row, "city") || "-" }}</td></template
                            ><template v-else-if="type === 'courier'"
                                ><td class="font-semibold text-sky-700">
                                    {{ str(row, "courier_code") }}
                                </td>
                                <td>{{ str(row, "name") }}</td>
                                <td>{{ str(row, "phone") || "-" }}</td>
                                <td>{{ str(row, "vehicle_type") || "-" }}</td>
                                <td>
                                    {{ str(row, "license_plate") || "-" }}
                                </td></template
                            ><template v-else-if="type === 'product'"
                                ><td class="font-semibold text-sky-700">
                                    {{ str(row, "sku") }}
                                </td>
                                <td>
                                    {{ str(row, "name") }}
                                    <div class="text-xs text-slate-400">
                                        {{ str(row, "unit") }}
                                    </div>
                                </td>
                                <td>{{ categoryName(row) }}</td>
                                <td v-if="canViewCost">
                                    {{ money(row.purchase_price) }}
                                </td>
                                <td>
                                    {{ money(row.selling_price) }}
                                </td></template
                            ><template v-else
                                ><td class="font-semibold">
                                    {{ str(row, "name") }}
                                </td>
                                <td class="max-w-sm truncate">
                                    {{ str(row, "description") || "-" }}
                                </td>
                                <td>
                                    {{ str(row, "products_count") }}
                                </td></template
                            >
                            <td>
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="
                                        row.is_active
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                    >{{
                                        row.is_active ? "Aktif" : "Nonaktif"
                                    }}</span
                                >
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="type === 'courier'"
                                        :href="
                                            route(
                                                'couriers.show',
                                                num(row, 'id'),
                                            )
                                        "
                                        class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    >
                                        Lihat
                                    </Link>
                                    <button
                                        class="rounded border border-sky-200 px-2 py-1 text-xs font-semibold text-sky-600 hover:bg-sky-50"
                                        @click="openEdit(row)"
                                    >
                                        Edit</button
                                    ><button
                                        class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        @click="remove(num(row, 'id'))"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                colspan="8"
                                class="py-12 text-center text-slate-500"
                            >
                                Belum ada data.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="flex items-center justify-between border-t border-slate-200 p-4"
            >
                <span class="text-xs text-slate-500"
                    >{{ rows.from ?? 0 }}-{{ rows.to ?? 0 }} dari
                    {{ rows.total }}</span
                ><Pagination :links="rows.links" />
            </div>
        </section>
        <AppModal
            :show="modal"
            :title="`${editingId ? 'Edit' : 'Tambah'} ${title}`"
            @close="modal = false"
            ><form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <template v-if="type === 'customer' || type === 'supplier'"
                    ><label
                        ><span class="label">Kode *</span
                        ><AppInput
                            v-if="type === 'customer'"
                            v-model="form.customer_code"
                            required /><AppInput
                            v-else
                            v-model="form.supplier_code"
                            required /></label
                    ><label
                        ><span class="label">Nama *</span
                        ><AppInput v-model="form.name" required /></label
                    ><label
                        ><span class="label">Perusahaan</span
                        ><AppInput v-model="form.company_name" /></label
                    ><label
                        ><span class="label">Telepon</span
                        ><AppInput v-model="form.phone" /></label
                    ><label
                        ><span class="label">WhatsApp</span
                        ><AppInput v-model="form.whatsapp" /></label
                    ><label
                        ><span class="label">Email</span
                        ><AppInput v-model="form.email" type="email" /></label
                    ><label
                        ><span class="label">NPWP</span
                        ><AppInput v-model="form.tax_number" /></label
                    ><label
                        ><span class="label">Kota</span
                        ><AppInput v-model="form.city" /></label
                    ><label class="sm:col-span-2"
                        ><span class="label">Alamat</span
                        ><AppTextarea
                            v-model="form.address" /></label></template
                ><template v-else-if="type === 'courier'"
                    ><label
                        ><span class="label">Kode Kurir *</span
                        ><AppInput
                            v-model="form.courier_code"
                            required /></label
                    ><label
                        ><span class="label">Nama Kurir *</span
                        ><AppInput v-model="form.name" required /></label
                    ><label
                        ><span class="label">Telepon</span
                        ><AppInput v-model="form.phone" /></label
                    ><label
                        ><span class="label">Jenis Kendaraan</span
                        ><AppInput v-model="form.vehicle_type" /></label
                    ><label
                        ><span class="label">Nomor Polisi</span
                        ><AppInput v-model="form.license_plate" /></label
                    ><label
                        ><span class="label">Nama Bank</span
                        ><AppInput
                            v-model="form.bank_name"
                            placeholder="Contoh: BCA" /></label
                    ><label
                        ><span class="label">Nomor Rekening</span
                        ><AppInput
                            v-model="form.bank_account_number"
                            inputmode="numeric" /></label
                    ><label class="sm:col-span-2"
                        ><span class="label">Atas Nama Rekening</span
                        ><AppInput v-model="form.bank_account_name" /></label
                    ><label class="sm:col-span-2"
                        ><span class="label">Catatan</span
                        ><AppTextarea v-model="form.notes" /></label></template
                ><template v-else-if="type === 'product'"
                    ><label
                        ><span class="label">SKU *</span
                        ><AppInput v-model="form.sku" required /></label
                    ><label
                        ><span class="label">Nama Barang *</span
                        ><AppInput v-model="form.name" required /></label
                    ><label
                        ><span class="label">Kategori *</span
                        ><AppSelect v-model="form.category_id" required
                            ><option value="">Pilih kategori</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option></AppSelect
                        ></label
                    ><label
                        ><span class="label">Satuan *</span
                        ><AppInput v-model="form.unit" required /></label
                    ><label
                        ><span class="label">Harga Beli</span
                        ><AppInput
                            v-model="form.purchase_price"
                            type="number"
                            min="0" /></label
                    ><label
                        ><span class="label">Harga Jual</span
                        ><AppInput
                            v-model="form.selling_price"
                            type="number"
                            min="0" /></label
                    ><label
                        ><span class="label">Barcode</span
                        ><AppInput v-model="form.barcode" /></label></template
                ><template v-else
                    ><label class="sm:col-span-2"
                        ><span class="label">Nama Kategori *</span
                        ><AppInput v-model="form.name" required /></label
                    ><label class="sm:col-span-2"
                        ><span class="label">Deskripsi</span
                        ><AppTextarea
                            v-model="form.description" /></label></template
                ><label class="flex items-center gap-2 sm:col-span-2"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded border-slate-300 text-sky-500 focus:ring-sky-500"
                    />
                    Aktif</label
                >
                <div
                    v-if="Object.keys(form.errors).length"
                    class="sm:col-span-2 rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ Object.values(form.errors)[0] }}
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="modal = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="form.processing"
                        >Simpan</AppButton
                    >
                </div>
            </form></AppModal
        ></AuthenticatedLayout
    >
</template>
