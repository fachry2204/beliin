<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";

interface Summary {
    shipping_total: string;
    commission_total: string;
    manual_cash_out_total: string;
    manual_expense_total: string;
    capital_total: string;
    invoice_cost_total: string;
    cash_in_total: string;
    cash_out_total: string;
    cash_balance: string;
    paid_facture_total: string;
    unpaid_facture_total: string;
    paid_margin_total: string;
    net_margin_total: string;
}

defineProps<{ summary: Summary }>();

const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
</script>

<template>
    <Head title="Ringkasan Keuangan" />
    <AuthenticatedLayout>
        <template #breadcrumb>Laporan / Ringkasan Keuangan</template>
        <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <h1 class="page-title">Ringkasan Keuangan</h1>
                <p class="page-subtitle">Posisi kas, modal, faktur, biaya, dan margin usaha secara menyeluruh.</p>
            </div>
            <Link :href="route('reports.index')"><AppButton variant="secondary">Kembali</AppButton></Link>
        </div>

        <section class="panel overflow-hidden">
            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <ReportStatCard label="Saldo Kas Utama" :value="money(summary.cash_balance)" tone="emerald" icon="cash" />
                <ReportStatCard label="Total Kas Masuk" :value="money(summary.cash_in_total)" tone="emerald" icon="cash" />
                <ReportStatCard label="Total Kas Keluar" :value="money(summary.cash_out_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Setoran Modal" :value="money(summary.capital_total)" tone="sky" icon="cash" />
                <ReportStatCard label="Modal Invoice Terpakai" :value="money(summary.invoice_cost_total)" tone="amber" icon="invoice" />
                <ReportStatCard label="Total Ongkir" :value="money(summary.shipping_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Komisi" :value="money(summary.commission_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Kas Keluar Manual" :value="money(summary.manual_cash_out_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Beban Manual Pengurang Margin" :value="money(summary.manual_expense_total)" tone="amber" icon="cash" />
                <ReportStatCard label="Total Faktur Sudah Dibayar" :value="money(summary.paid_facture_total)" tone="emerald" icon="combined" />
                <ReportStatCard label="Total Faktur Belum Dibayar" :value="money(summary.unpaid_facture_total)" tone="sky" icon="combined" />
                <ReportStatCard label="Margin Faktur Terbayar" :value="money(summary.paid_margin_total)" tone="violet" icon="margin" />
                <div class="sm:col-span-2 xl:col-span-3 2xl:col-span-2">
                    <ReportStatCard label="Total Margin Bersih" :value="money(summary.net_margin_total)" tone="emerald" icon="margin" />
                    <p class="mt-2 text-xs text-slate-500">Margin faktur terbayar dikurangi ongkir, kas keluar manual, dan komisi faktur lunas.</p>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
