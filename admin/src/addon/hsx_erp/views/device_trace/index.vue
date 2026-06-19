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
                <el-table-column label="从谁收的" min-width="120" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div>{{ row.customer_name || '-' }}</div>
                        <div v-if="row.customer_entity" class="text-xs text-gray-400">主体：{{ row.customer_entity }}</div>
                    </template>
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

        <trace-detail v-model="td.visible" :asset-id="td.assetId" :device-id="td.deviceId" />
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import TraceDetail from './trace-detail.vue'
import { searchDeviceTrace } from '@/addon/hsx_erp/api/device_trace'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const formatTime = (t: any) => {
    const n = Number(t || 0)
    // 0 或明显非法的早期时间(2000年前)都当空，避免显示 1970-01-01
    if (!n || n < 946684800) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

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

const td = reactive<any>({ visible: false, assetId: 0, deviceId: 0 })
function openDetail(row: any) {
    td.assetId = row.asset_id || 0
    td.deviceId = row.device_id || 0
    td.visible = true
}

// 支持从 AI 助手等处带 device_id 进来，直接打开链路详情
const route = useRoute()
onMounted(() => {
    const did = Number(route.query.device_id || 0)
    if (did > 0) {
        td.assetId = 0
        td.deviceId = did
        td.visible = true
    }
})
</script>
