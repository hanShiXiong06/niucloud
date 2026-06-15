<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="text-page-title">设备追溯</div>
            <div class="mt-1 text-sm text-gray-500">输入串号(IMEI)/SN/资产号/回收单号，查这台机器从回收到卖出、收款的完整链路。同一串号若多次回收，会列出多条，点开看某一次。</div>

            <div class="mt-4 flex items-center gap-2">
                <el-input v-model.trim="keyword" placeholder="IMEI / SN / 资产号 / 回收单号" clearable class="!w-[320px]" @keyup.enter="doSearch" />
                <el-button type="primary" :loading="loading" @click="doSearch">搜索</el-button>
            </div>

            <el-table :data="list" v-loading="loading" size="large" class="mt-4" empty-text="输入关键词搜索设备">
                <el-table-column label="设备" min-width="200">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="text-xs text-gray-500">IMEI：{{ row.imei || '-' }}<span v-if="row.asset_no"> · 资产号 {{ row.asset_no }}</span></div>
                    </template>
                </el-table-column>
                <el-table-column label="回收单" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.order_no || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ formatTime(row.recycle_time) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="从谁收的" min-width="110" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.customer_name || '-' }}</template>
                </el-table-column>
                <el-table-column label="卖给了谁" min-width="120" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div>{{ row.buyer_name || '-' }}</div>
                        <div v-if="row.buyer_entity" class="text-xs text-gray-400">主体：{{ row.buyer_entity }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="回收价" width="100" align="right"><template #default="{ row }">{{ money(row.recycle_price) }}</template></el-table-column>
                <el-table-column label="售价" width="100" align="right"><template #default="{ row }">{{ row.sale_price > 0 ? money(row.sale_price) : '-' }}</template></el-table-column>
                <el-table-column label="当前状态" width="110" align="center">
                    <template #default="{ row }"><el-tag size="small" effect="light">{{ row.status_text }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" width="90" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">查看链路</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 链路详情抽屉 -->
        <el-drawer v-model="drawer.visible" title="设备全链路" size="760px" @closed="drawer.data = null">
            <div v-loading="drawer.loading">
                <template v-if="drawer.data">
                    <!-- 概览 -->
                    <div class="rounded-lg bg-gray-50 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                            <span class="font-medium">{{ drawer.data.overview.model }}</span>
                            <span class="text-sm text-gray-600">IMEI：{{ drawer.data.overview.imei || '-' }}</span>
                            <span class="text-sm text-gray-600">资产号：{{ drawer.data.overview.asset_no || '-' }}</span>
                            <el-tag size="small" effect="light">{{ statusText(drawer.data.overview.inventory_status) }}</el-tag>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                            <span>从谁收的：<b>{{ drawer.data.overview.customer_name || '-' }}</b><span v-if="drawer.data.overview.customer_phone" class="text-gray-400"> · {{ drawer.data.overview.customer_phone }}</span>
                                <span v-if="drawer.data.overview.customer_entity" class="text-gray-500">（主体：<b class="cursor-pointer text-[var(--el-color-primary)]" @click="openEntity(drawer.data.overview.customer_entity_id)">{{ drawer.data.overview.customer_entity }}</b>）</span>
                            </span>
                            <span>卖给了谁：<b>{{ drawer.data.overview.buyer_name || '-' }}</b><span v-if="drawer.data.overview.buyer_mobile" class="text-gray-400"> · {{ drawer.data.overview.buyer_mobile }}</span>
                                <span v-if="drawer.data.overview.buyer_entity" class="text-gray-500">（主体：<b class="cursor-pointer text-[var(--el-color-primary)]" @click="openEntity(drawer.data.overview.buyer_entity_id)">{{ drawer.data.overview.buyer_entity }}</b>）</span>
                            </span>
                            <span v-if="drawer.data.overview.order_no">回收单：{{ drawer.data.overview.order_no }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-3 text-center">
                            <div><div class="text-xs text-gray-500">回收价</div><div class="mt-1 font-semibold">{{ money(drawer.data.overview.recycle_price) }}</div></div>
                            <div><div class="text-xs text-gray-500">整备费</div><div class="mt-1 font-semibold">{{ money(drawer.data.overview.refurbish_cost) }}</div></div>
                            <div><div class="text-xs text-gray-500">成本合计</div><div class="mt-1 font-semibold text-orange-600">{{ money(drawer.data.overview.current_cost) }}</div></div>
                            <div><div class="text-xs text-gray-500">售价</div><div class="mt-1 font-semibold text-blue-600">{{ drawer.data.overview.sale_price > 0 ? money(drawer.data.overview.sale_price) : '-' }}</div></div>
                            <div><div class="text-xs text-gray-500">毛利</div><div class="mt-1 font-semibold" :class="drawer.data.overview.profit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(drawer.data.overview.profit) }}</div></div>
                            <div><div class="text-xs text-gray-500">其它成本</div><div class="mt-1 font-semibold">{{ money(drawer.data.overview.other_cost) }}</div></div>
                            <div><div class="text-xs text-gray-500">已收</div><div class="mt-1 font-semibold text-green-600">{{ money(drawer.data.overview.received) }}</div></div>
                            <div><div class="text-xs text-gray-500">未收</div><div class="mt-1 font-semibold" :class="drawer.data.overview.unreceived > 0 ? 'text-orange-600' : 'text-gray-400'">{{ money(drawer.data.overview.unreceived) }}</div></div>
                        </div>
                    </div>

                    <!-- 时间线 -->
                    <div class="mt-4 flex items-center justify-between">
                        <span class="font-medium">全链路时间线</span>
                        <el-checkbox v-model="showDetail" label="显示细节节点" />
                    </div>
                    <el-timeline class="mt-3">
                        <el-timeline-item v-for="(e, i) in visibleEvents" :key="i" :timestamp="formatTime(e.time)" placement="top"
                            :type="stageType(e.stage)" :hollow="!e.key">
                            <div class="flex items-center gap-2">
                                <el-tag size="small" :type="stageType(e.stage)" effect="plain">{{ e.stage }}</el-tag>
                                <span class="font-medium">{{ e.title }}</span>
                                <span v-if="Number(e.amount) > 0" class="text-sm text-gray-600">¥{{ Number(e.amount).toFixed(2) }}</span>
                            </div>
                            <div v-if="e.detail" class="mt-0.5 text-sm text-gray-600">{{ e.detail }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">
                                <span v-if="e.operator_name">操作人：{{ e.operator_name }}</span>
                                <span v-if="e.no"> · 单号：{{ e.no }}</span>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                    <div v-if="!visibleEvents.length" class="py-6 text-center text-sm text-gray-400">暂无可展示的链路节点</div>
                </template>
            </div>
        </el-drawer>

        <!-- 主体抽屉(点"卖给了谁"打开) -->
        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" />
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'
import { searchDeviceTrace, getDeviceTraceDetail } from '@/addon/hsx_erp/api/device_trace'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const formatTime = (t: any) => {
    const n = Number(t || 0)
    if (!n) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
const statusText = (s: string) => ({
    in_stock: '在库', refurbishing: '整备中', pending_pricing: '待定价', available_for_sale: '可售',
    locked: '已售/下架', outbound: '已售/下架', pending_in: '待入库'
}[s] || s || '-')
const stageType = (s: string) => (s === '回收' ? 'success' : s === '中台' ? 'primary' : 'warning')

const keyword = ref('')
const loading = ref(false)
const list = ref<any[]>([])
async function doSearch() {
    if (!keyword.value) return ElMessage.warning('请输入关键词')
    loading.value = true
    try {
        const res: any = await searchDeviceTrace(keyword.value)
        list.value = res.data || []
        if (!list.value.length) ElMessage.info('没有匹配的设备')
    } finally {
        loading.value = false
    }
}

const drawer = reactive<any>({ visible: false, loading: false, data: null })
const showDetail = ref(true)
const visibleEvents = computed(() => {
    const evs = drawer.data?.events || []
    return showDetail.value ? evs : evs.filter((e: any) => e.key)
})
const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return
    entityDrawer.id = id
    entityDrawer.visible = true
}
async function openDetail(row: any) {
    drawer.visible = true
    drawer.loading = true
    drawer.data = null
    try {
        const res: any = await getDeviceTraceDetail({ asset_id: row.asset_id || 0, device_id: row.device_id || 0 })
        drawer.data = res.data || null
    } finally {
        drawer.loading = false
    }
}
</script>
