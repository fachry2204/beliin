<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
    modelValue: string | number;
    required?: boolean;
    placeholder?: string;
}>();
const emit = defineEmits<{
    (e: "update:modelValue", value: string): void;
}>();

const digitsOnly = (value: string | number) =>
    String(value ?? "")
        .replace(/\D/g, "")
        .replace(/^0+(?=\d)/, "");
const modelDigits = (value: string | number) => {
    const normalized = String(value ?? "").trim();

    // Nilai decimal dari database (contoh: 50000.00) adalah rupiah utuh,
    // bukan 5.000.000 setelah tanda titik dihapus.
    if (/^\d+\.\d{1,2}$/.test(normalized)) {
        return normalized.split(".")[0].replace(/^0+(?=\d)/, "");
    }

    return digitsOnly(normalized);
};
const formatThousands = (value: string) =>
    value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
const displayValue = computed(() => formatThousands(modelDigits(props.modelValue)));
const updateValue = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const value = digitsOnly(input.value);
    input.value = formatThousands(value);
    emit("update:modelValue", value);
};
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
            >Rp</span
        >
        <input
            :value="displayValue"
            type="text"
            inputmode="numeric"
            class="w-full rounded-lg border-slate-300 bg-white pl-10 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500"
            :placeholder="placeholder ?? '0'"
            :required="required"
            @input="updateValue"
        />
    </div>
</template>
