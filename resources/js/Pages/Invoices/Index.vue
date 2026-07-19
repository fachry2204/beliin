<script setup lang="ts">
import { ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import { percentageText } from "@/utils/percentage";

interface Row {
    id: number;
    invoice_number: string;
    purchase_order_number?: string | null;
    invoice_date: string;
    due_date: string;
    subtotal: string;
    discount_amount: string;
    grand_total: string;
    gross_profit?: string;
    paid_amount: string;
    remaining_amount: string;
    status: string | { value: string };
    delivery?: { status: string } | null;
    customer: { name: string; company_name?: string };
}
interface Page {
    data: Row[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
}

defineProps<{
    rows: Page;
    statuses: string[];
    canViewProfit: boolean;
}>();
const search = ref(new URLSearchParams(location.search).get("search") ?? "");
const status = ref(new URLSearchParams(location.search).get("status") ?? "");
let timer: number;
watch([search, status], () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () =>
            router.get(
                route("invoices.index"),
                { search: search.value, status: status.value },
                { preserveState: true, replace: true },
            ),
        300,
    );
});
const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const marginRate = (row: Row) => {
    const revenue = Number(row.subtotal) - Number(row.discount_amount || 0);
    const rate =
        revenue > 0 ? (Number(row.gross_profit || 0) / revenue) * 100 : 0;
    return percentageText(rate);
};
const statusValue = (value: Row["status"]) =>
    typeof value === "string" ? value : value.value;
const courierStatusLabel = (delivery?: Row["delivery"]) => {
    if (!delivery) return "Belum Ditugaskan";

    return {
        pending: "Tugas Belum Diambil",
        accepted: "Tugas Diambil",
        in_transit: "Dalam Pengantaran",
        delivered: "Selesai",
        cancelled: "Dibatalkan",
    }[delivery.status] ?? delivery.status.replaceAll("_", " ");
};
const courierStatusClass = (delivery?: Row["delivery"]) => {
    if (!delivery) return "bg-slate-100 text-slate-600";
    if (delivery.status === "delivered")
        return "bg-emerald-100 text-emerald-700";
    if (delivery.status === "in_transit")
        return "bg-amber-100 text-amber-700";
    if (delivery.status === "cancelled") return "bg-red-100 text-red-700";
    if (delivery.status === "accepted") return "bg-sky-100 text-sky-700";

    return "bg-violet-100 text-violet-700";
};
</script>

<template>
    <Head title="Semua Invoice" />
    <AuthenticatedLayout>
        <template #breadcrumb>Transaksi / Semua Invoice</template>
        <div
            class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <h1 class="page-title">Semua Invoice</h1>
                <p class="page-subtitle">
                    Pantau status penerbitan, pembayaran, dan jatuh tempo.
                </p>
            </div>
            <Link :href="route('invoices.create')">
                <AppButton>＋ Buat Invoice</AppButton>
            </Link>
        </div>
        <section class="panel">
            <div
                class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[1fr_220px]"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Cari nomor atau pelanggan..."
                />
                <AppSelect v-model="status">
                    <option value="">Semua status</option>
                    <option v-for="item in statuses" :key="item" :value="item">
                        {{ item.replaceAll("_", " ") }}
                    </option>
                </AppSelect>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor Invoice</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Subtotal</th>
                            <th>Grand Total</th>
                            <th v-if="canViewProfit">Margin</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="row.id">
                            <td>
                                <Link
                                    :href="route('invoices.show', row.id)"
                                    class="font-semibold text-sky-600 hover:underline"
                                >
                                    {{ row.invoice_number }}
                                </Link>
                                <div class="mt-1 text-xs text-slate-400">
                                    PO: {{ row.purchase_order_number || "-" }}
                                </div>
                            </td>
                            <td>
                                {{
                                    new Date(
                                        row.invoice_date,
                                    ).toLocaleDateString("id-ID")
                                }}
                            </td>
                            <td>
                                {{
                                    row.customer.company_name ||
                                    row.customer.name
                                }}
                            </td>
                            <td>{{ money(row.subtotal) }}</td>
                            <td class="font-semibold">
                                {{ money(row.grand_total) }}
                            </td>
                            <td v-if="canViewProfit">
                                <div class="font-semibold text-emerald-600">
                                    {{ money(row.gross_profit || 0) }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ marginRate(row) }}
                                </div>
                            </td>
                            <td>{{ money(row.paid_amount) }}</td>
                            <td>{{ money(row.remaining_amount) }}</td>
                            <td>
                                <StatusBadge
                                    :status="statusValue(row.status)"
                                />
                                <div class="mt-2">
                                    <span
                                        class="inline-flex rounded-md px-2 py-1 text-[10px] font-semibold"
                                        :class="courierStatusClass(row.delivery)"
                                    >
                                        Kurir:
                                        {{ courierStatusLabel(row.delivery) }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <Link
                                        :href="route('invoices.show', row.id)"
                                        class="rounded border px-2 py-1 text-xs"
                                    >
                                        Lihat
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                :colspan="canViewProfit ? 10 : 9"
                                class="py-12 text-center text-slate-500"
                            >
                                Belum ada invoice.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t p-4">
                <span class="text-xs text-slate-500">
                    {{ rows.from ?? 0 }}–{{ rows.to ?? 0 }} dari
                    {{ rows.total }}
                </span>
                <Pagination :links="rows.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
