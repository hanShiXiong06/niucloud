<template>
    <div class="return-order-list">
        <el-card class="box-card" shadow="never">
            <template #header>
                <div class="card-header">
                    <span>退回订单列表</span>
                    <div class="header-actions">
                        <!-- <el-button type="primary" size="small" @click="exportReturnOrders" :loading="exportLoading">
                            <el-icon>
                                <Download />
                            </el-icon> 导出数据
                        </el-button> -->
                    </div>
                </div>
            </template>

            <!-- 搜索区域 -->
            <el-form :model="searchParams" ref="searchForm" label-width="100px" inline>
                <el-form-item label="订单号" prop="order_id">
                    <el-input v-model="searchParams.order_no" placeholder="请输入订单号" clearable />
                </el-form-item>
                <el-form-item label="快递单号" prop="express_no">
                    <el-input v-model="searchParams.express_no" placeholder="请输入快递单号" clearable />
                </el-form-item>
                

                <el-form-item label="创建时间" prop="create_at">
                    <el-date-picker v-model="searchParams.create_at" type="daterange" range-separator="至"
                        start-placeholder="开始日期" end-placeholder="结束日期" value-format="yyyy-MM-dd" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon>
                            <Search />
                        </el-icon> 搜索
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon>
                            <Refresh />
                        </el-icon> 重置
                    </el-button>
                </el-form-item>
            </el-form>

            <!-- 状态统计卡片 -->
            <div class="status-cards">
              

                <el-tabs v-model="activeOrderStatus" @tab-click="filterByStatus">

                    <el-tab-pane v-for='(item, key) in statusCards' :label="item.label"
                        :name="item.status"></el-tab-pane>
                </el-tabs>
            </div>


            <!-- 表格区域 -->
            <el-table v-loading="tableLoading" :data="formattedTableData" style="width: 100%; margin-top: 20px" border
                @selection-change="handleSelectionChange">
                <el-table-column type="selection" width="55" />
                <el-table-column prop="id" label="ID" width="80" sortable />
                <el-table-column prop="order_id" label="退回订单编号" min-width="150" sortable show-overflow-tooltip />
                <el-table-column prop="express_no" label="快递单号" min-width="150" show-overflow-tooltip />

                <el-table-column label="会员信息" min-width="150">
                    <template #default="scope">
                        <div v-if="scope.row.memberInfo">
                            <el-tooltip :content="scope.row.memberInfo.name">
                                <span>{{ scope.row.memberInfo.name }}</span>
                            </el-tooltip>
                        </div>
                        <div v-if="scope.row.memberInfo && scope.row.memberInfo.mobile">
                            <el-tooltip content="点击复制手机号">
                                <span @click="copyToClipboard(scope.row.memberInfo.mobile)" class="clickable-text">
                                    {{ scope.row.memberInfo.mobile }}
                                </span>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="退回设备" min-width="140">
                    <template #default="scope">
                        <el-popover placement="top" width="300" trigger="hover" :content="getDeviceList(scope.row)">
                            <template #reference>
                                <el-tag type="info" effect="plain">{{ scope.row.deviceCount }} 台设备</el-tag>
                            </template>
                        </el-popover>
                    </template>
                </el-table-column>

                <el-table-column label="状态" width="100">
                    <template #default="scope">
                        <el-tag :type="scope.row.statusType">
                            {{ scope.row.status_name }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_at" label="创建时间" min-width="180" sortable />
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="scope">
                        <el-button type="primary" size="small" @click="handleDetail(scope.row)">
                            <el-icon>
                                <View />
                            </el-icon> 详情
                        </el-button>
                        <el-button v-if="canPerformAction('CONFIRM', scope.row.status)" type="success" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleConfirm(scope.row.id)">确认退货</el-button>
                        <el-button v-if="canPerformAction('COMPLETE', scope.row.status)" type="success" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleComplete(scope.row.id)">完成退货</el-button>
                        <!-- <el-button v-if="canPerformAction('CANCEL', scope.row.status)" type="danger" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleCancel(scope.row.id)">取消</el-button> -->
                        <el-button v-if="canPerformAction('DELETE', scope.row.status)" type="danger" size="small"
                            :loading="operationLoading && activeOperationId === scope.row.id"
                            @click="handleDelete(scope.row.id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量操作 -->
            <div class="batch-actions" v-if="selectedRows.length > 0">
                <el-button type="primary" size="small" @click="batchExport">批量导出</el-button>
                <el-button v-if="canBatchDelete" type="danger" size="small" @click="batchDelete">批量删除</el-button>
                <span class="selected-info">已选择 {{ selectedRows.length }} 项</span>
            </div>

            <!-- 分页 -->
            <div class="pagination-container">
                <el-pagination v-model:current-page="pagination.page" v-model:page-size="pagination.limit"
                    :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper"
                    :total="pagination.total" @size-change="handleSizeChange" @current-change="handleCurrentChange" />
            </div>
        </el-card>

        <!-- 取消订单对话框 -->
        <el-dialog v-model="cancelDialogVisible" title="取消退回订单" width="500px" :close-on-click-modal="false">
            <el-form :model="cancelForm" label-width="100px" ref="cancelFormRef">
                <el-form-item label="取消原因" prop="comment"
                    :rules="[{ required: true, message: '请输入取消原因', trigger: 'blur' }]">
                    <el-input v-model="cancelForm.comment" type="textarea" :rows="3" placeholder="请输入取消原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="cancelDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="confirmCancel">确认</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 完成订单对话框 -->
        <el-dialog v-model="completeDialogVisible" title="完成退回订单" width="500px" :close-on-click-modal="false">
            <el-form :model="completeForm" label-width="100px" ref="completeFormRef">
                <el-form-item label="备注">
                    <el-input v-model="completeForm.comment" type="textarea" :rows="3" placeholder="请输入备注信息" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="completeDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="confirmComplete">确认</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 确认退货对话框 -->
        <el-dialog v-model="confirmDialogVisible" title="确认退货" width="500px" :close-on-click-modal="false">
            <el-form :model="confirmForm" label-width="120px" ref="confirmFormRef">
                 <el-form-item label="快递公司" prop="express_company">
                    <el-select v-model="confirmForm.express_company" placeholder="请选择快递公司" clearable>
                        <el-option v-for="item in expressCompanyOptions" :key="item.value" :label="item.label"
                            :value="item.value" />
                    </el-select>
                </el-form-item> 
                
                
                <el-form-item v-if="confirmForm.express_company !=='物流车/自取'" label="快递单号" prop="express_no"
                    :rules="[{ required:confirmForm.express_company !=='物流车/自取' , message: '请输入快递单号', trigger: 'blur' }]">
                    <el-input v-model="confirmForm.express_no" placeholder="请输入或扫描快递单号" clearable ref="expressNoInput"
                        @focus="focusInput" />
                </el-form-item>
              

                <el-form-item label="备注" prop="remark">
                    <el-input v-model="confirmForm.remark" type="textarea" :rows="2" placeholder="请输入备注信息(选填)" />
                </el-form-item>
            </el-form>
            <div class="scan-tip">
                <el-alert title="支持扫码枪录入快递单号" type="info" :closable="false" show-icon>
                    <div class="scan-instruction">点击输入框后，可直接使用扫码枪扫描快递单号</div>
                </el-alert>
            </div>
            <div>
                <!-- 退货地址 -->
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="退货地址">
                        <!-- 插槽 -->
                        <template #default>
                            <div>
                                <p>联系人:{{ return_user_address.name }}</p>
                                <p>手机号:{{ return_user_address.mobile }}</p>
                                <p>地址:
                                    {{ return_user_address.address }}

                                </p>
                            </div>
                        </template>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="edit_return_user_address">修改本次退货地址</el-button>
                    <el-button @click="confirmDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="operationLoading" @click="submitConfirm">确认退货</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 修改退货地址对话框 -->
        <el-dialog v-model="editAddressDialogVisible" title="修改本次退货地址" width="500px" :close-on-click-modal="false">
            <el-form ref="editAddressFormRef" :model="editAddressForm" :rules="editAddressRules" label-width="80px">
                <el-form-item label="联系人" prop="name">
                    <el-input v-model="editAddressForm.name" placeholder="请输入联系人姓名" />
                </el-form-item>
                <el-form-item label="手机号" prop="mobile">
                    <el-input v-model="editAddressForm.mobile" placeholder="请输入手机号" />
                </el-form-item>
                <el-form-item label="详细地址" prop="address">
                    <el-input v-model="editAddressForm.address" type="textarea" :rows="3" placeholder="请输入详细地址" />
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="editAddressDialogVisible = false">取消</el-button>
                    <el-button type="primary" @click="confirmEditAddress">确认修改</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- 订单详情对话框 -->
        <el-dialog v-model="detailDialogVisible" title="退回订单详情" width="800px" :close-on-click-modal="false"
            destroy-on-close>
            <el-descriptions :column="2" border>
                <el-descriptions-item label="订单编号" >{{ currentDetail.order_id }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ currentDetail.create_at }}</el-descriptions-item>
               
                <el-descriptions-item label="订单状态">
                    <el-tag :type="getStatusType(currentDetail.status)">{{ currentDetail.status_name }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="操作人" >{{ currentDetail.operator_name }}</el-descriptions-item>
                <el-descriptions-item label="快递公司">{{ currentDetail.express_company }}</el-descriptions-item>
                <el-descriptions-item label="快递单号">{{ currentDetail.express_no || '无' }}</el-descriptions-item>
                <el-descriptions-item label="备注">{{ currentDetail.comment}}</el-descriptions-item>
                <el-descriptions-item label="会员信息" :span="2">
                    <div v-if="currentDetail.member">
                        <p>姓名：{{ currentDetail.member.nickname || currentDetail.member.username }}</p>
                        <p>手机：{{ currentDetail.member.mobile }}</p>
                    </div>
                </el-descriptions-item>
                <el-descriptions-item v-if="currentDetail.return_address" label="退货地址" :span="2">
                    <div>
                        <p>联系人:{{ currentDetail.member_name }}</p>
                        <p>手机号:{{ currentDetail.member_mobile }}</p>
                        <p>地址:{{ currentDetail.return_address }}</p>
                    </div>
                </el-descriptions-item>
               
                <el-descriptions-item v-if="currentDetail.remark" label="备注">{{ currentDetail.remark || '无' }}</el-descriptions-item>
            </el-descriptions>

            <div class="device-list-section">
                <h3>退回设备列表</h3>
                <el-table :data="currentDetail.returnDevices || []" stripe border style="width: 100%; margin-top: 10px">
                    <el-table-column prop="device.model" label="设备名称" min-width="150" />
                    <el-table-column prop="device.imei" label="IMEI" min-width="150" />
                    <el-table-column prop="remark" label="退回原因" min-width="150" />
                    <el-table-column prop="price" label="设备最终定价" width="200">
                        <template #default="scope">
                            <span class="amount">{{ formatPrice(scope.row.final_price) }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <div class="timeline-section" v-if="currentDetail.logs && currentDetail.logs.length">
                <h3>订单状态记录</h3>
                <el-timeline>
                    <el-timeline-item v-for="(log, index) in currentDetail.logs" :key="index" :timestamp="log.create_at"
                        :type="getTimelineItemType(log.status)">
                        <div>{{ log.comment || getStatusTextByValue(log.status) }}</div>
                        <div class="timeline-operator" v-if="log.operator">操作人：{{ log.operator }}</div>
                    </el-timeline-item>
                </el-timeline>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="detailDialogVisible = false">关闭</el-button>
                    <el-button type="primary" @click="printOrderDetail">打印订单</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { useRouter } from 'vue-router'
import { Download, Search, Refresh, View } from '@element-plus/icons-vue'

import {
    getReturnOrderList,
    getReturnOrderStatusCount,
    confirmReturnOrder,
    updateReturnOrderStatus,
    deleteReturnOrder,
    cancelReturnOrder,
    getReturnOrderDetail,
    getDeviceInfo,
    checkExistingReturnOrder,
    appendToReturnOrder
} from '../../api/recycle_return_order'
import { IReturnOrderListParams, IReturnOrder, IStatusCount } from '../../interface/recycle_return_order'
import {
    RETURN_ORDER_STATUS,
    RETURN_ORDER_STATUS_TEXT,
    RETURN_ORDER_STATUS_TYPE,
    STATUS_ACTION_PERMISSIONS
} from '../../constants/recycle_return_order'


const router = useRouter()

// 搜索表单数据
const searchParams = reactive<IReturnOrderListParams>({
    order_id: '',
    express_no: '',
    status: undefined,
    create_at: []
})

// 状态选项
const statusOptions = ref([
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.PENDING], value: RETURN_ORDER_STATUS.PENDING },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.RETURNING], value: RETURN_ORDER_STATUS.RETURNING },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.COMPLETED], value: RETURN_ORDER_STATUS.COMPLETED },
    { label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.CANCELLED], value: RETURN_ORDER_STATUS.CANCELLED }
])

