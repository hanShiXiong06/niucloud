<template>
    <div class="runner-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="接单员姓名">
                    <el-input v-model="searchForm.real_name" placeholder="请输入姓名" clearable />
                </el-form-item>
                <el-form-item label="手机号">
                    <el-input v-model="searchForm.mobile" placeholder="请输入手机号" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="待审核" :value="0" />
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
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <el-icon><User /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">{{ stat.total || 0 }}</div>
                            <div class="stat-label">接单员总数</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon online">
                            <el-icon><Connection /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value online">{{ stat.online || 0 }}</div>
                            <div class="stat-label">在线接单员</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon pending">
                            <el-icon><Clock /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value pending">{{ stat.pending_audit || 0 }}</div>
                            <div class="stat-label">待审核</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon orders">
                            <el-icon><Document /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">{{ stat.today_orders || 0 }}</div>
                            <div class="stat-label">今日接单</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 接单员列表 -->
        <el-card>
            <el-table :data="runnerList" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column label="接单员信息" min-width="200">
                    <template #default="{ row }">
                        <div class="runner-info">
                            <el-avatar :src="img(row.avatar)" :size="40" />
                            <div class="info">
                                <div class="name">{{ row.real_name }}</div>
                                <div class="mobile">{{ row.mobile }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="score" label="评分" width="100">
                    <template #default="{ row }">
                        <el-rate v-model="row.score" disabled :max="5" />
                    </template>
                </el-table-column>
                <el-table-column prop="total_orders" label="总订单" width="100" />
                <el-table-column prop="complete_orders" label="完成订单" width="100" />
                <el-table-column prop="balance" label="余额" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.balance }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="total_income" label="累计收益" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.total_income }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusName(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="可接单" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-if="row.status === 1"
                            :model-value="Number(row.can_jiedan) === 1"
                            @change="(v: boolean) => onToggleCanJiedan(row, v)"
                        />
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="申请时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button type="success" link size="small" v-if="row.status === 0" @click="auditRunner(row, 1)">通过</el-button>
                        <el-button type="danger" link size="small" v-if="row.status === 0" @click="auditRunner(row, 2)">拒绝</el-button>
                        <el-button type="danger" link size="small" @click="deleteRunnerRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadRunners"
                @current-change="loadRunners"
            />
        </el-card>

        <!-- 接单员详情弹窗 -->
        <el-dialog v-model="detailVisible" title="接单员详情" width="700px">
            <el-descriptions :column="2" border v-if="currentRunner">
                <el-descriptions-item label="接单员ID">{{ currentRunner.id }}</el-descriptions-item>
                <el-descriptions-item label="姓名">{{ currentRunner.real_name }}</el-descriptions-item>
                <el-descriptions-item label="手机号">{{ currentRunner.mobile }}</el-descriptions-item>
                <el-descriptions-item label="评分">{{ currentRunner.score }}</el-descriptions-item>
                <el-descriptions-item label="总订单">{{ currentRunner.total_orders }}</el-descriptions-item>
                <el-descriptions-item label="完成订单">{{ currentRunner.complete_orders }}</el-descriptions-item>
                <el-descriptions-item label="余额">¥{{ currentRunner.balance }}</el-descriptions-item>
                <el-descriptions-item label="累计收益">¥{{ currentRunner.total_income }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentRunner.status)">{{ getStatusName(currentRunner.status) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="学生证" :span="2">
                    <el-image :src="img(currentRunner.student_cert)" style="width: 200px;" :preview-src-list="[img(currentRunner.student_cert)]" />
                </el-descriptions-item>
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
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { User, Connection, Clock, Document } from '@element-plus/icons-vue'
import { img } from '@/utils/common'
import { getRunnerList, getRunnerDetail, auditRunner as auditRunnerApi, getRunnerStat, getAllSchools, setRunnerCanJiedan, deleteRunner as deleteRunnerApi } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const runnerList = ref<any[]>([])
const stat = ref<any>({})
const detailVisible = ref(false)
const refuseVisible = ref(false)
const currentRunner = ref<any>(null)
const refuseReason = ref('')

const searchForm = ref({
    school_id: '',
    real_name: '',
    mobile: '',
    status: ''
})

const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '已通过',
    2: '已拒绝'
}

