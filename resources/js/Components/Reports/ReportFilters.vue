<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import SearchInput from "@/Components/UI/SearchInput.vue";

interface Filters {
    search?: string;
    date_from?: string;
    date_to?: string;
    status?: string;
    type?: string;
}
const props = withDefaults(
    defineProps<{
        routeName: string;
        filters: Filters;
        searchPlaceholder?: string;
        showStatus?: boolean;
        showCashType?: boolean;
    }>(),
    { searchPlaceholder: "Cari data...", showStatus: false, showCashType: false },
);
const search = ref(props.filters.search || "");
const dateFrom = ref(props.filters.date_from || "");
const dateTo = ref(props.filters.date_to || "");
const status = ref(props.filters.status || "");
const type = ref(props.filters.type || "");
const iso = (date: Date) => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};
const navigate = () =>
    router.get(
        route(props.routeName),
        {
            search: search.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            status: props.showStatus ? status.value || undefined : undefined,
            type: props.showCashType ? type.value || undefined : undefined,
        },
        { preserveState: true, replace: true },
    );
const shortcut = (key: "today" | "week" | "month" | "last_month" | "year") => {
    const today = new Date();
    let start = new Date(today);
    let end = new Date(today);
    if (key === "week") start.setDate(today.getDate() - 6);
    if (key === "month") start = new Date(today.getFullYear(), today.getMonth(), 1);
    if (key === "last_month") {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    }
    if (key === "year") start = new Date(today.getFullYear(), 0, 1);
    dateFrom.value = iso(start);
    dateTo.value = iso(end);
    navigate();
};
const reset = () => {
    search.value = "";
    dateFrom.value = "";
    dateTo.value = "";
    status.value = "";
    type.value = "";
    navigate();
};
</script>

<template>
    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-[minmax(230px,1fr)_170px_170px_auto] lg:items-end" @submit.prevent="navigate">
            <label>
                <span class="label">Pencarian</span>
                <SearchInput v-model="search" :placeholder="searchPlaceholder" />
            </label>
            <label><span class="label">Dari Tanggal</span><AppInput v-model="dateFrom" type="date" /></label>
            <label><span class="label">Sampai Tanggal</span><AppInput v-model="dateTo" type="date" /></label>
            <div class="flex gap-2"><AppButton type="submit">Terapkan</AppButton><AppButton variant="secondary" @click="reset">Reset</AppButton></div>
            <label v-if="showStatus" class="lg:col-span-1">
                <span class="label">Status Invoice</span>
                <AppSelect v-model="status"><option value="">Semua status</option><option value="unpaid">Belum Dibayar</option><option value="partially_paid">Dibayar Sebagian</option><option value="paid">Lunas</option><option value="overdue">Jatuh Tempo</option></AppSelect>
            </label>
            <label v-if="showCashType" class="lg:col-span-1">
                <span class="label">Jenis Kas</span>
                <AppSelect v-model="type"><option value="">Kas masuk & keluar</option><option value="in">Kas Masuk</option><option value="out">Kas Keluar</option></AppSelect>
            </label>
        </form>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            <span class="mr-1 text-xs font-semibold text-slate-500">Shortcut rentang:</span>
            <button type="button" class="report-shortcut" @click="shortcut('today')">Hari Ini</button>
            <button type="button" class="report-shortcut" @click="shortcut('week')">7 Hari</button>
            <button type="button" class="report-shortcut" @click="shortcut('month')">Bulan Ini</button>
            <button type="button" class="report-shortcut" @click="shortcut('last_month')">Bulan Lalu</button>
            <button type="button" class="report-shortcut" @click="shortcut('year')">Tahun Ini</button>
        </div>
    </section>
</template>

<style scoped>
.report-shortcut { @apply rounded-lg border border-sky-200 bg-white px-3 py-1.5 text-xs font-semibold text-sky-600 transition hover:border-sky-400 hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-400; }
</style>
