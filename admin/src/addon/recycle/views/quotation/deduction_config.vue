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

                <el-form-item label="商品系列">
                    <el-input
                        v-model="searchForm.goods_series"
                        placeholder="请输入商品系列"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="报价类型">
                    <el-select
                        v-model="searchForm.price_type"
                        placeholder="请选择报价类型"
                        clearable
                        style="width: 200px"
                    >
                        <el-option label="花机/内爆" value="花机/内爆" />
                        <el-option label="靓机/小花" value="靓机/小花" />
                    </el-select>
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
                <el-table-column prop="config_name" label="配置名称" min-width="180" />
                <el-table-column prop="goods_series" label="商品系列" min-width="150" />
                <el-table-column prop="price_type" label="报价类型" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.price_type" type="primary" size="small">
                            {{ row.price_type }}
                        </el-tag>
                        <el-tag v-else type="info" size="small">全部</el-tag>
                    </template>
                </el-table-column>
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
                <el-table-column prop="create_at" label="创建时间" width="180" align="center" />
                <el-table-column label="操作" width="200" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="handleEdit(row)">
                            编辑
                        </el-button>
                        <el-button type="danger" link @click="handleDelete(row)">
                            删除
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination">
                <el-pagination
                    v-model:current-page="pager.page"
                    v-model:page-size="pager.limit"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="pager.total"
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
            width="800px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="formData"
                :rules="formRules"
                label-width="120px"
            >
                <el-form-item label="配置名称" prop="config_name">
                    <el-input
                        v-model="formData.config_name"
                        placeholder="请输入配置名称"
                        maxlength="100"
                    />
                </el-form-item>

                <el-form-item label="商品系列" prop="goods_series">
                    <el-input
                        v-model="formData.goods_series"
                        placeholder="如：iPhone 16 Pro"
                        maxlength="100"
                    />
                    <div class="form-tip">支持模糊匹配，如 "iPhone 16 Pro" 可匹配 "iPhone 16 Pro Max"</div>
                </el-form-item>

                <el-form-item label="报价类型" prop="price_type">
                    <el-select
                        v-model="formData.price_type"
                        placeholder="请选择报价类型"
                        clearable
                        style="width: 100%"
                    >
                        <el-option label="花机/内爆" value="花机/内爆" />
                        <el-option label="靓机/小花" value="靓机/小花" />
                        <el-option label="全部类型" value="" />
                    </el-select>
                    <div class="form-tip">留空表示适用所有报价类型</div>
                </el-form-item>

                <el-form-item label="扣费项目" prop="deduction_items">
                    <div class="deduction-items-container">
                        <div
                            v-for="(item, index) in formData.deduction_items"
                            :key="index"
                            class="deduction-item"
                        >
                            <el-input
                                v-model="item.item"
                                placeholder="扣费项"
                                style="width: 180px"
                            />
                            <el-input
                                v-model="item.deduction"
                                placeholder="扣费金额"
                                style="width: 150px"
                            />
                            <el-input
                                v-model="item.unit"
                                placeholder="单位"
                                style="width: 80px"
                            />
                            <el-button
                                type="danger"
                                icon="Delete"
                                circle
                                @click="removeDeductionItem(index)"
                            />
                        </div>
                        <el-button
                            type="primary"
                            icon="Plus"
                            plain
                            @click="addDeductionItem"
                        >
                            添加扣费项
                        </el-button>
                    </div>
                </el-form-item>

                <el-form-item label="备注预览">
                    <el-input
                        :model-value="remarkPreview"
                        type="textarea"
                        :rows="8"
                        readonly
                        placeholder="根据扣费项自动生成"
                    />
                </el-form-item>

                <el-form-item label="排序" prop="sort">
                    <el-input-number
                        v-model="formData.sort"
                        :min="0"
                        :max="9999"
                        controls-position="right"
                    />
                    <div class="form-tip">数字越小越靠前</div>
                </el-form-item>

                <el-form-item label="是否启用" prop="is_enable">
                    <el-switch
                        v-model="formData.is_enable"
                        :active-value="1"
                        :inactive-value="0"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSubmit">
                    确定
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { Search, Refresh, Plus } from '@element-plus/icons-vue'
import {
    getDeductionConfigPages,
    addDeductionConfig,
    editDeductionConfig,
    deleteDeductionConfig,
    modifyDeductionConfigStatus
} from '@/addon/recycle/api/deduction'

// 搜索表单
const searchForm = reactive({
    config_name: '',
    goods_series: '',
    price_type: '',
    is_enable: null as number | null
})

// 表格数据
const tableData = ref<any[]>([])
const loading = ref(false)

