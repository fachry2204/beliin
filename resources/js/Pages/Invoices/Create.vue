<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import Modal from "@/Components/Modal.vue";
import InvoiceItemTable, {
    type CustomerPriceOption,
    type InvoiceItem,
    type ProductOption,
} from "@/Components/Invoice/InvoiceItemTable.vue";
import InvoiceSummary from "@/Components/Invoice/InvoiceSummary.vue";
import InvoiceCalculator from "@/Components/Invoice/InvoiceCalculator.vue";
interface Customer {
    id: number;
    name: string;
    company_name?: string;
    address?: string;
}
interface PaymentSettings {
    bank_name?: string;
    bank_account_number?: string;
    bank_account_name?: string;
}
interface DraftInvoice {
    id: number;
    customer_id: number;
    invoice_date: string;
    due_date: string;
    purchase_order_number?: string;
    discount_type: "percentage" | "nominal";
    discount_value: string;
    tax_percentage: string;
    notes?: string;
    terms?: string;
    items: (InvoiceItem & {
        product_id: string | number | null;
        product_name_snapshot?: string;
        sku_snapshot?: string;
        unit_snapshot?: string;
    })[];
}
const props = defineProps<{
    customers: Customer[];
    products: ProductOption[];
    defaultTax: string | number;
    taxEnabled: boolean;
    discountEnabled: boolean;
    paymentSettings: PaymentSettings | null;
    canViewCost: boolean;
    invoice?: DraftInvoice;
}>();
const today = new Date().toISOString().slice(0, 10);
const dueDateFor = (invoiceDate: string) => {
    const date = new Date(`${invoiceDate}T12:00:00`);
    date.setDate(date.getDate() + 7);
    return date.toISOString().slice(0, 10);
};
const due = dueDateFor(today);
const paymentTerms = (dueDate: string) => {
    const formattedDate = dueDate
        ? new Date(`${dueDate}T00:00:00`).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "long",
              year: "numeric",
          })
        : "-";
    const bank = props.paymentSettings?.bank_name || "-";
    const accountNumber = props.paymentSettings?.bank_account_number || "-";
    const accountName = props.paymentSettings?.bank_account_name || "-";

    return [
        `Pembayaran Jatuh Tempo Tanggal : ${formattedDate}`,
        "Pembayaran Melalui Transfer :",
        `Bank : ${bank} | No Rekening : ${accountNumber}`,
        `Atas Nama Rekening : ${accountName}`,
    ].join("\n");
};
const fresh = (): InvoiceItem => ({
    product_id: "",
    product_name: "",
    sku: "",
    unit: "Pcs",
    purchase_price: "0",
    selling_price: "0",
    quantity: "1",
});
const normalizeQuantity = (value: string | number) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? String(numeric) : String(value ?? "");
};
const initial = props.invoice;
const form = useForm<{
    customer_id: string;
    invoice_date: string;
    due_date: string;
    purchase_order_number: string;
    discount_type: "percentage" | "nominal";
    discount_value: string;
    tax_percentage: string;
    notes: string;
    terms: string;
    items: InvoiceItem[];
}>({
    customer_id: String(initial?.customer_id ?? ""),
    invoice_date: initial?.invoice_date.slice(0, 10) ?? today,
    due_date: initial?.due_date.slice(0, 10) ?? due,
    purchase_order_number: initial?.purchase_order_number ?? "",
    discount_type: props.discountEnabled
        ? (initial?.discount_type ?? "percentage")
        : "nominal",
    discount_value: props.discountEnabled
        ? initial?.discount_type === "percentage"
            ? String(Math.round(Number(initial.discount_value)))
            : String(initial?.discount_value ?? "0")
        : "0",
    tax_percentage: props.taxEnabled
        ? String(
              Math.round(Number(initial?.tax_percentage ?? props.defaultTax)),
          )
        : "0",
    notes: initial?.notes ?? "",
    terms:
        initial?.terms ?? paymentTerms(initial?.due_date.slice(0, 10) ?? due),
    items: initial?.items.map((item) => ({
        product_id: String(item.product_id ?? ""),
        product_name: item.product_name_snapshot ?? item.product_name ?? "",
        sku: item.sku_snapshot ?? item.sku ?? "",
        unit: item.unit_snapshot ?? item.unit ?? "Pcs",
        purchase_price: String(item.purchase_price ?? 0),
        selling_price: String(item.selling_price),
        quantity: normalizeQuantity(item.quantity),
    })) ?? [fresh()],
});
watch(
    () => form.invoice_date,
    (value) => {
        if (value) form.due_date = dueDateFor(value);
    },
);
watch(
    () => form.due_date,
    (value) => {
        form.terms = paymentTerms(value);
    },
);
const selectedCustomer = computed(() =>
    props.customers.find((c) => c.id === Number(form.customer_id)),
);
const line = (i: InvoiceItem) =>
    Number(i.selling_price || 0) * Number(i.quantity || 0);
