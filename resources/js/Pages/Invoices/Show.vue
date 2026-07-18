<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import CurrencyInput from "@/Components/UI/CurrencyInput.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
import { percentageText } from "@/utils/percentage";
import { directPrint } from "@/utils/directPrint";
interface Item {
    id: number;
    product_name_snapshot: string;
    sku_snapshot: string;
    unit_snapshot: string;
    selling_price: string;
    purchase_price?: string;
    quantity: string;
    line_subtotal: string;
    cost_total?: string;
}
interface Payment {
    id: number;
    payment_number: string;
    payment_date: string;
    amount: string;
    payment_method: string;
}
interface Delivery {
    id: number;
    status: "pending" | "accepted" | "in_transit" | "delivered" | "cancelled";
    created_at?: string;
    accepted_at?: string;
    departed_at?: string;
    delivered_at?: string;
    proof_taken_at?: string;
    proof_url?: string;
    delivery_address?: string;
    departure_photo_taken_at?: string;
    departure_proof_url?: string;
    departure_address?: string;
    departed_latitude?: string;
    departed_longitude?: string;
    departed_accuracy?: string;
    delivered_latitude?: string;
    delivered_longitude?: string;
    delivered_accuracy?: string;
    delivery_notes?: string;
}
interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    billing_name: string;
    billing_company?: string;
    billing_address?: string;
    courier_name?: string;
    courier_id?: number;
    shipping_cost: string;
    subtotal: string;
    discount_amount: string;
    tax_percentage: string;
    tax_amount: string;
    grand_total: string;
    paid_amount: string;
    remaining_amount: string;
    status: string | { value: string };
    issued_at?: string;
    notes?: string;
    terms?: string;
    items: Item[];
    payments: Payment[];
    delivery?: Delivery;
    shipping_deposit?: {
        id: number;
        amount: string;
        paid_at?: string;
    };
}
interface Courier {
    id: number;
    name: string;
    phone?: string;
    vehicle_type?: string;
    license_plate?: string;
    is_active: boolean;
    deleted_at?: string;
}
const props = defineProps<{
    invoice: Invoice;
    couriers: Courier[];
    canViewCost: boolean;
    canEditInvoice: boolean;
    canDeleteInvoice: boolean;
}>();
const paymentOpen = ref(false);
const shippingOpen = ref(false);
const editMenuOpen = ref(false);
const documentMenuOpen = ref(false);
const deliveryDetailOpen = ref(false);
const status = () =>
    typeof props.invoice.status === "string"
        ? props.invoice.status
        : props.invoice.status.value;
const money = (v: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v));
const deliverySteps = [
    {
        key: "pending",
        label: "Tugas Belum Diambil",
        description: "Menunggu kurir mengambil tugas",
        timeKey: "created_at",
    },
    {
        key: "accepted",
        label: "Tugas Diambil",
        description: "Kurir telah menerima tugas",
        timeKey: "accepted_at",
    },
    {
        key: "in_transit",
        label: "Dalam Pengantaran",
        description: "Barang sedang diantar ke pelanggan",
        timeKey: "departed_at",
    },
    {
        key: "delivered",
        label: "Selesai",
        description: "Barang telah sampai di lokasi",
        timeKey: "delivered_at",
    },
] as const;
const deliveryStatusIndex = computed(() => {
    switch (props.invoice.delivery?.status) {
        case "delivered": return 3;
        case "in_transit": return 2;
        case "accepted": return 1;
        case "pending": return 0;
        default: return -1;
    }
});
const deliveryStepTime = (timeKey: (typeof deliverySteps)[number]["timeKey"]) =>
    props.invoice.delivery?.[timeKey];
const deliveryStepComplete = (index: number, timeKey: (typeof deliverySteps)[number]["timeKey"]) =>
    Boolean(deliveryStepTime(timeKey)) || deliveryStatusIndex.value >= index;
const formatDeliveryTime = (value?: string) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "Belum";
const pay = useForm({
    payment_date: new Date().toISOString().slice(0, 10),
    amount: props.invoice.remaining_amount,
    payment_method: "transfer",
    bank_name: "",
    reference_number: "",
    notes: "",
});
const submitPayment = () =>
    pay.post(route("payments.store", props.invoice.id), {
        onSuccess: () => (paymentOpen.value = false),
    });
