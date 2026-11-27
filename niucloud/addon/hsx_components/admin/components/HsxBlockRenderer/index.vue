<script lang="ts">export default { name: 'HsxBlockRenderer' }</script>
<script setup lang="ts">
import { computed } from 'vue'
import HsxActionBar from '../HsxActionBar/index.vue'
import HsxChartCard from '../HsxChartCard/index.vue'
import HsxStatCard from '../HsxStatCard/index.vue'
import HsxTable from '../HsxTable/index.vue'
import HsxTag from '../HsxTag/index.vue'
import { createBarChartOption, createDonutChartOption, createLineChartOption } from '../../charts'
import type { AnyRecord, HsxActionItem, HsxTableColumn } from '../../types'

export interface HsxContentBlock {
    type: 'stat_grid' | 'table' | 'chart' | 'notice' | 'action_group'
    source_plugin?: string
    data?: AnyRecord
}

const props = withDefaults(defineProps<{
    blocks?: HsxContentBlock[]
    maxTableRows?: number
}>(), { blocks: () => [], maxTableRows: 100 })

const emit = defineEmits<{
    (event: 'action', action: AnyRecord, block: HsxContentBlock): void
    (event: 'chart-click', params: AnyRecord, block: HsxContentBlock): void
}>()

const allowedTypes = new Set(['stat_grid', 'table', 'chart', 'notice', 'action_group'])
const safeBlocks = computed(() => props.blocks.filter((block) => block && allowedTypes.has(block.type)).slice(0, 16))
function blockArray(block: HsxContentBlock, key: string): AnyRecord[] {
    const value = block.data?.[key]
    return Array.isArray(value) ? value : []
}

function tableColumns(block: HsxContentBlock): HsxTableColumn[] {
    return blockArray(block, 'columns').slice(0, 20).map((column: AnyRecord) => ({
        prop: String(column.prop || column.key || ''),
        label: String(column.label || column.title || column.prop || ''),
        width: column.width,
        minWidth: column.min_width || column.minWidth || 120,
        align: ['left', 'center', 'right'].includes(column.align) ? column.align : 'left'
    })).filter((column: HsxTableColumn) => Boolean(column.prop && column.label))
}

function tableRows(block: HsxContentBlock): AnyRecord[] {
    return blockArray(block, 'rows').slice(0, props.maxTableRows)
}

function chartOption(block: HsxContentBlock) {
    const data = block.data || {}
    const chartType = String(data.chart_type || data.type || 'line')
    if (chartType === 'donut' || chartType === 'pie') {
        return createDonutChartOption({
            data: Array.isArray(data.items) ? data.items.map((item: AnyRecord) => ({
                name: String(item.name || item.label || ''), value: Number(item.value || 0), color: item.color
            })) : [],
            centerText: data.center_text,
            centerSubtext: data.center_subtext,
            showLegend: data.show_legend !== false
        })
    }
    const input = {
        labels: Array.isArray(data.labels) ? data.labels : [],
        series: Array.isArray(data.series) ? data.series.map((item: AnyRecord) => ({
            name: String(item.name || ''), data: Array.isArray(item.data) ? item.data.map(Number) : [], color: item.color
        })) : [],
        unit: String(data.unit || ''),
        smooth: data.smooth !== false,
        area: Boolean(data.area),
        horizontal: Boolean(data.horizontal),
        showLegend: data.show_legend !== false
    }
    return chartType === 'bar' ? createBarChartOption(input) : createLineChartOption(input)
}

function actions(block: HsxContentBlock): HsxActionItem[] {
    return blockArray(block, 'actions').slice(0, 8).map((action: AnyRecord) => ({
        key: String(action.id || action.key || ''),
        label: String(action.label || '继续'),
        icon: action.icon,
        type: ['default', 'primary', 'success', 'warning', 'danger', 'info'].includes(action.tone || action.type)
            ? (action.tone || action.type) : 'default',
        plain: action.plain !== false,
        disabled: Boolean(action.disabled),
        confirm: action.confirm ? (typeof action.confirm === 'string' ? action.confirm : {
            title: String(action.confirm.title || '确认操作'),
            message: String(action.confirm.message || `确认执行“${action.label || '该操作'}”吗？`),
            confirmText: String(action.confirm.confirm_text || '确认'),
            cancelText: String(action.confirm.cancel_text || '取消')
        }) : false
    })).filter((action: HsxActionItem) => Boolean(action.key && action.label))
}

