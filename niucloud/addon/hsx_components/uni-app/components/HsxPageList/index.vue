<script lang="ts">
export default { name: 'HsxPageList', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxEmpty from '../HsxEmpty/index.vue'
import type { AnyRecord, MobilePageAdapter, MobilePageRequest, MobileResponsiveValue } from '../../types'
import { useZPagingBridge } from '../../hooks/usePaging'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'

const props = withDefaults(
    defineProps<{
        request: MobilePageRequest<AnyRecord>
        requestParams?: AnyRecord
        adapter?: MobilePageAdapter<AnyRecord>
        pageSize?: number
        height?: string
        rowKey?: string | ((row: AnyRecord) => string | number)
        autoLoad?: boolean
        refresherEnabled?: boolean
        emptyText?: string
        fixed?: boolean
        safeAreaInsetBottom?: boolean
        columns?: MobileResponsiveValue<number>
        gap?: MobileResponsiveValue<number>
    }>(),
    {
        requestParams: () => ({}),
        pageSize: 20,
        height: '100%',
        rowKey: 'id',
        autoLoad: true,
        refresherEnabled: true,
        emptyText: '暂无数据',
        fixed: false,
        safeAreaInsetBottom: false,
        columns: 1,
        gap: () => ({ compact: 10, medium: 12, expanded: 16 })
    }
)

const emit = defineEmits<{
    (event: 'load', list: AnyRecord[]): void
    (event: 'error', error: unknown): void
}>()

const layout = useAdaptiveContext()
const actualColumns = computed(() => Math.max(1, resolveAdaptiveValue(props.columns, layout.widthClass.value, 1)))
const actualGap = computed(() => Math.max(0, resolveAdaptiveValue(props.gap, layout.widthClass.value, 10)))
const itemsStyle = computed(() => ({
    gridTemplateColumns: `repeat(${actualColumns.value}, minmax(0, 1fr))`,
    gap: `${actualGap.value}px`
}))

function itemKey(item: AnyRecord, index: number) {
    if (typeof props.rowKey === 'function') return props.rowKey(item)
    return item[props.rowKey] ?? index
}

const { pagingRef, list, total, loading, error, queryList, reload, refresh, loadMore } = useZPagingBridge<AnyRecord>({
    getRequest: () => props.request,
    getQuery: () => props.requestParams,
    getAdapter: () => props.adapter,
    onLoad: (rows) => emit('load', rows),
    onError: (reason) => emit('error', reason)
})

defineExpose({ pagingRef, list, total, loading, error, reload, refresh, loadMore })
</script>

<template>
    <z-paging
        ref="pagingRef"
        v-model="list"
        class="hsx-page-list"
        v-bind="$attrs"
        :auto="autoLoad"
        :default-page-size="pageSize"
        :fixed="fixed"
        :height="height"
        :refresher-enabled="refresherEnabled"
        :safe-area-inset-bottom="safeAreaInsetBottom"
        :empty-view-text="emptyText"
        @query="queryList"
    >
        <template #top>
            <slot name="header" :refresh="reload" />
        </template>

        <slot :list="list" :refresh="reload" :load-more="loadMore">
            <view class="hsx-page-list__items" :style="itemsStyle">
                <view v-for="(item, index) in list" :key="itemKey(item, index)" class="hsx-page-list__item">
                    <slot name="item" :item="item" :index="index" />
                </view>
            </view>
        </slot>

        <template #empty="{ isLoadFailed }">
            <slot name="empty" :is-load-failed="isLoadFailed" :reload="reload">
                <HsxEmpty :text="isLoadFailed ? '加载失败，点击重试' : emptyText" @click="reload" />
            </slot>
        </template>

        <template #bottom>
            <slot name="footer" :list="list" :total="total" :reload="reload" />
        </template>
    </z-paging>
</template>

<style scoped lang="scss">
.hsx-page-list {
    width: 100%;
}

.hsx-page-list__items {
    display: grid;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.hsx-page-list__item {
    min-width: 0;
}
</style>
