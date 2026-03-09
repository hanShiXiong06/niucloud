<template>
    <div class="credit-list-container">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-row">
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.total || 0 }}</div>
                    <div class="stat-label">总用户数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card warning">
                    <div class="stat-value">{{ stat.restricted || 0 }}</div>
                    <div class="stat-label">受限用户</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card danger">
                    <div class="stat-value">{{ stat.low_score || 0 }}</div>
                    <div class="stat-label">低分用户(&lt;60)</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card success">
                    <div class="stat-value">{{ stat.high_score || 0 }}</div>
                    <div class="stat-label">高分用户(≥90)</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="用户ID">
                    <el-input v-model="searchForm.member_id" placeholder="请输入用户ID" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.is_restricted" placeholder="全部状态" clearable>
                        <el-option label="正常" :value="0" />
                        <el-option label="受限" :value="1" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column label="用户信息" min-width="200">
                    <template #default="{ row }">
                        <div class="user-info">
                            <el-avatar :src="row.headimg" :size="40" />
                            <div class="info">
                                <div class="name">{{ row.nickname || '用户' + row.member_id }}</div>
                                <div class="mobile">{{ row.mobile || '-' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="credit_score" label="信誉分" width="120">
                    <template #default="{ row }">
                        <el-tag :type="getScoreType(row.credit_score)" size="large">
                            {{ row.credit_score }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="total_complete" label="完成订单" width="100" />
                <el-table-column prop="total_cancel" label="取消订单" width="100" />
                <el-table-column prop="total_complaint" label="投诉成立" width="100" />
                <el-table-column prop="is_restricted" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_restricted ? 'danger' : 'success'" size="small">
                            {{ row.is_restricted ? '受限' : '正常' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="update_time" label="更新时间" width="180">
                    <template #default="{ row }">
                        {{ row.update_time || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewLog(row)">变动记录</el-button>
                        <el-button type="warning" link size="small" @click="showAdjust(row)">调整分数</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <!-- 变动记录弹窗 -->
        <el-dialog v-model="logVisible" title="信誉分变动记录" width="800px">
            <el-table :data="logList" v-loading="logLoading" stripe max-height="400">
                <el-table-column prop="type" label="类型" width="120">
                    <template #default="{ row }">
                        <el-tag :type="getLogType(row.type)" size="small">
                            {{ typeMap[row.type] || row.type }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="change_score" label="变动分数" width="100">
                    <template #default="{ row }">
                        <span :class="row.change_score > 0 ? 'text-success' : 'text-danger'">
                            {{ row.change_score > 0 ? '+' : '' }}{{ row.change_score }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="before_score" label="变动前" width="80" />
                <el-table-column prop="after_score" label="变动后" width="80" />
                <el-table-column prop="remark" label="备注" min-width="150" />
                <el-table-column prop="create_time" label="时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>

        <!-- 调整分数弹窗 -->
        <el-dialog v-model="adjustVisible" title="调整信誉分" width="500px">
            <el-form :model="adjustForm" label-width="100px">
                <el-form-item label="当前分数">
                    <el-tag :type="getScoreType(adjustForm.current_score)" size="large">
                        {{ adjustForm.current_score }}
                    </el-tag>
                </el-form-item>
                <el-form-item label="调整分数">
                    <el-input-number v-model="adjustForm.score" :min="-100" :max="100" />
                    <span class="ml-2 text-muted">正数加分，负数扣分</span>
                </el-form-item>
                <el-form-item label="调整原因">
                    <el-input v-model="adjustForm.remark" type="textarea" rows="3" placeholder="请输入调整原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="adjustVisible = false">取消</el-button>
                <el-button type="primary" @click="handleAdjust" :loading="adjustLoading">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getCreditList, getCreditLogList, adjustCredit, getCreditStat } from '@/addon/sd_xiaoyuan/api/credit'

const loading = ref(false)
const list = ref<any[]>([])
const stat = ref<any>({})
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    member_id: '',
    is_restricted: ''
})

const typeMap: Record<string, string> = {
    'COMPLETE': '订单完成',
    'CANCEL': '取消订单',
    'COMPLAINT': '投诉成立',
    'ADMIN': '管理员调整'
}

// 变动记录
const logVisible = ref(false)
const logLoading = ref(false)
const logList = ref<any[]>([])
const currentMemberId = ref(0)

// 调整分数
const adjustVisible = ref(false)
const adjustLoading = ref(false)
const adjustForm = reactive({
    member_id: 0,
    current_score: 0,
    score: 0,
    remark: ''
})

onMounted(() => {
    loadStat()
    loadList()
})

const loadStat = async () => {
    try {
        const res: any = await getCreditStat()
        stat.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getCreditList({
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        })
        list.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    searchForm.member_id = ''
    searchForm.is_restricted = ''
    pagination.page = 1
    loadList()
}

const getScoreType = (score: number) => {
    if (score >= 90) return 'success'
    if (score >= 60) return 'warning'
    return 'danger'
}

const getLogType = (type: string) => {
    const map: Record<string, string> = {
        'COMPLETE': 'success',
        'CANCEL': 'warning',
        'COMPLAINT': 'danger',
        'ADMIN': 'info'
    }
    return map[type] || 'info'
}


const viewLog = async (row: any) => {
    currentMemberId.value = row.member_id
    logVisible.value = true
    logLoading.value = true
    try {
        const res: any = await getCreditLogList({
            member_id: row.member_id,
            page: 1,
            limit: 50
        })
        logList.value = res.data?.data || []
    } catch (e) {
        console.error(e)
    } finally {
        logLoading.value = false
    }
}

const showAdjust = (row: any) => {
    adjustForm.member_id = row.member_id
    adjustForm.current_score = row.credit_score
    adjustForm.score = 0
    adjustForm.remark = ''
    adjustVisible.value = true
}

const handleAdjust = async () => {
    if (adjustForm.score === 0) {
        ElMessage.warning('调整分数不能为0')
        return
    }
    if (!adjustForm.remark) {
        ElMessage.warning('请输入调整原因')
        return
    }
    
    adjustLoading.value = true
    try {
        await adjustCredit({
            member_id: adjustForm.member_id,
            score: adjustForm.score,
            remark: adjustForm.remark
        })
        ElMessage.success('调整成功')
        adjustVisible.value = false
        loadList()
        loadStat()
    } catch (e: any) {
        ElMessage.error(e.message || '调整失败')
    } finally {
        adjustLoading.value = false
    }
}
</script>

<style lang="scss" scoped>
.credit-list-container {
    padding: 20px;
}

.stat-row {
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    padding: 20px;
    
    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #409eff;
    }
    
    .stat-label {
        font-size: 14px;
        color: #909399;
        margin-top: 10px;
    }
    
    &.warning .stat-value {
        color: #e6a23c;
    }
    
    &.danger .stat-value {
        color: #f56c6c;
    }
    
    &.success .stat-value {
        color: #67c23a;
    }
}

.search-card {
    margin-bottom: 20px;
}

.text-success {
    color: #67c23a;
    font-weight: bold;
}

.text-danger {
    color: #f56c6c;
    font-weight: bold;
}

.text-muted {
    color: #909399;
    font-size: 12px;
}

.ml-2 {
    margin-left: 10px;
}

.user-info {
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
</style>
