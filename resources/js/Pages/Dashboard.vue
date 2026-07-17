<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DashboardCard from "@/Components/UI/DashboardCard.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    grand_total: string;
    paid_amount: string;
    status: string | { value: string };
    customer: { name: string; company_name?: string };
}
interface Point {
    period: string;
    total: string | number;
}
const props = defineProps<{
    metrics: {
        sales: string | number;
        receivables: string | number;
        monthly: number;
        profit: string | number;
    };
    recent: Invoice[];
    chart: Point[];
}>();
const money = (v: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v));
const max = computed(() =>
    Math.max(...props.chart.map((p) => Number(p.total)), 1),
);
const points = computed(() =>
    props.chart
        .map(
            (p, i) =>
                `${(i / Math.max(props.chart.length - 1, 1)) * 100},${92 - (Number(p.total) / max.value) * 75}`,
        )
        .join(" "),
);
const statusValue = (status: Invoice["status"]) =>
    typeof status === "string" ? status : status.value;
</script>
<template>
    <Head title="Dashboard" /><AuthenticatedLayout
        ><template #breadcrumb>Dashboard</template>
        <div class="mb-6">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">
                Ringkasan kinerja penjualan dan arus kas perusahaan.
            </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <DashboardCard
                label="Total Penjualan"
                :value="money(metrics.sales)"
                trend="Data penjualan aktif"
            /><DashboardCard
                label="Total Piutang"
                :value="money(metrics.receivables)"
                trend="Tagihan berjalan"
            /><DashboardCard
                label="Invoice Bulan Ini"
                :value="String(metrics.monthly)"
                trend="Bulan berjalan"
            /><DashboardCard
                label="Estimasi Laba"
                :value="money(metrics.profit)"
                trend="Laba kotor bulan ini"
            />
        </div>
        <section class="panel mt-5 p-5">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-800">Tren Penjualan</h2>
                    <p class="text-xs text-slate-500">12 bulan terakhir</p>
                </div>
                <span
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600"
                    >12 Bulan Terakhir</span
                >
            </div>
            <div class="h-64 w-full">
                <svg
                    viewBox="0 0 100 100"
                    class="h-full w-full"
                    preserveAspectRatio="none"
                >
                    <g
                        class="text-slate-200"
                        stroke="currentColor"
                        stroke-width=".35"
                    >
                        <line
                            v-for="y in [17, 36, 55, 74, 92]"
                            :key="y"
                            x1="0"
                            x2="100"
                            :y1="y"
                            :y2="y"
                        />
                    </g>
                    <polyline
                        v-if="chart.length"
                        :points="points"
                        fill="none"
                        stroke="#0ea5e9"
                        stroke-width="1.2"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        v-for="(p, i) in chart"
                        :key="p.period"
                        :cx="(i / Math.max(chart.length - 1, 1)) * 100"
                        :cy="92 - (Number(p.total) / max) * 75"
                        r="1.1"
                        fill="#0284c7"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>
            </div>
            <div
                class="grid text-center text-[10px] text-slate-500"
                :style="{
                    gridTemplateColumns: `repeat(${chart.length},minmax(0,1fr))`,
                }"
            >
                <span v-for="p in chart" :key="p.period">{{ p.period }}</span>
            </div>
        </section>
        <section class="panel mt-5">
            <div
                class="flex items-center justify-between border-b border-slate-200 px-5 py-4"
            >
                <div>
                    <h2 class="font-bold text-slate-800">Invoice Terbaru</h2>
                    <p class="text-xs text-slate-500">
                        Transaksi yang baru dibuat
                    </p>
                </div>
                <Link
                    :href="route('invoices.index')"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                    >Lihat Semua</Link
                >
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Pelanggan</th>
                            <th>Tanggal</th>
                            <th>Grand Total</th>
                            <th>Terbayar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in recent" :key="row.id">
                            <td>
                                <Link
                                    :href="route('invoices.show', row.id)"
                                    class="font-semibold text-sky-600 hover:underline"
                                    >{{ row.invoice_number }}</Link
                                >
                            </td>
                            <td>
                                {{
                                    row.customer.company_name ||
                                    row.customer.name
                                }}
                            </td>
                            <td>
                                {{
                                    new Date(
                                        row.invoice_date,
                                    ).toLocaleDateString("id-ID")
                                }}
                            </td>
                            <td>{{ money(row.grand_total) }}</td>
                            <td>{{ money(row.paid_amount) }}</td>
                            <td>
                                <StatusBadge
                                    :status="statusValue(row.status)"
                                />
                            </td>
                        </tr>
                        <tr v-if="!recent.length">
                            <td
                                colspan="6"
                                class="py-10 text-center text-slate-500"
                            >
                                Belum ada invoice.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
