<template>
    <HsxSearchPanel :collapsible="false" :show-layout-switch="false" class="lead-filter-bar">
        <form class="lead-filter-bar__row" role="search" :aria-label="`${label}筛选`" @submit.prevent="searchKeyword">
            <el-input v-model="keyword" class="lead-filter-bar__keyword" :aria-label="searchLabel" :placeholder="placeholder" clearable :prefix-icon="Search" @clear="clearKeyword" />
            <div class="lead-filter-bar__chips" role="group" aria-label="筛选条件">
                <el-popover v-for="filter in filters" :key="filter.key" v-model:visible="opened[filter.key]" trigger="click" placement="bottom-start" :width="264" popper-class="hsx-lead-filter-popper" @show="beginOption(filter)">
                    <template #reference><el-button :ref="value => setButton(filter.key, value)" class="lead-filter-bar__chip" :class="{ 'is-active': isActive(query[filter.key]) }" :aria-expanded="!!opened[filter.key]" aria-haspopup="dialog" @keydown.esc.stop.prevent="closeFilter(filter.key)"><span>{{ filter.label }}</span><strong v-if="isActive(query[filter.key])">{{ optionLabel(filter) }}</strong><el-icon><ArrowDown /></el-icon></el-button></template>
                    <section role="dialog" :aria-label="`${filter.label}筛选`" class="lead-filter-popover" @keydown.esc.stop.prevent="closeFilter(filter.key)">
                        <header><strong>{{ filter.label }}</strong><span>选择后点击应用</span></header>
                        <el-input v-if="filter.options.length > 8" v-model="optionSearch[filter.key]" :aria-label="`搜索${filter.label}选项`" :placeholder="`搜索${filter.label}`" clearable :prefix-icon="Search" class="lead-filter-popover__option-search" />
                        <el-radio-group v-model="drafts[filter.key]" class="lead-filter-popover__options" :aria-label="filter.label" :disabled="filter.loading">
                            <el-radio value="">全部</el-radio><el-radio v-for="option in visibleOptions(filter)" :key="String(option.value)" :value="option.value">{{ option.label }}</el-radio>
                        </el-radio-group>
                        <p v-if="optionSearch[filter.key] && !visibleOptions(filter).length" class="lead-filter-popover__note">没有匹配的选项，可换个关键词。</p>
                        <p v-if="filter.loading" class="lead-filter-popover__note">正在读取选项…</p>
                        <footer><el-button @click="closeFilter(filter.key)">取消</el-button><el-button type="primary" :disabled="filter.loading" @click="applyOption(filter)">应用</el-button></footer>
                    </section>
                </el-popover>
                <slot name="filters" />
                <el-popover v-model:visible="dateOpened" trigger="click" placement="bottom-start" :width="420" popper-class="hsx-lead-filter-popper" @show="beginDate">
                    <template #reference><el-button :ref="value => setButton('date', value)" class="lead-filter-bar__chip" :class="{ 'is-active': dateActive }" :aria-expanded="dateOpened" aria-haspopup="dialog" :icon="Calendar" @keydown.esc.stop.prevent="closeDate"><span>{{ dateActive ? dateSummary : '时间范围' }}</span><el-icon><ArrowDown /></el-icon></el-button></template>
                    <section role="dialog" class="lead-filter-popover" aria-label="时间范围筛选" @keydown.esc.stop.prevent="closeDate">
                        <header><strong>按时间检索</strong><span>按当前设备时区，包含起止当天</span></header>
                        <div class="lead-filter-popover__field"><span>时间维度</span><el-select v-model="dateDraft.field" :teleported="false" aria-label="时间维度"><el-option v-for="field in timeFields" :key="field.value" :value="field.value" :label="field.label" /></el-select></div>
                        <div class="lead-filter-popover__presets" role="group" aria-label="快捷日期"><el-button v-for="preset in presets" :key="preset.label" size="small" @click="selectPreset(preset.days, preset.yesterday)">{{ preset.label }}</el-button></div>
                        <div class="lead-filter-popover__dates"><div class="lead-filter-popover__field"><span>开始日期</span><el-date-picker v-model="dateDraft.start" type="date" value-format="YYYY-MM-DD" format="YYYY-MM-DD" clearable :teleported="false" aria-label="开始日期" placeholder="不限开始日期" /></div><span>至</span><div class="lead-filter-popover__field"><span>结束日期</span><el-date-picker v-model="dateDraft.end" type="date" value-format="YYYY-MM-DD" format="YYYY-MM-DD" clearable :teleported="false" aria-label="结束日期" placeholder="不限结束日期" /></div></div>
                        <p v-if="dateError" role="alert" class="lead-filter-popover__error">{{ dateError }}</p>
                        <footer><el-button link @click="dateDraft.start = ''; dateDraft.end = ''; dateError = ''">清空日期</el-button><span class="lead-filter-popover__spacer" /><el-button @click="closeDate">取消</el-button><el-button type="primary" @click="applyDate">应用</el-button></footer>
                    </section>
                </el-popover>
            </div>
            <div class="lead-filter-bar__actions"><el-button type="primary" native-type="submit" :loading="loading">查询</el-button><el-tooltip content="保留当前筛选，重新获取数据" placement="top"><el-button :icon="Refresh" :disabled="loading" aria-label="刷新列表" @click="emit('reload')" /></el-tooltip></div>
        </form>
        <div class="lead-filter-bar__summary" aria-live="polite"><span class="lead-filter-bar__count">{{ loading ? '正在查询…' : `共 ${total} 条` }}</span><el-button v-if="activeCount" link type="primary" @click="reset">清空筛选</el-button></div>
        <slot />
    </HsxSearchPanel>
