<script lang="ts">export default { name: 'HsxTimeline' }</script>
<script setup lang="ts">
import { computed } from 'vue'
import HsxTag from '../HsxTag/index.vue'
import type { HsxTagTone, HsxTimelineItem } from '../../types'
const props = withDefaults(defineProps<{ items?: HsxTimelineItem[], reverse?: boolean, compact?: boolean, showContent?: boolean }>(), { items: () => [], reverse: false, compact: false, showContent: true })
const actualItems = computed(() => props.reverse ? [...props.items].reverse() : props.items)
function keyOf(item: HsxTimelineItem, index: number) { return item.id ?? `${item.time || 'item'}-${index}` }
function tagOf(item: HsxTimelineItem): { label: string, tone: HsxTagTone } | undefined {
    if (!item.tag) return undefined
    return typeof item.tag === 'string' ? { label: item.tag, tone: item.tone || 'primary' } : { label: item.tag.label, tone: item.tag.tone || item.tone || 'primary' }
}
</script>
<template>
    <ol class="hsx-timeline" :class="{ 'hsx-timeline--compact': compact }">
        <li v-for="(item, index) in actualItems" :key="keyOf(item, index)" class="hsx-timeline__item">
            <div class="hsx-timeline__rail"><slot name="dot" :item="item" :index="index"><i :class="`hsx-timeline__dot--${item.tone || tagOf(item)?.tone || 'primary'}`" /></slot></div>
            <div class="hsx-timeline__main">
                <slot name="header" :item="item" :index="index">
                    <header class="hsx-timeline__header"><div><strong>{{ item.actor || item.title }}</strong><HsxTag v-if="tagOf(item)" :text="tagOf(item)?.label" :tone="tagOf(item)?.tone" /></div><time>{{ item.time }}</time></header>
                </slot>
                <slot name="content" :item="item" :index="index">
                    <div v-if="showContent && (item.content || item.details?.length)" class="hsx-timeline__content">
                        <p v-if="item.content">{{ item.content }}</p>
                        <dl v-if="item.details?.length"><template v-for="detail in item.details" :key="detail.label"><dt>{{ detail.label }}</dt><dd>{{ detail.value }}</dd></template></dl>
                    </div>
                </slot>
            </div>
        </li>
    </ol>
</template>
<style scoped>
.hsx-timeline { padding: 0; margin: 0; list-style: none; }
.hsx-timeline__item { display: grid; min-width: 0; grid-template-columns: 22px minmax(0, 1fr); gap: 12px; }
.hsx-timeline__rail { position: relative; display: flex; justify-content: center; }
.hsx-timeline__rail::after { position: absolute; z-index: 0; top: 16px; bottom: -8px; left: 50%; border-left: 1px dashed var(--hsx-border-strong); content: ''; transform: translateX(-50%); }
.hsx-timeline__item:last-child .hsx-timeline__rail::after { display: none; }
.hsx-timeline__rail i { position: relative; z-index: 1; width: 10px; height: 10px; margin-top: 7px; border: 4px solid var(--hsx-color-primary); border-radius: 50%; background: var(--hsx-bg-surface); }
.hsx-timeline__rail .hsx-timeline__dot--success { border-color: var(--hsx-color-success); }.hsx-timeline__rail .hsx-timeline__dot--warning { border-color: var(--hsx-color-warning); }.hsx-timeline__rail .hsx-timeline__dot--danger { border-color: var(--hsx-color-danger); }.hsx-timeline__rail .hsx-timeline__dot--neutral, .hsx-timeline__rail .hsx-timeline__dot--info { border-color: var(--hsx-text-secondary); }
.hsx-timeline__main { min-width: 0; padding-bottom: 32px; }
.hsx-timeline__header { display: flex; min-height: 28px; align-items: center; justify-content: space-between; gap: 20px; }
.hsx-timeline__header > div { display: flex; min-width: 0; align-items: center; gap: 10px; }.hsx-timeline__header strong { overflow: hidden; color: var(--hsx-text-primary); font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }.hsx-timeline__header time { flex: none; color: var(--hsx-text-secondary); font-size: 13px; font-variant-numeric: tabular-nums; }
.hsx-timeline__content { padding: 16px 18px; margin-top: 10px; border-radius: var(--hsx-radius-lg); color: var(--hsx-text-regular); background: var(--hsx-bg-muted); }.hsx-timeline__content p { margin: 0; line-height: 1.7; }.hsx-timeline__content dl { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 8px 12px; margin: 0; }.hsx-timeline__content dt { color: var(--hsx-text-primary); font-weight: 600; }.hsx-timeline__content dd { min-width: 0; margin: 0; overflow-wrap: anywhere; }
.hsx-timeline--compact .hsx-timeline__main { padding-bottom: 18px; }.hsx-timeline--compact .hsx-timeline__content { padding: 12px 14px; }
@media (max-width: 640px) { .hsx-timeline__header { align-items: flex-start; flex-direction: column; gap: 4px; }.hsx-timeline__header > div { flex-wrap: wrap; } }
</style>
