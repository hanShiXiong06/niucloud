<template>
    <div class="erp-dual-layout">
        <!-- 左栏：退货单列表 -->
        <div class="erp-list-panel">
            <div class="panel-header">
                <span class="panel-title">采购退货</span>
                <el-button type="primary" size="small" :icon="Plus" @click="openCreate">新建退货</el-button>
            </div>

            <!-- 状态 Tab -->
            <div class="status-tabs">
                <span
                    v-for="tab in statusTabs" :key="tab.value"
                    class="status-tab"
                    :class="{ active: listWhere.status === tab.value }"
                    @click="switchStatus(tab.value)"
                >{{ tab.label }}</span>
            </div>

            <!-- 搜索 -->
            <div class="panel-search">
                <el-input
                    v-model="listWhere.keyword"
                    placeholder="退货单号/采购单号/供应商"
                    clearable size="small"
                    @change="loadList"
                />
            </div>

            <!-- 列表 -->
            <div v-loading="listLoading" class="panel-list">
                <div
                    v-for="item in listData"
                    :key="item.id"
                    class="list-item"
                    :class="{ selected: selected?.id === item.id }"
                    @click="selectItem(item)"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-sm">{{ item.return_no }}</span>
                        <el-tag :type="statusTagType(item.status)" size="small">{{ statusLabel(item.status) }}</el-tag>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ item.party_name }} · {{ item.purchase_no }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">退货金额 ¥{{ item.total_amount }}</div>
                </div>
                <el-empty v-if="!listLoading && listData.length === 0" description="暂无退货单" :image-size="60" />
            </div>

            <div class="panel-footer">
                <el-pagination
                    v-model:current-page="pagination.page"
                    :page-size="pagination.limit"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    small background
                    @current-change="loadList"
                />
            </div>
        </div>

        <!-- 右栏：详情 / 新建 -->
        <div class="erp-form-panel">
            <!-- 新建表单 -->
            <template v-if="mode === 'create'">
                <div class="form-header">
                    <span class="form-title">新建采购退货单</span>
                    <div class="flex gap-2">
                        <el-button @click="resetCreate">取消</el-button>
                        <el-button type="primary" :loading="submitting" @click="submitCreate">保存</el-button>
                    </div>
                </div>

                <el-form ref="formRef" :model="form" label-width="100px" class="form-body">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="原采购单" prop="purchase_order_id" :rules="[{ required: true, message: '请选择原采购单' }]">
                                <el-select
                                    v-model="form.purchase_order_id"
                                    placeholder="搜索采购单号/供应商"
                                    filterable remote
                                    :remote-method="searchPurchaseOrders"
                                    :loading="purchaseSearchLoading"
                                    value-key="id"
                                    style="width:100%"
                                    @change="onPurchaseOrderChange"
                                >
                                    <el-option
                                        v-for="o in purchaseOptions"
                                        :key="o.id"
                                        :label="`${o.purchase_no} · ${o.party_name}`"
                                        :value="o.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="退款方式">
                                <el-select v-model="form.refund_mode" style="width:100%">
                                    <el-option label="现金退回" value="cash" />
                                    <el-option label="应收冲减" value="offset" />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :span="24">
                            <el-form-item label="备注">
                                <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="退货原因或备注" />
                            </el-form-item>
                        </el-col>
                    </el-row>

                    <!-- 设备选择表 -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">选择退货设备</span>
                            <span class="text-xs text-gray-400">从原采购单的库存设备中选择</span>
                        </div>
                        <el-table
                            :data="availableAssets"
                            v-loading="assetsLoading"
                            size="small"
                            border
                        >
                            <el-table-column type="selection" width="45" />
                            <el-table-column prop="imei" label="IMEI/序列号" min-width="130" />
                            <el-table-column prop="model" label="型号" min-width="120" />
                            <el-table-column prop="spec" label="规格" width="100" />
                            <el-table-column prop="warehouse_name" label="仓库" width="90" />
                            <el-table-column prop="total_cost" label="当前成本" width="90">
                                <template #default="{ row }">¥{{ row.total_cost }}</template>
                            </el-table-column>
                            <el-table-column label="退货价" width="100">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row._return_cost"
                                        :min="0" :precision="2" :step="1"
                                        size="small" style="width:88px"
                                        @change="syncReturnItems"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="退货原因" min-width="120">
                                <template #default="{ row }">
                                    <el-input v-model="row._reason" placeholder="可选" size="small" @change="syncReturnItems" />
                                </template>
                            </el-table-column>
                        </el-table>

                        <div ref="tableRef" @selection-change="onAssetSelectionChange" />

                        <div class="mt-2 flex items-center gap-4 text-sm">
                            <span>已选 <b class="text-primary">{{ form.items.length }}</b> 台</span>
                            <span>退货合计 <b class="text-orange-600">¥{{ totalReturnAmount }}</b></span>
                        </div>
                    </div>
                </el-form>
            </template>

            <!-- 详情视图 -->
            <template v-else-if="mode === 'detail' && selected">
                <div class="form-header">
                    <div>
                        <span class="form-title">{{ selected.return_no }}</span>
                        <el-tag :type="statusTagType(selected.status)" class="ml-2">{{ statusLabel(selected.status) }}</el-tag>
                    </div>
                    <div class="flex gap-2">
                        <el-button
                            v-if="selected.status === 'pending'"
                            type="danger" size="small"
                            @click="doCancel(selected.id)"
                        >撤销</el-button>
                        <el-button
                            v-if="selected.status === 'pending'"
                            type="primary" size="small"
                            :loading="confirming"
                            @click="doConfirm(selected.id)"
                        >财务确认</el-button>
                    </div>
                </div>

                <div class="detail-body">
                    <el-descriptions :column="3" border size="small" class="mb-4">
                        <el-descriptions-item label="供应商">{{ selected.party_name }}</el-descriptions-item>
                        <el-descriptions-item label="原采购单">{{ selected.purchase_no }}</el-descriptions-item>
                        <el-descriptions-item label="退货金额">¥{{ selected.total_amount }}</el-descriptions-item>
                        <el-descriptions-item label="退款方式">{{ refundModeLabel(selected.refund_mode) }}</el-descriptions-item>
                        <el-descriptions-item label="操作员">{{ selected.operator_name }}</el-descriptions-item>
                        <el-descriptions-item label="备注">{{ selected.remark || '-' }}</el-descriptions-item>
                    </el-descriptions>

                    <div class="text-sm font-medium mb-2">退货明细</div>
                    <el-table :data="selectedDetail?.items || []" size="small" border>
                        <el-table-column prop="imei" label="IMEI" min-width="130" />
                        <el-table-column prop="model" label="型号" min-width="110" />
                        <el-table-column prop="return_cost" label="退货价" width="90">
                            <template #default="{ row }">¥{{ row.return_cost }}</template>
                        </el-table-column>
                        <el-table-column prop="paid_amount" label="已付金额" width="90">
                            <template #default="{ row }">¥{{ row.paid_amount }}</template>
                        </el-table-column>
                        <el-table-column label="处理方式" width="120">
                            <template #default="{ row }">
                                <span v-if="row.paid_amount <= 0" class="text-gray-500">冲销应付</span>
                                <span v-else-if="row.paid_amount >= row.return_cost - 0.01" class="text-blue-600">生成应收退款</span>
                                <span v-else class="text-orange-600">混合处理</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reason" label="原因" min-width="100" />
                    </el-table>
                </div>
            </template>

            <!-- 空状态 -->
            <template v-else>
                <el-empty description="选择左侧退货单查看详情，或点击「新建退货」" class="mt-20" />
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, nextTick } from 'vue'
import { Plus, Refresh } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getErpPurchaseReturnList,
    getErpPurchaseReturnInfo,
    createErpPurchaseReturn,
    confirmErpPurchaseReturn,
    cancelErpPurchaseReturn,
} from '@/addon/hsx_erp/api/erp'
import { getErpPurchaseList, getErpPurchaseInfo } from '@/addon/hsx_erp/api/erp'
import { useRoute } from 'vue-router'

