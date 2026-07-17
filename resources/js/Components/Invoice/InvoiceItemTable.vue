<script setup lang="ts">
import { ref } from "vue";
import AppInput from "@/Components/UI/AppInput.vue";
import CurrencyInput from "@/Components/UI/CurrencyInput.vue";
export interface ProductOption {
    id: number;
    name: string;
    sku: string;
    unit: string;
    selling_price: string;
    purchase_price: string;
}
export interface InvoiceItem {
    product_id: string;
    product_name: string;
    sku: string;
    unit: string;
    purchase_price: string;
    selling_price: string;
    quantity: string;
}
const props = defineProps<{
    items: InvoiceItem[];
    products: ProductOption[];
    canViewCost: boolean;
}>();
const emit = defineEmits<{
    (e: "add"): void;
    (e: "remove", index: number): void;
}>();
const product = (id: string) => props.products.find((p) => p.id === Number(id));
const openIndex = ref<number | null>(null);
const activeResultIndex = ref(0);
const touchedSellingPrices = ref(new Set<number>());
const normalize = (value: string) => value.trim().toLocaleLowerCase("id-ID");
const filteredProducts = (item: InvoiceItem) => {
    const query = normalize(item.product_name);
    if (!query) return [];

    const words = query.split(/\s+/);
    return props.products
        .filter((p) => {
            const searchable = normalize(`${p.name} ${p.sku}`);
            return words.every((word) => searchable.includes(word));
        })
        .sort((a, b) => {
            const aName = normalize(a.name);
            const bName = normalize(b.name);
            const aScore = aName.startsWith(query) ? 0 : aName.includes(query) ? 1 : 2;
            const bScore = bName.startsWith(query) ? 0 : bName.includes(query) ? 1 : 2;
            return aScore - bScore || a.name.localeCompare(b.name, "id-ID");
        })
        .slice(0, 6);
};
const openSearch = (index: number) => {
    openIndex.value = index;
    activeResultIndex.value = 0;
};
const closeSearch = (index: number) => {
    window.setTimeout(() => {
        if (openIndex.value === index) openIndex.value = null;
    }, 100);
};
const updateProductName = (item: InvoiceItem, index: number, value: string) => {
    const selected = product(item.product_id);
    item.product_name = value;
    if (selected && normalize(selected.name) !== normalize(value)) {
        item.product_id = "";
        item.sku = "";
        item.unit = "Pcs";
        item.purchase_price = "0";
        item.selling_price = "0";
    }
    openSearch(index);
};
const selectProduct = (item: InvoiceItem, selected: ProductOption) => {
    item.product_id = String(selected.id);
    item.product_name = selected.name;
    item.sku = selected.sku;
    item.unit = selected.unit;
    item.purchase_price = selected.purchase_price;
    item.selling_price = selected.selling_price;
    openIndex.value = null;
};
const handleNameKeydown = (
    event: KeyboardEvent,
    item: InvoiceItem,
    index: number,
) => {
    const results = filteredProducts(item);
    if (event.key === "ArrowDown" && results.length) {
        event.preventDefault();
        openIndex.value = index;
        activeResultIndex.value = Math.min(
            activeResultIndex.value + 1,
            results.length - 1,
        );
    } else if (event.key === "ArrowUp" && results.length) {
        event.preventDefault();
        activeResultIndex.value = Math.max(activeResultIndex.value - 1, 0);
    } else if (event.key === "Enter" && openIndex.value === index && results.length) {
        event.preventDefault();
        selectProduct(item, results[activeResultIndex.value] ?? results[0]);
    } else if (event.key === "Escape") {
        openIndex.value = null;
    }
};
const total = (item: InvoiceItem) =>
    Number(item.selling_price || 0) * Number(item.quantity || 0);
const sellingPriceIsInvalid = (item: InvoiceItem) =>
    Number(item.selling_price || 0) <= Number(item.purchase_price || 0);
const touchSellingPrice = (index: number) => {
    touchedSellingPrices.value = new Set(touchedSellingPrices.value).add(index);
};
const money = (v: number | string) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v));
</script>
<template>
    <div class="table-wrap">
        <table class="data-table min-w-[900px]">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th v-if="canViewCost">Harga Beli</th>
                    <th>Harga Jual</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                    <th class="text-right">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in items" :key="index">
                    <td class="min-w-80 align-top">
                        <div class="relative">
                            <input
                                :value="item.product_name"
                                type="text"
                                required
                                autocomplete="off"
                                role="combobox"
                                aria-autocomplete="list"
                                :aria-expanded="openIndex === index"
                                :aria-controls="`product-results-${index}`"
                                placeholder="Ketik nama barang..."
                                class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500"
                                @focus="openSearch(index)"
                                @blur="closeSearch(index)"
                                @input="updateProductName(item, index, ($event.target as HTMLInputElement).value)"
                                @keydown="handleNameKeydown($event, item, index)"
                            />
                            <div
                                v-if="openIndex === index && item.product_name.trim()"
                                :id="`product-results-${index}`"
                                role="listbox"
                                class="mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                            >
                                <button
                                    v-for="(p, resultIndex) in filteredProducts(item)"
                                    :key="p.id"
                                    type="button"
                                    role="option"
                                    :aria-selected="activeResultIndex === resultIndex"
                                    class="flex w-full items-center justify-between gap-4 px-3 py-2 text-left text-sm hover:bg-sky-50"
                                    :class="activeResultIndex === resultIndex ? 'bg-sky-50 text-sky-800' : 'text-slate-700'"
                                    @mouseenter="activeResultIndex = resultIndex"
                                    @mousedown.prevent="selectProduct(item, p)"
                                >
                                    <span class="font-medium">{{ p.name }}</span>
                                    <span class="text-xs text-slate-400">{{ p.unit }}</span>
                                </button>
                                <div
                                    v-if="!filteredProducts(item).length"
                                    class="px-3 py-2 text-xs text-slate-500"
                                >
                                    Barang tidak ditemukan. Nama ini akan disimpan sebagai barang manual.
                                </div>
                            </div>
                            <div
                                v-if="item.product_id && openIndex !== index"
                                class="mt-1 text-xs font-medium text-emerald-600"
                            >
                                Barang tersimpan dipilih
                            </div>
                        </div>
                    </td>
                    <td v-if="canViewCost">
                        <CurrencyInput
                            v-model="item.purchase_price"
                            required
                        />
                    </td>
                    <td class="align-top">
                        <CurrencyInput
                            v-model="item.selling_price"
                            required
                            @update:model-value="touchSellingPrice(index)"
                        />
                        <p
                            v-if="touchedSellingPrices.has(index) && sellingPriceIsInvalid(item)"
                            class="mt-1 text-xs font-medium text-red-600"
                        >
                            Harga jual harus lebih besar dari harga beli.
                        </p>
                    </td>
                    <td>
                        <AppInput
                            v-model="item.quantity"
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            required
                        />
                    </td>
                    <td>
                        <span v-if="item.product_id">{{ item.unit }}</span>
                        <AppInput
                            v-else
                            v-model="item.unit"
                            placeholder="Pcs"
                            required
                        />
                    </td>
                    <td class="text-right font-semibold">
                        {{ money(total(item)) }}
                    </td>
                    <td>
                        <button
                            type="button"
                            class="rounded border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50"
                            @click="emit('remove', index)"
                        >
                            ⌫
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <button
        type="button"
        class="m-4 rounded-lg border border-sky-300 px-3 py-2 text-sm font-semibold text-sky-600 hover:bg-sky-50"
        @click="emit('add')"
    >
        ＋ Tambah Barang
    </button>
</template>
