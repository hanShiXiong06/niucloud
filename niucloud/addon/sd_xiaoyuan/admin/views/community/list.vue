<template>
    <div class="community-list">
        <el-card class="search-card">
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="标题/内容" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
                        <el-option label="待审核" :value="0" />
                        <el-option label="已发布" :value="1" />
                        <el-option label="已下架" :value="2" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card class="table-card">
            <el-table :data="tableData" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column prop="title" label="标题" min-width="150" />
                <el-table-column prop="content" label="内容" min-width="200" show-overflow-tooltip />
                <el-table-column prop="view_count" label="浏览" width="80" />
                <el-table-column prop="like_count" label="点赞" width="80" />
                <el-table-column prop="comment_count" label="评论" width="80" />
                <el-table-column label="置顶" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_top" type="warning">置顶</el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 0" type="warning">待审核</el-tag>
                        <el-tag v-else-if="row.status === 1" type="success">已发布</el-tag>
                        <el-tag v-else type="danger">已下架</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="handleAudit(row, 1)" v-if="row.status === 0">通过</el-button>
                        <el-button size="small" type="danger" @click="handleAudit(row, 2)" v-if="row.status === 0">拒绝</el-button>
                        <el-button size="small" @click="handleTop(row)" v-if="row.status === 1">{{ row.is_top ? '取消置顶' : '置顶' }}</el-button>
                        <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadData"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCommunityList, auditCommunity, setTopCommunity, deleteCommunity, getAllSchools } from '../../api/admin'

const searchForm = ref({ school_id: '', keyword: '', status: '' })
const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}
const tableData = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

onMounted(() => {
    loadSchools()
    loadData()
})

const loadData = async () => {
    loading.value = true
    try {
        const res: any = await getCommunityList({ ...searchForm.value, page: page.value, limit: limit.value })
        if (res.code === 1) {
            tableData.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const handleSearch = () => { page.value = 1; loadData() }
const handleReset = () => { searchForm.value = { school_id: '', keyword: '', status: '' }; handleSearch() }

const handleAudit = async (row: any, status: number) => {
    await ElMessageBox.confirm(`确定${status === 1 ? '通过' : '拒绝'}该帖子？`, '提示')
    const res: any = await auditCommunity({ id: row.id, status })
    if (res.code === 1) {
        ElMessage.success('操作成功')
        loadData()
    }
}

const handleTop = async (row: any) => {
    const res: any = await setTopCommunity({ id: row.id, is_top: row.is_top ? 0 : 1 })
    if (res.code === 1) {
        ElMessage.success('操作成功')
        loadData()
    }
}

const handleDelete = async (row: any) => {
    await ElMessageBox.confirm('确定删除该帖子？', '提示')
    const res: any = await deleteCommunity({ id: row.id })
    if (res.code === 1) {
        ElMessage.success('删除成功')
        loadData()
    }
}
</script>

<style scoped>
.search-card { margin-bottom: 16px; }
</style>
