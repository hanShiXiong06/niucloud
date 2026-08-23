<script lang="ts">export default { name: 'HsxEntityPicker' }</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import HsxActionBar from '../HsxActionBar/index.vue'
import HsxEmpty from '../HsxEmpty/index.vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxPopup from '../HsxPopup/index.vue'
import HsxSearchBar from '../HsxSearchBar/index.vue'
import HsxTag from '../HsxTag/index.vue'
import type { AnyRecord, MobileActionItem, MobileEntityFieldMap, MobilePageAdapter, MobilePageParams, MobilePageRequest } from '../../types'
import { deepClone, getPathValue } from '../../utils'
import { useHaptics } from '../../hooks/useFeedback'
import { useZPagingBridge } from '../../hooks/usePaging'

type PickerValue = AnyRecord[] | AnyRecord | null

const props = withDefaults(defineProps<{
    modelValue?: PickerValue
    visible?: boolean
    request?: MobilePageRequest<AnyRecord>
    adapter?: MobilePageAdapter<AnyRecord>
    items?: AnyRecord[]
    requestParams?: AnyRecord
    title?: string
    description?: string
    placeholder?: string
    multiple?: boolean
    max?: number
    rowKey?: string
    fieldMap?: MobileEntityFieldMap
    pageSize?: number
    height?: string | number
    adaptiveAt?: number
    adaptiveWidth?: number
    searchable?: boolean
    disabled?: boolean | ((item: AnyRecord) => boolean)
    emptyText?: string
    confirmText?: string
}>(), {
    modelValue: null,
    visible: false,
    items: () => [],
    requestParams: () => ({}),
    title: '选择数据',
    description: '',
    placeholder: '搜索名称、账号或手机号',
    multiple: true,
    max: 0,
    rowKey: 'id',
    fieldMap: () => ({ key: 'id', title: 'name', subtitle: 'account', avatar: 'avatar', description: 'department', status: 'status' }),
    pageSize: 20,
    height: '82vh',
    adaptiveAt: 600,
    adaptiveWidth: 520,
    searchable: true,
    disabled: false,
    emptyText: '暂无可选数据',
    confirmText: '确定'
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: PickerValue): void
    (event: 'update:visible', value: boolean): void
    (event: 'confirm', value: PickerValue, rows: AnyRecord[]): void
    (event: 'clear'): void
    (event: 'selection-change', rows: AnyRecord[]): void
}>()

const keyword = ref('')
const draftMap = ref(new Map<string | number, AnyRecord>())
const haptics = useHaptics()
const requestQuery = computed(() => ({ ...props.requestParams, keyword: keyword.value }))
const selectedRows = computed(() => Array.from(draftMap.value.values()))
const footerActions = computed<MobileActionItem[]>(() => [
    {
        key: 'clear',
        label: '清空',
        tone: 'default',
        plain: true,
        haptic: 'light',
        action: clear
    },
    {
        key: 'confirm',
        label: `${props.confirmText}${selectedRows.value.length ? `（${selectedRows.value.length}）` : ''}`,
        tone: 'primary',
        haptic: 'light',
        action: confirm
    }
])
const titleKey = computed(() => props.fieldMap.title || 'name')
const subtitleKey = computed(() => props.fieldMap.subtitle || 'account')
const avatarKey = computed(() => props.fieldMap.avatar || 'avatar')
const descriptionKey = computed(() => props.fieldMap.description || 'department')
const statusKey = computed(() => props.fieldMap.status || 'status')

const resolvedRequest: MobilePageRequest<AnyRecord> = async (params: MobilePageParams) => {
    if (props.request) return props.request(params)
    const search = String(params.keyword || '').trim().toLowerCase()
    const filtered = props.items.filter((item) => !search || [getPathValue(item, titleKey.value), getPathValue(item, subtitleKey.value), item.mobile, getPathValue(item, descriptionKey.value)].some((value) => String(value || '').toLowerCase().includes(search)))
    const start = (params.page - 1) * params.limit
    return { list: filtered.slice(start, start + params.limit), total: filtered.length }
}

