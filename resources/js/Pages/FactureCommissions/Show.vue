<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppTextarea from "@/Components/UI/AppTextarea.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import { percentageText } from "@/utils/percentage";

interface Item {
    id: number;
    product_name_snapshot: string;
    unit_snapshot: string;
    quantity: string;
    purchase_price: string;
    selling_price: string;
    cost_total: string;
    line_subtotal: string;
    profit: string;
}
interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    grand_total: string;
    total_cost: string;
    gross_profit: string;
    items: Item[];
}
interface Commission {
    id: number;
    facture_payment_date: string;
    commission_base: string;
    commission_type: string;
    commission_value: string;
    base_amount: string;
    facture_total: string;
    margin_total: string;
    commission_amount: string;
    status: "unpaid" | "paid";
    notes?: string;
    paid_date?: string;
    payment_method?: string;
    payment_notes?: string;
}
const props = defineProps<{
    commission: Commission;
    document: { id: number; facture_number: string };
    customer: { name: string; company_name?: string };
    invoices: Invoice[];
    canPay: boolean;
    canManage: boolean;
    commissionWarningPercentage: number;
    today: string;
}>();
const payOpen = ref(false);
const editOpen = ref(false);
const payment = useForm({
    paid_date: props.today,
    payment_method: "transfer",
    payment_notes: "",
});
const submit = () =>
    payment.post(route("facture-commissions.pay", props.commission.id), {
        onSuccess: () => (payOpen.value = false),
    });
const editForm = useForm({
    facture_payment_date: props.commission.facture_payment_date.slice(0, 10),
    commission_base: props.commission.commission_base,
    commission_type: props.commission.commission_type,
    commission_value:
        props.commission.commission_type === "percentage"
            ? String(Math.round(Number(props.commission.commission_value)))
            : props.commission.commission_value,
    notes: props.commission.notes || "",
    paid_date: props.commission.paid_date?.slice(0, 10) || "",
    payment_method: props.commission.payment_method || "transfer",
    payment_notes: props.commission.payment_notes || "",
});
const submitEdit = () =>
    editForm.put(route("facture-commissions.update", props.commission.id), {
        onSuccess: () => (editOpen.value = false),
    });
const remove = () => {
    if (
        confirm(
            `Hapus komisi Faktur ${props.document.facture_number}? Cash Keluar terkait juga akan dihapus.`,
        )
    ) {
        router.delete(
            route("facture-commissions.destroy", props.commission.id),
        );
    }
};
const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const date = (value?: string) =>
    value ? new Date(value).toLocaleDateString("id-ID") : "-";
const netMargin = computed(
    () =>
        Number(props.commission.margin_total) -
        Number(props.commission.commission_amount),
);
const warning = computed(
    () =>
        Number(props.commission.margin_total) > 0 &&
        Number(props.commission.commission_amount) >
            (Number(props.commission.margin_total) *
                props.commissionWarningPercentage) /
                100,
);
</script>

