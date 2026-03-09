<template>
    <div class="campus-auth-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="姓名/学号/校区" clearable />
                </el-form-item>
                <el-form-item label="身份类型">
                    <el-select v-model="searchForm.identity_type" placeholder="全部类型" clearable>
                        <el-option label="学生" value="STUDENT" />
                        <el-option label="教师" value="TEACHER" />
                        <el-option label="教职工" value="STAFF" />
                    </el-select>
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
                    <div class="stat-value">{{ stat.total || 0 }}</div>
                    <div class="stat-label">认证总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value pending">{{ stat.pending || 0 }}</div>
                    <div class="stat-label">待审核</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value success">{{ stat.passed || 0 }}</div>
                    <div class="stat-label">已通过</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value danger">{{ stat.rejected || 0 }}</div>
                    <div class="stat-label">已拒绝</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 认证列表 -->
        <el-card>
            <el-table :data="authList" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="关联用户" min-width="160">
                    <template #default="{ row }">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <el-avatar :size="32" :src="row.member_headimg" v-if="row.member_headimg" />
                            <el-avatar :size="32" v-else>{{ (row.member_nickname || '?').charAt(0) }}</el-avatar>
                            <div>
                                <div style="font-weight:500;">{{ row.member_nickname || '-' }}</div>
                                <div style="font-size:12px;color:#999;">{{ row.member_mobile || '-' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="real_name" label="姓名" width="120" />
                <el-table-column prop="identity_type" label="身份类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ getIdentityTypeName(row.identity_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="student_no" label="学号/工号" width="150" />
                <el-table-column prop="school_name" label="学校" min-width="150">
                    <template #default="{ row }">
                        {{ row.school_name || '未选择' }}
                    </template>
                </el-table-column>
                <el-table-column label="院系/部门" width="150">
                    <template #default="{ row }">
                        {{ row.department || row.college || row.major || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="campus" label="校区" width="120">
                    <template #default="{ row }">
                        {{ row.campus_name || row.campus || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusName(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="refuse_reason" label="拒绝原因" min-width="150">
                    <template #default="{ row }">
                        <span v-if="row.status === 2" style="color: #f56c6c;">{{ row.refuse_reason || '-' }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="申请时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button type="success" link size="small" v-if="row.status === 0" @click="auditAuth(row, 1)">通过</el-button>
                        <el-button type="danger" link size="small" v-if="row.status === 0" @click="auditAuth(row, 2)">拒绝</el-button>
                        <el-button type="warning" link size="small" v-if="row.status === 1" @click="cancelAuth(row)">取消认证</el-button>
                        <el-tag v-if="row.status === 1" type="success" size="small">已通过</el-tag>
                        <el-tag v-if="row.status === 2" type="danger" size="small">已拒绝</el-tag>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadAuthList"
                @current-change="loadAuthList"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="认证详情" width="700px">
            <el-descriptions :column="2" border v-if="currentAuth">
                <el-descriptions-item label="姓名">{{ currentAuth.real_name }}</el-descriptions-item>
                <el-descriptions-item label="身份类型">{{ getIdentityTypeName(currentAuth.identity_type) }}</el-descriptions-item>
                <el-descriptions-item label="学号/工号">{{ currentAuth.student_no }}</el-descriptions-item>
                <el-descriptions-item label="学校">{{ currentAuth.school_name }}</el-descriptions-item>
                <el-descriptions-item label="院系/部门">{{ currentAuth.department }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentAuth.status)">{{ getStatusName(currentAuth.status) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="证件照片" :span="2">
                    <el-image :src="currentAuth.cert_image" style="width: 200px;" :preview-src-list="[currentAuth.cert_image]" />
                </el-descriptions-item>
                <el-descriptions-item label="拒绝原因" :span="2" v-if="currentAuth.refuse_reason">{{ currentAuth.refuse_reason }}</el-descriptions-item>
                <el-descriptions-item label="申请时间">{{ (currentAuth.create_time) }}</el-descriptions-item>
                <el-descriptions-item label="审核时间" v-if="currentAuth.audit_time">{{ (currentAuth.audit_time) }}</el-descriptions-item>
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
import { getCampusAuthList, getCampusAuthStat, auditCampusAuth, cancelCampusAuth } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const authList = ref<any[]>([])
const stat = ref<any>({})
const detailVisible = ref(false)
const refuseVisible = ref(false)
const currentAuth = ref<any>(null)
const refuseReason = ref('')

const searchForm = ref({
    keyword: '',
    identity_type: '',
    status: ''
})

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

const identityTypeMap: Record<string, string> = {
    'STUDENT': '学生',
    'TEACHER': '教师',
    'STAFF': '教职工'
}

onMounted(() => {
    loadAuthList()
    loadStat()
})

const loadAuthList = async () => {
    loading.value = true
    try {
        const res = await getCampusAuthList({
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        })
        if (res.code === 1) {
            authList.value = res.data.list
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
        const res = await getCampusAuthStat()
        if (res.code === 1) {
            stat.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadAuthList()
}

const handleReset = () => {
    searchForm.value = { keyword: '', identity_type: '', status: '' }
    handleSearch()
}

const getStatusName = (status: number) => statusMap[status] || '未知'
const getIdentityTypeName = (type: string) => identityTypeMap[type] || type

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'
    if (status === 1) return 'success'
    return 'danger'
}


const viewDetail = (row: any) => {
    currentAuth.value = row
    detailVisible.value = true
}

const auditAuth = async (row: any, status: number) => {
    if (status === 2) {
        currentAuth.value = row
        refuseReason.value = ''
        refuseVisible.value = true
        return
    }
    
    try {
        const res = await auditCampusAuth({ id: row.id, status, refuse_reason: '' })
        if (res.code === 1) {
            ElMessage.success('审核成功')
            loadAuthList()
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
        const res = await auditCampusAuth({ id: currentAuth.value.id, status: 2, refuse_reason: refuseReason.value })
        if (res.code === 1) {
            ElMessage.success('操作成功')
            refuseVisible.value = false
            loadAuthList()
            loadStat()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const cancelAuth = async (row: any) => {
    try {
        await ElMessageBox.confirm('确定要取消该用户的认证吗？取消后将变为待审核状态。', '确认取消', {
            type: 'warning'
        })
        
        const res = await cancelCampusAuth(row.id)
        if (res.code === 1) {
            ElMessage.success('取消认证成功')
            loadAuthList()
            loadStat()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e: any) {
        if (e !== 'cancel') {
            ElMessage.error('操作失败')
        }
    }
}
</script>

<style scoped lang="scss">
.campus-auth-list-container {
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
</style>
