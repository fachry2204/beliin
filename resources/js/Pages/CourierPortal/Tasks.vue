<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import CourierLayout from "@/Layouts/CourierLayout.vue";

interface Customer { name: string; company_name?: string; address?: string; phone?: string }
interface Deposit { amount: string; paid_at?: string | null }
interface Invoice { id: number; invoice_number: string; billing_address?: string; shipping_cost: string; customer: Customer; shipping_deposit?: Deposit }
interface Delivery { id: number; status: string; accepted_at?: string; departed_at?: string; delivered_at?: string; invoice: Invoice }
defineProps<{ courier: Record<string, unknown>; availableTasks: Delivery[]; activeTasks: Delivery[]; completedTasks: Delivery[]; shippingSummary: { paid: number; unpaid: number } }>();

const page = usePage();
const systemName = computed(() => (page.props.company as { name?: string } | undefined)?.name || "InvoFlow");
const busy = ref<number | null>(null);
const selected = ref<number | null>(null);
const proof = ref<File | null>(null);
const preview = ref("");
const coords = ref<{ latitude: number; longitude: number; accuracy: number } | null>(null);
const deliveryAddress = ref("");
const notes = ref("");
const gpsError = ref("");
const cameraError = ref("");
const cameraOpen = ref(false);
const capturing = ref(false);
const facingMode = ref<"user" | "environment">("environment");
const video = ref<HTMLVideoElement | null>(null);
let cameraStream: MediaStream | null = null;
const setVideoRef = (element: unknown) => {
    video.value = element as HTMLVideoElement | null;
};

const money = (value: unknown) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value ?? 0));
const dateTime = (value?: string) => value ? new Date(value).toLocaleString("id-ID") : "-";
const customerName = (invoice: Invoice) => invoice.customer.company_name || invoice.customer.name;
const locate = (timeout = 20000) => new Promise<GeolocationPosition>((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout, maximumAge: 0 }));

const accept = async (delivery: Delivery) => {
    busy.value = delivery.id;
    const data: Record<string, number> = {};
    try {
        const position = await locate(4000);
        data.latitude = position.coords.latitude;
        data.longitude = position.coords.longitude;
    } catch { /* GPS wajib saat serah terima, tetapi tidak memblokir pengambilan tugas. */ }
    router.post(route("courier.tasks.accept", delivery.id), data, { onFinish: () => busy.value = null });
};
const start = (delivery: Delivery) => {
    busy.value = delivery.id;
    router.post(route("courier.tasks.start", delivery.id), {}, { onFinish: () => busy.value = null });
};

