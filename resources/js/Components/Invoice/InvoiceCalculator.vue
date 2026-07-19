<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from "vue";

const props = defineProps<{ show: boolean }>();
const emit = defineEmits<{ (event: "close"): void }>();

const entry = ref("0");
const accumulator = ref<number | null>(null);
const pendingOperator = ref<string | null>(null);
const waitingForEntry = ref(false);
const copied = ref(false);
const calculatorWindow = ref<HTMLElement | null>(null);
const position = ref({ left: 0, top: 0 });
const dragOffset = ref({ x: 0, y: 0 });
const dragging = ref(false);

const formattedEntry = computed(() => {
    if (entry.value === "Error") return entry.value;
    const negative = entry.value.startsWith("-");
    const unsigned = negative ? entry.value.slice(1) : entry.value;
    const [whole, decimal] = unsigned.split(".");
    const grouped = (whole || "0").replace(/\B(?=(\d{3})+(?!\d))/g, ".");

    return `${negative ? "-" : ""}${grouped}${decimal !== undefined ? `,${decimal}` : ""}`;
});

const reset = () => {
    entry.value = "0";
    accumulator.value = null;
    pendingOperator.value = null;
    waitingForEntry.value = false;
    copied.value = false;
};

watch(
    () => props.show,
    async (show) => {
        if (!show) return;
        reset();
        await nextTick();
        const width = calculatorWindow.value?.offsetWidth ?? 520;
        const height = calculatorWindow.value?.offsetHeight ?? 650;
        position.value = {
            left: Math.max(12, (window.innerWidth - width) / 2),
            top: Math.max(12, Math.min(80, window.innerHeight - height - 12)),
        };
    },
);

const clampPosition = (left: number, top: number) => {
    const width = calculatorWindow.value?.offsetWidth ?? 520;
    const height = calculatorWindow.value?.offsetHeight ?? 650;
    return {
        left: Math.min(Math.max(0, left), Math.max(0, window.innerWidth - width)),
        top: Math.min(Math.max(0, top), Math.max(0, window.innerHeight - height)),
    };
};

const stopDragging = () => {
    dragging.value = false;
    window.removeEventListener("pointermove", moveCalculator);
    window.removeEventListener("pointerup", stopDragging);
    window.removeEventListener("pointercancel", stopDragging);
};

const moveCalculator = (event: PointerEvent) => {
    if (!dragging.value) return;
    position.value = clampPosition(
        event.clientX - dragOffset.value.x,
        event.clientY - dragOffset.value.y,
    );
};

const startDragging = (event: PointerEvent) => {
    if (event.button !== 0) return;
    dragging.value = true;
    dragOffset.value = {
        x: event.clientX - position.value.left,
        y: event.clientY - position.value.top,
    };
    window.addEventListener("pointermove", moveCalculator);
    window.addEventListener("pointerup", stopDragging);
    window.addEventListener("pointercancel", stopDragging);
};

onUnmounted(stopDragging);

const inputDigit = (digit: string) => {
    copied.value = false;
    if (entry.value === "Error" || waitingForEntry.value) {
        entry.value = digit;
        waitingForEntry.value = false;
        return;
    }
    if (entry.value.replace("-", "").replace(".", "").length >= 15) return;
    entry.value = entry.value === "0" ? digit : entry.value + digit;
};

const inputDecimal = () => {
    copied.value = false;
    if (entry.value === "Error" || waitingForEntry.value) {
        entry.value = "0.";
        waitingForEntry.value = false;
    } else if (!entry.value.includes(".")) {
        entry.value += ".";
    }
};

const calculate = (left: number, right: number, operator: string) => {
    if (operator === "+") return left + right;
    if (operator === "−") return left - right;
    if (operator === "×") return left * right;
    if (operator === "÷") return right === 0 ? Number.NaN : left / right;
    return right;
};

const setResult = (value: number) => {
    if (!Number.isFinite(value)) {
        entry.value = "Error";
        accumulator.value = null;
        pendingOperator.value = null;
        waitingForEntry.value = true;
        return;
    }
    entry.value = String(Number(value.toFixed(10)));
};

const chooseOperator = (operator: string) => {
    const current = Number(entry.value);
    if (!Number.isFinite(current)) {
        reset();
        return;
    }
    if (pendingOperator.value && accumulator.value !== null && !waitingForEntry.value) {
        const result = calculate(accumulator.value, current, pendingOperator.value);
        setResult(result);
        if (!Number.isFinite(result)) return;
        accumulator.value = result;
    } else if (accumulator.value === null || !waitingForEntry.value) {
        accumulator.value = current;
    }
    pendingOperator.value = operator;
    waitingForEntry.value = true;
    copied.value = false;
};

