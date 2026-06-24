<template>
    <div class="package-price-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="规格名称">
                    <el-input v-model="searchForm.name" placeholder="请输入规格名称" clearable />
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
                    <el-button type="success" @click="showAdd">添加规格</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="规格名称" min-width="150" />
                <el-table-column prop="description" label="规格描述" min-width="200" />
                <el-table-column prop="price" label="价格(元)" width="120" align="center" />
                <el-table-column prop="sort" label="排序" width="80" align="center" />
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 1" type="success">启用</el-tag>
                        <el-tag v-else type="info">禁用</el-tag>
                    </template>
                </el-table-column>
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
        <el-dialog v-model="formVisible" :title="formData.id ? '编辑规格' : '添加规格'" width="500px">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="规格名称" required>
                    <el-input v-model="formData.name" placeholder="请输入规格名称" clearable />
                </el-form-item>
                <el-form-item label="规格描述">
                    <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="请输入规格描述" />
                </el-form-item>
                <el-form-item label="价格(元)" required>
                    <el-input-number v-model="formData.price" :min="0" :precision="2" style="width: 100%;" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" style="width: 100%;" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-radio-group v-model="formData.status">
                        <el-radio :label="1">启用</el-radio>
                        <el-radio :label="0">禁用</el-radio>
                    </el-radio-group>
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
import { getPackagePrices, addPackagePrice, editPackagePrice, deletePackagePrice } from '@/addon/sd_xiaoyuan/api/packagePrice'

const loading = ref(false)
const list = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    name: '',
    status: ''
})

const formVisible = ref(false)
const submitLoading = ref(false)
const formData = reactive({
    id: 0,
    size: '',
    name: '',
    description: '',
    price: 0,
    sort: 0,
    status: 1
})

onMounted(() => {
    loadList()
})

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getPackagePrices({
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
    searchForm.name = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const showAdd = () => {
    formData.id = 0
    formData.size = ''
    formData.name = ''
    formData.description = ''
    formData.price = 0
    formData.sort = 0
    formData.status = 1
    formVisible.value = true
}

const showEdit = (row: any) => {
    formData.id = row.id
    formData.size = row.size || ''
    formData.name = row.name
    formData.description = row.description || ''
    formData.price = row.price || 0
    formData.sort = row.sort || 0
    formData.status = row.status
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.name) {
        ElMessage.warning('请输入规格名称')
        return
    }
    
    // 自动生成规格代码
    if (!formData.size) {
        formData.size = 'pkg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6)
    }
    
    submitLoading.value = true
    try {
        if (formData.id) {
            await editPackagePrice(formData.id, formData)
        } else {
            await addPackagePrice(formData)
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
    ElMessageBox.confirm('确定要删除该规格吗？', '提示', {
        type: 'warning'
    }).then(async () => {
        try {
            await deletePackagePrice(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}
</script>

<style lang="scss" scoped>
.package-price-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}
</style>