const { pagingRef, list, queryList, reload } = useZPagingBridge<AnyRecord>({
    getRequest: () => resolvedRequest,
    getQuery: () => requestQuery.value,
    getAdapter: () => props.adapter
})

function keyOf(item: AnyRecord) { return item[props.rowKey] }
function isDisabled(item: AnyRecord) { return typeof props.disabled === 'function' ? props.disabled(item) : props.disabled }
function isSelected(item: AnyRecord) { return draftMap.value.has(keyOf(item)) }
function syncDraft() {
    const rows = Array.isArray(props.modelValue) ? props.modelValue : props.modelValue ? [props.modelValue] : []
    draftMap.value = new Map(rows.map((row) => [keyOf(row), deepClone(row)]))
}
function toggle(item: AnyRecord) {
    if (isDisabled(item)) return
    const next = new Map(draftMap.value)
    const key = keyOf(item)
    if (next.has(key)) next.delete(key)
    else {
        if (!props.multiple) next.clear()
        if (props.max > 0 && next.size >= props.max) return
        next.set(key, deepClone(item))
    }
    draftMap.value = next
    void haptics.selection()
    emit('selection-change', deepClone(selectedRows.value))
}
function clear() {
    draftMap.value = new Map()
    emit('selection-change', [])
    emit('clear')
}
function close() { emit('update:visible', false) }
function confirm() {
    const rows = deepClone(selectedRows.value)
    const value: PickerValue = props.multiple ? rows : rows[0] || null
    emit('update:modelValue', value)
    emit('confirm', value, rows)
    close()
}
function search() { void reload() }

watch(() => props.visible, (visible) => {
    if (!visible) return
    syncDraft()
    keyword.value = ''
    void nextTick(() => reload())
}, { immediate: true })
</script>

<template>
    <HsxPopup
        :model-value="visible"
        mode="bottom"
        adaptive="side"
        :adaptive-at="adaptiveAt"
        :adaptive-width="adaptiveWidth"
        :height="height"
        adaptive-height="100vh"
        :closeable="false"
        :body-scroll="false"
        body-padding="0"
        :z-index="10320"
        @update:model-value="emit('update:visible', $event)"
    >
        <template #header>
            <view class="hsx-entity-picker__header">
                <view class="hsx-entity-picker__heading"><text class="hsx-entity-picker__title">{{ title }}</text><text v-if="description" class="hsx-entity-picker__description">{{ description }}</text></view>
                <view class="hsx-entity-picker__close" @click="close"><HsxIcon name="close" :size="22" /></view>
            </view>
        </template>

        <view class="hsx-entity-picker__body">
            <view v-if="searchable" class="hsx-entity-picker__search"><HsxSearchBar v-model="keyword" :placeholder="placeholder" variant="outline" auto-search @search="search" /></view>
            <view v-if="selectedRows.length" class="hsx-entity-picker__selected">
                <text class="hsx-entity-picker__selected-label">已选 {{ selectedRows.length }}</text>
                <scroll-view scroll-x class="hsx-entity-picker__chips"><HsxTag v-for="item in selectedRows" :key="String(keyOf(item))" :text="String(getPathValue(item, titleKey) || keyOf(item))" tone="primary" @click="toggle(item)" /></scroll-view>
            </view>
            <z-paging
                ref="pagingRef"
                v-model="list"
                class="hsx-entity-picker__list"
                :default-page-size="pageSize"
                :fixed="false"
                height="100%"
                :safe-area-inset-bottom="false"
                :empty-view-text="emptyText"
                @query="queryList"
            >
                <view class="hsx-entity-picker__items">
                    <view v-for="item in list" :key="String(keyOf(item))" class="hsx-entity-picker__item-wrap">
                        <slot name="item" :item="item" :selected="isSelected(item)" :toggle="toggle">
                            <view class="hsx-entity-picker__item" :class="{ 'is-selected': isSelected(item), 'is-disabled': isDisabled(item) }" @click="toggle(item)">
                                <view class="hsx-entity-picker__avatar">
                                    <image v-if="getPathValue(item, avatarKey)" :src="String(getPathValue(item, avatarKey))" mode="aspectFill" />
                                    <HsxIcon v-else name="account" :size="22" />
                                </view>
                                <view class="hsx-entity-picker__content">
                                    <view class="hsx-entity-picker__top"><text class="hsx-entity-picker__name">{{ getPathValue(item, titleKey) }}</text><HsxTag v-if="getPathValue(item, statusKey)" :text="getPathValue(item, statusKey)" tone="success" size="small" /></view>
                                    <text v-if="getPathValue(item, subtitleKey)" class="hsx-entity-picker__subtitle">{{ getPathValue(item, subtitleKey) }}</text>
                                    <text v-if="getPathValue(item, descriptionKey)" class="hsx-entity-picker__description-row">{{ getPathValue(item, descriptionKey) }}</text>
                                </view>
                                <view class="hsx-entity-picker__check"><HsxIcon :name="isSelected(item) ? 'checkbox-mark' : 'circle'" :size="21" /></view>
                            </view>
                        </slot>
                    </view>
                </view>
                <template #empty="{ isLoadFailed }"><HsxEmpty :text="isLoadFailed ? '加载失败，点击重试' : emptyText" @click="reload" /></template>
            </z-paging>
        </view>

        <template #footer>
            <HsxActionBar :actions="footerActions" :max-visible="2" :safe-area="false" :bordered="false" :gap="10" />
        </template>
    </HsxPopup>
