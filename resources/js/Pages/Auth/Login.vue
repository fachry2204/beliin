<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import type { PageProps } from "@/types";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const page = usePage<
    PageProps<{ company: { name: string; logo_url?: string | null } }>
>();
const company = computed(() => page.props.company);
const showPassword = ref(false);
const form = useForm({
    username: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Masuk" />

    <main class="min-h-screen bg-white text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-2">
            <section
                class="relative hidden overflow-hidden bg-[#f4f7ff] lg:flex lg:items-center lg:justify-center"
                aria-label="Identitas perusahaan"
            >
                <div class="absolute -left-36 -top-32 h-96 w-96 rounded-full bg-[#487fff]/10" />
                <div class="absolute -bottom-44 -right-28 h-[32rem] w-[32rem] rounded-full bg-[#487fff]/10" />
                <div class="absolute left-[18%] top-[22%] h-24 w-24 rounded-[2rem] border border-[#487fff]/20 bg-white/50 rotate-12" />
                <div class="absolute bottom-[20%] right-[16%] h-16 w-16 rounded-2xl bg-[#487fff]/15 -rotate-12" />

                <div class="relative z-10 mx-auto max-w-lg px-12 text-center">
                    <div class="mx-auto mb-8 flex h-28 w-28 items-center justify-center rounded-[2rem] bg-white shadow-[0_24px_60px_rgba(72,127,255,.18)] ring-1 ring-slate-200/70">
                        <img
                            v-if="company.logo_url"
                            :src="company.logo_url"
                            :alt="`Logo ${company.name}`"
                            class="h-20 w-20 object-contain"
                        />
                        <span v-else class="text-5xl font-black text-[#487fff]">{{ company.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">{{ company.name }}</h1>
                    <p class="mx-auto mt-4 max-w-md text-lg leading-8 text-slate-500">
                        Kelola invoice, faktur, kas, margin, dan pengiriman dalam satu sistem terpadu.
                    </p>
                    <div class="mx-auto mt-10 grid max-w-md grid-cols-3 gap-3" aria-hidden="true">
                        <div class="h-2 rounded-full bg-[#487fff]" />
                        <div class="h-2 rounded-full bg-[#8daeff]" />
                        <div class="h-2 rounded-full bg-[#c6d6ff]" />
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen items-center justify-center px-6 py-10 sm:px-10 lg:px-16">
                <div class="w-full max-w-[464px]">
                    <div class="mb-9 flex items-center gap-3 lg:mb-10">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                            <img
                                v-if="company.logo_url"
                                :src="company.logo_url"
                                :alt="`Logo ${company.name}`"
                                class="h-9 w-9 object-contain"
                            />
                            <span v-else class="text-xl font-black text-[#487fff]">{{ company.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <span class="truncate text-xl font-extrabold tracking-tight text-slate-900">{{ company.name }}</span>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900">Masuk ke akun Anda</h2>
                        <p class="mt-3 text-base text-slate-500">Selamat datang kembali. Silakan masukkan detail login Anda.</p>
                    </div>

                    <div v-if="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {{ status }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                                <input
                                    id="username"
                                    v-model="form.username"
                                    type="text"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="Masukkan username"
                                    class="h-14 w-full rounded-xl border border-slate-300 bg-slate-50 pl-12 pr-4 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#487fff] focus:bg-white focus:ring-4 focus:ring-[#487fff]/10"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.username" />
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="4" y="10" width="16" height="11" rx="2" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                </svg>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Masukkan password"
                                    class="h-14 w-full rounded-xl border border-slate-300 bg-slate-50 pl-12 pr-12 text-[15px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#487fff] focus:bg-white focus:ring-4 focus:ring-[#487fff]/10"
                                />
                                <button
                                    type="button"
                                    class="absolute right-4 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg v-if="!showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 5.2A11.7 11.7 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.1 3.1M6.6 6.6C3.6 8.5 2 12 2 12s3.5 7 10 7a10.7 10.7 0 0 0 4.1-.8" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between gap-4 pt-1">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#487fff] focus:ring-[#487fff]" />
                                Ingat saya
                            </label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-semibold text-[#487fff] hover:underline"
                            >Lupa password?</Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-2 flex h-14 w-full items-center justify-center rounded-xl bg-[#487fff] px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(72,127,255,.24)] transition hover:bg-[#376ee8] focus:outline-none focus:ring-4 focus:ring-[#487fff]/25 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? "Memproses..." : "Masuk" }}
                        </button>
                    </form>

                    <p class="mt-9 text-center text-xs leading-5 text-slate-400">
                        Sistem Invoice & Keuangan · {{ company.name }}
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
