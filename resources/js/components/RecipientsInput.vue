<script setup lang="ts">
import { computed, ref } from 'vue';
import { belongsToDomains, describeDomains } from '@/lib/domains';

const props = defineProps<{
    domains: string[];
    max: number;
}>();

const allowedDomains = computed(() => describeDomains(props.domains));

const recipients = defineModel<string[]>({ required: true });

const draft = ref('');
const draftError = ref<string | null>(null);

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function addDraft(): boolean {
    const candidates = draft.value
        .split(/[\s,;]+/)
        .map((email) => email.trim().toLowerCase())
        .filter((email) => email !== '');

    draftError.value = null;

    for (const email of candidates) {
        if (!emailPattern.test(email)) {
            draftError.value = `${email} is not a valid email address.`;

            return false;
        }

        if (!belongsToDomains(email, props.domains)) {
            draftError.value = `Only ${allowedDomains.value} addresses can receive secret links.`;

            return false;
        }

        if (recipients.value.length >= props.max) {
            draftError.value = `You can add at most ${props.max} recipients.`;

            return false;
        }

        if (!recipients.value.includes(email)) {
            recipients.value = [...recipients.value, email];
        }
    }

    draft.value = '';

    return true;
}

function remove(email: string) {
    recipients.value = recipients.value.filter(
        (recipient) => recipient !== email,
    );
}

function onKeydown(event: KeyboardEvent) {
    if (['Enter', ',', ';', ' '].includes(event.key)) {
        event.preventDefault();
        addDraft();
    } else if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        recipients.value.length > 0
    ) {
        recipients.value = recipients.value.slice(0, -1);
    }
}

defineExpose({ addDraft });
</script>

<template>
    <div class="flex flex-col gap-1">
        <label for="recipients" class="text-sm font-medium">Recipients</label>
        <div
            class="flex flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-white px-2 py-2 shadow-xs focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-900"
        >
            <span
                v-for="email in recipients"
                :key="email"
                class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-sm text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"
            >
                {{ email }}
                <button
                    type="button"
                    class="rounded-full px-1 text-indigo-500 hover:text-indigo-800 dark:hover:text-indigo-100"
                    :aria-label="`Remove ${email}`"
                    @click="remove(email)"
                >
                    ×
                </button>
            </span>
            <input
                id="recipients"
                v-model="draft"
                type="email"
                inputmode="email"
                autocomplete="off"
                :placeholder="
                    recipients.length === 0
                        ? `name@${domains[0]}`
                        : 'Add another…'
                "
                class="min-w-40 flex-1 border-0 bg-transparent px-1 py-0.5 text-sm focus:ring-0 focus:outline-none"
                @keydown="onKeydown"
                @blur="addDraft"
            />
        </div>
        <p v-if="draftError" class="text-sm text-red-600 dark:text-red-400">
            {{ draftError }}
        </p>
        <p v-else class="text-xs text-gray-500">
            Up to {{ max }} people from {{ allowedDomains }}. Press Enter to
            add.
        </p>
    </div>
</template>
