<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="wjBooksOrderTable.searchParam" ref="searchFormRef">
                    <el-form-item label="订单编号" prop="order_no">
                        <el-input v-model="wjBooksOrderTable.searchParam.order_no" placeholder="请输入订单编号" />
                    </el-form-item>

                    <el-form-item label="订单状态" prop="status">
                        <el-select v-model="wjBooksOrderTable.searchParam.status" clearable placeholder="请选择订单状态">
                            <el-option label="待取件" :value="1" />
                            <el-option label="已取件" :value="2" />
                            <el-option label="审核中" :value="3" />
                            <el-option label="已完成" :value="4" />
                            <el-option label="已取消" :value="5" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="物流单号" prop="express_waybill">
                        <el-input v-model="wjBooksOrderTable.searchParam.express_waybill" placeholder="请输入物流单号" clearable />
                    </el-form-item>

                    <el-form-item label="创建时间" prop="create_time">
                        <el-date-picker
                            v-model="dateRange"
                            type="daterange"
                            range-separator="~"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            value-format="YYYY-MM-DD"
                            @change="handleDateChange"
                        />
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadWjBooksOrderList()">搜索</el-button>
                        <el-button @click="resetForm(searchFormRef)">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="wjBooksOrderTable.data" size="large" v-loading="wjBooksOrderTable.loading">
                    <template #empty>
                        <span>{{ !wjBooksOrderTable.loading ? '暂无数据' : '' }}</span>
                    </template>
                    
                    <el-table-column prop="order_no" label="订单编号" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column label="图书信息" min-width="120">
                        <template #default="{ row }">
                            <div>图书数量: {{ row.book_count }}</div>
                            <div>预估金额: ¥{{ row.total_amount }}</div>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="最终信息" min-width="120">
                        <template #default="{ row }">
                            <div v-if="row.final_book_count">最终数量: {{ row.final_book_count }}</div>
                            <div v-if="row.final_amount">最终金额: ¥{{ row.final_amount }}</div>
                            <div v-if="!row.final_book_count && !row.final_amount">未审核</div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="status" label="订单状态" min-width="100">
                        <template #default="{ row }">
                            <el-tag :type="getStatusTagType(row.status)">
                                {{ formatStatus(row.status) }}
                            </el-tag>
                            <div v-if="row.status === 3" class="mt-1">
                                <el-progress 
                                    :percentage="row.audit_progress || 0" 
                                    :stroke-width="5"
                                    :text-inside="true"
                                    :show-text="false"
                                ></el-progress>
                                <div class="text-xs text-gray-500 mt-1">{{ row.audit_progress || 0 }}%</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="pickup_time" label="上门时间" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column label="上门地址" min-width="180" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-if="row.pickup_address">
                                <div>{{ row.pickup_name }} {{ row.pickup_mobile }}</div>
                                <div class="text-xs text-gray-500">{{ row.pickup_address }}</div>
                            </div>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    
                    <el-table-column prop="express_waybill" label="物流单号" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="create_time" label="创建时间" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column label="操作" fixed="right" min-width="240">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="viewDetail(row)">查看</el-button>
                           <el-button v-if="row.status === 1" type="success" link @click="updateStatus(row.id, 2)">确认取件</el-button>
                           <el-button v-if="row.status === 2" type="success" link @click="updateStatus(row.id, 3)">开始审核</el-button>
                           <el-button v-if="[1, 2, 3].includes(row.status)" type="warning" link @click="showExpressDialog(row)">更新物流</el-button>
                           <el-button v-if="row.status === 1" type="danger" link @click="showCancelDialog(row)">取消</el-button>
                       </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination 
                        v-model:current-page="wjBooksOrderTable.page" 
                        v-model:page-size="wjBooksOrderTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" 
                        :total="wjBooksOrderTable.total"
                        @size-change="loadWjBooksOrderList()" 
                        @current-change="loadWjBooksOrderList" 
                    />
                </div>
            </div>

            <!-- 更新物流对话框 -->
            <el-dialog v-model="expressDialog.visible" title="更新物流信息" width="500px">
                <el-form :model="expressDialog.form" label-width="100px" ref="expressFormRef">
                    <el-form-item label="物流公司" required>
                        <el-input 
                            v-model="expressDialog.form.express_company" 
                            placeholder="请输入物流公司名称"
                        ></el-input>
                    </el-form-item>
                    <el-form-item label="物流单号" required>
                        <el-input 
                            v-model="expressDialog.form.express_waybill" 
                            placeholder="请输入物流单号"
                        ></el-input>
                    </el-form-item>
                    <el-form-item label="用户备注">
                        <el-input 
                            v-model="expressDialog.form.express_remark" 
                            type="textarea" 
                            :rows="3"
                            placeholder="请输入备注信息"
                        ></el-input>
                    </el-form-item>
                </el-form>
                <template #footer>
                    <span class="dialog-footer">
                        <el-button @click="expressDialog.visible = false">取消</el-button>
                        <el-button type="primary" @click="confirmUpdateExpress" :loading="expressDialog.loading">
                            确定
                        </el-button>
                    </span>
                </template>
            </el-dialog>

            <!-- 取消订单对话框 -->
            <el-dialog v-model="cancelDialog.visible" title="取消订单" width="500px">
                <el-form :model="cancelDialog.form" label-width="100px">
                    <el-form-item label="取消原因" required>
                        <el-input 
                            v-model="cancelDialog.form.reason" 
                            type="textarea" 
                            :rows="3"
                            placeholder="请输入取消原因"
                        ></el-input>
                    </el-form-item>
                </el-form>
                <template #footer>
                    <span class="dialog-footer">
                        <el-button @click="cancelDialog.visible = false">取消</el-button>
                        <el-button type="primary" @click="confirmCancel" :loading="cancelDialog.loading">
                            确定
                        </el-button>
                    </span>
                </template>
            </el-dialog>

            <!-- 订单详情组件 -->
            <order-detail ref="orderDetailRef" @refresh="loadWjBooksOrderList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { ElMessageBox, FormInstance } from 'element-plus'
