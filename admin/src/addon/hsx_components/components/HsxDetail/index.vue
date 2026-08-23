<script lang="ts">export default { name: 'HsxDetail' }</script>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { ElMessage } from 'element-plus'
import HsxIcon from '../HsxIcon/index.vue'
import HsxTag from '../HsxTag/index.vue'
import { HSX_PERMISSION_CHECKER, type PermissionChecker } from '../../tokens'
import type { AnyRecord, HsxDetailItem, HsxTagTone } from '../../types'
import { getPathValue } from '../../utils'

const props = withDefaults(defineProps<{
    data?: AnyRecord
    schema: HsxDetailItem[]
    columns?: number
    direction?: 'horizontal' | 'vertical'
    border?: boolean
    size?: 'large' | 'default' | 'small'
    title?: string
    emptyText?: string
    labelWidth?: string | number
    loading?: boolean
    permissionChecker?: PermissionChecker
}>(), {
    data: () => ({}),
    columns: 3,
    direction: 'horizontal',
    border: true,
    size: 'default',
    title: '',
    emptyText: '-',
    labelWidth: '',
    loading: false
})

const emit = defineEmits<{
    (event: 'copy', value: any, item: HsxDetailItem): void
    (event: 'click', value: any, item: HsxDetailItem): void
}>()

const injectedChecker = inject(HSX_PERMISSION_CHECKER, undefined)
const checker = computed(() => props.permissionChecker || injectedChecker || (() => true))
const visibleSchema = computed(() => props.schema.filter((item) => {
    const visible = typeof item.visible === 'function' ? item.visible(props.data) : item.visible !== false
    return visible && (!item.permission || checker.value(item.permission))
}))

function rawValue(item: HsxDetailItem) { return getPathValue(props.data, String(item.prop)) }
function isEmpty(value: any) { return value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length) }
function maskValue(value: any) {
    const text = String(value ?? '')
    if (text.length <= 4) return '*'.repeat(text.length)
    return `${text.slice(0, 3)}${'*'.repeat(Math.min(8, text.length - 5))}${text.slice(-2)}`
}
function displayValue(item: HsxDetailItem) {
    const value = rawValue(item)
    if (isEmpty(value)) return item.emptyText ?? props.emptyText
    if (item.sensitive) return item.mask ? item.mask(value, props.data) : maskValue(value)
    if (item.formatter) return item.formatter(value, props.data)
    if (item.type === 'money') return `¥${Number(value).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    return value
}
function toneOf(item: HsxDetailItem): HsxTagTone {
    const tone = item.tone
    return typeof tone === 'function' ? tone(rawValue(item), props.data) : tone || 'neutral'
}
function imageList(item: HsxDetailItem) {
    const value = rawValue(item)
    return (Array.isArray(value) ? value : value ? [value] : []).map(String)
}
async function copy(item: HsxDetailItem) {
    const value = rawValue(item)
    if (isEmpty(value)) return
    try {
        await navigator.clipboard.writeText(String(value))
        ElMessage.success('已复制')
        emit('copy', value, item)
    } catch {
        ElMessage.warning('复制失败，请手动复制')
    }
}
</script>

<template>
    <div v-loading="loading" class="hsx-detail">
        <el-descriptions :title="title" :column="columns" :direction="direction" :border="border" :size="size">
            <template v-if="$slots.extra" #extra><slot name="extra" :data="data" /></template>
            <el-descriptions-item
                v-for="item in visibleSchema"
                :key="item.prop"
                :label="item.label"
                :span="item.span"
                :min-width="item.minWidth"
                :label-width="item.labelWidth || labelWidth"
                v-bind="item.props"
            >
                <slot :name="item.slot || `item-${item.prop}`" :item="item" :data="data" :value="rawValue(item)">
                    <div class="hsx-detail__value" :class="`hsx-detail__value--${item.type || 'text'}`" @click="emit('click', rawValue(item), item)">
                        <HsxTag v-if="item.type === 'tag'" :text="displayValue(item)" :tone="toneOf(item)" />
                        <div v-else-if="item.type === 'image'" class="hsx-detail__images">
                            <el-image v-for="url in imageList(item)" :key="url" :src="url" :preview-src-list="imageList(item)" fit="cover" preview-teleported />
                            <span v-if="!imageList(item).length">{{ item.emptyText ?? emptyText }}</span>
                        </div>
                        <a v-else-if="item.type === 'link' && !isEmpty(rawValue(item))" :href="String(rawValue(item))" target="_blank" rel="noopener">{{ displayValue(item) }}</a>
                        <span v-else>{{ displayValue(item) }}</span>
                        <button v-if="item.copyable && !isEmpty(rawValue(item))" type="button" class="hsx-detail__copy" title="复制" @click.stop="copy(item)"><HsxIcon name="element CopyDocument" :size="14" /></button>
                    </div>
                </slot>
            </el-descriptions-item>
        </el-descriptions>
    </div>
</template>

<style scoped>
.hsx-detail { min-height: 44px; }
.hsx-detail__value { display: flex; min-width: 0; align-items: center; gap: 7px; color: var(--hsx-text-primary); overflow-wrap: anywhere; }
.hsx-detail__value--money { color: var(--hsx-color-danger, #ef4444); font-weight: 700; font-variant-numeric: tabular-nums; }
.hsx-detail__images { display: flex; flex-wrap: wrap; gap: 8px; }
.hsx-detail__images :deep(.el-image) { width: 64px; height: 64px; overflow: hidden; border: 1px solid var(--hsx-border-color); border-radius: 8px; }
.hsx-detail__value a { color: var(--hsx-color-primary); text-decoration: none; }
.hsx-detail__copy { display: inline-flex; width: 25px; height: 25px; flex: none; align-items: center; justify-content: center; padding: 0; border: 0; border-radius: 6px; color: var(--hsx-text-secondary); background: transparent; cursor: pointer; }
.hsx-detail__copy:hover { color: var(--hsx-color-primary); background: var(--hsx-bg-muted); }
</style>
