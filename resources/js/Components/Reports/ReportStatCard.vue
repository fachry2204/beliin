<script setup lang="ts">
import ReportIcon from "@/Components/Reports/ReportIcon.vue";
withDefaults(
    defineProps<{
        label: string;
        value: string;
        detail?: string;
        tone?: "sky" | "emerald" | "amber" | "violet";
        icon?: "invoice" | "combined" | "cash" | "margin";
    }>(),
    { detail: "", tone: "sky", icon: "invoice" },
);
const styles = {
    sky: "bg-sky-50 text-sky-600",
    emerald: "bg-emerald-50 text-emerald-600",
    amber: "bg-amber-50 text-amber-600",
    violet: "bg-violet-50 text-violet-600",
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="styles[tone]">
                <ReportIcon :type="icon" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-slate-500">{{ label }}</p>
                <p class="mt-1 truncate text-lg font-bold tracking-tight text-slate-900">{{ value }}</p>
                <p v-if="detail" class="mt-0.5 text-xs text-slate-400">{{ detail }}</p>
            </div>
        </div>
    </div>
</template>
