<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import { percentageText } from "@/utils/percentage";

interface CommissionRow {
    id: number;
    facture_payment_date: string;
    commission_base: "facture_total" | "margin";
    commission_type: "nominal" | "percentage";
    commission_value: string;
    commission_amount: string;
    margin_total: string;
    status: "unpaid" | "paid";
    document: {
        id: number;
        facture_number: string;
        customer: { name: string; company_name?: string };
    };
}
interface PageData {
    data: CommissionRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number;
    to: number;
    total: number;
}
const props = defineProps<{
    commissions: PageData;
    filters: { search: string; status: string };
    summary: { unpaid: string; paid: string };
}>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const filter = () =>
    router.get(
        route("facture-commissions.index"),
        { search: search.value, status: status.value },
        { preserveState: true },
    );
const money = (value: string | number) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value));
const date = (value: string) => new Date(value).toLocaleDateString("id-ID");
const calculation = (row: CommissionRow) =>
    row.commission_type === "percentage"
        ? `${percentageText(row.commission_value)} dari ${row.commission_base === "margin" ? "margin" : "total Faktur"}`
        : "Nominal rupiah";
</script>

<template>
    <Head title="Komisi Faktur" />
    <AuthenticatedLayout>
        <template #breadcrumb>Faktur / Komisi Faktur</template>
        <div class="mb-6">
            <h1 class="page-title">Komisi Faktur</h1>
            <p class="page-subtitle">
                Komisi baru masuk Kas Keluar setelah statusnya dibayar.
            </p>
        </div>
        <div class="mb-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <span class="text-sm font-semibold text-amber-700"
                    >Komisi Belum Dibayar</span
                ><strong class="mt-2 block text-2xl text-amber-800">{{
                    money(summary.unpaid)
                }}</strong>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <span class="text-sm font-semibold text-emerald-700"
                    >Komisi Sudah Dibayar</span
                ><strong class="mt-2 block text-2xl text-emerald-800">{{
                    money(summary.paid)
                }}</strong>
            </div>
        </div>
        <section class="panel">
            <form
                class="grid gap-3 border-b p-4 sm:grid-cols-[1fr_220px_auto]"
                @submit.prevent="filter"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Cari nomor Faktur atau pelanggan..."
                />
                <AppSelect v-model="status"
                    ><option value="">Semua status</option>
                    <option value="unpaid">Belum Dibayar</option>
                    <option value="paid">Sudah Dibayar</option></AppSelect
                >
                <AppButton type="submit">Cari</AppButton>
            </form>
            <div class="table-wrap">
                <table class="data-table min-w-[1050px]">
                    <thead>
                        <tr>
                            <th>Tanggal Pembayaran Faktur</th>
                            <th>Nomor Faktur</th>
                            <th>Pelanggan</th>
                            <th>Dasar</th>
                            <th>Perhitungan</th>
                            <th class="text-right">Jumlah Komisi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in commissions.data" :key="row.id">
                            <td>{{ date(row.facture_payment_date) }}</td>
                            <td class="font-semibold text-sky-700">
                                {{ row.document.facture_number }}
                            </td>
                            <td>
                                {{
                                    row.document.customer.company_name ||
                                    row.document.customer.name
                                }}
                            </td>
                            <td>
                                {{
                                    row.commission_base === "margin"
                                        ? "Total Margin"
                                        : "Total Faktur"
                                }}
                            </td>
                            <td>{{ calculation(row) }}</td>
                            <td class="text-right font-bold">
                                {{ money(row.commission_amount) }}
                            </td>
                            <td>
                                <span
                                    class="whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="
                                        row.status === 'paid'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-amber-100 text-amber-700'
                                    "
                                    >{{
                                        row.status === "paid"
                                            ? "Sudah Dibayar"
                                            : "Belum Dibayar"
                                    }}</span
                                >
                            </td>
                            <td>
                                <Link
                                    :href="
                                        route(
                                            'facture-commissions.show',
                                            row.id,
                                        )
                                    "
                                    ><AppButton variant="secondary"
                                        >Lihat Detail</AppButton
                                    ></Link
                                >
                            </td>
                        </tr>
                        <tr v-if="!commissions.data.length">
                            <td
                                colspan="8"
                                class="py-12 text-center text-slate-500"
                            >
                                Belum ada Komisi Faktur.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t p-4">
                <span class="text-xs text-slate-500"
                    >{{ commissions.from ?? 0 }}–{{ commissions.to ?? 0 }} dari
                    {{ commissions.total }}</span
                ><Pagination :links="commissions.links" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
