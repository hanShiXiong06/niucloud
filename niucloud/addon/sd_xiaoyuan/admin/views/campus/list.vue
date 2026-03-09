<template>
    <div class="campus-list-container">
        <!-- 搜索表单 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="请选择学校" clearable style="width: 200px">
                        <el-option
                            v-for="school in schoolList"
                            :key="school.id"
                            :label="school.name"
                            :value="school.id"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 120px">
                        <el-option label="启用" :value="1" />
                        <el-option label="禁用" :value="0" />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="请输入校区名称或地址" clearable style="width: 200px" />
                </el-form-item>
                
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作按钮 -->
        <el-card class="operation-card">
            <el-button type="primary" @click="handleAdd">添加校区</el-button>
        </el-card>

        <!-- 数据表格 -->
        <el-card>
            <el-table :data="tableData" v-loading="loading" style="width: 100%">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="校区名称" min-width="150" />
                <el-table-column prop="school_name" label="所属学校" min-width="150" />
                <el-table-column prop="address" label="校区地址" min-width="200" />
                <el-table-column prop="status" label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'">
                            {{ row.status === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column prop="create_time" label="创建时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
                        <el-button :type="row.status === 1 ? 'warning' : 'success'" size="small" @click="handleStatus(row)">
                            {{ row.status === 1 ? '禁用' : '启用' }}
                        </el-button>
                        <el-button type="danger" size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            
            <!-- 分页 -->
            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="searchForm.page"
                    v-model:page-size="searchForm.limit"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="handleSizeChange"
                    @current-change="handleCurrentChange"
                />
            </div>
        </el-card>

        <!-- 添加/编辑弹窗 -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogTitle"
            width="600px"
            @close="resetForm"
        >
            <el-form
                ref="formRef"
                :model="formData"
                :rules="formRules"
                label-width="100px"
            >
                <el-form-item label="所属学校" prop="school_id">
                    <el-select v-model="formData.school_id" placeholder="请选择学校" style="width: 100%">
                        <el-option
                            v-for="school in schoolList"
                            :key="school.id"
                            :label="school.name"
                            :value="school.id"
                        />
                    </el-select>
                </el-form-item>
                
                <el-form-item label="校区名称" prop="name">
                    <el-input v-model="formData.name" placeholder="请输入校区名称" />
                </el-form-item>
                
                <el-form-item label="校区地址" prop="address">
                    <el-input v-model="formData.address" placeholder="请输入校区地址" />
                </el-form-item>
                
                <el-form-item label="经度" prop="lng">
                    <el-input v-model="formData.lng" placeholder="请输入经度" />
                </el-form-item>
                
                <el-form-item label="纬度" prop="lat">
                    <el-input v-model="formData.lat" placeholder="请输入纬度" />
                </el-form-item>
                
                <el-form-item label="状态" prop="status">
                    <el-radio-group v-model="formData.status">
                        <el-radio :label="1">启用</el-radio>
                        <el-radio :label="0">禁用</el-radio>
                    </el-radio-group>
                </el-form-item>
                
                <el-form-item label="排序" prop="sort">
                    <el-input-number v-model="formData.sort" :min="0" placeholder="请输入排序" />
                </el-form-item>
            </el-form>
            
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCampusList, addCampus, editCampus, deleteCampus, setCampusStatus } from '@/addon/sd_xiaoyuan/api/campus'
import { getSchoolAll } from '@/addon/sd_xiaoyuan/api/school'

// 响应式数据
const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const schoolList = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref()

// 搜索表单
const searchForm = reactive({
    school_id: '',
    status: '',
    keyword: '',
    page: 1,
    limit: 10
})

// 表单数据
const formData = reactive({
    id: 0,
    school_id: '',
    name: '',
    address: '',
    lng: '',
    lat: '',
    status: 1,
    sort: 0
})

// 表单验证规则
const formRules = {
    school_id: [{ required: true, message: '请选择学校', trigger: 'change' }],
    name: [{ required: true, message: '请输入校区名称', trigger: 'blur' }]
}


// 加载学校列表
const loadSchoolList = async () => {
    try {
        const res = await getSchoolAll({})
        schoolList.value = res.data || []
    } catch (error) {
        console.error('加载学校列表失败:', error)
    }
}

// 加载校区列表
const loadList = async () => {
    loading.value = true
    try {
        const res = await getCampusList(searchForm)
        tableData.value = res.data.list || []
        total.value = res.data.count || 0
    } catch (error) {
        console.error('加载校区列表失败:', error)
        ElMessage.error('加载校区列表失败')
    } finally {
        loading.value = false
    }
}

// 重置搜索
const resetSearch = () => {
    searchForm.school_id = ''
    searchForm.status = ''
    searchForm.keyword = ''
    searchForm.page = 1
    loadList()
}

// 分页大小变化
const handleSizeChange = (size: number) => {
    searchForm.limit = size
    searchForm.page = 1
    loadList()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
    searchForm.page = page
    loadList()
}

// 添加校区
const handleAdd = () => {
    dialogTitle.value = '添加校区'
    resetForm()
    dialogVisible.value = true
}

// 编辑校区
const handleEdit = (row: any) => {
    dialogTitle.value = '编辑校区'
    Object.assign(formData, row)
    dialogVisible.value = true
}

// 设置状态
const handleStatus = async (row: any) => {
    const status = row.status === 1 ? 0 : 1
    const statusText = status === 1 ? '启用' : '禁用'
    
    try {
        await setCampusStatus({ id: row.id, status })
        ElMessage.success(`${statusText}成功`)
        loadList()
    } catch (error) {
        console.error('设置状态失败:', error)
        ElMessage.error('设置状态失败')
    }
}

// 删除校区
const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该校区吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            await deleteCampus(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (error) {
            console.error('删除校区失败:', error)
            ElMessage.error('删除校区失败')
        }
    })
}

// 重置表单
const resetForm = () => {
    Object.assign(formData, {
        id: 0,
        school_id: '',
        name: '',
        address: '',
        lng: '',
        lat: '',
        status: 1,
        sort: 0
    })
    formRef.value?.clearValidate()
}

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return
    
    try {
        await formRef.value.validate()
        
        if (formData.id) {
            await editCampus(formData)
            ElMessage.success('修改成功')
        } else {
            await addCampus(formData)
            ElMessage.success('添加成功')
        }
        
        dialogVisible.value = false
        loadList()
    } catch (error) {
        console.error('提交失败:', error)
        ElMessage.error('提交失败')
    }
}

// 初始化
onMounted(() => {
    loadSchoolList()
    loadList()
})
</script>

<style lang="scss" scoped>
.campus-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.operation-card {
    margin-bottom: 20px;
}

.pagination-container {
    margin-top: 20px;
    text-align: right;
}
</style>