// 表格数据
const tableData = ref<IReturnOrder[]>([])
const tableLoading = ref(false)
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

// 导出相关
const exportLoading = ref(false)

// 批量操作相关
const selectedRows = ref<IReturnOrder[]>([])
const canBatchDelete = computed(() => {
    return selectedRows.value.every(row =>
        STATUS_ACTION_PERMISSIONS['DELETE'].includes(row.status))
})

// 操作加载状态
const operationLoading = ref(false)
const activeOperationId = ref<number | null>(null)

// 状态统计数据
const statusCounts = ref<{
    all: number;
    [key: string]: number;
}>({
    all: 0,
    0: 0,
    1: 0,
    2: 0,
    3: 0
})

// 计算状态卡片数据
const statusCards = computed(() => [
    { status: 'all', label: '全部', count: statusCounts.value.all },
    { status: String(RETURN_ORDER_STATUS.PENDING), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.PENDING], count: statusCounts.value[RETURN_ORDER_STATUS.PENDING] },
    { status: String(RETURN_ORDER_STATUS.RETURNING), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.RETURNING], count: statusCounts.value[RETURN_ORDER_STATUS.RETURNING] },
    { status: String(RETURN_ORDER_STATUS.COMPLETED), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.COMPLETED], count: statusCounts.value[RETURN_ORDER_STATUS.COMPLETED] },
    { status: String(RETURN_ORDER_STATUS.CANCELLED), label: RETURN_ORDER_STATUS_TEXT[RETURN_ORDER_STATUS.CANCELLED], count: statusCounts.value[RETURN_ORDER_STATUS.CANCELLED] }
])