const equals = () => {
    if (!pendingOperator.value || accumulator.value === null || entry.value === "Error") return;
    const result = calculate(accumulator.value, Number(entry.value), pendingOperator.value);
    setResult(result);
    accumulator.value = Number.isFinite(result) ? result : null;
    pendingOperator.value = null;
    waitingForEntry.value = true;
    copied.value = false;
};

const toggleSign = () => {
    if (entry.value === "0" || entry.value === "Error") return;
    entry.value = entry.value.startsWith("-") ? entry.value.slice(1) : `-${entry.value}`;
};

const percent = () => {
    if (entry.value === "Error") return;
    setResult(Number(entry.value) / 100);
};

const backspace = () => {
    if (waitingForEntry.value || entry.value === "Error") return;
    entry.value = entry.value.length <= 1 || (entry.value.startsWith("-") && entry.value.length === 2)
        ? "0"
        : entry.value.slice(0, -1);
};

const copyResult = async () => {
    if (entry.value === "Error") return;
    await navigator.clipboard.writeText(entry.value);
    copied.value = true;
};

const keys = ["7", "8", "9", "4", "5", "6", "1", "2", "3", "0"];
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="pointer-events-none fixed inset-0 z-50">
            <section
                ref="calculatorWindow"
                aria-label="Calculator"
                class="pointer-events-auto absolute w-[min(520px,calc(100vw-24px))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
                :style="{ left: `${position.left}px`, top: `${position.top}px` }"
            >
                <header
                    class="flex touch-none select-none items-center justify-between border-b border-slate-200 px-5 py-3.5"
                    :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
                    title="Geser Calculator"
                    @pointerdown="startDragging"
                >
                    <div class="flex items-center gap-2">
                        <svg aria-hidden="true" class="h-5 w-5 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="2.5" width="16" height="19" rx="2" />
                            <path d="M7.5 6.5h9M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01M16 19h.01" stroke-linecap="round" />
                        </svg>
                        <h2 class="text-lg font-bold text-slate-900">Calculator</h2>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup Calculator"
                        class="cursor-pointer rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        @pointerdown.stop
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </header>

                <div class="p-4">
            <div class="mb-3 rounded-xl bg-slate-900 px-4 py-5 text-right text-white">
                <div class="h-5 text-xs text-slate-400">{{ pendingOperator ?? "" }}</div>
                <output aria-live="polite" class="block min-h-10 break-all text-3xl font-bold tracking-tight">
                    {{ formattedEntry }}
                </output>
            </div>

            <div class="grid grid-cols-4 gap-2" aria-label="Tombol calculator">
                <button type="button" class="calculator-key text-red-600" @click="reset">AC</button>
                <button type="button" class="calculator-key" @click="toggleSign">+/−</button>
                <button type="button" class="calculator-key" @click="percent">%</button>
                <button type="button" class="calculator-key calculator-operator" @click="chooseOperator('÷')">÷</button>

                <template v-for="(digit, index) in keys.slice(0, 9)" :key="digit">
                    <button type="button" class="calculator-key" @click="inputDigit(digit)">{{ digit }}</button>
                    <button
                        v-if="index === 2 || index === 5 || index === 8"
                        type="button"
                        class="calculator-key calculator-operator"
                        @click="chooseOperator(index === 2 ? '×' : index === 5 ? '−' : '+')"
                    >
                        {{ index === 2 ? "×" : index === 5 ? "−" : "+" }}
                    </button>
                </template>

                <button type="button" class="calculator-key" aria-label="Hapus angka terakhir" @click="backspace">⌫</button>
                <button type="button" class="calculator-key" @click="inputDigit('0')">0</button>
                <button type="button" class="calculator-key" @click="inputDecimal">,</button>
                <button type="button" class="calculator-key bg-sky-500 text-white hover:bg-sky-600" @click="equals">=</button>
            </div>

            <button
                type="button"
                class="mt-3 w-full rounded-lg border border-sky-300 px-4 py-2.5 text-sm font-semibold text-sky-700 transition hover:bg-sky-50"
                @click="copyResult"
            >
                {{ copied ? "Hasil Tersalin" : "Salin Hasil" }}
            </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.calculator-key {
    @apply min-h-12 rounded-lg border border-slate-200 bg-slate-50 text-lg font-semibold text-slate-800 transition hover:bg-slate-100 active:scale-95;
}
.calculator-operator {
    @apply border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100;
}
</style>