onMounted(() => {
    loadSchools()
    loadRunners()
    loadStat()
})

const loadRunners = async () => {
    loading.value = true
    try {
        const res = await getRunnerList({
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        })
        if (res.code === 1) {
            runnerList.value = res.data.list
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
        const res = await getRunnerStat()
        if (res.code === 1) {
            stat.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadRunners()
}

const handleReset = () => {
    searchForm.value = { school_id: '', real_name: '', mobile: '', status: '' }
    handleSearch()
}

const getStatusName = (status: number) => statusMap[status] || '未知'


const getStatusType = (status: number) => {
    if (status === 0) return 'warning'
    if (status === 1) return 'success'
    if (status === 2) return 'danger'
    return 'info'
}

const viewDetail = (row: any) => {
    currentRunner.value = row
    detailVisible.value = true
}

const auditRunner = async (row: any, status: number) => {
    if (status === 2) {
        currentRunner.value = row
        refuseReason.value = ''
        refuseVisible.value = true
        return
    }
    
    try {
        const res = await auditRunnerApi({ id: row.id, status, refuse_reason: '' })
        if (res.code === 1) {
            ElMessage.success('审核成功')
            loadRunners()
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
        const res = await auditRunnerApi({ id: currentRunner.value.id, status: 2, refuse_reason: refuseReason.value })
        if (res.code === 1) {
            ElMessage.success('操作成功')
            refuseVisible.value = false
            loadRunners()
            loadStat()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const onToggleCanJiedan = async (row: any, v: boolean) => {
    const res: any = await setRunnerCanJiedan({ id: row.id, can_jiedan: v ? 1 : 0 })
    if (res.code === 1) {
        row.can_jiedan = v ? 1 : 0
    } else {
        ElMessage.error(res.msg || '操作失败')
    }
}

const deleteRunnerRow = (row: any) => {
    ElMessageBox.confirm(`确定删除接单员「${row.real_name}」吗？`, '提示', { type: 'warning' }).then(async () => {
        const res: any = await deleteRunnerApi({ id: row.id })
        if (res.code === 1) {
            ElMessage.success('删除成功')
            loadRunners()
            loadStat()
        } else {
            ElMessage.error(res.msg || '删除失败')
        }
    }).catch(() => {})
}

</script>

<style scoped lang="scss">
.runner-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.stat-row {
    margin-bottom: 20px;
}

.stat-card {
    .stat-content {
        display: flex;
        align-items: center;
        padding: 10px;
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            background: #f0f2f5;
            
            .el-icon {
                font-size: 24px;
                color: #666;
            }
            
            &.online {
                background: #f0f9ff;
                .el-icon { color: #1890ff; }
            }
            
            &.pending {
                background: #fff7e6;
                .el-icon { color: #fa8c16; }
            }
            
            &.orders {
                background: #f6ffed;
                .el-icon { color: #52c41a; }
            }
        }
        
        .stat-info {
            flex: 1;
            
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #333;
                line-height: 1;
                
                &.online { color: #1890ff; }
                &.pending { color: #fa8c16; }
            }
            
            .stat-label {
                font-size: 14px;
                color: #999;
                margin-top: 5px;
            }
        }
    }
}

.runner-info {
    display: flex;
    align-items: center;
    
    .info {
        margin-left: 10px;
        
        .name {
            font-weight: bold;
        }
        
        .mobile {
            font-size: 12px;
            color: #999;
        }
    }
}

.text-price {
    color: #ff6b00;
    font-weight: bold;
}

.el-pagination {
    margin-top: 20px;
    justify-content: flex-end;
}
</style>
