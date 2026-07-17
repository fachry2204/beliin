<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import Pagination from "@/Components/UI/Pagination.vue";

interface Courier {
    id: number;
    courier_code: string;
    name: string;
    phone?: string;
    vehicle_type?: string;
    license_plate?: string;
    bank_name?: string;
    bank_account_number?: string;
    bank_account_name?: string;
    notes?: string;
}
interface Deposit {
    id: number;
    amount: string;
    created_at: string;
    paid_at?: string | null;
    invoice: {
        id: number;
        invoice_number: string;
        invoice_date: string;
        billing_name: string;
        billing_company?: string;
        delivery?: {
            id: number;
            status:
                | "pending"
                | "accepted"
                | "in_transit"
                | "delivered"
                | "cancelled";
        } | null;
    };
}
interface Delivery {
    id: number;
    status: string;
    delivered_at?: string;
    proof_url?: string;
    invoice: {
        id: number;
        invoice_number: string;
        billing_name: string;
        billing_company?: string;
    };
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
defineProps<{
    courier: Courier;
    deposits: {
        data: Deposit[];
        links: PageLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    unpaidTotal: string | number;
    paidTotal: string | number;
    deliveries: Delivery[];
    canManageCouriers: boolean;
}>();
const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const date = (value: string) => new Date(value).toLocaleDateString("id-ID");
const deliveryLabels: Record<string, string> = {
    pending: "Belum Diambil",
    accepted: "Tugas Diambil",
    in_transit: "Dalam Pengantaran",
    delivered: "Selesai",
    cancelled: "Dibatalkan",
};
const deliveryLabel = (deposit: Deposit) =>
    deliveryLabels[deposit.invoice.delivery?.status ?? ""] ?? "Belum Ada Tugas";
const deliveryTone = (deposit: Deposit) => {
    const status = deposit.invoice.delivery?.status;
    if (status === "delivered") return "bg-emerald-100 text-emerald-700";
    if (status === "in_transit") return "bg-sky-100 text-sky-700";
    if (status === "accepted") return "bg-indigo-100 text-indigo-700";
    if (status === "cancelled") return "bg-red-100 text-red-700";
    return "bg-amber-100 text-amber-700";
};
const pay = (courierId: number, deposit: Deposit) => {
    if (
        confirm(
            `Bayarkan ongkir ${money(deposit.amount)} untuk ${deposit.invoice.invoice_number}? Transaksi akan masuk ke Kas Keluar.`,
        )
    ) {
        router.post(
            route("couriers.shipping-deposits.pay", [courierId, deposit.id]),
        );
    }
};
</script>

<template>
    <Head :title="`Detail Kurir - ${courier.name}`" />
    <AuthenticatedLayout>
        <template #breadcrumb
            >Master Data / Kurir / {{ courier.name }}</template
        >
        <div
            class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">{{ courier.name }}</h1>
                <p class="page-subtitle">
                    Detail akun kurir, status pengiriman, dan riwayat pembayaran
                    ongkir.
                </p>
            </div>
            <Link :href="route('couriers.index')">
                <AppButton variant="secondary">Kembali ke Data Kurir</AppButton>
            </Link>
        </div>

        <div class="grid gap-5 lg:grid-cols-[320px_1fr]">
            <aside class="space-y-5">
                <section class="panel p-5">
                    <h2 class="mb-4 font-bold">Informasi Kurir</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Kode</dt>
                            <dd class="font-semibold">
                                {{ courier.courier_code }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Telepon</dt>
                            <dd>{{ courier.phone || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Kendaraan</dt>
                            <dd>{{ courier.vehicle_type || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">No. Polisi</dt>
                            <dd>{{ courier.license_plate || "-" }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="panel p-5">
                    <h2 class="mb-4 font-bold">Rekening Bank</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Nama Bank</dt>
                            <dd class="font-semibold">
                                {{ courier.bank_name || "-" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Nomor Rekening</dt>
                            <dd>{{ courier.bank_account_number || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Atas Nama Rekening</dt>
                            <dd>{{ courier.bank_account_name || "-" }}</dd>
                        </div>
                    </dl>
                </section>
                <section
                    class="rounded-xl bg-amber-50 p-5 ring-1 ring-amber-200"
                >
                    <p class="text-sm font-medium text-amber-700">
                        Total Ongkir Belum Dibayar
                    </p>
                    <p class="mt-2 text-2xl font-bold text-amber-800">
                        {{ money(unpaidTotal) }}
                    </p>
                </section>
                <section
                    class="rounded-xl bg-emerald-50 p-5 ring-1 ring-emerald-200"
                >
                    <p class="text-sm font-medium text-emerald-700">
                        Total Ongkir Sudah Dibayar
                    </p>
                    <p class="mt-2 text-2xl font-bold text-emerald-800">
                        {{ money(paidTotal) }}
                    </p>
                </section>
            </aside>

            <section class="panel">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-bold">Riwayat Ongkir Kurir</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ongkir yang sudah maupun belum dibayarkan kepada kurir.
                    </p>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal Invoice</th>
                                <th>Nomor Invoice</th>
                                <th>Pelanggan</th>
                                <th>Status</th>
                                <th class="text-right">Ongkir</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="deposit in deposits.data"
                                :key="deposit.id"
                            >
                                <td>
                                    {{ date(deposit.invoice.invoice_date) }}
                                </td>
                                <td>
                                    <Link
                                        class="font-semibold text-sky-600 hover:underline"
                                        :href="
                                            route(
                                                'invoices.show',
                                                deposit.invoice.id,
                                            )
                                        "
                                        >{{
                                            deposit.invoice.invoice_number
                                        }}</Link
                                    >
                                </td>
                                <td>
                                    {{
                                        deposit.invoice.billing_company ||
                                        deposit.invoice.billing_name
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="deliveryTone(deposit)"
                                        >{{ deliveryLabel(deposit) }}</span
                                    >
                                </td>
                                <td class="text-right font-semibold">
                                    {{ money(deposit.amount) }}
                                </td>
                                <td class="text-right">
                                    <AppButton
                                        v-if="canManageCouriers"
                                        class="whitespace-nowrap !px-3 !py-2"
                                        :variant="
                                            deposit.paid_at
                                                ? 'secondary'
                                                : 'primary'
                                        "
                                        :disabled="Boolean(deposit.paid_at)"
                                        :title="
                                            deposit.paid_at
                                                ? 'Ongkir sudah dibayarkan kepada kurir'
                                                : 'Bayarkan ongkir ke kurir'
                                        "
                                        @click="pay(courier.id, deposit)"
                                        >{{
                                            deposit.paid_at
                                                ? "Sudah Dibayar"
                                                : "Bayar"
                                        }}</AppButton
                                    >
                                </td>
                            </tr>
                            <tr v-if="!deposits.data.length">
                                <td
                                    colspan="6"
                                    class="py-12 text-center text-slate-500"
                                >
                                    Belum ada riwayat ongkir.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="flex items-center justify-between border-t border-slate-200 p-4"
                >
                    <span class="text-xs text-slate-500"
                        >{{ deposits.from ?? 0 }}-{{ deposits.to ?? 0 }} dari
                        {{ deposits.total }}</span
                    >
                    <Pagination :links="deposits.links" />
                </div>
            </section>
            <section class="panel lg:col-start-2">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-bold">Status Tugas Terakhir</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Pelanggan</th>
                                <th>Status</th>
                                <th>Selesai</th>
                                <th>Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="delivery in deliveries"
                                :key="delivery.id"
                            >
                                <td>
                                    <Link
                                        class="font-semibold text-sky-600"
                                        :href="
                                            route(
                                                'invoices.show',
                                                delivery.invoice.id,
                                            )
                                        "
                                        >{{
                                            delivery.invoice.invoice_number
                                        }}</Link
                                    >
                                </td>
                                <td>
                                    {{
                                        delivery.invoice.billing_company ||
                                        delivery.invoice.billing_name
                                    }}
                                </td>
                                <td class="capitalize">
                                    {{ delivery.status.replace("_", " ") }}
                                </td>
                                <td>
                                    {{
                                        delivery.delivered_at
                                            ? new Date(
                                                  delivery.delivered_at,
                                              ).toLocaleString("id-ID")
                                            : "-"
                                    }}
                                </td>
                                <td>
                                    <a
                                        v-if="delivery.proof_url"
                                        :href="delivery.proof_url"
                                        target="_blank"
                                        class="font-semibold text-sky-600"
                                        >Lihat Foto</a
                                    ><span v-else>-</span>
                                </td>
                            </tr>
                            <tr v-if="!deliveries.length">
                                <td
                                    colspan="5"
                                    class="py-8 text-center text-slate-500"
                                >
                                    Belum ada tugas pengiriman.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
