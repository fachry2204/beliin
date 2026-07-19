import axios from "axios";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

type PushState = "loading" | "unsupported" | "install-required" | "disabled" | "enabling" | "enabled" | "denied" | "error";

interface PushOptions {
    enabled: boolean;
    publicKey?: string | null;
}

const state = ref<PushState>("loading");
const errorMessage = ref("");
let registration: ServiceWorkerRegistration | null = null;
let audioContext: AudioContext | null = null;

const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
const isStandalone = () => window.matchMedia("(display-mode: standalone)").matches || (navigator as Navigator & { standalone?: boolean }).standalone === true;

const base64UrlToArrayBuffer = (value: string): ArrayBuffer => {
    const padding = "=".repeat((4 - (value.length % 4)) % 4);
    const raw = window.atob((value + padding).replace(/-/g, "+").replace(/_/g, "/"));
    const bytes = new Uint8Array(raw.length);
    for (let index = 0; index < raw.length; index += 1) bytes[index] = raw.charCodeAt(index);
    return bytes.buffer;
};

const playMessageTone = async () => {
    try {
        audioContext ??= new AudioContext();
        if (audioContext.state === "suspended") await audioContext.resume();
        const now = audioContext.currentTime;
        [0, 0.22].forEach((offset, index) => {
            const oscillator = audioContext!.createOscillator();
            const gain = audioContext!.createGain();
            oscillator.type = "sine";
            oscillator.frequency.value = index === 0 ? 880 : 1175;
            gain.gain.setValueAtTime(0.0001, now + offset);
            gain.gain.exponentialRampToValueAtTime(0.22, now + offset + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.18);
            oscillator.connect(gain).connect(audioContext!.destination);
            oscillator.start(now + offset);
            oscillator.stop(now + offset + 0.19);
        });
    } catch (_error) {
        // Perangkat boleh memblokir audio sampai pengguna berinteraksi dengan halaman.
    }
};

const serializeSubscription = (subscription: PushSubscription) => {
    const json = subscription.toJSON();
    return {
        endpoint: subscription.endpoint,
        keys: json.keys,
        content_encoding: (PushManager as typeof PushManager & { supportedContentEncodings?: string[] }).supportedContentEncodings?.[0] || "aes128gcm",
    };
};

export function useCourierPush(options: PushOptions) {
    const supported = computed(() => state.value !== "unsupported");
    const isEnabled = computed(() => state.value === "enabled");
    const label = computed(() => ({
        loading: "Memeriksa notifikasi…",
        unsupported: "Notifikasi tidak didukung",
        "install-required": "Install aplikasi untuk notifikasi",
        disabled: "Aktifkan Notifikasi",
        enabling: "Mengaktifkan…",
        enabled: "Notifikasi Aktif",
        denied: "Notifikasi diblokir",
        error: "Notifikasi bermasalah",
    })[state.value]);

    const initialize = async () => {
        if (!options.enabled || !options.publicKey || !("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
            state.value = "unsupported";
            return;
        }
        if (isIos() && !isStandalone()) {
            state.value = "install-required";
            return;
        }

        try {
            registration = await navigator.serviceWorker.register("/sw.js", { scope: "/" });
            await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await axios.post(route("courier.push-subscriptions.store"), serializeSubscription(subscription));
                state.value = "enabled";
            } else {
                state.value = Notification.permission === "denied" ? "denied" : "disabled";
            }
        } catch (error) {
            state.value = "error";
            errorMessage.value = error instanceof Error ? error.message : "Service worker tidak dapat dijalankan.";
        }
    };

    const enable = async () => {
        if (state.value === "enabled" || state.value === "enabling") return;
        if (state.value === "install-required") {
            errorMessage.value = "Di iPhone/iPad, tambahkan aplikasi ke Layar Utama terlebih dahulu lalu buka dari ikon aplikasi.";
            return;
        }

        state.value = "enabling";
        errorMessage.value = "";
        try {
            audioContext ??= new AudioContext();
            const permission = await Notification.requestPermission();
            if (permission !== "granted") {
                state.value = "denied";
                errorMessage.value = "Izinkan notifikasi pada pengaturan browser/perangkat, lalu coba kembali.";
                return;
            }
            registration ??= await navigator.serviceWorker.register("/sw.js", { scope: "/" });
            const existing = await registration.pushManager.getSubscription();
            const subscription = existing ?? await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToArrayBuffer(options.publicKey!),
            });
            await axios.post(route("courier.push-subscriptions.store"), serializeSubscription(subscription));
            state.value = "enabled";
            await playMessageTone();
        } catch (error) {
            state.value = "error";
            errorMessage.value = error instanceof Error ? error.message : "Notifikasi gagal diaktifkan.";
        }
    };

    const disable = async () => {
        if (!registration) return;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            const endpoint = subscription.endpoint;
            await subscription.unsubscribe();
            try {
                await axios.delete(route("courier.push-subscriptions.destroy"), { data: { endpoint } });
            } catch (_error) {
                // Endpoint yang sudah berhenti akan dibersihkan server saat pengiriman berikutnya.
            }
        }
        state.value = "disabled";
    };

    const onServiceWorkerMessage = (event: MessageEvent) => {
        if (event.data?.type === "courier-push") {
            playMessageTone();
            window.dispatchEvent(new CustomEvent("courier-task-received", { detail: event.data.payload }));
        }
    };

    onMounted(() => {
        navigator.serviceWorker?.addEventListener("message", onServiceWorkerMessage);
        initialize();
    });
    onBeforeUnmount(() => navigator.serviceWorker?.removeEventListener("message", onServiceWorkerMessage));

    return { state, supported, isEnabled, label, errorMessage, enable, disable };
}
