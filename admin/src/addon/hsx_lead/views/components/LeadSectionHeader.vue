<template>
    <header class="lead-page-title lead-section-header">
        <div class="lead-section-header__copy"><h2>{{ title }}</h2><p v-if="description">{{ description }}</p><slot name="hint" /></div>
        <div v-if="$slots.default" class="lead-actions lead-section-header__actions"><slot /></div>
    </header>
</template>
<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>
<style scoped>
.lead-section-header__copy { min-width: 0; }
.lead-section-header__actions { flex-shrink: 0; justify-content: flex-end; }
@media (max-width: 720px) { .lead-section-header__actions { justify-content: flex-start; } }
</style>
