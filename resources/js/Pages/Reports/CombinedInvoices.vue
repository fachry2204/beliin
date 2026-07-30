<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import ReportPageHeader from "@/Components/Reports/ReportPageHeader.vue";
import ReportFilters from "@/Components/Reports/ReportFilters.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";

interface Customer {
    id: number;
    customer_code: string;
    name: string;
    company_name?: string;
}

interface Row {
    id: number;
    facture_number: string;
    opened_at: string;
    due_date?: string | null;
    status: "open" | "closed";
    invoices_count: number;
    grand_total: string;
    paid_total: string;
    remaining_total: string;
    customer: Customer;
}

interface PageData {
    data: Row[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

defineProps<{
    summary: {
        facture_count: number;
        customer_count: number;
        invoice_count: number;
        grand_total: string;
        remaining_total: string;
    };
    rows: PageData;
    filters: Record<string, string>;
}>();

type PaymentStatus = "paid" | "partially_paid" | "unpaid";

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

const paymentStatus = (row: Row): PaymentStatus => {
    if (Number(row.remaining_total) <= 0 || row.status === "closed") {
        return "paid";
    }

    return Number(row.paid_total) > 0 ? "partially_paid" : "unpaid";
};

const rowClass = (row: Row) =>
    ({
        paid: "bg-emerald-50/80 hover:bg-emerald-100/80",
        partially_paid: "bg-amber-50/80 hover:bg-amber-100/80",
        unpaid: "bg-red-50/80 hover:bg-red-100/80",
    })[paymentStatus(row)];

const statusLabel = (row: Row) =>
    ({
        paid: "Lunas",
        partially_paid: "Terbayar Sebagian",
        unpaid: "Belum Bayar",
    })[paymentStatus(row)];

const statusClass = (row: Row) =>
    ({
        paid: "bg-emerald-100 text-emerald-700",
        partially_paid: "bg-amber-100 text-amber-700",
        unpaid: "bg-red-100 text-red-700",
    })[paymentStatus(row)];
</script>

<template>
    <Head title="Laporan Faktur" />
    <AuthenticatedLayout>
        <template #breadcrumb>Laporan / Faktur</template>
        <ReportPageHeader
            title="Laporan Faktur"
            description="Daftar Faktur yang dibuat manual dari invoice pilihan."
        />
        <ReportFilters
            route-name="reports.combined-invoices"
            :filters="filters"
            search-placeholder="Cari nomor faktur, pelanggan, atau invoice..."
            show-facture-status
        />

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportStatCard
                label="Jumlah Faktur"
                :value="`${summary.facture_count} faktur`"
                tone="emerald"
                icon="combined"
            />
            <ReportStatCard
                label="Jumlah Invoice"
                :value="`${summary.invoice_count} invoice`"
            />
            <ReportStatCard
                label="Total Tagihan"
                :value="money(summary.grand_total)"
                tone="sky"
                icon="cash"
            />
            <ReportStatCard
                label="Total Sisa"
                :value="money(summary.remaining_total)"
                tone="amber"
                icon="cash"
            />
        </div>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>Nomor Faktur</th>
                            <th>Pelanggan</th>
                            <th>Dibuat</th>
                            <th>Jatuh Tempo</th>
                            <th>Invoice</th>
                            <th class="text-right">Total Tagihan</th>
                            <th class="text-right">Terbayar</th>
                            <th class="text-right">Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
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
                            <td class="text-right">
                                {{ money(row.grand_total || 0) }}
                            </td>
                            <td class="text-right font-semibold text-emerald-700">
                                {{ money(row.paid_total || 0) }}
                            </td>
                            <td class="text-right font-semibold text-red-700">
                                {{ money(row.remaining_total || 0) }}
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
                                    class="font-semibold text-sky-600 hover:underline"
                                >
                                    Lihat Faktur
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                colspan="10"
                                class="py-12 text-center text-slate-500"
                            >
                                Tidak ada Faktur sesuai filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t p-4">
                <span class="text-xs text-slate-500">
                    {{ rows.from ?? 0 }}–{{ rows.to ?? 0 }} dari
                    {{ rows.total }}
                </span>
                <Pagination :links="rows.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
