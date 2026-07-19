<script setup lang="ts">
import { onMounted, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import Pagination from "@/Components/UI/Pagination.vue";
import ActionWarningModal from "@/Components/UI/ActionWarningModal.vue";

interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    is_active: boolean;
    last_login_at?: string;
    roles: { name: string }[];
    courier?: { id: number };
}
interface Page {
    data: User[];
    links: { url: string | null; label: string; active: boolean }[];
}
defineProps<{ rows: Page; roles: string[] }>();
const modal = ref(false);
const id = ref<number | null>(null);
const selectedUser = ref<User | null>(null);
const confirmation = ref<"status" | "delete" | null>(null);
const actionProcessing = ref(false);
const actionWarning = ref("");
const form = useForm({
    name: "",
    username: "",
    email: "",
    role: "Staff",
    is_active: true,
});
const create = (role = "Staff") => {
    id.value = null;
    selectedUser.value = null;
    form.reset();
    form.role = role;
    modal.value = true;
};
const edit = (user: User) => {
    id.value = user.id;
    selectedUser.value = user;
    form.name = user.name;
    form.username = user.username;
    form.email = user.email;
    form.role = user.roles[0]?.name ?? "Staff";
    form.is_active = user.is_active;
    modal.value = true;
};
const submit = () =>
    id.value
        ? form.put(route("users.update", id.value), {
              onSuccess: () => (modal.value = false),
          })
        : form.post(route("users.store"), {
              onSuccess: () => (modal.value = false),
          });
const closeConfirmation = () => {
    if (!actionProcessing.value) confirmation.value = null;
};
const runUserAction = () => {
    if (!selectedUser.value || !confirmation.value) return;
    actionProcessing.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.value = null;
            modal.value = false;
        },
        onError: (errors: Record<string, string>) => {
            confirmation.value = null;
            actionWarning.value = errors.action ?? "Tindakan tidak dapat dilakukan.";
        },
        onFinish: () => (actionProcessing.value = false),
    };

    if (confirmation.value === "delete") {
        router.delete(route("users.destroy", selectedUser.value.id), options);
        return;
    }
    router.patch(
        route("users.status", selectedUser.value.id),
        { is_active: !selectedUser.value.is_active },
        options,
    );
};
onMounted(() => {
    const query = new URLSearchParams(location.search);
    if (query.get("create") === "1") {
        create(query.get("role") === "Kurir" ? "Kurir" : "Staff");
    }
});
</script>

