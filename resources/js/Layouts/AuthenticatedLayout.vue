<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminNavIcon from "@/Components/AdminNavIcon.vue";

interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}
interface PageProps extends Record<string, unknown> {
    auth: { user: AuthUser };
    company: { name: string; logo_url?: string | null };
    flash: { success?: string; error?: string };
}
interface NavItem {
    label: string;
    routeName?: string;
    icon: string;
    permission: string;
    children?: NavItem[];
}

const page = usePage<PageProps>();
const collapsed = ref(false);
const mobileOpen = ref(false);
const profileOpen = ref(false);
const expanded = ref<Record<string, boolean>>({
    Invoice: route().current("invoices.*"),
    Faktur:
        route().current("combined-invoices.*") ||
        route().current("facture-commissions.*"),
    Keuangan:
        route().current("payments.*") ||
        route().current("receivables.*") ||
        route().current("cash-in.*") ||
        route().current("cash-out.*"),
});
const flash = computed(() => page.props.flash);
const groups: { label: string; items: NavItem[] }[] = [
    {
        label: "",
        items: [
            {
                label: "Dashboard",
                routeName: "dashboard",
                icon: "dashboard",
                permission: "dashboard.view",
            },
        ],
    },
    {
        label: "MASTER DATA",
        items: [
            {
                label: "Pelanggan",
                routeName: "customers.index",
                icon: "customer",
                permission: "customers.view",
            },
            {
                label: "Barang",
                routeName: "products.index",
                icon: "box",
                permission: "products.view",
            },
            {
                label: "Kategori",
                routeName: "categories.index",
                icon: "category",
                permission: "products.view",
            },
        ],
    },
    {
        label: "KURIR",
        items: [
            {
                label: "Data Kurir",
                routeName: "couriers.index",
                icon: "courier",
                permission: "couriers.view",
            },
            {
                label: "Map Kurir",
                routeName: "couriers.map",
                icon: "map",
                permission: "couriers.map",
            },
        ],
    },
    {
        label: "TRANSAKSI",
        items: [
            {
                label: "Invoice",
                icon: "invoice",
                permission: "invoices.view",
                children: [
                    {
                        label: "Buat Invoice",
                        routeName: "invoices.create",
                        icon: "invoice",
                        permission: "invoices.create",
                    },
                    {
                        label: "Semua Invoice",
                        routeName: "invoices.index",
                        icon: "invoice",
                        permission: "invoices.view",
                    },
                ],
            },
            {
                label: "Faktur",
                icon: "facture",
                permission: "invoices.view",
                children: [
                    {
                        label: "Buat Faktur",
                        routeName: "combined-invoices.create",
                        icon: "facture",
                        permission: "invoices.create",
                    },
                    {
                        label: "Semua Faktur",
                        routeName: "combined-invoices.index",
                        icon: "facture",
                        permission: "invoices.view",
                    },
                    {
                        label: "Komisi Faktur",
                        routeName: "facture-commissions.index",
                        icon: "cash-out",
                        permission: "profit.view",
                    },
                ],
            },
            {
                label: "Keuangan",
                icon: "payment",
                permission: "payments.view",
                children: [
                    {
                        label: "Pembayaran",
                        routeName: "payments.index",
                        icon: "payment",
                        permission: "payments.view",
                    },
                    {
                        label: "Piutang",
                        routeName: "receivables.index",
                        icon: "receivable",
                        permission: "payments.view",
                    },
                    {
                        label: "Kas Masuk",
                        routeName: "cash-in.index",
                        icon: "cash-in",
                        permission: "cash.view",
                    },
                    {
                        label: "Kas Keluar",
                        routeName: "cash-out.index",
                        icon: "cash-out",
                        permission: "cash.view",
                    },
                ],
            },
        ],
    },
    {
        label: "LAPORAN",
        items: [
            {
                label: "Laporan",
                routeName: "reports.index",
                icon: "report",
                permission: "reports.view",
            },
        ],
    },
    {
        label: "PENGATURAN",
        items: [
            {
                label: "Perusahaan",
                routeName: "company.edit",
                icon: "company",
                permission: "settings.manage",
            },
            {
                label: "Pengguna",
                routeName: "users.index",
                icon: "user",
                permission: "users.manage",
            },
            {
                label: "Audit Log",
                routeName: "activity.index",
                icon: "audit",
                permission: "audit.view",
            },
        ],
    },
];
const can = (permission: string) =>
    page.props.auth.user.permissions.includes(permission);
