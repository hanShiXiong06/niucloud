<template><CoopProfileDiySection :component="component" :index="index" :global="global" kind="benefits" @update:component-is-show="emit('update:componentIsShow', $event)" /></template>
<script setup lang="ts">
import CoopProfileDiySection from '../../cooperation/CoopProfileDiySection.vue'
defineProps(['component', 'index', 'global'])
const emit=defineEmits(['update:componentIsShow'])
</script>
