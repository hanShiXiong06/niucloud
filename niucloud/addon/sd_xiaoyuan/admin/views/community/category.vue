<template>
    <div class="category-manage">
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>社区分类管理</span>
                    <el-button type="primary" @click="handleAdd">添加分类</el-button>
                </div>
            </template>
            <el-table :data="categoryList" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="分类名称" min-width="120" />
                <el-table-column prop="icon" label="图标" width="100" align="center">
                    <template #default="{ row }">
                        <el-image v-if="row.icon" :src="img(row.icon)" style="width: 40px; height: 40px; border-radius: 4px;" fit="cover" />
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" align="center" />
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 1" type="success">启用</el-tag>
                        <el-tag v-else type="info">禁用</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
                        <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑分类' : '添加分类'" width="500px" :destroy-on-close="true">
            <el-form :model="formData" label-width="100px" ref="formRef">
                <el-form-item label="分类名称" required>
                    <el-input v-model="formData.name" placeholder="请输入分类名称" clearable />
                </el-form-item>
                <el-form-item label="分类图标">
                    <upload-image v-model="formData.icon" />
                    <div class="text-gray-400 text-xs mt-1">建议尺寸: 100x100px</div>
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
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCategoryList, addCategory, editCategory, deleteCategory } from '../../api/admin'
import { img } from '@/utils/common'
import UploadImage from '@/components/upload-image/index.vue'

const categoryList = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formData = ref({
    id: 0,
    name: '',
    icon: '',
    sort: 0,
    status: 1
})

onMounted(() => loadData())

const loadData = async () => {
    loading.value = true
    try {
        const res: any = await getCategoryList()
        if (res.code === 1) {
            categoryList.value = res.data
        }
    } finally {
        loading.value = false
    }
}

const handleAdd = () => {
    isEdit.value = false
    formData.value = { id: 0, name: '', icon: '', sort: 0, status: 1 }
    dialogVisible.value = true
}

const handleEdit = (row: any) => {
    isEdit.value = true
    formData.value = { ...row }
    dialogVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.value.name) {
        ElMessage.warning('请输入分类名称')
        return
    }
    
    const api = isEdit.value ? editCategory : addCategory
    const res: any = await api(formData.value)
    if (res.code === 1) {
        ElMessage.success(isEdit.value ? '保存成功' : '添加成功')
        dialogVisible.value = false
        loadData()
    }
}

const handleDelete = async (row: any) => {
    await ElMessageBox.confirm('确定删除该分类？', '提示')
    const res: any = await deleteCategory({ id: row.id })
    if (res.code === 1) {
        ElMessage.success('删除成功')
        loadData()
    }
}
</script>

<style scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
