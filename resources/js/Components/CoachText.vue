<script setup>
import { computed } from 'vue';
import { renderMarkdown } from '../markdown.js';

const props = defineProps({ text: { type: String, required: true } });

// renderMarkdown escapes the source before adding its own tags
const html = computed(() => renderMarkdown(props.text));
</script>

<template>
    <div class="coach-prose" v-html="html" />
</template>