// ── 状态 ──────────────────────────────────────────────────────────────────────
const statusTabs = [
    { label: '全部', value: '' },
    { label: '待确认', value: 'pending' },
    { label: '已确认', value: 'confirmed' },
    { label: '已撤销', value: 'cancelled' },
]

const mode = ref<'idle' | 'create' | 'detail'>('idle')
const listLoading = ref(false)
const submitting = ref(false)
const confirming = ref(false)
const listData = ref<any[]>([])
const selected = ref<any>(null)
const selectedDetail = ref<any>(null)
const pagination = reactive({ page: 1, limit: 15, total: 0 })
const listWhere = reactive({ keyword: '', status: '' })

// 新建表单
const formRef = ref()
const tableRef = ref()
const form = reactive({
    purchase_order_id: null as number | null,
    refund_mode: 'cash',
    remark: '',
    items: [] as any[],
})
const purchaseOptions = ref<any[]>([])
const purchaseSearchLoading = ref(false)
const availableAssets = ref<any[]>([])
const assetsLoading = ref(false)
const selectedAssets = ref<any[]>([])

// ── 计算 ──────────────────────────────────────────────────────────────────────
const totalReturnAmount = computed(() =>
    form.items.reduce((s: number, i: any) => s + (Number(i.return_cost) || 0), 0).toFixed(2)
)

