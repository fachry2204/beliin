<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
import { percentageText } from "@/utils/percentage";
import { directPrint } from "@/utils/directPrint";

interface Customer {
    id: number;
    customer_code: string;
    name: string;
    company_name?: string;
    phone?: string;
    email?: string;
    address?: string;
}
interface Invoice {
    id: number;
    invoice_number: string;
    purchase_order_number?: string;
    invoice_date: string;
    grand_total: string;
    paid_amount: string;
    remaining_amount: string;
    subtotal?: string;
    discount_amount?: string;
    gross_profit?: string;
    status: string | { value: string };
}
interface Totals {
    grand_total: string;
    paid_total: string;
    remaining_total: string;
    gross_profit_total?: string;
    profit_base_total?: string;
    commission_total?: string;
}
interface PaymentRecord {
    id: number;
    payment_number: string;
    payment_date: string;
    amount: string;
    payment_method: string;
    bank_name?: string | null;
    reference_number?: string | null;
    notes?: string | null;
    invoice: { id: number; invoice_number: string };
}
interface Document {
    id: number;
    facture_number: string;
    opened_at: string;
    due_date?: string | null;
    status: string;
    courier_name?: string | null;
    shipping_cost?: string | number;
}
const props = defineProps<{
    document: Document;
    customer: Customer;
    invoices: Invoice[];
    payments: PaymentRecord[];
    totals: Totals;
    canViewProfit: boolean;
    canManagePayments: boolean;
    canEditDueDate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    deletionLocked: boolean;
    today: string;
    defaultDueDate: string;
    commissionWarningPercentage: number;
}>();
const paymentOpen = ref(false);
const dueDateOpen = ref(false);
const editPaymentOpen = ref(false);
const editingPayment = ref<PaymentRecord | null>(null);
const payment = useForm({
    payment_date: props.today,
    amount: props.totals.remaining_total,
    payment_method: "transfer",
    bank_name: "",
    reference_number: "",
    notes: "",
    payment_proof: null as File | null,
    commission_enabled: false,
    commission_base: "facture_total",
    commission_type: "nominal",
    commission_value: "",
    commission_notes: "",
});
const dueDateForm = useForm({
    use_due_date: Boolean(props.document.due_date),
    due_date: props.document.due_date?.slice(0, 10) || props.defaultDueDate,
});
const submitPayment = () =>
    payment.post(route("combined-invoices.pay", props.document.id), {
        forceFormData: true,
        onSuccess: () => (paymentOpen.value = false),
    });
const submitDueDate = () =>
    dueDateForm.put(
        route("combined-invoices.due-date.update", props.document.id),
        { onSuccess: () => (dueDateOpen.value = false) },
    );
const editPaymentForm = useForm({
    payment_date: "",
    amount: "",
    payment_method: "transfer",
    bank_name: "",
    reference_number: "",
    notes: "",
});
const openPaymentCorrection = (row: PaymentRecord) => {
    editingPayment.value = row;
    editPaymentForm.payment_date = row.payment_date.slice(0, 10);
    editPaymentForm.amount = row.amount;
    editPaymentForm.payment_method = row.payment_method;
    editPaymentForm.bank_name = row.bank_name || "";
    editPaymentForm.reference_number = row.reference_number || "";
    editPaymentForm.notes = row.notes || "";
    editPaymentForm.clearErrors();
    editPaymentOpen.value = true;
};
const submitPaymentCorrection = () => {
    if (!editingPayment.value) return;
    editPaymentForm.put(
        route("combined-invoices.payments.update", [
            props.document.id,
            editingPayment.value.id,
        ]),
        {
            onSuccess: () => {
                editPaymentOpen.value = false;
                editingPayment.value = null;
            },
        },
    );
};
const remove = () =>
    confirm(
        `Hapus Faktur ${props.document.facture_number}? Invoice di dalamnya tidak akan dihapus.`,
    ) && router.delete(route("combined-invoices.destroy", props.document.id));
const printFacture = () =>
    directPrint(route("combined-invoices.print", props.document.id));
const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const date = (value?: string | null) =>
    value ? new Date(value).toLocaleDateString("id-ID") : "Tanpa jatuh tempo";
const status = (value: Invoice["status"]) =>
    typeof value === "string" ? value : value.value;
