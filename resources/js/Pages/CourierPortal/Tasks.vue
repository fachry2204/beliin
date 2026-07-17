<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import CourierLayout from "@/Layouts/CourierLayout.vue";
import { startCourierLocationTracking, stopCourierLocationTracking, useCourierLocation } from "@/composables/useCourierLocation";

interface Customer { name: string; company_name?: string; address?: string; phone?: string }
interface Deposit { amount: string; paid_at?: string | null }
interface Invoice { id: number; invoice_number: string; billing_address?: string; shipping_cost: string; customer: Customer; shipping_deposit?: Deposit }
interface Delivery { id: number; status: string; accepted_at?: string; departed_at?: string; delivered_at?: string; invoice: Invoice }
defineProps<{ courier: Record<string, unknown>; availableTasks: Delivery[]; activeTasks: Delivery[]; completedTasks: Delivery[]; shippingSummary: { paid: number; unpaid: number } }>();

const page = usePage();
const systemName = computed(() => (page.props.company as { name?: string } | undefined)?.name || "InvoFlow");
const { coordinates: liveCoordinates, address: liveAddress, error: locationError, ready: locationReady } = useCourierLocation();
const busy = ref<number | null>(null);
const selected = ref<number | null>(null);
const cameraPhase = ref<"departure" | "arrival">("arrival");
const proof = ref<File | null>(null);
const preview = ref("");
const coords = ref<{ latitude: number; longitude: number; accuracy: number } | null>(null);
const deliveryAddress = ref("");
const notes = ref("");
const gpsError = ref("");
const cameraError = ref("");
const cameraOpen = ref(false);
const capturing = ref(false);
const savedFacingMode = typeof window !== "undefined" ? window.localStorage.getItem("courier_preferred_camera") : null;
const facingMode = ref<"user" | "environment">(savedFacingMode === "user" ? "user" : "environment");
const video = ref<HTMLVideoElement | null>(null);
let cameraStream: MediaStream | null = null;
const setVideoRef = (element: unknown) => {
    video.value = element as HTMLVideoElement | null;
};

