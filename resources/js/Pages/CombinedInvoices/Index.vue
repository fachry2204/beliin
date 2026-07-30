<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import { percentageText } from "@/utils/percentage";

interface Customer {
    id: number;
    customer_code: string;
    name: string;
    company_name?: string;
}

interface DocumentRow {
    id: number;
    facture_number: string;
    opened_at: string;
    due_date?: string | null;
    status: "open" | "closed";
    invoices_count: number;
    grand_total: string;
    paid_total: string;
    remaining_total: string;
    gross_profit_total?: string;
    subtotal_total?: string;
    discount_total?: string;
    customer: Customer;
}

interface PageData {
    data: DocumentRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

interface StatusSummary {
    paid: number;
    partially_paid: number;
    unpaid: number;
}

const props = defineProps<{
    documents: PageData;
    statusSummary: StatusSummary;
    canViewProfit: boolean;
    canCreate: boolean;
}>();

type PaymentStatus = "paid" | "partially_paid" | "unpaid";

const params = new URLSearchParams(location.search);
const search = ref(params.get("search") ?? "");
const activeStatus = ref<PaymentStatus | null>(
    (["paid", "partially_paid", "unpaid"] as const).includes(
        params.get("status") as PaymentStatus,
    )
        ? (params.get("status") as PaymentStatus)
        : null,
);

const applyFilters = () =>
    router.get(
        route("combined-invoices.index"),
        {
            search: search.value || undefined,
            status: activeStatus.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );

const filter = () => applyFilters();

const filterByStatus = (status: PaymentStatus) => {
    activeStatus.value = activeStatus.value === status ? null : status;
    applyFilters();
};

const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));

const date = (value?: string | null) =>
    value
        ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("id-ID")
        : "Tanpa jatuh tempo";

const marginRate = (row: DocumentRow) => {
    const base =
        Number(row.subtotal_total || 0) - Number(row.discount_total || 0);

    return percentageText(
        base > 0 ? (Number(row.gross_profit_total || 0) / base) * 100 : 0,
    );
};

const paymentStatus = (row: DocumentRow): PaymentStatus => {
    if (Number(row.remaining_total) <= 0 || row.status === "closed") {
        return "paid";
    }

    return Number(row.paid_total) > 0 ? "partially_paid" : "unpaid";
};

const rowClass = (row: DocumentRow) =>
    ({
        paid: "bg-emerald-50/80 hover:bg-emerald-100/80",
        partially_paid: "bg-amber-50/80 hover:bg-amber-100/80",
        unpaid: "bg-red-50/80 hover:bg-red-100/80",
    })[paymentStatus(row)];

const statusLabel = (row: DocumentRow) =>
    ({
        paid: "Lunas",
        partially_paid: "Bayar Sebagian",
        unpaid: "Belum Lunas",
    })[paymentStatus(row)];

const statusClass = (row: DocumentRow) =>
    ({
        paid: "bg-emerald-100 text-emerald-700",
        partially_paid: "bg-amber-100 text-amber-700",
        unpaid: "bg-red-100 text-red-700",
    })[paymentStatus(row)];

const statusCards = [
    {
        key: "paid" as const,
        label: "Faktur Lunas",
        description: "Seluruh tagihan sudah dibayar",
        className: "border-emerald-200 bg-emerald-50 text-emerald-800",
    },
    {
        key: "partially_paid" as const,
        label: "Bayar Sebagian",
        description: "Masih memiliki sisa tagihan",
        className: "border-amber-200 bg-amber-50 text-amber-800",
    },
    {
        key: "unpaid" as const,
        label: "Belum Lunas",
        description: "Belum ada pembayaran",
        className: "border-red-200 bg-red-50 text-red-800",
    },
];
</script>

