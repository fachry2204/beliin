<script setup lang="ts">
import { computed, ref } from "vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import CurrencyInput from "@/Components/UI/CurrencyInput.vue";
export interface ProductOption {
    id: number;
    name: string;
    sku: string;
    unit: string;
    selling_price: string;
    purchase_price: string;
}
export interface CustomerPriceOption {
    product_id: number | null;
    name: string;
    sku: string | null;
    unit: string;
    selling_price: string;
    purchase_price: string | null;
    invoice_number: string | null;
    invoice_date: string | null;
}
interface SearchProductOption extends Omit<ProductOption, "id"> {
    id: number | null;
    source: "customer_history" | "master";
    invoice_number?: string | null;
}
export interface InvoiceItem {
    product_id: string;
    product_name: string;
    sku: string;
    unit: string;
    purchase_price: string;
    selling_price: string;
    purchase_total: string;
    selling_total: string;
    quantity: string;
}
const props = defineProps<{
    items: InvoiceItem[];
    products: ProductOption[];
    customerPrices: CustomerPriceOption[];
    canViewCost: boolean;
}>();
const emit = defineEmits<{
    (e: "add"): void;
    (e: "remove", index: number): void;
    (e: "focus-item", index: number): void;
}>();
const product = (id: string) => props.products.find((p) => p.id === Number(id));
const openIndex = ref<number | null>(null);
const activeResultIndex = ref(0);
const touchedSellingPrices = ref(new Set<number>());
const defaultUnits = [
    "Pcs",
    "Kg",
    "Gram",
    "Ikat",
    "Bungkus",
    "Pack",
    "Kotak",
    "Dus",
    "Karung",
    "Sak",
    "Liter",
    "Botol",
    "Kaleng",
    "Drg",
    "M3",
];
const unitOptions = (item: InvoiceItem) =>
    Array.from(
        new Set(
            [item.unit, ...props.products.map((product) => product.unit), ...defaultUnits]
                .map((unit) => unit?.trim())
                .filter(Boolean),
        ),
    );
