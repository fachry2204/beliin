<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import CurrencyInput from "@/Components/UI/CurrencyInput.vue";
import DateInput from "@/Components/UI/DateInput.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";

interface CashRow {
    id: number;
    payment_id?: number;
    invoice_id?: number;
    invoice_cost_id?: number;
    combined_invoice_document_id?: number;
    facture_commission_exists?: boolean;
    transaction_number: string;
    transaction_date: string;
    category: string;
    description: string;
    payment_method: string;
    amount: string;
    reference_number?: string;
    notes?: string;
    creator: { name: string };
}
interface PageRows {
    data: CashRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
}

const props = defineProps<{
    type: "in" | "out";
    rows: PageRows;
    typeTotal: number;
    cashBalance: number;
}>();
const incoming = computed(() => props.type === "in");
const title = computed(() => (incoming.value ? "Cash Masuk" : "Cash Keluar"));
const endpoint = computed(() => (incoming.value ? "cash-in" : "cash-out"));
const search = ref(new URLSearchParams(location.search).get("search") ?? "");
const modal = ref(false);
const editingId = ref<number | null>(null);
let timer: number;
const today = new Date().toISOString().slice(0, 10);
const form = useForm({
    transaction_date: today,
    category: "",
    description: "",
    payment_method: "cash",
    amount: "",
    reference_number: "",
    notes: "",
});

watch(search, () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () =>
            router.get(
                route(`${endpoint.value}.index`),
                { search: search.value },
                { preserveState: true, replace: true },
            ),
        350,
    );
});

const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const date = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("id-ID");
const method = (value: string) =>
    ({
        cash: "Tunai",
        transfer: "Transfer",
        card: "Kartu",
        qris: "QRIS",
        virtual_account: "Virtual Account",
        other: "Lainnya",
    })[value] ?? value;
