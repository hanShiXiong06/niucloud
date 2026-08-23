<script lang="ts">export default { name: 'HsxDetail' }</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxTag from '../HsxTag/index.vue'
import type { AnyRecord, MobileDetailItem, MobileResponsiveValue } from '../../types'
import { formatMoney, getPathValue } from '../../utils'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import { useToast } from '../../hooks/useFeedback'

const props = withDefaults(defineProps<{
    data?: AnyRecord
    schema: MobileDetailItem[]
    columns?: MobileResponsiveValue<number>
    labelPosition?: 'top' | 'left'
    emptyText?: string
    bordered?: boolean
    divided?: boolean
    compact?: boolean
}>(), {
    data: () => ({}),
    columns: () => ({ compact: 1, medium: 2, expanded: 3 }),
    labelPosition: 'top',
    emptyText: '-',
    bordered: true,
    divided: false,
    compact: false
})

const emit = defineEmits<{
    (event: 'copy', value: any, item: MobileDetailItem): void
    (event: 'click', value: any, item: MobileDetailItem): void
}>()

const layout = useAdaptiveContext()
const actualColumns = computed(() => Math.max(1, resolveAdaptiveValue(props.columns, layout.widthClass.value, 1)))
const visibleSchema = computed(() => props.schema.filter((item) => typeof item.visible === 'function' ? item.visible(props.data) : item.visible !== false))
const gridStyle = computed(() => ({ gridTemplateColumns: `repeat(${actualColumns.value}, minmax(0, 1fr))` }))
const toast = useToast()

function rawValue(item: MobileDetailItem) { return getPathValue(props.data, String(item.prop)) }
function isEmpty(value: any) { return value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length) }
function maskValue(value: any) {
    const text = String(value ?? '')
    if (text.length <= 4) return '*'.repeat(text.length)
    return `${text.slice(0, 3)}${'*'.repeat(Math.min(8, text.length - 5))}${text.slice(-2)}`
}
function displayValue(item: MobileDetailItem) {
    const value = rawValue(item)
    if (isEmpty(value)) return item.emptyText ?? props.emptyText
    if (item.sensitive) return item.mask ? item.mask(value, props.data) : maskValue(value)
    if (item.formatter) return item.formatter(value, props.data)
    if (item.type === 'money') return `¥${formatMoney(value)}`
    return value
}
function toneOf(item: MobileDetailItem) {
    return typeof item.tone === 'function' ? item.tone(rawValue(item), props.data) : item.tone || 'neutral'
}
function imageList(item: MobileDetailItem) {
    const value = rawValue(item)
    return (Array.isArray(value) ? value : value ? [value] : []).map(String)
}
function preview(url: string, urls: string[]) { uni.previewImage({ current: url, urls }) }
function openLink(value: any) {
    const url = String(value || '')
    if (!url) return
    // #ifdef H5
    window.open(url, '_blank')
    // #endif
    // #ifndef H5
    uni.setClipboardData({ data: url })
    // #endif
}
function copy(item: MobileDetailItem) {
    const value = rawValue(item)
    if (isEmpty(value)) return
    uni.setClipboardData({ data: String(value), success: () => { toast.success('已复制'); emit('copy', value, item) } })
}
</script>

<template>
    <view class="hsx-detail" :class="[{ 'is-bordered': bordered, 'is-divided': divided, 'is-compact': compact }, `hsx-detail--label-${labelPosition}`]" :style="gridStyle">
        <view v-for="item in visibleSchema" :key="item.prop" class="hsx-detail__item" :style="{ gridColumn: `span ${Math.min(actualColumns, Math.max(1, item.span || 1))}` }">
            <text class="hsx-detail__label">{{ item.label }}</text>
            <slot :name="item.slot || `item-${item.prop}`" :item="item" :data="data" :value="rawValue(item)">
                <view class="hsx-detail__value" :class="`hsx-detail__value--${item.type || 'text'}`" @click="emit('click', rawValue(item), item)">
                    <HsxTag v-if="item.type === 'tag'" :text="displayValue(item)" :tone="toneOf(item)" />
                    <view v-else-if="item.type === 'image'" class="hsx-detail__images">
                        <image v-for="url in imageList(item)" :key="url" :src="url" mode="aspectFill" @click.stop="preview(url, imageList(item))" />
                        <text v-if="!imageList(item).length">{{ item.emptyText ?? emptyText }}</text>
                    </view>
                    <text v-else class="hsx-detail__text" @click="item.type === 'link' && openLink(rawValue(item))">{{ displayValue(item) }}</text>
                    <view v-if="item.copyable && !isEmpty(rawValue(item))" class="hsx-detail__copy" @click.stop="copy(item)"><HsxIcon name="file-text" :size="15" /></view>
                </view>
            </slot>
        </view>
    </view>
</template>

<style scoped lang="scss">
.hsx-detail { display: grid; width: 100%; min-width: 0; box-sizing: border-box; gap: 0; overflow: hidden; background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-detail.is-bordered { border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 14px; }
.hsx-detail__item { display: flex; min-width: 0; box-sizing: border-box; flex-direction: column; gap: 6px; padding: 13px 14px; }
.hsx-detail.is-divided .hsx-detail__item { border-bottom: 1px solid var(--hsx-mobile-border, #eef1f5); }
.hsx-detail.is-compact .hsx-detail__item { padding: 9px 11px; }
.hsx-detail--label-left .hsx-detail__item { flex-direction: row; align-items: flex-start; gap: 12px; }
.hsx-detail__label { flex: none; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: var(--hsx-mobile-font-caption, 12px); line-height: 1.45; }
.hsx-detail--label-left .hsx-detail__label { min-width: 72px; }
.hsx-detail__value { display: flex; min-width: 0; flex: 1; align-items: center; gap: 7px; color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-body, 14px); line-height: 1.55; overflow-wrap: anywhere; }
.hsx-detail__value--money { color: var(--hsx-mobile-price, #ff5a36); font-size: 16px; font-weight: 700; }
.hsx-detail__text { min-width: 0; flex: 1; }
.hsx-detail__value--link .hsx-detail__text { color: var(--hsx-mobile-primary, #2563eb); }
.hsx-detail__copy { display: flex; width: 26px; height: 26px; flex: none; align-items: center; justify-content: center; border-radius: 7px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); }
.hsx-detail__images { display: flex; min-width: 0; flex: 1; flex-wrap: wrap; gap: 8px; }
.hsx-detail__images image { width: 60px; height: 60px; border-radius: 9px; background: var(--hsx-mobile-bg-muted, #f3f5f8); }
</style>
