<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { startCourierLocationTracking, stopCourierLocationTracking, useCourierLocation } from "@/composables/useCourierLocation";
import { useCourierPush } from "@/composables/useCourierPush";

interface PageProps extends Record<string, unknown> {
    auth: { user: { id: number; name: string; email: string } };
    company: { name: string; logo_url?: string | null };
    flash: { success?: string; error?: string };
    webPush: { enabled: boolean; publicKey?: string | null };
}
const page = usePage<PageProps>();
const flash = computed(() => page.props.flash);
const { status: locationState } = useCourierLocation();
const { state: pushState, isEnabled: pushEnabled, label: pushLabel, errorMessage: pushError, enable: enablePush, disable: disablePush } = useCourierPush(page.props.webPush);
let viewportMeta: HTMLMetaElement | null = null;
let previousViewportContent = "";
onMounted(() => {
    viewportMeta = document.querySelector('meta[name="viewport"]');
    if (viewportMeta) {
        previousViewportContent = viewportMeta.content;
        viewportMeta.content = "width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover";
    }
    startCourierLocationTracking();
});
onBeforeUnmount(() => {
    stopCourierLocationTracking();
    if (viewportMeta && previousViewportContent) viewportMeta.content = previousViewportContent;
});
const logout = async () => {
    try {
        await disablePush();
    } finally {
        router.post(route("logout"));
    }
};
const refreshTasks = () => {
    if (route().current("courier.tasks.*")) router.reload({ only: ["availableTasks", "activeTasks", "shippingSummary"] });
};

onMounted(() => window.addEventListener("courier-task-received", refreshTasks));
onBeforeUnmount(() => window.removeEventListener("courier-task-received", refreshTasks));
</script>

<template>
    <div class="courier-shell min-h-[100dvh] overflow-x-hidden bg-slate-50 pb-[calc(6rem+env(safe-area-inset-bottom))] text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                <Link :href="route('courier.tasks.index')" class="flex items-center gap-2 font-bold text-sky-600">
                    <img v-if="page.props.company.logo_url" :src="page.props.company.logo_url" :alt="`Logo ${page.props.company.name}`" class="h-9 w-9 rounded-lg border border-slate-200 bg-white object-contain p-1"/>
                    <span v-else class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-600 text-white">{{page.props.company.name.charAt(0).toUpperCase()}}</span>
                    <span class="max-w-[180px] truncate text-xl">{{page.props.company.name}}</span>
                </Link>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full border transition"
                        :class="pushEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-amber-200 bg-amber-50 text-amber-600'"
                        :title="pushLabel"
                        :disabled="pushState === 'loading' || pushState === 'unsupported' || pushState === 'enabling'"
                        @click="pushEnabled ? disablePush() : enablePush()"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                    </button>
                    <div class="text-right">
                        <p class="text-sm font-semibold">{{ page.props.auth.user.name }}</p>
                        <p class="flex items-center justify-end gap-1 text-[11px] text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500" />{{ locationState }}</p>
                        <p class="max-w-[150px] truncate text-[10px]" :class="pushEnabled ? 'text-emerald-600' : 'text-amber-600'">{{ pushLabel }}</p>
                    </div>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-5xl p-4 sm:p-6">
            <div v-if="flash.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ flash.success }}</div>
            <div v-if="flash.error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ flash.error }}</div>
            <div v-if="pushError" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ pushError }}</div>
            <slot />
        </main>
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_30px_rgba(15,23,42,.08)]">
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
.courier-shell { touch-action: pan-x pan-y; -webkit-text-size-adjust: 100%; }
.courier-shell :deep(button), .courier-shell :deep(a) { touch-action: manipulation; }
.courier-shell :deep(input), .courier-shell :deep(textarea), .courier-shell :deep(select) { font-size: 16px; }
</style>
