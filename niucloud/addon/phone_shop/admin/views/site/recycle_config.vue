<template>
    <div>
        <el-card>
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg">回收商配置</span>
                <el-button type="primary" @click="handleAdd" v-if="canAdd">配置回收商信息</el-button>
            </div>
            <div v-if="!canAdd">
                <!-- 回收商信息列表 -->
                <el-table :data="tableData" v-loading="loading">
                    <el-table-column prop="contact_name" label="联系人" />
                    <el-table-column prop="contact_mobile" label="联系电话" />
                    <el-table-column prop="full_address" label="地址" />
                    <el-table-column prop="status" label="状态">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 1 ? 'success' : 'info'">
                                {{ row.status === 1 ? '启用' : '禁用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="200">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
                            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div v-else>
                <el-empty description="当前站点没有配置回收商信息" />
            </div>

        </el-card>

        <!-- 编辑弹窗 -->
        <edit-recycle ref="editDialog" @success="loadData" />
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getRole, getRecyclerInfo, deleteRecycler } from '@/addon/phone_shop/api/site'
import EditRecycle from './components/edit-recycle.vue'

const editDialog = ref()
const loading = ref(false)
const tableData = ref([])
const canAdd = ref(true)

// 检查是否具有回收商角色
const checkRole = async () => {
    try {
        const result = await getRole()
        if (result.code === 0) {
            ElMessage.error('请先开通回收商套餐')
            canAdd.value = false
            return false
        }
        return true
    } catch (error) {
        console.error('检查角色失败:', error)
        return false
    }
}

// 加载回收商数据
const loadData = async () => {
    loading.value = true
    try {
        const result = await getRecyclerInfo() // 0表示获取当前站点的回收商信息

        tableData.value = result.data ? [result.data] : []
        // 如果 result.data是空数组
        if (result.data.length === 0) {
            canAdd.value = true
        } else {
            canAdd.value = false
        }
    } catch (error) {
        console.error('加载数据失败:', error)
    } finally {
        loading.value = false
    }
}

// 添加回收商
const handleAdd = async () => {
    if (!await checkRole()) return
    editDialog.value.setFormData()
    editDialog.value.showDialog = true
}

// 编辑回收商
const handleEdit = (row: any) => {
    editDialog.value.setFormData(row)
    editDialog.value.showDialog = true
}

// 删除回收商
const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该回收商信息吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            await deleteRecycler(row.id)
            ElMessage.success('删除成功')
            loadData()
        } catch (error) {
            console.error('删除失败:', error)
        }
    })
}

onMounted(() => {
    loadData()
})
</script>