const money = (value: unknown) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value ?? 0));
const dateTime = (value?: string) => value ? new Date(value).toLocaleString("id-ID") : "-";
const customerName = (invoice: Invoice) => invoice.customer.company_name || invoice.customer.name;
const accept = (delivery: Delivery) => {
    busy.value = delivery.id;
    const data: Record<string, number> = {};
    if (liveCoordinates.value) {
        data.latitude = liveCoordinates.value.latitude;
        data.longitude = liveCoordinates.value.longitude;
    }
    router.post(route("courier.tasks.accept", delivery.id), data, { onFinish: () => busy.value = null });
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
            video: { facingMode: { ideal: facingMode.value } },
            audio: false,
        });
        const videoTrack = cameraStream.getVideoTracks()[0];
        try {
            const capabilities = videoTrack?.getCapabilities() as MediaTrackCapabilities & { zoom?: { min?: number } };
            if (videoTrack && capabilities?.zoom?.min !== undefined) {
                await videoTrack.applyConstraints({
                    advanced: [{ zoom: capabilities.zoom.min } as unknown as MediaTrackConstraintSet],
                });
            }
        } catch {
            // Perangkat lama tetap menggunakan zoom bawaan kamera tanpa menggagalkan preview.
        }
        await nextTick();
        if (video.value) {
            video.value.srcObject = cameraStream;
            await video.value.play();
        }
    } catch {
        cameraError.value = "Kamera tidak dapat dibuka. Pastikan izin kamera aktif dan tidak digunakan aplikasi lain.";
    }
};
const openCamera = async (deliveryId: number, phase: "departure" | "arrival") => {
    selected.value = deliveryId;
    cameraPhase.value = phase;
    if (phase === "arrival") {
        proof.value = null;
        coords.value = null;
        deliveryAddress.value = "";
        if (preview.value) URL.revokeObjectURL(preview.value);
        preview.value = "";
    }
    gpsError.value = "";
    if (!locationReady.value) {
        gpsError.value = locationError.value || "Tunggu hingga GPS dan alamat lengkap aktif sebelum membuka kamera.";
        return;
    }
    cameraOpen.value = true;
    document.documentElement.classList.add("overflow-hidden");
    await nextTick();
    await startCamera();
};
const closeCamera = () => {
    stopCamera();
    cameraOpen.value = false;
    document.documentElement.classList.remove("overflow-hidden");
};
const switchCamera = async () => {
    facingMode.value = facingMode.value === "environment" ? "user" : "environment";
    window.localStorage.setItem("courier_preferred_camera", facingMode.value);
    await startCamera();
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
const stampPhoto = async (address: string): Promise<File> => {
    if (!video.value?.videoWidth || !video.value.videoHeight) throw new Error("camera-not-ready");
    const canvas = document.createElement("canvas");
    canvas.width = 1080;
    canvas.height = 1440;
    const context = canvas.getContext("2d")!;
    const scale = Math.min(canvas.width / video.value.videoWidth, canvas.height / video.value.videoHeight);
    const renderedWidth = video.value.videoWidth * scale;
    const renderedHeight = video.value.videoHeight * scale;
    const renderedX = (canvas.width - renderedWidth) / 2;
    const renderedY = (canvas.height - renderedHeight) / 2;
    context.fillStyle = "black";
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.save();
    if (facingMode.value === "user") {
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
    }
    context.drawImage(video.value, renderedX, renderedY, renderedWidth, renderedHeight);
    context.restore();
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
    gpsError.value = "";
    try {
        if (!liveCoordinates.value || !liveAddress.value) throw new Error("location-not-ready");
        const location = { ...liveCoordinates.value };
        const address = liveAddress.value;
        const photo = await stampPhoto(address);
        closeCamera();
        if (cameraPhase.value === "departure" && selected.value !== null) {
            busy.value = selected.value;
            router.post(route("courier.tasks.start", selected.value), {
                ...location,
                departure_address: address,
                departure_photo: photo,
            }, {
                forceFormData: true,
                onFinish: () => busy.value = null,
            });
            return;
        }
        coords.value = location;
        deliveryAddress.value = address;
        proof.value = photo;
        preview.value = URL.createObjectURL(photo);
    } catch {
        gpsError.value = "Foto gagal. Tunggu hingga GPS dan alamat lengkap aktif, lalu coba kembali.";
        if (cameraPhase.value === "arrival") proof.value = null;
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
    stopCourierLocationTracking();
    stopCamera();
    document.documentElement.classList.remove("overflow-hidden");
    if (preview.value) URL.revokeObjectURL(preview.value);
});
onMounted(startCourierLocationTracking);
</script>

<template>
    <Head title="Tugas Kurir" />
    <CourierLayout>
        <div class="mb-5">
            <h1 class="text-2xl font-bold">Tugas Saya</h1>
            <p class="mt-1 text-sm text-slate-500">Ambil tugas, mulai perjalanan, lalu simpan bukti kamera dan GPS saat sampai.</p>
        </div>
        <div class="mb-5 rounded-2xl border p-4" :class="locationReady ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="locationReady ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-white'">GPS</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold" :class="locationReady ? 'text-emerald-800' : 'text-amber-800'">{{ locationReady ? 'GPS dan alamat aktif' : 'Mencari lokasi GPS...' }}</p>
                    <p class="mt-1 break-words text-xs leading-5" :class="locationReady ? 'text-emerald-700' : 'text-amber-700'">{{ liveAddress || locationError || 'Tetap berada di halaman ini dan izinkan akses lokasi.' }}</p>
                    <p v-if="liveCoordinates" class="mt-1 text-[11px] text-slate-500">{{ liveCoordinates.latitude.toFixed(6) }}, {{ liveCoordinates.longitude.toFixed(6) }} · akurasi ±{{ Math.round(liveCoordinates.accuracy) }} m</p>
                </div>
            </div>
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
                    <div v-if="task.status === 'accepted'">
                        <button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-300 px-4 py-3 text-sm font-bold text-emerald-950 shadow-sm transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" :disabled="busy === task.id || !locationReady" @click="openCamera(task.id, 'departure')">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 4h-5L8 6H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3l-1.5-2Z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                            <span>Buka Kamera &amp; Mulai Antar</span>
                        </button>
                        <p v-if="!locationReady" class="mt-2 text-center text-xs font-medium text-amber-700">Tunggu hingga GPS dan alamat aktif.</p>
                    </div>
                    <div v-else>
                        <button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-300 p-4 text-center text-sm font-bold text-emerald-950 shadow-sm transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!locationReady" @click="openCamera(task.id, 'arrival')">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 4h-5L8 6H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3l-1.5-2Z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                            <span>Buka Kamera di Lokasi Tujuan</span>
                        </button>
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

        <Teleport to="body">
            <div v-if="cameraOpen" class="fixed inset-0 z-[100] flex flex-col overflow-hidden bg-black text-white">
                <div class="absolute inset-x-0 top-0 z-10 bg-gradient-to-b from-black/85 to-transparent px-4 pb-12 pt-[max(1rem,env(safe-area-inset-top))]">
                    <div class="mx-auto flex max-w-lg items-start justify-between gap-3">
                        <div>
                            <p class="text-base font-bold">{{ cameraPhase === 'departure' ? 'Foto Sebelum Mengantar' : 'Foto Sampai di Lokasi' }}</p>
                            <p class="mt-1 text-xs text-emerald-300">GPS & alamat aktif · {{ facingMode === 'environment' ? 'Kamera belakang' : 'Kamera depan' }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-xl backdrop-blur" aria-label="Tutup kamera" @click="closeCamera">×</button>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 items-center justify-center bg-black">
                    <video :ref="setVideoRef" autoplay muted playsinline class="h-full w-full bg-black object-contain sm:aspect-[3/4] sm:h-[min(100dvh,900px)] sm:w-auto" :class="facingMode === 'user' ? '-scale-x-100' : ''"></video>
                </div>

                <div class="absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-black via-black/85 to-transparent px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-16">
                    <div class="mx-auto max-w-lg">
                        <div class="mb-4 rounded-xl bg-black/45 p-3 text-xs leading-5 backdrop-blur">
                            <p class="font-bold">{{ systemName }}</p>
                            <p class="mt-1 line-clamp-2 text-white/85">{{ liveAddress }}</p>
                        </div>
                        <p v-if="cameraError" class="mb-3 rounded-lg bg-red-500/90 p-3 text-center text-xs font-semibold">{{ cameraError }}</p>
                        <div class="grid grid-cols-3 items-center">
                            <button type="button" class="justify-self-start rounded-full bg-white/15 px-4 py-3 text-xs font-semibold backdrop-blur" @click="closeCamera">Batal</button>
                            <button type="button" class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border-4 border-white bg-white/25 disabled:opacity-40" :disabled="capturing || !!cameraError || !locationReady" aria-label="Ambil foto" @click="capturePhoto">
                                <span class="h-14 w-14 rounded-full bg-white"></span>
                            </button>
                            <button type="button" class="justify-self-end rounded-full bg-white/15 px-4 py-3 text-xs font-semibold backdrop-blur" @click="switchCamera">Balik</button>
                        </div>
                        <p class="mt-3 text-center text-xs text-white/70">{{ capturing ? 'Memproses foto dan timestamp...' : 'Foto otomatis diberi nama sistem, waktu, dan alamat GPS.' }}</p>
                    </div>
                </div>
            </div>
        </Teleport>
    </CourierLayout>
</template>