// 优化表格数据格式
const formattedTableData = computed(() => {
    return tableData.value.map(item => ({
        ...item,
        deviceCount: item.returnDevices?.length || 0,
        memberInfo: item.member
            ? {
                name: item.member.nickname || item.member.username,
                mobile: item.member.mobile || ''
            }
            : null,
        statusType: getStatusType(item.status)
    }))
})

// 判断状态卡片是否激活
const getActiveStatus = (status: string) => {
    if (status === 'all') {
        return searchParams.status === undefined
    }

    return searchParams.status === Number(status)
}

// 详情对话框相关
const detailDialogVisible = ref(false)
const currentDetail = ref<any>({})

// 取消订单相关
const cancelDialogVisible = ref(false)
const cancelForm = reactive({
    id: 0,
    comment: ''
})
const cancelFormRef = ref<FormInstance>()

// 完成订单相关
const completeDialogVisible = ref(false)
const completeForm = reactive({
    id: 0,
    comment: ''
})
const completeFormRef = ref<FormInstance>()

// 确认退货对话框相关
const confirmDialogVisible = ref(false)
const confirmForm = reactive({
    id: 0,
    order_id: 0,
    express_no: '',
    express_company: '',
    remark: '',
    is_append: false,
    return_order_id: 0
})
const confirmFormRef = ref<FormInstance>()
const expressNoInput = ref<HTMLInputElement>()

