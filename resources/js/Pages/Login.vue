<script setup>
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });

// The mark: four plates, light to heavy
const plates = [
    { color: 'var(--color-green)', width: '2.75rem' },
    { color: 'var(--color-yellow)', width: '4rem' },
    { color: 'var(--color-blue)', width: '5.25rem' },
    { color: 'var(--color-red)', width: '6.5rem' },
];
</script>

<template>
    <Head title="Log in" />
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6">
        <div class="mb-9">
            <div class="mb-5 space-y-[3px]" aria-hidden="true">
                <div v-for="(p, i) in plates" :key="i" class="grow-x h-3.5 rounded-full" :style="{ width: p.width, backgroundColor: p.color, '--i': i * 2 }" />
            </div>
            <h1 class="display text-6xl">Pumpwerk</h1>
        </div>

        <form class="space-y-4" @submit.prevent="form.post('/login')">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" inputmode="email" autocapitalize="none" autocomplete="username" required class="field" />
                <p v-if="form.errors.email" class="mt-2 text-sm font-semibold text-red">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="field" />
                <p v-if="form.errors.password" class="mt-2 text-sm font-semibold text-red">{{ form.errors.password }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="btn-primary w-full">
                {{ form.processing ? 'Logging in…' : 'Log in' }}
            </button>
        </form>
    </div>
</template>