const normalize = (value: string) => value.trim().toLocaleLowerCase("id-ID");
const searchableProducts = computed<SearchProductOption[]>(() => {
    const historicalProductIds = new Set(
        props.customerPrices
            .map((price) => price.product_id)
            .filter((id): id is number => id !== null),
    );
    const history = props.customerPrices.map((price) => ({
        id: price.product_id,
        name: price.name,
        sku: price.sku ?? "MANUAL",
        unit: price.unit,
        selling_price: price.selling_price,
        purchase_price: price.purchase_price ?? "0",
        source: "customer_history" as const,
        invoice_number: price.invoice_number,
    }));
    const master = props.products
        .filter((product) => !historicalProductIds.has(product.id))
        .map((product) => ({ ...product, source: "master" as const }));

    return [...history, ...master];
});
const filteredProducts = (item: InvoiceItem) => {
    const query = normalize(item.product_name);
    if (!query) return [];

    const words = query.split(/\s+/);
    return searchableProducts.value
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
        item.purchase_total = "0";
        item.selling_total = "0";
    }
    openSearch(index);
};
const selectProduct = (item: InvoiceItem, selected: SearchProductOption) => {
    item.product_id = selected.id === null ? "" : String(selected.id);
    item.product_name = selected.name;
    item.sku = selected.sku;
    item.unit = selected.unit;
    item.purchase_price = selected.purchase_price;
    item.selling_price = selected.selling_price;
    const quantity = Number(item.quantity || 0);
    item.purchase_total = String(Math.round(Number(selected.purchase_price || 0) * quantity));
    item.selling_total = String(Math.round(Number(selected.selling_price || 0) * quantity));
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
const roundedPrice = (value: number) =>
    Number.isFinite(value) ? String(Math.round(value * 100) / 100) : "0";
const syncUnitPrices = (item: InvoiceItem) => {
    const quantity = Number(item.quantity || 0);
    if (!Number.isFinite(quantity) || quantity <= 0) return;
    item.purchase_price = roundedPrice(Number(item.purchase_total || 0) / quantity);
    item.selling_price = roundedPrice(Number(item.selling_total || 0) / quantity);
};
const basePurchasePrice = (item: InvoiceItem) =>
    Number(item.quantity || 0) > 0
        ? Number(item.purchase_total || 0) / Number(item.quantity)
        : 0;
const baseSellingPrice = (item: InvoiceItem) =>
    Number(item.quantity || 0) > 0
        ? Number(item.selling_total || 0) / Number(item.quantity)
        : 0;
const itemMargin = (item: InvoiceItem) =>
    Number(item.selling_total || 0) - Number(item.purchase_total || 0);
const sellingPriceIsInvalid = (item: InvoiceItem) =>
    Number(item.selling_total || 0) < Number(item.purchase_total || 0);
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
    <div class="mx-4 mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800">
        <p>
            <strong>Cara input:</strong> isi Total Modal dan Total Jual untuk seluruh QTY.
            Harga Dasar otomatis dihitung dari Total ÷ QTY, sedangkan Margin Item = Total Jual − Total Modal.
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-sky-200 pt-2">
            <strong class="text-sky-900">Panduan QTY berat:</strong>
            <span><strong>1</strong> = 1 kg</span>
            <span><strong>0,5 / 0.5</strong> = setengah kg</span>
            <span><strong>0,25 / 0.25</strong> = seperempat kg</span>
            <span><strong>0,75 / 0.75</strong> = tiga perempat kg</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[1320px]">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th v-if="canViewCost">Total Modal</th>
                    <th>Total Jual</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                    <th v-if="canViewCost">Harga Dasar Modal</th>
                    <th>Harga Dasar Jual</th>
                    <th v-if="canViewCost">Margin Item</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in items"
                    :key="index"
                    @focusin="emit('focus-item', index)"
                >
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
                                    :key="`${p.source}-${p.id ?? p.name}-${p.unit}`"
                                    type="button"
                                    role="option"
                                    :aria-selected="activeResultIndex === resultIndex"
                                    class="flex w-full items-center justify-between gap-4 px-3 py-2 text-left text-sm hover:bg-sky-50"
                                    :class="activeResultIndex === resultIndex ? 'bg-sky-50 text-sky-800' : 'text-slate-700'"
                                    @mouseenter="activeResultIndex = resultIndex"
                                    @mousedown.prevent="selectProduct(item, p)"
                                >
                                    <span class="font-medium">{{ p.name }}</span>
                                    <span class="text-right text-xs">
                                        <span
                                            v-if="p.source === 'customer_history'"
                                            class="block font-semibold text-emerald-600"
                                        >
                                            Riwayat pelanggan
                                        </span>
                                        <span class="text-slate-400">
                                            {{ p.unit }} · {{ money(p.selling_price) }}
                                        </span>
                                    </span>
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
                            v-model="item.purchase_total"
                            :data-testid="`purchase-total-${index}`"
                            required
                            @update:model-value="syncUnitPrices(item)"
                        />
                    </td>
                    <td class="align-top">
                        <CurrencyInput
                            v-model="item.selling_total"
                            :data-testid="`selling-total-${index}`"
                            required
                            @update:model-value="syncUnitPrices(item); touchSellingPrice(index)"
                        />
                        <p
                            v-if="touchedSellingPrices.has(index) && sellingPriceIsInvalid(item)"
                            class="mt-1 text-xs font-medium text-amber-600"
                        >
                            Total jual di bawah total modal. Konfirmasi diperlukan saat menyimpan.
                        </p>
                    </td>
                    <td>
                        <AppInput
                            v-model="item.quantity"
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            required
                            @update:model-value="syncUnitPrices(item)"
                        />
                    </td>
                    <td>
                        <AppSelect
                            v-model="item.unit"
                            :data-testid="`invoice-unit-${index}`"
                            required
                        >
                            <option
                                v-for="unit in unitOptions(item)"
                                :key="unit"
                                :value="unit"
                            >
                                {{ unit }}
                            </option>
                        </AppSelect>
                    </td>
                    <td v-if="canViewCost" class="font-medium text-amber-700">
                        {{ money(basePurchasePrice(item)) }}
                    </td>
                    <td class="font-medium text-sky-700">
                        {{ money(baseSellingPrice(item)) }}
                    </td>
                    <td
                        v-if="canViewCost"
                        class="font-semibold"
                        :class="itemMargin(item) < 0 ? 'text-red-700' : 'text-emerald-700'"
                    >
                        {{ money(itemMargin(item)) }}
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
