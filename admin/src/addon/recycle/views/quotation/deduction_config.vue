<template>
    <div class="deduction-config-page">
        <el-card shadow="never" class="search-card">
            <el-form :inline="true" :model="searchForm" class="search-form">
                <el-form-item label="配置名称">
                    <el-input
                        v-model="searchForm.config_name"
                        placeholder="请输入配置名称"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="型号ID">
                    <el-input
                        v-model="searchForm.model_id"
                        placeholder="请输入型号ID"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="报价ID">
                    <el-input
                        v-model="searchForm.price_id"
                        placeholder="请输入报价ID"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="状态">
                    <el-select
                        v-model="searchForm.is_enable"
                        placeholder="全部"
                        clearable
                        style="width: 120px"
                    >
                        <el-option label="启用" :value="1" />
                        <el-option label="禁用" :value="0" />
                    </el-select>
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        查询
                    </el-button>
                    <el-button @click="handleReset">
                        <el-icon><Refresh /></el-icon>
                        重置
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never" class="table-card">
            <div class="table-toolbar">
                <el-button type="primary" @click="handleAdd">
                    <el-icon><Plus /></el-icon>
                    添加配置
                </el-button>
            </div>

            <el-table v-loading="loading" :data="tableData" border>
                <el-table-column prop="id" label="ID" width="80" align="center" />
                <el-table-column prop="config_name" label="配置名称" min-width="150" />
                <el-table-column prop="model_id" label="型号ID" min-width="120">
                    <template #default="{ row }">
                        <el-tag v-for="(id, idx) in row.model_id.split(',')" :key="idx" size="small" class="mr-1">
                            {{ id.trim() }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="price_id" label="报价类型" min-width="150">
                    <template #default="{ row }">
                        <el-tag
                            v-for="(id, idx) in row.price_id.split(',')"
                            :key="idx"
                            size="small"
                            type="success"
                            class="mr-1"
                        >
                            {{ id.trim() === '114' ? '靓机/小花' : id.trim() === '115' ? '花机/内爆' : id.trim() }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="remark_text" label="备注说明" min-width="250" show-overflow-tooltip />
                <el-table-column prop="sort" label="排序" width="80" align="center" />
                <el-table-column prop="is_enable" label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_enable"
                            :active-value="1"
                            :inactive-value="0"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
                        <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="limit"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadData"
                    @current-change="loadData"
                />
            </div>
        </el-card>

        <!-- 编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogTitle"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="formData"
                :rules="formRules"
                label-width="100px"
                v-loading="formLoading"
            >
                <el-form-item label="配置名称" prop="config_name">
                    <el-input v-model="formData.config_name" placeholder="请输入配置名称" />
                </el-form-item>

                <el-form-item label="适用型号" prop="model_ids">
                    <el-select
                        v-model="formData.model_ids"
                        multiple
                        filterable
                        placeholder="请选择适用的型号"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="item in modelOptions"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                        />
                    </el-select>
                    <div class="form-tip">可选择多个型号，此配置将应用于所选的所有型号</div>
                </el-form-item>

                <el-form-item label="报价类型" prop="price_ids">
                    <el-select
                        v-model="formData.price_ids"
                        multiple
                        placeholder="请选择报价类型"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="item in priceTypeOptions"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                        />
                    </el-select>
                    <div class="form-tip">可选择多个报价类型，此配置将应用于所选的所有类型</div>
                </el-form-item>

                <el-form-item label="备注说明" prop="remark_text">
                    <el-input
                        v-model="formData.remark_text"
                        type="textarea"
                        :rows="8"
                        placeholder="请输入完整的备注文本，用于前端显示"
                    />
                    <div class="form-tip">此内容将显示在报价数据的备注列中</div>
                </el-form-item>

                <el-form-item label="排序" prop="sort">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>

                <el-form-item label="是否启用" prop="is_enable">
                    <el-switch v-model="formData.is_enable" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="formLoading" @click="handleSubmit">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { Search, Refresh, Plus } from '@element-plus/icons-vue'
import {
    getDeductionConfigPages,
    getDeductionConfigInfo,
    addDeductionConfig,
    editDeductionConfig,
    // delDeductionConfig,
    modifyDeductionConfigStatus
} from '@/addon/recycle/api/deduction'
import { getQuotationModelList } from '@/addon/recycle/api/quotation'

// 搜索表单
const searchForm = reactive({
    config_name: '',
    model_id: '',
    price_id: '',
    is_enable: ''
})

// 表格数据
const loading = ref(false)
const tableData = ref([])
const page = ref(1)
const limit = ref(20)
const total = ref(0)

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('添加配置')
const formLoading = ref(false)
const formRef = ref<FormInstance>()

// 表单数据
const formData = reactive({
    id: 0,
    config_name: '',
    model_id: '',
    price_id: '',
    model_ids: [] as number[], // 用于选择器的数组
    price_ids: [] as string[], // 用于选择器的数组
    remark_text: '',
    sort: 0,
    is_enable: 1
})

// 型号选项
const modelOptions = ref<any[]>([])

// 报价类型选项（固定）
const priceTypeOptions = [
    { label: '靓机/小花', value: '114' },
    { label: '花机/内爆', value: '115' }
]

// 表单验证规则
const formRules: FormRules = {
    config_name: [{ required: true, message: '请输入配置名称', trigger: 'blur' }],
    model_ids: [{ required: true, message: '请选择型号', trigger: 'change', type: 'array', min: 1 }],
    price_ids: [{ required: true, message: '请选择报价类型', trigger: 'change', type: 'array', min: 1 }],
    remark_text: [{ required: true, message: '请输入备注说明', trigger: 'blur' }]
}

// 加载型号列表
const loadModelOptions = async () => {
    try {
        const res = await getQuotationModelList({})
        modelOptions.value = res.data.map((item: any) => ({
            label: `${item.goods_name} (ID: ${item.goods_id})`,
            value: item.goods_id
        }))
    } catch (error) {
        console.error('加载型号列表失败:', error)
    }
}

// 加载数据
const loadData = async () => {
    loading.value = true
    try {
        const res = await getDeductionConfigPages({
            page: page.value,
            limit: limit.value,
            ...searchForm
        })
        tableData.value = res.data.data
        total.value = res.data.total
    } catch (error) {
        ElMessage.error('加载数据失败')
    } finally {
        loading.value = false
    }
}

// 搜索
const handleSearch = () => {
    page.value = 1
    loadData()
}

// 重置
const handleReset = () => {
    Object.assign(searchForm, {
        config_name: '',
        model_id: '',
        price_id: '',
        is_enable: ''
    })
    handleSearch()
}

// 添加
const handleAdd = () => {
    dialogTitle.value = '添加配置'
    Object.assign(formData, {
        id: 0,
        config_name: '',
        model_id: '',
        price_id: '',
        model_ids: [],
        price_ids: [],
        remark_text: '',
        sort: 0,
        is_enable: 1
    })
    dialogVisible.value = true
}

// 编辑
const handleEdit = async (row: any) => {
    dialogTitle.value = '编辑配置'
    formLoading.value = true
    dialogVisible.value = true

    try {
        const res = await getDeductionConfigInfo(row.id)
        Object.assign(formData, res.data)

        // 将字符串转换为数组用于选择器回显
        formData.model_ids = formData.model_id ? formData.model_id.split(',').map((id: string) => parseInt(id.trim())) : []
        formData.price_ids = formData.price_id ? formData.price_id.split(',').map((id: string) => id.trim()) : []
    } catch (error) {
        ElMessage.error('加载配置详情失败')
        dialogVisible.value = false
    } finally {
        formLoading.value = false
    }
}

// 提交表单
const handleSubmit = async () => {
    if (!formRef.value) return

    await formRef.value.validate(async (valid) => {
        if (valid) {
            formLoading.value = true
            try {
                // 将数组转换为逗号分隔的字符串
                const submitData = {
                    ...formData,
                    model_id: formData.model_ids.join(','),
                    price_id: formData.price_ids.join(',')
                }

                if (formData.id) {
                    await editDeductionConfig(formData.id, submitData)
                    ElMessage.success('编辑成功')
                } else {
                    await addDeductionConfig(submitData)
                    ElMessage.success('添加成功')
                }
                dialogVisible.value = false
                loadData()
            } catch (error: any) {
                ElMessage.error(error.message || '操作失败')
            } finally {
                formLoading.value = false
            }
        }
    })
}

// 删除
const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm('确定要删除该配置吗？', '提示', {
            type: 'warning'
        })

        // await delDeductionConfig(row.id)
        ElMessage.success('删除成功')
        loadData()
    } catch (error: any) {
        if (error !== 'cancel') {
            ElMessage.error(error.message || '删除失败')
        }
    }
}

// 修改状态
const handleStatusChange = async (row: any) => {
    try {
        await modifyDeductionConfigStatus({ id: row.id, is_enable: row.is_enable })
        ElMessage.success('状态修改成功')
        loadData()
    } catch (error: any) {
        ElMessage.error(error.message || '状态修改失败')
        // 恢复原状态
        row.is_enable = row.is_enable === 1 ? 0 : 1
    }
}

onMounted(() => {
    loadData()
    loadModelOptions()
})
</script>

<style lang="scss" scoped>
.deduction-config-page {
    .search-card {
        margin-bottom: 16px;
    }

    .table-card {
        .table-toolbar {
            margin-bottom: 16px;
        }

        .pagination-container {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }

    .form-tip {
        font-size: 12px;
        color: #909399;
        margin-top: 4px;
    }

    .mr-1 {
        margin-right: 4px;
        margin-bottom: 4px;
    }
}
</style>