const stopCamera = () => {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    if (video.value) video.value.srcObject = null;
};
const startCamera = async () => {
    stopCamera();
    cameraError.value = "";
    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = "Kamera tidak tersedia. Buka aplikasi melalui HTTPS dan izinkan akses kamera.";
        return;
    }
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: facingMode.value }, width: { ideal: 1920 }, height: { ideal: 1080 } },
            audio: false,
        });
        await nextTick();
        if (video.value) {
            video.value.srcObject = cameraStream;
            await video.value.play();
        }
    } catch {
        cameraError.value = "Kamera tidak dapat dibuka. Pastikan izin kamera aktif dan tidak digunakan aplikasi lain.";
    }
};
const openCamera = async (deliveryId: number) => {
    selected.value = deliveryId;
    proof.value = null;
    coords.value = null;
    deliveryAddress.value = "";
    gpsError.value = "";
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = "";
    cameraOpen.value = true;
    await nextTick();
    await startCamera();
};
const closeCamera = () => {
    stopCamera();
    cameraOpen.value = false;
};
const switchCamera = async () => {
    facingMode.value = facingMode.value === "environment" ? "user" : "environment";
    await startCamera();
};
const reverseGeocode = async (position: GeolocationPosition): Promise<string> => {
    const url = new URL("https://nominatim.openstreetmap.org/reverse");
    url.searchParams.set("format", "jsonv2");
    url.searchParams.set("lat", String(position.coords.latitude));
    url.searchParams.set("lon", String(position.coords.longitude));
    url.searchParams.set("zoom", "18");
    url.searchParams.set("addressdetails", "1");
    url.searchParams.set("accept-language", "id");
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("reverse-geocode-failed");
    const result = await response.json() as { display_name?: string };
    if (!result.display_name) throw new Error("address-not-found");
    return result.display_name;
};
const wrapText = (context: CanvasRenderingContext2D, text: string, maxWidth: number) => {
    const lines: string[] = [];
    let line = "";
    text.split(/\s+/).forEach((word) => {
        const candidate = line ? `${line} ${word}` : word;
        if (line && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    });
    if (line) lines.push(line);
    return lines;
};
const stampPhoto = async (position: GeolocationPosition, address: string): Promise<File> => {
    if (!video.value?.videoWidth || !video.value.videoHeight) throw new Error("camera-not-ready");
    const scale = Math.min(1, 1800 / Math.max(video.value.videoWidth, video.value.videoHeight));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(video.value.videoWidth * scale);
    canvas.height = Math.round(video.value.videoHeight * scale);
    const context = canvas.getContext("2d")!;
    context.drawImage(video.value, 0, 0, canvas.width, canvas.height);
    const fontSize = Math.max(22, Math.round(canvas.width * 0.026));
    context.font = `600 ${fontSize}px sans-serif`;
    const now = new Date();
    const date = now.toLocaleDateString("id-ID", { day: "2-digit", month: "long", year: "numeric" });
    const time = now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
    const padding = fontSize * 0.7;
    const lines = [systemName.value, `${date} | ${time}`, ...wrapText(context, address, canvas.width - padding * 2)];
    const lineHeight = fontSize * 1.35;
    const boxHeight = lines.length * lineHeight + padding * 2;
    context.fillStyle = "rgba(2,47,77,.84)";
    context.fillRect(0, canvas.height - boxHeight, canvas.width, boxHeight);
    context.fillStyle = "white";
    lines.forEach((line, index) => context.fillText(line, padding, canvas.height - boxHeight + padding + fontSize + index * lineHeight));
    const blob = await new Promise<Blob>((resolve, reject) => canvas.toBlob((value) => value ? resolve(value) : reject(new Error("photo-encode-failed")), "image/jpeg", .88));
    return new File([blob], `bukti-${Date.now()}.jpg`, { type: "image/jpeg" });
};
const capturePhoto = async () => {
    capturing.value = true;
    gpsError.value = "Mengambil GPS saat ini dan mencari alamat lengkap...";
    try {
        const position = await locate();
        const address = await reverseGeocode(position);
        coords.value = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy };
        deliveryAddress.value = address;
        proof.value = await stampPhoto(position, address);
        preview.value = URL.createObjectURL(proof.value);
        gpsError.value = "";
        closeCamera();
    } catch {
        gpsError.value = "Foto gagal. Aktifkan GPS, pastikan internet tersedia untuk membaca alamat, lalu coba kembali.";
        proof.value = null;
    } finally {
        capturing.value = false;
    }
};
const complete = (delivery: Delivery) => {
    if (!proof.value || !coords.value || !deliveryAddress.value) {
        gpsError.value = "Foto, GPS saat ini, dan alamat lengkap wajib tersedia.";
        return;
    }
    busy.value = delivery.id;
    router.post(route("courier.tasks.complete", delivery.id), {
        ...coords.value,
        delivery_address: deliveryAddress.value,
        proof_photo: proof.value,
        delivery_notes: notes.value,
    }, { forceFormData: true, onFinish: () => busy.value = null });
};
onBeforeUnmount(() => {
    stopCamera();
    if (preview.value) URL.revokeObjectURL(preview.value);
});
</script>

