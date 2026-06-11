<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">入库工作台</div>
                    <div class="mt-1 text-sm text-gray-500">按入库单逐台核对设备；支持部分确认、异常驳回和修正后重新提交。</div>
                </div>
                <el-button :icon="Refresh" @click="loadList">刷新</el-button>
            </div>

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input
                        v-model.trim="search.keyword"
                        clearable
                        class="!w-[260px]"
                        placeholder="入库单号 / 操作人 / 备注"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item label="单据状态">
                    <el-select v-model="search.status" clearable class="!w-[170px]" placeholder="全部">
                        <el-option label="待处理" value="draft" />
                        <el-option label="部分入库" value="partial_confirmed" />
                        <el-option label="全部驳回" value="rejected" />
                        <el-option label="已完成" value="confirmed" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column prop="order_no" label="入库单号" min-width="210" />
                <el-table-column label="来源" width="130">
                    <template #default="{ row }">{{ sourceName(row) }}</template>
                </el-table-column>
                <el-table-column label="往来单位" min-width="150">
                    <template #default="{ row }">{{ row.counterparty?.name || (row.counterparty_id ? `往来单位#${row.counterparty_id}` : '-') }}</template>
                </el-table-column>
                <el-table-column label="处理进度" min-width="240">
                    <template #default="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <el-tag type="info">总数 {{ row.device_count }}</el-tag>
                            <el-tag v-if="row.pending_count" type="warning">待处理 {{ row.pending_count }}</el-tag>
                            <el-tag v-if="row.confirmed_count" type="success">已入库 {{ row.confirmed_count }}</el-tag>
                            <el-tag v-if="row.rejected_count" type="danger">已驳回 {{ row.rejected_count }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="总成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="orderStatusMeta(row.status).type">{{ orderStatusMeta(row.status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="operator_name" label="创建人" width="120" />
                <el-table-column label="创建时间" width="180">
                    <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="110" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openOrder(row)">处理详情</el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.status"
                        icon="search"
                        title="没有符合条件的入库单"
                        description="换个关键词或状态筛选再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="document"
                        title="还没有入库单"
                        description="手工建档或回收同步的设备进入待入库后，会自动生成入库单。"
                    />
                </template>
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

        <el-drawer v-model="detail.visible" title="入库单处理" size="88%" destroy-on-close>
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.order" :column="4" border>
                    <el-descriptions-item label="入库单号">{{ detail.order.order_no }}</el-descriptions-item>
                    <el-descriptions-item label="来源">{{ sourceName(detail.order) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="orderStatusMeta(detail.order.status).type">
                            {{ orderStatusMeta(detail.order.status).label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建人">{{ detail.order.operator_name || '系统' }}</el-descriptions-item>
                    <el-descriptions-item label="往来单位">
                        {{ detail.counterparty?.name || (detail.order.counterparty_id ? `往来单位#${detail.order.counterparty_id}` : '多个/无') }}
                    </el-descriptions-item>
                    <el-descriptions-item label="设备总数">{{ detail.summary.total }}</el-descriptions-item>
                    <el-descriptions-item label="待处理">{{ detail.summary.pending }}</el-descriptions-item>
                    <el-descriptions-item label="已入库">{{ detail.summary.confirmed }}</el-descriptions-item>
                    <el-descriptions-item label="已驳回">{{ detail.summary.rejected }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="4">{{ detail.order.remark || '-' }}</el-descriptions-item>
                </el-descriptions>

                <div class="my-4 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        仅待处理设备可勾选，已选择 {{ selectedItems.length }} 台
                    </div>
                    <div class="flex gap-2">
                        <el-button
                            type="danger"
                            plain
                            :disabled="selectedItems.length === 0"
                            :loading="detail.rejecting"
                            @click="rejectSelected"
                        >
                            驳回选中
                        </el-button>
                        <el-button
                            type="primary"
                            :disabled="selectedItems.length === 0"
                            :loading="detail.confirming"
                            @click="confirmSelected"
                        >
                            确认选中{{ selectedItems.length ? ` (${selectedItems.length})` : '' }}
                        </el-button>
                        <el-button
                            type="success"
                            :disabled="pendingItems.length === 0"
                            :loading="detail.confirming"
                            @click="confirmAllPending"
                        >
                            整单确认剩余 {{ pendingItems.length }} 台
                        </el-button>
                    </div>
                </div>

                <el-table
                    ref="detailTableRef"
                    :data="detail.items"
                    row-key="id"
                    size="large"
                    @selection-change="handleDetailSelection"
                >
                    <el-table-column type="selection" width="52" :selectable="itemSelectable" />
                    <el-table-column label="设备" min-width="250">
                        <template #default="{ row }">
                            <div class="font-medium">{{ row.asset?.model || '-' }}</div>
                            <div class="mt-1 text-xs text-gray-500">IMEI：{{ row.asset?.imei || '-' }}</div>
                            <div class="text-xs text-gray-500">SN：{{ row.asset?.sn || '-' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="asset.asset_no" label="资产编号" min-width="180" />
                    <el-table-column label="采购成本" width="130" align="right">
                        <template #default="{ row }">¥{{ money(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column label="明细状态" width="120">
                        <template #default="{ row }">
                            <el-tag :type="itemStatusMeta(row.status).type">{{ itemStatusMeta(row.status).label }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="异常信息" min-width="220">
                        <template #default="{ row }">
                            <div v-if="row.status === 'rejected'" class="text-red-600">
                                {{ row.reject_reason }}
                                <div class="mt-1 text-xs text-gray-400">
                                    {{ row.rejected_name || '-' }} · {{ formatTime(row.rejected_at) }}
                                </div>
                            </div>
                            <span v-else class="text-gray-400">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="190" align="center">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'pending'" type="primary" link @click="confirmOne(row)">
                                确认
                            </el-button>
                            <el-button v-if="row.status === 'pending'" type="danger" link @click="rejectOne(row)">
                                驳回
                            </el-button>
                            <el-button v-if="row.status === 'rejected'" type="primary" link @click="openResubmit(row)">
                                修正并重提
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-drawer>

        <el-dialog v-model="resubmit.visible" title="修正并重新提交" width="680px" destroy-on-close>
            <el-alert
                class="mb-4"
                type="error"
                :closable="false"
                :title="`上次驳回原因：${resubmit.reason || '-'}`"
            />
            <el-form label-width="100px">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="设备型号" required>
                        <el-input v-model.trim="resubmit.form.model" />
                    </el-form-item>
                    <el-form-item label="采购成本">
                        <el-input-number v-model="resubmit.form.purchase_cost" :min="0" :precision="2" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="IMEI">
                        <el-input v-model.trim="resubmit.form.imei" />
                    </el-form-item>
                    <el-form-item label="IMEI2">
                        <el-input v-model.trim="resubmit.form.imei2" />
                    </el-form-item>
                    <el-form-item label="SN">
                        <el-input v-model.trim="resubmit.form.sn" />
                    </el-form-item>
                    <el-form-item label="容量">
                        <el-input v-model.trim="resubmit.form.capacity" />
                    </el-form-item>
                    <el-form-item label="颜色">
                        <el-input v-model.trim="resubmit.form.color" />
                    </el-form-item>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="resubmit.visible = false">取消</el-button>
                <el-button type="primary" :loading="resubmit.submitting" @click="submitResubmit">重新提交</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="inbound.visible" title="确认入库位置" width="560px">
            <el-alert class="mb-4" type="warning" :closable="false" title="确认后将生成不可变库存流水和初始采购成本流水。" />
            <el-form label-width="100px">
                <el-form-item label="入库仓库" required>
                    <el-select v-model="inbound.warehouse_id" class="w-full" @change="inbound.location_id = 0">
                        <el-option v-for="item in warehouseOptions" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库库位" required>
                    <el-select v-model="inbound.location_id" class="w-full">
                        <el-option v-for="item in availableLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="入库备注"><el-input v-model.trim="inbound.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="inbound.visible = false">取消</el-button>
                <el-button type="primary" :loading="detail.confirming" @click="submitConfirm">确认 {{ inbound.items.length }} 台入库</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import {
    confirmErpStockOrderItems,
    getErpStockOrderInfo,
    getErpStockOrderList,
    rejectErpStockOrderItems,
    resubmitErpStockOrderItem
} from '@/addon/hsx_erp/api/stock_order'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const search = reactive({ keyword: '', status: '' })
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const detailTableRef = ref<any>()
const selectedItems = ref<any[]>([])
const detail = reactive<any>({
    visible: false,
    loading: false,
    confirming: false,
    rejecting: false,
    order: null,
    items: [],
    summary: { total: 0, pending: 0, confirmed: 0, rejected: 0 }
})
const resubmit = reactive<any>({
    visible: false,
    submitting: false,
    itemId: 0,
    reason: '',
    form: { model: '', imei: '', imei2: '', sn: '', capacity: '', color: '', purchase_cost: 0 }
})
const warehouseOptions = ref<any[]>([])
const inbound = reactive<any>({ visible: false, warehouse_id: 0, location_id: 0, remark: '', items: [] })

const pendingItems = computed(() => detail.items.filter((item: any) => item.status === 'pending'))
const availableLocations = computed(() =>
    warehouseOptions.value.find((item: any) => Number(item.id) === Number(inbound.warehouse_id))?.locations || []
)

const loadList = async () => {
    table.loading = true
    try {
        const res: any = await getErpStockOrderList({ ...search, page: table.page, limit: table.limit })
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
    search.status = ''
    handleSearch()
}

const openOrder = async (row: any) => {
    detail.visible = true
    await loadDetail(Number(row.id))
}

const loadDetail = async (orderId = Number(detail.order?.id || 0)) => {
    if (!orderId) return
    detail.loading = true
    selectedItems.value = []
    try {
        const res: any = await getErpStockOrderInfo(orderId)
        Object.assign(detail, res.data || {})
    } finally {
        detail.loading = false
    }
}

const itemSelectable = (row: any) => row.status === 'pending'
const handleDetailSelection = (rows: any[]) => {
    selectedItems.value = rows.filter(itemSelectable)
}

const doConfirm = async (items: any[]) => {
    if (!detail.order?.id || items.length === 0) return
    inbound.items = items
    inbound.remark = ''
    inbound.visible = true
}

const submitConfirm = async () => {
    if (!inbound.warehouse_id || !inbound.location_id) {
        ElMessage.warning('请选择入库仓库和库位')
        return
    }
    detail.confirming = true
    try {
        await confirmErpStockOrderItems(
            Number(detail.order.id),
            inbound.items.map((item: any) => Number(item.id)),
            { warehouse_id: inbound.warehouse_id, location_id: inbound.location_id, remark: inbound.remark }
        )
        ElMessage.success(`已确认入库 ${inbound.items.length} 台`)
        inbound.visible = false
        await Promise.all([loadDetail(), loadList()])
    } finally {
        detail.confirming = false
    }
}

const confirmSelected = () => doConfirm(selectedItems.value)
const confirmAllPending = () => doConfirm(pendingItems.value)
const confirmOne = (row: any) => doConfirm([row])

const doReject = async (items: any[]) => {
    if (!detail.order?.id || items.length === 0) return
    const result: any = await ElMessageBox.prompt(
        `将驳回 ${items.length} 台设备。驳回后可修正设备信息并重新提交。`,
        '驳回入库',
        {
            confirmButtonText: '确认驳回',
            cancelButtonText: '取消',
            inputType: 'textarea',
            inputPlaceholder: '请填写明确的驳回原因',
            inputValidator: (value: string) => value.trim() ? true : '驳回原因不能为空'
        }
    )
    detail.rejecting = true
    try {
        await rejectErpStockOrderItems(
            Number(detail.order.id),
            items.map((item: any) => Number(item.id)),
            String(result.value || '').trim()
        )
        ElMessage.success(`已驳回 ${items.length} 台`)
        await Promise.all([loadDetail(), loadList()])
    } finally {
        detail.rejecting = false
    }
}

const rejectSelected = () => doReject(selectedItems.value)
const rejectOne = (row: any) => doReject([row])

const openResubmit = (row: any) => {
    const asset = row.asset || {}
    resubmit.itemId = Number(row.id)
    resubmit.reason = row.reject_reason || ''
    Object.assign(resubmit.form, {
        model: asset.model || '',
        imei: asset.imei || '',
        imei2: asset.imei2 || '',
        sn: asset.sn || '',
        capacity: asset.capacity || '',
        color: asset.color || '',
        purchase_cost: Number(row.amount || asset.purchase_cost || 0)
    })
    resubmit.visible = true
}

const submitResubmit = async () => {
    if (!detail.order?.id || !resubmit.itemId) return
    if (!resubmit.form.model) {
        ElMessage.warning('请填写设备型号')
        return
    }
    if (!resubmit.form.imei && !resubmit.form.sn) {
        ElMessage.warning('IMEI 和 SN 至少填写一个')
        return
    }
    resubmit.submitting = true
    try {
        await resubmitErpStockOrderItem(Number(detail.order.id), resubmit.itemId, { ...resubmit.form })
        ElMessage.success('已重新提交，设备回到待处理状态')
        resubmit.visible = false
        await Promise.all([loadDetail(), loadList()])
    } finally {
        resubmit.submitting = false
    }
}

const orderStatusMeta = (status: string) => ({
    draft: { label: '待处理', type: 'warning' as const },
    partial_confirmed: { label: '部分入库', type: 'primary' as const },
    rejected: { label: '全部驳回', type: 'danger' as const },
    confirmed: { label: '已完成', type: 'success' as const }
}[status] || { label: status || '-', type: 'info' as const })

const itemStatusMeta = (status: string) => ({
    pending: { label: '待处理', type: 'warning' as const },
    confirmed: { label: '已入库', type: 'success' as const },
    rejected: { label: '已驳回', type: 'danger' as const }
}[status] || { label: status || '-', type: 'info' as const })

const sourceName = (row: any) => {
    if (row.source_plugin === 'hsx_recycle') return '回收插件'
    if (row.source_plugin === 'hsx_erp' && row.source_type === 'manual_inbound') return '手工建档'
    return row.source_plugin || '外部系统'
}
const money = (value: any) => Number(value || 0).toFixed(2)
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('zh-CN')
}

const loadWarehouses = async () => {
    const res: any = await getErpWarehouseOptions()
    warehouseOptions.value = res.data || []
    const defaultWarehouse = warehouseOptions.value.find((item: any) => item.is_default === 1) || warehouseOptions.value[0]
    inbound.warehouse_id = Number(defaultWarehouse?.id || 0)
    inbound.location_id = Number(defaultWarehouse?.locations?.[0]?.id || 0)
}

loadList()
loadWarehouses()
</script>
