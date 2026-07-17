<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import { percentageText } from "@/utils/percentage";

interface Customer { id:number; customer_code:string; name:string; company_name?:string }
interface DocumentRow {
    id:number; facture_number:string; opened_at:string; due_date?:string|null; status:string;
    invoices_count:number; grand_total:string; paid_total:string; remaining_total:string;
    gross_profit_total?:string; subtotal_total?:string; discount_total?:string; customer:Customer;
}
interface PageData { data:DocumentRow[]; links:{url:string|null;label:string;active:boolean}[]; from:number;to:number;total:number }
defineProps<{documents:PageData;canViewProfit:boolean;canCreate:boolean}>();
const params=new URLSearchParams(location.search);
const search=ref(params.get("search")??"");
const filter=()=>router.get(route("combined-invoices.index"),{search:search.value},{preserveState:true});
const money=(value:string|number)=>new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(Number(value));
const date=(value?:string|null)=>value?new Date(value).toLocaleDateString("id-ID"):"Tanpa jatuh tempo";
const marginRate=(row:DocumentRow)=>{const base=Number(row.subtotal_total||0)-Number(row.discount_total||0);return percentageText(base>0?(Number(row.gross_profit_total||0)/base)*100:0)};
</script>

<template>
<Head title="Faktur"/><AuthenticatedLayout><template #breadcrumb>Transaksi / Faktur</template>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h1 class="page-title">Faktur</h1><p class="page-subtitle">Faktur hanya dibuat dari invoice yang dipilih secara manual.</p></div><Link v-if="canCreate" :href="route('combined-invoices.create')"><AppButton>+ Faktur Baru</AppButton></Link></div>
<section class="panel"><form class="flex gap-3 border-b p-4" @submit.prevent="filter"><SearchInput v-model="search" placeholder="Cari nomor faktur atau pelanggan..." class="flex-1"/><AppButton type="submit">Cari</AppButton></form>
<div class="table-wrap"><table class="data-table min-w-[1050px]"><thead><tr><th>Nomor Faktur</th><th>Pelanggan</th><th>Dibuat</th><th>Jatuh Tempo</th><th>Invoice</th><th>Total Tagihan</th><th>Terbayar</th><th>Sisa</th><th v-if="canViewProfit">Margin</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<tr v-for="row in documents.data" :key="row.id"><td class="font-semibold text-sky-700">{{row.facture_number}}</td><td><strong>{{row.customer.company_name||row.customer.name}}</strong><div class="text-xs text-slate-500">{{row.customer.name}} · {{row.customer.customer_code}}</div></td><td>{{date(row.opened_at)}}</td><td>{{date(row.due_date)}}</td><td>{{row.invoices_count}} invoice</td><td>{{money(row.grand_total||0)}}</td><td class="text-emerald-600">{{money(row.paid_total||0)}}</td><td class="font-bold text-red-600">{{money(row.remaining_total||0)}}</td><td v-if="canViewProfit"><div class="font-semibold text-emerald-600">{{money(row.gross_profit_total||0)}}</div><div class="text-xs text-slate-500">{{marginRate(row)}}</div></td><td><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="row.status==='open'?'bg-amber-100 text-amber-700':'bg-emerald-100 text-emerald-700'">{{row.status==='open'?'Aktif':'Lunas'}}</span></td><td><Link :href="route('combined-invoices.show',row.id)"><AppButton variant="secondary">Lihat Faktur</AppButton></Link></td></tr>
<tr v-if="!documents.data.length"><td :colspan="canViewProfit?11:10" class="py-12 text-center text-slate-500">Belum ada Faktur. Klik “Faktur Baru” untuk memilih pelanggan dan invoice.</td></tr>
</tbody></table></div><div class="flex items-center justify-between border-t p-4"><span class="text-xs text-slate-500">{{documents.from??0}}–{{documents.to??0}} dari {{documents.total}}</span><Pagination :links="documents.links"/></div></section>
</AuthenticatedLayout></template>
