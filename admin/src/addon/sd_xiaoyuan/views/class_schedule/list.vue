<template>
    <div class="class-schedule-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="班级">
                    <el-select v-model="searchForm.class_id" placeholder="请选择班级" clearable filterable>
                        <el-option v-for="c in classList" :key="c.id" :label="`${c.grade} ${c.name}`" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="学期">
                    <el-input v-model="searchForm.semester" placeholder="如2024-2025-1" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="showAdd">添加课程</el-button>
                    <el-button type="warning" @click="goImport">导入课表</el-button>
                    <el-button type="danger" @click="handleClear" :disabled="!searchForm.class_id || !searchForm.semester">清空课表</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="week_day" label="星期" width="80">
                    <template #default="{ row }">{{ weekDayMap[row.week_day] || row.week_day }}</template>
                </el-table-column>
                <el-table-column label="节次" width="80">
                    <template #default="{ row }">{{ row.section_start }}-{{ row.section_end }}</template>
                </el-table-column>
                <el-table-column prop="course_name" label="课程名称" min-width="150" />
                <el-table-column prop="course_code" label="课程代码" width="120" />
                <el-table-column prop="teacher" label="教师" width="100" />
                <el-table-column prop="classroom" label="教室" width="120" />
                <el-table-column prop="location" label="地点" width="120" />
                <el-table-column prop="credit" label="学分" width="70" />
                <el-table-column label="周次" width="100">
                    <template #default="{ row }">{{ row.weeks_text || `${row.start_week}-${row.end_week}周` }}</template>
                </el-table-column>
                <el-table-column prop="color" label="颜色" width="70">
                    <template #default="{ row }">
                        <div :style="{ width: '24px', height: '24px', borderRadius: '4px', background: row.color || '#ccc' }"></div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="130" fixed="right">
                    <template #default="{ row }">
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
        <el-dialog v-model="formVisible" :title="formData.id ? '编辑课程' : '添加课程'" width="550px">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="班级" required>
                    <el-select v-model="formData.class_id" placeholder="请选择班级" filterable @change="(val: number) => { formData.school_id = getSchoolIdByClassId(val) }">
                        <el-option v-for="c in classList" :key="c.id" :label="`${c.grade} ${c.name}`" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="学期" required>
                    <el-input v-model="formData.semester" placeholder="如2024-2025-1" />
                </el-form-item>
                <el-form-item label="课程名称" required>
                    <el-input v-model="formData.course_name" placeholder="请输入课程名称" />
                </el-form-item>
                <el-form-item label="星期" required>
                    <el-select v-model="formData.week_day">
                        <el-option v-for="(label, val) in weekDayMap" :key="val" :label="label" :value="Number(val)" />
                    </el-select>
                </el-form-item>
                <el-form-item label="节次">
                    <el-col :span="11"><el-input-number v-model="formData.section_start" :min="1" :max="14" /></el-col>
                    <el-col :span="2" style="text-align:center">-</el-col>
                    <el-col :span="11"><el-input-number v-model="formData.section_end" :min="1" :max="14" /></el-col>
                </el-form-item>
                <el-form-item label="课程代码">
                    <el-input v-model="formData.course_code" placeholder="可选" />
                </el-form-item>
                <el-form-item label="教师">
                    <el-input v-model="formData.teacher" />
                </el-form-item>
                <el-form-item label="教室">
                    <el-input v-model="formData.classroom" />
                </el-form-item>
                <el-form-item label="地点">
                    <el-input v-model="formData.location" />
                </el-form-item>
                <el-form-item label="学分">
                    <el-input-number v-model="formData.credit" :min="0" :max="20" :precision="1" :step="0.5" />
                </el-form-item>
                <el-form-item label="周次">
                    <el-col :span="11"><el-input-number v-model="formData.start_week" :min="1" :max="30" /></el-col>
                    <el-col :span="2" style="text-align:center">-</el-col>
                    <el-col :span="11"><el-input-number v-model="formData.end_week" :min="1" :max="30" /></el-col>
                </el-form-item>
                <el-form-item label="颜色">
                    <el-color-picker v-model="formData.color" />
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
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getClassScheduleList, addClassSchedule, editClassSchedule, delClassSchedule, clearClassSchedule } from '@/addon/sd_xiaoyuan/api/schoolClass'
import { getAllSchoolClass } from '@/addon/sd_xiaoyuan/api/schoolClass'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const list = ref<any[]>([])
const classList = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 20, total: 0 })

const weekDayMap: Record<number, string> = { 1: '周一', 2: '周二', 3: '周三', 4: '周四', 5: '周五', 6: '周六', 7: '周日' }

const searchForm = reactive({
    class_id: Number(route.query.class_id) || '',
    semester: ''
})

const formVisible = ref(false)
const submitLoading = ref(false)
const formData = reactive({
    id: 0,
    school_id: 0,
    class_id: 0,
    semester: '',
    week_day: 1,
    section_start: 1,
    section_end: 2,
    course_code: '',
    course_name: '',
    teacher: '',
    classroom: '',
    location: '',
    credit: 0,
    start_week: 1,
    end_week: 16,
    week_type: 0,
    color: '#4ECDC4',
    weeks_text: ''
})

onMounted(() => {
    loadClasses()
    loadList()
})

const loadClasses = async () => {
    try {
        const res: any = await getAllSchoolClass()
        classList.value = res.data || []
    } catch (e) { console.error(e) }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getClassScheduleList({
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
    searchForm.class_id = ''
    searchForm.semester = ''
    pagination.page = 1
    loadList()
}

const getSchoolIdByClassId = (classId: number) => {
    const cls = classList.value.find((c: any) => c.id === classId)
    return cls ? cls.school_id : 0
}

const showAdd = () => {
    formData.id = 0
    formData.class_id = Number(searchForm.class_id) || 0
    formData.school_id = getSchoolIdByClassId(formData.class_id)
    formData.semester = searchForm.semester || ''
    formData.week_day = 1
    formData.section_start = 1
    formData.section_end = 2
    formData.course_code = ''
    formData.course_name = ''
    formData.teacher = ''
    formData.classroom = ''
    formData.location = ''
    formData.credit = 0
    formData.start_week = 1
    formData.end_week = 16
    formData.color = '#4ECDC4'
    formVisible.value = true
}

const showEdit = (row: any) => {
    Object.assign(formData, row)
    if (!formData.school_id) {
        formData.school_id = getSchoolIdByClassId(formData.class_id)
    }
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.course_name) { ElMessage.warning('请输入课程名称'); return }
    if (!formData.class_id) { ElMessage.warning('请选择班级'); return }
    if (!formData.semester) { ElMessage.warning('请输入学期'); return }

    submitLoading.value = true
    try {
        if (formData.id) {
            await editClassSchedule(formData)
        } else {
            await addClassSchedule(formData)
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

const handleDel = (row: any) => {
    ElMessageBox.confirm('确定要删除该课程吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            await delClassSchedule(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}

const handleClear = () => {
    ElMessageBox.confirm('确定要清空该班级本学期的所有课表数据吗？此操作不可恢复。', '警告', { type: 'error' }).then(async () => {
        try {
            await clearClassSchedule(Number(searchForm.class_id), searchForm.semester as string)
            ElMessage.success('清空成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '操作失败')
        }
    }).catch(() => {})
}

const goImport = () => {
    router.push({ path: '/sd_xiaoyuan/class_schedule/import' })
}
</script>

<style lang="scss" scoped>
.class-schedule-container { padding: 20px; }
.search-card { margin-bottom: 20px; }
</style>
