<script setup lang="ts">
import { Head, useForm, usePage } from "@inertiajs/vue3";
import CourierLayout from "@/Layouts/CourierLayout.vue";
interface Courier {
    name: string;
    phone?: string;
    courier_code: string;
    vehicle_type?: string;
    license_plate?: string;
    bank_name?: string;
    bank_account_number?: string;
    bank_account_name?: string;
}
interface PageProps extends Record<string, unknown> {
    auth: {
        user: { id: number; name: string; username: string; email: string };
    };
}
const props = defineProps<{ courier: Courier }>();
const page = usePage<PageProps>();
const form = useForm({
    name: page.props.auth.user.name,
    username: page.props.auth.user.username,
    email: page.props.auth.user.email,
    phone: props.courier.phone || "",
    bank_name: props.courier.bank_name || "",
    bank_account_number: props.courier.bank_account_number || "",
    bank_account_name: props.courier.bank_account_name || "",
});
const submit = () =>
    form.patch(route("courier.profile.update"), { preserveScroll: true });
const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});
const updatePassword = () =>
    passwordForm.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
</script>
<template>
    <Head title="Profil Kurir" /><CourierLayout
        ><div class="mb-5">
            <h1 class="text-2xl font-bold">Profil Kurir</h1>
            <p class="mt-1 text-sm text-slate-500">
                Informasi akun, kontak, kendaraan, dan rekening bank Anda.
            </p>
        </div>
        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <div class="mb-6 flex items-center gap-4">
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-sky-100 text-2xl font-bold text-sky-700"
                >
                    {{ form.name.charAt(0) }}
                </div>
                <div>
                    <h2 class="font-bold">{{ props.courier.name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ props.courier.courier_code }} ·
                        {{ props.courier.vehicle_type || "-" }}
                        {{
                            props.courier.license_plate
                                ? `(${props.courier.license_plate})`
                                : ""
                        }}
                    </p>
                </div>
            </div>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label
                    ><span class="label">Nama</span
                    ><input v-model="form.name" class="input" required /></label
                ><label
                    ><span class="label">Username</span
                    ><input
                        v-model="form.username"
                        class="input"
                        required /></label
                ><label
                    ><span class="label">Email</span
                    ><input
                        v-model="form.email"
                        type="email"
                        class="input"
                        required /></label
                ><label
                    ><span class="label">No. HP</span
                    ><input v-model="form.phone" class="input"
                /></label>
                <div class="border-t border-slate-200 pt-4 sm:col-span-2">
                    <h2 class="font-bold">Rekening Bank</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Rekening untuk menerima pembayaran ongkir.
                    </p>
                </div>
                <label
                    ><span class="label">Nama Bank</span
                    ><input
                        v-model="form.bank_name"
                        class="input"
                        placeholder="Contoh: BCA" /></label
                ><label
                    ><span class="label">Nomor Rekening</span
                    ><input
                        v-model="form.bank_account_number"
                        class="input"
                        inputmode="numeric" /></label
                ><label class="sm:col-span-2"
                    ><span class="label">Atas Nama Rekening</span
                    ><input v-model="form.bank_account_name" class="input"
                /></label>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <button
                    class="rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white sm:col-span-2"
                    :disabled="form.processing"
                >
                    Simpan Profil
                </button>
            </form>
        </section>
        <section
            class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <h2 class="font-bold">Ubah Password</h2>
            <p class="mt-1 text-sm text-slate-500">
                Gunakan password yang hanya Anda ketahui untuk menjaga keamanan akun.
            </p>
            <form
                class="mt-5 grid gap-4"
                @submit.prevent="updatePassword"
            >
                <label
                    ><span class="label">Password Saat Ini</span
                    ><input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="input"
                        required
                /></label>
                <label
                    ><span class="label">Password Baru</span
                    ><input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="input"
                        required
                /></label>
                <label
                    ><span class="label">Konfirmasi Password Baru</span
                    ><input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="input"
                        required
                /></label>
                <p
                    v-if="Object.keys(passwordForm.errors).length"
                    class="text-sm text-red-600"
                >
                    {{ Object.values(passwordForm.errors)[0] }}
                </p>
                <button
                    class="rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-50"
                    :disabled="passwordForm.processing"
                >
                    {{ passwordForm.processing ? "Menyimpan..." : "Simpan Password" }}
                </button>
            </form>
        </section></CourierLayout
    >
</template>
<style scoped>
.input {
    @apply mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500;
}
.label {
    @apply text-xs font-semibold text-slate-600;
}
</style>
