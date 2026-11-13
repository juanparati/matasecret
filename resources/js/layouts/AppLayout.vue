<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { logout } from '@/routes';
import { create } from '@/routes/secrets';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <header
            class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3"
            >
                <Link
                    :href="create()"
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <span aria-hidden="true">🔐</span>
                    {{ page.props.name }}
                </Link>

                <div v-if="user" class="flex min-w-0 items-center gap-3">
                    <img
                        v-if="user.avatar"
                        :src="user.avatar"
                        alt=""
                        class="size-7 rounded-full"
                        referrerpolicy="no-referrer"
                    />
                    <span
                        class="hidden truncate text-sm text-gray-600 sm:inline dark:text-gray-400"
                    >
                        {{ user.email }}
                    </span>
                    <Link
                        :href="logout()"
                        as="button"
                        class="rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                    >
                        Sign out
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer
            class="mx-auto w-full max-w-3xl px-4 py-6 text-center text-xs text-gray-500 dark:text-gray-500"
        >
            Secrets are encrypted and kept in memory only. They may disappear
            earlier than planned during maintenance.
        </footer>
    </div>
</template>