import { getWjBooksOrderList, updateWjBooksOrderStatus, cancelWjBooksOrder, updateWjBooksOrderAuditProgress, updateWjBooksOrderExpress } from '@/addon/wj_books/api/wj_books_order'
import { useRoute, useRouter } from 'vue-router'
import OrderDetail from '@/addon/wj_books/views/wj_books_order/components/wj-books-order-detail.vue'

const route = useRoute()
const router = useRouter()
const pageName = route.meta.title

// 订单列表数据
const wjBooksOrderTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        order_no: '',
        status: '',
        express_waybill: '',
        start_time: '',
        end_time: ''
    }
})

// 日期范围
const dateRange = ref([])

// 搜索表单引用
const searchFormRef = ref<FormInstance>()

// 订单详情组件引用
const orderDetailRef = ref()

// 更新物流对话框
const expressDialog = reactive({
    visible: false,
    loading: false,
    orderId: null as number | null,
    form: {
        express_company: '',
        express_waybill: '',
        express_remark: ''
    }
})

// 物流表单引用
const expressFormRef = ref<FormInstance>()

// 取消订单对话框
const cancelDialog = reactive({
    visible: false,
    loading: false,
    orderId: null as number | null,
    form: {
        reason: ''
    }
})

/**
 * 加载订单列表
 */
const loadWjBooksOrderList = (page: number = 1) => {
    wjBooksOrderTable.loading = true
    wjBooksOrderTable.page = page

    getWjBooksOrderList({
        page: wjBooksOrderTable.page,
        limit: wjBooksOrderTable.limit,
        ...wjBooksOrderTable.searchParam
    }).then(res => {
        wjBooksOrderTable.loading = false
        wjBooksOrderTable.data = res.data.data
        wjBooksOrderTable.total = res.data.total
    }).catch(() => {
        wjBooksOrderTable.loading = false
    })
}