// 分页
const pager = reactive({
    page: 1,
    limit: 10,
    total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref<FormInstance>()
const saving = ref(false)

// 表单数据
const formData = reactive({
    id: 0,
    config_name: '',
    goods_series: '',
    price_type: '',
    deduction_items: [] as Array<{ item: string; deduction: string; unit: string }>,
    sort: 0,
    is_enable: 1
})

// 表单验证规则
const formRules: FormRules = {
    config_name: [
        { required: true, message: '请输入配置名称', trigger: 'blur' }
    ],
    goods_series: [
        { required: true, message: '请输入商品系列', trigger: 'blur' }
    ]
}

// 备注预览
const remarkPreview = computed(() => {
    if (formData.deduction_items.length === 0) return ''

    let text = formData.config_name ? formData.config_name + '：\n' : ''
    formData.deduction_items.forEach(item => {
        if (item.item && item.deduction) {
            text += item.item + item.deduction + '\n'
        }
    })
    return text.trim()
})

// 加载数据
async function loadData () {
    try {
        loading.value = true

        const params = {
            ...searchForm,
            page: pager.page,
            limit: pager.limit
        }

        const res = await getDeductionConfigPages(params)

        if (res.data) {
            tableData.value = res.data.list || []
            pager.total = res.data.total || 0
        }
    } catch (error) {
        console.error('加载数据失败:', error)
        ElMessage.error('加载数据失败')
    } finally {
        loading.value = false
    }
}

// 搜索
function handleSearch () {
    pager.page = 1
    loadData()
}

// 重置
function handleReset () {
    searchForm.config_name = ''
    searchForm.goods_series = ''
    searchForm.price_type = ''
    searchForm.is_enable = null
    pager.page = 1
    loadData()
}

// 添加
function handleAdd () {
    dialogTitle.value = '添加扣费配置'
    resetForm()
    dialogVisible.value = true
}

// 编辑
function handleEdit (row: any) {
    dialogTitle.value = '编辑扣费配置'
    formData.id = row.id
    formData.config_name = row.config_name
    formData.goods_series = row.goods_series
    formData.price_type = row.price_type || ''
    formData.deduction_items = row.deduction_items ? [...row.deduction_items] : []
    formData.sort = row.sort
    formData.is_enable = row.is_enable
    dialogVisible.value = true
}

// 删除
function handleDelete (row: any) {
    ElMessageBox.confirm('确定要删除该配置吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            await deleteDeductionConfig(row.id)
            ElMessage.success('删除成功')
            loadData()
        } catch (error) {
            console.error('删除失败:', error)
        }
    }).catch(() => {
        // 取消删除
    })
}

// 状态改变
async function handleStatusChange (row: any) {
    try {
        await modifyDeductionConfigStatus({
            id: row.id,
            is_enable: row.is_enable
        })
        ElMessage.success('状态修改成功')
    } catch (error) {
        console.error('状态修改失败:', error)
        // 恢复原状态
        row.is_enable = row.is_enable === 1 ? 0 : 1
    }
}

// 添加扣费项
function addDeductionItem () {
    formData.deduction_items.push({
        item: '',
        deduction: '',
        unit: '元'
    })
}

// 删除扣费项
function removeDeductionItem (index: number) {
    formData.deduction_items.splice(index, 1)
}

// 提交表单
async function handleSubmit () {
    if (!formRef.value) return

    await formRef.value.validate(async (valid) => {
        if (!valid) return

        try {
            saving.value = true

            const params = {
                config_name: formData.config_name,
                goods_series: formData.goods_series,
                price_type: formData.price_type,
                deduction_items: formData.deduction_items,
                sort: formData.sort,
                is_enable: formData.is_enable
            }

            if (formData.id) {
                await editDeductionConfig(formData.id, params)
            } else {
                await addDeductionConfig(params)
            }

            dialogVisible.value = false
            loadData()
        } catch (error) {
            console.error('保存失败:', error)
        } finally {
            saving.value = false
        }
    })
}

// 重置表单
function resetForm () {
    formData.id = 0
    formData.config_name = ''
    formData.goods_series = ''
    formData.price_type = ''
    formData.deduction_items = []
    formData.sort = 0
    formData.is_enable = 1
}

// 初始化
onMounted(() => {
    loadData()
})
</script>

<style scoped lang="scss">
.deduction-config-page {
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

.search-card {
    margin-bottom: 20px;

    :deep(.el-card__body) {
        padding: 20px;
    }
}

.search-form {
    :deep(.el-form-item) {
        margin-bottom: 0;
    }
}

.table-card {
    :deep(.el-card__body) {
        padding: 0;
    }
}

.table-toolbar {
    padding: 16px 20px;
    border-bottom: 1px solid #ebeef5;
}

.pagination {
    padding: 20px;
    display: flex;
    justify-content: flex-end;
}

.deduction-items-container {
    width: 100%;

    .deduction-item {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
    }
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 5px;
}
</style>