// ── 列表 ──────────────────────────────────────────────────────────────────────
async function loadList() {
    listLoading.value = true
    try {
        const res = await getErpPurchaseReturnList({
            ...listWhere,
            page: pagination.page,
            limit: pagination.limit,
        })
        listData.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } finally {
        listLoading.value = false
    }
}

function switchStatus(status: string) {
    listWhere.status = status
    pagination.page = 1
    loadList()
}

async function selectItem(item: any) {
    selected.value = item
    mode.value = 'detail'
    const res = await getErpPurchaseReturnInfo(item.id)
    selectedDetail.value = res.data
}

// ── 新建 ──────────────────────────────────────────────────────────────────────
function openCreate() {
    mode.value = 'create'
    selected.value = null
    form.purchase_order_id = null
    form.refund_mode = 'cash'
    form.remark = ''
    form.items = []
    availableAssets.value = []
}

function resetCreate() {
    mode.value = selected.value ? 'detail' : 'idle'
}

async function searchPurchaseOrders(query: string) {
    if (!query) return
    purchaseSearchLoading.value = true
    try {
        const res = await getErpPurchaseList({ keyword: query, limit: 20 })
        // 采购列表返回设备列表，需要去重采购单
        const orders: Record<number, any> = {}
        ;(res.data?.data || []).forEach((a: any) => {
            if (a.purchase_order_id && !orders[a.purchase_order_id]) {
                orders[a.purchase_order_id] = {
                    id: a.purchase_order_id,
                    purchase_no: a.purchase_no,
                    party_name: a.party_name,
                }
            }
        })
        purchaseOptions.value = Object.values(orders)
    } finally {
        purchaseSearchLoading.value = false
    }
}

async function onPurchaseOrderChange(orderId: number) {
    if (!orderId) {
        availableAssets.value = []
        return
    }
    assetsLoading.value = true
    try {
        const res = await getErpPurchaseInfo(orderId)
        // 过滤出在库设备
        const assets = (res.data?.items || [])
            .filter((item: any) => item.status === 'in_stock' || item.asset_status === 'in_stock')
            .map((item: any) => ({
                ...item,
                _return_cost: Number(item.total_cost || item.purchase_cost || 0),
                _reason: '',
            }))
        availableAssets.value = assets
    } finally {
        assetsLoading.value = false
    }
}

function onAssetSelectionChange(selection: any[]) {
    selectedAssets.value = selection
    syncReturnItems()
}

function syncReturnItems() {
    form.items = selectedAssets.value.map((a: any) => ({
        asset_id: a.asset_id || a.id,
        return_cost: Number(a._return_cost) || 0,
        reason: a._reason || '',
    }))
}

