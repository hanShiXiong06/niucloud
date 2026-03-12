<template>
    <div class="game-companion-manage">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="6"><el-card shadow="never"><el-statistic title="总数" :value="stat.total" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><el-statistic title="待审核" :value="stat.pending" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><el-statistic title="上架中" :value="stat.online" /></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><el-statistic title="已下架" :value="stat.offline" /></el-card></el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" style="margin-bottom: 16px;">
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable style="width: 140px;">
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="游戏类型">
                    <el-select v-model="searchForm.game_type" placeholder="全部" clearable style="width: 140px;">
                        <el-option v-for="item in gameTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="服务类型">
                    <el-select v-model="searchForm.service_type" placeholder="全部" clearable style="width: 120px;">
                        <el-option v-for="item in serviceTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;">
                        <el-option label="待审核" :value="0" />
                        <el-option label="上架中" :value="1" />
                        <el-option label="已下架" :value="2" />
                        <el-option label="已拒绝" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="标题/昵称/游戏ID" clearable style="width: 180px;" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column label="用户" width="160">
                    <template #default="{ row }">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <el-avatar :src="img(row.avatar)" :size="32" />
                            <span>{{ row.nickname || '-' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="游戏" width="110">
                    <template #default="{ row }">
                        <el-tag size="small" type="primary">{{ getGameName(row.game_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="服务" width="80">
                    <template #default="{ row }">
                        <el-tag size="small" type="success">{{ getServiceName(row.service_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
                <el-table-column prop="rank_level" label="段位" width="100" />
                <el-table-column label="价格" width="100">
                    <template #default="{ row }">
                        <span style="color:#ff6b00;font-weight:bold;">¥{{ row.price }}/{{ row.unit || '小时' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="view_count" label="浏览" width="70" />
                <el-table-column prop="order_count" label="单量" width="70" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusName(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="置顶" width="70">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_top === 1" @change="(val: boolean) => handleSetTop(row, val)" size="small" />
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="success" link size="small" v-if="row.status === 0" @click="handleAudit(row, 1)">通过</el-button>
                        <el-button type="danger" link size="small" v-if="row.status === 0" @click="handleReject(row)">拒绝</el-button>
                        <el-button type="warning" link size="small" v-if="row.status === 1" @click="handleAudit(row, 2)">下架</el-button>
                        <el-button type="success" link size="small" v-if="row.status === 2" @click="handleAudit(row, 1)">上架</el-button>
                        <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                <el-pagination
                    v-model:current-page="searchForm.page"
                    v-model:page-size="searchForm.limit"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 拒绝弹窗 -->
        <el-dialog v-model="rejectVisible" title="拒绝原因" width="400px">
            <el-input v-model="rejectReason" type="textarea" :rows="3" placeholder="请输入拒绝原因" />
            <template #footer>
                <el-button @click="rejectVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmReject">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getGameCompanionList, auditGameCompanion, setTopGameCompanion,
    deleteGameCompanion, getGameCompanionStat, getAllSchools
} from '@/addon/sd_xiaoyuan/api/admin'
import { img } from '@/utils/common'

const list = ref<any[]>([])
const total = ref(0)
const loading = ref(false)
const stat = ref({ total: 0, pending: 0, online: 0, offline: 0 })

const searchForm = ref({
    school_id: '', game_type: '', service_type: '', status: '' as string | number,
    keyword: '', page: 1, limit: 10
})

const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}

const gameTypeOptions = [
    { value: 'WZRY', label: '王者荣耀' }, { value: 'LOL', label: '英雄联盟' },
    { value: 'PUBG', label: '和平精英' }, { value: 'CSGO', label: 'CS2' },
    { value: 'YS', label: '原神' }, { value: 'EGG', label: '蛋仔派对' },
    { value: 'OTHER', label: '其他' }
]
const serviceTypeOptions = [
    { value: 'PLAY_WITH', label: '陪玩' }, { value: 'BOOST', label: '代练' },
    { value: 'TEACH', label: '教学' }, { value: 'TEAM', label: '组队' }
]

const gameMap: Record<string, string> = { WZRY: '王者荣耀', LOL: '英雄联盟', PUBG: '和平精英', CSGO: 'CS2', YS: '原神', EGG: '蛋仔派对', OTHER: '其他' }
const serviceMap: Record<string, string> = { PLAY_WITH: '陪玩', BOOST: '代练', TEACH: '教学', TEAM: '组队' }
const getGameName = (t: string) => gameMap[t] || t
const getServiceName = (t: string) => serviceMap[t] || t

const statusName = (s: number) => ({ 0: '待审核', 1: '上架中', 2: '已下架', 3: '已拒绝' }[s] || '未知')
const statusType = (s: number) => ({ 0: 'warning', 1: 'success', 2: 'info', 3: 'danger' }[s] || 'info') as any

const rejectVisible = ref(false)
const rejectReason = ref('')
let rejectRow: any = null

onMounted(() => { loadSchools(); loadList(); loadStat() })

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getGameCompanionList(searchForm.value)
        if (res.code === 1) {
            list.value = res.data?.list || []
            total.value = res.data?.count || 0
        }
    } catch (e) { console.error(e) }
    finally { loading.value = false }
}

const loadStat = async () => {
    try {
        const res: any = await getGameCompanionStat()
        if (res.code === 1) stat.value = res.data
    } catch (e) { console.error(e) }
}

const resetSearch = () => {
    searchForm.value = { school_id: '', game_type: '', service_type: '', status: '', keyword: '', page: 1, limit: 10 }
    loadList()
}

const handleAudit = async (row: any, status: number) => {
    try {
        const res: any = await auditGameCompanion({ id: row.id, status })
        if (res.code === 1) { ElMessage.success('操作成功'); loadList(); loadStat() }
        else ElMessage.error(res.msg || '操作失败')
    } catch (e) { ElMessage.error('操作失败') }
}

const handleReject = (row: any) => {
    rejectRow = row
    rejectReason.value = ''
    rejectVisible.value = true
}

const confirmReject = async () => {
    if (!rejectRow) return
    try {
        const res: any = await auditGameCompanion({ id: rejectRow.id, status: 3, refuse_reason: rejectReason.value })
        if (res.code === 1) { ElMessage.success('已拒绝'); rejectVisible.value = false; loadList(); loadStat() }
        else ElMessage.error(res.msg || '操作失败')
    } catch (e) { ElMessage.error('操作失败') }
}

const handleSetTop = async (row: any, val: boolean) => {
    try {
        const res: any = await setTopGameCompanion({ id: row.id, is_top: val ? 1 : 0 })
        if (res.code === 1) { ElMessage.success('操作成功'); loadList() }
        else ElMessage.error(res.msg || '操作失败')
    } catch (e) { ElMessage.error('操作失败') }
}

const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定删除该陪玩信息？', '确认', { type: 'warning' }).then(async () => {
        try {
            const res: any = await deleteGameCompanion({ id: row.id })
            if (res.code === 1) { ElMessage.success('删除成功'); loadList(); loadStat() }
            else ElMessage.error(res.msg || '删除失败')
        } catch (e) { ElMessage.error('删除失败') }
    }).catch(() => {})
}
</script>

<style scoped>
.stat-row { margin-bottom: 16px; }
</style>
