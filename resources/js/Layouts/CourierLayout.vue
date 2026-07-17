<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import axios from "axios";

interface PageProps extends Record<string, unknown> {
    auth: { user: { id: number; name: string; email: string } };
    company: { name: string; logo_url?: string | null };
    flash: { success?: string; error?: string };
}
const page = usePage<PageProps>();
const flash = computed(() => page.props.flash);
const locationState = ref("Mencari GPS...");
let watchId: number | null = null;
let lastSent = 0;

const sendLocation = async (position: GeolocationPosition) => {
    locationState.value = "GPS aktif";
    if (Date.now() - lastSent < 25000) return;
    lastSent = Date.now();
    await axios.post(route("courier.location.store"), {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
    });
};

onMounted(() => {
    if (!navigator.geolocation) {
        locationState.value = "GPS tidak tersedia";
        return;
    }
    watchId = navigator.geolocation.watchPosition(sendLocation, () => {
        locationState.value = "Izin GPS diperlukan";
    }, { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 });
});
onBeforeUnmount(() => watchId !== null && navigator.geolocation.clearWatch(watchId));
const logout = () => router.post(route("logout"));
</script>

<template>
    <div class="min-h-screen bg-slate-50 pb-24 text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                <Link :href="route('courier.tasks.index')" class="flex items-center gap-2 font-bold text-sky-600">
                    <img v-if="page.props.company.logo_url" :src="page.props.company.logo_url" :alt="`Logo ${page.props.company.name}`" class="h-9 w-9 rounded-lg border border-slate-200 bg-white object-contain p-1"/>
                    <span v-else class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-600 text-white">{{page.props.company.name.charAt(0).toUpperCase()}}</span>
                    <span class="max-w-[180px] truncate text-xl">{{page.props.company.name}}</span>
                </Link>
                <div class="text-right">
                    <p class="text-sm font-semibold">{{ page.props.auth.user.name }}</p>
                    <p class="flex items-center justify-end gap-1 text-[11px] text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500" />{{ locationState }}</p>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-5xl p-4 sm:p-6">
            <div v-if="flash.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ flash.success }}</div>
            <div v-if="flash.error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ flash.error }}</div>
            <slot />
        </main>
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white shadow-[0_-8px_30px_rgba(15,23,42,.08)]">
            <div class="mx-auto grid h-20 max-w-md grid-cols-4 px-2">
                <Link :href="route('courier.tasks.index')" class="portal-nav" :class="route().current('courier.tasks.*') ? 'active' : ''"><img src="/images/courier-nav/job-offer.png" alt="" class="portal-nav-icon" aria-hidden="true"/><span>Tugas</span></Link>
                <Link :href="route('courier.earnings.index')" class="portal-nav" :class="route().current('courier.earnings.*') ? 'active' : ''"><img src="/images/courier-nav/money-bag.png" alt="" class="portal-nav-icon" aria-hidden="true"/><span>Ongkir</span></Link>
                <Link :href="route('courier.profile.edit')" class="portal-nav" :class="route().current('courier.profile.*') ? 'active' : ''"><img src="/images/courier-nav/user.png" alt="" class="portal-nav-icon" aria-hidden="true"/><span>Profil</span></Link>
                <button class="portal-nav text-red-500" @click="logout"><img src="/images/courier-nav/logout.png" alt="" class="portal-nav-icon" aria-hidden="true"/><span>Keluar</span></button>
            </div>
        </nav>
    </div>
</template>

<style scoped>
.portal-nav { @apply flex flex-col items-center justify-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-sky-600; }
.portal-nav.active { @apply border-t-2 border-sky-500 text-sky-600; }
.portal-nav-icon { @apply h-8 w-8 object-contain; }
</style>
