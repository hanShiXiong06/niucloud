<template>
    <div class="express-station-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="所属学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="school in schoolList" :key="school.id" :label="school.name" :value="school.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="驿站名称">
                    <el-input v-model="searchForm.name" placeholder="请输入驿站名称" clearable />
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
                    <el-button type="success" @click="showAdd">添加驿站</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="驿站名称" min-width="200" />
                <el-table-column label="所属学校" min-width="150">
                    <template #default="{ row }">
                        {{ getSchoolName(row.school_id) }}
                    </template>
                </el-table-column>
                <el-table-column prop="address" label="地址" min-width="200" />
                <el-table-column prop="business_hours" label="营业时间" width="120" />
                <el-table-column prop="contact_name" label="联系人" width="100" />
                <el-table-column prop="contact_mobile" label="联系电话" width="120" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" />
                    </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column label="操作" width="150" fixed="right">
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
        <el-dialog v-model="formVisible" :title="formData.id ? '编辑驿站' : '添加驿站'" width="600px">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="所属学校" required>
                    <template #label>
                        <span>所属学校</span>
                        <span class="required-star">*</span>
                    </template>
                    <el-select v-model="formData.school_id" placeholder="请选择学校" filterable style="width: 100%">
                        <el-option v-for="school in schoolList" :key="school.id" :label="school.name" :value="school.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="驿站名称" required>
                    <template #label>
                        <span>驿站名称</span>
                        <span class="required-star">*</span>
                    </template>
                    <el-input v-model="formData.name" placeholder="请输入驿站名称" />
                </el-form-item>
                <el-form-item label="详细地址" required>
                    <template #label>
                        <span>详细地址</span>
                        <span class="required-star">*</span>
                    </template>
                    <el-input v-model="formData.address" placeholder="请输入详细地址" />
                </el-form-item>
                <el-form-item label="快递公司">
                    <el-input v-model="formData.express_company" placeholder="如：菜鸟驿站、顺丰、京东等" />
                </el-form-item>
                <el-form-item label="营业时间">
                    <el-input v-model="formData.business_hours" placeholder="如：08:00-20:00" />
                </el-form-item>
                <el-form-item label="联系人">
                    <el-input v-model="formData.contact_name" placeholder="请输入联系人" />
                </el-form-item>
                <el-form-item label="联系电话">
                    <el-input v-model="formData.contact_mobile" placeholder="请输入联系电话" />
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
import { getExpressStationList, addExpressStation, editExpressStation, delExpressStation, setExpressStationStatus } from '@/addon/sd_xiaoyuan/api/expressStation'
import { getAllSchools } from '@/addon/sd_xiaoyuan/api/school'

const loading = ref(false)
const list = ref<any[]>([])
const schoolList = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    school_id: '',
    name: '',
    status: ''
})

const formVisible = ref(false)
const submitLoading = ref(false)
const formData = reactive({
    id: 0,
    school_id: 0,
    name: '',
    address: '',
    express_company: '',
    lng: '',
    lat: '',
    contact_name: '',
    contact_mobile: '',
    business_hours: '08:00-20:00',
    sort: 0,
    status: 1
})

onMounted(() => {
    loadSchoolList()
    loadList()
})

const loadSchoolList = async () => {
    try {
        const res: any = await getAllSchools()
        schoolList.value = res.data || []
    } catch (e) {
        console.error(e)
    }
}

const getSchoolName = (schoolId: number) => {
    const school = schoolList.value.find(s => s.id === schoolId)
    return school ? school.name : '-'
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getExpressStationList({
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
    searchForm.name = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const showAdd = () => {
    formData.id = 0
    formData.school_id = 0
    formData.name = ''
    formData.address = ''
    formData.express_company = ''
    formData.lng = ''
    formData.lat = ''
    formData.contact_name = ''
    formData.contact_mobile = ''
    formData.business_hours = '08:00-20:00'
    formData.sort = 0
    formData.status = 1
    formVisible.value = true
}

const showEdit = (row: any) => {
    formData.id = row.id
    formData.school_id = row.school_id
    formData.name = row.name
    formData.address = row.address || ''
    formData.express_company = row.express_company || ''
    formData.lng = row.lng || ''
    formData.lat = row.lat || ''
    formData.contact_name = row.contact_name || ''
    formData.contact_mobile = row.contact_mobile || ''
    formData.business_hours = row.business_hours || '08:00-20:00'
    formData.sort = row.sort || 0
    formData.status = row.status
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.school_id) {
        ElMessage.warning('请选择所属学校')
        return
    }
    if (!formData.name) {
        ElMessage.warning('请输入驿站名称')
        return
    }
    if (!formData.address) {
        ElMessage.warning('请输入详细地址')
        return
    }
    
    submitLoading.value = true
    try {
        if (formData.id) {
            await editExpressStation(formData.id, formData)
        } else {
            await addExpressStation(formData)
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
        await setExpressStationStatus(row.id, row.status)
        ElMessage.success('状态修改成功')
    } catch (e: any) {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(e.message || '操作失败')
    }
}

const handleDel = (row: any) => {
    ElMessageBox.confirm('确定要删除该快递站点吗？', '提示', {
        type: 'warning'
    }).then(async () => {
        try {
            await delExpressStation(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}
</script>

<style lang="scss" scoped>
.express-station-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.required-star {
    color: #f56c6c;
    margin-left: 4px;
}
</style>
