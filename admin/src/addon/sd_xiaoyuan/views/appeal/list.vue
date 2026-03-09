<template>
    <div class="appeal-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="接单员姓名">
                    <el-input v-model="searchForm.runner_name" placeholder="请输入接单员姓名" clearable />
                </el-form-item>
                <el-form-item label="申诉类型">
                    <el-select v-model="searchForm.appeal_type" placeholder="全部类型" clearable>
                        <el-option label="订单问题" value="ORDER_ISSUE" />
                        <el-option label="费用问题" value="FEE_ISSUE" />
                        <el-option label="用户问题" value="USER_ISSUE" />
                        <el-option label="其他" value="OTHER" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="待处理" :value="0" />
                        <el-option label="已通过" :value="1" />
                        <el-option label="已拒绝" :value="2" />
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
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value pending">{{ stat.pending || 0 }}</div>
                    <div class="stat-label">待处理</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value success">{{ stat.passed || 0 }}</div>
                    <div class="stat-label">已通过</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value danger">{{ stat.rejected || 0 }}</div>
                    <div class="stat-label">已拒绝</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 申诉列表 -->
        <el-card>
            <el-table :data="appealList" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="order_no" label="订单号" width="180" />
                <el-table-column prop="runner_name" label="接单员" width="120" />
                <el-table-column prop="appeal_type" label="申诉类型" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ getTypeName(row.appeal_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" label="申诉原因" min-width="200">
                    <template #default="{ row }">
                        <el-tooltip :content="row.reason" placement="top" v-if="row.reason?.length > 50">
                            <span>{{ row.reason?.substring(0, 50) }}...</span>
                        </el-tooltip>
                        <span v-else>{{ row.reason }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusName(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="申诉时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button type="success" link size="small" v-if="row.status === 0" @click="handleAppeal(row, 1)">通过</el-button>
                        <el-button type="danger" link size="small" v-if="row.status === 0" @click="handleAppeal(row, 2)">拒绝</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadAppeals"
                @current-change="loadAppeals"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="申诉详情" width="700px">
            <el-descriptions :column="2" border v-if="currentAppeal">
                <el-descriptions-item label="订单号">{{ currentAppeal.order_no }}</el-descriptions-item>
                <el-descriptions-item label="接单员">{{ currentAppeal.runner_name }}</el-descriptions-item>
                <el-descriptions-item label="申诉类型">{{ getTypeName(currentAppeal.appeal_type) }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentAppeal.status)">{{ getStatusName(currentAppeal.status) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="申诉原因" :span="2">{{ currentAppeal.reason }}</el-descriptions-item>
                <el-descriptions-item label="图片凭证" :span="2" v-if="currentAppeal.images">
                    <div class="image-list">
                        <el-image 
                            v-for="(img, index) in parseImages(currentAppeal.images)" 
                            :key="index"
                            :src="img(img)" 
                            style="width: 100px; height: 100px; margin-right: 10px;"
                            :preview-src-list="parseImages(currentAppeal.images).map(item => img(item))"
                        />
                    </div>
                </el-descriptions-item>
                <el-descriptions-item label="处理结果" :span="2" v-if="currentAppeal.handle_result">{{ currentAppeal.handle_result }}</el-descriptions-item>
                <el-descriptions-item label="申诉时间">{{ currentAppeal.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="处理时间" v-if="currentAppeal.handle_time">{{ currentAppeal.handle_time || '-' }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 处理弹窗 -->
        <el-dialog v-model="handleVisible" title="处理申诉" width="500px">
            <el-form :model="handleForm" label-width="100px">
                <el-form-item label="处理结果">
                    <el-input v-model="handleForm.handle_result" type="textarea" :rows="4" placeholder="请输入处理结果说明" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="handleVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmHandle">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getAppealList, handleAppeal as handleAppealApi } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const appealList = ref<any[]>([])
const stat = ref<any>({})
const detailVisible = ref(false)
const handleVisible = ref(false)
const currentAppeal = ref<any>(null)

const searchForm = ref({
    runner_name: '',
    appeal_type: '',
    status: ''
})

const handleForm = ref({
    id: 0,
    status: 0,
    handle_result: ''
})

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

const statusMap: Record<number, string> = {
    0: '待处理',
    1: '已通过',
    2: '已拒绝'
}

const typeMap: Record<string, string> = {
    'ORDER_ISSUE': '订单问题',
    'FEE_ISSUE': '费用问题',
    'USER_ISSUE': '用户问题',
    'OTHER': '其他'
}

onMounted(() => {
    loadAppeals()
})

const loadAppeals = async () => {
    loading.value = true
    try {
        const res = await getAppealList({
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        })
        if (res.code === 1) {
            appealList.value = res.data.list
            pagination.value.total = res.data.count
            if (res.data.stat) {
                stat.value = res.data.stat
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadAppeals()
}

const handleReset = () => {
    searchForm.value = { runner_name: '', appeal_type: '', status: '' }
    handleSearch()
}

const getStatusName = (status: number) => statusMap[status] || '未知'
const getTypeName = (type: string) => typeMap[type] || type

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'
    if (status === 1) return 'success'
    return 'danger'
}


const parseImages = (images: any) => {
    if (typeof images === 'string') {
        try {
            return JSON.parse(images)
        } catch {
            return []
        }
    }
    return images || []
}

const viewDetail = (row: any) => {
    currentAppeal.value = row
    detailVisible.value = true
}

const handleAppeal = (row: any, status: number) => {
    currentAppeal.value = row
    handleForm.value = {
        id: row.id,
        status: status,
        handle_result: ''
    }
    handleVisible.value = true
}

const confirmHandle = async () => {
    try {
        const res = await handleAppealApi(handleForm.value)
        if (res.code === 1) {
            ElMessage.success('处理成功')
            handleVisible.value = false
            loadAppeals()
        } else {
            ElMessage.error(res.msg || '处理失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}
</script>

<style scoped lang="scss">
.appeal-list-container {
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
        &.danger { color: #f56c6c; }
    }
    
    .stat-label {
        font-size: 14px;
        color: #999;
        margin-top: 8px;
    }
}

.el-pagination {
    margin-top: 20px;
    justify-content: flex-end;
}

.image-list {
    display: flex;
    flex-wrap: wrap;
}
</style>
