<script lang="ts">export default { name: 'HsxTag', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed } from 'vue'
import type { HsxTagTone } from '../../types'
const props = withDefaults(defineProps<{ text?: string | number, tone?: HsxTagTone, effect?: 'dark' | 'light' | 'plain', size?: 'large' | 'default' | 'small', round?: boolean, closable?: boolean, dot?: boolean }>(), { text: '', tone: 'neutral', effect: 'light', size: 'default', round: true, closable: false, dot: false })
const emit = defineEmits<{ (event: 'click', value: MouseEvent): void, (event: 'close', value: MouseEvent): void }>()
const elementType = computed(() => props.tone === 'neutral' ? 'info' : props.tone === 'primary' ? undefined : props.tone)
</script>
<template><el-tag v-bind="$attrs" class="hsx-tag" :class="`hsx-tag--${tone}`" :type="elementType" :effect="effect" :size="size" :round="round" :closable="closable" @click="emit('click', $event)" @close="emit('close', $event)"><i v-if="dot" class="hsx-tag__dot" /><slot>{{ text }}</slot></el-tag></template>
<style scoped>
.hsx-tag { gap: 5px; font-weight: 550; }
.hsx-tag__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.hsx-tag--primary { color: var(--hsx-color-primary); border-color: color-mix(in srgb, var(--hsx-color-primary) 28%, transparent); background: color-mix(in srgb, var(--hsx-color-primary) 10%, transparent); }
</style>
