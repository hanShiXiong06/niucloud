<script lang="ts">export default { name: 'HsxDataArchive' }</script>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import HsxFold from '../HsxFold/index.vue'

const props = withDefaults(defineProps<{
    data?: Record<string, unknown>
    title?: string
    labels?: Record<string, string>
    resetKey?: string | number
}>(), { data: () => ({}), title: '采集记录与原始数据', labels: () => ({}), resetKey: '' })
const open = ref(false)
const entries = computed(() => Object.entries(props.data).filter(([key, value]) => key !== 'version' && value !== undefined && value !== null && (!Array.isArray(value) || value.length)))
watch(() => props.resetKey, () => { open.value = false })
const format = (value: unknown) => JSON.stringify(value, null, 2)
</script>
<template>
    <HsxFold v-if="entries.length" v-model="open" :title="title" summary="仅备查，不作为质检结论" class="hsx-data-archive">
        <div v-if="open">
            <details v-for="[key, value] in entries" :key="key" class="hsx-data-archive__entry">
                <summary>{{ labels[key] || key }}</summary>
                <pre tabindex="0" :aria-label="labels[key] || key">{{ format(value) }}</pre>
            </details>
        </div>
    </HsxFold>
</template>
<style scoped>
.hsx-data-archive { margin-top: 12px; }
.hsx-data-archive__entry + .hsx-data-archive__entry { margin-top: 8px; }
summary { cursor: pointer; color: var(--hsx-text-secondary); font-size: 12px; line-height: 24px; }
pre { margin: 8px 0 0; padding: 12px; max-height: 320px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; line-height: 1.6; background: var(--hsx-bg-page, #f8fafc); border-radius: 6px; }
</style>