const subtotal = computed(() => form.items.reduce((s, i) => s + line(i), 0));
const discount = computed(() =>
    props.discountEnabled
        ? Math.min(
              form.discount_type === "percentage"
                  ? (subtotal.value * Number(form.discount_value || 0)) / 100
                  : Number(form.discount_value || 0),
              subtotal.value,
          )
        : 0,
);
const taxBase = computed(() => subtotal.value - discount.value);
const tax = computed(() =>
    props.taxEnabled
        ? (taxBase.value * Number(form.tax_percentage || 0)) / 100
        : 0,
);
const grand = computed(() => taxBase.value + tax.value);
const calculatorOpen = ref(false);
const calculatorItemIndex = ref(0);
const customerPrices = ref<CustomerPriceOption[]>([]);
const customerPricesLoading = ref(false);
let customerPriceRequest = 0;
const lossWarningOpen = ref(false);
const lossItems = computed(() =>
    form.items
        .map((item) => ({
            name: item.product_name || "Barang tanpa nama",
            loss:
                Math.max(
                    Number(item.purchase_price || 0) -
                        Number(item.selling_price || 0),
                    0,
                ) * Number(item.quantity || 0),
        }))
        .filter((item) => item.loss > 0),
);
const estimatedLoss = computed(() =>
    lossItems.value.reduce((total, item) => total + item.loss, 0),
);
const money = (value: number | string) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const add = () => form.items.push(fresh());
const remove = (i: number) => form.items.length > 1 && form.items.splice(i, 1);
const calculatorItem = computed(
    () => form.items[calculatorItemIndex.value] ?? form.items[0],
);
const focusCalculatorItem = (index: number) => {
    calculatorItemIndex.value = index;
};
const pasteCalculatorPrice = (
    field: "purchase_price" | "selling_price",
    value: string,
) => {
    const item = calculatorItem.value;
    if (!item || (field === "purchase_price" && !props.canViewCost)) return;
    item[field] = value;
};
const generatePoNumber = () => {
    const datePart = (form.invoice_date || today).replaceAll("-", "");
    const randomPart = String(Math.floor(1000 + Math.random() * 9000));
    form.purchase_order_number = `PO-${datePart}-${randomPart}`;
};
const persistInvoice = () => {
    return initial
        ? form.put(route("invoices.update", initial.id))
        : form.post(route("invoices.store"));
};
const submit = () => {
    if (estimatedLoss.value > 0) {
        lossWarningOpen.value = true;
        return;
    }

    persistInvoice();
};
const confirmLossAndSubmit = () => {
    lossWarningOpen.value = false;
    persistInvoice();
};
const loadCustomerPrices = async (customerId: string) => {
    const requestId = ++customerPriceRequest;
    customerPrices.value = [];
    if (!customerId) return;

    customerPricesLoading.value = true;
    try {
        const response = await fetch(
            route("customers.item-prices", Number(customerId)),
            { headers: { Accept: "application/json" } },
        );
        if (!response.ok) throw new Error("Gagal memuat riwayat harga pelanggan.");
        const payload = (await response.json()) as { items: CustomerPriceOption[] };
        if (requestId === customerPriceRequest) customerPrices.value = payload.items;
    } catch {
        if (requestId === customerPriceRequest) customerPrices.value = [];
    } finally {
        if (requestId === customerPriceRequest) customerPricesLoading.value = false;
    }
};
watch(
    () => form.customer_id,
    (customerId) => loadCustomerPrices(customerId),
    { immediate: true },
);
</script>
<template>
    <Head
        :title="initial ? 'Edit Invoice' : 'Buat Invoice'"
    /><AuthenticatedLayout
        ><template #breadcrumb
            >Transaksi /
            {{ initial ? "Edit Invoice" : "Buat Invoice" }}</template
        >
        <form @submit.prevent="submit">
            <div
                class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <h1 class="page-title">
                        {{ initial ? "Edit Invoice" : "Buat Invoice" }}
                    </h1>
                    <p class="page-subtitle">
                        Nilai dihitung realtime dan diverifikasi ulang oleh
                        server saat disimpan.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <AppButton variant="secondary" @click="calculatorOpen = true">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="2.5" width="16" height="19" rx="2" />
                            <path d="M7.5 6.5h9M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01M16 19h.01" stroke-linecap="round" />
                        </svg>
                        Calculator
                    </AppButton>
                    <Link v-if="initial" :href="route('invoices.show', initial.id)">
                        <AppButton variant="secondary">Batal Edit</AppButton>
                    </Link>
                    <AppButton type="submit" :disabled="form.processing">{{
                        initial ? "Simpan Perubahan" : "Simpan sebagai Draft"
                    }}</AppButton>
                </div>
            </div>
            <section class="panel p-5">
                <h2 class="mb-5 font-bold">Informasi Invoice</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label
                        ><span class="label">Pelanggan *</span
                        ><AppSelect v-model="form.customer_id" required
                            ><option value="">Pilih pelanggan</option>
                            <option
                                v-for="c in customers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.company_name || c.name }}
                            </option></AppSelect
                        ></label
                    ><label
                        ><span class="label">Nomor PO / Referensi</span>
                        <div class="flex gap-2">
                            <AppInput
                                v-model="form.purchase_order_number"
                                placeholder="Isi manual atau generate"
                            />
                            <button
                                type="button"
                                title="Generate Nomor PO"
                                aria-label="Generate Nomor PO"
                                class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg border border-sky-300 text-xl font-bold text-sky-600 transition hover:bg-sky-50"
                                @click="generatePoNumber"
                            >
                                ↻
                            </button>
                        </div></label
                    >
                    <div class="space-y-4">
                        <label class="block"
                            ><span class="label">Alamat Penagihan</span
                            ><AppTextarea
                                :model-value="selectedCustomer?.address ?? ''"
                                :rows="3"
                                disabled
                        /></label>
                    </div>
                    <div class="space-y-4">
                        <label
                            ><span class="label">Tanggal Invoice *</span
                            ><AppInput
                                v-model="form.invoice_date"
                                type="date"
                                required
                        /></label>
                    </div>
                </div>
            </section>
            <section class="panel mt-5">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-bold">Rincian Barang</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        <template v-if="customerPricesLoading">Memuat riwayat harga pelanggan...</template>
                        <template v-else-if="customerPrices.length">
                            {{ customerPrices.length }} barang dari riwayat pelanggan siap digunakan.
                        </template>
                        <template v-else>
                            Pilih pelanggan dan cari barang. Harga terakhir pelanggan akan diprioritaskan.
                        </template>
                    </p>
                </div>
                <InvoiceItemTable
                    :items="form.items"
                    :products="products"
                    :customer-prices="customerPrices"
                    :can-view-cost="canViewCost"
                    @add="add"
                    @remove="remove"
                    @focus-item="focusCalculatorItem"
                />
            </section>
            <section class="panel mt-5 p-5">
                <div class="lg:ml-auto lg:w-1/2">
                    <div class="mb-4 grid grid-cols-2 gap-3">
                            <label v-if="discountEnabled"
                                ><span class="label">Tipe Diskon</span
                                ><AppSelect v-model="form.discount_type"
                                    ><option value="percentage">
                                        Persentase (%)
                                    </option>
                                    <option value="nominal">
                                        Nominal Rupiah
                                    </option></AppSelect
                                ></label
                            ><label v-if="discountEnabled"
                                ><span class="label">Nilai Diskon</span
                                ><AppInput
                                    v-model="form.discount_value"
                                    type="number"
                                    min="0"
                                    :max="
                                        form.discount_type === 'percentage'
                                            ? 100
                                            : undefined
                                    "
                                    :step="
                                        form.discount_type === 'percentage'
                                            ? 1
                                            : 0.01
                                    " /></label
                            ><label v-if="taxEnabled"
                                ><span class="label">Pajak (%)</span
                                ><AppInput
                                    v-model="form.tax_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                            /></label>
                    </div>
                    <InvoiceSummary
                        :subtotal="subtotal"
                        :discount="discount"
                        :tax-base="taxBase"
                        :tax="tax"
                        :grand-total="grand"
                        :discount-enabled="discountEnabled"
                        :tax-enabled="taxEnabled"
                    />
                </div>
                <div
                    v-if="Object.keys(form.errors).length"
                    class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ Object.values(form.errors)[0] }}
                </div>
            </section>
        </form>
        <Modal :show="lossWarningOpen" max-width="lg" :closeable="false">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xl text-amber-700">
                        !
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Peringatan Invoice Rugi</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Harga jual beberapa barang lebih kecil dari harga beli.
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="space-y-2 text-sm text-slate-700">
                        <div
                            v-for="(item, index) in lossItems"
                            :key="`${item.name}-${index}`"
                            class="flex justify-between gap-4"
                        >
                            <span>{{ item.name }}</span>
                            <strong class="text-red-700">- {{ money(item.loss) }}</strong>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-amber-200 pt-4">
                        <span class="font-semibold text-slate-800">Estimasi total kerugian</span>
                        <strong class="text-xl text-red-700">{{ money(estimatedLoss) }}</strong>
                    </div>
                </div>

                <p class="mt-4 text-sm font-medium text-slate-700">
                    Apakah Anda tetap ingin menyimpan invoice ini?
                </p>
                <div class="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                    <AppButton variant="secondary" @click="lossWarningOpen = false">
                        Tidak, Kembali ke Invoice
                    </AppButton>
                    <AppButton :disabled="form.processing" @click="confirmLossAndSubmit">
                        Ya, Lanjutkan Simpan
                    </AppButton>
                </div>
            </div>
        </Modal>
        <InvoiceCalculator
            :show="calculatorOpen"
            :target-item-name="calculatorItem?.product_name"
            :can-paste-purchase="canViewCost"
            @close="calculatorOpen = false"
            @paste-purchase="pasteCalculatorPrice('purchase_price', $event)"
            @paste-selling="pasteCalculatorPrice('selling_price', $event)"
        />
    </AuthenticatedLayout
    >
</template>
