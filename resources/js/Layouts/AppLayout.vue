<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const isActive = (path) =>
    path === '/' ? page.url === '/' : page.url.startsWith(path);
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <main class="flex-1 px-4 pt-[calc(env(safe-area-inset-top)+1rem)] pb-28">
            <slot />
        </main>

        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 backdrop-blur"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <div class="mx-auto grid max-w-lg grid-cols-3">
                <Link
                    href="/"
                    class="flex flex-col items-center gap-0.5 py-3 text-[11px] font-semibold tracking-widest uppercase"
                    :class="isActive('/') || isActive('/sessions') ? 'text-volt' : 'text-muted'"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M6.5 6.5v11M17.5 6.5v11M3 9v6M21 9v6M6.5 12h11" />
                    </svg>
                    Sessions
                </Link>
                <Link
                    href="/progression"
                    class="flex flex-col items-center gap-0.5 py-3 text-[11px] font-semibold tracking-widest uppercase"
                    :class="isActive('/progression') ? 'text-volt' : 'text-muted'"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 20h18M5 16l4-5 4 3 6-8" />
                    </svg>
                    Progress
                </Link>
                <button
                    class="flex flex-col items-center gap-0.5 py-3 text-[11px] font-semibold tracking-widest uppercase text-muted"
                    @click="router.post('/logout')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    Out
                </button>
            </div>
        </nav>
    </div>
</template>
