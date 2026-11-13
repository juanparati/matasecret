<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import { useCountdown } from '@/composables/useCountdown';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatRelative } from '@/lib/datetime';
import { httpErrorMessage } from '@/lib/http';
import { takePendingLinkHash } from '@/lib/pendingLink';
import { create, reveal } from '@/routes/secrets';

const props = defineProps<{
    id: string;
    secret: {
        sharedBy: string | null;
        expiresAt: string;
        selfDestruct: boolean;
    } | null;
    visibleSeconds: number;
}>();

const unavailableMessage =
    'This secret does not exist or is no longer available.';

const revealForm = useHttp<{ key: string }, { content: string }>({ key: '' });

const content = ref<string | null>(null);
const isVisible = ref(false);
const copied = ref(false);
const error = ref<string | null>(
    props.secret === null ? unavailableMessage : null,
);

const countdown = useCountdown(props.visibleSeconds, () => {
    isVisible.value = false;
});

onMounted(() => {
    let hash = window.location.hash;

    // Restore the key kept in session storage across the Google sign-in.
    if (hash.length <= 1) {
        hash = takePendingLinkHash(window.location.pathname) ?? '';

        if (hash.length > 1) {
            history.replaceState(
                history.state,
                '',
                window.location.pathname + hash,
            );
        }
    }

    revealForm.key = decodeURIComponent(hash.slice(1));

    if (props.secret !== null && revealForm.key === '') {
        error.value =
            'This link is incomplete. Ask the sender for the full link.';
    }
});

async function revealSecret() {
    error.value = null;

    try {
        const response = await revealForm.post(reveal.url(props.id));

        content.value = response.content;
        show();
    } catch (exception) {
        error.value = httpErrorMessage(exception, unavailableMessage);
    }
}

function show() {
    isVisible.value = true;
    countdown.start();
}

function hide() {
    isVisible.value = false;
    countdown.stop();
}
</script>

<template>
    <Head title="View secret" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <h1 class="text-2xl font-semibold">
                {{
                    secret === null
                        ? 'Secret unavailable'
                        : 'A secret was shared with you'
                }}
            </h1>

            <template v-if="secret !== null">
                <dl
                    class="grid gap-x-6 gap-y-2 rounded-xl border border-gray-200 bg-white p-5 text-sm sm:grid-cols-[auto_1fr] dark:border-gray-800 dark:bg-gray-900"
                >
                    <dt class="font-medium text-gray-500">Shared by</dt>
                    <dd>{{ secret.sharedBy ?? 'Unknown' }}</dd>
                    <dt class="font-medium text-gray-500">Expires</dt>
                    <dd>
                        {{ formatDateTime(secret.expiresAt) }}
                        <span class="text-gray-500">
                            ({{ formatRelative(secret.expiresAt) }})
                        </span>
                    </dd>
                    <dt class="font-medium text-gray-500">Views</dt>
                    <dd>
                        {{
                            secret.selfDestruct
                                ? 'Can be viewed only once'
                                : 'Can be viewed until it expires'
                        }}
                    </dd>
                </dl>

                <div
                    v-if="content === null && secret.selfDestruct"
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    You can only view this secret once. It is destroyed as soon
                    as you reveal it, so copy it before leaving this page.
                </div>
            </template>

            <p
                v-if="error"
                role="alert"
                class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/15 dark:text-red-300"
            >
                {{ error }}
            </p>

            <div
                v-if="
                    secret !== null && content === null && revealForm.key !== ''
                "
            >
                <button
                    type="button"
                    :disabled="revealForm.processing"
                    class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="revealSecret"
                >
                    {{ revealForm.processing ? 'Revealing…' : 'Reveal secret' }}
                </button>
            </div>

            <div v-if="content !== null" class="flex flex-col gap-3">
                <div class="relative">
                    <pre
                        v-show="isVisible"
                        class="max-h-[60vh] overflow-auto rounded-md border border-gray-300 bg-white px-4 py-3 font-mono text-sm break-words whitespace-pre-wrap dark:border-gray-700 dark:bg-gray-900"
                        >{{ content }}</pre>
                    <div
                        v-show="!isVisible"
                        class="rounded-md border border-dashed border-gray-300 bg-gray-100 px-4 py-6 text-center font-mono text-sm tracking-widest text-gray-500 select-none dark:border-gray-700 dark:bg-gray-900"
                        aria-label="Secret hidden"
                    >
                        ••••••••••••
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <CopyButton :value="content" @copied="copied = true" />
                    <button
                        v-if="isVisible"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="hide"
                    >
                        Hide
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="show"
                    >
                        Show
                    </button>
                    <span
                        v-if="isVisible"
                        class="text-sm text-gray-500 tabular-nums"
                    >
                        Hiding in {{ countdown.remaining.value }}s
                    </span>
                </div>

                <p
                    v-if="copied"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    Copied. Paste it somewhere safe and clear your clipboard.
                </p>
                <p
                    v-if="secret?.selfDestruct"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    This secret has been destroyed on the server. It stays on
                    this page only until you leave or reload.
                </p>
            </div>

            <div>
                <Link
                    :href="create()"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    Share a secret of your own →
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