</template>
<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { ArrowDown, Calendar, Refresh, Search } from '@element-plus/icons-vue'
import HsxSearchPanel from '@/addon/hsx_components/components/HsxSearchPanel/index.vue'
import { calendarBoundary, calendarDate, calendarPreset, filterIsActive, type FilterDefinition, type TimeField } from '../../composables/filtering'
const props = withDefaults(defineProps<{ query: Record<string, any>; filters?: FilterDefinition[]; timeFields: TimeField[]; defaultTimeField?: string; placeholder?: string; searchLabel?: string; label?: string; total?: number; loading?: boolean; resetValues?: Record<string, any>; extraActiveCount?: number }>(), { filters: () => [], defaultTimeField: 'create_time', placeholder: '输入关键词搜索', searchLabel: '搜索关键词', label: '列表', total: 0, loading: false, resetValues: () => ({}), extraActiveCount: 0 })
const emit = defineEmits<{ (event: 'apply', value: Record<string, any>): void; (event: 'reload'): void }>()
const keyword = ref(String(props.query.keyword || '')), opened = reactive<Record<string, boolean>>({}), drafts = reactive<Record<string, any>>({}), dateOpened = ref(false), dateError = ref(''), optionSearch = reactive<Record<string, string>>({}), buttons: Record<string, any> = {}
const dateDraft = reactive({ field: props.defaultTimeField, start: '', end: '' })
const isActive = filterIsActive
const activeFilters = computed(() => props.filters.filter(filter => isActive(props.query[filter.key])))
const dateActive = computed(() => Number(props.query.start_time) > 0 || Number(props.query.end_time) > 0)
const activeCount = computed(() => activeFilters.value.length + Number(dateActive.value) + Number(!!String(props.query.keyword || '').trim()) + props.extraActiveCount)
const dateSummary = computed(() => `${props.timeFields.find(field => field.value === props.query.time_field)?.label || props.timeFields.find(field => field.value === props.defaultTimeField)?.label || '时间'}：${Number(props.query.start_time) > 0 ? calendarDate(Number(props.query.start_time)) : '不限'} 至 ${Number(props.query.end_time) > 0 ? calendarDate(Number(props.query.end_time)) : '不限'}`)
const presets = [{ label: '今天', days: 1, yesterday: false }, { label: '昨天', days: 1, yesterday: true }, { label: '近 7 天', days: 7, yesterday: false }, { label: '近 30 天', days: 30, yesterday: false }]
function optionLabel(filter: FilterDefinition) { return filter.options.find(option => String(option.value) === String(props.query[filter.key]))?.label || String(props.query[filter.key]) }
function visibleOptions(filter: FilterDefinition) { const term = (optionSearch[filter.key] || '').trim().toLocaleLowerCase(); return term ? filter.options.filter(option => option.label.toLocaleLowerCase().includes(term)) : filter.options }
function setButton(key: string, value: any) { if (value) buttons[key] = value; else delete buttons[key] }
function restoreFocus(key: string) { void nextTick(() => buttons[key]?.$el?.focus()) }
function closeFilter(key: string) { opened[key] = false; restoreFocus(key) }
function closeDate() { dateOpened.value = false; restoreFocus('date') }
function beginOption(filter: FilterDefinition) { closeOther(filter.key); optionSearch[filter.key] = ''; drafts[filter.key] = props.query[filter.key] ?? '' }
function closeOther(key: string) { Object.keys(opened).forEach(item => { if (item !== key) opened[item] = false }); if (key !== 'date') dateOpened.value = false }
function applyOption(filter: FilterDefinition) { closeFilter(filter.key); emit('apply', { [filter.key]: drafts[filter.key] ?? '' }) }
function beginDate() { closeOther('date'); dateError.value = ''; dateDraft.field = props.timeFields.some(field => field.value === props.query.time_field) ? props.query.time_field : props.defaultTimeField; dateDraft.start = Number(props.query.start_time) > 0 ? calendarDate(Number(props.query.start_time)) : ''; dateDraft.end = Number(props.query.end_time) > 0 ? calendarDate(Number(props.query.end_time)) : '' }
function selectPreset(days: number, yesterday = false) { [dateDraft.start, dateDraft.end] = calendarPreset(days, yesterday); dateError.value = '' }
function applyDate() { if (!props.timeFields.some(field => field.value === dateDraft.field)) { dateError.value = '请选择有效的时间维度。'; return }; const start = calendarBoundary(dateDraft.start), end = calendarBoundary(dateDraft.end, true); if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end < 0 || (start && end && start > end)) { dateError.value = '请选择有效日期，结束日期不能早于开始日期。'; return }; closeDate(); emit('apply', { time_field: dateDraft.field, start_time: start, end_time: end }) }
function searchKeyword() { emit('apply', { keyword: keyword.value.trim() }) }
function clearKeyword() { keyword.value = ''; emit('apply', { keyword: '' }) }
function clearDate() { emit('apply', { time_field: props.defaultTimeField, start_time: 0, end_time: 0 }) }
function reset() { keyword.value = ''; dateOpened.value = false; Object.keys(opened).forEach(key => { opened[key] = false }); emit('apply', { ...props.resetValues, keyword: '', ...Object.fromEntries(props.filters.map(filter => [filter.key, ''])), time_field: props.defaultTimeField, start_time: 0, end_time: 0 }) }
watch(() => props.query.keyword, value => { keyword.value = String(value || '') })
</script>
<style scoped>
/* The toolbar owns vertical spacing; the table card only supplies horizontal gutters. */
.lead-filter-bar.hsx-search-panel { margin: 0 0 12px; padding: 18px 0 12px; border: 0; border-bottom: 1px solid var(--hsx-border-color, #e8edf3); border-radius: 0; background: transparent; }
.lead-filter-bar__row { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.lead-filter-bar .lead-filter-bar__keyword { flex: 1 1 260px; max-width: 360px; min-width: 180px; }
.lead-filter-bar__chips { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.lead-filter-bar__chip.el-button { margin: 0; height: 34px; padding: 0 11px; border-radius: 7px; border-color: var(--hsx-border-color, #dde4ed); background: var(--hsx-bg-surface, #fff); }
.lead-filter-bar__chip :deep(>span) { gap: 6px; }.lead-filter-bar__chip strong { max-width: 140px; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.lead-filter-bar__chip.is-active { color: var(--el-color-primary); background: var(--el-color-primary-light-9); border-color: var(--el-color-primary-light-7); }
.lead-filter-bar__actions { display: flex; gap: 8px; margin-left: auto; }.lead-filter-bar__actions .el-button + .el-button { margin-left: 0; }
.lead-filter-bar__summary { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; min-height: 24px; margin-top: 12px; font-size: 12px; }
.lead-filter-bar__count { font-weight: 600; color: var(--hsx-text-primary, #24344d); margin-right: 6px; }
.lead-filter-popover header { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin: 0 0 14px; }.lead-filter-popover header strong { color: var(--el-text-color-primary); font-size: 14px; }.lead-filter-popover header span,.lead-filter-popover__note { color: var(--el-text-color-secondary); font-size: 12px; }
.lead-filter-popover__option-search { margin-bottom: 8px; }
.lead-filter-popover__options { display: flex; flex-direction: column; align-items: stretch; max-height: 280px; width: 100%; overflow-y: auto; }.lead-filter-popover__options :deep(.el-radio) { margin: 0; min-height: 36px; height: auto; padding: 7px 9px; border-radius: 6px; }.lead-filter-popover__options :deep(.el-radio:hover) { background: var(--el-fill-color-light); }.lead-filter-popover__options :deep(.el-radio.is-checked) { background: var(--el-color-primary-light-9); }.lead-filter-popover__options :deep(.el-radio__label) { white-space: normal; line-height: 20px; }
.lead-filter-popover footer { display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding-top: 14px; margin-top: 12px; border-top: 1px solid var(--el-border-color-lighter); }.lead-filter-popover footer .el-button + .el-button { margin-left: 0; }.lead-filter-popover__spacer { flex: 1; }
.lead-filter-popover__field { display: flex; flex-direction: column; gap: 6px; color: var(--el-text-color-regular); font-size: 12px; min-width: 0; }.lead-filter-popover__presets { display: flex; flex-wrap: wrap; gap: 7px; margin: 14px 0; }.lead-filter-popover__presets .el-button { margin: 0; }.lead-filter-popover__dates { display: flex; gap: 8px; align-items: flex-end; }.lead-filter-popover__dates > .lead-filter-popover__field { flex: 1; }.lead-filter-popover__dates > span { padding-bottom: 9px; color: var(--el-text-color-secondary); }.lead-filter-popover__dates :deep(.el-date-editor) { width: 100%; min-width: 0; }.lead-filter-popover__error { color: var(--el-color-danger); font-size: 12px; margin: 10px 0 0; }
@media (max-width: 700px) { .lead-filter-bar .lead-filter-bar__keyword { max-width: none; flex-basis: 100%; }.lead-filter-bar__actions { margin-left: 0; }.lead-filter-bar__chips { flex: 1; }.lead-filter-popover__dates { flex-wrap: wrap; } }
</style>
<style>
.hsx-lead-filter-popper.el-popover.el-popper { max-width: calc(100vw - 32px); box-sizing: border-box; padding: 16px; border-radius: 10px; box-shadow: 0 8px 28px rgb(26 42 68 / 12%); }
</style>
