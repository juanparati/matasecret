<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef } from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import ExpirationSelect from '@/components/ExpirationSelect.vue';
import type { ExpirationOption } from '@/components/ExpirationSelect.vue';
import RecipientsInput from '@/components/RecipientsInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatRelative } from '@/lib/datetime';
import { httpErrorMessage, isValidationError } from '@/lib/http';
import { destroy, email, store } from '@/routes/secrets';

const props = defineProps<{
    expirationOptions: ExpirationOption[];
    defaultExpiration: string;
    maxLength: number;
    minCustomExpirationSeconds: number;
    maxExpirationDays: number;
    maxRecipients: number;
    maxMessageLength: number;
    domains: string[];
    emailLinks: boolean;
}>();

type CreatedSecret = {
    id: string;
    key: string;
    url: string;
    expiresAt: string;
    selfDestruct: boolean;
};

const secretForm = useHttp<
    {
        content: string;
        expiration: string;
        expires_at: string;
        self_destruct: boolean;
    },
    CreatedSecret
>({
    content: '',
    expiration: props.defaultExpiration,
    expires_at: '',
    self_destruct: true,
}).transform((data) => ({
    ...data,
    expires_at:
        data.expiration === 'custom' && data.expires_at !== ''
            ? new Date(data.expires_at).toISOString()
            : null,
}));

const created = ref<CreatedSecret | null>(null);
const createError = ref<string | null>(null);

const characterCount = computed(() => [...secretForm.content].length);
const isBlank = computed(() => secretForm.content.trim() === '');
const isTooLong = computed(() => characterCount.value > props.maxLength);

const link = computed(() =>
    created.value === null ? '' : `${created.value.url}#${created.value.key}`,
);

async function createSecret() {
    if (isBlank.value || isTooLong.value) {
        return;
    }

    createError.value = null;

    try {
        created.value = await secretForm.post(store.url());
        secretForm.reset();
    } catch (error) {
        if (!isValidationError(error)) {
            createError.value = httpErrorMessage(
                error,
                'The secret could not be created. Please try again.',
            );
        }
    }
}

const emailForm = useHttp<
    { key: string; recipients: string[]; message: string },
    { sent: string[] }
>({
    key: '',
    recipients: [],
    message: '',
});

const recipientsInput = useTemplateRef('recipientsInput');
const emailError = ref<string | null>(null);
const sentTo = ref<string[]>([]);

async function sendEmail() {
    if (created.value === null || recipientsInput.value?.addDraft() === false) {
        return;
    }

    emailError.value = null;
    emailForm.key = created.value.key;

    try {
        const response = await emailForm.post(email.url(created.value.id));

        sentTo.value = [...sentTo.value, ...response.sent];
        emailForm.reset('recipients', 'message');
    } catch (error) {
        if (!isValidationError(error)) {
            emailError.value = httpErrorMessage(
                error,
                'The email could not be sent. Please try again.',
            );
        }
    }
}

const destroyForm = useHttp({ key: '' });
const destroyed = ref(false);
const destroyError = ref<string | null>(null);

async function destroySecret() {
    if (
        created.value === null ||
        !window.confirm(
            'Destroy this secret now? The link will stop working immediately.',
        )
    ) {
        return;
    }

    destroyError.value = null;
    destroyForm.key = created.value.key;

    try {
        await destroyForm.delete(destroy.url(created.value.id));
        destroyed.value = true;
    } catch (error) {
        destroyError.value = httpErrorMessage(
            error,
            'The secret could not be destroyed.',
        );
    }
}

function createAnother() {
    created.value = null;
    destroyed.value = false;
    destroyError.value = null;
    emailError.value = null;
    sentTo.value = [];
    emailForm.resetAndClearErrors();
}
</script>

