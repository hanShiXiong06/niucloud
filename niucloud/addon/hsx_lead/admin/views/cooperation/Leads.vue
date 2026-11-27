<template>
    <section>
        <header class="coop-head"><h2>协作线索</h2></header>
        <CooperationFilter :query="query" :statuses="cooperationStatuses" :loading="loading" placeholder="搜索客户、手机号、型号、门店或线索号" @search="search" @reload="load" @reset="resetExtra">
            <OwnerSelect v-if="viewerScope.can_transfer" v-model="owner" include-unassigned placeholder="按监管负责人筛选" class="coop-owner-filter" />
        </CooperationFilter>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error"><template #default><el-button link type="primary" @click="load">重新加载</el-button></template></el-alert>
        <div class="coop-surface">
            <HsxTable :columns="columns" :data="rows" :loading="loading" row-key="id" empty-text="暂无符合条件的协作线索">
                <template #customer="{row}"><div class="coop-cell"><strong>{{ row.customer_name || '未命名客户' }} <el-tag size="small" type="info">{{ coopBusiness(row.business_type,row.business_name) }}</el-tag></strong><small>{{ row.region_text || '地区未填写' }}</small><small v-if="row.mobile">{{ row.mobile }}</small></div></template>
                <template #models="{row}"><div class="coop-cell"><div class="coop-tags"><el-tag v-for="name in coopModels(row).slice(0,3)" :key="name" size="small" effect="plain" type="info">{{ name }}</el-tag><el-tag v-if="coopModels(row).length>3" size="small" type="info">另{{ coopModels(row).length-3 }}项</el-tag><span v-if="!coopModels(row).length" class="coop-muted">需求未返回</span></div><small v-for="field in businessFormEntries(row).slice(0,2)" :key="field.key">{{ field.label }}：{{ field.value }}</small><small>冻结最低协作费：{{ coopMoney(row.commission_minimum) }}</small></div></template>
                <template #stores="{row}"><div class="coop-cell"><strong>发布：{{ row.publisher?.name || row.publisher_name || '未返回' }}</strong><small>承接：{{ row.receiver?.name || row.receiver_name || '尚未选择' }}</small></div></template>
                <template #status="{row}"><el-tag :type="coopTone(row.status)" size="small">{{ row.status_label || coopLabel(row.status) }}</el-tag></template>
                <template #owner="{row}"><div class="coop-cell"><strong>{{ row.owner_name || (Number(row.owner_uid) ? '平台员工' : '未指定') }}</strong><small>{{ coopTime(row.create_time) }}</small></div></template>
                <template #actions="{row}"><el-button link type="primary" @click="open(row.id)">查看详情</el-button></template>
            </HsxTable>
            <div class="coop-pagination"><HsxPagination :current-page="Number(query.page)" :page-size="Number(query.limit)" :total="total" :disabled="loading" @change="changePage" /></div>
        </div>
        <LeadDetail v-model="detailVisible" :lead-id="selectedId" :viewer-scope="viewerScope" :can-review="canReview" @changed="load" @review="emit('review',$event)" />
    </section>
</template>
<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import LeadDetail from './LeadDetail.vue'
import OwnerSelect from '../components/OwnerSelect.vue'
import { getCooperationLeads } from '../../api/cooperation'
import { cooperationStatuses, coopBusiness, coopLabel, coopModels, coopMoney, coopTime, coopTone, useCooperationList, type CoopRow } from '../../composables/cooperation'
import { businessFormEntries } from '../../composables/business'
const props=withDefaults(defineProps<{ viewerScope?: CoopRow; canReview?: boolean; initialFilter?:CoopRow }>(), { viewerScope: () => ({}), canReview: false, initialFilter:()=>({}) })
const emit = defineEmits<{ (event: 'review', id: number): void }>()
const owner = ref<number | ''>('')
const { rows, total, loading, error, query, load, search: searchList, changePage } = useCooperationList(getCooperationLeads,props.initialFilter)
function search(patch: CoopRow = {}) { return searchList({ ...patch, owner_uid: owner.value }) }
function resetExtra() { owner.value = '' }
const columns = [ { prop: 'customer_name', label: '客户 / 业务 / 地区', slot: 'customer', minWidth: 185 }, { prop: 'models', label: '客户需求 / 冻结规则', slot: 'models', minWidth: 240 }, { prop: 'publisher_name', label: '发布 / 承接门店', slot: 'stores', minWidth: 190 }, { prop: 'status', label: '当前状态', slot: 'status', width: 120 }, { prop: 'owner_uid', label: '监管负责人 / 发布于', slot: 'owner', minWidth: 165 }, { prop: 'actions', label: '操作', slot: 'actions', width: 100, fixed: 'right' as const } ]
const detailVisible = ref(false), selectedId = ref(0)
function open(id: number) { selectedId.value = Number(id); detailVisible.value = true }
onMounted(load)
watch(()=>props.initialFilter,value=>{void searchList(value)},{deep:true})
</script>
<style scoped>.coop-owner-filter{width:180px}</style>
