<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        value: string;
        label?: string;
    }>(),
    { label: 'Copy' },
);

const emit = defineEmits<{ copied: [] }>();

const copied = ref(false);
const failed = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    clearTimeout(resetTimer);

    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        failed.value = false;
        emit('copied');
    } catch {
        failed.value = true;
    }

    resetTimer = setTimeout(() => {
        copied.value = false;
        failed.value = false;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(resetTimer));
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        @click="copy"
    >
        {{ copied ? 'Copied!' : failed ? 'Copy failed' : label }}
    </button>
</template>
