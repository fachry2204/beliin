<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ReportFilters from "@/Components/Reports/ReportFilters.vue";
import ReportPageHeader from "@/Components/Reports/ReportPageHeader.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import { percentageText } from "@/utils/percentage";
interface Row {
    id: number;
    facture_number: string;
    opened_at: string;
    invoices_count: number;
    customer: { name: string; company_name?: string };
    subtotal_total: string | null;
    discount_total: string | null;
    cost_total: string | null;
    gross_margin_total: string | null;
    commission_total: string | null;
    shipping_total: string | null;
}
interface PageData {
    data: Row[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}
interface Summary {
    facture_count: number;
    invoice_count: number;
    sales_total: string;
    cost_total: string;
    gross_margin_total: string;
    commission_total: string;
    shipping_total: string;
    net_margin_total: string;
}
defineProps<{
    summary: Summary;
    rows: PageData;
    filters: Record<string, string>;
}>();
const money = (v: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v));
const rate = (profit: string | number, base: string | number) =>
    percentageText(
        Number(base) > 0 ? (Number(profit) / Number(base)) * 100 : 0,
    );
const date = (v: string) =>
    new Date(`${v.slice(0, 10)}T00:00:00`).toLocaleDateString("id-ID");
</script>
<template>
    <Head title="Laporan Margin" /><AuthenticatedLayout
        ><template #breadcrumb>Laporan / Margin</template
        ><ReportPageHeader
            title="Laporan Margin"
            description="Analisis penjualan, harga pokok, ongkir, komisi Faktur, dan margin bersih." /><ReportFilters
            route-name="reports.margins"
            :filters="filters"
            search-placeholder="Cari nomor faktur, invoice, atau pelanggan..." />
        <div
            class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <ReportStatCard
                label="Jumlah Faktur"
                :value="`${summary.facture_count} faktur`"
            /><ReportStatCard
                label="Penjualan Bersih"
                :value="money(summary.sales_total)"
                tone="sky"
                icon="cash"
            /><ReportStatCard
                label="Total Harga Pokok"
                :value="money(summary.cost_total)"
                tone="amber"
                icon="cash"
            /><ReportStatCard
                label="Total Margin Kotor"
                :value="money(summary.gross_margin_total)"
                :detail="rate(summary.gross_margin_total, summary.sales_total)"
                tone="violet"
                icon="margin"
            /><ReportStatCard
                label="Total Komisi"
                :value="money(summary.commission_total)"
                tone="amber"
                icon="cash"
            /><ReportStatCard
                label="Total Ongkir"
                :value="money(summary.shipping_total)"
                tone="amber"
                icon="cash"
            /><ReportStatCard
                label="Total Margin Bersih"
                :value="money(summary.net_margin_total)"
                :detail="rate(summary.net_margin_total, summary.sales_total)"
                tone="emerald"
                icon="margin"
            />
        </div>
        <section class="panel">
            <div class="table-wrap">
                <table class="data-table min-w-[1200px]">
                    <thead>
                        <tr>
                            <th>Tanggal Faktur</th>
                            <th>Nomor Faktur</th>
                            <th>Pelanggan</th>
                            <th class="text-right">Penjualan Bersih</th>
                            <th class="text-right">Harga Pokok</th>
                            <th class="text-right">Margin Kotor</th>
                            <th class="text-right">Komisi</th>
                            <th class="text-right">Ongkir</th>
                            <th class="text-right">Margin Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="row.id">
                            <td>{{ date(row.opened_at) }}</td>
                            <td>
                                <Link
                                    :href="route('combined-invoices.show', row.id)"
                                    class="font-semibold text-sky-600 hover:underline"
                                    >{{ row.facture_number }}</Link
                                >
                                <div class="text-xs text-slate-400">
                                    {{ row.invoices_count }} invoice
                                </div>
                            </td>
                            <td>
                                {{ row.customer.company_name || row.customer.name }}
                            </td>
                            <td class="text-right">
                                {{
                                    money(
                                        Number(row.subtotal_total) -
                                            Number(row.discount_total),
                                    )
                                }}
                            </td>
                            <td class="text-right text-amber-600">
                                {{ money(row.cost_total ?? 0) }}
                            </td>
                            <td class="text-right">
                                <div class="font-semibold text-violet-600">
                                    {{ money(row.gross_margin_total ?? 0) }}
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{
                                        rate(
                                            row.gross_margin_total ?? 0,
                                            Number(row.subtotal_total) -
                                                Number(row.discount_total),
                                        )
                                    }}
                                </div>
                            </td>
                            <td class="text-right font-semibold text-amber-600">
                                {{ money(row.commission_total ?? 0) }}
                            </td>
                            <td class="text-right font-semibold text-red-600">
                                {{ money(row.shipping_total ?? 0) }}
                            </td>
                            <td class="text-right">
                                <div class="font-semibold text-emerald-600">
                                    {{
                                        money(
                                            Number(row.gross_margin_total) -
                                                Number(row.commission_total) -
                                                Number(row.shipping_total),
                                        )
                                    }}
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{
                                        rate(
                                            Number(row.gross_margin_total) -
                                                Number(row.commission_total) -
                                                Number(row.shipping_total),
                                            Number(row.subtotal_total) -
                                                Number(row.discount_total),
                                        )
                                    }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                colspan="9"
                                class="py-12 text-center text-slate-500"
                            >
                                Tidak ada data margin sesuai filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t p-4">
                <span class="text-xs text-slate-500"
                    >{{ rows.from ?? 0 }}-{{ rows.to ?? 0 }} dari
                    {{ rows.total }}</span
                ><Pagination :links="rows.links" />
            </div></section
    ></AuthenticatedLayout>
</template>