// 快递公司选项
const expressCompanyOptions = ref([
    { label: '顺丰速运', value: 'sf' },
    { label: '京东速递', value: 'jd' },
    { label: '物流车/自取', value: '物流车/自取' },
   
])

// 聚焦输入框，便于扫码枪使用
const focusInput = () => {
    // 使用setTimeout确保DOM已更新
    setTimeout(() => {
        expressNoInput.value?.focus()
    }, 100)
}

// 统一API响应处理
const handleApiResponse = (res: any, successMsg?: string, errorMsg = '操作失败') => {
    // 统一检查响应结构
    if (res && ((res.code === 1) || (res.data && res.data.code === 1))) {
        successMsg && ElMessage.success(successMsg)
        return true
    } else {
        const msg = res?.data?.msg || res?.msg || errorMsg
        ElMessage.error(msg)
        return false
    }
}

// 通用业务操作处理函数
const performOperation = async (
    id: number,
    operation: () => Promise<any>,
    successMsg: string,
    errorMsg: string
) => {
    activeOperationId.value = id
    operationLoading.value = true

    try {
        const res = await operation()
        if (handleApiResponse(res, successMsg, errorMsg)) {
            getList()
            getStatusCount()
            return true
        }
    } catch (error) {
        console.error(`${errorMsg}:`, error)
        ElMessage.error(errorMsg)
    } finally {
        operationLoading.value = false
        activeOperationId.value = null
    }

    return false
}

