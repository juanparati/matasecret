<script setup lang="ts">
import { computed } from 'vue';
import { toDateTimeLocalValue } from '@/lib/datetime';

export type ExpirationOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    options: ExpirationOption[];
    minSeconds: number;
    maxDays: number;
    error?: string;
    customError?: string;
}>();

const expiration = defineModel<string>('expiration', { required: true });
const customExpiresAt = defineModel<string>('customExpiresAt', {
    required: true,
});

const isCustom = computed(() => expiration.value === 'custom');

const minimum = computed(() =>
    toDateTimeLocalValue(new Date(Date.now() + props.minSeconds * 1000)),
);
const maximum = computed(() =>
    toDateTimeLocalValue(new Date(Date.now() + props.maxDays * 86_400_000)),
);

function onExpirationChange() {
    if (isCustom.value && customExpiresAt.value === '') {
        customExpiresAt.value = toDateTimeLocalValue(
            new Date(Date.now() + 2 * 3_600_000),
        );
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
        <div class="flex flex-col gap-1">
            <label for="expiration" class="text-sm font-medium">
                Expires after
            </label>
            <select
                id="expiration"
                v-model="expiration"
                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                @change="onExpirationChange"
            >
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </p>
        </div>

        <div v-if="isCustom" class="flex flex-col gap-1">
            <label for="expires_at" class="text-sm font-medium">
                Expires at (your local time)
            </label>
            <input
                id="expires_at"
                v-model="customExpiresAt"
                type="datetime-local"
                :min="minimum"
                :max="maximum"
                required
                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
            />
            <p
                v-if="customError"
                class="text-sm text-red-600 dark:text-red-400"
            >
                {{ customError }}
            </p>
        </div>
    </div>
</template>
