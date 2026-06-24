<template>
    <div class="lost-found-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable @change="runSearch">
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="searchForm.type" placeholder="全部类型" clearable @change="runSearch">
                        <el-option label="我丢了" value="LOST" />
                        <el-option label="我捡到了" value="FOUND" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="searchForm.category" placeholder="全部分类" clearable @change="runSearch">
                        <el-option v-for="(name, key) in categoryList" :key="key" :label="name" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable @change="runSearch">
                        <el-option label="待审核" :value="0" />
                        <el-option label="进行中" :value="1" />
                        <el-option label="已找到/已归还" :value="2" />
                        <el-option label="已关闭" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="请输入关键词" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="runSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'LOST' ? 'danger' : 'success'" size="small">
                            {{ row.type === 'LOST' ? '我丢了' : '我捡到了' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="category" label="分类" width="100" />
                <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                <el-table-column prop="lost_address" label="地点" width="150" show-overflow-tooltip />
                <el-table-column prop="reward" label="跑腿费" width="100">
                    <template #default="{ row }">
                        <span v-if="row.type === 'LOST' && row.reward > 0" class="text-price">¥{{ row.reward }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="view_count" label="浏览" width="80" />
                <el-table-column prop="status" label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ statusMap[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="发布时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button link size="small" @click="handleAudit(row, 1)" v-if="row.status === 0">通过</el-button>
                        <el-button type="danger" link size="small" @click="handleAudit(row, 2)" v-if="row.status === 0">拒绝</el-button>
                        <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="onPageSizeChange"
                @current-change="loadList"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="失物招领详情" width="700px">
            <el-descriptions :column="2" border v-if="currentItem">
                <el-descriptions-item label="类型">
                    <el-tag :type="currentItem.type === 'LOST' ? 'danger' : 'success'">
                        {{ currentItem.type === 'LOST' ? '我丢了' : '我捡到了' }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="分类">{{ currentItem.category }}</el-descriptions-item>
                <el-descriptions-item label="标题" :span="2">{{ currentItem.title }}</el-descriptions-item>
                <el-descriptions-item label="丢失/捡到时间">{{ currentItem.lost_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="地点">{{ currentItem.lost_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="联系人">{{ currentItem.contact_name }}</el-descriptions-item>
                <el-descriptions-item label="联系电话">{{ currentItem.contact_mobile }}</el-descriptions-item>
                <el-descriptions-item label="跑腿费" v-if="currentItem.type === 'LOST'">
                    <span class="text-price">¥{{ currentItem.reward }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="浏览量">{{ currentItem.view_count }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentItem.status)">{{ statusMap[currentItem.status] }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="发布时间">{{ currentItem.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="详细描述" :span="2">{{ currentItem.content || '-' }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getLostFoundList, getLostFoundInfo, getLostFoundCategoryList } from '@/addon/sd_xiaoyuan/api/lostFound'
import { getAllSchools, auditLostFound, deleteLostFound } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const list = ref<any[]>([])
const categoryList = ref<Record<string, string>>({})
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    school_id: '',
    type: '',
    category: '',
    status: '',
    keyword: ''
})

const schoolList = ref<any[]>([])
let listFetchSeq = 0

const buildListParams = () => {
    const q: Record<string, any> = {
        page: pagination.page,
        limit: pagination.limit
    }
    if (searchForm.school_id !== '' && searchForm.school_id != null) q.school_id = searchForm.school_id
    if (searchForm.type) q.type = searchForm.type
    if (searchForm.category) q.category = searchForm.category
    if (searchForm.status !== '' && searchForm.status != null) q.status = searchForm.status
    const kw = String(searchForm.keyword || '').trim()
    if (kw) q.keyword = kw
    return q
}

const runSearch = () => {
    pagination.page = 1
    loadList()
}

const onPageSizeChange = () => {
    pagination.page = 1
    loadList()
}

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '进行中',
    2: '已找到/已归还',
    3: '已关闭'
}

const detailVisible = ref(false)
const currentItem = ref<any>(null)

onMounted(() => {
    loadSchools()
    loadCategoryList()
    loadList()
})

const loadCategoryList = async () => {
    try {
        const res: any = await getLostFoundCategoryList()
        categoryList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadList = async () => {
    const seq = ++listFetchSeq
    loading.value = true
    try {
        const res: any = await getLostFoundList(buildListParams())
        if (seq !== listFetchSeq) return
        list.value = res.data?.list || res.data?.data || []
        pagination.total = res.data?.count ?? res.data?.total ?? 0
    } catch (e) {
        console.error(e)
    } finally {
        if (seq === listFetchSeq) loading.value = false
    }
}

const handleReset = () => {
    searchForm.school_id = ''
    searchForm.type = ''
    searchForm.category = ''
    searchForm.status = ''
    searchForm.keyword = ''
    pagination.page = 1
    loadList()
}

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'  // 待审核
    if (status === 1) return 'primary'  // 进行中
    if (status === 2) return 'success'  // 已找到/已归还
    if (status === 3) return 'info'     // 已关闭
    return 'info'
}


const viewDetail = async (row: any) => {
    try {
        const res: any = await getLostFoundInfo(row.id)
        currentItem.value = res.data
        detailVisible.value = true
    } catch (e) {
        console.error(e)
    }
}

const handleAudit = async (row: any, status: number) => {
    const action = status === 1 ? '通过' : '拒绝'
    let refuseReason = ''
    
    if (status === 2) {
        // 拒绝时需要输入原因
        const result = await ElMessageBox.prompt('请输入拒绝原因', '拒绝审核', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            inputPattern: /.+/,
            inputErrorMessage: '请输入拒绝原因'
        }).catch(() => null)
        
        if (!result) return
        refuseReason = result.value
    } else {
        // 通过审核确认
        await ElMessageBox.confirm(`确定${action}该信息吗？`, '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).catch(() => null)
    }
    
    try {
        const res: any = await auditLostFound({ 
            id: row.id, 
            status,
            refuse_reason: refuseReason 
        })
        if (res.code === 1) {
            ElMessage.success(`${action}成功`)
            loadList()
        } else {
            ElMessage.error(res.msg || `${action}失败`)
        }
    } catch (e: any) {
        ElMessage.error(e.message || `${action}失败`)
    }
}

const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该信息吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            const res: any = await deleteLostFound({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('删除成功')
                loadList()
            } else {
                ElMessage.error(res.msg || '删除失败')
            }
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}
</script>

<style lang="scss" scoped>
.lost-found-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.text-price {
    color: #f56c6c;
    font-weight: bold;
}
</style>
