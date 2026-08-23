<template><AiConfigSection :section="section" /></template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AiConfigSection from '../config/index.vue'

const route = useRoute()
const section = computed(() => route.path.endsWith('/assistant') ? 'assistant' : 'playground')
</script>
