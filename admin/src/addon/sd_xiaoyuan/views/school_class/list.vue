<template>
    <div class="school-class-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="请选择学校" clearable @change="loadList">
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="年级">
                    <el-input v-model="searchForm.grade" placeholder="如2024" clearable />
                </el-form-item>
                <el-form-item label="班级名称">
                    <el-input v-model="searchForm.name" placeholder="请输入班级名称" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="启用" :value="1" />
                        <el-option label="禁用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="showAdd">添加班级</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="grade" label="年级" width="100" />
                <el-table-column prop="name" label="班级名称" min-width="180" />
                <el-table-column prop="code" label="班级代码" width="140" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" />
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="创建时间" width="170">
                    <template #default="{ row }">
                        {{ row.create_time ? new Date(row.create_time * 1000).toLocaleString() : '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="goSchedule(row)">课表</el-button>
                        <el-button type="primary" link size="small" @click="showEdit(row)">编辑</el-button>
                        <el-button type="danger" link size="small" @click="handleDel(row)">删除</el-button>
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

        <!-- 添加/编辑弹窗 -->
        <el-dialog v-model="formVisible" :title="formData.id ? '编辑班级' : '添加班级'" width="500px">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="学校" required>
                    <el-select v-model="formData.school_id" placeholder="请选择学校">
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="年级" required>
                    <el-input v-model="formData.grade" placeholder="如2024" />
                </el-form-item>
                <el-form-item label="班级名称" required>
                    <el-input v-model="formData.name" placeholder="请输入班级名称" />
                </el-form-item>
                <el-form-item label="班级代码">
                    <el-input v-model="formData.code" placeholder="班级代码(可选)" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSchoolClassList, addSchoolClass, editSchoolClass, delSchoolClass, setSchoolClassStatus } from '@/addon/sd_xiaoyuan/api/schoolClass'
import { getAllSchools } from '@/addon/sd_xiaoyuan/api/school'
import { useRouter } from 'vue-router'

const router = useRouter()
const loading = ref(false)
const list = ref<any[]>([])
const schoolList = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const searchForm = reactive({
    school_id: '',
    grade: '',
    name: '',
    status: ''
})

const formVisible = ref(false)
const submitLoading = ref(false)
const formData = reactive({
    id: 0,
    school_id: 0,
    department_id: 0,
    major_id: 0,
    name: '',
    code: '',
    grade: '',
    sort: 0,
    status: 1
})

onMounted(() => {
    loadSchools()
    loadList()
})

const loadSchools = async () => {
    try {
        const res: any = await getAllSchools()
        schoolList.value = res.data || []
    } catch (e) { console.error(e) }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getSchoolClassList({
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
    searchForm.school_id = ''
    searchForm.grade = ''
    searchForm.name = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const showAdd = () => {
    formData.id = 0
    formData.school_id = 0
    formData.name = ''
    formData.code = ''
    formData.grade = ''
    formData.sort = 0
    formData.status = 1
    formVisible.value = true
}

const showEdit = (row: any) => {
    formData.id = row.id
    formData.school_id = row.school_id || 0
    formData.name = row.name
    formData.code = row.code || ''
    formData.grade = row.grade || ''
    formData.sort = row.sort || 0
    formData.status = row.status
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.name) { ElMessage.warning('请输入班级名称'); return }
    if (!formData.school_id) { ElMessage.warning('请选择学校'); return }

    submitLoading.value = true
    try {
        if (formData.id) {
            await editSchoolClass(formData)
        } else {
            await addSchoolClass(formData)
        }
        ElMessage.success(formData.id ? '编辑成功' : '添加成功')
        formVisible.value = false
        loadList()
    } catch (e: any) {
        ElMessage.error(e.message || '操作失败')
    } finally {
        submitLoading.value = false
    }
}

const handleStatusChange = async (row: any) => {
    try {
        await setSchoolClassStatus(row.id, row.status)
        ElMessage.success('状态修改成功')
    } catch (e: any) {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(e.message || '操作失败')
    }
}

const handleDel = (row: any) => {
    ElMessageBox.confirm('确定要删除该班级吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            await delSchoolClass(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}

const goSchedule = (row: any) => {
    router.push({ path: '/sd_xiaoyuan/class_schedule/list', query: { class_id: row.id, class_name: row.name } })
}
</script>

<style lang="scss" scoped>
.school-class-container { padding: 20px; }
.search-card { margin-bottom: 20px; }
</style>