// 格式化价格
const formatPrice = (price: number | string | undefined) => {
    if (price === undefined || price === null) return '¥0.00'

    const priceNum = typeof price === 'string' ? parseFloat(price) : price
    return `¥${priceNum.toFixed(2)}`
}

// 获取设备列表信息
const getDeviceList = (row: any) => {
    if (!row.returnDevices || row.returnDevices.length === 0) {
        return '暂无设备信息'
    }

console.log(row);


    return row.returnDevices.map((device: any, index: number) =>
        `${index + 1}. ${device.device?.model || '未知设备'} (${device.device?.imei || 'IMEI未知'})`
    ).join('\n')
}

// 获取设备检测状态类型
const getDeviceCheckStatusType = (status: number | string) => {
    const statusMap: Record<string, string> = {
        '0': 'info',
        '1': 'success',
        '2': 'danger'
    }
    return statusMap[String(status)] || 'info'
}

// 获取设备检测状态文本
const getDeviceCheckStatusText = (status: number | string) => {
    const statusMap: Record<string, string> = {
        '0': '待检测',
        '1': '通过',
        '2': '不通过'
    }
    return statusMap[String(status)] || '未知'
}

// 获取时间线项的类型
const getTimelineItemType = (status: number) => {
    const typeMap: Record<number, string> = {
        [RETURN_ORDER_STATUS.PENDING]: 'primary',
        [RETURN_ORDER_STATUS.RETURNING]: 'warning',
        [RETURN_ORDER_STATUS.COMPLETED]: 'success',
        [RETURN_ORDER_STATUS.CANCELLED]: 'danger'
    }
    return typeMap[status] || 'info'
}

// 根据状态值获取状态文本
const getStatusTextByValue = (status: number) => {
    return RETURN_ORDER_STATUS_TEXT[status as RETURN_ORDER_STATUS] || '未知状态'
}

// 复制到剪贴板
const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板')
    }).catch(() => {
        ElMessage.error('复制失败')
    })
}

// 获取订单列表
const getList = async () => {
    tableLoading.value = true
    try {
        const params: IReturnOrderListParams = {
            ...searchParams,
            page: pagination.page,
            limit: pagination.limit
        }

        // 处理日期范围
        if (params.create_at && params.create_at.length === 2) {
            const [start, end] = params.create_at
            params.create_at = [`${start} 00:00:00`, `${end} 23:59:59`]
        }
      

        const res = await getReturnOrderList(params)

        
        

        if (handleApiResponse(res, '', '获取列表失败')) {
            // 确保数据结构一致性
            const responseData = res.data

            // 检查不同的响应数据结构
            if (responseData.list) {
                // 如果是 {list: [...], count: number} 结构
                tableData.value = Array.isArray(responseData.list) ? responseData.list : []
                pagination.total = responseData.count || 0
            } else if (responseData.data) {
                // 如果是 {data: [...], total: number, current_page: number, per_page: number} 结构
                tableData.value = Array.isArray(responseData.data) ? responseData.data : []
                pagination.total = responseData.total || 0
                pagination.page = Number(responseData.current_page || 1)
                pagination.limit = Number(responseData.per_page || 10)
            } else {
                // 如果直接是数组
                tableData.value = Array.isArray(responseData) ? responseData : []
            }
        }
    } catch (error) {
        console.error('获取列表失败:', error)
        ElMessage.error('获取列表失败')
    } finally {
        tableLoading.value = false
    }
}

