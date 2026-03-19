<template>
    <div class="task-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="任务类型">
                    <el-select v-model="searchForm.task_type" placeholder="全部类型" clearable>
                        <el-option v-for="(name, key) in typeList" :key="key" :label="name" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option v-for="(name, key) in statusList" :key="key" :label="name" :value="Number(key)" />
                    </el-select>
                </el-form-item>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable>
                        <el-option v-for="school in schoolList" :key="school.id" :label="school.name" :value="school.id" />
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
                <el-table-column prop="task_no" label="任务编号" width="180" />
                <el-table-column prop="task_type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ typeList[row.task_type] || row.task_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                <el-table-column prop="reward" label="悬赏金额" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.reward }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ statusList[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_urgent" label="加急" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_urgent" type="danger" size="small">加急</el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="发布时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
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

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="任务详情" width="700px">
            <el-descriptions :column="2" border v-if="currentTask">
                <el-descriptions-item label="任务编号">{{ currentTask.task_no }}</el-descriptions-item>
                <el-descriptions-item label="任务类型">{{ typeList[currentTask.task_type] }}</el-descriptions-item>
                <el-descriptions-item label="标题" :span="2">{{ currentTask.title }}</el-descriptions-item>
                <el-descriptions-item label="悬赏金额">¥{{ currentTask.reward }}</el-descriptions-item>
                <el-descriptions-item label="小费">¥{{ currentTask.tip || 0 }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentTask.status)">{{ statusList[currentTask.status] }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="是否加急">{{ currentTask.is_urgent ? '是' : '否' }}</el-descriptions-item>
                <el-descriptions-item label="取件地址" :span="2">{{ currentTask.pickup_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="送达地址" :span="2">{{ currentTask.delivery_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="任务描述" :span="2">{{ currentTask.content || '-' }}</el-descriptions-item>
                <el-descriptions-item label="发布时间">{{ currentTask.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="完成时间">{{ currentTask.complete_time || '-' }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { getTaskList, getTaskInfo, getTaskTypeList, getTaskStatusList } from '@/addon/sd_xiaoyuan/api/task'
import { getAllSchools } from '@/addon/sd_xiaoyuan/api/school'

const loading = ref(false)
const list = ref<any[]>([])
const typeList = ref<Record<string, string>>({})
const statusList = ref<Record<number, string>>({})
const schoolList = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    task_type: '',
    status: '',
    school_id: ''
})

const detailVisible = ref(false)
const currentTask = ref<any>(null)

onMounted(() => {
    loadTypeList()
    loadStatusList()
    loadSchoolList()
    loadList()
})

const loadTypeList = async () => {
    try {
        const res: any = await getTaskTypeList()
        typeList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadStatusList = async () => {
    try {
        const res: any = await getTaskStatusList()
        statusList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadSchoolList = async () => {
    try {
        const res: any = await getAllSchools()
        schoolList.value = res.data || []
    } catch (e) {
        console.error(e)
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getTaskList({
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
    searchForm.task_type = ''
    searchForm.status = ''
    searchForm.school_id = ''
    pagination.page = 1
    loadList()
}

const getStatusType = (status: number) => {
    if (status === 50) return 'success'
    if (status >= 90) return 'info'
    if (status >= 20) return 'warning'
    return 'primary'
}


const viewDetail = async (row: any) => {
    try {
        const res: any = await getTaskInfo(row.id)
        currentTask.value = res.data
        detailVisible.value = true
    } catch (e) {
        console.error(e)
    }
}
</script>

<style lang="scss" scoped>
.task-list-container {
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