const marginRate = (profit: string | number, base: string | number) =>
    percentageText(
        Number(base) > 0 ? (Number(profit) / Number(base)) * 100 : 0,
    );
const commissionBaseAmount = computed(() =>
    payment.commission_base === "margin"
        ? Number(props.totals.gross_profit_total || 0)
        : Number(props.totals.grand_total),
);
const commissionAmount = computed(() =>
    payment.commission_type === "percentage"
        ? (commissionBaseAmount.value * Number(payment.commission_value || 0)) /
          100
        : Number(payment.commission_value || 0),
);
const commissionWarningLimit = computed(
    () =>
        (Number(props.totals.gross_profit_total || 0) *
            props.commissionWarningPercentage) /
        100,
);
const finalMargin = computed(
    () => Number(props.totals.gross_profit_total || 0) - commissionAmount.value,
);
const commissionWarning = computed(
    () =>
        payment.commission_enabled &&
        Number(props.totals.gross_profit_total || 0) > 0 &&
        commissionAmount.value > commissionWarningLimit.value,
);
</script>

<template>
    <Head :title="document.facture_number" /><AuthenticatedLayout
        ><template #breadcrumb
            >Transaksi / Faktur / {{ document.facture_number }}</template
        >
        <div
            class="mb-6 flex flex-col justify-between gap-3 lg:flex-row lg:items-end"
        >
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="page-title">{{ document.facture_number }}</h1>
                    <p class="page-subtitle">
                        {{ invoices.length }} invoice dipilih untuk
                        {{ customer.company_name || customer.name }}.
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-bold"
                    :class="
                        document.status === 'open'
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-emerald-100 text-emerald-700'
                    "
                    >{{ document.status === "open" ? "Aktif" : "Lunas" }}</span
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('combined-invoices.index')"
                    ><AppButton variant="secondary">Kembali</AppButton></Link
                ><Link
                    v-if="canEdit"
                    :href="route('combined-invoices.edit', document.id)"
                    ><AppButton variant="secondary"
                        >Edit Faktur</AppButton
                    ></Link
                ><AppButton
                    v-if="
                        canManagePayments && Number(totals.remaining_total) > 0
                    "
                    @click="paymentOpen = true"
                    >Bayar</AppButton
                ><AppButton variant="secondary" @click="printFacture"
                    >Cetak</AppButton
                ><a :href="route('combined-invoices.pdf', document.id)"
                    ><AppButton variant="secondary">Download PDF</AppButton></a
                ><AppButton
                    v-if="canDelete"
                    variant="danger"
                    :disabled="deletionLocked"
                    :title="
                        deletionLocked
                            ? 'Faktur yang sudah menerima pembayaran tidak dapat dihapus'
                            : 'Hapus Faktur'
                    "
                    @click="remove"
                    >{{
                        deletionLocked ? "Tidak Dapat Dihapus" : "Hapus Faktur"
                    }}</AppButton
                >
            </div>
        </div>

        <section class="panel mb-5 p-5">
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <span class="label">Nomor Faktur</span
                    ><strong class="block text-lg text-sky-700">{{
                        document.facture_number
                    }}</strong
                    ><span class="text-xs text-slate-500"
                        >Dibuat {{ date(document.opened_at) }}</span
                    >
                </div>
                <div>
                    <span class="label">Tagihan Kepada</span
                    ><strong class="block text-lg">{{
                        customer.company_name || customer.name
                    }}</strong
                    ><span
                        >{{ customer.name }} · {{ customer.phone || "-" }}</span
                    >
                </div>
                <div>
                    <span class="label">Alamat</span>
                    <p>{{ customer.address || "-" }}</p>
                </div>
                <div>
                    <span class="label">Driver & Ongkir</span>
                    <strong class="block">{{ document.courier_name || "-" }}</strong>
                    <span class="text-sm text-slate-500">{{ money(document.shipping_cost || 0) }}</span>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <span class="label">Tanggal Jatuh Tempo</span
                    ><strong
                        class="block text-lg"
                        :class="
                            document.due_date
                                ? 'text-amber-700'
                                : 'text-slate-500'
                        "
                        >{{ date(document.due_date) }}</strong
                    ><button
                        v-if="canEditDueDate"
                        type="button"
                        class="mt-2 text-sm font-semibold text-sky-600 hover:underline"
                        @click="dueDateOpen = true"
                    >
                        Ubah pengaturan
                    </button>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table min-w-[850px]">
                    <thead>
                        <tr>
                            <th>Nomor Invoice</th>
                            <th>No. PO</th>
                            <th>Tanggal</th>
                            <th>Grand Total</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
                            <th v-if="canViewProfit">Margin</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td>
                                <Link
                                    :href="route('invoices.show', invoice.id)"
                                    class="font-semibold text-sky-600"
                                    >{{ invoice.invoice_number }}</Link
                                >
                            </td>
                            <td>{{ invoice.purchase_order_number || "-" }}</td>
                            <td>{{ date(invoice.invoice_date) }}</td>
                            <td>{{ money(invoice.grand_total) }}</td>
                            <td class="text-emerald-600">
                                {{ money(invoice.paid_amount) }}
                            </td>
                            <td class="font-bold text-red-600">
                                {{ money(invoice.remaining_amount) }}
                            </td>
                            <td v-if="canViewProfit">
                                <div class="font-semibold text-emerald-600">
                                    {{ money(invoice.gross_profit || 0) }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{
                                        marginRate(
                                            invoice.gross_profit || 0,
                                            Number(invoice.subtotal || 0) -
                                                Number(
                                                    invoice.discount_amount ||
                                                        0,
                                                ),
                                        )
                                    }}
                                </div>
                            </td>
                            <td>
                                <StatusBadge :status="status(invoice.status)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="grid gap-3 border-t bg-slate-50 p-5"
                :class="canViewProfit ? 'sm:grid-cols-5' : 'sm:grid-cols-3'"
            >
                <div>
                    <span class="label">Total Tagihan</span
                    ><strong class="block text-lg">{{
                        money(totals.grand_total)
                    }}</strong>
                </div>
                <div v-if="canViewProfit">
                    <span class="label">Total Komisi</span
                    ><strong class="block text-lg text-amber-600">{{
                        money(totals.commission_total || 0)
                    }}</strong>
                </div>
                <div v-if="canViewProfit">
                    <span class="label">Total Margin</span
                    ><strong class="block text-lg text-emerald-600">{{
                        money(totals.gross_profit_total || 0)
                    }}</strong
                    ><span class="text-xs text-slate-500">{{
                        marginRate(
                            totals.gross_profit_total || 0,
                            totals.profit_base_total || 0,
                        )
                    }}</span>
                </div>
                <div>
                    <span class="label">Total Terbayar</span
                    ><strong class="block text-lg text-emerald-600">{{
                        money(totals.paid_total)
                    }}</strong>
                </div>
                <div>
                    <span class="label">Total Sisa</span
                    ><strong class="block text-xl text-red-600">{{
                        money(totals.remaining_total)
                    }}</strong>
                </div>
            </div>
        </section>

        <section v-if="payments.length" class="panel mt-5">
            <div class="border-b p-5">
                <h2 class="text-lg font-bold">Riwayat Pembayaran Faktur</h2>
                <p class="text-sm text-slate-500">
                    Koreksi pembayaran akan otomatis memperbarui saldo invoice,
                    status Faktur, dan Cash Masuk.
                </p>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[850px]">
                    <thead>
                        <tr>
                            <th>Nomor Pembayaran</th>
                            <th>Invoice</th>
                            <th>Tanggal</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Referensi</th>
                            <th v-if="canManagePayments">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in payments" :key="row.id">
                            <td class="font-semibold text-sky-600">
                                {{ row.payment_number }}
                            </td>
                            <td>{{ row.invoice.invoice_number }}</td>
                            <td>{{ date(row.payment_date) }}</td>
                            <td class="font-semibold">{{ money(row.amount) }}</td>
                            <td>{{ row.payment_method }}</td>
                            <td>{{ row.reference_number || "-" }}</td>
                            <td v-if="canManagePayments">
                                <AppButton
                                    variant="secondary"
                                    @click="openPaymentCorrection(row)"
                                    >Koreksi</AppButton
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <AppModal
            :show="dueDateOpen"
            title="Pengaturan Jatuh Tempo Faktur"
            @close="dueDateOpen = false"
            ><form class="space-y-5" @submit.prevent="submitDueDate">
                <label
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4"
                    ><input
                        v-model="dueDateForm.use_due_date"
                        type="checkbox"
                        class="h-5 w-5 rounded border-slate-300 text-sky-600"
                    /><span
                        ><strong class="block">Pakai Tanggal Jatuh Tempo</strong
                        ><small class="text-slate-500"
                            >Nonaktifkan jika Faktur tidak memiliki batas
                            pembayaran.</small
                        ></span
                    ></label
                ><label v-if="dueDateForm.use_due_date" class="block"
                    ><span class="label">Tanggal Jatuh Tempo *</span
                    ><AppInput
                        v-model="dueDateForm.due_date"
                        type="date"
                        required
                /></label>
                <p
                    v-if="Object.keys(dueDateForm.errors).length"
                    class="text-sm text-red-600"
                >
                    {{ Object.values(dueDateForm.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2">
                    <AppButton variant="secondary" @click="dueDateOpen = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="dueDateForm.processing"
                        >Simpan</AppButton
                    >
                </div>
            </form></AppModal
        >

        <AppModal
            :show="paymentOpen"
            title="Bayar Faktur"
            @close="paymentOpen = false"
            ><form
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submitPayment"
            >
                <div class="rounded-xl bg-sky-50 p-4 text-sm sm:col-span-2">
                    <div class="flex justify-between">
                        <span>Nomor Faktur</span
                        ><strong>{{ document.facture_number }}</strong>
                    </div>
                    <div class="mt-2 flex justify-between">
                        <span>Total Sisa Faktur</span
                        ><strong class="text-red-600">{{
                            money(totals.remaining_total)
                        }}</strong>
                    </div>
                    <p class="mt-2 text-xs text-slate-600">
                        Nilai pembayaran tidak boleh melebihi total sisa Faktur.
                    </p>
                </div>
                <label
                    ><span class="label">Tanggal Pembayaran *</span
                    ><AppInput
                        v-model="payment.payment_date"
                        type="date"
                        required /></label
                ><label
                    ><span class="label">Nilai Pembayaran Faktur *</span
                    ><AppInput
                        v-model="payment.amount"
                        type="number"
                        min="1"
                        :max="totals.remaining_total"
                        required /></label
                ><label
                    ><span class="label">Jenis Pembayaran Faktur *</span
                    ><AppSelect v-model="payment.payment_method"
                        ><option value="transfer">Transfer bank</option>
                        <option value="cash">Tunai</option>
                        <option value="card">Kartu</option>
                        <option value="qris">QRIS</option>
                        <option value="virtual_account">Virtual account</option>
                        <option value="other">Lainnya</option></AppSelect
                    ></label
                ><label
                    ><span class="label">Bank Pembayaran Faktur</span
                    ><AppInput v-model="payment.bank_name" /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Nomor Referensi</span
                    ><AppInput v-model="payment.reference_number" /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Bukti Pembayaran Faktur</span
                    ><input
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="mt-1 block w-full rounded-lg border border-slate-300 p-2 text-sm"
                        @change="
                            payment.payment_proof =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] || null
                        " /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Keterangan Pembayaran Faktur</span
                    ><AppTextarea v-model="payment.notes"
                /></label>
                <div
                    class="grid gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2 sm:grid-cols-2"
                >
                    <label
                        class="flex cursor-pointer items-center gap-3 sm:col-span-2"
                        ><input
                            v-model="payment.commission_enabled"
                            type="checkbox"
                            class="h-5 w-5 rounded border-amber-300 text-amber-600"
                        /><span
                            ><strong class="block text-amber-900"
                                >Catat Komisi Faktur</strong
                            ><small class="text-amber-700"
                                >Komisi disimpan sebagai belum dibayar dan belum
                                masuk Kas Keluar.</small
                            ></span
                        ></label
                    ><template v-if="payment.commission_enabled"
                        ><label
                            ><span class="label">Komisi Diambil Dari *</span
                            ><AppSelect v-model="payment.commission_base"
                                ><option value="facture_total">
                                    Total Faktur
                                </option>
                                <option value="margin">
                                    Total Margin
                                </option></AppSelect
                            ></label
                        ><label
                            ><span class="label">Tipe Komisi *</span
                            ><AppSelect v-model="payment.commission_type"
                                ><option value="nominal">Rupiah</option>
                                <option value="percentage">
                                    Persentase
                                </option></AppSelect
                            ></label
                        ><label
                            ><span class="label"
                                >{{
                                    payment.commission_type === "percentage"
                                        ? "Persentase Komisi (%)"
                                        : "Nominal Komisi (Rp)"
                                }}
                                *</span
                            ><AppInput
                                v-model="payment.commission_value"
                                type="number"
                                :min="payment.commission_type === 'percentage' ? 1 : 0.01"
                                :max="payment.commission_type === 'percentage' ? 100 : undefined"
                                :step="payment.commission_type === 'percentage' ? 1 : 0.01"
                                required
                        /></label>
                        <div class="rounded-lg bg-white/70 p-3 text-sm">
                            <div class="flex justify-between">
                                <span>Dasar</span
                                ><strong>{{
                                    money(commissionBaseAmount)
                                }}</strong>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span>Jumlah Komisi</span
                                ><strong>{{ money(commissionAmount) }}</strong>
                            </div>
                            <div
                                class="mt-2 flex justify-between border-t border-amber-200 pt-2"
                            >
                                <span>Hasil Akhir Margin</span
                                ><strong
                                    :class="
                                        finalMargin >= 0
                                            ? 'text-emerald-700'
                                            : 'text-red-700'
                                    "
                                    >{{ money(finalMargin) }}</strong
                                >
                            </div>
                        </div>
                        <div
                            v-if="commissionWarning"
                            class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-700 sm:col-span-2"
                        >
                            Peringatan: jumlah komisi lebih besar dari
                            {{ percentageText(commissionWarningPercentage) }} total margin
                            Faktur ({{ money(commissionWarningLimit) }}).
                        </div>
                        <label class="sm:col-span-2"
                            ><span class="label">Keterangan Komisi</span
                            ><AppTextarea
                                v-model="payment.commission_notes" /></label
                    ></template>
                </div>
                <p
                    v-if="Object.keys(payment.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(payment.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="paymentOpen = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="payment.processing"
                        >Simpan Pembayaran</AppButton
                    >
                </div>
            </form></AppModal
        >

        <AppModal
            :show="editPaymentOpen"
            title="Koreksi Pembayaran Faktur"
            @close="editPaymentOpen = false"
        >
            <form
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submitPaymentCorrection"
            >
                <div class="rounded-xl bg-amber-50 p-4 text-sm sm:col-span-2">
                    <div class="flex justify-between gap-4">
                        <span>Nomor Pembayaran</span>
                        <strong>{{ editingPayment?.payment_number }}</strong>
                    </div>
                    <div class="mt-2 flex justify-between gap-4">
                        <span>Alokasi Invoice</span>
                        <strong>{{ editingPayment?.invoice.invoice_number }}</strong>
                    </div>
                </div>
                <label
                    ><span class="label">Tanggal Pembayaran *</span
                    ><AppInput
                        v-model="editPaymentForm.payment_date"
                        type="date"
                        required
                /></label>
                <label
                    ><span class="label">Nominal Pembayaran *</span
                    ><AppInput
                        v-model="editPaymentForm.amount"
                        type="number"
                        min="1"
                        required
                /></label>
                <label
                    ><span class="label">Metode Pembayaran *</span
                    ><AppSelect v-model="editPaymentForm.payment_method"
                        ><option value="transfer">Transfer bank</option>
                        <option value="cash">Tunai</option>
                        <option value="card">Kartu</option>
                        <option value="qris">QRIS</option>
                        <option value="virtual_account">Virtual account</option>
                        <option value="other">Lainnya</option></AppSelect
                    ></label
                >
                <label
                    ><span class="label">Bank</span
                    ><AppInput v-model="editPaymentForm.bank_name"
                /></label>
                <label class="sm:col-span-2"
                    ><span class="label">Nomor Referensi</span
                    ><AppInput v-model="editPaymentForm.reference_number"
                /></label>
                <label class="sm:col-span-2"
                    ><span class="label">Keterangan</span
                    ><AppTextarea v-model="editPaymentForm.notes"
                /></label>
                <p
                    v-if="Object.keys(editPaymentForm.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(editPaymentForm.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton
                        variant="secondary"
                        @click="editPaymentOpen = false"
                        >Batal</AppButton
                    ><AppButton
                        type="submit"
                        :disabled="editPaymentForm.processing"
                        >Simpan Koreksi</AppButton
                    >
                </div>
            </form>
        </AppModal>
    </AuthenticatedLayout>
</template>