// 获取状态统计
const getStatusCount = async () => {
    try {
        const res = await getReturnOrderStatusCount()
        if (handleApiResponse(res, '', '获取状态统计失败')) {
            // 初始化计数
            statusCounts.value = { all: 0, 0: 0, 1: 0, 2: 0, 3: 0 }

            // 正确处理返回的数据
            const countData = res.data?.data || []
            countData.forEach((item: IStatusCount) => {
                if (item.status === 'all') {
                    statusCounts.value.all = item.count
                } else if (typeof item.status === 'string' && item.status in statusCounts.value) {
                    statusCounts.value[item.status] = item.count
                }
            })
        }
    } catch (error) {
        console.error('获取状态统计失败:', error)
        ElMessage.error('获取状态统计失败')
    }
}

// 搜索
const handleSearch = () => {
    pagination.page = 1
    getList()
}

// 重置搜索
const resetSearch = () => {
    searchParams.order_id = ''
    searchParams.express_no = ''
    searchParams.status = undefined
    searchParams.create_at = []
    pagination.page = 1
    getList()
}

// 状态筛选
const activeOrderStatus = ref('all')
const filterByStatus = (status: any) => {

   
    if (status === 'all') {
        searchParams.status = undefined // 全部状态即为不筛选
    } else {
        searchParams.status = activeOrderStatus.value
    }
    pagination.page = 1
    getList()
}

// 分页相关
const handleSizeChange = (val: number) => {
    pagination.limit = val
    pagination.page = 1 // 切换每页显示数量时，重置为第一页
    getList()
}

const handleCurrentChange = (val: number) => {
    pagination.page = val
    getList()
}

// 获取状态类型
const getStatusType = (status: number) => {
    return RETURN_ORDER_STATUS_TYPE[status as RETURN_ORDER_STATUS] || ''
}

// 查看详情
const handleDetail = async (row: IReturnOrder) => {
    try {
        tableLoading.value = true
        currentDetail.value = row
        detailDialogVisible.value = true

    } catch (error) {
        console.error('获取订单详情失败:', error)
        ElMessage.error('获取订单详情失败')
    } finally {
        tableLoading.value = false
    }
}

// 退货地址类型定义
interface ReturnUserAddress {
    id?: number
    name?: string
    mobile?: string
    address?: string
    id_card?: string
    card_pic?: string
}

const return_user_address = ref<ReturnUserAddress>({})

// 修改退货地址对话框相关
const editAddressDialogVisible = ref(false)
const editAddressForm = reactive({
    name: '',
    mobile: '',
    address: ''
})
const editAddressFormRef = ref<FormInstance>()

