<template>
    <el-popover v-if="text" trigger="click" :width="popoverWidth" placement="bottom-start">
        <template #reference>
            <span class="erp-copy-text" :style="{ maxWidth }" :title="hint">
                <span class="erp-copy-text__value">{{ text }}</span>
                <el-icon class="erp-copy-text__icon"><DocumentCopy /></el-icon>
            </span>
        </template>
        <div class="erp-copy-popover">
            <div class="erp-copy-popover__title">{{ title }}</div>
            <div class="erp-copy-popover__value">{{ text }}</div>
            <el-button type="primary" size="small" @click="copy">一键复制</el-button>
        </div>
    </el-popover>
    <span v-else class="text-gray-400">{{ emptyText }}</span>
</template>

<script setup lang="ts">
import { useFeedback } from '@/addon/hsx_components/core'
import { computed } from 'vue'

import { DocumentCopy } from '@element-plus/icons-vue'
const hsxFeedback = useFeedback()

const props = withDefaults(defineProps<{ value?: string | number; title?: string; emptyText?: string; maxWidth?: string; popoverWidth?: number }>(), {
    value: '', title: '完整内容', emptyText: '-', maxWidth: '220px', popoverWidth: 420,
})
const text = computed(() => String(props.value ?? '').trim())
const hint = computed(() => text.value ? '点击查看完整内容并复制' : '')
async function copy() {
    try {
        await navigator.clipboard.writeText(text.value)
        hsxFeedback.success('已复制')
    } catch (_) {
        const input = document.createElement('textarea')
        input.value = text.value
        document.body.appendChild(input)
        input.select()
        document.execCommand('copy')
        input.remove()
        hsxFeedback.success('已复制')
    }
}
</script>

<style scoped>
.erp-copy-text{display:inline-flex;align-items:center;gap:5px;min-width:0;cursor:pointer;color:inherit}.erp-copy-text__value{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.erp-copy-text__icon{flex:none;color:var(--el-color-primary)}.erp-copy-popover__title{font-weight:600;color:#0f172a}.erp-copy-popover__value{margin:10px 0 12px;padding:10px;border-radius:6px;background:#f8fafc;color:#334155;line-height:1.5;word-break:break-all}
</style>
