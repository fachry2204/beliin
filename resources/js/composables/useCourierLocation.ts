import { computed, readonly, ref } from "vue";
import axios from "axios";

export interface CourierCoordinates {
    latitude: number;
    longitude: number;
    accuracy: number;
}

const coordinates = ref<CourierCoordinates | null>(null);
const address = ref("");
const status = ref("Mencari GPS...");
const error = ref("");
let watchId: number | null = null;
let consumers = 0;
let lastSentAt = 0;
let lastGeocodedAt = 0;
let lastGeocodedCoordinates: CourierCoordinates | null = null;

const distanceInMeters = (from: CourierCoordinates, to: CourierCoordinates) => {
    const radius = 6_371_000;
    const latitudeDelta = (to.latitude - from.latitude) * Math.PI / 180;
    const longitudeDelta = (to.longitude - from.longitude) * Math.PI / 180;
    const firstLatitude = from.latitude * Math.PI / 180;
    const secondLatitude = to.latitude * Math.PI / 180;
    const value = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(firstLatitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;

    return radius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
};

const reverseGeocode = async (location: CourierCoordinates) => {
    const response = await axios.get<{ address: string }>(route("courier.location.address"), {
        params: { latitude: location.latitude, longitude: location.longitude },
    });
    if (!response.data.address) throw new Error("address-not-found");

    address.value = response.data.address;
    lastGeocodedAt = Date.now();
    lastGeocodedCoordinates = location;
};

const updateLocation = async (position: GeolocationPosition) => {
    const current = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
    };
    coordinates.value = current;
    status.value = address.value ? "GPS & alamat aktif" : "GPS aktif, mencari alamat...";
    error.value = "";

    if (Date.now() - lastSentAt >= 25_000) {
        lastSentAt = Date.now();
        axios.post(route("courier.location.store"), current).catch(() => {
            status.value = "GPS aktif, sinkronisasi tertunda";
        });
    }

    const shouldRefreshAddress = !lastGeocodedCoordinates
        || Date.now() - lastGeocodedAt >= 60_000
        || distanceInMeters(lastGeocodedCoordinates, current) >= 50;
    if (shouldRefreshAddress) {
        try {
            await reverseGeocode(current);
            status.value = "GPS & alamat aktif";
        } catch {
            error.value = "GPS aktif, tetapi alamat lengkap belum ditemukan.";
            status.value = "GPS aktif, alamat tertunda";
        }
    }
};

export const startCourierLocationTracking = () => {
    consumers += 1;
    if (watchId !== null) return;
    if (!navigator.geolocation) {
        status.value = "GPS tidak tersedia";
        error.value = "Perangkat ini tidak mendukung GPS.";
        return;
    }

    status.value = "Mencari GPS...";
    watchId = navigator.geolocation.watchPosition(
        updateLocation,
        (geolocationError) => {
            error.value = geolocationError.code === geolocationError.PERMISSION_DENIED
                ? "Izin lokasi diperlukan agar foto dapat diberi alamat GPS."
                : "Lokasi GPS belum dapat diperoleh. Pastikan GPS perangkat aktif.";
            status.value = "GPS tidak aktif";
        },
        { enableHighAccuracy: true, maximumAge: 10_000, timeout: 20_000 },
    );
};

export const stopCourierLocationTracking = () => {
    consumers = Math.max(0, consumers - 1);
    if (consumers > 0 || watchId === null) return;
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
};

export const useCourierLocation = () => ({
    coordinates: readonly(coordinates),
    address: readonly(address),
    status: readonly(status),
    error: readonly(error),
    ready: computed(() => Boolean(coordinates.value && address.value)),
});