// 修改退货地址验证规则
const editAddressRules = {
    name: [
        { required: true, message: '请输入联系人姓名', trigger: 'blur' }
    ],
    mobile: [
        { required: true, message: '请输入手机号', trigger: 'blur' },
        { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
    ],
    address: [
        { required: true, message: '请输入详细地址', trigger: 'blur' }
    ]
}

// 修改本次退货地址
const edit_return_user_address = () => {
    // 填充当前地址信息到编辑表单
    editAddressForm.name = return_user_address.value.name || ''
    editAddressForm.mobile = return_user_address.value.mobile || ''
    editAddressForm.address = return_user_address.value.address || ''
    
    editAddressDialogVisible.value = true
}

// 确认修改地址（本地修改）
const confirmEditAddress = async () => {
    if (!editAddressFormRef.value) return

    await editAddressFormRef.value.validate(async (valid) => {
        if (!valid) {
            ElMessage.warning('请填写完整的地址信息')
            return
        }

        // 直接更新本地显示的地址信息
        return_user_address.value = {
            ...return_user_address.value,
            name: editAddressForm.name,
            mobile: editAddressForm.mobile,
            address: editAddressForm.address
        }
        
        editAddressDialogVisible.value = false
        ElMessage.success('地址修改成功')
    })
}
// 确认退货
const handleConfirm = async (id: number) => {
    try {
        // 先获取设备信息，了解其所属订单
        tableLoading.value = true
        const res = await getDeviceInfo(id)

        return_user_address.value = res.data?.memberAddress || {}


        if (handleApiResponse(res, '', '获取设备信息失败')) {
            const deviceInfo = res.data?.data || res.data || {}

            // 重置表单
            confirmForm.id = id
            confirmForm.order_id = deviceInfo.order_id || 0
            confirmForm.express_no = ''
            confirmForm.express_company = ''
            confirmForm.remark = ''
            confirmForm.is_append = false
            confirmForm.return_order_id = 0

            // 检查是否已有该订单的退货单
            if (deviceInfo.order_id) {
                const existingReturnOrder = await checkExistingReturnOrder(deviceInfo.order_id)
                if (existingReturnOrder) {
                    confirmForm.is_append = true
                    confirmForm.return_order_id = existingReturnOrder.id
                    confirmForm.express_no = existingReturnOrder.express_no || ''
                    confirmForm.express_company = existingReturnOrder.express_company || ''

                    // 如果已有退货单，显示追加提示
                    ElMessage({
                        type: 'info',
                        message: `检测到订单 ${deviceInfo.order_no || deviceInfo.order_id} 已有退货单，设备将追加到现有退货单中`
                    })
                }
            }

            confirmDialogVisible.value = true

            // 自动聚焦到输入框，方便扫码枪使用
            focusInput()
        }
    } catch (error) {
        console.error('获取设备信息失败:', error)
        ElMessage.error('获取设备信息失败')
    } finally {
        tableLoading.value = false
    }
}

// 检查是否已有退货单
const checkExistingReturnOrder = async (orderId: number) => {
    try {
        // 实际实现中，需要调用后端API检查是否存在退货单
        const res = await getExistingReturnOrder(orderId)
        if (res.data && res.data.code === 1 && res.data.data) {
            return res.data.data
        }
    } catch (error) {
        console.error('检查现有退货单失败:', error)
    }
    return null
}

// 提交确认退货
const submitConfirm = async () => {
    if (!confirmFormRef.value) return

    await confirmFormRef.value.validate(async (valid) => {
        if (!valid) {
            ElMessage.warning('请填写完整信息')
            return
        }

        operationLoading.value = true
        activeOperationId.value = confirmForm.id

        try {
            let res

            if (confirmForm.is_append) {
                // 如果是追加到现有退货单
                res = await appendToReturnOrder({
                    device_id: confirmForm.id,
                    return_order_id: confirmForm.return_order_id,
                    remark: confirmForm.remark
                })

                if (handleApiResponse(res, '设备已成功添加到退货单', '添加设备到退货单失败')) {
                    confirmDialogVisible.value = false
                    getList()
                    getStatusCount()
                }
            } else {
                // 创建新退货单
                res = await confirmReturnOrder(confirmForm.id, {
                    express_no: confirmForm.express_no,
                    express_company: confirmForm.express_company,
                    remark: confirmForm.remark,
                    order_id: confirmForm.order_id, // 传递订单ID，便于后端关联
                    member_mobile: return_user_address.value.mobile,
                    member_name: return_user_address.value.name,
                    return_address:return_user_address.value.address, 
                })

                if (handleApiResponse(res, '确认退货成功', '确认退货失败')) {
                    confirmDialogVisible.value = false
                    getList()
                    getStatusCount()
                }
            }
        } catch (error) {
            console.error('确认退货失败:', error)
            ElMessage.error('确认退货失败')
        } finally {
            operationLoading.value = false
            activeOperationId.value = null
        }
    })
}



// 获取现有退货单 - 假设这是一个API调用
const getExistingReturnOrder = async (orderId: number) => {
    // 实际实现中应替换为真实的API调用
    // 示例: return request.get(`/recycle/recycle_order/${orderId}/return_order`)
    return Promise.resolve({
        data: {
            code: 1,
            data: null // 示例中返回null，表示没有找到现有退货单
        }
    })
}

// 追加设备到现有退货单 - 假设这是一个API调用
const appendToReturnOrder = async (data: any) => {
    // 实际实现中应替换为真实的API调用
    // 示例: return request.post('/recycle/recycle_return_order/append_device', data)
    return Promise.resolve({
        data: {
            code: 1,
            msg: '设备已成功添加到退货单'
        }
    })
}

// 完成退货
const handleComplete = (id: number) => {
    completeForm.id = id
    completeForm.comment = ''
    completeDialogVisible.value = true
}

// 确认完成
const confirmComplete = async () => {
    operationLoading.value = true
    try {
        const res = await updateReturnOrderStatus(completeForm.id, {
            status: RETURN_ORDER_STATUS.COMPLETED,
            comment: completeForm.comment
        })
        if (handleApiResponse(res, '完成退货成功', '完成退货失败')) {
            completeDialogVisible.value = false
            getList()
            getStatusCount()
        }
    } catch (error) {
        console.error('完成退货失败:', error)
        ElMessage.error('完成退货失败')
    } finally {
        operationLoading.value = false
    }
}

// 删除订单
const handleDelete = (id: number) => {
    ElMessageBox.confirm('确定要删除该退回订单吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    })
        .then(async () => {
            await performOperation(
                id,
                () => deleteReturnOrder(id),
                '删除成功',
                '删除失败'
            )
        })
        .catch(() => { })
}

// 取消订单
const handleCancel = (id: number) => {
    cancelForm.id = id
    cancelForm.comment = ''
    cancelDialogVisible.value = true
}

// 确认取消
const confirmCancel = async () => {
    // 验证表单
    if (!cancelFormRef.value) return

    await cancelFormRef.value.validate(async (valid) => {
        if (!valid) {
            ElMessage.warning('请填写取消原因')
            return
        }

        operationLoading.value = true
        try {
            const res = await cancelReturnOrder(cancelForm.id, cancelForm.comment)
            if (handleApiResponse(res, '取消成功', '取消失败')) {
                cancelDialogVisible.value = false
                getList()
                getStatusCount()
            }
        } catch (error) {
            console.error('取消失败:', error)
            ElMessage.error('取消失败')
        } finally {
            operationLoading.value = false
        }
    })
}

// 多选处理
const handleSelectionChange = (rows: IReturnOrder[]) => {
    selectedRows.value = rows
}

// 批量导出
const batchExport = () => {
    ElMessage.success(`已导出选中的 ${selectedRows.value.length} 条记录`)
    // 实现批量导出逻辑
}

// 批量删除
const batchDelete = () => {
    const ids = selectedRows.value.map(row => row.id)
    ElMessageBox.confirm(`确定要删除选中的 ${ids.length} 条记录吗？`, '批量删除', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(() => {
        ElMessage.success('批量删除成功')
        getList()
        getStatusCount()
    }).catch(() => { })
}

// 导出数据
const exportReturnOrders = () => {
    exportLoading.value = true
    setTimeout(() => {
        ElMessage.success('数据导出成功')
        exportLoading.value = false
    }, 1500)
}

// 打印订单
const printOrderDetail = () => {
    ElMessage.success('订单打印功能已触发')
    // 实现打印功能
}

// 检查操作权限
const canPerformAction = (action: 'DELETE' | 'CANCEL' | 'CONFIRM' | 'COMPLETE', status: number) => {
    return STATUS_ACTION_PERMISSIONS[action].includes(status)
}

onMounted(() => {
    getList()
    getStatusCount()
})
</script>

<style scoped>

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.search-form {
    margin-bottom: 20px;
}

.status-cards {

    margin-bottom: 20px;
    flex-wrap: wrap;
}

.status-card {
    margin-right: 15px;
    margin-bottom: 15px;
    width: 150px;
    cursor: pointer;
    transition: all 0.3s;
}

.status-card.active {
    border-color: #409eff;
    box-shadow: 0 0 8px rgba(64, 158, 255, 0.6);
}

.status-card-content {
    text-align: center;
}

.status-card-title {
    font-size: 14px;
    color: #606266;
    margin-bottom: 10px;
}

.status-card-count {
    font-size: 24px;
    font-weight: bold;
    color: #303133;
}

.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.amount {
    font-weight: bold;
    color: #f56c6c;
}

.batch-actions {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.selected-info {
    margin-left: 10px;
    color: #606266;
    font-size: 14px;
}

.device-list-section,
.timeline-section {
    margin-top: 20px;
}

.device-list-section h3,
.timeline-section h3 {
    font-size: 16px;
    margin-bottom: 10px;
    color: #303133;
    font-weight: bold;
}

.timeline-operator {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.clickable-text {
    color: #409eff;
    cursor: pointer;
}

.clickable-text:hover {
    text-decoration: underline;
}

.scan-tip {
    margin-top: 10px;
}

.scan-instruction {
    font-size: 12px;
    color: #909399;
}
</style>