<template>
    <Head title="Share a secret" />

    <AppLayout>
        <form
            v-if="created === null"
            class="flex flex-col gap-6"
            @submit.prevent="createSecret"
        >
            <div>
                <h1 class="text-2xl font-semibold">Share a secret</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Write a password, token or note. It is encrypted, and only
                    people with the link can read it before it expires.
                </p>
            </div>

            <div class="flex flex-col gap-1">
                <label for="content" class="text-sm font-medium">Secret</label>
                <textarea
                    id="content"
                    v-model="secretForm.content"
                    v-focus
                    rows="10"
                    required
                    spellcheck="false"
                    autocomplete="off"
                    placeholder="Paste or type your secret here…"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-900"
                />
                <div class="flex justify-between gap-4 text-sm">
                    <p
                        v-if="secretForm.errors.content"
                        class="text-red-600 dark:text-red-400"
                    >
                        {{ secretForm.errors.content }}
                    </p>
                    <span v-else />
                    <span
                        class="shrink-0 tabular-nums"
                        :class="
                            isTooLong
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : 'text-gray-500'
                        "
                    >
                        {{ characterCount.toLocaleString() }} /
                        {{ maxLength.toLocaleString() }}
                    </span>
                </div>
            </div>

            <ExpirationSelect
                v-model:expiration="secretForm.expiration"
                v-model:custom-expires-at="secretForm.expires_at"
                :options="expirationOptions"
                :min-seconds="minCustomExpirationSeconds"
                :max-days="maxExpirationDays"
                :error="secretForm.errors.expiration"
                :custom-error="secretForm.errors.expires_at"
            />

            <label class="flex items-start gap-3">
                <input
                    v-model="secretForm.self_destruct"
                    type="checkbox"
                    class="mt-0.5 size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm">
                    <span class="font-medium">
                        Self-destruct after the first view
                    </span>
                    <span class="block text-gray-600 dark:text-gray-400">
                        The secret is deleted as soon as someone reveals it.
                    </span>
                </span>
            </label>

            <p
                v-if="createError"
                role="alert"
                class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/15 dark:text-red-300"
            >
                {{ createError }}
            </p>

            <div>
                <button
                    type="submit"
                    :disabled="secretForm.processing || isBlank || isTooLong"
                    class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{
                        secretForm.processing
                            ? 'Creating…'
                            : 'Create secret link'
                    }}
                </button>
            </div>
        </form>

        <div v-else class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ destroyed ? 'Secret destroyed' : 'Your secret link' }}
                </h1>
                <p
                    v-if="!destroyed"
                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                >
                    Expires {{ formatRelative(created.expiresAt) }} ({{
                        formatDateTime(created.expiresAt)
                    }}).
                    <template v-if="created.selfDestruct">
                        It self-destructs after the first view.
                    </template>
                    <template v-else>
                        It can be viewed until it expires.
                    </template>
                </p>
                <p v-else class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    The link no longer works.
                </p>
            </div>

            <template v-if="!destroyed">
                <div
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    Copy or send this link now. It will not be shown again.
                    <template v-if="created.selfDestruct">
                        Do not open it yourself, or it will self-destruct.
                    </template>
                </div>

                <div class="flex gap-2">
                    <input
                        :value="link"
                        type="text"
                        readonly
                        aria-label="Secret link"
                        class="min-w-0 flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm shadow-xs dark:border-gray-700 dark:bg-gray-900"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <CopyButton :value="link" label="Copy link" />
                </div>

                <form
                    v-if="emailLinks"
                    class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                    @submit.prevent="sendEmail"
                >
                    <h2 class="text-base font-semibold">Send by email</h2>

                    <RecipientsInput
                        ref="recipientsInput"
                        v-model="emailForm.recipients"
                        :domains="domains"
                        :max="maxRecipients"
                    />
                    <p
                        v-if="emailForm.errors.recipients"
                        class="-mt-3 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ emailForm.errors.recipients }}
                    </p>
                    <p
                        v-for="(message, field) in emailForm.errors"
                        v-show="String(field).startsWith('recipients.')"
                        :key="field"
                        class="-mt-3 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ message }}
                    </p>

                    <div class="flex flex-col gap-1">
                        <label for="message" class="text-sm font-medium">
                            Message
                            <span class="font-normal text-gray-500"
                                >(optional)</span
                            >
                        </label>
                        <textarea
                            id="message"
                            v-model="emailForm.message"
                            rows="3"
                            :maxlength="maxMessageLength"
                            placeholder="Never include the secret itself here."
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                        />
                        <p
                            v-if="emailForm.errors.message"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ emailForm.errors.message }}
                        </p>
                    </div>

                    <p
                        v-if="emailError"
                        role="alert"
                        class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/15 dark:text-red-300"
                    >
                        {{ emailError }}
                    </p>
                    <p
                        v-if="sentTo.length > 0"
                        role="status"
                        class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-500/15 dark:text-green-300"
                    >
                        Sent to {{ sentTo.join(', ') }}.
                    </p>

                    <div>
                        <button
                            type="submit"
                            :disabled="emailForm.processing"
                            class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                emailForm.processing ? 'Sending…' : 'Send link'
                            }}
                        </button>
                    </div>
                </form>

                <p
                    v-if="destroyError"
                    role="alert"
                    class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/15 dark:text-red-300"
                >
                    {{ destroyError }}
                </p>
            </template>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="createAnother"
                >
                    Create another secret
                </button>
                <button
                    v-if="!destroyed"
                    type="button"
                    :disabled="destroyForm.processing"
                    class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="destroySecret"
                >
                    Destroy now
                </button>
            </div>
        </div>
    </AppLayout>
</template>
