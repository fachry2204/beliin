<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ReportIcon from "@/Components/Reports/ReportIcon.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";
interface CompleteSummary {
    shipping_total: string;
    commission_total: string;
    manual_cash_out_total: string;
    paid_facture_total: string;
    unpaid_facture_total: string;
    paid_margin_total: string;
    net_margin_total: string;
}
defineProps<{ canViewProfit: boolean; completeSummary: CompleteSummary | null }>();
interface ReportCard {
    title: string;
    description: string;
    route: string;
    type: "invoice" | "combined" | "cash" | "margin";
    tone: "sky" | "emerald" | "amber" | "violet";
    profitOnly?: boolean;
}
const cards: ReportCard[] = [
    { title: "Laporan Invoice", description: "Pantau seluruh invoice, pembayaran, sisa tagihan, dan statusnya.", route: "reports.invoices", type: "invoice", tone: "sky" },
    { title: "Laporan Faktur", description: "Lihat faktur gabungan yang masih harus ditagihkan per pelanggan.", route: "reports.combined-invoices", type: "combined", tone: "emerald" },
    { title: "Laporan Kas", description: "Analisis arus Kas Masuk dan Kas Keluar dalam satu laporan.", route: "reports.cash", type: "cash", tone: "amber" },
    { title: "Laporan Margin", description: "Ukur margin terealisasi hanya dari Faktur yang sudah dibayar lunas.", route: "reports.margins", type: "margin", tone: "violet", profitOnly: true },
] as ReportCard[];
const toneClasses = {
    sky: { icon: "bg-sky-50 text-sky-600", title: "text-sky-600", button: "border-sky-300 text-sky-600 hover:bg-sky-50" },
    emerald: { icon: "bg-emerald-50 text-emerald-600", title: "text-emerald-600", button: "border-emerald-300 text-emerald-600 hover:bg-emerald-50" },
    amber: { icon: "bg-amber-50 text-amber-600", title: "text-amber-600", button: "border-amber-300 text-amber-600 hover:bg-amber-50" },
    violet: { icon: "bg-violet-50 text-violet-600", title: "text-violet-600", button: "border-violet-300 text-violet-600 hover:bg-violet-50" },
};
const money = (value: string | number) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value));
</script>

<template>
    <Head title="Laporan" />
    <AuthenticatedLayout>
        <template #breadcrumb>Laporan</template>
        <div class="mb-7">
            <h1 class="page-title">Laporan</h1>
            <p class="page-subtitle">Pusat laporan untuk memantau transaksi dan kinerja keuangan perusahaan.</p>
        </div>
        <section v-if="canViewProfit && completeSummary" class="panel mb-7 overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-800">Laporan Lengkap</h2>
                <p class="mt-1 text-sm text-slate-500">Ringkasan keseluruhan faktur, biaya, komisi, dan margin bersih.</p>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <ReportStatCard label="Total Ongkir" :value="money(completeSummary.shipping_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Komisi" :value="money(completeSummary.commission_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Kas Keluar Manual" :value="money(completeSummary.manual_cash_out_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Faktur Sudah Dibayar" :value="money(completeSummary.paid_facture_total)" tone="emerald" icon="combined" />
                <ReportStatCard label="Total Faktur Belum Dibayar" :value="money(completeSummary.unpaid_facture_total)" tone="sky" icon="combined" />
                <ReportStatCard label="Margin Faktur Terbayar" :value="money(completeSummary.paid_margin_total)" tone="violet" icon="margin" />
                <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-2">
                    <ReportStatCard label="Total Margin Bersih" :value="money(completeSummary.net_margin_total)" tone="emerald" icon="margin" />
                    <p class="mt-2 text-xs text-slate-500">Margin faktur terbayar dikurangi ongkir, kas keluar manual, dan komisi.</p>
                </div>
            </div>
        </section>
        <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-4">
            <article
                v-for="card in cards.filter((item) => !item.profitOnly || canViewProfit)"
                :key="card.route"
                class="flex min-h-64 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl" :class="toneClasses[card.tone].icon">
                        <ReportIcon :type="card.type" class="h-8 w-8" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold" :class="toneClasses[card.tone].title">{{ card.title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ card.description }}</p>
                    </div>
                </div>
                <Link
                    :href="route(card.route)"
                    class="mt-auto flex items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2"
                    :class="toneClasses[card.tone].button"
                >Buka Laporan</Link>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
