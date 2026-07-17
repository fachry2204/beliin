<script setup lang="ts">
import { onMounted, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppButton from "@/Components/UI/AppButton.vue";
import AppInput from "@/Components/UI/AppInput.vue";
import AppSelect from "@/Components/UI/AppSelect.vue";
import AppModal from "@/Components/UI/AppModal.vue";
import Pagination from "@/Components/UI/Pagination.vue";

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
const form = useForm({
    name: "",
    username: "",
    email: "",
    password: "",
    role: "Staff",
    is_active: true,
});
const create = (role = "Staff") => {
    id.value = null;
    form.reset();
    form.role = role;
    modal.value = true;
};
const edit = (user: User) => {
    id.value = user.id;
    form.name = user.name;
    form.username = user.username;
    form.email = user.email;
    form.password = "";
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
            <AppButton @click="create">＋ Tambah Pengguna</AppButton>
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
                    <span class="label">
                        Password {{ id ? "(opsional)" : "*" }}
                    </span>
                    <AppInput
                        v-model="form.password"
                        type="password"
                        :required="!id"
                    />
                    <span class="mt-1 block text-xs text-slate-500">
                        {{ id ? "Kosongkan jika tidak diubah." : "Minimal 8 karakter." }}
                    </span>
                </label>
                <label>
                    <span class="label">Role *</span>
                    <AppSelect v-model="form.role">
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </AppSelect>
                </label>
                <p v-if="form.role === 'Kurir'" class="rounded-lg bg-blue-50 p-3 text-sm text-blue-700 sm:col-span-2">
                    Data Kurir dan kode kurir akan dibuat otomatis setelah pengguna disimpan.
                </p>
                <label class="flex items-center gap-2 pt-6">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="rounded text-sky-500"
                    />
                    Aktif
                </label>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <AppButton variant="secondary" @click="modal = false">
                        Batal
                    </AppButton>
                    <AppButton type="submit">Simpan</AppButton>
                </div>
            </form>
        </AppModal>
    </AuthenticatedLayout>
</template>
