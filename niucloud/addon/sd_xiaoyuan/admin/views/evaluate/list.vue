<template>
    <div class="evaluate-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="接单员姓名">
                    <el-input v-model="searchForm.runner_name" placeholder="请输入接单员姓名" clearable />
                </el-form-item>
                <el-form-item label="评分">
                    <el-select v-model="searchForm.score" placeholder="全部评分" clearable>
                        <el-option label="5分" :value="5" />
                        <el-option label="4分" :value="4" />
                        <el-option label="3分" :value="3" />
                        <el-option label="2分" :value="2" />
                        <el-option label="1分" :value="1" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 评价列表 -->
        <el-card>
            <el-table :data="evaluateList" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                                <el-table-column prop="runner_name" label="接单员" width="120" />
                <el-table-column prop="score" label="综合评分" width="150">
                    <template #default="{ row }">
                        <el-rate v-model="row.score" disabled :max="5" />
                    </template>
                </el-table-column>
                <el-table-column prop="service_score" label="服务评分" width="100" />
                <el-table-column prop="speed_score" label="速度评分" width="100" />
                <el-table-column prop="content" label="评价内容" min-width="200">
                    <template #default="{ row }">
                        {{ row.content || '用户未填写评价' }}
                    </template>
                </el-table-column>
                <el-table-column prop="is_anonymous" label="匿名" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_anonymous ? 'info' : 'success'" size="small">
                            {{ row.is_anonymous ? '是' : '否' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="评价时间" width="160" />
                <el-table-column label="操作" width="80" fixed="right">
                    <template #default="{ row }">
                        <el-button type="danger" link size="small" @click="deleteEvaluate(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadEvaluates"
                @current-change="loadEvaluates"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="评价详情" width="600px">
            <el-descriptions :column="2" border v-if="currentEvaluate">
                <el-descriptions-item label="订单号">{{ currentEvaluate.order_no }}</el-descriptions-item>
                <el-descriptions-item label="接单员">{{ currentEvaluate.runner_name }}</el-descriptions-item>
                <el-descriptions-item label="综合评分">
                    <el-rate v-model="currentEvaluate.score" disabled />
                </el-descriptions-item>
                <el-descriptions-item label="服务评分">{{ currentEvaluate.service_score }}分</el-descriptions-item>
                <el-descriptions-item label="速度评分">{{ currentEvaluate.speed_score }}分</el-descriptions-item>
                <el-descriptions-item label="匿名评价">{{ currentEvaluate.is_anonymous ? '是' : '否' }}</el-descriptions-item>
                <el-descriptions-item label="评价内容" :span="2">{{ currentEvaluate.content || '用户未填写评价' }}</el-descriptions-item>
                <el-descriptions-item label="评价图片" :span="2" v-if="currentEvaluate.images">
                    <div class="image-list">
                        <el-image 
                            v-for="(img, index) in parseImages(currentEvaluate.images)" 
                            :key="index"
                            :src="img(img)" 
                            style="width: 100px; height: 100px; margin-right: 10px;"
                            :preview-src-list="parseImages(currentEvaluate.images).map(item => img(item))"
                        />
                    </div>
                </el-descriptions-item>
                <el-descriptions-item label="评价时间">{{ currentEvaluate.create_time || '-' }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getEvaluateList, deleteEvaluate as deleteEvaluateApi } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const evaluateList = ref<any[]>([])
const detailVisible = ref(false)
const currentEvaluate = ref<any>(null)

const formatTime = (time: number | string) => {
    if (!time) return ''
    const date = typeof time === 'number' ? new Date(time * 1000) : new Date(time)
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const min = String(date.getMinutes()).padStart(2, '0')
    const s = String(date.getSeconds()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${min}:${s}`
}

const searchForm = ref({
    runner_name: '',
    score: ''
})

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

onMounted(() => {
    loadEvaluates()
})

const loadEvaluates = async () => {
    loading.value = true
    try {
        const res = await getEvaluateList({
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        })
        if (res.code === 1) {
            evaluateList.value = res.data.list
            pagination.value.total = res.data.count
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadEvaluates()
}

const handleReset = () => {
    searchForm.value = { runner_name: '', score: '' }
    handleSearch()
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
    currentEvaluate.value = row
    detailVisible.value = true
}

const deleteEvaluate = (row: any) => {
    ElMessageBox.confirm('确定要删除该评价吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            const res = await deleteEvaluateApi({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('删除成功')
                loadEvaluates()
            } else {
                ElMessage.error(res.msg || '删除失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}
</script>

<style scoped lang="scss">
.evaluate-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
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