function originalAction(block: HsxContentBlock, key: string | number) {
    return blockArray(block, 'actions').find((item: AnyRecord) => String(item.id || item.key || '') === String(key)) || {}
}
</script>

<template>
    <div v-if="safeBlocks.length" class="hsx-block-renderer">
        <template v-for="(block, index) in safeBlocks" :key="`${block.type}_${index}`">
            <section v-if="block.type === 'stat_grid'" class="hsx-block-renderer__stats">
                <HsxStatCard
                    v-for="(item, itemIndex) in (block.data?.items || []).slice(0, 8)"
                    :key="`${item.title}_${itemIndex}`"
                    :title="String(item.title || '')" :value="item.value ?? '-'" :unit="String(item.unit || '')"
                    :description="String(item.description || '')" :trend="item.trend" :trend-label="item.trend_label"
                    :trend-tone="item.trend_tone" :tone="item.tone" :icon="item.icon"
                />
            </section>

            <section v-else-if="block.type === 'table'" class="hsx-block-renderer__section">
                <header v-if="block.data?.title || block.data?.description" class="hsx-block-renderer__header">
                    <div><h4>{{ block.data?.title }}</h4><p v-if="block.data?.description">{{ block.data.description }}</p></div>
                    <HsxTag v-if="tableRows(block).length" :text="`${tableRows(block).length} 条`" tone="info" size="small" />
                </header>
                <HsxTable :columns="tableColumns(block)" :data="tableRows(block)" border stripe />
            </section>

            <HsxChartCard
                v-else-if="block.type === 'chart'"
                :title="String(block.data?.title || '数据趋势')" :subtitle="String(block.data?.subtitle || '')"
                :option="chartOption(block)" :height="Number(block.data?.height || 280)"
                :empty="!((block.data?.series || block.data?.items || []).length)"
                @chart-click="emit('chart-click', $event as AnyRecord, block)"
            />

            <section v-else-if="block.type === 'notice'" class="hsx-block-renderer__notice" :class="`is-${block.data?.tone || 'info'}`">
                <strong>{{ block.data?.title || '提示' }}</strong><p>{{ block.data?.content || '' }}</p>
            </section>

            <HsxActionBar
                v-else-if="block.type === 'action_group' && actions(block).length"
                :actions="actions(block)" align="left" bordered
                @action="emit('action', originalAction(block, $event.key), block)"
            />
        </template>
    </div>
</template>

<style scoped>
.hsx-block-renderer { display: grid; min-width: 0; gap: 12px; }
.hsx-block-renderer__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.hsx-block-renderer__section { min-width: 0; overflow: hidden; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); }
.hsx-block-renderer__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px 16px; border-bottom: 1px solid var(--hsx-border-color); }
.hsx-block-renderer__header h4 { margin: 0; color: var(--hsx-text-primary); font-size: 14px; }
.hsx-block-renderer__header p { margin: 5px 0 0; color: var(--hsx-text-secondary); font-size: 12px; }
.hsx-block-renderer__notice { padding: 13px 15px; border: 1px solid color-mix(in srgb, var(--hsx-color-primary) 28%, var(--hsx-border-color)); border-radius: var(--hsx-radius-lg); background: color-mix(in srgb, var(--hsx-color-primary) 7%, var(--hsx-bg-surface)); }
.hsx-block-renderer__notice.is-warning { border-color: color-mix(in srgb, var(--hsx-color-warning) 34%, var(--hsx-border-color)); background: color-mix(in srgb, var(--hsx-color-warning) 8%, var(--hsx-bg-surface)); }
.hsx-block-renderer__notice.is-danger { border-color: color-mix(in srgb, var(--hsx-color-danger) 34%, var(--hsx-border-color)); background: color-mix(in srgb, var(--hsx-color-danger) 7%, var(--hsx-bg-surface)); }
.hsx-block-renderer__notice strong { color: var(--hsx-text-primary); font-size: 13px; }
.hsx-block-renderer__notice p { margin: 5px 0 0; color: var(--hsx-text-regular); font-size: 13px; line-height: 1.65; }
@media (max-width: 760px) { .hsx-block-renderer__stats { grid-template-columns: 1fr; } }
</style>
