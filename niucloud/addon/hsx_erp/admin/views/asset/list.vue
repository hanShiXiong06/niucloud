<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">ERP 设备库存</div>
                    <div class="mt-1 text-sm text-gray-500">手工建档或外部业务同步后先进入待入库，确认后才形成正式库存和成本流水。</div>
                </div>
                <div class="flex gap-2">
                    <el-button type="primary" @click="openManualInbound">手工建档入库</el-button>
                    <el-button :icon="Refresh" @click="loadList">刷新</el-button>
                </div>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input
                        v-model.trim="search.keyword"
                        clearable
                        class="!w-[260px]"
                        placeholder="资产编号 / IMEI / SN / 型号"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item label="库存状态">
                    <el-select v-model="search.inventory_status" clearable class="!w-[150px]" placeholder="全部">
                        <el-option label="待入库" value="pending_in" />
                        <el-option label="在库" value="in_stock" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-alert
                class="mb-4"
                type="info"
                :closable="false"
                title="当前流程：逐台核对待入库设备 → 确认形成正式库存 → 整备/维修追加成本 → 销售定价与上架"
            />

            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    已选择 {{ selectedAssets.length }} 台待入库设备
                </div>
                <el-button
                    type="primary"
                    :disabled="selectedAssets.length === 0"
                    :loading="batchConfirming"
                    @click="batchConfirmInbound"
                >
                    批量确认入库{{ selectedAssets.length ? ` (${selectedAssets.length})` : '' }}
                </el-button>
            </div>

            <el-table
                :data="table.data"
                v-loading="table.loading"
                size="large"
                @selection-change="handleSelectionChange"
            >
                <el-table-column type="selection" width="52" :selectable="rowSelectable" />
                <el-table-column prop="asset_no" label="资产编号" min-width="180" />
                <el-table-column label="设备" min-width="260">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">IMEI：{{ row.imei || '-' }}</div>
                        <div class="text-xs text-gray-500">SN：{{ row.sn || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="source_device_id" label="来源设备ID" width="120" />
                <el-table-column label="归属" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.ownership_type === 'consign' ? 'warning' : 'success'" effect="plain">
                            {{ row.ownership_type === 'consign' ? '代卖' : '自有' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.current_cost) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.inventory_status === 'in_stock' ? 'success' : 'warning'">
                            {{ statusName(row.inventory_status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="stock_in_at" label="入库时间" width="180">
                    <template #default="{ row }">{{ formatTime(row.stock_in_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="170" align="center">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.inventory_status === 'pending_in'"
                            type="primary"
                            link
                            :loading="row._confirming"
                            @click="confirmInbound(row)"
                        >
                            确认入库
                        </el-button>
                        <el-button type="primary" link @click="openDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="table.page"
                    v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next, jumper"
                    :total="table.total"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="manualDialog.visible" title="手工建档入库" width="680px" destroy-on-close>
            <el-alert
                class="mb-4"
                type="info"
                :closable="false"
                title="ERP 可独立使用：手工录入后同样进入待入库，后续核对、库存和成本流水与回收同步设备完全一致。"
            />
            <el-form label-width="110px">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="设备型号" required>
                        <el-input v-model.trim="manualForm.model" placeholder="例如 iPhone 15 Pro" />
                    </el-form-item>
                    <el-form-item label="归属类型">
                        <el-select v-model="manualForm.ownership_type" class="w-full">
                            <el-option label="自有库存" value="owned" />
                            <el-option label="代卖库存" value="consign" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="IMEI">
                        <el-input v-model.trim="manualForm.imei" />
                    </el-form-item>
                    <el-form-item label="IMEI2">
                        <el-input v-model.trim="manualForm.imei2" />
                    </el-form-item>
                    <el-form-item label="SN">
                        <el-input v-model.trim="manualForm.sn" />
                    </el-form-item>
                    <el-form-item label="容量">
                        <el-input v-model.trim="manualForm.capacity" placeholder="例如 256GB" />
                    </el-form-item>
                    <el-form-item label="颜色">
                        <el-input v-model.trim="manualForm.color" />
                    </el-form-item>
                    <el-form-item label="采购成本">
                        <el-input-number v-model="manualForm.purchase_cost" :min="0" :precision="2" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="建议销售价">
                        <el-input-number v-model="manualForm.suggested_sale_price" :min="0" :precision="2" class="!w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="备注">
                    <el-input v-model.trim="manualForm.remark" type="textarea" :rows="3" placeholder="录入来源、采购说明等" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="manualDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="manualDialog.submitting" @click="submitManualInbound">
                    创建待入库设备
                </el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="ERP 设备详情" size="720px">
            <el-descriptions v-if="detail.asset" :column="2" border>
                <el-descriptions-item label="资产编号">{{ detail.asset.asset_no }}</el-descriptions-item>
                <el-descriptions-item label="库存状态">{{ statusName(detail.asset.inventory_status) }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.asset.imei || '-' }}</el-descriptions-item>
                <el-descriptions-item label="SN">{{ detail.asset.sn || '-' }}</el-descriptions-item>
                <el-descriptions-item label="型号" :span="2">{{ detail.asset.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="采购成本">¥{{ money(detail.asset.purchase_cost) }}</el-descriptions-item>
                <el-descriptions-item label="当前总成本">¥{{ money(detail.asset.current_cost) }}</el-descriptions-item>
            </el-descriptions>

            <el-alert
                v-if="detail.asset"
                class="mt-4"
                :type="detail.asset.inventory_status === 'pending_in' ? 'warning' : 'success'"
                :closable="false"
                :title="detail.asset.inventory_status === 'pending_in'
                    ? '下一步：核对串号、型号和成本后确认入库'
                    : '下一步：进入整备/维修与成本管理，完成后进行销售定价和上架'"
            />

            <div class="mt-5 font-medium">库存流水</div>
            <el-table class="mt-3" :data="detail.stock_ledger || []" size="small" empty-text="暂无库存流水">
                <el-table-column prop="action" label="动作" width="120" />
                <el-table-column label="状态变化" min-width="160">
                    <template #default="{ row }">{{ statusName(row.before_status) }} → {{ statusName(row.after_status) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="mt-5 font-medium">成本流水</div>
            <el-table class="mt-3" :data="detail.cost_ledger || []" size="small" empty-text="暂无成本流水">
                <el-table-column prop="cost_type" label="成本类型" width="120" />
                <el-table-column label="变动金额" width="120" align="right">
                    <template #default="{ row }">¥{{ money(row.amount_delta) }}</template>
                </el-table-column>
                <el-table-column label="变动后成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.after_cost) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="120" />
                <el-table-column prop="remark" label="说明" min-width="180" show-overflow-tooltip />
            </el-table>

            <div class="mt-5 font-medium">操作时间线</div>
            <el-timeline class="mt-4">
                <el-timeline-item
                    v-for="item in detail.timeline || []"
                    :key="item.id"
                    :timestamp="formatTime(item.occurred_at)"
                >
                    {{ item.action }} · {{ item.operator_name || '系统' }}
                </el-timeline-item>
            </el-timeline>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import {
    batchConfirmErpAssetInbound,
    confirmErpAssetInbound,
    createErpManualInbound,
    getErpAssetInfo,
    getErpAssetList
} from '@/addon/hsx_erp/api/asset'

const search = reactive({ keyword: '', inventory_status: '' })
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const detailVisible = ref(false)
const detail = reactive<any>({ asset: null, timeline: [] })
const selectedAssets = ref<any[]>([])
const batchConfirming = ref(false)
const manualDialog = reactive({ visible: false, submitting: false })
const manualForm = reactive({
    model: '',
    ownership_type: 'owned',
    imei: '',
    imei2: '',
    sn: '',
    capacity: '',
    color: '',
    purchase_cost: 0,
    suggested_sale_price: 0,
    remark: ''
})

const loadList = async () => {
    table.loading = true
    try {
        const res: any = await getErpAssetList({ ...search, page: table.page, limit: table.limit })
        table.data = res.data?.data || []
        table.total = Number(res.data?.total || 0)
    } finally {
        table.loading = false
    }
}

const handleSearch = () => {
    table.page = 1
    loadList()
}

const handleReset = () => {
    search.keyword = ''
    search.inventory_status = ''
    handleSearch()
}

const resetManualForm = () => {
    Object.assign(manualForm, {
        model: '',
        ownership_type: 'owned',
        imei: '',
        imei2: '',
        sn: '',
        capacity: '',
        color: '',
        purchase_cost: 0,
        suggested_sale_price: 0,
        remark: ''
    })
}

const openManualInbound = () => {
    resetManualForm()
    manualDialog.visible = true
}

const submitManualInbound = async () => {
    if (!manualForm.model) {
        ElMessage.warning('请填写设备型号')
        return
    }
    if (!manualForm.imei && !manualForm.sn) {
        ElMessage.warning('IMEI 和 SN 至少填写一个')
        return
    }
    manualDialog.submitting = true
    try {
        await createErpManualInbound({ ...manualForm })
        ElMessage.success('已创建待入库设备')
        manualDialog.visible = false
        search.inventory_status = 'pending_in'
        table.page = 1
        await loadList()
    } finally {
        manualDialog.submitting = false
    }
}

const rowSelectable = (row: any) => row.inventory_status === 'pending_in'
const handleSelectionChange = (rows: any[]) => {
    selectedAssets.value = rows.filter(rowSelectable)
}

const confirmInbound = async (row: any) => {
    await ElMessageBox.confirm(
        `确认将 ${ row.imei || row.asset_no } 正式入库吗？确认后会生成库存和初始成本流水。`,
        '确认入库',
        { type: 'warning' }
    )
    row._confirming = true
    try {
        await confirmErpAssetInbound(row.id)
        ElMessage.success('入库成功')
        await loadList()
    } finally {
        row._confirming = false
    }
}

const batchConfirmInbound = async () => {
    const assetIds = selectedAssets.value.map((item: any) => Number(item.id))
    if (assetIds.length === 0) {
        ElMessage.warning('请先勾选待入库设备')
        return
    }
    await ElMessageBox.confirm(
        `确认将选中的 ${assetIds.length} 台设备正式入库吗？未勾选的设备会继续保持待入库。`,
        '批量确认入库',
        { type: 'warning' }
    )
    batchConfirming.value = true
    try {
        const res: any = await batchConfirmErpAssetInbound(assetIds)
        ElMessage.success(`已确认入库 ${Number(res.data?.confirmed_count || assetIds.length)} 台`)
        selectedAssets.value = []
        await loadList()
    } finally {
        batchConfirming.value = false
    }
}

const openDetail = async (row: any) => {
    const res: any = await getErpAssetInfo(row.id)
    Object.assign(detail, res.data || {})
    detailVisible.value = true
}

const money = (value: any) => Number(value || 0).toFixed(2)
const statusName = (status: string) => ({
    pending_in: '待入库',
    in_stock: '在库'
}[status] || status || '-')
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('zh-CN')
}

onMounted(loadList)
</script>
