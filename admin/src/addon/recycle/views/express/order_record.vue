<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">快递订单记录</span>
            </div>

            <el-card class="box-card !border-none mt-[20px]" shadow="never">
                <!-- 搜索表单 -->
                <el-form :inline="true" :model="formData" class="demo-form-inline">
                    <el-form-item label="订单号">
                        <el-input v-model="formData.order_no" placeholder="请输入订单号" clearable />
                    </el-form-item>
                    <el-form-item label="运单号">
                        <el-input v-model="formData.delivery_id" placeholder="请输入运单号" clearable />
                    </el-form-item>
                    <el-form-item label="订单状态">
                        <el-select v-model="formData.order_status" placeholder="请选择" clearable>
                            <el-option label="待揽收" value="pending" />
                            <el-option label="已揽收" value="picked" />
                            <el-option label="运输中" value="in_transit" />
                            <el-option label="已签收" value="delivered" />
                            <el-option label="已取消" value="cancelled" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="回收订单ID">
                        <el-input v-model="formData.recycle_order_id" placeholder="请输入回收订单ID" clearable />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadOrderList()">查询</el-button>
                        <el-button @click="resetForm">重置</el-button>
                    </el-form-item>
                </el-form>

                <!-- 统计卡片 -->
                <el-row :gutter="20" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-label">总订单数</div>
                                <div class="stat-value">{{ statistics.total_count || 0 }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-label">预估总费用</div>
                                <div class="stat-value text-blue-500">¥{{ statistics.total_estimated_cost || 0 }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-label">实际总费用</div>
                                <div class="stat-value text-green-500">¥{{ statistics.total_actual_cost || 0 }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-label">费用差异</div>
                                <div class="stat-value" :class="statistics.total_cost_diff > 0 ? 'text-red-500' : 'text-green-500'">
                                    ¥{{ statistics.total_cost_diff || 0 }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 数据表格 -->
                <el-table v-loading="loading" :data="orderList" stripe style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80" />
                    <el-table-column prop="order_no" label="订单号" width="150" />
                    <el-table-column prop="delivery_id" label="运单号" width="150" />
                    <el-table-column prop="product_name" label="快递产品" width="120" />
                    <el-table-column label="发件人" width="150">
                        <template #default="{ row }">
                            <div>{{ row.sender_name }}</div>
                            <div class="text-gray-500 text-xs">{{ row.sender_mobile }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="收件人" width="150">
                        <template #default="{ row }">
                            <div>{{ row.receiver_name }}</div>
                            <div class="text-gray-500 text-xs">{{ row.receiver_mobile }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="重量(kg)" width="120">
                        <template #default="{ row }">
                            <div>预估: {{ row.estimated_weight }}</div>
                            <div v-if="row.actual_weight > 0" :class="row.weight_diff > 0 ? 'text-red-500' : 'text-green-500'">
                                实际: {{ row.actual_weight }}
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="费用(元)" width="120">
                        <template #default="{ row }">
                            <div>预估: ¥{{ row.estimated_cost }}</div>
                            <div v-if="row.actual_cost > 0" :class="row.cost_diff > 0 ? 'text-red-500' : 'text-green-500'">
                                实际: ¥{{ row.actual_cost }}
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="order_status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag v-if="row.order_status === 'pending'" type="info">待揽收</el-tag>
                            <el-tag v-else-if="row.order_status === 'picked'" type="warning">已揽收</el-tag>
                            <el-tag v-else-if="row.order_status === 'in_transit'" type="primary">运输中</el-tag>
                            <el-tag v-else-if="row.order_status === 'delivered'" type="success">已签收</el-tag>
                            <el-tag v-else-if="row.order_status === 'cancelled'" type="danger">已取消</el-tag>
                            <el-tag v-else>{{ row.order_status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_at" label="创建时间" width="180">
                        <template #default="{ row }">
                            {{ timeStampTurnTime(row.create_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="150">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="handleViewDetail(row)">详情</el-button>
                            <el-button link type="warning" @click="handleUpdateActual(row)">更新</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 分页 -->
                <div class="flex justify-end mt-4">
                    <el-pagination
                        v-model:current-page="formData.page"
                        v-model:page-size="formData.limit"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="total"
                        layout="total, sizes, prev, pager, next, jumper"
                        @size-change="loadOrderList"
                        @current-change="loadOrderList"
                    />
                </div>
            </el-card>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog
            v-model="detailDialogVisible"
            title="订单详情"
            width="800px"
        >
            <el-descriptions :column="2" border v-if="currentOrder">
                <el-descriptions-item label="订单号">{{ currentOrder.order_no }}</el-descriptions-item>
                <el-descriptions-item label="运单号">{{ currentOrder.delivery_id }}</el-descriptions-item>
                <el-descriptions-item label="快递产品">{{ currentOrder.product_name }}</el-descriptions-item>
                <el-descriptions-item label="订单状态">
                    <el-tag v-if="currentOrder.order_status === 'pending'" type="info">待揽收</el-tag>
                    <el-tag v-else-if="currentOrder.order_status === 'picked'" type="warning">已揽收</el-tag>
                    <el-tag v-else-if="currentOrder.order_status === 'in_transit'" type="primary">运输中</el-tag>
                    <el-tag v-else-if="currentOrder.order_status === 'delivered'" type="success">已签收</el-tag>
                    <el-tag v-else-if="currentOrder.order_status === 'cancelled'" type="danger">已取消</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="发件人">{{ currentOrder.sender_name }}</el-descriptions-item>
                <el-descriptions-item label="发件电话">{{ currentOrder.sender_mobile }}</el-descriptions-item>
                <el-descriptions-item label="发件地址" :span="2">
                    {{ currentOrder.sender_province }} {{ currentOrder.sender_city }} {{ currentOrder.sender_district }} {{ currentOrder.sender_address }}
                </el-descriptions-item>
                <el-descriptions-item label="收件人">{{ currentOrder.receiver_name }}</el-descriptions-item>
                <el-descriptions-item label="收件电话">{{ currentOrder.receiver_mobile }}</el-descriptions-item>
                <el-descriptions-item label="收件地址" :span="2">
                    {{ currentOrder.receiver_province }} {{ currentOrder.receiver_city }} {{ currentOrder.receiver_district }} {{ currentOrder.receiver_address }}
                </el-descriptions-item>
                <el-descriptions-item label="物品名称">{{ currentOrder.goods_name }}</el-descriptions-item>
                <el-descriptions-item label="包裹数量">{{ currentOrder.package_count }}</el-descriptions-item>
                <el-descriptions-item label="预估重量">{{ currentOrder.estimated_weight }} kg</el-descriptions-item>
                <el-descriptions-item label="实际重量">
                    <span :class="currentOrder.weight_diff > 0 ? 'text-red-500' : ''">
                        {{ currentOrder.actual_weight }} kg
                        <span v-if="currentOrder.weight_diff !== 0">({{ currentOrder.weight_diff > 0 ? '+' : '' }}{{ currentOrder.weight_diff }})</span>
                    </span>
                </el-descriptions-item>
                <el-descriptions-item label="预估费用">¥{{ currentOrder.estimated_cost }}</el-descriptions-item>
                <el-descriptions-item label="实际费用">
                    <span :class="currentOrder.cost_diff > 0 ? 'text-red-500' : ''">
                        ¥{{ currentOrder.actual_cost }}
                        <span v-if="currentOrder.cost_diff !== 0">({{ currentOrder.cost_diff > 0 ? '+' : '' }}¥{{ currentOrder.cost_diff }})</span>
                    </span>
                </el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ timeStampTurnTime(currentOrder.create_at) }}</el-descriptions-item>
                <el-descriptions-item label="更新时间">{{ timeStampTurnTime(currentOrder.update_at) }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 更新实际信息对话框 -->
        <el-dialog
            v-model="updateDialogVisible"
            title="更新实际信息"
            width="500px"
        >
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
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { timeStampTurnTime } from '@/utils/common'
import {
    getExpressOrderRecordList,
    getExpressOrderRecordInfo,
    updateExpressOrderActualInfo,
    getExpressOrderStatistics
} from '@/addon/recycle/api/express'

const loading = ref(false)
const orderList = ref<any[]>([])
const total = ref(0)
const statistics = ref<any>({})
const detailDialogVisible = ref(false)
const updateDialogVisible = ref(false)
const currentOrder = ref<any>(null)

const formData = ref({
    order_no: '',
    delivery_id: '',
    order_status: '',
    recycle_order_id: '',
    page: 1,
    limit: 20
})

const updateForm = ref({
    id: 0,
    actual_weight: 0,
    actual_cost: 0
})

// 加载订单列表
const loadOrderList = async () => {
    loading.value = true
    try {
        const res = await getExpressOrderRecordList(formData.value)
        orderList.value = res.data.list || []
        total.value = res.data.total || 0
    } catch (error) {
        ElMessage.error('加载订单列表失败')
    } finally {
        loading.value = false
    }
}

// 加载统计数据
const loadStatistics = async () => {
    try {
        const res = await getExpressOrderStatistics({})
        statistics.value = res.data || {}
    } catch (error) {
        console.error('加载统计数据失败', error)
    }
}

// 重置表单
const resetForm = () => {
    formData.value = {
        order_no: '',
        delivery_id: '',
        order_status: '',
        recycle_order_id: '',
        page: 1,
        limit: 20
    }
    loadOrderList()
}

// 查看详情
const handleViewDetail = async (row: any) => {
    try {
        const res = await getExpressOrderRecordInfo(row.id)
        currentOrder.value = res.data
        detailDialogVisible.value = true
    } catch (error) {
        ElMessage.error('获取订单详情失败')
    }
}

// 更新实际信息
const handleUpdateActual = (row: any) => {
    updateForm.value = {
        id: row.id,
        actual_weight: row.actual_weight || row.estimated_weight,
        actual_cost: row.actual_cost || row.estimated_cost
    }
    updateDialogVisible.value = true
}

// 确认更新
const handleConfirmUpdate = async () => {
    try {
        await updateExpressOrderActualInfo(updateForm.value)
        ElMessage.success('更新成功')
        updateDialogVisible.value = false
        await loadOrderList()
        await loadStatistics()
    } catch (error: any) {
        ElMessage.error(error.message || '更新失败')
    }
}

onMounted(() => {
    loadOrderList()
    loadStatistics()
})
</script>

<style scoped lang="scss">
.main-container {
    padding: 20px;
}

.stat-card {
    text-align: center;
    .stat-label {
        font-size: 14px;
        color: #909399;
        margin-bottom: 10px;
    }
    .stat-value {
        font-size: 24px;
        font-weight: bold;
    }
}
</style>
