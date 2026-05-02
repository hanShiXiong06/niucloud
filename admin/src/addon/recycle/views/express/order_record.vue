<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="page-head">
                <div>
                    <span class="text-page-title">快递运单记录</span>
                    <div class="page-desc">查看站点内通过回收插件创建的快递运单，按时间、状态、单号和联系人快速筛选。</div>
                </div>
                <div class="head-actions">
                    <el-button type="primary" @click="openCreateDialog">新建运单</el-button>
                    <el-button :loading="loading" @click="refreshPage">刷新</el-button>
                </div>
            </div>

            <div class="stat-grid mt-[18px]">
                <div class="stat-item">
                    <span>筛选订单</span>
                    <strong>{{ total }}</strong>
                </div>
                <div class="stat-item">
                    <span>统计订单</span>
                    <strong>{{ statistics.total_count || 0 }}</strong>
                </div>
                <div class="stat-item">
                    <span>预估费用</span>
                    <strong>¥{{ formatMoney(statistics.total_estimated_cost) }}</strong>
                </div>
                <div class="stat-item">
                    <span>实际费用</span>
                    <strong>¥{{ formatMoney(statistics.total_actual_cost) }}</strong>
                </div>
                <div class="stat-item">
                    <span>费用差异</span>
                    <strong :class="Number(statistics.total_cost_diff || 0) > 0 ? 'danger' : 'success'">¥{{ formatMoney(statistics.total_cost_diff) }}</strong>
                </div>
            </div>

            <div class="filter-panel mt-[18px]">
                <el-form :model="searchForm" label-width="84px">
                    <el-row :gutter="12">
                        <el-col :span="8">
                            <el-form-item label="关键词">
                                <el-input v-model.trim="searchForm.keyword" clearable placeholder="订单号、运单号、姓名、手机号" @keyup.enter="handleSearch" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item label="创建时间">
                                <el-date-picker
                                    v-model="searchForm.create_time"
                                    type="datetimerange"
                                    value-format="YYYY-MM-DD HH:mm:ss"
                                    start-placeholder="开始时间"
                                    end-placeholder="结束时间"
                                    class="!w-full"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :span="4">
                            <el-form-item label="状态">
                                <el-select v-model="searchForm.order_status" clearable placeholder="全部状态">
                                    <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :span="4">
                            <el-form-item label="产品代码">
                                <el-input v-model.trim="searchForm.product_code" clearable placeholder="如 59" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="6">
                            <el-form-item label="平台订单">
                                <el-input v-model.trim="searchForm.order_no" clearable placeholder="易速订单号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="运单号">
                                <el-input v-model.trim="searchForm.delivery_id" clearable placeholder="快递运单号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="寄件手机">
                                <el-input v-model.trim="searchForm.sender_mobile" clearable placeholder="寄件人手机号" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="6">
                            <el-form-item label="收件手机">
                                <el-input v-model.trim="searchForm.receiver_mobile" clearable placeholder="收件人手机号" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="6">
                            <el-form-item label="回收订单">
                                <el-input v-model.trim="searchForm.recycle_order_id" clearable placeholder="回收订单ID" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="18">
                            <el-form-item>
                                <el-button type="primary" :loading="loading" @click="handleSearch">查询</el-button>
                                <el-button @click="resetForm">重置</el-button>
                            </el-form-item>
                        </el-col>
                    </el-row>
                </el-form>
            </div>

            <el-table v-loading="loading" :data="orderList" class="mt-[16px]" stripe border>
                <el-table-column label="运单信息" min-width="230">
                    <template #default="{ row }">
                        <div class="primary-text">{{ row.delivery_id || '-' }}</div>
                        <div class="muted-text">平台订单：{{ row.order_no || '-' }}</div>
                        <div v-if="row.recycle_order_id" class="muted-text">回收订单：{{ row.recycle_order_id }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="产品" min-width="140">
                    <template #default="{ row }">
                        <div>{{ row.product_name || '-' }}</div>
                        <div class="muted-text">{{ row.product_code || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="寄件人" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.sender_name || '-' }} {{ row.sender_mobile || '' }}</div>
                        <div class="muted-text text-line">{{ joinAddress(row, 'sender') }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="收件人" min-width="190">
                    <template #default="{ row }">
                        <div>{{ row.receiver_name || '-' }} {{ row.receiver_mobile || '' }}</div>
                        <div class="muted-text text-line">{{ joinAddress(row, 'receiver') }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="重量/费用" width="150">
                    <template #default="{ row }">
                        <div>{{ row.estimated_weight || 0 }} kg</div>
                        <div>¥{{ formatMoney(row.estimated_cost) }}</div>
                        <div v-if="Number(row.actual_cost || 0) > 0" class="muted-text">实付 ¥{{ formatMoney(row.actual_cost) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="110">
                    <template #default="{ row }">
                        <el-tag :type="statusMeta(row.order_status).type">{{ row.status_text || statusMeta(row.order_status).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="210">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="handleViewDetail(row)">详情</el-button>
                        <el-button link type="warning" @click="handleUpdateActual(row)">更新费用</el-button>
                        <el-button v-if="row.order_no || row.delivery_id" link type="primary" :loading="operationLoading[row.id] === 'waybill'" @click="handleWaybillPdf(row)">面单</el-button>
                        <el-button v-if="canCancel(row)" link type="danger" :loading="operationLoading[row.id] === 'cancel'" @click="handleCloseOrder(row)">取消</el-button>
                        <el-button v-if="canIntercept(row)" link type="danger" :loading="operationLoading[row.id] === 'intercept'" @click="handleCloseOrder(row)">拦截</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="flex justify-end mt-[16px]">
                <el-pagination
                    v-model:current-page="searchForm.page"
                    v-model:page-size="searchForm.limit"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="handlePageChange"
                    @current-change="handlePageChange"
                />
            </div>
        </el-card>

        <el-dialog v-model="detailDialogVisible" title="运单详情" width="900px">
            <template v-if="currentOrder">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="平台订单">{{ currentOrder.order_no || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="运单号">{{ currentOrder.delivery_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="快递产品">{{ currentOrder.product_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusMeta(currentOrder.order_status).type">{{ currentOrder.status_text || statusMeta(currentOrder.order_status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="寄件人">{{ currentOrder.sender_name }} {{ currentOrder.sender_mobile }}</el-descriptions-item>
                    <el-descriptions-item label="收件人">{{ currentOrder.receiver_name }} {{ currentOrder.receiver_mobile }}</el-descriptions-item>
                    <el-descriptions-item label="寄件地址" :span="2">{{ joinAddress(currentOrder, 'sender') }}</el-descriptions-item>
                    <el-descriptions-item label="收件地址" :span="2">{{ joinAddress(currentOrder, 'receiver') }}</el-descriptions-item>
                    <el-descriptions-item label="物品">{{ currentOrder.goods_name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="包裹数">{{ currentOrder.package_count || 1 }}</el-descriptions-item>
                    <el-descriptions-item label="预估重量">{{ currentOrder.estimated_weight || 0 }} kg</el-descriptions-item>
                    <el-descriptions-item label="预估费用">¥{{ formatMoney(currentOrder.estimated_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="实际重量">{{ currentOrder.actual_weight || 0 }} kg</el-descriptions-item>
                    <el-descriptions-item label="实际费用">¥{{ formatMoney(currentOrder.actual_cost) }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatTime(currentOrder.create_at) }}</el-descriptions-item>
                    <el-descriptions-item label="更新时间">{{ formatTime(currentOrder.update_at) }}</el-descriptions-item>
                </el-descriptions>

                <div class="detail-section-title">系统流程</div>
                <el-timeline v-if="currentOrder.status_history?.length">
                    <el-timeline-item v-for="(item, index) in currentOrder.status_history" :key="index" :timestamp="formatTime(item.time)">
                        {{ statusMeta(item.status).label }}<span v-if="item.remark">：{{ item.remark }}</span>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无流程记录" :image-size="80" />
            </template>
        </el-dialog>

        <el-dialog v-model="updateDialogVisible" title="更新实际费用" width="500px">
            <el-form :model="updateForm" label-width="100px">
                <el-form-item label="实际重量">
                    <el-input-number v-model="updateForm.actual_weight" :min="0" :precision="2" :step="0.1" />
                    <span class="ml-2">kg</span>
                </el-form-item>
                <el-form-item label="实际费用">
                    <el-input-number v-model="updateForm.actual_cost" :min="0" :precision="2" :step="0.1" />
                    <span class="ml-2">元</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="updateDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleConfirmUpdate">确定</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="createDialogVisible" title="新建快递运单" width="1080px" destroy-on-close>
            <el-form :model="shipmentForm" label-width="92px" class="shipment-form">
                <el-row :gutter="14">
                    <el-col :span="12">
                        <div class="address-panel">
                            <div class="address-title">寄件人</div>
                            <el-form-item label="整段地址">
                                <el-input v-model="senderRawAddress" type="textarea" :rows="2" placeholder="姓名 手机号 省市区详细地址" />
                            </el-form-item>
                            <el-form-item>
                                <el-button :loading="addressParseLoading.sender" @click="parseRawAddress('sender')">解析寄件地址</el-button>
                            </el-form-item>
                            <el-row :gutter="10">
                                <el-col :span="12"><el-form-item label="姓名"><el-input v-model="shipmentForm.senderName" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="手机号"><el-input v-model="shipmentForm.senderMobile" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="10">
                                <el-col :span="8"><el-form-item label="省"><el-input v-model="shipmentForm.senderProvince" /></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="市"><el-input v-model="shipmentForm.senderCity" /></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="区县"><el-input v-model="shipmentForm.senderDistrict" /></el-form-item></el-col>
                            </el-row>
                            <el-form-item label="详细地址">
                                <el-input v-model="shipmentForm.senderAddress" />
                            </el-form-item>
                        </div>
                    </el-col>
                    <el-col :span="12">
                        <div class="address-panel">
                            <div class="address-title">
                                <span>收件人</span>
                                <el-select v-model="selectedShopAddressId" clearable placeholder="选择商家地址" class="address-select" @change="applyShopAddress">
                                    <el-option v-for="item in shopAddressList" :key="item.id" :label="`${item.contact_name} ${item.mobile} ${item.full_address || item.address || ''}`" :value="item.id" />
                                </el-select>
                            </div>
                            <el-form-item label="整段地址">
                                <el-input v-model="receiverRawAddress" type="textarea" :rows="2" placeholder="姓名 手机号 省市区详细地址" />
                            </el-form-item>
                            <el-form-item>
                                <el-button :loading="addressParseLoading.receiver" @click="parseRawAddress('receiver')">解析收件地址</el-button>
                            </el-form-item>
                            <el-row :gutter="10">
                                <el-col :span="12"><el-form-item label="姓名"><el-input v-model="shipmentForm.receiveName" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="手机号"><el-input v-model="shipmentForm.receiveMobile" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="10">
                                <el-col :span="8"><el-form-item label="省"><el-input v-model="shipmentForm.receiveProvince" /></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="市"><el-input v-model="shipmentForm.receiveCity" /></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="区县"><el-input v-model="shipmentForm.receiveDistrict" /></el-form-item></el-col>
                            </el-row>
                            <el-form-item label="详细地址">
                                <el-input v-model="shipmentForm.receiveAddress" />
                            </el-form-item>
                        </div>
                    </el-col>
                </el-row>

                <el-row :gutter="12" class="mt-[12px]">
                    <el-col :span="5">
                        <el-form-item label="快递产品">
                            <el-select v-model="shipmentForm.deliveryType" clearable placeholder="智能报价">
                                <el-option label="智能报价" value="" />
                                <el-option v-for="item in enabledYisuProducts" :key="item.product_code" :label="`${item.product_name}（${item.product_code}）`" :value="item.product_code" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="4"><el-form-item label="物品"><el-input v-model="shipmentForm.goods" /></el-form-item></el-col>
                    <el-col :span="4"><el-form-item label="重量kg"><el-input-number v-model="shipmentForm.weight" :min="0.1" :precision="2" :step="0.1" /></el-form-item></el-col>
                    <el-col :span="4"><el-form-item label="包裹数"><el-input-number v-model="shipmentForm.packageCount" :min="1" :max="99" /></el-form-item></el-col>
                    <el-col :span="4"><el-form-item label="保价"><el-input-number v-model="shipmentForm.guaranteeValueAmount" :min="0" :precision="2" /></el-form-item></el-col>
                    <el-col :span="3"><el-form-item label="预约"><el-date-picker v-model="shipmentForm.orderSendTime" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="可选" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="备注">
                    <el-input v-model="shipmentForm.remark" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :loading="shipmentLoading.quote" @click="runShipmentQuote">获取报价</el-button>
                    <el-button type="success" :loading="shipmentLoading.create" @click="runShipmentCreate">确认下单</el-button>
                </el-form-item>
            </el-form>

            <el-table v-if="quoteList.length" :data="quoteList" border size="small" class="mt-[12px]">
                <el-table-column prop="productName" label="产品" min-width="130" />
                <el-table-column label="预估费用" width="120">
                    <template #default="{ row }">
                        <span :class="{ danger: Number(getQuotePrice(row)) <= 0 }">¥{{ getQuotePrice(row) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="channelName" label="渠道" min-width="120" />
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="selectQuote(row)">选择</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <template #footer>
                <el-button @click="createDialogVisible = false">关闭</el-button>
                <el-button type="primary" :loading="shipmentLoading.create" @click="runShipmentCreate">确认下单</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    cancelOrInterceptExpressOrder,
    createExpressOrderDirect,
    getExpressQuote,
    getExpressOrderRecordList,
    getExpressOrderRecordInfo,
    getExpressWaybillPdf,
    updateExpressOrderActualInfo,
    getExpressOrderStatistics
} from '@/addon/recycle/api/express'
import { parseThirdPartyAddress } from '@/addon/recycle/api/third_party'
import { getShopAddressList } from '@/addon/recycle/api/shop_address'
import { getYisuProductList } from '@/addon/recycle/api/yisu'

const loading = ref(false)
const orderList = ref<any[]>([])
const total = ref(0)
const statistics = ref<any>({})
const detailDialogVisible = ref(false)
const updateDialogVisible = ref(false)
const createDialogVisible = ref(false)
const currentOrder = ref<any>(null)
const operationLoading = reactive<Record<number, '' | 'cancel' | 'intercept' | 'waybill'>>({})
const shopAddressList = ref<any[]>([])
const yisuProductList = ref<any[]>([])
const selectedShopAddressId = ref('')
const senderRawAddress = ref('')
const receiverRawAddress = ref('')
const quoteList = ref<any[]>([])
const addressParseLoading = reactive<Record<string, boolean>>({
    sender: false,
    receiver: false
})
const shipmentLoading = reactive({
    quote: false,
    create: false
})

const defaultSearchForm = () => ({
    keyword: '',
    order_no: '',
    delivery_id: '',
    order_status: '',
    product_code: '',
    sender_mobile: '',
    receiver_mobile: '',
    recycle_order_id: '',
    create_time: [] as string[],
    page: 1,
    limit: 20
})

const searchForm = reactive(defaultSearchForm())
const updateForm = reactive({
    id: 0,
    actual_weight: 0,
    actual_cost: 0
})
const defaultShipmentForm = () => ({
    deliveryType: '',
    senderName: '',
    senderMobile: '',
    senderProvince: '',
    senderCity: '',
    senderDistrict: '',
    senderAddress: '',
    receiveName: '',
    receiveMobile: '',
    receiveProvince: '',
    receiveCity: '',
    receiveDistrict: '',
    receiveAddress: '',
    goods: '回收设备',
    weight: 1,
    packageCount: 1,
    guaranteeValueAmount: 0,
    estimated_cost: 0,
    orderSendTime: '',
    remark: '',
    thirdOrderNo: ''
})
const shipmentForm = reactive<any>(defaultShipmentForm())

const statusOptions = [
    { label: '待揽收', value: 'pending', type: 'info' },
    { label: '已揽收', value: 'picked', type: 'warning' },
    { label: '运输中', value: 'in_transit', type: 'primary' },
    { label: '已签收', value: 'delivered', type: 'success' },
    { label: '已关闭', value: 'cancelled', type: 'danger' }
] as const

const statusMeta = (status: string): any => {
    return statusOptions.find(item => item.value === status) || { label: status || '未知', value: status || '', type: 'info' }
}

const enabledYisuProducts = computed(() => yisuProductList.value.filter(item => Number(item.status) === 1))

const buildParams = () => {
    const params: Record<string, any> = {
        page: searchForm.page,
        limit: searchForm.limit
    }
    ;['keyword', 'order_no', 'delivery_id', 'order_status', 'product_code', 'sender_mobile', 'receiver_mobile'].forEach((key) => {
        if ((searchForm as any)[key]) params[key] = (searchForm as any)[key]
    })
    if (searchForm.recycle_order_id) params.recycle_order_id = searchForm.recycle_order_id
    if (searchForm.create_time?.length === 2) params.create_time = searchForm.create_time
    return params
}

const loadOrderList = async () => {
    loading.value = true
    try {
        const res = await getExpressOrderRecordList(buildParams())
        orderList.value = res.data.data || res.data.list || []
        total.value = res.data.total || 0
    } catch (error) {
        ElMessage.error('加载运单记录失败')
    } finally {
        loading.value = false
    }
}

const loadStatistics = async () => {
    try {
        const params: Record<string, any> = {}
        if (searchForm.create_time?.length === 2) {
            params.start_time = Math.floor(new Date(searchForm.create_time[0]).getTime() / 1000)
            params.end_time = Math.floor(new Date(searchForm.create_time[1]).getTime() / 1000)
        }
        const res = await getExpressOrderStatistics(params)
        statistics.value = res.data || {}
    } catch (error) {
        statistics.value = {}
    }
}

const loadCreateOptions = async () => {
    const [addressRes, productRes] = await Promise.allSettled([
        getShopAddressList({ page: 1, limit: 100 }),
        getYisuProductList()
    ])
    if (addressRes.status === 'fulfilled') {
        shopAddressList.value = addressRes.value.data?.list || []
        const defaultAddress = shopAddressList.value.find(item => Number(item.is_default_refund) === 1) || shopAddressList.value[0]
        if (defaultAddress && !selectedShopAddressId.value) {
            selectedShopAddressId.value = defaultAddress.id
            applyShopAddress(defaultAddress.id)
        }
    }
    if (productRes.status === 'fulfilled') {
        yisuProductList.value = productRes.value.data || []
    }
}

const handleSearch = async () => {
    searchForm.page = 1
    await refreshPage()
}

const handlePageChange = async () => {
    await loadOrderList()
}

const refreshPage = async () => {
    await Promise.all([loadOrderList(), loadStatistics()])
}

const resetForm = async () => {
    Object.assign(searchForm, defaultSearchForm())
    await refreshPage()
}

const handleViewDetail = async (row: any) => {
    try {
        const res = await getExpressOrderRecordInfo(row.id)
        currentOrder.value = res.data
        detailDialogVisible.value = true
    } catch (error) {
        ElMessage.error('获取运单详情失败')
    }
}

const handleUpdateActual = (row: any) => {
    updateForm.id = row.id
    updateForm.actual_weight = Number(row.actual_weight || row.estimated_weight || 0)
    updateForm.actual_cost = Number(row.actual_cost || row.estimated_cost || 0)
    updateDialogVisible.value = true
}

const handleConfirmUpdate = async () => {
    try {
        await updateExpressOrderActualInfo(updateForm)
        ElMessage.success('更新成功')
        updateDialogVisible.value = false
        await refreshPage()
    } catch (error: any) {
        ElMessage.error(error.message || '更新失败')
    }
}

const resetShipmentForm = () => {
    Object.assign(shipmentForm, defaultShipmentForm())
    senderRawAddress.value = ''
    receiverRawAddress.value = ''
    quoteList.value = []
    selectedShopAddressId.value = ''
}

const openCreateDialog = async () => {
    resetShipmentForm()
    createDialogVisible.value = true
    await loadCreateOptions()
}

const parseAddressText = (raw: string) => {
    let text = raw.trim().replace(/\s+/g, ' ')
    const mobileMatch = text.match(/1[3-9]\d{9}/)
    const mobile = mobileMatch ? mobileMatch[0] : ''
    if (mobile) text = text.replace(mobile, ' ').replace(/\s+/g, ' ').trim()

    const parts = text.split(' ').filter(Boolean)
    let name = ''
    if (parts.length > 1 && !/(省|市|区|县|州|盟|旗|镇|路|街|号|室)/.test(parts[0])) {
        name = parts.shift() || ''
        text = parts.join('')
    } else {
        text = text.replace(/\s/g, '')
    }

    const areaMatch = text.match(/^(.+?(?:省|自治区|市))(.+?(?:市|自治州|地区|盟))?(.+?(?:区|县|市|旗))?(.+)$/)
    return {
        name,
        mobile,
        province: areaMatch?.[1] || '',
        city: areaMatch?.[2] || '',
        district: areaMatch?.[3] || '',
        address: areaMatch?.[4] || text
    }
}

const fillParsedAddress = (type: 'sender' | 'receiver', parsed: Record<string, any>) => {
    if (type === 'sender') {
        shipmentForm.senderName = parsed.name || shipmentForm.senderName
        shipmentForm.senderMobile = parsed.mobile || shipmentForm.senderMobile
        shipmentForm.senderProvince = parsed.province || shipmentForm.senderProvince
        shipmentForm.senderCity = parsed.city || shipmentForm.senderCity
        shipmentForm.senderDistrict = parsed.district || shipmentForm.senderDistrict
        shipmentForm.senderAddress = parsed.address || parsed.info || shipmentForm.senderAddress
    } else {
        shipmentForm.receiveName = parsed.name || shipmentForm.receiveName
        shipmentForm.receiveMobile = parsed.mobile || shipmentForm.receiveMobile
        shipmentForm.receiveProvince = parsed.province || shipmentForm.receiveProvince
        shipmentForm.receiveCity = parsed.city || shipmentForm.receiveCity
        shipmentForm.receiveDistrict = parsed.district || shipmentForm.receiveDistrict
        shipmentForm.receiveAddress = parsed.address || parsed.info || shipmentForm.receiveAddress
    }
}

const parseRawAddress = async (type: 'sender' | 'receiver') => {
    const raw = type === 'sender' ? senderRawAddress.value : receiverRawAddress.value
    if (!raw.trim()) {
        ElMessage.warning('请先粘贴需要解析的地址')
        return
    }

    addressParseLoading[type] = true
    try {
        const res = await parseThirdPartyAddress({ address: raw })
        fillParsedAddress(type, res.data || {})
        ElMessage.success('地址解析成功')
    } catch (error) {
        fillParsedAddress(type, parseAddressText(raw))
        ElMessage.warning('地址解析接口不可用，已使用本地基础解析')
    } finally {
        addressParseLoading[type] = false
    }
}

const applyShopAddress = (id: string | number) => {
    const item = shopAddressList.value.find(address => String(address.id) === String(id))
    if (!item) return
    receiverRawAddress.value = `${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`
    fillParsedAddress('receiver', parseAddressText(receiverRawAddress.value))
}

const getQuotePrice = (row: any) => {
    const fields = [
        'estimatedCost',
        'totalPrice',
        'totalFee',
        'totalAmount',
        'price',
        'fee',
        'amount',
        'prePrice',
        'predictPrice',
        'estimatedPrice',
        'freight',
        'freightFee',
        'transportFee',
        'channelFee'
    ]
    for (const field of fields) {
        const value = Number(row[field])
        if (value > 0) return value.toFixed(2)
    }

    const detailFee = ['channelFee', 'serviceCharge', 'serviceFee', 'guarantFee', 'guaranteeFee', 'incrementFee', 'otherFee']
        .reduce((total, field) => total + Number(row[field] || 0), 0)
    return detailFee.toFixed(2)
}

const selectQuote = (row: any) => {
    shipmentForm.deliveryType = String(row.productCode || row.product_code || shipmentForm.deliveryType)
    shipmentForm.estimated_cost = Number(getQuotePrice(row))
}

const validateShipment = (requireProduct = true) => {
    const required = [
        ['senderName', '请填写寄件人姓名'],
        ['senderMobile', '请填写寄件人手机号'],
        ['senderProvince', '请填写寄件省份'],
        ['senderCity', '请填写寄件城市'],
        ['senderDistrict', '请填写寄件区县'],
        ['senderAddress', '请填写寄件详细地址'],
        ['receiveName', '请填写收件人姓名'],
        ['receiveMobile', '请填写收件人手机号'],
        ['receiveProvince', '请填写收件省份'],
        ['receiveCity', '请填写收件城市'],
        ['receiveDistrict', '请填写收件区县'],
        ['receiveAddress', '请填写收件详细地址']
    ]
    if (requireProduct) required.unshift(['deliveryType', '请选择快递产品'])
    for (const [field, message] of required) {
        if (!shipmentForm[field]) {
            ElMessage.warning(message)
            return false
        }
    }
    return true
}

const runShipmentQuote = async () => {
    if (!validateShipment(false)) return
    shipmentLoading.quote = true
    try {
        const res = await getExpressQuote(shipmentForm)
        quoteList.value = res.data || []
        if (quoteList.value[0]) selectQuote(quoteList.value[0])
        ElMessage.success('报价获取成功')
    } finally {
        shipmentLoading.quote = false
    }
}

const runShipmentCreate = async () => {
    if (!validateShipment()) return
    if (!shipmentForm.thirdOrderNo) {
        shipmentForm.thirdOrderNo = `manual_${Date.now()}`
    }
    shipmentLoading.create = true
    try {
        await createExpressOrderDirect(shipmentForm)
        ElMessage.success('快递下单成功')
        createDialogVisible.value = false
        await refreshPage()
    } finally {
        shipmentLoading.create = false
    }
}

const canOperateClose = (row: any) => !['cancelled', 'delivered'].includes(row.order_status) && (row.order_no || row.delivery_id || row.recycle_order_id)

const canCancel = (row: any) => canOperateClose(row) && ['pending', ''].includes(row.order_status || '')

const canIntercept = (row: any) => canOperateClose(row) && !canCancel(row)

const buildCancelParams = (row: any, genre: 1 | 3) => {
    const params: Record<string, any> = { genre }
    if (row.order_no) params.order_no = row.order_no
    if (row.delivery_id) params.waybill_no = row.delivery_id
    if (row.site_id && row.recycle_order_id) params.third_order_no = `recycle_${row.site_id}_${row.recycle_order_id}`
    return params
}

const handleCloseOrder = async (row: any) => {
    const genre: 1 | 3 = canCancel(row) ? 1 : 3
    const actionName = genre === 3 ? '拦截' : '取消'
    try {
        await ElMessageBox.confirm(`确认${actionName}这个运单吗？操作成功后列表状态会变为已关闭。`, `${actionName}运单`, {
            type: 'warning',
            confirmButtonText: actionName,
            cancelButtonText: '返回'
        })
    } catch (error) {
        return
    }

    operationLoading[row.id] = genre === 3 ? 'intercept' : 'cancel'
    try {
        await cancelOrInterceptExpressOrder(buildCancelParams(row, genre))
        ElMessage.success(`${actionName}请求已提交`)
        await refreshPage()
    } catch (error: any) {
        ElMessage.error(error.message || `${actionName}失败`)
    } finally {
        operationLoading[row.id] = ''
    }
}

const buildWaybillParams = (row: any) => {
    const params: Record<string, any> = {}
    if (row.order_no) params.order_no = row.order_no
    if (row.delivery_id) params.waybill_no = row.delivery_id
    if (row.site_id && row.recycle_order_id) params.third_order_no = `recycle_${row.site_id}_${row.recycle_order_id}`
    params.template_code = String(row.product_code) === '59' ? '113' : ''
    return params
}

const openWaybillData = (data: any) => {
    const pdfData = data?.pdfData || data?.url || data?.pdf_url || ''
    if (!pdfData) return false
    if (Number(data?.dataFormat) === 1 || /^https?:\/\//.test(pdfData)) {
        window.open(pdfData, '_blank')
        return true
    }
    const base64 = pdfData.startsWith('data:') ? pdfData : `data:application/pdf;base64,${pdfData}`
    const win = window.open('', '_blank')
    if (win) {
        win.document.write(`<iframe src="${base64}" style="border:0;width:100%;height:100vh;"></iframe>`)
        return true
    }
    return false
}

const handleWaybillPdf = async (row: any) => {
    operationLoading[row.id] = 'waybill'
    try {
        const res = await getExpressWaybillPdf(buildWaybillParams(row))
        const opened = openWaybillData(res.data || {})
        ElMessage.success(opened ? '面单已打开' : '面单获取成功')
    } catch (error: any) {
        ElMessage.error(error.message || '获取面单失败')
    } finally {
        operationLoading[row.id] = ''
    }
}

const joinAddress = (row: any, type: 'sender' | 'receiver') => {
    const prefix = type === 'sender' ? 'sender' : 'receiver'
    return [row[`${prefix}_province`], row[`${prefix}_city`], row[`${prefix}_district`], row[`${prefix}_address`]].filter(Boolean).join('') || '-'
}

const formatMoney = (value: any) => Number(value || 0).toFixed(2)

const formatTime = (value: any) => {
    if (!value) return '-'
    let time = value
    if (typeof time === 'string' && /^\d+$/.test(time)) time = Number(time)
    const date = typeof time === 'number' ? new Date(time < 1000000000000 ? time * 1000 : time) : new Date(time)
    if (Number.isNaN(date.getTime())) return '-'
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

onMounted(() => {
    refreshPage()
})
</script>

<style scoped lang="scss">
.main-container {
    padding: 20px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.page-desc {
    margin-top: 6px;
    color: #6b7280;
    font-size: 13px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
}

.stat-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    background: #fff;

    span {
        display: block;
        color: #6b7280;
        font-size: 13px;
    }

    strong {
        display: block;
        margin-top: 8px;
        color: #111827;
        font-size: 22px;
        line-height: 1;
    }
}

.filter-panel {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 16px 0;
    background: #fbfdff;
}

.primary-text {
    color: #111827;
    font-weight: 600;
}

.muted-text {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.text-line {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-section-title {
    margin: 18px 0 10px;
    color: #111827;
    font-weight: 600;
}

.success {
    color: #16a34a !important;
}

.danger {
    color: #dc2626 !important;
}

.head-actions {
    display: flex;
    gap: 8px;
}

.shipment-form {
    :deep(.el-input-number) {
        width: 100%;
    }

    :deep(.el-date-editor) {
        width: 100%;
    }
}

.address-panel {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 14px 0;
    background: #fbfdff;
}

.address-title {
    display: flex;
    min-height: 32px;
    margin-bottom: 10px;
    align-items: center;
    justify-content: space-between;
    color: #111827;
    font-weight: 600;
}

.address-select {
    width: 260px;
}
</style>
