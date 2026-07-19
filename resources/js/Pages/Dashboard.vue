<script setup lang="ts">
import { computed, ref } from "vue";
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
    dailyChart: Point[];
}>();
const money = (v: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v));
type ChartPeriod = "daily" | "6" | "12";
const selectedPeriod = ref<ChartPeriod>("12");
const periodOptions: { value: ChartPeriod; label: string }[] = [
    { value: "daily", label: "Per Hari" },
    { value: "6", label: "Per 6 Bulan" },
    { value: "12", label: "Per 12 Bulan" },
];
const visibleChart = computed(() =>
    selectedPeriod.value === "daily"
        ? props.dailyChart
        : props.chart.slice(-Number(selectedPeriod.value)),
);
const selectedPeriodLabel = computed(
    () =>
        periodOptions.find((option) => option.value === selectedPeriod.value)
            ?.label ?? "Per 12 Bulan",
);
const max = computed(() =>
    Math.max(...visibleChart.value.map((p) => Number(p.total)), 1),
);
const barSlotWidth = computed(
    () => 100 / Math.max(visibleChart.value.length, 1),
);
const barWidth = computed(() => Math.min(barSlotWidth.value * 0.56, 10));
const chartMinWidth = computed(() =>
    selectedPeriod.value === "daily"
        ? Math.max(720, visibleChart.value.length * 52)
        : 0,
);
const shortMoney = (value: string | number) => {
    const amount = Number(value);
    if (amount >= 1_000_000_000)
        return `Rp${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 1 }).format(amount / 1_000_000_000)} M`;
    if (amount >= 1_000_000)
        return `Rp${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 1 }).format(amount / 1_000_000)} jt`;
    if (amount >= 1_000)
        return `Rp${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(amount / 1_000)} rb`;
    return money(amount);
};
const chartMoney = (value: string | number) =>
    selectedPeriod.value === "daily" ? shortMoney(value) : money(value);
const labelTop = (value: string | number) =>
    `${Math.max(3, 92 - (Number(value) / max.value) * 75 - 2)}%`;
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
                    <p class="text-xs text-slate-500">
                        {{ selectedPeriodLabel }}
                    </p>
                </div>
                <label class="sr-only" for="sales-chart-period"
                    >Periode tren penjualan</label
                >
                <select
                    id="sales-chart-period"
                    v-model="selectedPeriod"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 focus:border-sky-500 focus:ring-sky-500"
                >
                    <option
                        v-for="option in periodOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div class="overflow-x-auto pb-2">
                <div
                    class="relative h-64 w-full"
                    :style="chartMinWidth ? { minWidth: `${chartMinWidth}px` } : undefined"
                >
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
                    <rect
                        v-for="(p, i) in visibleChart"
                        :key="p.period"
                        :x="i * barSlotWidth + (barSlotWidth - barWidth) / 2"
                        :y="92 - (Number(p.total) / max) * 75"
                        :width="barWidth"
                        :height="(Number(p.total) / max) * 75"
                        rx="1"
                        fill="#0284c7"
                        class="transition-opacity hover:opacity-80"
                    >
                        <title>{{ p.period }}: {{ money(p.total) }}</title>
                    </rect>
                </svg>
                <div
                    v-for="(p, i) in visibleChart"
                    v-show="Number(p.total) > 0"
                    :key="`value-${p.period}`"
                    class="pointer-events-none absolute text-center text-[9px] font-bold text-slate-700 sm:text-[10px]"
                    :style="{
                        left: `${i * barSlotWidth}%`,
                        top: labelTop(p.total),
                        width: `${barSlotWidth}%`,
                        transform: 'translateY(-100%)',
                    }"
                >
                    {{ chartMoney(p.total) }}
                </div>
                <div
                    class="absolute inset-x-0 bottom-0 grid text-center text-[9px] text-slate-500 sm:text-[10px]"
                    :style="{
                        gridTemplateColumns: `repeat(${visibleChart.length},minmax(0,1fr))`,
                    }"
                >
                    <span v-for="p in visibleChart" :key="p.period">{{ p.period }}</span>
                </div>
                </div>
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
