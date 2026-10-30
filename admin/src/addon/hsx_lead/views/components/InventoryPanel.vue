<template>
    <section>
        <div class="lead-page-title"><div><h2>全店库存快照</h2><p>按型号查看各门店的库存参考；不会修改商户 ERP 的价格或库存。</p></div><el-button :icon="Refresh" :loading="loading" @click="load">刷新快照</el-button></div>
        <el-alert title="快照仅供匹配参考，不代表确定可售或已锁货。接单前请与门店核对；过期数据会明确标注。" type="info" :closable="false" show-icon class="lead-banner" />
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="lead-error-banner" />
        <div class="lead-card lead-table-card">
            <HsxSearchPanel :collapsible="false" :show-layout-switch="false"><el-form inline class="lead-search" @submit.prevent="search">
                <el-form-item label="商品 / 规格"><el-input v-model="query.keyword" clearable placeholder="搜索型号、规格或商品名称" @keyup.enter="search" @clear="search" /></el-form-item>
                <el-form-item label="所属门店"><el-select v-model="query.merchant_id" filterable clearable placeholder="全部门店" :loading="merchantLoading"><el-option v-for="merchant in merchants" :key="merchant.id" :value="merchant.id" :label="merchant.name" /></el-select></el-form-item>
                <el-form-item><el-button :icon="Search" @click="search">查询</el-button></el-form-item>
            </el-form><div v-if="merchantError" class="lead-error">{{ merchantError }}</div></HsxSearchPanel>
            <HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无库存快照，请在商户管理中配置 ERP 并同步">
                <template #product="{ row }"><div class="lead-cell"><strong>{{ row.model || row.name || '未识别型号' }}</strong><small>{{ [row.memory, row.color, row.grade].filter(Boolean).join(' · ') || '规格未返回' }}</small></div></template>
                <template #price="{ row }"><span :class="row.price !== null && row.price !== undefined ? 'lead-money' : 'lead-muted'">{{ row.price === null || row.price === undefined || row.price === '' ? '—' : money(row.price) }}</span></template>
                <template #merchant="{ row }"><div class="lead-cell"><strong>{{ row.merchant_name }}</strong><small>{{ row.region_text || region(row) }}</small></div></template>
                <template #availability="{ row }"><div class="lead-cell"><el-tag type="info" effect="plain">待门店确认</el-tag><small>{{ row.quantity == null ? '数量未返回' : `快照数量 ${row.quantity}` }}</small></div></template>
                <template #updated="{ row }"><div class="lead-cell"><small>{{ time(row.last_sync_at || row.source_updated_at) }}</small><el-tag v-if="row.is_stale" type="warning" size="small">数据已过期</el-tag></div></template>
                <template #actions="{ row }"><el-button link type="primary" @click="detail = row; drawer = true">查看快照</el-button></template>
            </HsxTable>
            <div class="lead-pagebar"><HsxPagination v-model:current-page="query.page" v-model:page-size="query.limit" :total="total" @change="load" /></div>
        </div>
        <HsxDrawer v-model="drawer" title="商品快照" subtitle="仅展示最近一次同步结果，实际库存以门店确认结果为准。" size="sm">
            <el-descriptions :column="1" border><el-descriptions-item label="型号">{{ detail.model || detail.name || '未返回' }}</el-descriptions-item><el-descriptions-item label="品牌">{{ detail.brand || '未返回' }}</el-descriptions-item><el-descriptions-item label="规格">{{ [detail.memory, detail.color, detail.grade].filter(Boolean).join(' · ') || '未返回' }}</el-descriptions-item><el-descriptions-item label="快照价格">{{ detail.price == null || detail.price === '' ? '—' : money(detail.price) }}</el-descriptions-item><el-descriptions-item label="快照数量">{{ detail.quantity ?? '未返回' }}</el-descriptions-item><el-descriptions-item label="所属门店">{{ detail.merchant_name }}</el-descriptions-item><el-descriptions-item label="同步时间">{{ time(detail.last_sync_at || detail.source_updated_at) }}</el-descriptions-item><el-descriptions-item label="可售确认">需要门店人工确认</el-descriptions-item></el-descriptions>
            <el-alert v-if="detail.is_stale" title="该快照已过期，请先同步最新库存。" type="warning" :closable="false" style="margin-top: 18px" />
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Refresh, Search } from '@element-plus/icons-vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxSearchPanel from '@/addon/hsx_components/components/HsxSearchPanel/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import * as api from '../../api'
import { errorText, money, region, time, useLeadList, type Row } from '../../composables/useLeadUi'
const { rows, total, loading, error, query, load, search } = useLeadList(api.getInventory, { merchant_id: '' })
const merchants = ref<Row[]>([]), merchantLoading = ref(false), merchantError = ref(''), drawer = ref(false), detail = ref<Row>({})
const columns = [ { prop: 'model', label: '商品 / 规格', slot: 'product', minWidth: 220 }, { prop: 'price', label: '快照价格', slot: 'price', width: 130 }, { prop: 'merchant_name', label: '所属门店', slot: 'merchant', minWidth: 200 }, { prop: 'availability', label: '库存参考', slot: 'availability', width: 145 }, { prop: 'last_sync_at', label: '快照时间', slot: 'updated', minWidth: 170 }, { prop: 'actions', label: '操作', slot: 'actions', width: 100, fixed: 'right' as const } ]
onMounted(async () => { void load(); merchantLoading.value = true; try { const result = await api.getMerchants({ page: 1, limit: 200 }); merchants.value = result.data.data || [] } catch (cause) { merchantError.value = errorText(cause, '门店筛选加载失败，仍可搜索商品。') } finally { merchantLoading.value = false } })
</script>
