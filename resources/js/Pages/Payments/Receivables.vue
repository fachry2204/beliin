<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import StatusBadge from "@/Components/UI/StatusBadge.vue";
import Pagination from "@/Components/UI/Pagination.vue";

interface Row {
    id: number;
    invoice_number: string;
    due_date: string;
    grand_total: string;
    paid_amount: string;
    remaining_amount: string;
    status: string | { value: string };
    customer: { name: string; company_name?: string };
}

interface Page {
    data: Row[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

defineProps<{
    rows: Page;
    totalReceivables: string | number;
}>();

const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const statusValue = (status: Row["status"]) =>
    typeof status === "string" ? status : status.value;
const lateDays = (date: string) =>
    Math.max(
        0,
        Math.floor(
            (Date.now() - new Date(`${date.slice(0, 10)}T00:00:00`).getTime()) /
                86400000,
        ),
    );
</script>

<template>
    <Head title="Piutang" />
    <AuthenticatedLayout>
        <template #breadcrumb>Keuangan / Piutang</template>

        <div
            class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="page-title">Piutang</h1>
                <p class="page-subtitle">Tagihan pelanggan yang belum lunas.</p>
            </div>
            <div
                class="min-w-[280px] rounded-xl border border-slate-200 bg-white px-6 py-4 shadow-sm"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                    Total Piutang
                </div>
                <div class="mt-1 text-2xl font-bold text-red-600">
                    {{ money(totalReceivables) }}
                </div>
            </div>
        </div>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Invoice</th>
                            <th>Jatuh Tempo</th>
                            <th>Grand Total</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
                            <th>Terlambat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows.data" :key="row.id">
                            <td>
                                {{
                                    row.customer.company_name ||
                                    row.customer.name
                                }}
                            </td>
                            <td>
                                <Link
                                    :href="route('invoices.show', row.id)"
                                    class="font-semibold text-sky-600"
                                >
                                    {{ row.invoice_number }}
                                </Link>
                            </td>
                            <td>
                                {{
                                    new Date(
                                        `${row.due_date.slice(0, 10)}T00:00:00`,
                                    ).toLocaleDateString("id-ID")
                                }}
                            </td>
                            <td>{{ money(row.grand_total) }}</td>
                            <td>{{ money(row.paid_amount) }}</td>
                            <td class="font-bold text-red-600">
                                {{ money(row.remaining_amount) }}
                            </td>
                            <td>{{ lateDays(row.due_date) }} hari</td>
                            <td>
                                <StatusBadge
                                    :status="statusValue(row.status)"
                                />
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                colspan="8"
                                class="py-12 text-center text-slate-500"
                            >
                                Tidak ada piutang.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t p-4">
                <Pagination :links="rows.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
