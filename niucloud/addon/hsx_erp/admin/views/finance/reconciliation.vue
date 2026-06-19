<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">按设备对账</div>
                    <div class="mt-1 text-sm text-gray-500">一台机器一行，从回收到销售的全链路账目：金额、时间、各环节经手人、结算方式与折账原因。可筛选后导出 Excel 对账。</div>
                </div>
                <el-button type="success" :icon="Download" :loading="exporting" :disabled="!rows.length" @click="exportExcel">导出 Excel</el-button>
            </div>

            <!-- 结算方式切换 -->
            <el-tabs v-model="filter.settle_method" class="mt-3" @tab-change="load">
                <el-tab-pane label="全部" name="all" />
                <el-tab-pane label="现金" name="cash" />
                <el-tab-pane label="折账" name="offset" />
            </el-tabs>

            <!-- 筛选 -->
            <el-form :inline="true" @submit.prevent>
                <el-form-item label="往来单位">
                    <counterparty-select v-model="filter.counterparty_id" value-field="member_id" placeholder="按客户/同行筛选" class="!w-[220px]" />
                </el-form-item>
                <el-form-item label="打款状态">
                    <el-select v-model="filter.pay_state" class="!w-[120px]">
                        <el-option label="全部" value="" />
                        <el-option label="已结清" value="settled" />
                        <el-option label="未结清" value="unsettled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="收款状态">
                    <el-select v-model="filter.recv_state" class="!w-[120px]">
                        <el-option label="全部" value="" />
                        <el-option label="已结清" value="settled" />
                        <el-option label="未结清" value="unsettled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="时间">
                    <el-date-picker v-model="filter.dateRange" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始" end-placeholder="结束" class="!w-[240px]" />
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="filter.keyword" placeholder="型号/IMEI/单号" clearable class="!w-[180px]" @keyup.enter="load" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="load">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                </el-form-item>
            </el-form>

            <div v-if="truncated" class="mb-2 text-xs text-orange-500">数据较多，仅展示/导出前 2000 台，请缩小时间范围或指定客户。</div>

            <el-table :data="rows" v-loading="loading" border size="small" empty-text="没有符合条件的账目" max-height="620"
                :default-sort="{ prop: 'recycle_at', order: 'descending' }">
                <el-table-column label="设备" min-width="170" fixed>
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ row.imei || row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="counterparty" label="往来单位" min-width="110" show-overflow-tooltip />
                <el-table-column prop="recycle_price" label="回收价" width="100" align="right" sortable><template #default="{ row }">¥{{ money(row.recycle_price) }}</template></el-table-column>
                <el-table-column prop="recycle_at" label="回收单/时间/确认人" min-width="180" sortable>
                    <template #default="{ row }">
                        <div class="text-xs">{{ row.recycle_no || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ row.recycle_at }} · {{ row.recycle_operator || '—' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="打款" min-width="150">
                    <template #default="{ row }">
                        <div class="text-xs"><el-tag size="small" :type="row.pay_status === '已结清' ? 'success' : 'warning'" effect="light">{{ row.pay_status || '—' }}</el-tag></div>
                        <div class="text-xs text-gray-400">{{ row.pay_operator ? (row.pay_operator + ' · ' + row.pay_at) : '' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="cost" label="成本" width="100" align="right" sortable><template #default="{ row }">¥{{ money(row.cost) }}</template></el-table-column>
                <el-table-column prop="sale_price" label="售价" width="100" align="right" sortable><template #default="{ row }">{{ row.sale_price > 0 ? '¥' + money(row.sale_price) : '-' }}</template></el-table-column>
                <el-table-column prop="sale_at" label="销售单/时间/销售人" min-width="180" sortable>
                    <template #default="{ row }">
                        <div class="text-xs">{{ row.sale_no || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ row.sale_at }} · {{ row.sale_operator || '—' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="profit" label="毛利" width="100" align="right" sortable>
                    <template #default="{ row }"><span :class="row.profit >= 0 ? 'text-green-600' : 'text-red-600'">¥{{ money(row.profit) }}</span></template>
                </el-table-column>
                <el-table-column label="收款" min-width="150">
                    <template #default="{ row }">
                        <div class="text-xs"><el-tag size="small" :type="row.recv_status === '已结清' ? 'success' : 'warning'" effect="light">{{ row.recv_status || '—' }}</el-tag></div>
                        <div class="text-xs text-gray-400">{{ row.collect_operator ? (row.collect_operator + ' · ' + row.collect_at) : '' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="结算方式" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.settle_method === 'offset' ? 'primary' : (row.settle_method === 'cash' ? 'success' : 'info')" effect="light">{{ row.settle_method_text }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="折账原因" min-width="240">
                    <template #default="{ row }"><span class="text-xs text-gray-600">{{ row.offset_reason || '—' }}</span></template>
                </el-table-column>
            </el-table>
            <div class="mt-2 text-sm text-gray-500">共 {{ rows.length }} 台</div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Download } from '@element-plus/icons-vue'
import * as XLSX from 'xlsx'
import { getFinanceReconciliation } from '@/addon/hsx_erp/api/finance'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'

const money = (v: any) => Number(v || 0).toFixed(2)

const loading = ref(false)
const exporting = ref(false)
const rows = ref<any[]>([])
const truncated = ref(false)
const filter = reactive<any>({ counterparty_id: 0, settle_method: 'all', pay_state: '', recv_state: '', dateRange: [], keyword: '' })

const load = async () => {
    loading.value = true
    try {
        const [s, e] = filter.dateRange || []
        const res: any = await getFinanceReconciliation({
            counterparty_id: filter.counterparty_id || 0,
            settle_method: filter.settle_method === 'all' ? '' : filter.settle_method,
            pay_state: filter.pay_state,
            recv_state: filter.recv_state,
            keyword: filter.keyword,
            start_time: s ? Math.floor(new Date(s + ' 00:00:00').getTime() / 1000) : 0,
            end_time: e ? Math.floor(new Date(e + ' 23:59:59').getTime() / 1000) : 0,
        })
        const d = res?.data || {}
        rows.value = d.rows || []
        truncated.value = !!d.truncated
    } finally {
        loading.value = false
    }
}
const reset = () => {
    Object.assign(filter, { counterparty_id: 0, settle_method: 'all', pay_state: '', recv_state: '', dateRange: [], keyword: '' })
    load()
}

const exportExcel = () => {
    if (!rows.value.length) return
    exporting.value = true
    try {
        const header = [
            '型号', 'IMEI', '资产号', '往来单位',
            '回收单号', '回收价', '回收时间', '回收确认人',
            '打款状态', '已付', '未付', '打款人', '打款时间', '付款户头',
            '成本', '销售单号', '售价', '销售时间', '销售人', '毛利',
            '收款状态', '已收', '未收', '收款人', '收款时间', '收款户头',
            '结算方式', '折账金额', '折账原因', '库存状态',
        ]
        const aoa: any[][] = [header]
        for (const r of rows.value) {
            aoa.push([
                r.model, r.imei, r.asset_no, r.counterparty,
                r.recycle_no, r.recycle_price, r.recycle_at, r.recycle_operator,
                r.pay_status, r.paid, r.unpaid, r.pay_operator, r.pay_at, r.pay_account,
                r.cost, r.sale_no, r.sale_price, r.sale_at, r.sale_operator, r.profit,
                r.recv_status, r.received, r.unreceived, r.collect_operator, r.collect_at, r.collect_account,
                r.settle_method_text, r.offset_amount, r.offset_reason, r.status_text,
            ])
        }
        const ws = XLSX.utils.aoa_to_sheet(aoa)
        ws['!cols'] = header.map(() => ({ wch: 14 }))
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, '按设备对账')
        const d = new Date()
        const p = (x: number) => String(x).padStart(2, '0')
        XLSX.writeFile(wb, `按设备对账_${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}_${p(d.getHours())}${p(d.getMinutes())}.xlsx`)
        ElMessage.success('已导出 Excel')
    } finally {
        exporting.value = false
    }
}

onMounted(load)
</script>