<template>
    <Head title="Tugas Kurir" />
    <CourierLayout>
        <div class="mb-5">
            <h1 class="text-2xl font-bold">Tugas Saya</h1>
            <p class="mt-1 text-sm text-slate-500">Ambil tugas, mulai perjalanan, lalu simpan bukti kamera dan GPS saat sampai.</p>
        </div>
        <div class="mb-6 grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs text-emerald-700">Ongkir Dibayar</p><strong class="mt-1 block text-xl text-emerald-700">{{ money(shippingSummary.paid) }}</strong></div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs text-amber-700">Ongkir Belum Dibayar</p><strong class="mt-1 block text-xl text-amber-700">{{ money(shippingSummary.unpaid) }}</strong></div>
        </div>

        <section v-if="activeTasks.length" class="mb-7">
            <h2 class="mb-3 font-bold">Tugas Aktif</h2>
            <article v-for="task in activeTasks" :key="task.id" class="mb-4 overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-4">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-sky-700">{{ task.invoice.invoice_number }}</h3><p class="mt-1 text-sm font-semibold">{{ customerName(task.invoice) }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="task.status === 'in_transit' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'">{{ task.status === 'in_transit' ? 'Dalam Perjalanan' : 'Diambil' }}</span></div>
                    <p class="mt-2 text-sm text-slate-600">⌖ {{ task.invoice.billing_address || task.invoice.customer.address || '-' }}</p>
                    <p class="mt-2 text-sm">Ongkir <strong class="float-right text-emerald-600">{{ money(task.invoice.shipping_cost) }}</strong></p>
                </div>
                <div class="grid grid-cols-3 border-b border-slate-100 px-4 py-4 text-center text-[11px]"><div class="text-emerald-600"><b class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white">✓</b>Diambil</div><div :class="task.status === 'in_transit' ? 'text-emerald-600' : 'text-slate-400'"><b class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full" :class="task.status === 'in_transit' ? 'bg-emerald-500 text-white' : 'bg-slate-100'">2</b>Dalam Perjalanan</div><div class="text-slate-400"><b class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full bg-slate-100">3</b>Sampai Lokasi</div></div>
                <div class="p-4">
                    <button v-if="task.status === 'accepted'" class="w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white hover:bg-sky-700 disabled:opacity-50" :disabled="busy === task.id" @click="start(task)">Mulai Antar Barang</button>
                    <div v-else>
                        <button type="button" class="block w-full rounded-xl border-2 border-dashed border-slate-300 p-4 text-center text-sm font-semibold text-slate-600 hover:border-sky-400" @click="openCamera(task.id)">📷 Buka Kamera di Lokasi</button>
                        <div v-if="selected === task.id && cameraOpen" class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 p-3">
                            <video :ref="setVideoRef" autoplay muted playsinline class="aspect-video w-full rounded-xl object-cover" :class="facingMode === 'user' ? '-scale-x-100' : ''"></video>
                            <p v-if="cameraError" class="mt-2 rounded-lg bg-red-50 p-2 text-xs font-medium text-red-700">{{ cameraError }}</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <button type="button" class="rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white" @click="closeCamera">Tutup</button>
                                <button type="button" class="rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white" @click="switchCamera">↻ Depan / Belakang</button>
                                <button type="button" class="rounded-lg bg-sky-500 px-3 py-2 text-xs font-bold text-white disabled:opacity-50" :disabled="capturing || !!cameraError" @click="capturePhoto">{{ capturing ? 'Mengambil...' : 'Ambil Foto' }}</button>
                            </div>
                        </div>
                        <img v-if="selected === task.id && preview" :src="preview" class="mt-3 max-h-96 w-full rounded-xl object-contain" alt="Pratinjau bukti pengiriman bertimestamp" />
                        <div v-if="selected === task.id && deliveryAddress" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800"><strong class="block">Lokasi GPS saat foto</strong>{{ deliveryAddress }}</div>
                        <p v-if="selected === task.id && gpsError" class="mt-2 text-xs font-medium text-red-600">{{ gpsError }}</p>
                        <textarea v-model="notes" class="mt-3 w-full rounded-xl border-slate-300 text-sm" rows="2" placeholder="Catatan penerima (opsional)" />
                        <button class="mt-3 w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white hover:bg-sky-700 disabled:opacity-50" :disabled="busy === task.id || !proof" @click="complete(task)">Tandai Pengiriman Selesai</button>
                    </div>
                </div>
            </article>
        </section>

        <section class="mb-7"><h2 class="mb-3 font-bold">Tugas Tersedia</h2><div v-if="!availableTasks.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada tugas baru.</div><article v-for="task in availableTasks" :key="task.id" class="mb-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-start justify-between"><div><h3 class="font-bold">{{ task.invoice.invoice_number }}</h3><p class="mt-1 text-sm font-semibold">{{ customerName(task.invoice) }}</p></div><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">Tersedia</span></div><p class="mt-2 text-sm text-slate-600">⌖ {{ task.invoice.billing_address || task.invoice.customer.address || '-' }}</p><p class="mt-3 text-sm">Ongkir <strong class="float-right text-emerald-600">{{ money(task.invoice.shipping_cost) }}</strong></p><button class="mt-4 w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white hover:bg-sky-700 disabled:opacity-50" :disabled="busy === task.id" @click="accept(task)">Ambil Tugas</button></article></section>

        <section v-if="completedTasks.length"><h2 class="mb-3 font-bold">Riwayat Selesai</h2><article v-for="task in completedTasks" :key="task.id" class="mb-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4"><div><p class="font-semibold">{{ task.invoice.invoice_number }}</p><p class="text-xs text-slate-500">{{ customerName(task.invoice) }} · {{ dateTime(task.delivered_at) }}</p></div><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Selesai</span></article></section>
    </CourierLayout>
</template>
