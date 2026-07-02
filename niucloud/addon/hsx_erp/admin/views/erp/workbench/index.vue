<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">ERP 工作台</div>
                    <div class="mt-1 text-sm text-gray-500">先围绕采购和财务付款跑通闭环，所有付款都由财务确认后正式核销。</div>
                </div>
                <el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">采购单数</div>
                    <div class="summary-value">{{ purchaseRows.length }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">采购金额</div>
                    <div class="summary-value">{{ money(purchaseTotal) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">待财务付款</div>
                    <div class="summary-value text-orange-600">{{ payablePending.length }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">剩余应付</div>
                    <div class="summary-value text-orange-600">{{ money(payableRemain) }}</div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title">待财务确认付款</div>
                        <div class="text-xs text-gray-400">财务核对账户流水后再确认</div>
                    </div>
                    <el-table :data="payablePending" v-loading="loading" size="large" max-height="420">
                        <el-table-column prop="party_name" label="付款对象" min-width="150" />
                        <el-table-column prop="source_no" label="来源单号" min-width="150" />
                        <el-table-column label="剩余应付" width="130" align="right">
                            <template #default="{ row }">{{ money(remain(row)) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="110">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'partial' ? 'primary' : 'warning'">{{ row.status === 'partial' ? '部分付款' : '待付款' }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title">最近采购单</div>
                        <div class="text-xs text-gray-400">采购开单后自动生成库存和应付</div>
                    </div>
                    <el-table :data="purchaseRows" v-loading="loading" size="large" max-height="420">
                        <el-table-column prop="purchase_no" label="采购单号" min-width="160" />
                        <el-table-column prop="party_name" label="采购渠道" min-width="150" />
                        <el-table-column label="金额" width="130" align="right">
                            <template #default="{ row }">{{ money(row.total_cost) }}</template>
                        </el-table-column>
                        <el-table-column label="付款状态" width="110">
                            <template #default="{ row }">
                                <el-tag :type="row.finance_status === 'settled' ? 'success' : row.finance_status === 'partial' ? 'primary' : 'warning'">
                                    {{ row.finance_status === 'settled' ? '已结清' : row.finance_status === 'partial' ? '部分付款' : '待付款' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getErpPayableList, getErpPurchaseList } from '@/addon/hsx_erp/api/erp'

const loading = ref(false)
const purchaseRows = ref<any[]>([])
const payableRows = ref<any[]>([])

const purchaseTotal = computed(() => purchaseRows.value.reduce((sum, row) => sum + Number(row.total_cost || 0), 0))
const payablePending = computed(() => payableRows.value.filter(row => remain(row) > 0))
const payableRemain = computed(() => payablePending.value.reduce((sum, row) => sum + remain(row), 0))

onMounted(loadAll)

async function loadAll() {
    loading.value = true
    try {
        const [purchaseRes, payableRes]: any[] = await Promise.all([
            getErpPurchaseList({ page: 1, limit: 8 }),
            getErpPayableList({ status: '', page: 1, limit: 8 })
        ])
        purchaseRows.value = purchaseRes?.data?.data || []
        payableRows.value = payableRes?.data?.data || []
    } finally {
        loading.value = false
    }
}

function remain(row: any) {
    return Math.max(0, Number(row.amount || 0) - Number(row.settled_amount || 0))
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}
</script>

<style scoped>
.summary-tile {
    border-radius: 8px;
    background: #f8fafc;
    padding: 14px 16px;
}
.summary-label {
    color: #64748b;
    font-size: 13px;
}
.summary-value {
    margin-top: 6px;
    color: #111827;
    font-size: 22px;
    font-weight: 650;
}
.panel {
    border: 1px solid #eef2f7;
    border-radius: 8px;
    padding: 16px;
}
.panel-head {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}
.panel-title {
    color: #111827;
    font-size: 16px;
    font-weight: 650;
}
</style>
