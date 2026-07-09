<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">采购管理</div>
                    <div class="mt-1 text-sm text-gray-500">以每台设备为核心查看采购入库、成本、位置和账目状态；采购单作为批次凭证保留。</div>
                </div>
                <div class="flex gap-2">
                    <el-button :icon="Refresh" :loading="table.loading" @click="loadList">刷新</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreate">采购开单</el-button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="summary-tile">
                    <div class="summary-label">采购台数</div>
                    <div class="summary-value">{{ summary.count }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">设备成本</div>
                    <div class="summary-value">{{ money(summary.totalCost) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">分摊已付</div>
                    <div class="summary-value text-green-600">{{ money(summary.paid) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">分摊未付</div>
                    <div class="summary-value text-orange-600">{{ money(summary.payable) }}</div>
                </div>
            </div>

            <!-- 状态快筛 Tab -->
            <el-tabs v-model="activeTab" class="mt-4 erp-status-tabs" @tab-change="onTabChange">
                <el-tab-pane label="全部" name="" />
                <el-tab-pane label="待付款" name="pending" />
                <el-tab-pane label="部分付款" name="partial" />
                <el-tab-pane label="已结清" name="settled" />
                <el-tab-pane label="已撤销" name="void" />
            </el-tabs>

            <el-form :inline="true" class="mt-2" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[300px]" placeholder="型号 / IMEI / 资产号 / 采购单 / 用户" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="仓库">
                    <el-select v-model="search.warehouse_id" clearable class="!w-[160px]" placeholder="全部仓库" @change="onSearchWarehouseChange">
                        <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库位">
                    <el-select v-model="search.location_id" clearable class="!w-[160px]" placeholder="全部库位" :disabled="!search.warehouse_id">
                        <el-option v-for="item in searchLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-tree-select
                        v-model="search.category_id"
                        :data="categoryTree"
                        :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                        check-strictly
                        clearable
                        class="!w-[220px]"
                        node-key="category_id"
                        placeholder="全部分类"
                    />
                </el-form-item>
                <el-form-item label="采购员">
                    <el-select v-model="search.purchaser_uid" clearable filterable class="!w-[150px]" placeholder="全部">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                    </el-select>
                </el-form-item>
                <el-form-item label="采购时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" start-placeholder="开始" end-placeholder="结束" class="!w-[260px]" />
                </el-form-item>
                <el-form-item label="成本">
                    <el-input-number v-model="search.min_amount" :min="0" :precision="2" :controls="false" placeholder="最低" class="!w-[110px]" />
                    <span class="mx-1 text-gray-400">-</span>
                    <el-input-number v-model="search.max_amount" :min="0" :precision="2" :controls="false" placeholder="最高" class="!w-[110px]" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ row.spec || '-' }} · IMEI {{ row.imei || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">资产号：{{ row.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="采购用户" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.party_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">M号：{{ row.m_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="成本" min-width="160" align="right">
                    <template #default="{ row }">
                        <div>{{ money(row.total_cost) }}</div>
                        <div v-if="Number(row.adjust_cost)" class="mt-1 text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="位置" min-width="160">
                    <template #default="{ row }">{{ [row.warehouse_name, row.location_name].filter(Boolean).join(' / ') || '-' }}</template>
                </el-table-column>
                <el-table-column label="批次" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.purchase_no || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.purchase_at) }}</div>
                        <div class="mt-1 text-xs text-gray-400">采购员：{{ row.purchaser_name || '-' }}</div>
                        <div v-if="row.inspector_name" class="mt-1 text-xs text-gray-400">质检员：{{ row.inspector_name }}</div>
                        <div v-if="Number(row.estimate_sale_price)" class="mt-1 text-xs text-gray-400">预计卖价：{{ money(row.estimate_sale_price) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="付款状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="financeStatusMeta(row.finance_status).type">{{ financeStatusMeta(row.finance_status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="单据" width="110">
                    <template #default="{ row }"><el-tag :type="orderStatusMeta(row.order_status).type" effect="plain">{{ orderStatusMeta(row.order_status).label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }"><el-tag effect="plain">{{ assetStatusLabel(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="230" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">批次</el-button>
                        <el-button v-if="row.order_status !== 'void'" type="primary" link @click="openAdjust(row)">调成本</el-button>
                        <el-button v-if="canReturnPurchase(row)" type="warning" link @click="goReturn(row)">退货</el-button>
                        <el-button v-if="canCancelPurchase(row)" type="danger" link @click="cancelPurchase(row)">撤销</el-button>
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

        <el-dialog v-model="create.visible" title="采购开单" width="980px" destroy-on-close>
            <el-form label-width="96px">
                <div class="section-title">1. 用户</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="采购用户" required>
                        <counterparty-select v-model="create.form.party_id" role-type="supplier" placeholder="搜索或新建采购用户" @resolved="onPartyResolved" />
                    </el-form-item>
                    <el-form-item label="M号">
                        <el-input v-model.trim="create.form.m_no" placeholder="客户M号/业务编号" />
                    </el-form-item>
                </div>

                <div class="section-title">2. 货品</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="入库仓库" required>
                        <el-select v-model="create.form.warehouse_id" class="w-full" placeholder="选择仓库" @change="onWarehouseChange">
                            <el-option v-for="item in warehouses" :key="item.id" :label="item.warehouse_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="入库库位" required>
                        <el-select v-model="create.form.location_id" class="w-full" placeholder="选择库位" :disabled="!create.form.warehouse_id">
                            <el-option v-for="item in currentLocations" :key="item.id" :label="item.location_name" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="mt-1 flex items-center justify-between">
                    <div class="font-medium">机器明细</div>
                    <el-button :icon="Plus" @click="addItem">加一台</el-button>
                </div>
                <el-table :data="create.form.items" class="mt-3" size="large">
                    <el-table-column label="型号" min-width="170">
                        <template #default="{ row }"><el-input v-model.trim="row.model" placeholder="iPhone 15 Pro" /></template>
                    </el-table-column>
                    <el-table-column label="IMEI" min-width="170">
                        <template #default="{ row }"><el-input v-model.trim="row.imei" /></template>
                    </el-table-column>
                    <el-table-column label="规格" min-width="150">
                        <template #default="{ row }"><el-input v-model.trim="row.spec" placeholder="256G 黑色" /></template>
                    </el-table-column>
                    <el-table-column label="采购成本" width="170">
                        <template #default="{ row }"><el-input-number v-model="row.purchase_cost" :min="0" :precision="2" :controls="false" class="!w-full" /></template>
                    </el-table-column>
                    <el-table-column label="备注" min-width="180">
                        <template #default="{ row }"><el-input v-model.trim="row.remark" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" align="center">
                        <template #default="{ row, $index }">
                            <el-button type="primary" link @click="openItemExtra(row, $index)">更多</el-button>
                            <el-button type="danger" link @click="removeItem($index)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-3 text-right text-sm text-gray-500">本单采购成本合计：<span class="font-semibold text-gray-800">{{ money(createTotal) }}</span></div>

                <div class="section-title">3. 账目</div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-3">
                    <el-form-item label="结算方式">
                        <el-select v-model="create.form.settle_mode" class="w-full">
                            <el-option label="挂账，稍后付款" value="credit" />
                            <el-option label="现结，本次付款" value="cash" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="本次付款" required>
                        <el-input-number v-model="create.form.paid_amount" :min="0" :precision="2" :controls="false" class="!w-full" />
                    </el-form-item>
                    <el-form-item v-if="create.form.settle_mode === 'cash'" label="付款账户" required>
                        <el-select v-model="create.form.capital_account_id" class="w-full" placeholder="选择账户">
                            <el-option v-for="item in accounts" :key="item.id" :label="`${item.account_name}（${money(item.balance)}）`" :value="item.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item class="mt-4" label="备注">
                    <el-input v-model.trim="create.form.remark" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="create.visible = false">取消</el-button>
                <el-button type="primary" :loading="create.saving" @click="submitCreate">确认开单</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="itemExtra.visible" title="设备入库信息" width="620px" append-to-body>
            <div v-if="itemExtra.item" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>设备：<span class="font-medium text-gray-900">{{ itemExtra.item.model || `第 ${itemExtra.index + 1} 台` }}</span></div>
                <div class="mt-1">{{ itemExtra.item.spec || '-' }} · IMEI {{ itemExtra.item.imei || '-' }}</div>
            </div>
            <el-form v-if="itemExtra.item" label-width="100px">
                <el-form-item label="质检员">
                    <el-select v-model="itemExtra.item.inspector_uid" clearable filterable class="w-full" placeholder="未质检可不选">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                    </el-select>
                </el-form-item>
                <el-form-item label="预计卖价">
                    <el-input-number v-model="itemExtra.item.estimate_sale_price" :min="0" :precision="2" :controls="false" class="!w-[220px]" />
                </el-form-item>
                <el-form-item label="图片">
                    <el-input v-model.trim="itemExtra.item.image_urls" placeholder="图片地址，多张用逗号分隔" />
                </el-form-item>
                <el-form-item label="质检备注">
                    <el-input v-model.trim="itemExtra.item.quality_remark" type="textarea" :rows="3" placeholder="如：屏幕划痕、电池效率等" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button type="primary" @click="itemExtra.visible = false">完成</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="采购单详情" size="76%" destroy-on-close>
            <div v-loading="detail.loading">
                <el-descriptions v-if="detail.data" :column="4" border>
                    <el-descriptions-item label="采购单号">{{ detail.data.purchase_no }}</el-descriptions-item>
                    <el-descriptions-item label="采购用户">{{ detail.data.party_name }}</el-descriptions-item>
                    <el-descriptions-item label="M号">{{ detail.data.m_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="付款状态">
                        <el-tag :type="financeStatusMeta(detail.data.finance_status).type">{{ financeStatusMeta(detail.data.finance_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="采购金额">{{ money(detail.data.total_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="已付款">{{ money(detail.data.paid_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="剩余应付">{{ money(detail.data.payable_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="仓库">{{ detail.data.warehouse_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="库位">{{ detail.data.location_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="付款账户">{{ detail.data.capital_account_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="采购员">{{ detail.data.purchaser_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="4">{{ detail.data.remark || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-5 font-medium">机器明细</div>
                <el-table class="mt-3" :data="detail.data?.items || []" size="large">
                    <el-table-column prop="model" label="型号" min-width="180" />
                    <el-table-column prop="imei" label="IMEI" min-width="170" />
                    <el-table-column prop="spec" label="规格" min-width="150" />
                    <el-table-column prop="inspector_name" label="质检员" min-width="120" />
                    <el-table-column label="预计卖价" width="130" align="right">
                        <template #default="{ row }">{{ Number(row.estimate_sale_price || 0) ? money(row.estimate_sale_price) : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="成本" width="160" align="right">
                        <template #default="{ row }">
                            <div>{{ money(row.total_cost) }}</div>
                            <div v-if="Number(row.adjust_cost)" class="text-xs text-gray-500">调整 {{ money(row.adjust_cost) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="quality_remark" label="质检备注" min-width="180" />
                    <el-table-column prop="remark" label="备注" min-width="180" />
                    <el-table-column label="操作" width="110" align="center">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="openAdjust(row)">调成本</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-drawer>

        <el-dialog v-model="adjust.visible" title="成本调整" width="620px">
            <div v-if="adjust.item" class="mb-4 rounded bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <div>设备：<span class="font-medium text-gray-900">{{ adjust.item.model || '-' }}</span></div>
                <div class="mt-1">{{ adjust.item.spec || '-' }} · IMEI {{ adjust.item.imei || '-' }}</div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">当前成本</div>
                    <div class="mt-1 font-medium text-gray-900">{{ money(adjustCurrentCost) }}</div>
                </div>
                <div :class="adjust.form.type === 'deduct' ? 'bg-red-50' : 'bg-blue-50'" class="rounded px-3 py-2">
                    <div class="text-xs text-gray-500">本次调整</div>
                    <div class="mt-1 font-medium" :class="adjust.form.type === 'deduct' ? 'text-red-600' : 'text-blue-600'">{{ money(adjustSignedAmount) }}</div>
                </div>
                <div class="rounded bg-gray-50 px-3 py-2">
                    <div class="text-xs text-gray-500">调整后成本</div>
                    <div class="mt-1 font-medium text-gray-900">{{ money(adjustAfterCost) }}</div>
                </div>
            </div>
            <el-form label-width="90px">
                <el-form-item label="调整类型" required>
                    <el-radio-group v-model="adjust.form.type">
                        <el-radio-button label="deduct">扣款</el-radio-button>
                        <el-radio-button label="supplement">补款</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="调整金额" required>
                    <el-input-number v-model="adjust.form.amount" :min="0" :precision="2" :controls="false" class="!w-[220px]" />
                    <div class="mt-1 text-xs text-gray-500">只填正数；扣款会减少成本，补款会增加成本。</div>
                </el-form-item>
                <el-form-item label="影响说明">
                    <el-alert
                        :closable="false"
                        show-icon
                        :type="adjust.form.type === 'deduct' ? 'warning' : 'info'"
                        :title="adjust.form.type === 'deduct' ? '扣款会减少采购成本和应付款，后续毛利会增加。' : '补款会增加采购成本和应付款，后续毛利会减少。'"
                    />
                </el-form-item>
                <el-form-item label="原因">
                    <el-input v-model.trim="adjust.form.remark" type="textarea" :rows="2" placeholder="例如：验机发现屏幕瑕疵，扣供应商 100" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="adjust.visible = false">取消</el-button>
                <el-button type="primary" :loading="adjust.saving" @click="submitAdjust">确认调整并记账</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'
import { adjustErpPurchaseCost, cancelErpPurchase, createErpPurchase, getErpGoodsCategoryTree, getErpPurchaseInfo, getErpPurchaseList, getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'

const search = reactive<any>({ keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
const activeTab = ref('')
const router = useRouter()

function onTabChange(tab: string) {
    // Tab 映射：void 用 status 字段，其余用 finance_status
    if (tab === 'void') {
        search.finance_status = ''
        search.status = 'void'
    } else {
        search.finance_status = tab
        search.status = ''
    }
    table.page = 1
    loadList()
}
const table = reactive({ loading: false, data: [] as any[], page: 1, limit: 15, total: 0 })
const accounts = ref<any[]>([])
const warehouses = ref<any[]>([])
const categoryTree = ref<any[]>([])
const staffOptions = ref<any[]>([])
const currentUid = ref(0)
const create = reactive({ visible: false, saving: false, form: defaultForm() })
const itemExtra = reactive({ visible: false, index: -1, item: null as any })
const detail = reactive({ visible: false, loading: false, data: null as any })
const adjust = reactive({ visible: false, saving: false, itemId: 0, item: null as any, form: { type: 'deduct', amount: 0, remark: '' } })

const summary = computed(() => {
    return table.data.reduce((acc, row: any) => {
        if (row.order_status === 'void') return acc
        const paid = allocatedPaid(row)
        acc.count += 1
        acc.totalCost += Number(row.total_cost || 0)
        acc.paid += paid
        acc.payable += Math.max(0, Number(row.total_cost || 0) - paid)
        return acc
    }, { count: 0, totalCost: 0, paid: 0, payable: 0 })
})
const createTotal = computed(() => create.form.items.reduce((sum: number, row: any) => sum + Number(row.purchase_cost || 0), 0))
const currentWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(create.form.warehouse_id)) || null)
const currentLocations = computed(() => currentWarehouse.value?.locations || [])
const searchWarehouse = computed(() => warehouses.value.find(row => Number(row.id) === Number(search.warehouse_id)) || null)
const searchLocations = computed(() => searchWarehouse.value?.locations || [])
const adjustCurrentCost = computed(() => Number(adjust.item?.total_cost || 0))
const adjustSignedAmount = computed(() => {
    const amount = Number(adjust.form.amount || 0)
    return adjust.form.type === 'deduct' ? -amount : amount
})
const adjustAfterCost = computed(() => adjustCurrentCost.value + adjustSignedAmount.value)

watch(createTotal, amount => {
    if (create.form.settle_mode === 'cash') create.form.paid_amount = amount
})
watch(() => create.form.settle_mode, mode => {
    create.form.paid_amount = mode === 'cash' ? createTotal.value : 0
    if (mode !== 'cash') create.form.capital_account_id = 0
})

onMounted(() => {
    loadList()
    loadAccounts()
    loadWarehouses()
    loadStaffOptions()
    loadCategories()
})

function defaultForm() {
    return {
        party_id: 0,
        party_name: '',
        m_no: '',
        purchase_channel: '',
        purchaser_uid: 0,
        settle_method: '',
        settle_mode: 'credit',
        paid_amount: 0,
        capital_account_id: 0,
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        remark: '',
        items: [blankItem()]
    }
}

function blankItem() {
    return { model: '', imei: '', spec: '', purchase_cost: 0, inspector_uid: null, estimate_sale_price: 0, image_urls: '', quality_remark: '', remark: '' }
}

async function loadList() {
    table.loading = true
    try {
        const res: any = await getErpPurchaseList({ ...buildSearchParams(), page: table.page, limit: table.limit })
        table.data = res?.data?.data || []
        table.total = res?.data?.total || 0
    } finally {
        table.loading = false
    }
}

async function loadAccounts() {
    const res: any = await getCapitalAccounts()
    accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
}

async function loadWarehouses() {
    const res: any = await getErpWarehouseOptions()
    warehouses.value = Array.isArray(res?.data) ? res.data : []
    applyDefaultWarehouse()
}

async function loadStaffOptions() {
    const res: any = await getErpStaffOptions()
    currentUid.value = Number(res?.data?.current_uid || 0)
    staffOptions.value = res?.data?.users || []
}

async function loadCategories() {
    const res: any = await getErpGoodsCategoryTree()
    categoryTree.value = Array.isArray(res?.data) ? res.data : []
}

function buildSearchParams() {
    const [start_at, end_at] = Array.isArray(search.dateRange) ? search.dateRange : []
    return {
        ...search,
        start_at: start_at || '',
        end_at: end_at || '',
        dateRange: undefined
    }
}

function handleSearch() {
    table.page = 1
    loadList()
}

function handleReset() {
    Object.assign(search, { keyword: '', finance_status: '', status: '', warehouse_id: '', location_id: '', category_id: '', purchaser_uid: '', dateRange: [], min_amount: undefined, max_amount: undefined })
    activeTab.value = ''
    handleSearch()
}

function onSearchWarehouseChange() {
    search.location_id = ''
}

function openCreate() {
    create.form = defaultForm()
    create.form.purchaser_uid = currentUid.value || staffOptions.value[0]?.uid || 0
    create.visible = true
    loadAccounts()
    loadWarehouses()
    loadStaffOptions()
}

function applyDefaultWarehouse() {
    if (!create.visible || create.form.warehouse_id || !warehouses.value.length) return
    create.form.warehouse_id = Number(warehouses.value[0]?.id || 0)
    onWarehouseChange()
}

function onWarehouseChange() {
    const warehouse = currentWarehouse.value
    create.form.warehouse_name = warehouse?.warehouse_name || ''
    const firstLocation = warehouse?.locations?.[0]
    create.form.location_id = firstLocation?.id || 0
    create.form.location_name = firstLocation?.location_name || ''
}

function addItem() {
    create.form.items.push(blankItem())
}

function removeItem(index: number) {
    if (create.form.items.length === 1) {
        ElMessage.warning('至少保留一台机器')
        return
    }
    if (itemExtra.index === index) itemExtra.visible = false
    create.form.items.splice(index, 1)
}

function openItemExtra(row: any, index: number) {
    itemExtra.item = row
    itemExtra.index = index
    itemExtra.visible = true
}

async function submitCreate() {
    if (!create.form.party_id && !create.form.party_name) return ElMessage.warning('请选择采购渠道')
    if (!create.form.items.length || create.form.items.some((row: any) => !row.model || Number(row.purchase_cost || 0) <= 0)) {
        return ElMessage.warning('请补全机器型号和采购成本')
    }
    if (!create.form.warehouse_id) return ElMessage.warning('请选择入库仓库')
    if (!create.form.location_id) return ElMessage.warning('请选择入库库位')
    create.form.purchaser_uid = create.form.purchaser_uid || currentUid.value || staffOptions.value[0]?.uid || 0
    if (create.form.settle_mode === 'cash') {
        if (Number(create.form.paid_amount || 0) <= 0) return ElMessage.warning('请填写本次付款')
        if (!create.form.capital_account_id) return ElMessage.warning('请选择付款账户')
        if (Number(create.form.paid_amount || 0) > createTotal.value) return ElMessage.warning('付款不能大于采购成本')
    }
    create.saving = true
    try {
        await createErpPurchase({
            ...create.form,
            warehouse_name: currentWarehouse.value?.warehouse_name || create.form.warehouse_name,
            location_name: currentLocations.value.find((row: any) => Number(row.id) === Number(create.form.location_id))?.location_name || create.form.location_name,
            settle_method: create.form.settle_mode === 'cash' ? '现结' : '挂账'
        })
        ElMessage.success('采购单已生成')
        create.visible = false
        loadList()
    } finally {
        create.saving = false
    }
}

function onPartyResolved(row: any) {
    create.form.party_name = row?.party_name || row?.name || ''
    create.form.m_no = row?.m_no || create.form.m_no || ''
}

async function openDetail(row: any) {
    detail.visible = true
    detail.loading = true
    try {
        const res: any = await getErpPurchaseInfo(row.purchase_order_id || row.id)
        detail.data = res?.data || null
    } finally {
        detail.loading = false
    }
}

function openAdjust(row: any) {
    adjust.itemId = row.id
    adjust.item = row
    adjust.form = { type: 'deduct', amount: 0, remark: '' }
    adjust.visible = true
}

function canCancelPurchase(row: any) {
    return row.order_status === 'completed' && row.finance_status === 'pending' && row.status === 'in_stock'
}

/** 设备仍在库（未被销售/退货/作废）且采购单未撤销，才能发起退货 */
function canReturnPurchase(row: any) {
    return row.status === 'in_stock' && row.order_status !== 'void'
}

function goReturn(row: any) {
    router.push({
        path: '/site/hsx_erp/purchase_return',
        query: { purchase_order_id: row.purchase_order_id },
    })
}

async function cancelPurchase(row: any) {
    try {
        const result: any = await ElMessageBox.prompt(
            '撤销后设备会从库存移出，应付款作废。已有付款或折账的单据不能撤销，需要走退货/调整。',
            '撤销采购单',
            {
                confirmButtonText: '确认撤销',
                cancelButtonText: '取消',
                inputPlaceholder: '填写撤销原因，便于后续追溯'
            }
        )
        await cancelErpPurchase(Number(row.purchase_order_id || row.id), { remark: result?.value || '' })
        ElMessage.success('采购单已撤销')
        await loadList()
    } catch (e: any) {
        if (e !== 'cancel' && e !== 'close') throw e
    }
}

async function submitAdjust() {
    if (!adjust.form.amount) return ElMessage.warning('请填写调整金额')
    if (adjustAfterCost.value < 0) return ElMessage.warning('调整后成本不能小于0')
    adjust.saving = true
    try {
        await adjustErpPurchaseCost(adjust.itemId, {
            amount: adjustSignedAmount.value,
            remark: adjust.form.remark
        })
        ElMessage.success('成本已调整')
        adjust.visible = false
        if (detail.data?.id) await openDetail(detail.data)
        loadList()
    } finally {
        adjust.saving = false
    }
}

function financeStatusMeta(status: string) {
    const map: any = {
        pending: { label: '待付款', type: 'warning' },
        partial: { label: '部分付款', type: 'primary' },
        settled: { label: '已结清', type: 'success' },
        void: { label: '已撤销', type: 'info' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function assetStatusLabel(status: string) {
    const map: any = { in_stock: '在库', sold: '已售', returned: '已退', void: '作废' }
    return map[status] || status || '-'
}

function orderStatusMeta(status: string) {
    const map: any = {
        completed: { label: '已完成', type: 'success' },
        returned: { label: '已退货', type: 'warning' },
        void: { label: '已撤销', type: 'info' }
    }
    return map[status] || { label: status || '-', type: 'info' }
}

function allocatedPaid(row: any) {
    const itemCost = Number(row.total_cost || 0)
    const orderCost = Number(row.order_total_cost || 0)
    const orderPaid = Number(row.paid_amount || 0)
    if (itemCost <= 0 || orderCost <= 0 || orderPaid <= 0) return 0
    return Math.min(itemCost, itemCost * Math.min(orderPaid / orderCost, 1))
}

function money(value: any) {
    return `¥${Number(value || 0).toFixed(2)}`
}

function formatTime(value: any) {
    const time = Number(value || 0)
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}

function staffName(user: any) {
    return user?.name || user?.real_name || user?.username || `员工#${user?.uid || '-'}`
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
.section-title {
    margin: 18px 0 12px;
    border-left: 3px solid var(--el-color-primary);
    padding-left: 10px;
    color: #111827;
    font-size: 15px;
    font-weight: 650;
}
</style>
