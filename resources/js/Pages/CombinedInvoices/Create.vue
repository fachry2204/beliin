<script setup lang="ts">
import { computed, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";

interface Invoice { id:number; invoice_number:string; invoice_date:string; grand_total:string; paid_amount:string; remaining_amount:string }
interface Customer { id:number; customer_code:string; name:string; company_name?:string; phone?:string; invoices:Invoice[] }
interface DocumentData { id:number;facture_number:string;customer_id:number;invoice_ids:number[];due_date?:string|null }
const props=defineProps<{customers:Customer[];today:string;defaultDueDate:string;document?:DocumentData}>();
const editing=computed(()=>Boolean(props.document));
const form=useForm({customer_id:String(props.document?.customer_id??""),invoice_ids:props.document?.invoice_ids??[] as number[],use_due_date:props.document?Boolean(props.document.due_date):true,due_date:props.document?.due_date??props.defaultDueDate});
const customer=computed(()=>props.customers.find(item=>item.id===Number(form.customer_id)));
const invoices=computed(()=>customer.value?.invoices??[]);
const selected=computed(()=>invoices.value.filter(invoice=>form.invoice_ids.includes(invoice.id)));
const total=computed(()=>selected.value.reduce((sum,invoice)=>sum+Number(invoice.remaining_amount),0));
watch(()=>form.customer_id,()=>form.invoice_ids=[]);
watch(()=>form.use_due_date,value=>{if(value&&!form.due_date)form.due_date=props.defaultDueDate});
const toggleAll=()=>{form.invoice_ids=form.invoice_ids.length===invoices.value.length?[]:invoices.value.map(invoice=>invoice.id)};
const submit=()=>editing.value?form.put(route("combined-invoices.update",props.document!.id)):form.post(route("combined-invoices.store"));
const money=(value:string|number)=>new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(Number(value));
const date=(value:string)=>new Date(value).toLocaleDateString("id-ID");
</script>

<template><Head :title="editing?'Edit Faktur':'Faktur Baru'"/><AuthenticatedLayout><template #breadcrumb>Transaksi / Faktur / {{editing?'Edit':'Baru'}}</template>
<form @submit.prevent="submit"><div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h1 class="page-title">{{editing?'Edit Faktur':'Faktur Baru'}}</h1><p class="page-subtitle">{{editing?`Perbarui ${document?.facture_number}.`:'Pilih pelanggan dan invoice yang belum terbayar.'}}</p></div><div class="flex gap-2"><Link :href="editing?route('combined-invoices.show',document!.id):route('combined-invoices.index')"><AppButton variant="secondary">Batal</AppButton></Link><AppButton type="submit" :disabled="form.processing||!form.invoice_ids.length">{{editing?'Simpan Perubahan':'Buat Faktur'}}</AppButton></div></div>
<section class="panel p-5"><div class="grid gap-5 md:grid-cols-2"><label><span class="label">Pelanggan *</span><AppSelect v-model="form.customer_id" :disabled="editing" required><option value="">Pilih pelanggan</option><option v-for="item in customers" :key="item.id" :value="String(item.id)">{{item.company_name||item.name}} ({{item.invoices.length}} invoice)</option></AppSelect><small v-if="editing" class="mt-1 block text-slate-500">Pelanggan tidak dapat diubah setelah Faktur dibuat.</small></label><div class="rounded-xl border border-slate-200 p-4"><label class="flex cursor-pointer items-center gap-3"><input v-model="form.use_due_date" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-sky-600"/><span><strong class="block">Pakai Tanggal Jatuh Tempo</strong><small class="text-slate-500">Default tujuh hari sejak Faktur dibuat.</small></span></label><label v-if="form.use_due_date" class="mt-4 block"><span class="label">Tanggal Jatuh Tempo *</span><AppInput v-model="form.due_date" type="date" :min="editing?undefined:today" required/></label></div></div></section>
<section class="panel mt-5"><div class="flex items-center justify-between border-b p-5"><div><h2 class="font-bold">Pilih Invoice</h2><p class="text-sm text-slate-500">Hanya invoice belum lunas dan belum masuk Faktur lain.</p></div><button v-if="invoices.length" type="button" class="text-sm font-semibold text-sky-600" @click="toggleAll">{{form.invoice_ids.length===invoices.length?'Hapus Semua':'Pilih Semua'}}</button></div>
<div v-if="!form.customer_id" class="p-12 text-center text-slate-500">Pilih pelanggan terlebih dahulu.</div><div v-else-if="!invoices.length" class="p-12 text-center text-slate-500">Tidak ada invoice yang dapat dipilih.</div><div v-else class="table-wrap"><table class="data-table min-w-[760px]"><thead><tr><th class="w-12"></th><th>Nomor Invoice</th><th>Tanggal</th><th>Grand Total</th><th>Terbayar</th><th>Sisa</th></tr></thead><tbody><tr v-for="invoice in invoices" :key="invoice.id" class="cursor-pointer" @click="form.invoice_ids.includes(invoice.id)?form.invoice_ids=form.invoice_ids.filter(id=>id!==invoice.id):form.invoice_ids.push(invoice.id)"><td><input v-model="form.invoice_ids" type="checkbox" :value="invoice.id" class="h-5 w-5 rounded border-slate-300 text-sky-600" @click.stop/></td><td class="font-semibold text-sky-700">{{invoice.invoice_number}}</td><td>{{date(invoice.invoice_date)}}</td><td>{{money(invoice.grand_total)}}</td><td class="text-emerald-600">{{money(invoice.paid_amount)}}</td><td class="font-bold text-red-600">{{money(invoice.remaining_amount)}}</td></tr></tbody></table></div>
<div class="flex items-center justify-between border-t bg-slate-50 p-5"><span>{{selected.length}} invoice dipilih</span><strong class="text-xl text-sky-700">Total Sisa: {{money(total)}}</strong></div></section>
<p v-if="Object.keys(form.errors).length" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{Object.values(form.errors)[0]}}</p></form>
</AuthenticatedLayout></template>
