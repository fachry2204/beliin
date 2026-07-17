<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

interface Courier {
    id: number;
    name: string;
    courier_code: string;
    phone?: string;
    vehicle_type?: string;
    license_plate?: string;
    latitude?: string | null;
    longitude?: string | null;
    accuracy?: string | null;
    last_location_at?: string | null;
    status: "online" | "delivering" | "offline";
    invoice_numbers: string[];
}

const props = defineProps<{
    couriers: Courier[];
    summary: { online: number; delivering: number; offline: number };
    refreshedAt: string;
}>();
const mapElement = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let markers = L.layerGroup();
let timer: number;
const labels = { online: "Aktif", delivering: "Mengantar", offline: "Offline" };

const ago = (value?: string | null) => {
    if (!value) return "Belum ada lokasi";
    const seconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 1000),
    );
    if (seconds < 60) return `${seconds} detik lalu`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} menit lalu`;
    return new Date(value).toLocaleString("id-ID");
};

const courierIcon = (courier: Courier) => {
    const image =
        courier.status === "delivering"
            ? "/images/couriers/fast-shipping.png"
            : "/images/couriers/delivery-bike.png";

    return L.divIcon({
        className: "courier-vehicle-marker-wrap",
        html: `<div class="courier-vehicle-marker courier-vehicle-marker--${courier.status}"><img src="${image}" alt=""></div>`,
        iconSize: [64, 64],
        iconAnchor: [32, 58],
        popupAnchor: [0, -54],
    });
};

const renderMarkers = () => {
    if (!map) return;
    markers.clearLayers();
    const points: L.LatLngExpression[] = [];

    props.couriers.forEach((courier) => {
        if (courier.latitude == null || courier.longitude == null) return;
        const latitude = Number(courier.latitude);
        const longitude = Number(courier.longitude);
        points.push([latitude, longitude]);
        L.marker([latitude, longitude], { icon: courierIcon(courier) })
            .bindPopup(
                `<strong>${courier.name}</strong><br>${labels[courier.status]}${courier.invoice_numbers.length ? `<br>${courier.invoice_numbers.join("<br>")}` : ""}<br><small>${ago(courier.last_location_at)}</small>`,
            )
            .addTo(markers);
    });

    markers.addTo(map);
    if (points.length && !map.getBounds().contains(points[0])) {
        map.fitBounds(L.latLngBounds(points), { padding: [50, 50], maxZoom: 14 });
    }
};

onMounted(async () => {
    await nextTick();
    map = L.map(mapElement.value!, { zoomControl: true }).setView(
        [-6.2, 106.816666],
        11,
    );
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);
    renderMarkers();
    timer = window.setInterval(
        () => router.reload({ only: ["couriers", "summary", "refreshedAt"] }),
        15000,
    );
});
watch(() => props.couriers, renderMarkers, { deep: true });
onBeforeUnmount(() => {
    clearInterval(timer);
    map?.remove();
});
const refresh = () =>
    router.reload({ only: ["couriers", "summary", "refreshedAt"] });
</script>

<template>
    <Head title="Map Kurir" />
    <AuthenticatedLayout>
        <template #breadcrumb>Kurir / Map Kurir</template>
        <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <h1 class="page-title">Map Kurir</h1>
                <p class="page-subtitle">
                    Pantau posisi terakhir kurir. Lokasi diperbarui otomatis setiap 15 detik.
                </p>
            </div>
            <button
                class="rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50"
                @click="refresh"
            >
                ↻ Perbarui
            </button>
        </div>
        <div class="mb-5 grid gap-3 sm:grid-cols-3">
            <div class="panel flex items-center gap-3 p-4">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">●</span>
                <div><p class="text-xs text-slate-500">Aktif</p><strong class="text-xl text-emerald-600">{{ summary.online }}</strong></div>
            </div>
            <div class="panel flex items-center gap-3 p-4">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-100 text-amber-600">●</span>
                <div><p class="text-xs text-slate-500">Mengantar</p><strong class="text-xl text-amber-600">{{ summary.delivering }}</strong></div>
            </div>
            <div class="panel flex items-center gap-3 p-4">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-500">●</span>
                <div><p class="text-xs text-slate-500">Offline</p><strong class="text-xl text-slate-600">{{ summary.offline }}</strong></div>
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="panel overflow-hidden">
                <div ref="mapElement" class="h-[520px] w-full bg-slate-100" />
            </section>
            <aside class="panel overflow-hidden">
                <div class="border-b border-slate-200 p-4">
                    <h2 class="font-bold">Daftar Kurir</h2>
                    <p class="mt-1 text-xs text-slate-500">Terakhir diperbarui {{ new Date(refreshedAt).toLocaleTimeString("id-ID") }}</p>
                </div>
                <div class="max-h-[470px] overflow-y-auto p-3">
                    <Link
                        v-for="courier in couriers"
                        :key="courier.id"
                        :href="route('couriers.show', courier.id)"
                        class="mb-2 block rounded-xl border border-slate-200 p-3 hover:border-sky-300 hover:bg-sky-50"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ courier.name }}</p>
                                <p class="text-xs text-slate-500">{{ courier.vehicle_type || "-" }} · {{ courier.license_plate || "-" }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="courier.status === 'online' ? 'bg-emerald-100 text-emerald-700' : courier.status === 'delivering' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                            >{{ labels[courier.status] }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <span class="text-slate-500">Update</span><span class="text-right">{{ ago(courier.last_location_at) }}</span>
                            <span class="text-slate-500">Invoice ({{ courier.invoice_numbers.length }})</span>
                            <span class="text-right font-medium">
                                <template v-if="courier.invoice_numbers.length">
                                    <span v-for="invoiceNumber in courier.invoice_numbers" :key="invoiceNumber" class="block">{{ invoiceNumber }}</span>
                                </template>
                                <template v-else>-</template>
                            </span>
                        </div>
                    </Link>
                    <div v-if="!couriers.length" class="p-8 text-center text-sm text-slate-500">Belum ada data kurir.</div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.courier-vehicle-marker-wrap {
    background: transparent !important;
    border: 0 !important;
}
.courier-vehicle-marker {
    position: relative;
    display: grid;
    width: 64px;
    height: 64px;
    place-items: center;
    overflow: visible;
    --pulse-color: #22c55e;
}
.courier-vehicle-marker::before {
    position: absolute;
    z-index: 0;
    width: 48px;
    height: 48px;
    border: 3px solid var(--pulse-color);
    border-radius: 9999px;
    content: "";
    animation: courier-pulse 1.8s ease-out infinite;
}
.courier-vehicle-marker--delivering {
    --pulse-color: #f59e0b;
}
.courier-vehicle-marker img {
    position: relative;
    z-index: 1;
    width: 58px;
    height: 58px;
    object-fit: contain;
    filter: drop-shadow(0 4px 4px rgb(15 23 42 / 25%));
}
@keyframes courier-pulse {
    0% {
        opacity: 0.75;
        transform: scale(0.65);
    }
    75%,
    100% {
        opacity: 0;
        transform: scale(1.45);
    }
}
</style>
