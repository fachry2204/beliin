<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ReportFilters from "@/Components/Reports/ReportFilters.vue";
import ReportPageHeader from "@/Components/Reports/ReportPageHeader.vue";
import ReportStatCard from "@/Components/Reports/ReportStatCard.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
interface Row { id:number; invoice_number:string; invoice_date:string; billing_name:string; billing_company?:string; grand_total:string; paid_amount:string; remaining_amount:string; status:string|{value:string}; }
interface PageData { data:Row[]; links:{url:string|null;label:string;active:boolean}[]; from:number|null; to:number|null; total:number; }
interface Summary { invoice_count:number; grand_total:string; paid_total:string; remaining_total:string; }
const props = defineProps<{ summary:Summary; rows:PageData; filters:Record<string,string> }>();
const money=(v:string|number)=>new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(Number(v));
const date=(v:string)=>new Date(`${v.slice(0,10)}T00:00:00`).toLocaleDateString("id-ID");
const status=(v:Row["status"])=>typeof v==="string"?v:v.value;
const exportUrl=(format:string)=>route("reports.export",{format,date_from:props.filters.date_from||undefined,date_to:props.filters.date_to||undefined,status:props.filters.status||undefined,search:props.filters.search||undefined});
</script>
<template>
    <Head title="Laporan Invoice"/><AuthenticatedLayout><template #breadcrumb>Laporan / Invoice</template>
        <ReportPageHeader title="Laporan Invoice" description="Ringkasan dan rincian seluruh invoice yang telah diterbitkan.">
            <template #actions><a :href="exportUrl('xlsx')"><AppButton variant="secondary">Excel</AppButton></a><a :href="exportUrl('pdf')"><AppButton variant="secondary">PDF</AppButton></a></template>
        </ReportPageHeader>
        <ReportFilters route-name="reports.invoices" :filters="filters" search-placeholder="Cari nomor invoice, PO, atau pelanggan..." show-status />
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><ReportStatCard label="Jumlah Invoice" :value="`${summary.invoice_count} invoice`" detail="Sesuai filter"/><ReportStatCard label="Total Tagihan" :value="money(summary.grand_total)" tone="emerald" icon="combined"/><ReportStatCard label="Total Terbayar" :value="money(summary.paid_total)" tone="sky" icon="cash"/><ReportStatCard label="Total Sisa" :value="money(summary.remaining_total)" tone="amber" icon="cash"/></div>
        <section class="panel"><div class="table-wrap"><table class="data-table min-w-[900px]"><thead><tr><th>Tanggal</th><th>Nomor Invoice</th><th>Pelanggan</th><th class="text-right">Grand Total</th><th class="text-right">Terbayar</th><th class="text-right">Sisa</th><th>Status</th></tr></thead><tbody><tr v-for="row in rows.data" :key="row.id"><td>{{date(row.invoice_date)}}</td><td><Link :href="route('invoices.show',row.id)" class="font-semibold text-sky-600 hover:underline">{{row.invoice_number}}</Link></td><td>{{row.billing_company||row.billing_name}}</td><td class="text-right">{{money(row.grand_total)}}</td><td class="text-right text-emerald-600">{{money(row.paid_amount)}}</td><td class="text-right font-semibold text-red-600">{{money(row.remaining_amount)}}</td><td><StatusBadge :status="status(row.status)"/></td></tr><tr v-if="!rows.data.length"><td colspan="7" class="py-12 text-center text-slate-500">Tidak ada invoice sesuai filter.</td></tr></tbody></table></div><div class="flex items-center justify-between border-t p-4"><span class="text-xs text-slate-500">{{rows.from??0}}-{{rows.to??0}} dari {{rows.total}}</span><Pagination :links="rows.links"/></div></section>
    </AuthenticatedLayout>
</template>
