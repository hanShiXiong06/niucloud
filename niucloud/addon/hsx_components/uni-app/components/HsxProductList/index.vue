<script lang="ts">export default { name: 'HsxProductList' }</script>

<script setup lang="ts">
import { computed, watch } from 'vue'
import type { AnyRecord, MobilePageAdapter, MobilePageParams, MobilePageRequest, MobileProductFieldMap, MobileResponsiveValue, MobileSwipeActionOption } from '../../types'
import { getPathValue } from '../../utils'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import { useZPagingBridge } from '../../hooks/usePaging'
import { useHaptics } from '../../hooks/useFeedback'
import HsxEmpty from '../HsxEmpty/index.vue'
import HsxMediaCard from '../HsxMediaCard/index.vue'

const props = withDefaults(defineProps<{
    request?: MobilePageRequest<AnyRecord>
    adapter?: MobilePageAdapter<AnyRecord>
    items?: AnyRecord[]
    requestParams?: AnyRecord
    pageSize?: number
    rowKey?: string
    fieldMap?: MobileProductFieldMap
    cardProps?: AnyRecord
    cardPropsResolver?: (item: AnyRecord, index: number) => AnyRecord
    columns?: MobileResponsiveValue<number>
    gap?: MobileResponsiveValue<number>
    swipeActions?: MobileSwipeActionOption[] | ((item: AnyRecord, index: number) => MobileSwipeActionOption[])
    swipeAutoClose?: boolean
    swipeThreshold?: number
    swipeDuration?: string | number
    haptic?: boolean
    emptyText?: string
    fixed?: boolean
    height?: string
    autoLoad?: boolean
    refresherEnabled?: boolean
    safeAreaInsetBottom?: boolean
}>(), {
    items: () => [],
    requestParams: () => ({}),
    pageSize: 20,
    rowKey: 'id',
    fieldMap: () => ({ key: 'id', title: 'title', subtitle: 'subtitle', image: 'image', price: 'price', originalPrice: 'originalPrice', status: 'status', description: 'description' }),
    cardProps: () => ({}),
    columns: () => ({ compact: 1, medium: 2, expanded: 2 }),
    gap: () => ({ compact: 10, medium: 12, expanded: 14 }),
    swipeActions: () => [],
    swipeAutoClose: true,
    swipeThreshold: 30,
    swipeDuration: 280,
    haptic: true,
    emptyText: '暂无商品',
    fixed: false,
    height: '100%',
    autoLoad: true,
    refresherEnabled: true,
    safeAreaInsetBottom: false
})

const emit = defineEmits<{
    (event: 'item-click', item: AnyRecord, index: number): void
    (event: 'action', action: MobileSwipeActionOption, item: AnyRecord, index: number): void
    (event: 'load', list: AnyRecord[]): void
    (event: 'error', error: unknown): void
}>()

const layout = useAdaptiveContext()
const haptics = useHaptics()
const toneColors: Record<string, string> = {
    neutral: '#64748b',
    primary: '#2563eb',
    success: '#16a34a',
    warning: '#d97706',
    danger: '#ef4444'
}
const actualColumns = computed(() => Math.max(1, resolveAdaptiveValue(props.columns, layout.widthClass.value, 1)))
const actualGap = computed(() => Math.max(0, resolveAdaptiveValue(props.gap, layout.widthClass.value, 10)))
const listStyle = computed(() => ({
    gridTemplateColumns: `repeat(${actualColumns.value}, minmax(0, 1fr))`,
    gap: `${actualGap.value}px`
}))

