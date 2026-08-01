<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ReportFilters from "@/Components/Reports/ReportFilters.vue";
import ReportPageHeader from "@/Components/Reports/ReportPageHeader.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";
import Pagination from "@/Components/UI/Pagination.vue";
interface Row{id:number;transaction_number:string;transaction_date:string;description:string;payment_method:string;amount:string;reference_number?:string}
interface PageData{data:Row[];links:{url:string|null;label:string;active:boolean}[];from:number|null;to:number|null;total:number}
defineProps<{summary:{capital_total:string;invoice_cost_total:string;capital_returned:string;available_capital:string};rows:PageData;filters:Record<string,string>}>();
const money=(v:string|number)=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v));
const date=(v:string)=>new Date(`${v.slice(0,10)}T00:00:00`).toLocaleDateString('id-ID');
</script>
<template><Head title="Laporan Setoran & Penggunaan Modal"/><AuthenticatedLayout><template #breadcrumb>Laporan / Setoran & Penggunaan Modal</template><ReportPageHeader title="Laporan Setoran & Penggunaan Modal" description="Pantau dana pemilik yang masuk dan penggunaannya untuk modal invoice."/><ReportFilters route-name="reports.capital" :filters="filters" search-placeholder="Cari nomor, keterangan, atau referensi setoran..."/>
<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><ReportStatCard label="Total Setoran Modal" :value="money(summary.capital_total)" tone="emerald" icon="cash"/><ReportStatCard label="Modal Invoice Terpakai" :value="money(summary.invoice_cost_total)" tone="amber" icon="invoice"/><ReportStatCard label="Pengembalian Modal / Prive" :value="money(summary.capital_returned)" tone="amber" icon="cash"/><ReportStatCard label="Sisa Dana Modal Operasional" :value="money(summary.available_capital)" tone="violet" icon="margin"/></div>
<section class="panel"><div class="table-wrap"><table class="data-table min-w-[850px]"><thead><tr><th>Tanggal</th><th>Nomor</th><th>Keterangan</th><th>Referensi</th><th>Metode</th><th class="text-right">Nominal</th></tr></thead><tbody><tr v-for="row in rows.data" :key="row.id"><td>{{date(row.transaction_date)}}</td><td class="font-semibold text-sky-600">{{row.transaction_number}}</td><td>{{row.description}}</td><td>{{row.reference_number||'-'}}</td><td class="capitalize">{{row.payment_method}}</td><td class="text-right font-semibold text-emerald-600">{{money(row.amount)}}</td></tr><tr v-if="!rows.data.length"><td colspan="6" class="py-12 text-center text-slate-500">Belum ada Setoran Modal sesuai filter.</td></tr></tbody></table></div><div class="flex items-center justify-between border-t p-4"><span class="text-xs text-slate-500">{{rows.from??0}}-{{rows.to??0}} dari {{rows.total}}</span><Pagination :links="rows.links"/></div></section></AuthenticatedLayout></template>
