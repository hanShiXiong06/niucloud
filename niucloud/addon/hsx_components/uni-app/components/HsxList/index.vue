<script lang="ts">export default { name: 'HsxList' }</script>
<script setup lang="ts">
import type { AnyRecord } from '../../types'
const props = withDefaults(defineProps<{ items?: AnyRecord[], itemKey?: string, loading?: boolean, skeletonRows?: number, emptyText?: string, divided?: boolean, clickable?: boolean }>(), { items: () => [], itemKey: 'id', loading: false, skeletonRows: 3, emptyText: '暂无数据', divided: true, clickable: false })
const emit = defineEmits<{ (event: 'item-click', item: AnyRecord, index: number): void }>()
</script>
<template>
    <view class="hsx-mobile-list" :class="{ 'hsx-mobile-list--divided': divided, 'hsx-mobile-list--clickable': clickable }">
        <template v-if="loading"><view v-for="index in skeletonRows" :key="index" class="hsx-mobile-list__skeleton"><view /><text /></view></template>
        <slot v-else-if="!items.length" name="empty"><view class="hsx-mobile-list__empty">{{ emptyText }}</view></slot>
        <view v-for="(item, index) in items" v-else :key="item[itemKey] ?? index" class="hsx-mobile-list__item" @click="emit('item-click', item, index)">
            <view class="hsx-mobile-list__content"><slot :item="item" :index="index">{{ item }}</slot></view>
            <view v-if="$slots.action" class="hsx-mobile-list__action"><slot name="action" :item="item" :index="index" /></view>
        </view>
    </view>
</template>
<style scoped lang="scss">
.hsx-mobile-list { overflow: hidden; border: 1rpx solid var(--hsx-mobile-border); border-radius: 24rpx; background: var(--hsx-mobile-bg-surface); }
.hsx-mobile-list__item { display: flex; min-height: 96rpx; min-width: 0; align-items: center; gap: 20rpx; padding: 20rpx 24rpx; box-sizing: border-box; }
.hsx-mobile-list--divided .hsx-mobile-list__item + .hsx-mobile-list__item { border-top: 1rpx solid var(--hsx-mobile-border); }
.hsx-mobile-list--clickable .hsx-mobile-list__item:active { background: var(--hsx-mobile-bg-muted); }
.hsx-mobile-list__content { min-width: 0; flex: 1; } .hsx-mobile-list__action { flex: none; }
.hsx-mobile-list__empty { padding: 72rpx 24rpx; color: var(--hsx-mobile-text-secondary); font-size: 26rpx; text-align: center; }
.hsx-mobile-list__skeleton { display: flex; align-items: center; gap: 20rpx; padding: 24rpx; }
.hsx-mobile-list__skeleton view { width: 72rpx; height: 72rpx; flex: none; border-radius: 18rpx; background: var(--hsx-mobile-skeleton); animation: hsx-mobile-pulse 1.4s infinite; }
.hsx-mobile-list__skeleton text { width: 60%; height: 24rpx; border-radius: 999rpx; background: var(--hsx-mobile-skeleton); animation: hsx-mobile-pulse 1.4s infinite; }
@keyframes hsx-mobile-pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
</style>