const shippingForm = useForm({
    courier_id: String(props.invoice.courier_id ?? ""),
    shipping_cost: String(props.invoice.shipping_cost ?? "0"),
    shipping_paid_now: Boolean(props.invoice.shipping_deposit?.paid_at),
});
const openShipping = () => {
    editMenuOpen.value = false;
    shippingForm.courier_id = String(props.invoice.courier_id ?? "");
    shippingForm.shipping_cost = String(props.invoice.shipping_cost ?? "0");
    shippingForm.shipping_paid_now =
        status() === "draft"
            ? true
            : Boolean(props.invoice.shipping_deposit?.paid_at);
    shippingForm.clearErrors();
    shippingOpen.value = true;
};
const toggleEditMenu = () => {
    editMenuOpen.value = !editMenuOpen.value;
    documentMenuOpen.value = false;
};
const toggleDocumentMenu = () => {
    documentMenuOpen.value = !documentMenuOpen.value;
    editMenuOpen.value = false;
};
const printInvoice = () => {
    documentMenuOpen.value = false;
    directPrint(route("invoices.print", props.invoice.id));
};
const submitShipping = () => {
    const options = { onSuccess: () => (shippingOpen.value = false) };

    return status() === "draft"
        ? shippingForm.post(route("invoices.issue", props.invoice.id), options)
        : shippingForm.put(
              route("invoices.shipping.update", props.invoice.id),
              options,
          );
};
const cancel = () =>
    confirm("Batalkan invoice ini?") &&
    router.post(route("invoices.cancel", props.invoice.id));
const remove = () =>
    confirm(
        `Hapus invoice ${props.invoice.invoice_number} beserta riwayat pembayaran dan Cash Masuk terkait?`,
    ) && router.delete(route("invoices.destroy", props.invoice.id));
