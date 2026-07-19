<script setup lang="ts">
import AppButton from "@/Components/UI/AppButton.vue";
import AppModal from "@/Components/UI/AppModal.vue";

defineProps<{
    show: boolean;
    message: string;
    title?: string;
}>();

defineEmits<{ (event: "close"): void }>();
</script>

<template>
    <AppModal
        :show="show"
        :title="title || 'Tindakan Tidak Dapat Dilakukan'"
        @close="$emit('close')"
    >
        <div class="space-y-5">
            <div
                role="alert"
                class="flex gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900"
            >
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xl font-bold text-amber-700"
                    aria-hidden="true"
                    >!</span
                >
                <div>
                    <h3 class="font-bold">Data masih digunakan</h3>
                    <p class="mt-1 text-sm leading-6">{{ message }}</p>
                </div>
            </div>
            <div class="flex justify-end">
                <AppButton @click="$emit('close')">Mengerti</AppButton>
            </div>
        </div>
    </AppModal>
</template>
