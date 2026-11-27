<script lang="ts">export default { name: 'HsxDataArchive' }</script>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import HsxDrawer from '../HsxDrawer/index.vue'

const props = withDefaults(defineProps<{
    data?: Record<string, unknown>
    title?: string
    buttonText?: string
    labels?: Record<string, string>
    resetKey?: string | number
}>(), { data: () => ({}), title: '采集记录与原始数据', buttonText: '查看采集档案', labels: () => ({}), resetKey: '' })
const open = ref(false)
const entries = computed(() => Object.entries(props.data).filter(([key, value]) => key !== 'version' && value !== undefined && value !== null && (!Array.isArray(value) || value.length)))
watch(() => props.resetKey, () => { open.value = false })
watch(() => entries.value.length, (length) => { if (!length) open.value = false })
const format = (value: unknown) => JSON.stringify(value, null, 2)
</script>
<template>
    <span v-if="entries.length" class="hsx-data-archive">
        <el-button size="small" link type="primary" native-type="button" aria-haspopup="dialog" :aria-expanded="open" @click.stop="open = true">
            {{ buttonText }}
        </el-button>
        <HsxDrawer v-model="open" :title="title" subtitle="仅供备查，不作为质检结论；此处查看不会修改设备数据。" size="md" :append-to-body="true" :destroy-on-close="true" :close-on-click-modal="true">
            <div v-if="open" class="hsx-data-archive__content">
                <details v-for="[key, value] in entries" :key="key" class="hsx-data-archive__entry">
                    <summary>{{ labels[key] || key }}</summary>
                    <pre tabindex="0" :aria-label="labels[key] || key">{{ format(value) }}</pre>
                </details>
            </div>
            <template #footer>
                <el-button native-type="button" @click="open = false">关闭</el-button>
            </template>
        </HsxDrawer>
    </span>
</template>
<style scoped>
.hsx-data-archive { display: inline-flex; flex: none; align-items: center; vertical-align: middle; }
.hsx-data-archive__content { min-width: 0; }
.hsx-data-archive__entry { border-bottom: 1px solid var(--hsx-border-color, #e2e8f0); padding: 8px 0; }
.hsx-data-archive__entry summary { cursor: pointer; color: var(--hsx-text-primary); font-size: 13px; font-weight: 500; line-height: 28px; }
.hsx-data-archive__entry pre { margin: 8px 0; padding: 12px; max-height: 320px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; line-height: 1.6; background: var(--hsx-bg-page, #f8fafc); border-radius: 6px; }
</style>
