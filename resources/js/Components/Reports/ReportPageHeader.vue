<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import AppButton from "@/Components/UI/AppButton.vue";
defineProps<{ title: string; description: string }>();
</script>

<template>
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="page-title">{{ title }}</h1>
            <p class="page-subtitle">{{ description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <slot name="actions" />
            <Link :href="route('reports.index')"><AppButton variant="secondary">Kembali</AppButton></Link>
        </div>
    </div>
</template>