const active = (item: NavItem) => {
    if (item.children) return item.children.some(active);
    if (!item.routeName) return false;
    if (item.routeName === "couriers.index") {
        return (
            route().current("couriers.index") ||
            route().current("couriers.show")
        );
    }
    return (
        route().current(item.routeName.replace(".index", "") + ".*") ||
        route().current(item.routeName)
    );
};
const visible = (item: NavItem) =>
    item.children
        ? item.children.some((child) => can(child.permission))
        : can(item.permission);
const logout = () => router.post(route("logout"));
</script>

<template>
    <div class="min-h-screen bg-[#f5f6fa] text-[#111827]">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[1px] xl:hidden"
            @click="mobileOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-[#e5e7eb] bg-white transition-all duration-300"
            :class="[
                collapsed ? 'w-[86px]' : 'w-[275px]',
                mobileOpen
                    ? 'translate-x-0'
                    : '-translate-x-full xl:translate-x-0',
            ]"
        >
            <button
                type="button"
                class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 xl:hidden"
                aria-label="Tutup menu"
                @click="mobileOpen = false"
            >
                <svg
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>

            <Link
                :href="route('dashboard')"
                class="flex h-[72px] items-center gap-3 border-b border-slate-200 px-5"
            >
                <img
                    v-if="page.props.company.logo_url"
                    :src="page.props.company.logo_url"
                    :alt="`Logo ${page.props.company.name}`"
                    class="h-10 w-10 shrink-0 rounded-lg object-contain"
                />
                <span
                    v-else
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#487fff] font-bold text-white shadow-sm"
                    >{{ page.props.company.name.charAt(0).toUpperCase() }}</span
                >
                <span
                    v-if="!collapsed"
                    class="min-w-0 truncate text-xl font-bold tracking-tight text-[#1f2937]"
                    :title="page.props.company.name"
                    >{{ page.props.company.name }}</span
                >
            </Link>

            <nav class="wow-scrollbar flex-1 overflow-y-auto px-4 py-3">
                <template v-for="group in groups" :key="group.label">
                    <p
                        v-if="group.label && !collapsed"
                        class="mb-2 mt-5 px-3 text-sm font-semibold text-[#6b7280]"
                    >
                        {{ group.label }}
                    </p>
                    <div class="space-y-0.5">
                        <template
                            v-for="item in group.items.filter(visible)"
                            :key="item.label"
                        >
                            <div v-if="item.children">
                                <button
                                    type="button"
                                    :title="item.label"
                                    class="group flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                                    :class="
                                        active(item)
                                            ? 'bg-[#edf3ff] text-[#487fff]'
                                            : 'text-[#4b5563] hover:bg-[#edf3ff] hover:text-[#487fff]'
                                    "
                                    @click="
                                        collapsed
                                            ? (collapsed = false)
                                            : (expanded[item.label] =
                                                  !expanded[item.label])
                                    "
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center"
                                        ><AdminNavIcon
                                            :name="item.icon"
                                            class="h-[21px] w-[21px]"
                                    /></span>
                                    <span
                                        v-if="!collapsed"
                                        class="flex-1 text-left"
                                        >{{ item.label }}</span
                                    >
                                    <svg
                                        v-if="!collapsed"
                                        class="h-4 w-4 transition-transform"
                                        :class="
                                            expanded[item.label]
                                                ? 'rotate-180'
                                                : ''
                                        "
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div
                                    v-if="!collapsed && expanded[item.label]"
                                    class="ml-9 mt-1 space-y-0.5 border-l border-slate-200 pl-2"
                                >
                                    <Link
                                        v-for="child in item.children.filter(
                                            (entry) => can(entry.permission),
                                        )"
                                        :key="child.routeName"
                                        :href="route(child.routeName!)"
                                        class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                                        :class="
                                            active(child)
                                                ? 'bg-[#487fff] text-white shadow-[0_4px_10px_rgba(72,127,255,.2)]'
                                                : 'text-[#64748b] hover:bg-[#edf3ff] hover:text-[#487fff]'
                                        "
                                        @click="mobileOpen = false"
                                        >{{ child.label }}</Link
                                    >
                                </div>
                            </div>
                            <Link
                                v-else
                                :href="route(item.routeName!)"
                                :title="item.label"
                                class="group flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    active(item)
                                        ? 'bg-[#487fff] text-white shadow-[0_5px_12px_rgba(72,127,255,.24)]'
                                        : 'text-[#4b5563] hover:bg-[#edf3ff] hover:text-[#487fff]'
                                "
                                @click="mobileOpen = false"
                            >
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center"
                                    ><AdminNavIcon
                                        :name="item.icon"
                                        class="h-[21px] w-[21px]"
                                /></span>
                                <span v-if="!collapsed">{{ item.label }}</span>
                            </Link>
                        </template>
                    </div>
                </template>
            </nav>

            <button
                class="hidden h-14 items-center gap-3 border-t border-slate-200 px-6 text-sm font-medium text-slate-500 hover:bg-slate-50 hover:text-[#487fff] xl:flex"
                @click="collapsed = !collapsed"
            >
                <svg
                    class="h-5 w-5 transition-transform"
                    :class="collapsed ? 'rotate-180' : ''"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="m15 18-6-6 6-6" />
                </svg>
                <span v-if="!collapsed">Ciutkan menu</span>
            </button>
        </aside>

        <div
            class="flex min-h-screen flex-col transition-all duration-300"
            :class="collapsed ? 'xl:pl-[86px]' : 'xl:pl-[275px]'"
        >
            <header
                class="sticky top-0 z-30 flex h-[72px] items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <button
                        class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100"
                        aria-label="Buka menu"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg
                            class="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div
                        class="hidden text-sm font-medium text-slate-500 sm:block"
                    >
                        <slot name="breadcrumb">Dashboard</slot>
                    </div>
                </div>

                <div class="relative">
                    <button
                        class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50"
                        @click="profileOpen = !profileOpen"
                    >
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#edf3ff] font-bold text-[#487fff]"
                            >{{
                                page.props.auth.user.name
                                    .charAt(0)
                                    .toUpperCase()
                            }}</span
                        >
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold">{{
                                page.props.auth.user.name
                            }}</span>
                            <span
                                class="block text-xs capitalize text-slate-500"
                                >{{ page.props.auth.user.roles[0] }}</span
                            >
                        </span>
                        <svg
                            class="h-4 w-4 text-slate-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div
                        v-if="profileOpen"
                        class="absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl"
                    >
                        <Link
                            :href="route('profile.edit')"
                            class="block rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-[#edf3ff] hover:text-[#487fff]"
                            >Profil</Link
                        >
                        <button
                            class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                            @click="logout"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                <div
                    v-if="flash.success"
                    class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm"
                >
                    {{ flash.error }}
                </div>
                <slot />
            </main>

            <footer
                class="flex flex-col gap-1 border-t border-slate-200 bg-white px-6 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between"
            >
                <span
                    >© {{ new Date().getFullYear() }}
                    {{ page.props.company.name }}. Semua hak dilindungi.</span
                >
                <span>Sistem Invoice &amp; Keuangan</span>
            </footer>
        </div>
    </div>
</template>