// 初始加载订单列表
loadWjBooksOrderList()

/**
 * 处理日期范围变化
 */
const handleDateChange = (val: any) => {
    if (val) {
        wjBooksOrderTable.searchParam.start_time = val[0]
        wjBooksOrderTable.searchParam.end_time = val[1]
    } else {
        wjBooksOrderTable.searchParam.start_time = ''
        wjBooksOrderTable.searchParam.end_time = ''
    }
}

/**
 * 重置表单
 */
const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    dateRange.value = []
    wjBooksOrderTable.searchParam.start_time = ''
    wjBooksOrderTable.searchParam.end_time = ''
    loadWjBooksOrderList()
}

/**
 * 格式化订单状态
 */
const formatStatus = (status: number) => {
    const statusMap: Record<number, string> = {
        1: '待取件',
        2: '已取件',
        3: '审核中',
        4: '已完成',
        5: '已取消'
    }
    return statusMap[status] || status
}

/**
 * 获取状态标签类型
 */
const getStatusTagType = (status: number) => {
    const typeMap: Record<number, string> = {
        1: 'warning',
        2: 'info',
        3: 'primary',
        4: 'success',
        5: 'danger'
    }
    return typeMap[status] || ''
}

/**
 * 查看订单详情
 */
const viewDetail = (row: any) => {
    orderDetailRef.value.showDetail(row.id)
}

/**
 * 更新订单状态
 */
const updateStatus = (id: number, status: number) => {
    let confirmMessage = ''
    if (status === 2) {
        confirmMessage = '确认已取件吗？'
    } else if (status === 3) {
        confirmMessage = '确认开始审核吗？'
    }

    ElMessageBox.confirm(confirmMessage, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
    }).then(() => {
        updateWjBooksOrderStatus(id, status).then(() => {
            // 如果是开始审核，初始化审核进度为0
            if (status === 3) {
                updateWjBooksOrderAuditProgress(id, 0)
            }
            loadWjBooksOrderList()
        })
    })
}

/**
 * 显示取消订单对话框
 */
const showCancelDialog = (row: any) => {
    cancelDialog.orderId = row.id
    cancelDialog.form.reason = ''
    cancelDialog.visible = true
}

/**
 * 显示更新物流对话框
 */
const showExpressDialog = (row: any) => {
    expressDialog.orderId = row.id
    expressDialog.form.express_company = row.express_channel || ''
    expressDialog.form.express_waybill = row.express_waybill || ''
    expressDialog.form.express_remark = row.remark || ''
    expressDialog.visible = true
}

/**
 * 确认更新物流信息
 */
const confirmUpdateExpress = () => {
    if (!expressDialog.form.express_company) {
        ElMessageBox.alert('请输入物流公司', '提示')
        return
    }
    if (!expressDialog.form.express_waybill) {
        ElMessageBox.alert('请输入物流单号', '提示')
        return
    }

    expressDialog.loading = true
    updateWjBooksOrderExpress(expressDialog.orderId as number, expressDialog.form).then(() => {
        expressDialog.loading = false
        expressDialog.visible = false
        loadWjBooksOrderList()
    }).catch(() => {
        expressDialog.loading = false
    })
}

/**
 * 确认取消订单
 */
const confirmCancel = () => {
    if (!cancelDialog.form.reason) {
        ElMessageBox.alert('请输入取消原因', '提示')
        return
    }

    cancelDialog.loading = true
    cancelWjBooksOrder(cancelDialog.orderId as number, cancelDialog.form.reason).then(() => {
        cancelDialog.loading = false
        cancelDialog.visible = false
        loadWjBooksOrderList()
    }).catch(() => {
        cancelDialog.loading = false
    })
}
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
    word-break: break-all;
    text-overflow: ellipsis;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
</style>
 