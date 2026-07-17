<script setup lang="ts">
defineProps<{ type: "invoice" | "combined" | "cash" | "margin" }>();
</script>

<template>
    <svg v-if="type === 'invoice'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M7 3.75h7l3 3V20.25H7V3.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
        <path d="M14 3.75v3h3M9.5 11h5M9.5 14h5M9.5 17h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
    </svg>
    <svg v-else-if="type === 'combined'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 3.75h10.5V18H6V3.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
        <path d="M9 7.5h4.5M9 11h4.5M9 14.5h3M9 20.25h9V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg v-else-if="type === 'cash'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 7.25h14.25A1.75 1.75 0 0 1 20 9v8a1.75 1.75 0 0 1-1.75 1.75H4V7.25Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
        <path d="M4 7.25 15.5 4v3.25M15.5 11.25H20v3.5h-4.5a1.75 1.75 0 1 1 0-3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
    </svg>
    <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M5 18.5V13M10 18.5V9M15 18.5V5.5M20 18.5V3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        <path d="m4.5 9.5 4-3 4 1.5 6-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