async function submitCreate() {
    await formRef.value?.validate()
    if (!form.items.length) {
        ElMessage.warning('请选择至少一台退货设备')
        return
    }
    submitting.value = true
    try {
        await createErpPurchaseReturn({
            purchase_order_id: form.purchase_order_id,
            refund_mode: form.refund_mode,
            remark: form.remark,
            items: form.items,
        })
        ElMessage.success('退货单已创建，等待财务确认')
        mode.value = 'idle'
        loadList()
    } finally {
        submitting.value = false
    }
}

// ── 确认 / 撤销 ───────────────────────────────────────────────────────────────
async function doConfirm(id: number) {
    await ElMessageBox.confirm('确认后设备将从库存中退出，应付账款将同步处理，是否继续？', '财务确认', { type: 'warning' })
    confirming.value = true
    try {
        await confirmErpPurchaseReturn(id)
        ElMessage.success('退货已确认')
        loadList()
        const res = await getErpPurchaseReturnInfo(id)
        selected.value = listData.value.find((i) => i.id === id) || selected.value
        selectedDetail.value = res.data
    } finally {
        confirming.value = false
    }
}

async function doCancel(id: number) {
    await ElMessageBox.confirm('确认撤销该退货单？撤销后无法恢复。', '撤销退货', { type: 'warning' })
    await cancelErpPurchaseReturn(id)
    ElMessage.success('退货单已撤销')
    loadList()
    mode.value = 'idle'
    selected.value = null
}

// ── 辅助 ──────────────────────────────────────────────────────────────────────
function statusLabel(status: string) {
    const map: Record<string, string> = {
        pending: '待确认', confirmed: '已确认', cancelled: '已撤销',
    }
    return map[status] || status
}
function statusTagType(status: string) {
    const map: Record<string, string> = {
        pending: 'warning', confirmed: 'success', cancelled: 'info',
    }
    return map[status] || ''
}
function refundModeLabel(mode: string) {
    const map: Record<string, string> = { cash: '现金退回', offset: '应收冲减' }
    return map[mode] || mode
}

const route = useRoute()

// 初始化
loadList()
// 如果从采购页带着 purchase_order_id 过来，自动打开新建并预选采购单
if (route.query.purchase_order_id) {
    openCreate()
    form.purchase_order_id = Number(route.query.purchase_order_id)
    onPurchaseOrderChange(Number(route.query.purchase_order_id))
}
</script>

<style scoped>
.erp-dual-layout {
    display: flex;
    gap: 0;
    height: calc(100vh - 120px);
    overflow: hidden;
}
.erp-list-panel {
    width: 300px;
    min-width: 260px;
    border-right: 1px solid #e4e7ed;
    display: flex;
    flex-direction: column;
    background: #fff;
}
.erp-form-panel {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #fff;
}
.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 12px 8px;
    border-bottom: 1px solid #f0f0f0;
}
.panel-title { font-weight: 600; font-size: 14px; }
.status-tabs {
    display: flex;
    padding: 6px 8px;
    gap: 4px;
    border-bottom: 1px solid #f0f0f0;
}
.status-tab {
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    cursor: pointer;
    color: #606266;
    transition: all .2s;
}
.status-tab:hover { background: #f5f7fa; }
.status-tab.active { background: var(--el-color-primary-light-9); color: var(--el-color-primary); font-weight: 500; }
.panel-search { padding: 8px; }
.panel-list { flex: 1; overflow-y: auto; padding: 4px 0; }
.list-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f5f5f5;
    transition: background .15s;
}
.list-item:hover { background: #f5f7fa; }
.list-item.selected { background: var(--el-color-primary-light-9); }
.panel-footer { padding: 8px; border-top: 1px solid #f0f0f0; display: flex; justify-content: center; }
.form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.form-title { font-size: 16px; font-weight: 600; }
.form-body { padding: 0 4px; }
.detail-body { padding: 0 4px; }
</style>