</template>

<style scoped lang="scss">
.hsx-entity-picker__header { display: flex; width: 100%; min-width: 0; min-height: 72px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 18px 11px; }
.hsx-entity-picker__heading { min-width: 0; flex: 1; }
.hsx-entity-picker__title,
.hsx-entity-picker__description { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-entity-picker__title { color: var(--hsx-mobile-text-primary, #172033); font-size: 18px; font-weight: 700; }
.hsx-entity-picker__description { margin-top: 3px; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: 12px; }
.hsx-entity-picker__close { display: flex; width: 34px; height: 34px; flex: none; align-items: center; justify-content: center; }
.hsx-entity-picker__body { display: flex; height: 100%; min-height: 0; flex-direction: column; background: var(--hsx-mobile-bg-page, #f3f6fa); }
.hsx-entity-picker__list { min-height: 0; flex: 1; }
.hsx-entity-picker__list :deep(.hsx-page-list) { height: 100%; }
.hsx-entity-picker__items { width: 100%; min-width: 0; box-sizing: border-box; }
.hsx-entity-picker__item-wrap { min-width: 0; }
.hsx-entity-picker__search { flex: none; padding: 12px; }
.hsx-entity-picker__selected { display: flex; min-width: 0; flex: none; align-items: center; gap: 10px; padding: 0 12px 10px; }
.hsx-entity-picker__selected-label { flex: none; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: 12px; }
.hsx-entity-picker__chips { min-width: 0; flex: 1; white-space: nowrap; }
.hsx-entity-picker__chips :deep(.hsx-mobile-tag) { margin-right: 6px; }
.hsx-entity-picker__item { display: flex; min-width: 0; align-items: center; gap: 12px; margin: 0 12px 10px; padding: 13px; border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 14px; background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-entity-picker__item.is-selected { border-color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #edf3ff); }
.hsx-entity-picker__item.is-disabled { opacity: .48; }
.hsx-entity-picker__avatar { display: flex; width: 44px; height: 44px; flex: none; overflow: hidden; align-items: center; justify-content: center; border-radius: 12px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); }
.hsx-entity-picker__avatar image { width: 100%; height: 100%; }
.hsx-entity-picker__content { min-width: 0; flex: 1; }
.hsx-entity-picker__top { display: flex; min-width: 0; align-items: center; gap: 7px; }
.hsx-entity-picker__name { min-width: 0; overflow: hidden; color: var(--hsx-mobile-text-primary, #172033); font-size: 15px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.hsx-entity-picker__subtitle,
.hsx-entity-picker__description-row { display: block; margin-top: 3px; overflow: hidden; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.hsx-entity-picker__check { flex: none; color: var(--hsx-mobile-primary, #2563eb); }
</style>
