<template>
    <div class="comment-manage">
        <el-tabs v-model="activeTab" @tab-change="handleTabChange">
            <el-tab-pane label="社区评论" name="community" />
            <el-tab-pane label="表白墙评论" name="confession" />
        </el-tabs>

        <el-card>
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="评论内容" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
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

            <el-table :data="tableData" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="content" label="评论内容" min-width="200" show-overflow-tooltip />
                <el-table-column prop="member_id" label="用户ID" width="100" />
                <el-table-column label="匿名" width="80" v-if="activeTab === 'confession'">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_anonymous" size="small">匿名</el-tag>
                        <span v-else>否</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 0" type="warning">待审核</el-tag>
                        <el-tag v-else-if="row.status === 1" type="success">已通过</el-tag>
                        <el-tag v-else type="danger">已拒绝</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="handleAudit(row, 1)" v-if="row.status === 0">通过</el-button>
                        <el-button size="small" type="danger" @click="handleAudit(row, 2)" v-if="row.status === 0">拒绝</el-button>
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
import { getCommunityCommentList, getConfessionCommentList, auditComment, deleteComment } from '../../api/admin'

const activeTab = ref('community')
const searchForm = ref({ keyword: '', status: '' })
const tableData = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

onMounted(() => loadData())

const handleTabChange = () => {
    page.value = 1
    loadData()
}

const loadData = async () => {
    loading.value = true
    try {
        const api = activeTab.value === 'community' ? getCommunityCommentList : getConfessionCommentList
        const res: any = await api({ ...searchForm.value, page: page.value, limit: limit.value })
        if (res.code === 1) {
            tableData.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const handleSearch = () => { page.value = 1; loadData() }
const handleReset = () => { searchForm.value = { keyword: '', status: '' }; handleSearch() }

const handleAudit = async (row: any, status: number) => {
    await ElMessageBox.confirm(`确定${status === 1 ? '通过' : '拒绝'}该评论？`, '提示')
    const res: any = await auditComment({ id: row.id, type: activeTab.value, status })
    if (res.code === 1) {
        ElMessage.success('操作成功')
        loadData()
    }
}

const handleDelete = async (row: any) => {
    await ElMessageBox.confirm('确定删除该评论？', '提示')
    const res: any = await deleteComment({ id: row.id, type: activeTab.value })
    if (res.code === 1) {
        ElMessage.success('删除成功')
        loadData()
    }
}
</script>

<style scoped>
</style>
