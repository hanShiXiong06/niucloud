<template>
    <div class="withdraw-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="提现单号">
                    <el-input v-model="searchForm.withdraw_no" placeholder="请输入提现单号" clearable />
                </el-form-item>
                <el-form-item label="接单员姓名">
                    <el-input v-model="searchForm.runner_name" placeholder="请输入接单员姓名" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="待审核" :value="0" />
                        <el-option label="审核通过" :value="1" />
                        <el-option label="审核拒绝" :value="2" />
                        <el-option label="已打款" :value="3" />
                        <el-option label="打款失败" :value="4" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-row">
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value pending">{{ stat.pending || 0 }}</div>
                    <div class="stat-label">待审核</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">¥{{ stat.pending_amount || 0 }}</div>
                    <div class="stat-label">待审核金额</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value success">{{ stat.completed || 0 }}</div>
                    <div class="stat-label">已完成</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">¥{{ stat.completed_amount || 0 }}</div>
                    <div class="stat-label">已打款金额</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 提现列表 -->
        <el-card>
            <div class="mb-[10px]">
                <el-button type="primary" @click="batchAudit" :disabled="selectedRows.length === 0">批量审核</el-button>
                <span class="ml-[10px] text-gray-500">已选择 {{ selectedRows.length }} 条</span>
            </div>
            <el-table :data="withdrawList" v-loading="loading" stripe @selection-change="handleSelectionChange">
                <el-table-column type="selection" width="55" :selectable="checkSelectable" />
                <el-table-column prop="withdraw_no" label="提现单号" width="180" />
                <el-table-column prop="runner_name" label="接单员" width="120" />
                <el-table-column prop="amount" label="提现金额" width="120">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.amount }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="fee" label="手续费" width="100">
                    <template #default="{ row }">
                        ¥{{ row.fee }}
                    </template>
                </el-table-column>
                <el-table-column prop="actual_amount" label="实际到账" width="120">
                    <template #default="{ row }">
                        <span class="text-success">¥{{ row.actual_amount }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="withdraw_type" label="提现方式" width="100">
                    <template #default="{ row }">
                        {{ getWithdrawTypeName(row.withdraw_type) }}
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusName(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="申请时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button type="success" link size="small" v-if="row.status === 0" @click="auditWithdraw(row, 1)">通过</el-button>
                        <el-button type="danger" link size="small" v-if="row.status === 0" @click="auditWithdraw(row, 2)">拒绝</el-button>
                        <el-button type="primary" link size="small" v-if="row.status === 1" @click="transferWithdraw(row)">打款</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadWithdraws"
                @current-change="loadWithdraws"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="提现详情" width="600px">
            <el-descriptions :column="2" border v-if="currentWithdraw">
                <el-descriptions-item label="提现单号">{{ currentWithdraw.withdraw_no }}</el-descriptions-item>
                <el-descriptions-item label="接单员">{{ currentWithdraw.runner_name }}</el-descriptions-item>
                <el-descriptions-item label="提现金额">¥{{ currentWithdraw.amount }}</el-descriptions-item>
                <el-descriptions-item label="手续费">¥{{ currentWithdraw.fee }}</el-descriptions-item>
                <el-descriptions-item label="实际到账">¥{{ currentWithdraw.actual_amount }}</el-descriptions-item>
                <el-descriptions-item label="提现方式">{{ getWithdrawTypeName(currentWithdraw.withdraw_type) }}</el-descriptions-item>
                <el-descriptions-item label="账户名">{{ currentWithdraw.account_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="账号">{{ currentWithdraw.account_no || '-' }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentWithdraw.status)">{{ getStatusName(currentWithdraw.status) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="申请时间">{{ currentWithdraw.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="拒绝原因" :span="2" v-if="currentWithdraw.refuse_reason">{{ currentWithdraw.refuse_reason }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 拒绝原因弹窗 -->
        <el-dialog v-model="refuseVisible" title="拒绝原因" width="400px">
            <el-input v-model="refuseReason" type="textarea" :rows="4" placeholder="请输入拒绝原因" />
            <template #footer>
                <el-button @click="refuseVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmRefuse">确定</el-button>
            </template>
        </el-dialog>

        <!-- 批量审核弹窗 -->
        <el-dialog v-model="batchAuditVisible" title="出纳审核" width="600px">
            <el-form label-width="100px">
                <el-form-item label="审核状态">
                    <el-radio-group v-model="batchAuditStatus">
                        <el-radio :label="1">审核通过</el-radio>
                        <el-radio :label="2">审核驳回</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="操作订单" v-if="selectedRows.length > 0">
                    <div class="order-list">
                        <el-tag v-for="row in selectedRows" :key="row.id" class="mr-[5px] mb-[5px]">
                            {{ row.withdraw_no }}
                        </el-tag>
                    </div>
                </el-form-item>
                <el-form-item label="驳回原因" v-if="batchAuditStatus === 2">
                    <el-input v-model="batchRefuseReason" type="textarea" :rows="4" placeholder="请输入驳回原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="batchAuditVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmBatchAudit">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getWithdrawList, getWithdrawStat, auditWithdraw as auditWithdrawApi, transferWithdraw as transferWithdrawApi } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const withdrawList = ref<any[]>([])
const stat = ref<any>({})
const detailVisible = ref(false)
const refuseVisible = ref(false)
const currentWithdraw = ref<any>(null)
const refuseReason = ref('')
const selectedRows = ref<any[]>([])
const batchAuditVisible = ref(false)
const batchAuditStatus = ref(1)
const batchRefuseReason = ref('')

const searchForm = ref({
    withdraw_no: '',
    runner_name: '',
    status: ''
})

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '审核通过',
    2: '审核拒绝',
    3: '已打款',
    4: '打款失败'
}

const withdrawTypeMap: Record<string, string> = {
    'WECHAT': '微信',
    'ALIPAY': '支付宝',
    'BANK': '银行卡'
}

onMounted(() => {
    loadWithdraws()
    loadStat()
})

const loadWithdraws = async () => {
    loading.value = true
    try {
        const res = await getWithdrawList({
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        })
        if (res.code === 1) {
            withdrawList.value = res.data.list
            pagination.value.total = res.data.count
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const loadStat = async () => {
    try {
        const res = await getWithdrawStat()
        if (res.code === 1) {
            stat.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadWithdraws()
}

const handleReset = () => {
    searchForm.value = { withdraw_no: '', runner_name: '', status: '' }
    handleSearch()
}

const getStatusName = (status: number) => statusMap[status] || '未知'
const getWithdrawTypeName = (type: string) => withdrawTypeMap[type] || type

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'
    if (status === 1) return 'primary'
    if (status === 2 || status === 4) return 'danger'
    if (status === 3) return 'success'
    return 'info'
}


const viewDetail = (row: any) => {
    currentWithdraw.value = row
    detailVisible.value = true
}

const auditWithdraw = async (row: any, status: number) => {
    if (status === 2) {
        currentWithdraw.value = row
        refuseReason.value = ''
        refuseVisible.value = true
        return
    }
    
    try {
        const res = await auditWithdrawApi({ id: row.id, status, refuse_reason: '' })
        if (res.code === 1) {
            ElMessage.success('审核成功')
            loadWithdraws()
            loadStat()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const confirmRefuse = async () => {
    if (!refuseReason.value) {
        ElMessage.warning('请输入拒绝原因')
        return
    }
    
    try {
        const res = await auditWithdrawApi({ id: currentWithdraw.value.id, status: 2, refuse_reason: refuseReason.value })
        if (res.code === 1) {
            ElMessage.success('操作成功')
            refuseVisible.value = false
            loadWithdraws()
            loadStat()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const transferWithdraw = (row: any) => {
    ElMessageBox.confirm('确定已完成打款吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            const res = await transferWithdrawApi({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('打款成功')
                loadWithdraws()
                loadStat()
            } else {
                ElMessage.error(res.msg || '操作失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}

// 表格多选
const handleSelectionChange = (selection: any[]) => {
    selectedRows.value = selection
}

// 只允许选择待审核状态的记录
const checkSelectable = (row: any) => {
    return row.status === 0
}

// 批量审核
const batchAudit = () => {
    if (selectedRows.value.length === 0) {
        ElMessage.warning('请选择要审核的记录')
        return
    }
    batchAuditStatus.value = 1
    batchRefuseReason.value = ''
    batchAuditVisible.value = true
}

// 确认批量审核
const confirmBatchAudit = async () => {
    if (batchAuditStatus.value === 2 && !batchRefuseReason.value) {
        ElMessage.warning('请输入驳回原因')
        return
    }

    try {
        const promises = selectedRows.value.map(row => 
            auditWithdrawApi({ 
                id: row.id, 
                status: batchAuditStatus.value, 
                refuse_reason: batchAuditStatus.value === 2 ? batchRefuseReason.value : '' 
            })
        )
        
        await Promise.all(promises)
        
        ElMessage.success('批量审核成功')
        batchAuditVisible.value = false
        selectedRows.value = []
        loadWithdraws()
        loadStat()
    } catch (e) {
        ElMessage.error('操作失败')
    }
}
</script>

<style scoped lang="scss">
.withdraw-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.stat-row {
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    
    .stat-value {
        font-size: 28px;
        font-weight: bold;
        color: #333;
        
        &.pending { color: #e6a23c; }
        &.success { color: #67c23a; }
    }
    
    .stat-label {
        font-size: 14px;
        color: #999;
        margin-top: 8px;
    }
}

.text-price {
    color: #ff6b00;
    font-weight: bold;
}

.text-success {
    color: #67c23a;
    font-weight: bold;
}

.el-pagination {
    margin-top: 20px;
    justify-content: flex-end;
}

.order-list {
    max-height: 200px;
    overflow-y: auto;
}
</style>