<template>
    <Head title="Faktur" />
    <AuthenticatedLayout>
        <template #breadcrumb>Transaksi / Faktur</template>

        <div
            class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">Faktur</h1>
                <p class="page-subtitle">
                    Faktur hanya dibuat dari invoice yang dipilih secara manual.
                </p>
            </div>
            <Link
                v-if="canCreate"
                :href="route('combined-invoices.create')"
            >
                <AppButton>+ Faktur Baru</AppButton>
            </Link>
        </div>

        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <button
                v-for="card in statusCards"
                :key="card.key"
                type="button"
                class="rounded-xl border p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
                :class="[
                    card.className,
                    activeStatus === card.key
                        ? 'ring-2 ring-sky-600 ring-offset-2'
                        : '',
                ]"
                :aria-pressed="activeStatus === card.key"
                :aria-label="`Tampilkan ${card.label}`"
                @click="filterByStatus(card.key)"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold">{{ card.label }}</p>
                    <span
                        v-if="activeStatus === card.key"
                        class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold shadow-sm"
                    >
                        Filter aktif
                    </span>
                </div>
                <p class="mt-2 text-3xl font-bold">
                    {{ props.statusSummary[card.key] }}
                </p>
                <p class="mt-1 text-xs opacity-75">{{ card.description }}</p>
            </button>
        </div>

        <section class="panel">
            <form
                class="flex gap-3 border-b p-4"
                @submit.prevent="filter"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Cari nomor faktur atau pelanggan..."
                    class="flex-1"
                />
                <AppButton type="submit">Cari</AppButton>
            </form>

            <div class="table-wrap">
                <table class="data-table min-w-[1150px]">
                    <thead>
                        <tr>
                            <th>Nomor Faktur</th>
                            <th>Pelanggan</th>
                            <th>Dibuat</th>
                            <th>Jatuh Tempo</th>
                            <th>Invoice</th>
                            <th>Total Tagihan</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
                            <th v-if="canViewProfit">Margin</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in documents.data"
                            :key="row.id"
                            :class="rowClass(row)"
                        >
                            <td class="font-semibold text-sky-700">
                                {{ row.facture_number }}
                            </td>
                            <td>
                                <strong>{{
                                    row.customer.company_name || row.customer.name
                                }}</strong>
                                <div class="text-xs text-slate-500">
                                    {{ row.customer.name }} ·
                                    {{ row.customer.customer_code }}
                                </div>
                            </td>
                            <td>{{ date(row.opened_at) }}</td>
                            <td>{{ date(row.due_date) }}</td>
                            <td>{{ row.invoices_count }} invoice</td>
                            <td>{{ money(row.grand_total || 0) }}</td>
                            <td class="font-semibold text-emerald-700">
                                {{ money(row.paid_total || 0) }}
                            </td>
                            <td class="font-bold text-red-700">
                                {{ money(row.remaining_total || 0) }}
                            </td>
                            <td v-if="canViewProfit">
                                <div class="font-semibold text-emerald-700">
                                    {{ money(row.gross_profit_total || 0) }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ marginRate(row) }}
                                </div>
                            </td>
                            <td>
                                <span
                                    class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusClass(row)"
                                >
                                    {{ statusLabel(row) }}
                                </span>
                            </td>
                            <td>
                                <Link
                                    :href="
                                        route('combined-invoices.show', row.id)
                                    "
                                >
                                    <AppButton variant="secondary">
                                        Lihat Faktur
                                    </AppButton>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!documents.data.length">
                            <td
                                :colspan="canViewProfit ? 11 : 10"
                                class="py-12 text-center text-slate-500"
                            >
                                {{
                                    activeStatus
                                        ? "Tidak ada Faktur dengan status yang dipilih."
                                        : "Belum ada Faktur. Klik Faktur Baru untuk memilih pelanggan dan invoice."
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t p-4">
                <span class="text-xs text-slate-500">
                    {{ documents.from ?? 0 }}–{{ documents.to ?? 0 }} dari
                    {{ documents.total }}
                </span>
                <Pagination :links="documents.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
