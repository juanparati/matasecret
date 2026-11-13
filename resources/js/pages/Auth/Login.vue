<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { describeDomains } from '@/lib/domains';
import { rememberPendingLink } from '@/lib/pendingLink';
import { redirect, test } from '@/routes/auth';

const props = defineProps<{
    provider: string;
    testAccount: boolean;
    domains: string[];
    error: string | null;
    intendedPath: string | null;
}>();

const allowedDomains = computed(() => describeDomains(props.domains));

onMounted(() => {
    // A guest opening /s/{id}#key lands here with the key still in the URL
    // fragment. Keep it for after the sign-in round trip, then drop it from
    // this page's address bar.
    if (window.location.hash.length > 1 && props.intendedPath !== null) {
        rememberPendingLink(props.intendedPath, window.location.hash);
        history.replaceState(
            history.state,
            '',
            window.location.pathname + window.location.search,
        );
    }
});
</script>

<template>
    <Head title="Sign in" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-4 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <div
            class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="text-4xl" aria-hidden="true">🔐</div>
            <h1 class="mt-4 text-xl font-semibold">
                {{ $page.props.name }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Share passwords and other secrets that destroy themselves.
            </p>
            <p
                v-if="testAccount"
                class="mt-2 text-sm text-gray-600 dark:text-gray-400"
            >
                Sign in with the test account to continue.
            </p>
            <p v-else class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Sign in with your {{ allowedDomains }} {{ provider }} account to
                continue.
            </p>

            <p
                v-if="intendedPath?.startsWith('/s/')"
                class="mt-4 rounded-md bg-indigo-50 px-3 py-2 text-sm text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"
            >
                Someone shared a secret with you. Sign in to view it.
            </p>

            <p
                v-if="error"
                role="alert"
                class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/15 dark:text-red-300"
            >
                {{ error }}
            </p>

            <Form
                v-if="testAccount"
                v-bind="test.form()"
                :reset-on-error="['password']"
                class="mt-6 flex flex-col gap-4 text-left"
                #default="{ errors, processing }"
            >
                <div class="flex flex-col gap-1">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="username"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                    />
                    <p
                        v-if="errors.email"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errors.email }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-medium">
                        Password
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none dark:border-gray-700 dark:bg-gray-950"
                    />
                    <p
                        v-if="errors.password"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errors.password }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60"
                >
                    Sign in
                </button>
            </Form>

            <a
                v-else
                :href="redirect.url()"
                class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            >
                Sign in with {{ provider }}
            </a>
        </div>
    </div>
</template>