const resolvedRequest: MobilePageRequest<AnyRecord> = async (params: MobilePageParams) => {
    if (props.request) return props.request(params)
    const start = (params.page - 1) * params.limit
    return { list: props.items.slice(start, start + params.limit), total: props.items.length }
}
const field = (item: AnyRecord, key: keyof MobileProductFieldMap) => getPathValue(item, props.fieldMap[key] || String(key))
const actionsOf = (item: AnyRecord, index: number) => typeof props.swipeActions === 'function' ? props.swipeActions(item, index) : props.swipeActions
const styledActionsOf = (item: AnyRecord, index: number) => actionsOf(item, index).map((action) => ({
    ...action,
    style: {
        color: '#fff',
        backgroundColor: toneColors[action.tone || 'neutral'],
        fontSize: '14px',
        ...(action.style || {})
    }
}))
const cardPropsOf = (item: AnyRecord, index: number) => props.cardPropsResolver?.(item, index) || props.cardProps
const itemKey = (item: AnyRecord, index: number) => item?.[props.rowKey] ?? index

function handleSwipeAction(detail: any, item: AnyRecord, index: number) {
    const action = styledActionsOf(item, index)[Number(detail?.index)]
    if (!action || action.disabled) return
    if (props.haptic) void (action.tone === 'danger' ? haptics.trigger('medium') : haptics.selection())
    emit('action', action, item, index)
}

const { pagingRef, list, total, loading, error, queryList, reload, refresh, loadMore } = useZPagingBridge<AnyRecord>({
    getRequest: () => resolvedRequest,
    getQuery: () => props.requestParams,
    getAdapter: () => props.adapter,
    onLoad: (rows) => emit('load', rows),
    onError: (reason) => emit('error', reason)
})

watch(() => props.items, () => { if (!props.request) void reload() }, { deep: true })
defineExpose({ pagingRef, list, total, loading, error, reload, refresh, loadMore })
</script>

<template>
    <z-paging
        ref="pagingRef"
        v-model="list"
        class="hsx-product-list"
        :auto="autoLoad"
        :default-page-size="pageSize"
        :fixed="fixed"
        :height="height"
        :refresher-enabled="refresherEnabled"
        :safe-area-inset-bottom="safeAreaInsetBottom"
        :empty-view-text="emptyText"
        @query="queryList"
    >
        <template #top><slot name="header" :refresh="reload" /></template>

        <view class="hsx-product-list__grid" :style="listStyle">
            <view v-for="(item, index) in list" :key="itemKey(item, index)" class="hsx-product-list__item">
                <slot name="item" :item="item" :index="index">
                    <!--
                        小程序的 scoped slot 跨两层自定义组件时会偶发丢失默认内容。
                        商品卡直接进入 uview 的 swipe item，避免 HsxSwipeActions 再转发一次 slot。
                    -->
                    <u-swipe-action :auto-close="swipeAutoClose">
                        <u-swipe-action-item
                            :name="itemKey(item, index)"
                            :options="styledActionsOf(item, index)"
                            :disabled="!actionsOf(item, index).length"
                            :auto-close="swipeAutoClose"
                            :threshold="swipeThreshold"
                            :duration="swipeDuration"
                            @click="handleSwipeAction($event, item, index)"
                        >
                            <view class="hsx-product-list__card">
                                <HsxMediaCard
                                    v-bind="cardPropsOf(item, index)"
                                    :image="String(field(item, 'image') || '')"
                                    :title="String(field(item, 'title') || '')"
                                    :subtitle="String(field(item, 'subtitle') || '')"
                                    :description="String(field(item, 'description') || '')"
                                    :price="field(item, 'price')"
                                    :status="String(field(item, 'status') || '')"
                                    @click="emit('item-click', item, index)"
                                />
                            </view>
                        </u-swipe-action-item>
                    </u-swipe-action>
                </slot>
            </view>
        </view>

        <template #empty="{ isLoadFailed }">
            <slot name="empty" :is-load-failed="isLoadFailed" :reload="reload">
                <HsxEmpty :text="isLoadFailed ? '加载失败，点击重试' : emptyText" @click="reload" />
            </slot>
        </template>
        <template #bottom><slot name="footer" :list="list" :total="total" :reload="reload" /></template>
    </z-paging>
</template>

<style scoped>
.hsx-product-list__grid {
    display: grid;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
.hsx-product-list__item { min-width: 0; }
.hsx-product-list__card { min-width: 0; background: var(--hsx-mobile-bg-surface, #fff); }
</style>