const category = (row: CashRow) =>
    row.invoice_id || row.combined_invoice_document_id
        ? "Ongkir Driver"
        : row.invoice_cost_id
          ? "Harga Modal"
          : row.category;

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.transaction_date = today;
    form.payment_method = "cash";
    modal.value = true;
};
const openEdit = (row: CashRow) => {
    editingId.value = row.id;
    form.transaction_date = row.transaction_date.slice(0, 10);
    form.category = row.category;
    form.description = row.description;
    form.payment_method = row.payment_method;
    form.amount = row.amount;
    form.reference_number = row.reference_number ?? "";
    form.notes = row.notes ?? "";
    modal.value = true;
};
const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = false;
            form.reset();
        },
    };
    if (editingId.value) {
        form.put(route(`${endpoint.value}.update`, editingId.value), options);
    } else {
        form.post(route(`${endpoint.value}.store`), options);
    }
};
const remove = (row: CashRow) => {
    if (confirm(`Hapus transaksi ${row.transaction_number}?`)) {
        router.delete(route(`${endpoint.value}.destroy`, row.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <template #breadcrumb>Transaksi / {{ title }}</template>
        <div
            class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">{{ title }}</h1>
                <p class="page-subtitle">
                    {{
                        incoming
                            ? "Catat seluruh penerimaan kas."
                            : "Catat seluruh pengeluaran kas."
                    }}
                </p>
            </div>
            <AppButton @click="openCreate">+ Tambah {{ title }}</AppButton>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <section class="panel p-5">
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                    Total {{ title }}
                </p>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="incoming ? 'text-emerald-600' : 'text-red-600'"
                >
                    {{ money(typeTotal) }}
                </p>
            </section>
            <section class="panel p-5">
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                    Saldo Kas Berjalan
                </p>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="cashBalance >= 0 ? 'text-sky-600' : 'text-red-600'"
                >
                    {{ money(cashBalance) }}
                </p>
            </section>
        </div>

        <section class="panel">
            <div class="border-b border-slate-200 p-4">
                <SearchInput
                    v-model="search"
                    class="max-w-md"
                    placeholder="Cari nomor, kategori, keterangan, atau referensi..."
                />
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th>Kategori / Sumber</th>
                            <th>Keterangan / Referensi</th>
                            <th>Metode</th>
                            <th class="text-right">Nominal</th>
                            <th>Dibuat Oleh</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="row.id">
                            <td class="font-semibold text-sky-700">
                                {{ row.transaction_number }}
                            </td>
                            <td>{{ date(row.transaction_date) }}</td>
                            <td>
                                <div>{{ category(row) }}</div>
                                <span
                                    class="mt-1 inline-block rounded-full px-2 py-1 text-[10px] font-semibold"
                                    :class="
                                        row.payment_id ||
                                        row.invoice_id ||
                                        row.invoice_cost_id ||
                                        row.combined_invoice_document_id ||
                                        row.facture_commission_exists
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                    >{{ 
                                        row.payment_id
                                            ? "Pembayaran Invoice"
                                            : row.invoice_id
                                              ? "Ongkir Driver"
                                              : row.invoice_cost_id
                                                ? "Modal Invoice"
                                                : row.combined_invoice_document_id
                                                  ? "Ongkir Driver Faktur"
                                                  : row.facture_commission_exists
                                                    ? "Komisi Faktur"
                                                    : "Input Manual"
                                    }}</span
                                >
                            </td>
                            <td class="max-w-xs">
                                <div>{{ row.description }}</div>
                                <div class="mt-1 text-xs text-slate-400">
                                    Ref: {{ row.reference_number || "-" }}
                                </div>
                            </td>
                            <td>{{ method(row.payment_method) }}</td>
                            <td
                                class="text-right font-semibold"
                                :class="
                                    incoming
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                "
                            >
                                {{ incoming ? "+" : "-" }}
                                {{ money(row.amount) }}
                            </td>
                            <td>{{ row.creator.name }}</td>
                            <td>
                                <div
                                    v-if="
                                        !row.payment_id &&
                                        !row.invoice_id &&
                                        !row.invoice_cost_id &&
                                        !row.combined_invoice_document_id &&
                                        !row.facture_commission_exists
                                    "
                                    class="flex justify-end gap-2"
                                >
                                    <button
                                        class="rounded border border-sky-200 px-2 py-1 text-xs font-semibold text-sky-600 hover:bg-sky-50"
                                        @click="openEdit(row)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        @click="remove(row)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="block text-right text-xs text-slate-400"
                                    >Terkunci</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                colspan="8"
                                class="py-12 text-center text-slate-500"
                            >
                                Belum ada transaksi {{ title.toLowerCase() }}.
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
                >
                <Pagination :links="rows.links" />
            </div>
        </section>

        <AppModal
            :show="modal"
            :title="`${editingId ? 'Edit' : 'Tambah'} ${title}`"
            @close="modal = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label>
                    <span class="label">Tanggal *</span>
                    <DateInput v-model="form.transaction_date" required />
                </label>
                <label>
                    <span class="label">Kategori *</span>
                    <AppInput
                        v-model="form.category"
                        :placeholder="
                            incoming
                                ? 'Contoh: Penjualan'
                                : 'Contoh: Operasional'
                        "
                        required
                    />
                </label>
                <label class="sm:col-span-2">
                    <span class="label">Keterangan *</span>
                    <AppInput v-model="form.description" required />
                </label>
                <label>
                    <span class="label">Metode Pembayaran *</span>
                    <AppSelect v-model="form.payment_method" required>
                        <option value="cash">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="card">Kartu</option>
                        <option value="qris">QRIS</option>
                        <option value="other">Lainnya</option>
                    </AppSelect>
                </label>
                <label>
                    <span class="label">Nominal *</span>
                    <CurrencyInput v-model="form.amount" required />
                </label>
                <label class="sm:col-span-2">
                    <span class="label">Nomor Referensi</span>
                    <AppInput v-model="form.reference_number" />
                </label>
                <label class="sm:col-span-2">
                    <span class="label">Catatan</span>
                    <AppTextarea v-model="form.notes" />
                </label>
                <div
                    v-if="Object.keys(form.errors).length"
                    class="sm:col-span-2 rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ Object.values(form.errors)[0] }}
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="modal = false"
                        >Batal</AppButton
                    >
                    <AppButton type="submit" :disabled="form.processing"
                        >Simpan</AppButton
                    >
                </div>
            </form>
        </AppModal>
    </AuthenticatedLayout>
</template>
