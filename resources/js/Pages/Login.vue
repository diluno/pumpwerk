<script setup>
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <Head title="Login · Workout" />
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6">
        <div class="rise-in">
            <div class="mb-10">
                <div class="mb-3 h-1.5 w-16 bg-volt" />
                <h1 class="font-display text-6xl uppercase leading-none">Work<br />out.</h1>
            </div>

            <form class="space-y-4" @submit.prevent="form.post('/login')">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold tracking-widest text-muted uppercase">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="w-full rounded-xl border border-line bg-surface px-4 py-3.5 text-lg outline-none focus:border-volt"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold tracking-widest text-muted uppercase">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-line bg-surface px-4 py-3.5 text-lg outline-none focus:border-volt"
                    />
                    <p v-if="form.errors.password" class="mt-2 text-sm text-danger">{{ form.errors.password }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="font-display w-full rounded-xl bg-volt py-4 text-xl tracking-wide text-volt-ink uppercase active:bg-volt-bright disabled:opacity-60"
                >
                    Enter
                </button>
            </form>
        </div>
    </div>
</template>
