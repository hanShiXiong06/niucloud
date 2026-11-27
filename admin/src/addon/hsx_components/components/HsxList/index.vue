<script lang="ts">
export default { name: 'HsxList' }
</script>

<script setup lang="ts">
import type { AnyRecord } from '../../types'

const props = withDefaults(defineProps<{
    items?: AnyRecord[]
    itemKey?: string | ((item: AnyRecord, index: number) => string | number)
    loading?: boolean
    skeletonRows?: number
    emptyText?: string
    divided?: boolean
    hoverable?: boolean
    clickable?: boolean
}>(), {
    items: () => [], itemKey: 'id', loading: false, skeletonRows: 3, emptyText: '暂无数据', divided: true, hoverable: false, clickable: false
})
const emit = defineEmits<{ (event: 'item-click', item: AnyRecord, index: number): void }>()
function keyOf(item: AnyRecord, index: number) { return typeof props.itemKey === 'function' ? props.itemKey(item, index) : item[props.itemKey] ?? index }
</script>

<template>
    <div class="hsx-list" :class="{ 'hsx-list--divided': divided, 'hsx-list--hoverable': hoverable, 'hsx-list--clickable': clickable }" :aria-busy="loading">
        <template v-if="loading">
            <div v-for="index in skeletonRows" :key="index" class="hsx-list__skeleton"><i /><span /></div>
        </template>
        <slot v-else-if="!items.length" name="empty"><div class="hsx-list__empty">{{ emptyText }}</div></slot>
        <div v-for="(item, index) in items" v-else :key="keyOf(item, index)" class="hsx-list__item" :tabindex="clickable ? 0 : undefined" @click="emit('item-click', item, index)" @keydown.enter="emit('item-click', item, index)">
            <slot :item="item" :index="index">{{ item }}</slot>
            <div v-if="$slots.action" class="hsx-list__action"><slot name="action" :item="item" :index="index" /></div>
        </div>
    </div>
</template>

<style scoped>
.hsx-list { overflow: hidden; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); }
.hsx-list__item { position: relative; display: flex; min-width: 0; align-items: center; gap: var(--hsx-space-3); padding: var(--hsx-space-4); outline: none; transition: background var(--hsx-motion-fast) var(--hsx-ease-standard); }
.hsx-list--divided .hsx-list__item + .hsx-list__item { border-top: 1px solid var(--hsx-border-color); }
.hsx-list--hoverable .hsx-list__item:hover { background: var(--hsx-bg-muted); }
.hsx-list--clickable .hsx-list__item { cursor: pointer; }
.hsx-list--clickable .hsx-list__item:focus-visible { box-shadow: inset 0 0 0 2px var(--hsx-color-primary); }
.hsx-list__action { flex: none; margin-left: auto; }
.hsx-list__empty { padding: 44px 20px; color: var(--hsx-text-secondary); text-align: center; }
.hsx-list__skeleton { display: flex; align-items: center; gap: var(--hsx-space-3); padding: var(--hsx-space-4); }
.hsx-list__skeleton i { width: 38px; height: 38px; flex: none; border-radius: 10px; background: var(--hsx-skeleton-gradient); background-size: 200% 100%; animation: hsx-shimmer 1.4s infinite; }
.hsx-list__skeleton span { width: min(420px, 70%); height: 14px; border-radius: 999px; background: var(--hsx-skeleton-gradient); background-size: 200% 100%; animation: hsx-shimmer 1.4s infinite; }
</style>

