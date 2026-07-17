<script setup lang="ts">
defineProps<{ name: string }>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="name === 'dashboard'"><path d="M3 10.8 12 3l9 7.8"/><path d="M5.5 9.5V21h13V9.5M9 21v-6h6v6"/></template>
        <template v-else-if="name === 'customer'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></template>
        <template v-else-if="name === 'box'"><path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v8l-9 5-9-5V8Z"/><path d="M12 13v8"/></template>
        <template v-else-if="name === 'category'"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></template>
        <template v-else-if="name === 'courier'"><path d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></template>
        <template v-else-if="name === 'map'"><path d="m3 6 5-3 8 3 5-3v15l-5 3-8-3-5 3V6Z"/><path d="M8 3v15M16 6v15"/></template>
        <template v-else-if="name === 'invoice'"><path d="M6 2h9l4 4v16H6z"/><path d="M14 2v5h5M9 12h7M9 16h7"/></template>
        <template v-else-if="name === 'facture'"><path d="M4 3h13v16H4z"/><path d="M8 7h5M8 11h5M8 15h3"/><path d="M17 7h3v14H7v-2"/></template>
        <template v-else-if="name === 'payment'"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></template>
        <template v-else-if="name === 'receivable'"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></template>
        <template v-else-if="name === 'cash-in'"><path d="M4 7h16v12H4zM4 10h16"/><path d="M12 16v-5M9.5 13.5 12 11l2.5 2.5"/></template>
        <template v-else-if="name === 'cash-out'"><path d="M4 7h16v12H4zM4 10h16"/><path d="M12 11v5M9.5 13.5 12 16l2.5-2.5"/></template>
        <template v-else-if="name === 'report'"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></template>
        <template v-else-if="name === 'company'"><path d="M3 21V7l9-4 9 4v14M3 10h18M8 14h1M12 14h1M16 14h1M10 21v-4h4v4"/></template>
        <template v-else-if="name === 'user'"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></template>
        <template v-else-if="name === 'audit'"><path d="M4 4h16v16H4zM8 9h8M8 13h5M8 17h3"/><path d="m16 16 1.5 1.5L21 14"/></template>
        <template v-else><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></template>
    </svg>
</template>
