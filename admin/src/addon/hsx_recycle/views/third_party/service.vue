<template>
    <PremiumTheme class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">第三方服务配置</span>
                <el-button type="primary" @click="handleAdd">
                    <template #icon>
                        <el-icon><Plus /></el-icon>
                    </template>
                    添加服务
                </el-button>
            </div>

            <el-card class="box-card !border-none mt-[20px]" shadow="never">
                <!-- 搜索表单 -->
                <el-form :inline="true" :model="formData" class="demo-form-inline">
                    <el-form-item label="服务类型">
                        <el-select v-model="formData.service_type" placeholder="请选择" clearable>
                            <el-option label="设备查询" value="device_query" />
                            <el-option label="快递下单" value="express_order" />
                            <el-option label="快递查询" value="express_query" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="服务商">
                        <el-input v-model="formData.provider_name" placeholder="请输入服务商名称" clearable />
                    </el-form-item>
                    <el-form-item label="状态">
                        <el-select v-model="formData.status" placeholder="请选择" clearable>
                            <el-option label="启用" :value="1" />
                            <el-option label="禁用" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadServiceList()">查询</el-button>
                        <el-button @click="resetForm">重置</el-button>
                    </el-form-item>
                </el-form>

                <!-- 数据表格 -->
                <el-table v-loading="loading" :data="serviceList" stripe style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80" />
                    <el-table-column prop="service_type" label="服务类型" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.service_type === 'device_query'" type="primary">设备查询</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_order'" type="success">快递下单</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_query'" type="info">快递查询</el-tag>
                            <el-tag v-else>{{ row.service_type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="provider_name" label="服务商" width="150" />
                    <el-table-column prop="priority" label="优先级" width="100" />
                    <el-table-column prop="balance" label="余额" width="120">
                        <template #default="{ row }">
                            <span :class="row.balance < row.min_balance_alert ? 'text-red-500' : ''">
                                ¥{{ row.balance }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.status"
                                :active-value="1"
                                :inactive-value="0"
                                @change="handleStatusChange(row)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_at" label="创建时间" width="180">
                        <template #default="{ row }">
                            {{ timeStampTurnTime(row.create_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="200">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
                            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 分页 -->
                <div class="flex justify-end mt-4">
                    <el-pagination
                        v-model:current-page="formData.page"
                        v-model:page-size="formData.limit"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="total"
                        layout="total, sizes, prev, pager, next, jumper"
                        @size-change="loadServiceList"
                        @current-change="loadServiceList"
                    />
                </div>
            </el-card>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogTitle"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form :model="editForm" :rules="rules" ref="editFormRef" label-width="120px">
                <el-form-item label="服务类型" prop="service_type">
                    <el-select v-model="editForm.service_type" placeholder="请选择服务类型" :disabled="!!editForm.id">
                        <el-option label="设备查询" value="device_query" />
                        <el-option label="快递下单" value="express_order" />
                        <el-option label="快递查询" value="express_query" />
                    </el-select>
                </el-form-item>
                <el-form-item label="服务商名称" prop="provider_name">
                    <el-input v-model="editForm.provider_name" placeholder="如：3023、anguo、ali_express" :disabled="!!editForm.id" />
                </el-form-item>
                <el-form-item label="优先级" prop="priority">
                    <el-input-number v-model="editForm.priority" :min="1" :max="100" />
                    <div class="text-xs text-gray-500 mt-1">数字越小优先级越高</div>
                </el-form-item>
                <el-form-item label="配置信息" prop="config">
                    <el-input
                        v-model="configJson"
                        type="textarea"
                        :rows="8"
                        placeholder='{"base_url": "http://api.example.com", "api_key": "your_key"}'
                    />
                    <div class="text-xs text-gray-500 mt-1">JSON格式，包含base_url、api_key等配置</div>
                </el-form-item>
                <el-form-item label="最低余额告警">
                    <el-input-number v-model="editForm.min_balance_alert" :min="0" :precision="2" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="editForm.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
            </template>
        </el-dialog>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { timeStampTurnTime } from '@/utils/common'
import { apiThirdPartyServiceLists, apiThirdPartyServiceAdd, apiThirdPartyServiceEdit, apiThirdPartyServiceDelete, apiThirdPartyServiceStatus } from '@/addon/hsx_recycle/api/third_party'

const loading = ref(false)
const submitLoading = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('添加服务')
const editFormRef = ref()

const formData = reactive({
    service_type: '',
    provider_name: '',
    status: '',
    page: 1,
    limit: 10
})

const serviceList = ref([])
const total = ref(0)

const editForm = reactive({
    id: 0,
    service_type: '',
    provider_name: '',
    priority: 1,
    config: {},
    status: 1,
    min_balance_alert: 100
})

const configJson = ref('')

const rules = {
    service_type: [{ required: true, message: '请选择服务类型', trigger: 'change' }],
    provider_name: [{ required: true, message: '请输入服务商名称', trigger: 'blur' }],
    priority: [{ required: true, message: '请输入优先级', trigger: 'blur' }]
}

// 加载服务列表
const loadServiceList = async () => {
    loading.value = true
    try {
        const res = await apiThirdPartyServiceLists(formData)
        serviceList.value = res.data.data
        total.value = res.data.total
    } catch (error) {
        ElMessage.error('加载失败')
    } finally {
        loading.value = false
    }
}

// 重置表单
const resetForm = () => {
    formData.service_type = ''
    formData.provider_name = ''
    formData.status = ''
    formData.page = 1
    loadServiceList()
}

// 添加
const handleAdd = () => {
    dialogTitle.value = '添加服务'
    Object.assign(editForm, {
        id: 0,
        service_type: '',
        provider_name: '',
        priority: 1,
        config: {},
        status: 1,
        min_balance_alert: 100
    })
    configJson.value = JSON.stringify({ base_url: '', api_key: '' }, null, 2)
    dialogVisible.value = true
}

// 编辑
const handleEdit = (row: any) => {
    dialogTitle.value = '编辑服务'
    Object.assign(editForm, row)
    configJson.value = JSON.stringify(row.config || {}, null, 2)
    dialogVisible.value = true
}

// 提交
const handleSubmit = async () => {
    if (!editFormRef.value) return

    await editFormRef.value.validate(async (valid: boolean) => {
        if (!valid) return

        // 解析JSON配置
        try {
            editForm.config = JSON.parse(configJson.value)
        } catch (error) {
            ElMessage.error('配置信息格式错误，请输入有效的JSON')
            return
        }

        submitLoading.value = true
        try {
            if (editForm.id) {
                await apiThirdPartyServiceEdit(editForm)
                ElMessage.success('编辑成功')
            } else {
                await apiThirdPartyServiceAdd(editForm)
                ElMessage.success('添加成功')
            }
            dialogVisible.value = false
            loadServiceList()
        } catch (error) {
            ElMessage.error(editForm.id ? '编辑失败' : '添加失败')
        } finally {
            submitLoading.value = false
        }
    })
}

// 删除
const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该服务吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            await apiThirdPartyServiceDelete(row.id)
            ElMessage.success('删除成功')
            loadServiceList()
        } catch (error) {
            ElMessage.error('删除失败')
        }
    })
}

// 修改状态
const handleStatusChange = async (row: any) => {
    try {
        await apiThirdPartyServiceStatus(row.id, { status: row.status })
        ElMessage.success('状态修改成功')
    } catch (error) {
        ElMessage.error('状态修改失败')
        row.status = row.status === 1 ? 0 : 1
    }
}

onMounted(() => {
    loadServiceList()
})
</script>

<style scoped lang="scss">
.text-page-title {
    font-size: 18px;
    font-weight: 600;
}
</style>
