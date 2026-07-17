<script setup lang="ts">
defineProps<{
    subtotal: number;
    discount: number;
    taxBase: number;
    tax: number;
    grandTotal: number;
    discountEnabled: boolean;
    taxEnabled: boolean;
}>();
const money = (value: number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);
</script>
<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Subtotal</dt>
                <dd class="font-semibold">{{ money(subtotal) }}</dd>
            </div>
            <div v-if="discountEnabled" class="flex justify-between">
                <dt class="text-slate-500">Diskon</dt>
                <dd class="font-semibold text-red-600">
                    - {{ money(discount) }}
                </dd>
            </div>
            <div v-if="taxEnabled" class="flex justify-between">
                <dt class="text-slate-500">Dasar Pengenaan Pajak</dt>
                <dd>{{ money(taxBase) }}</dd>
            </div>
            <div v-if="taxEnabled" class="flex justify-between">
                <dt class="text-slate-500">Pajak</dt>
                <dd>{{ money(tax) }}</dd>
            </div>
            <div
                class="flex items-end justify-between border-t border-slate-300 pt-4"
            >
                <dt class="font-bold">GRAND TOTAL</dt>
                <dd class="text-2xl font-bold text-sky-600">
                    {{ money(grandTotal) }}
                </dd>
            </div>
        </dl>
    </div>
</template>
