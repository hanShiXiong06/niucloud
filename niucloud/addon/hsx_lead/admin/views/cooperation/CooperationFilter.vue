<template>
    <HsxSearchPanel :collapsible="false" :show-layout-switch="false" class="coop-filter">
        <form class="coop-search" role="search" :aria-label="label" @submit.prevent="apply">
            <el-input v-model="draft.keyword" :prefix-icon="Search" :placeholder="placeholder" clearable aria-label="搜索关键词" @clear="apply" />
            <el-select v-if="statuses.length" v-model="draft.status" clearable placeholder="全部状态" aria-label="状态" @change="apply"><el-option v-for="item in statuses" :key="item.value" :value="item.value" :label="item.label" /></el-select>
            <el-date-picker v-if="withDate" v-model="range" type="daterange" value-format="YYYY-MM-DD" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" unlink-panels :shortcuts="shortcuts" aria-label="创建时间范围" />
            <slot />
            <div class="coop-search-actions"><el-button native-type="submit" type="primary" :loading="loading">查询</el-button><el-button :icon="Refresh" :disabled="loading" aria-label="刷新当前列表" @click="emit('reload')" /><el-button v-if="hasFilters" link @click="reset">清空</el-button></div>
        </form>
        <div v-if="$slots.extra" class="coop-filter-extra"><slot name="extra" /></div>
    </HsxSearchPanel>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Refresh, Search } from '@element-plus/icons-vue'
import HsxSearchPanel from '@/addon/hsx_components/components/HsxSearchPanel/index.vue'
import type { CoopRow } from '../../composables/cooperation'
const props = withDefaults(defineProps<{ query: CoopRow; label?: string; placeholder?: string; statuses?: { value: string; label: string }[]; loading?: boolean; withDate?: boolean }>(), { label: '协作业务筛选', placeholder: '搜索客户、门店或线索号', statuses: () => [], loading: false, withDate: true })
const emit = defineEmits<{ (event: 'search', value: CoopRow): void; (event: 'reload'): void; (event: 'reset'): void }>()
const draft = reactive({ keyword: String(props.query.keyword || ''), status: String(props.query.status || '') }), range = ref<string[] | null>(null)
const hasFilters = computed(() => !!draft.keyword || !!draft.status || !!range.value?.length)
const shortcuts = [{ text: '今天', value: () => { const start = new Date(); start.setHours(0, 0, 0, 0); return [start, new Date()] } }, { text: '近7天', value: () => [new Date(Date.now() - 6 * 86400000), new Date()] }, { text: '近30天', value: () => [new Date(Date.now() - 29 * 86400000), new Date()] }]
function boundary(value: string, end = false) { const [y, m, d] = value.split('-').map(Number); return Math.floor(new Date(y, m - 1, d, end ? 23 : 0, end ? 59 : 0, end ? 59 : 0).getTime() / 1000) }
function apply() { emit('search', { keyword: draft.keyword.trim(), status: draft.status, start_time: range.value?.[0] ? boundary(range.value[0]) : 0, end_time: range.value?.[1] ? boundary(range.value[1], true) : 0 }) }
function reset() { draft.keyword = ''; draft.status = ''; range.value = null; emit('reset'); apply() }
watch(() => [props.query.keyword, props.query.status], () => { draft.keyword = String(props.query.keyword || ''); draft.status = String(props.query.status || '') })
</script>