</script>
<template>
    <Head :title="invoice.invoice_number" /><AuthenticatedLayout
        ><template #breadcrumb
            >Transaksi / Invoice / {{ invoice.invoice_number }}</template
        >
        <div
            class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
        >
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="page-title">{{ invoice.invoice_number }}</h1>
                    <StatusBadge :status="status()" />
                </div>
                <p class="page-subtitle">
                    {{ invoice.billing_company || invoice.billing_name }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('invoices.index')"
                    ><AppButton variant="secondary">Kembali</AppButton></Link
                >
                <div v-if="canEditInvoice" class="relative">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2"
                        aria-label="Menu Edit Invoice"
                        @click="toggleEditMenu"
                    >
                        Edit Invoice
                        <span class="text-xs" :class="editMenuOpen ? 'rotate-180' : ''">▼</span>
                    </button>
                    <div
                        v-if="editMenuOpen"
                        class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl"
                    >
                        <Link
                            :href="route('invoices.edit', invoice.id)"
                            class="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700"
                            @click="editMenuOpen = false"
                        >Edit Data Invoice</Link>
                        <button
                            v-if="status() !== 'draft'"
                            type="button"
                            class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700"
                            @click="openShipping"
                        >
                            Edit Kurir &amp; Ongkir
                        </button>
                    </div>
                </div>
                <div class="relative">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2"
                        aria-label="Menu Cetak dan Download"
                        @click="toggleDocumentMenu"
                    >
                        Cetak / Download
                        <span class="text-xs" :class="documentMenuOpen ? 'rotate-180' : ''">▼</span>
                    </button>
                    <div
                        v-if="documentMenuOpen"
                        class="absolute right-0 z-20 mt-2 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl"
                    >
                        <button
                            type="button"
                            class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700"
                            @click="printInvoice"
                        >Cetak Invoice</button>
                        <a
                            :href="route('invoices.pdf', invoice.id)"
                            class="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-sky-50 hover:text-sky-700"
                            @click="documentMenuOpen = false"
                        >Download PDF</a>
                    </div>
                </div>
                <AppButton v-if="status() === 'draft'" @click="openShipping"
                    >Terbitkan</AppButton
                ><AppButton
                    v-if="!['draft', 'paid', 'cancelled'].includes(status())"
                    @click="paymentOpen = true"
                    >Catat Pembayaran</AppButton
                ><AppButton
                    v-if="!['draft', 'cancelled'].includes(status())"
                    variant="danger"
                    @click="cancel"
                    >Batalkan</AppButton
                ><AppButton
                    v-else-if="status() === 'cancelled' && canDeleteInvoice"
                    variant="danger"
                    @click="remove"
                    >Hapus Invoice</AppButton
                >
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <div class="space-y-5">
                <section class="panel p-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <p class="label">Pelanggan</p>
                            <h2 class="font-bold">
                                {{
                                    invoice.billing_company ||
                                    invoice.billing_name
                                }}
                            </h2>
                            <p
                                class="mt-1 whitespace-pre-line text-sm text-slate-500"
                            >
                                {{ invoice.billing_name }}<br />{{
                                    invoice.billing_address
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="label">Informasi Invoice</p>
                            <dl class="grid grid-cols-2 gap-2 text-sm">
                                <dt class="text-slate-500">Tanggal</dt>
                                <dd>
                                    {{
                                        new Date(
                                            invoice.invoice_date,
                                        ).toLocaleDateString("id-ID")
                                    }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </section>
                <section class="panel">
                    <div class="border-b px-5 py-4 font-bold">
                        Rincian Barang
                    </div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th v-if="canViewCost">Harga Beli</th>
                                    <th>Harga Jual</th>
                                    <th>Qty</th>
                                    <th>Satuan</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in invoice.items"
                                    :key="item.id"
                                >
                                    <td>
                                        {{ item.product_name_snapshot }}
                                        <div class="text-xs text-slate-400">
                                            {{ item.sku_snapshot }}
                                        </div>
                                    </td>
                                    <td v-if="canViewCost">
                                        {{ money(item.purchase_price ?? 0) }}
                                    </td>
                                    <td>{{ money(item.selling_price) }}</td>
                                    <td>{{ item.quantity }}</td>
                                    <td>{{ item.unit_snapshot }}</td>
                                    <td class="font-semibold">
                                        {{ money(item.line_subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section v-if="invoice.payments.length" class="panel">
                    <div class="border-b px-5 py-4 font-bold">
                        Riwayat Pembayaran
                    </div>
                    <table class="data-table">
                        <tbody>
                            <tr v-for="p in invoice.payments" :key="p.id">
                                <td>{{ p.payment_number }}</td>
                                <td>
                                    {{
                                        new Date(
                                            p.payment_date,
                                        ).toLocaleDateString("id-ID")
                                    }}
                                </td>
                                <td>{{ p.payment_method }}</td>
                                <td class="text-right font-semibold">
                                    {{ money(p.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
            <aside class="space-y-5">
                <section class="panel p-5">
                    <h2 class="mb-4 font-bold">Ringkasan</h2>
                    <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Subtotal</dt>
                        <dd>{{ money(invoice.subtotal) }}</dd>
                    </div>
                    <div
                        v-if="Number(invoice.discount_amount) > 0"
                        class="flex justify-between text-red-600"
                    >
                        <dt>Diskon</dt>
                        <dd>- {{ money(invoice.discount_amount) }}</dd>
                    </div>
                    <div
                        v-if="Number(invoice.tax_amount) > 0"
                        class="flex justify-between"
                    >
                        <dt>Pajak ({{ percentageText(invoice.tax_percentage) }})</dt>
                        <dd>{{ money(invoice.tax_amount) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-4 text-base font-bold"
                    >
                        <dt>Grand Total</dt>
                        <dd class="text-sky-600">
                            {{ money(invoice.grand_total) }}
                        </dd>
                    </div>
                    <div class="flex justify-between text-emerald-600">
                        <dt>Terbayar</dt>
                        <dd>{{ money(invoice.paid_amount) }}</dd>
                    </div>
                    <div class="flex justify-between font-bold">
                        <dt>Sisa</dt>
                        <dd>{{ money(invoice.remaining_amount) }}</dd>
                    </div>
                    </dl>
                </section>
                <section class="panel p-5">
                    <h2 class="mb-4 font-bold">Informasi Pengiriman</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Kurir</dt>
                            <dd class="text-right font-semibold">
                                {{ invoice.courier_name || "-" }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 border-t border-slate-200 pt-3"
                        >
                            <dt class="text-slate-500">Ongkir Kurir</dt>
                            <dd class="text-right font-semibold">
                                {{ money(invoice.shipping_cost) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(invoice.shipping_cost) > 0 && status() !== 'draft'"
                            class="flex justify-between gap-4 border-t border-slate-200 pt-3"
                        >
                            <dt class="text-slate-500">Status Ongkir</dt>
                            <dd
                                class="text-right font-semibold"
                                :class="invoice.shipping_deposit?.paid_at ? 'text-emerald-600' : 'text-amber-600'"
                            >
                                {{ invoice.shipping_deposit?.paid_at ? "Sudah Dibayar" : "Deposito Belum Dibayar" }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <h3 class="mb-4 text-sm font-bold text-slate-800">
                            Log Status Pengiriman
                        </h3>
                        <div v-if="invoice.delivery" class="space-y-0">
                            <div
                                v-for="(step, index) in deliverySteps"
                                :key="step.key"
                                class="relative flex gap-3 pb-5 last:pb-0"
                            >
                                <span
                                    v-if="index < deliverySteps.length - 1"
                                    class="absolute left-[7px] top-4 h-full w-px"
                                    :class="deliveryStepComplete(index + 1, deliverySteps[index + 1].timeKey) ? 'bg-emerald-400' : 'bg-slate-200'"
                                ></span>
                                <span
                                    class="relative z-10 mt-1 h-4 w-4 shrink-0 rounded-full border-2 bg-white"
                                    :class="deliveryStepComplete(index, step.timeKey) ? 'border-emerald-500 bg-emerald-500' : 'border-slate-300'"
                                ></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-800">{{ step.label }}</p>
                                        <span
                                            v-if="invoice.delivery.status === step.key"
                                            class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-700"
                                        >Saat ini</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ step.description }}</p>
                                    <p class="mt-1 text-xs font-medium" :class="deliveryStepTime(step.timeKey) ? 'text-slate-600' : 'text-slate-400'">
                                        {{ formatDeliveryTime(deliveryStepTime(step.timeKey)) }}
                                    </p>
                                    <button
                                        v-if="step.key === 'delivered' && invoice.delivery.status === 'delivered'"
                                        type="button"
                                        class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700 hover:bg-sky-100"
                                        @click="deliveryDetailOpen = true"
                                    >
                                        Lihat Detail
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="invoice.delivery.status === 'cancelled'"
                                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"
                            >
                                Tugas pengiriman dibatalkan
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">
                            Tugas pengiriman belum dibuat.
                        </p>
                    </div>
                </section>
            </aside>
        </div>
        <AppModal
            :show="shippingOpen"
            :title="status() === 'draft' ? 'Terbitkan Invoice' : 'Edit Kurir & Ongkir'"
            @close="shippingOpen = false"
        >
            <form class="space-y-5" @submit.prevent="submitShipping">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="label">Kurir *</span>
                        <AppSelect v-model="shippingForm.courier_id" required>
                            <option value="">Pilih kurir</option>
                            <option v-for="courier in couriers" :key="courier.id" :value="String(courier.id)">
                                {{ courier.name }}
                                {{ courier.vehicle_type ? `- ${courier.vehicle_type}` : "" }}
                                {{ courier.license_plate ? `(${courier.license_plate})` : "" }}
                                {{ !courier.is_active || courier.deleted_at ? "(Nonaktif)" : "" }}
                            </option>
                        </AppSelect>
                    </label>
                    <label class="block">
                        <span class="label">Ongkos Kirim *</span>
                        <CurrencyInput v-model="shippingForm.shipping_cost" placeholder="0" />
                    </label>
                </div>
                <fieldset v-if="Number(shippingForm.shipping_cost) > 0">
                    <legend class="mb-3 font-semibold">
                        Apakah ongkir dibayarkan langsung ke kurir?
                    </legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-lg border p-4" :class="shippingForm.shipping_paid_now ? 'border-sky-500 bg-sky-50' : 'border-slate-200'">
                            <input v-model="shippingForm.shipping_paid_now" class="mr-2" type="radio" :value="true" />
                            <strong>Ya, bayar langsung</strong>
                            <span class="mt-1 block text-xs text-slate-500">Ongkir langsung dicatat ke Kas Keluar.</span>
                        </label>
                        <label class="cursor-pointer rounded-lg border p-4" :class="!shippingForm.shipping_paid_now ? 'border-amber-500 bg-amber-50' : 'border-slate-200'">
                            <input v-model="shippingForm.shipping_paid_now" class="mr-2" type="radio" :value="false" />
                            <strong>Tidak, simpan deposito</strong>
                            <span class="mt-1 block text-xs text-slate-500">Ongkir dicatat sebagai belum dibayar di detail kurir.</span>
                        </label>
                    </div>
                </fieldset>
                <p v-else class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">Ongkir Rp 0 tidak akan dicatat ke Kas Keluar maupun deposito kurir.</p>
                <div v-if="Object.keys(shippingForm.errors).length" class="text-sm text-red-600">
                    {{ Object.values(shippingForm.errors)[0] }}
                </div>
                <div class="flex justify-end gap-2">
                    <AppButton variant="secondary" @click="shippingOpen = false">Batal</AppButton>
                    <AppButton type="submit" :disabled="shippingForm.processing">
                        {{ status() === "draft" ? "Terbitkan Invoice" : "Simpan Pengiriman" }}
                    </AppButton>
                </div>
            </form>
        </AppModal>
        <AppModal
            :show="deliveryDetailOpen"
            title="Detail Bukti Pengiriman"
            @close="deliveryDetailOpen = false"
        >
            <div v-if="invoice.delivery" class="space-y-4">
                <section v-if="invoice.delivery.departure_proof_url" class="space-y-3">
                    <h3 class="font-bold text-slate-800">Bukti Sebelum Mengantar</h3>
                    <img
                        :src="invoice.delivery.departure_proof_url"
                        alt="Bukti kurir sebelum mulai mengantar"
                        class="max-h-[60vh] w-full rounded-xl border border-slate-200 bg-slate-100 object-contain"
                    />
                    <dl class="grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat Keberangkatan</dt><dd class="mt-1 font-medium text-slate-800">{{ invoice.delivery.departure_address || '-' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal dan Waktu</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDeliveryTime(invoice.delivery.departure_photo_taken_at || invoice.delivery.departed_at) }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Koordinat GPS</dt><dd class="mt-1 font-medium text-slate-800">{{ invoice.delivery.departed_latitude || '-' }}, {{ invoice.delivery.departed_longitude || '-' }}<span v-if="invoice.delivery.departed_accuracy" class="block text-xs text-slate-500">Akurasi ±{{ Math.round(Number(invoice.delivery.departed_accuracy)) }} meter</span></dd></div>
                    </dl>
                </section>
                <h3 v-if="invoice.delivery.departure_proof_url" class="border-t border-slate-200 pt-4 font-bold text-slate-800">Bukti Sampai di Lokasi</h3>
                <img
                    v-if="invoice.delivery.proof_url"
                    :src="invoice.delivery.proof_url"
                    alt="Bukti pengiriman bertimestamp"
                    class="max-h-[60vh] w-full rounded-xl border border-slate-200 bg-slate-100 object-contain"
                />
                <div v-else class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    Foto bukti tidak tersedia.
                </div>
                <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat Lengkap</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ invoice.delivery.delivery_address || "-" }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal dan Waktu</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ formatDeliveryTime(invoice.delivery.proof_taken_at || invoice.delivery.delivered_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Koordinat GPS</dt>
                        <dd class="mt-1 font-medium text-slate-800">
                            {{ invoice.delivery.delivered_latitude || "-" }}, {{ invoice.delivery.delivered_longitude || "-" }}
                            <span v-if="invoice.delivery.delivered_accuracy" class="block text-xs text-slate-500">Akurasi ±{{ Math.round(Number(invoice.delivery.delivered_accuracy)) }} meter</span>
                        </dd>
                    </div>
                    <div v-if="invoice.delivery.delivery_notes" class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Penerima</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ invoice.delivery.delivery_notes }}</dd>
                    </div>
                </dl>
                <div class="flex justify-end">
                    <AppButton variant="secondary" @click="deliveryDetailOpen = false">Tutup</AppButton>
                </div>
            </div>
        </AppModal>
        <AppModal
            :show="paymentOpen"
            title="Catat Pembayaran"
            @close="paymentOpen = false"
            ><form
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submitPayment"
            >
                <label
                    ><span class="label">Tanggal</span
                    ><AppInput
                        v-model="pay.payment_date"
                        type="date"
                        required /></label
                ><label
                    ><span class="label">Nominal</span
                    ><AppInput
                        v-model="pay.amount"
                        type="number"
                        min="1"
                        required /></label
                ><label
                    ><span class="label">Metode</span
                    ><AppSelect v-model="pay.payment_method"
                        ><option value="transfer">Transfer bank</option>
                        <option value="cash">Tunai</option>
                        <option value="card">Kartu</option>
                        <option value="qris">QRIS</option>
                        <option value="virtual_account">Virtual account</option>
                        <option value="other">Lainnya</option></AppSelect
                    ></label
                ><label
                    ><span class="label">Bank Tujuan</span
                    ><AppInput v-model="pay.bank_name" /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Nomor Referensi</span
                    ><AppInput v-model="pay.reference_number" /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Catatan</span
                    ><AppTextarea v-model="pay.notes"
                /></label>
                <div
                    v-if="Object.keys(pay.errors).length"
                    class="sm:col-span-2 text-sm text-red-600"
                >
                    {{ Object.values(pay.errors)[0] }}
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="paymentOpen = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="pay.processing"
                        >Simpan Pembayaran</AppButton
                    >
                </div>
            </form></AppModal
        ></AuthenticatedLayout
    >
</template>
