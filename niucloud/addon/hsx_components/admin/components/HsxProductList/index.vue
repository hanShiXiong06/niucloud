<script lang="ts">export default { name: 'HsxProductList' }</script>

<script setup lang="ts">
import type { AnyRecord, HsxActionItem, HsxProductFieldMap } from '../../types'
import type { HsxResponsiveNumber } from '../HsxGrid/types'
import HsxGrid from '../HsxGrid/index.vue'
import HsxProductCard from '../HsxProductCard/index.vue'

const props = withDefaults(defineProps<{
    items?: AnyRecord[]
    itemKey?: string | ((item: AnyRecord, index: number) => string | number)
    fieldMap?: HsxProductFieldMap
    actions?: HsxActionItem[] | ((item: AnyRecord, index: number) => HsxActionItem[])
    layout?: 'grid' | 'list'
    columns?: HsxResponsiveNumber
    gap?: number
    loading?: boolean
    skeletonRows?: number
    emptyText?: string
    cardClickable?: boolean
}>(), {
    items: () => [],
    itemKey: 'id',
    fieldMap: () => ({ key: 'id', title: 'title', subtitle: 'subtitle', image: 'image', price: 'price', originalPrice: 'originalPrice', status: 'status', description: 'description' }),
    actions: () => [],
    layout: 'grid',
    columns: () => ({ xs: 1, sm: 2, md: 3, lg: 4, xl: 5 }),
    gap: 16,
    loading: false,
    skeletonRows: 5,
    emptyText: '暂无商品',
    cardClickable: true
})

const emit = defineEmits<{
    (event: 'item-click', item: AnyRecord, index: number): void
    (event: 'action', action: HsxActionItem, item: AnyRecord, index: number): void
}>()

function keyOf(item: AnyRecord, index: number) { return typeof props.itemKey === 'function' ? props.itemKey(item, index) : item[props.itemKey] ?? index }
function actionsOf(item: AnyRecord, index: number) { return typeof props.actions === 'function' ? props.actions(item, index) : props.actions }
</script>

<template>
    <div class="hsx-product-list" :class="`hsx-product-list--${layout}`" :aria-busy="loading">
        <HsxGrid v-if="loading" :columns="layout === 'list' ? 1 : columns" :gap="gap">
            <div v-for="index in skeletonRows" :key="index" class="hsx-product-list__skeleton"><i /><span /><span /></div>
        </HsxGrid>
        <slot v-else-if="!items.length" name="empty"><div class="hsx-product-list__empty">{{ emptyText }}</div></slot>
        <HsxGrid v-else :columns="layout === 'list' ? 1 : columns" :gap="gap">
            <slot v-for="(item, index) in items" :key="keyOf(item, index)" name="item" :item="item" :index="index">
                <HsxProductCard
                    :product="item"
                    :field-map="fieldMap"
                    :actions="actionsOf(item, index)"
                    :layout="layout === 'list' ? 'horizontal' : 'vertical'"
                    :clickable="cardClickable"
                    :status-tone="(_status: any, product: AnyRecord) => product.statusTone || 'success'"
                    @click="emit('item-click', item, index)"
                    @action="emit('action', $event, item, index)"
                >
                    <template v-for="(_, name) in $slots" #[name]="slotProps"><slot :name="name" v-bind="slotProps || {}" :index="index" /></template>
                </HsxProductCard>
            </slot>
        </HsxGrid>
    </div>
</template>

<style scoped>
.hsx-product-list { width: 100%; min-width: 0; }
.hsx-product-list__empty { padding: 64px 20px; border: 1px dashed var(--hsx-border-color); border-radius: var(--hsx-radius-lg); color: var(--hsx-text-secondary); background: var(--hsx-bg-surface); text-align: center; }
.hsx-product-list__skeleton { display: flex; min-height: 220px; flex-direction: column; gap: 12px; padding: 14px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); }
.hsx-product-list__skeleton i { height: 130px; border-radius: 10px; background: var(--hsx-skeleton-gradient); background-size: 200% 100%; animation: hsx-shimmer 1.4s infinite; }
.hsx-product-list__skeleton span { width: 76%; height: 14px; border-radius: 99px; background: var(--hsx-skeleton-gradient); background-size: 200% 100%; animation: hsx-shimmer 1.4s infinite; }
.hsx-product-list__skeleton span:last-child { width: 48%; }
.hsx-product-list--list .hsx-product-list__skeleton { min-height: 150px; }
</style>
