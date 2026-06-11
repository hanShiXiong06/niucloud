<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">销售定价</div>
                    <div class="mt-1 text-sm text-gray-500">
                        对已完成入库和整备的设备确认销售价，定价后进入可售库存，后续可被客户锁定和销售出库。
                    </div>
                </div>
                <el-button :icon="Refresh" @click="loadList">刷新</el-button>
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
                <el-form-item label="状态">
                    <el-select v-model="search.inventory_status" clearable class="!w-[160px]" placeholder="全部">
                        <el-option label="待销售定价" value="pending_pricing" />
                        <el-option label="可售" value="available_for_sale" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <el-alert
                class="mb-4"
                type="info"
                :closable="false"
                title="定价只固化销售口径，不产生收款。销售出库后才会形成应收、收入和利润事实。"
            />

            <el-table :data="table.data" v-loading="table.loading" size="large">
                <el-table-column label="设备" min-width="280">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">资产：{{ row.asset_no || '-' }}</div>
                        <div class="text-xs text-gray-500">IMEI：{{ row.imei || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="当前成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.current_cost) }}</template>
                </el-table-column>
                <el-table-column label="销售价" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.current_sale_price) }}</template>
                </el-table-column>
                <el-table-column label="毛利" width="130" align="right">
                    <template #default="{ row }">
                        <span :class="Number(row.gross_profit || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
                            ¥{{ money(row.gross_profit) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="最近定价" min-width="170">
                    <template #default="{ row }">
                        <div>{{ row.latest_price_log?.operator_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ formatTime(row.latest_price_log?.occurred_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.inventory_status)">
                            {{ statusName(row.inventory_status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="190" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openPrice(row)">
                            {{ row.inventory_status === 'available_for_sale' ? '调价' : '定价' }}
                        </el-button>
                        <el-button type="primary" link @click="openDetail(row)">详情</el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.inventory_status"
                        icon="search"
                        title="没有符合条件的设备"
                        description="换个关键词或库存状态再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="document"
                        title="暂无待定价设备"
                        description="设备完成整备、进入「待销售定价」后会自动出现在这里。"
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

        <el-dialog v-model="priceDialog.visible" :title="priceDialog.isAdjust ? '调整销售价' : '销售定价'" width="560px">
            <el-descriptions v-if="priceDialog.asset" class="mb-4" :column="2" border>
                <el-descriptions-item label="设备">{{ priceDialog.asset.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="资产编号">{{ priceDialog.asset.asset_no || '-' }}</el-descriptions-item>
                <el-descriptions-item label="当前成本">¥{{ money(priceDialog.asset.current_cost) }}</el-descriptions-item>
                <el-descriptions-item label="原销售价">¥{{ money(priceDialog.asset.current_sale_price) }}</el-descriptions-item>
            </el-descriptions>
            <el-form label-width="110px">
                <el-form-item label="销售价" required>
                    <el-input-number v-model="priceDialog.form.sale_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="最低利润">
                    <el-input-number v-model="priceDialog.form.min_profit" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-alert
                    class="mb-4"
                    :type="priceProfit >= 0 ? 'success' : 'error'"
                    :closable="false"
                    :title="`预计毛利 ¥${money(priceProfit)}，毛利率 ${priceMargin}%`"
                />
                <el-form-item label="定价说明">
                    <el-input
                        v-model.trim="priceDialog.form.remark"
                        type="textarea"
                        :rows="3"
                        placeholder="例如：参考成色、行情价、整备后定价"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="priceDialog.loading" @click="submitPrice">
                    确认{{ priceDialog.isAdjust ? '调价' : '定价' }}
                </el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="定价详情" size="680px">
            <el-descriptions v-if="detail.data.asset" :column="2" border>
                <el-descriptions-item label="资产编号">{{ detail.data.asset.asset_no }}</el-descriptions-item>
                <el-descriptions-item label="状态">{{ statusName(detail.data.asset.inventory_status) }}</el-descriptions-item>
                <el-descriptions-item label="设备">{{ detail.data.asset.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.data.asset.imei || '-' }}</el-descriptions-item>
                <el-descriptions-item label="当前成本">¥{{ money(detail.data.asset.current_cost) }}</el-descriptions-item>
                <el-descriptions-item label="销售价">¥{{ money(detail.data.asset.current_sale_price) }}</el-descriptions-item>
            </el-descriptions>
            <div class="mt-5 font-medium">定价历史</div>
            <el-table class="mt-3" :data="detail.data.price_logs || []" size="small" empty-text="暂无定价记录">
                <el-table-column label="动作" width="90">
                    <template #default="{ row }">{{ row.action === 'adjust' ? '调价' : '初始定价' }}</template>
                </el-table-column>
                <el-table-column label="价格变化" min-width="170">
                    <template #default="{ row }">¥{{ money(row.before_price) }} → ¥{{ money(row.after_price) }}</template>
                </el-table-column>
                <el-table-column label="毛利" width="110" align="right">
                    <template #default="{ row }">¥{{ money(row.gross_profit) }}</template>
                </el-table-column>
                <el-table-column prop="operator_name" label="操作人" width="110" />
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
                <el-table-column prop="remark" label="说明" min-width="160" show-overflow-tooltip />
            </el-table>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { useRoute } from 'vue-router'
import { getErpPricingInfo, getErpPricingList, saveErpAssetPrice } from '@/addon/hsx_erp/api/pricing'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const route = useRoute()
const search = reactive({ keyword: '', inventory_status: '' })
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const priceDialog = reactive<any>({
    visible: false,
    loading: false,
    isAdjust: false,
    asset: null,
    form: { sale_price: 0, min_profit: 0, remark: '' }
})
const detail = reactive<any>({ visible: false, data: {} })

const priceProfit = computed(() =>
    Number(priceDialog.form.sale_price || 0) - Number(priceDialog.asset?.current_cost || 0)
)
const priceMargin = computed(() => {
    const price = Number(priceDialog.form.sale_price || 0)
    return price > 0 ? ((priceProfit.value / price) * 100).toFixed(2) : '0.00'
})

const loadList = async () => {
    table.loading = true
    try {
        const res: any = await getErpPricingList({
            ...search,
            asset_id: Number(route.query.asset_id || 0),
            page: table.page,
            limit: table.limit
        })
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
const resetSearch = () => {
    search.keyword = ''
    search.inventory_status = ''
    handleSearch()
}

const openPrice = (row: any) => {
    priceDialog.asset = row
    priceDialog.isAdjust = row.inventory_status === 'available_for_sale'
    priceDialog.form = {
        sale_price: Number(row.current_sale_price || 0) > 0 ? Number(row.current_sale_price) : Number(row.current_cost || 0),
        min_profit: 0,
        remark: ''
    }
    priceDialog.visible = true
}

const submitPrice = async () => {
    if (!priceDialog.asset?.id) return
    if (Number(priceDialog.form.sale_price || 0) <= 0) return ElMessage.warning('请填写销售价')
    if (priceProfit.value < Number(priceDialog.form.min_profit || 0)) return ElMessage.warning('销售价低于最低利润要求')
    priceDialog.loading = true
    try {
        await saveErpAssetPrice(Number(priceDialog.asset.id), { ...priceDialog.form })
        ElMessage.success(priceDialog.isAdjust ? '调价已保存' : '销售定价已保存')
        priceDialog.visible = false
        await loadList()
    } finally {
        priceDialog.loading = false
    }
}

const openDetail = async (row: any) => {
    const res: any = await getErpPricingInfo(Number(row.id))
    detail.data = res.data || {}
    detail.visible = true
}

const money = (value: any) => Number(value || 0).toFixed(2)
const statusName = (status: string) => ({
    pending_pricing: '待销售定价',
    available_for_sale: '可售'
}[status] || status || '-')
const statusType = (status: string) => ({
    pending_pricing: 'warning',
    available_for_sale: 'success'
}[status] || 'info')
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('zh-CN')
}

onMounted(async () => {
    await loadList()
    const assetId = Number(route.query.asset_id || 0)
    if (assetId > 0) {
        const row = table.data.find((item: any) => Number(item.id) === assetId)
        if (row) openPrice(row)
    }
})
</script>