<template>
    <Head title="Pengguna" />
    <AuthenticatedLayout>
        <template #breadcrumb>Pengaturan / Pengguna</template>
        <div class="mb-6 flex items-end justify-between">
            <div>
                <h1 class="page-title">Manajemen Pengguna</h1>
                <p class="page-subtitle">
                    Atur username login, status, role, dan permission akses.
                </p>
            </div>
            <AppButton @click="create()">＋ Tambah Pengguna</AppButton>
        </div>
        <section class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Login Terakhir</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in rows.data" :key="user.id">
                            <td class="font-semibold">{{ user.name }}</td>
                            <td class="font-medium text-sky-700">
                                {{ user.username }}
                            </td>
                            <td>{{ user.email }}</td>
                            <td>
                                {{ user.roles.map((role) => role.name).join(", ") }}
                            </td>
                            <td>
                                {{
                                    user.last_login_at
                                        ? new Date(user.last_login_at).toLocaleString("id-ID")
                                        : "-"
                                }}
                            </td>
                            <td>{{ user.is_active ? "Aktif" : "Nonaktif" }}</td>
                            <td>
                                <button
                                    class="rounded border px-3 py-1 text-xs"
                                    @click="edit(user)"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t p-4">
                <Pagination :links="rows.links" />
            </div>
        </section>
        <AppModal
            :show="modal"
            :title="id ? 'Edit Pengguna' : 'Tambah Pengguna'"
            @close="modal = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label>
                    <span class="label">Nama *</span>
                    <AppInput v-model="form.name" required />
                </label>
                <label>
                    <span class="label">Username *</span>
                    <AppInput
                        v-model="form.username"
                        placeholder="contoh: kasir_01"
                        required
                    />
                </label>
                <label>
                    <span class="label">Email *</span>
                    <AppInput v-model="form.email" type="email" required />
                </label>
                <label>
                    <span class="label">Role *</span>
                    <AppSelect v-model="form.role">
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </AppSelect>
                </label>
                <div
                    v-if="!id"
                    class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800"
                >
                    <strong class="block">Password awal: 12345678</strong>
                    <span class="mt-1 block text-xs leading-5">
                        Pengguna dapat mengganti password sendiri melalui menu Profil.
                    </span>
                </div>
                <p v-if="form.role === 'Kurir'" class="rounded-lg bg-blue-50 p-3 text-sm text-blue-700 sm:col-span-2">
                    Data Kurir dan kode kurir akan dibuat otomatis setelah pengguna disimpan.
                </p>
                <label v-if="!id" class="flex items-center gap-2 pt-6">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded text-sky-500"
                    />
                    Aktif
                </label>
                <div v-else class="pt-5">
                    <span class="label">Status Akun</span>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                        :class="selectedUser?.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ selectedUser?.is_active ? "Aktif" : "Nonaktif" }}
                    </span>
                </div>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <div
                    v-if="id && selectedUser"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2"
                >
                    <h3 class="text-sm font-bold text-slate-900">Aksi Pengguna</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Nonaktifkan untuk memblokir login tanpa menghapus histori. Hapus hanya tersedia jika pengguna belum memiliki data transaksi.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border px-4 py-2 text-sm font-semibold transition"
                            :class="selectedUser.is_active ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                            @click="confirmation = 'status'"
                        >
                            {{ selectedUser.is_active ? 'Nonaktifkan User' : 'Aktifkan User' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                            @click="confirmation = 'delete'"
                        >
                            Hapus User
                        </button>
                    </div>
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="modal = false">
                        Batal
                    </AppButton>
                    <AppButton type="submit" :disabled="form.processing">
                        {{ form.processing ? "Menyimpan..." : "Simpan" }}
                    </AppButton>
                </div>
            </form>
        </AppModal>
        <AppModal
            :show="confirmation !== null"
            :title="confirmation === 'delete' ? 'Hapus Pengguna' : selectedUser?.is_active ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna'"
            @close="closeConfirmation"
        >
            <div class="space-y-5">
                <div
                    class="rounded-xl border p-4 text-sm leading-6"
                    :class="confirmation === 'delete' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800'"
                >
                    <template v-if="confirmation === 'delete'">
                        Hapus akun <strong>{{ selectedUser?.name }}</strong>? Penghapusan akan ditolak jika akun masih memiliki histori transaksi. Tindakan ini tidak dapat dibatalkan.
                    </template>
                    <template v-else-if="selectedUser?.is_active">
                        Nonaktifkan akun <strong>{{ selectedUser?.name }}</strong>? Pengguna tidak akan dapat login sampai akun diaktifkan kembali.
                    </template>
                    <template v-else>
                        Aktifkan kembali akun <strong>{{ selectedUser?.name }}</strong> agar pengguna dapat login.
                    </template>
                </div>
                <div class="flex justify-end gap-2">
                    <AppButton variant="secondary" :disabled="actionProcessing" @click="closeConfirmation">Batal</AppButton>
                    <AppButton :variant="confirmation === 'delete' ? 'danger' : 'primary'" :disabled="actionProcessing" @click="runUserAction">
                        {{ actionProcessing ? 'Memproses...' : confirmation === 'delete' ? 'Ya, Hapus User' : selectedUser?.is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}
                    </AppButton>
                </div>
            </div>
        </AppModal>
        <ActionWarningModal
            :show="Boolean(actionWarning)"
            :message="actionWarning"
            title="Aksi Pengguna Ditolak"
            @close="actionWarning = ''"
        />
    </AuthenticatedLayout>
</template>