<template>
    <Head :title="`Komisi ${document.facture_number}`" />
    <AuthenticatedLayout>
        <template #breadcrumb
            >Faktur / Komisi Faktur / {{ document.facture_number }}</template
        >
        <div
            class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">Detail Komisi Faktur</h1>
                <p class="page-subtitle">
                    {{ document.facture_number }} ·
                    {{ customer.company_name || customer.name }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('facture-commissions.index')"
                    ><AppButton variant="secondary">Kembali</AppButton></Link
                ><AppButton v-if="canPay" @click="payOpen = true"
                    >Bayar Komisi</AppButton
                ><AppButton
                    v-if="canManage"
                    variant="secondary"
                    @click="editOpen = true"
                    >Edit Komisi</AppButton
                ><AppButton v-if="canManage" variant="danger" @click="remove"
                    >Hapus Komisi</AppButton
                >
            </div>
        </div>
        <section class="panel mb-5 p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="label">Tanggal Pembayaran Faktur</span
                    ><strong class="block">{{
                        date(commission.facture_payment_date)
                    }}</strong>
                </div>
                <div>
                    <span class="label">Dasar Komisi</span
                    ><strong class="block">{{
                        commission.commission_base === "margin"
                            ? "Total Margin"
                            : "Total Faktur"
                    }}</strong
                    ><span class="text-sm text-slate-500">{{
                        money(commission.base_amount)
                    }}</span>
                </div>
                <div>
                    <span class="label">Perhitungan</span
                    ><strong class="block">{{
                        commission.commission_type === "percentage"
                            ? percentageText(commission.commission_value)
                            : "Nominal Rupiah"
                    }}</strong>
                </div>
                <div>
                    <span class="label">Status</span
                    ><span
                        class="inline-block rounded-full px-2 py-1 text-xs font-semibold"
                        :class="
                            commission.status === 'paid'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-amber-100 text-amber-700'
                        "
                        >{{
                            commission.status === "paid"
                                ? "Sudah Dibayar"
                                : "Belum Dibayar"
                        }}</span
                    >
                </div>
            </div>
            <p
                v-if="commission.notes"
                class="mt-4 border-t pt-4 text-sm text-slate-600"
            >
                {{ commission.notes }}
            </p>
        </section>
        <div
            v-if="warning"
            class="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 font-semibold text-red-700"
        >
            Peringatan: komisi lebih besar dari
            {{ percentageText(commissionWarningPercentage) }} total margin Faktur ({ money(
            (Number(commission.margin_total) * commissionWarningPercentage) /
            100, ) }}).
        </div>
        <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="panel p-5">
                <span class="label">Total Faktur</span
                ><strong class="block text-xl">{{
                    money(commission.facture_total)
                }}</strong>
            </div>
            <div class="panel p-5">
                <span class="label">Total Margin</span
                ><strong class="block text-xl text-emerald-600">{{
                    money(commission.margin_total)
                }}</strong>
            </div>
            <div class="panel p-5">
                <span class="label">Jumlah Komisi</span
                ><strong class="block text-xl text-amber-700">{{
                    money(commission.commission_amount)
                }}</strong>
            </div>
            <div class="panel p-5">
                <span class="label">Hasil Akhir Margin</span
                ><strong
                    class="block text-xl"
                    :class="netMargin >= 0 ? 'text-sky-700' : 'text-red-600'"
                    >{{ money(netMargin) }}</strong
                >
            </div>
        </div>
        <section class="panel">
            <div class="border-b p-5">
                <h2 class="font-bold">
                    Rincian Harga Modal, Harga Jual, dan Margin
                </h2>
            </div>
            <div
                v-for="invoice in invoices"
                :key="invoice.id"
                class="border-b last:border-b-0"
            >
                <div
                    class="flex flex-wrap justify-between gap-2 bg-slate-50 px-5 py-3"
                >
                    <Link
                        :href="route('invoices.show', invoice.id)"
                        class="font-bold text-sky-700"
                        >{{ invoice.invoice_number }}</Link
                    >
                    <div class="flex gap-5 text-sm">
                        <span
                            >Modal
                            <strong>{{
                                money(invoice.total_cost)
                            }}</strong></span
                        ><span
                            >Jual
                            <strong>{{
                                money(invoice.grand_total)
                            }}</strong></span
                        ><span
                            >Margin
                            <strong class="text-emerald-600">{{
                                money(invoice.gross_profit)
                            }}</strong></span
                        >
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table min-w-[850px]">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Qty</th>
                                <th>Harga Modal</th>
                                <th>Harga Jual</th>
                                <th>Total Modal</th>
                                <th>Total Jual</th>
                                <th>Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td>{{ item.product_name_snapshot }}</td>
                                <td>
                                    {{ Number(item.quantity) }}
                                    {{ item.unit_snapshot }}
                                </td>
                                <td>{{ money(item.purchase_price) }}</td>
                                <td>{{ money(item.selling_price) }}</td>
                                <td>{{ money(item.cost_total) }}</td>
                                <td>{{ money(item.line_subtotal) }}</td>
                                <td class="font-semibold text-emerald-600">
                                    {{ money(item.profit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <section v-if="commission.status === 'paid'" class="panel mt-5 p-5">
            <h2 class="mb-3 font-bold">Informasi Pembayaran Komisi</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <span class="label">Tanggal Dibayar</span
                    ><strong>{{ date(commission.paid_date) }}</strong>
                </div>
                <div>
                    <span class="label">Metode</span
                    ><strong class="capitalize">{{
                        commission.payment_method
                    }}</strong>
                </div>
                <div>
                    <span class="label">Keterangan</span
                    ><span>{{ commission.payment_notes || "-" }}</span>
                </div>
            </div>
        </section>
        <AppModal
            :show="payOpen"
            title="Bayar Komisi Faktur"
            @close="payOpen = false"
            ><form class="space-y-4" @submit.prevent="submit">
                <div class="rounded-xl bg-amber-50 p-4">
                    <div class="flex justify-between">
                        <span>Nomor Faktur</span
                        ><strong>{{ document.facture_number }}</strong>
                    </div>
                    <div class="mt-2 flex justify-between">
                        <span>Jumlah Komisi</span
                        ><strong class="text-amber-800">{{
                            money(commission.commission_amount)
                        }}</strong>
                    </div>
                    <p class="mt-2 text-xs text-amber-700">
                        Setelah disimpan, transaksi otomatis masuk Kas Keluar.
                    </p>
                </div>
                <label class="block"
                    ><span class="label">Tanggal Pembayaran *</span
                    ><AppInput
                        v-model="payment.paid_date"
                        type="date"
                        required /></label
                ><label class="block"
                    ><span class="label">Metode Pembayaran *</span
                    ><AppSelect v-model="payment.payment_method"
                        ><option value="transfer">Transfer bank</option>
                        <option value="cash">Tunai</option>
                        <option value="card">Kartu</option>
                        <option value="qris">QRIS</option>
                        <option value="virtual_account">Virtual account</option>
                        <option value="other">Lainnya</option></AppSelect
                    ></label
                ><label class="block"
                    ><span class="label">Keterangan</span
                    ><AppTextarea v-model="payment.payment_notes"
                /></label>
                <p
                    v-if="Object.keys(payment.errors).length"
                    class="text-sm text-red-600"
                >
                    {{ Object.values(payment.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2">
                    <AppButton variant="secondary" @click="payOpen = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="payment.processing"
                        >Bayar dan Catat Kas Keluar</AppButton
                    >
                </div>
            </form></AppModal
        >
        <AppModal
            :show="editOpen"
            title="Edit Komisi Faktur"
            @close="editOpen = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                <label class="sm:col-span-2"
                    ><span class="label">Tanggal Pembayaran Faktur *</span
                    ><AppInput
                        v-model="editForm.facture_payment_date"
                        type="date"
                        required
                /></label>
                <label
                    ><span class="label">Komisi Diambil Dari *</span
                    ><AppSelect v-model="editForm.commission_base"
                        ><option value="facture_total">Total Faktur</option>
                        <option value="margin">Total Margin</option></AppSelect
                    ></label
                >
                <label
                    ><span class="label">Tipe Komisi *</span
                    ><AppSelect v-model="editForm.commission_type"
                        ><option value="nominal">Rupiah</option>
                        <option value="percentage">Persentase</option></AppSelect
                    ></label
                >
                <label class="sm:col-span-2"
                    ><span class="label">Nilai Komisi *</span
                    ><AppInput
                        v-model="editForm.commission_value"
                        type="number"
                        :min="editForm.commission_type === 'percentage' ? 1 : 0.01"
                        :max="editForm.commission_type === 'percentage' ? 100 : undefined"
                        :step="editForm.commission_type === 'percentage' ? 1 : 0.01"
                        required
                /></label>
                <label class="sm:col-span-2"
                    ><span class="label">Keterangan Komisi</span
                    ><AppTextarea v-model="editForm.notes"
                /></label>
                <template v-if="commission.status === 'paid'">
                    <div
                        class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 sm:col-span-2"
                    >
                        Perubahan pembayaran di bawah ini akan otomatis
                        disinkronkan ke Cash Keluar.
                    </div>
                    <label
                        ><span class="label">Tanggal Dibayar *</span
                        ><AppInput
                            v-model="editForm.paid_date"
                            type="date"
                            required
                    /></label>
                    <label
                        ><span class="label">Metode Pembayaran *</span
                        ><AppSelect v-model="editForm.payment_method"
                            ><option value="transfer">Transfer bank</option>
                            <option value="cash">Tunai</option>
                            <option value="card">Kartu</option>
                            <option value="qris">QRIS</option>
                            <option value="virtual_account">
                                Virtual account
                            </option>
                            <option value="other">Lainnya</option></AppSelect
                        ></label
                    >
                    <label class="sm:col-span-2"
                        ><span class="label">Keterangan Pembayaran</span
                        ><AppTextarea v-model="editForm.payment_notes"
                    /></label>
                </template>
                <p
                    v-if="Object.keys(editForm.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(editForm.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="editOpen = false"
                        >Batal</AppButton
                    ><AppButton type="submit" :disabled="editForm.processing"
                        >Simpan Perubahan</AppButton
                    >
                </div>
            </form>
        </AppModal>
    </AuthenticatedLayout>
</template>
