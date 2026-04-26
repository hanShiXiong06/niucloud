<template>
    <el-dialog
        v-model="showDialog"
        :title="formData.id ? '编辑价格配置' : '添加价格配置'"
        width="1400px"
        class="diy-dialog-wrap"
        :destroy-on-close="true"
    >
        <el-form :model="formData" label-width="100px" ref="formRef" :rules="formRules" v-loading="loading">
            <!-- 基础信息 -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <el-form-item label="标题" prop="title">
                    <el-input v-model="formData.title" clearable placeholder="请输入标题" />
                </el-form-item>
                <el-form-item label="配置类型" prop="config_type">
                    <el-select v-model="formData.config_type" placeholder="请选择配置类型" @change="onConfigTypeChange">
                        <el-option label="SKU级别（型号+容量+等级）" :value="1" />
                        <el-option label="型号+容量" :value="2" />
                        <el-option label="型号" :value="3" />
                        <el-option label="分组" :value="4" />
                    </el-select>
                </el-form-item>
            </div>

            <!-- SKU级别配置 -->
            <template v-if="formData.config_type === 1">
                <el-divider content-position="left">SKU选择</el-divider>

                <!-- 报价单和型号选择 -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <el-form-item label="报价单">
                        <el-select v-model="selectedQuotationId" placeholder="请选择报价单" @change="handleQuotationChange" clearable>
                            <el-option
                                v-for="quotation in quotationList"
                                :key="quotation.id"
                                :label="`${quotation.quotation_id} - ${quotation.price_name}`"
                                :value="quotation.id"
                            />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="型号">
                        <el-select
                            v-model="selectedModelIds"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            placeholder="请选择型号（可多选）"
                            @change="handleModelChange"
                            :disabled="!selectedQuotationId"
                        >
                            <el-option
                                v-for="model in modelList"
                                :key="model.goods_id"
                                :label="model.goods_name"
                                :value="model.goods_id"
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <!-- SKU表格 -->
                <div v-if="selectedModelIds.length > 0" class="sku-table-container">
                    <!-- 筛选和操作栏 -->
                    <div class="flex items-center justify-between mb-3 p-3 bg-gray-50 rounded">
                        <div class="flex items-center gap-3">
                            <!-- 等级筛选 -->
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">等级:</span>
                                <el-select v-model="filterGrade" placeholder="全部" size="small" style="width: 150px" clearable>
                                    <el-option label="全部" value="" />
                                    <el-option
                                        v-for="grade in availableGrades"
                                        :key="grade"
                                        :label="`${grade} (${getGradeCount(grade)})`"
                                        :value="grade"
                                    />
                                </el-select>
                            </div>
                            <!-- 容量筛选 -->
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">容量:</span>
                                <el-select v-model="filterCapacity" placeholder="全部" size="small" style="width: 120px" clearable>
                                    <el-option label="全部" value="" />
                                    <el-option
                                        v-for="capacity in availableCapacities"
                                        :key="capacity"
                                        :label="`${capacity} (${getCapacityCount(capacity)})`"
                                        :value="capacity"
                                    />
                                </el-select>
                            </div>
                            <!-- 搜索 -->
                            <el-input
                                v-model="searchKeyword"
                                placeholder="搜索型号名称"
                                size="small"
                                style="width: 200px"
                                clearable
                            >
                                <template #prefix>
                                    <el-icon><Search /></el-icon>
                                </template>
                            </el-input>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">
                                已选 <span class="text-blue-600 font-medium">{{ selectedSkuKeys.length }}</span> / {{ filteredSkuList.length }}
                            </span>
                            <el-button size="small" @click="selectAllFiltered">全选当前</el-button>
                            <el-button size="small" @click="selectByGrade" v-if="availableGrades.length > 0">按等级选择</el-button>
                            <el-button size="small" @click="selectByCapacity" v-if="availableCapacities.length > 0">按容量选择</el-button>
                            <el-button size="small" @click="clearSelection">清空</el-button>
                        </div>
                    </div>

                    <!-- 表格 -->
                    <el-table
                        :data="paginatedSkuList"
                        v-loading="skuListLoading"
                        height="400"
                        @selection-change="handleSelectionChange"
                        ref="skuTableRef"
                    >
                        <el-table-column type="selection" width="50" :selectable="checkSelectable" />
                        <el-table-column prop="goods_name" label="型号" min-width="180" />
                        <el-table-column prop="capacity" label="容量" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" type="success">{{ row.capacity }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="config_item_name" label="等级" width="150">
                            <template #default="{ row }">
                                <el-tag size="small" type="primary">{{ row.config_item_name || '-' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="isSkuInList(row)" size="small" type="info">已添加</el-tag>
                                <el-tag v-else size="small" type="success">可选</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- 分页 -->
                    <div class="flex justify-end mt-3">
                        <el-pagination
                            v-model:current-page="currentPage"
                            v-model:page-size="pageSize"
                            :page-sizes="[20, 50, 100, 200]"
                            :total="filteredSkuList.length"
                            layout="total, sizes, prev, pager, next"
                            small
                        />
                    </div>
                </div>
            </template>

            <!-- 其他配置类型 -->
            <template v-else-if="formData.config_type === 2">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="设备ID" prop="goods_id">
                        <el-input-number v-model="formData.goods_id" :min="0" placeholder="请输入设备ID" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="容量" prop="capacity">
                        <el-input v-model="formData.capacity" clearable placeholder="请输入容量" />
                    </el-form-item>
                </div>
            </template>
            <template v-else-if="formData.config_type === 3">
                <el-form-item label="设备ID" prop="goods_id">
                    <el-input-number v-model="formData.goods_id" :min="0" placeholder="请输入设备ID" class="input-width" style="width: 100%" />
                </el-form-item>
            </template>
            <template v-else-if="formData.config_type === 4">
                <el-form-item label="分组标识" prop="group_key">
                    <el-input v-model="formData.group_key" clearable placeholder="请输入分组标识" class="input-width" />
                </el-form-item>
            </template>

            <!-- 价格调整设置 -->
            <el-divider content-position="left">价格调整</el-divider>
            <div class="grid grid-cols-2 gap-4">
                <el-form-item label="调整类型" prop="adjustment_type">
                    <el-select v-model="formData.adjustment_type" placeholder="请选择调整类型">
                        <el-option label="固定金额（±元）" :value="1" />
                        <el-option label="百分比（±%）" :value="2" />
                        <el-option label="直接覆盖" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item label="调整值" prop="adjustment_value">
                    <el-input-number
                        v-model="formData.adjustment_value"
                        :precision="2"
                        :min="formData.adjustment_type === 2 ? -100 : undefined"
                        :max="formData.adjustment_type === 2 ? 100 : undefined"
                        placeholder="请输入调整值"
                        style="width: 100%"
                    >
                        <template #append v-if="formData.adjustment_type === 2">%</template>
                        <template #append v-else>元</template>
                    </el-input-number>
                </el-form-item>
            </div>
            <el-form-item label="是否启用" prop="is_enable">
                <el-switch v-model="formData.is_enable" :active-value="1" :inactive-value="0" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="showDialog = false">取消</el-button>
            <el-button type="primary" :loading="loading" @click="confirm">确定</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import type { FormInstance } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import {
    addQuotationPriceConfig,
    editQuotationPriceConfig,
    getQuotationPriceConfigInfo,
    batchAddSkuPriceConfig,
    getQuotationConfigList,
    getQuotationDataCascadeOptions
} from '@/addon/recycle/api/quotation'

const showDialog = ref(false)
const loading = ref(false)
const formRef = ref<FormInstance>()
const skuTableRef = ref()

// 表单数据
const initialFormData = {
    id: '',
    config_type: '',
    goods_id: '',
    capacity: '',
    group_key: '',
    adjustment_type: '',
    adjustment_value: '',
    is_enable: 1,
    title: ''
}
const formData: Record<string, any> = reactive({ ...initialFormData })

// SKU选择相关
const selectedQuotationId = ref<number | null>(null)
const quotationList = ref<Array<any>>([])
const selectedModelIds = ref<number[]>([])
const modelList = ref<Array<any>>([])
const skuList = ref<Array<any>>([])
const selectedSkuKeys = ref<string[]>([])
const skuListLoading = ref(false)

// 筛选条件
const filterGrade = ref('')
const filterCapacity = ref('')
const searchKeyword = ref('')

// 分页
const currentPage = ref(1)
const pageSize = ref(50)

// 表单验证规则
const formRules = computed(() => {
    const rules: Record<string, any[]> = {
        config_type: [{ required: true, message: '请选择配置类型', trigger: 'change' }],
        adjustment_type: [{ required: true, message: '请选择调整类型', trigger: 'change' }],
        adjustment_value: [{ required: true, message: '请输入调整值', trigger: 'blur' }]
    }

    if (formData.config_type === 2) {
        rules.goods_id = [{ required: true, message: '请输入设备ID', trigger: 'blur' }]
        rules.capacity = [{ required: true, message: '请输入容量', trigger: 'blur' }]
    } else if (formData.config_type === 3) {
        rules.goods_id = [{ required: true, message: '请输入设备ID', trigger: 'blur' }]
    } else if (formData.config_type === 4) {
        rules.group_key = [{ required: true, message: '请输入分组标识', trigger: 'blur' }]
    }

    return rules
})

// 可用的等级列表
const availableGrades = computed(() => {
    const grades = new Set<string>()
    skuList.value.forEach(sku => {
        if (sku.config_item_name) grades.add(sku.config_item_name)
    })
    return Array.from(grades).sort()
})

// 可用的容量列表
const availableCapacities = computed(() => {
    const capacities = new Set<string>()
    skuList.value.forEach(sku => {
        if (sku.capacity) capacities.add(sku.capacity)
    })
    return Array.from(capacities).sort((a, b) => {
        const getSize = (cap: string) => {
            const num = parseInt(cap)
            if (cap.includes('T')) return num * 1024
            return num
        }
        return getSize(a) - getSize(b)
    })
})

// 筛选后的SKU列表
const filteredSkuList = computed(() => {
    let filtered = skuList.value

    if (filterGrade.value) {
        filtered = filtered.filter(sku => sku.config_item_name === filterGrade.value)
    }

    if (filterCapacity.value) {
        filtered = filtered.filter(sku => sku.capacity === filterCapacity.value)
    }

    if (searchKeyword.value) {
        const keyword = searchKeyword.value.toLowerCase()
        filtered = filtered.filter(sku =>
            sku.goods_name.toLowerCase().includes(keyword)
        )
    }

    return filtered
})

// 分页后的SKU列表
const paginatedSkuList = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value
    const end = start + pageSize.value
    return filteredSkuList.value.slice(start, end)
})

// 获取等级数量
const getGradeCount = (grade: string) => {
    return skuList.value.filter(sku => sku.config_item_name === grade).length
}

// 获取容量数量
const getCapacityCount = (capacity: string) => {
    return skuList.value.filter(sku => sku.capacity === capacity).length
}

// 检查SKU是否已在列表中
const isSkuInList = (sku: any): boolean => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return false
    return formData.sku_list.some((item: any) =>
        item.goods_id === sku.goods_id &&
        item.capacity === sku.capacity &&
        (item.config_item_name || '') === (sku.config_item_name || '')
    )
}

// 检查行是否可选
const checkSelectable = (row: any) => {
    return !isSkuInList(row)
}

// 加载报价单列表
const loadQuotationList = async () => {
    try {
        const res = await getQuotationConfigList({ limit: 200 })
        quotationList.value = res.data.data || []
    } catch (error) {
        console.error('加载报价单列表失败:', error)
    }
}

// 报价单变化
const handleQuotationChange = () => {
    selectedModelIds.value = []
    skuList.value = []
    selectedSkuKeys.value = []
    if (selectedQuotationId.value) {
        loadModelList()
    }
}

// 加载型号列表
const loadModelList = async () => {
    if (!selectedQuotationId.value) return

    try {
        const quotation = quotationList.value.find(q => q.id === selectedQuotationId.value)
        if (!quotation) return

        const res = await getQuotationDataCascadeOptions({
            quotation_id: quotation.quotation_id,
            price_name: quotation.price_name
        })
        const data = res.data || {}
        modelList.value = (data.models || []).map((item: any) => ({
            goods_id: item.goods_id,
            goods_name: item.goods_name
        }))
    } catch (error) {
        console.error('加载型号列表失败:', error)
    }
}

// 型号变化
const handleModelChange = async () => {
    selectedSkuKeys.value = []
    if (selectedModelIds.value.length > 0) {
        await loadSkuList()
    } else {
        skuList.value = []
    }
}

// 加载SKU列表
const loadSkuList = async () => {
    if (!selectedQuotationId.value || selectedModelIds.value.length === 0) {
        skuList.value = []
        return
    }

    skuListLoading.value = true
    try {
        const quotation = quotationList.value.find(q => q.id === selectedQuotationId.value)
        if (!quotation) return

        const res = await getQuotationDataCascadeOptions({
            quotation_id: quotation.quotation_id,
            price_name: quotation.price_name
        })
        const data = res.data || {}
        const cascadeOptions = data.cascade_options || []

        const skus: Array<any> = []
        cascadeOptions.forEach((model: any) => {
            if (!selectedModelIds.value.includes(model.goods_id)) return

            const capacities = model.children || []
            capacities.forEach((capacity: any) => {
                const configItems = capacity.children || []
                if (configItems.length > 0) {
                    configItems.forEach((configItem: any) => {
                        skus.push({
                            key: `${model.goods_id}_${capacity.value}_${configItem.value}`,
                            goods_id: model.goods_id,
                            goods_name: model.goods_name,
                            capacity: capacity.value,
                            capacity_answer_id: capacity.capacity_answer_id || 0,
                            config_item_name: configItem.value
                        })
                    })
                } else {
                    skus.push({
                        key: `${model.goods_id}_${capacity.value}_`,
                        goods_id: model.goods_id,
                        goods_name: model.goods_name,
                        capacity: capacity.value,
                        capacity_answer_id: capacity.capacity_answer_id || 0,
                        config_item_name: ''
                    })
                }
            })
        })

        skuList.value = skus

        // 编辑模式：自动选中已有的SKU
        if (formData.id && formData.sku_list && Array.isArray(formData.sku_list)) {
            await nextTick()
            formData.sku_list.forEach((existingSku: any) => {
                const sku = skus.find(s =>
                    s.goods_id === existingSku.goods_id &&
                    s.capacity === existingSku.capacity &&
                    (s.config_item_name || '') === (existingSku.config_item_name || '')
                )
                if (sku && !selectedSkuKeys.value.includes(sku.key)) {
                    selectedSkuKeys.value.push(sku.key)
                }
            })
            syncTableSelection()
        }
    } catch (error) {
        console.error('加载SKU列表失败:', error)
        skuList.value = []
    } finally {
        skuListLoading.value = false
    }
}

// 表格选择变化
const handleSelectionChange = (selection: any[]) => {
    selectedSkuKeys.value = selection.map(item => item.key)
}

// 同步表格选择状态
const syncTableSelection = () => {
    if (!skuTableRef.value) return
    nextTick(() => {
        skuTableRef.value.clearSelection()
        paginatedSkuList.value.forEach((row: any) => {
            if (selectedSkuKeys.value.includes(row.key)) {
                skuTableRef.value.toggleRowSelection(row, true)
            }
        })
    })
}

// 监听分页变化，同步选择状态
watch([currentPage, pageSize], () => {
    syncTableSelection()
})

// 全选当前筛选结果
const selectAllFiltered = () => {
    const availableSkus = filteredSkuList.value.filter(sku => !isSkuInList(sku))
    const keys = availableSkus.map(sku => sku.key)
    selectedSkuKeys.value = [...new Set([...selectedSkuKeys.value, ...keys])]
    syncTableSelection()
}

// 按等级选择
const selectByGrade = async () => {
    const { value: grade } = await ElMessageBox.prompt('请选择要批量选择的等级', '按等级选择', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        inputType: 'select',
        inputOptions: availableGrades.value.reduce((acc: any, g: string) => {
            acc[g] = `${g} (${getGradeCount(g)}个)`
            return acc
        }, {})
    })
    if (grade) {
        const skus = skuList.value.filter(sku => sku.config_item_name === grade && !isSkuInList(sku))
        const keys = skus.map(sku => sku.key)
        selectedSkuKeys.value = [...new Set([...selectedSkuKeys.value, ...keys])]
        syncTableSelection()
        ElMessage.success(`已选择 ${keys.length} 个"${grade}"等级的SKU`)
    }
}

// 按容量选择
const selectByCapacity = async () => {
    const { value: capacity } = await ElMessageBox.prompt('请选择要批量选择的容量', '按容量选择', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        inputType: 'select',
        inputOptions: availableCapacities.value.reduce((acc: any, c: string) => {
            acc[c] = `${c} (${getCapacityCount(c)}个)`
            return acc
        }, {})
    })
    if (capacity) {
        const skus = skuList.value.filter(sku => sku.capacity === capacity && !isSkuInList(sku))
        const keys = skus.map(sku => sku.key)
        selectedSkuKeys.value = [...new Set([...selectedSkuKeys.value, ...keys])]
        syncTableSelection()
        ElMessage.success(`已选择 ${keys.length} 个"${capacity}"容量的SKU`)
    }
}

// 清空选择
const clearSelection = () => {
    selectedSkuKeys.value = []
    syncTableSelection()
}

// 配置类型变化
const onConfigTypeChange = () => {
    if (formData.config_type !== 1) {
        selectedQuotationId.value = null
        selectedModelIds.value = []
        skuList.value = []
        selectedSkuKeys.value = []
    }
}

// 确认提交
const confirm = async () => {
    if (!formRef.value) return

    await formRef.value.validate(async (valid) => {
        if (!valid) return

        // SKU级别配置验证
        if (formData.config_type === 1 && selectedSkuKeys.value.length === 0) {
            ElMessage.warning('请至少选择一个SKU')
            return
        }

        loading.value = true
        try {
            if (formData.config_type === 1 && selectedSkuKeys.value.length > 0) {
                // 批量添加SKU配置
                const skus = selectedSkuKeys.value.map(key => {
                    const sku = skuList.value.find(s => s.key === key)
                    return sku ? {
                        goods_id: sku.goods_id,
                        goods_name: sku.goods_name,
                        capacity: sku.capacity,
                        capacity_answer_id: sku.capacity_answer_id,
                        config_item_name: sku.config_item_name
                    } : null
                }).filter(Boolean)

                await batchAddSkuPriceConfig({
                    skus,
                    adjustment_type: formData.adjustment_type,
                    adjustment_value: formData.adjustment_value,
                    is_enable: formData.is_enable,
                    title: formData.title
                })
                ElMessage.success(`成功为 ${skus.length} 个SKU创建价格配置`)
            } else {
                // 其他配置类型
                const save = formData.id ? editQuotationPriceConfig : addQuotationPriceConfig
                await save({ ...formData })
            }

            showDialog.value = false
            emit('complete')
        } catch (error) {
            console.error('保存失败:', error)
        } finally {
            loading.value = false
        }
    })
}

// 设置表单数据
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    selectedQuotationId.value = null
    selectedModelIds.value = []
    skuList.value = []
    selectedSkuKeys.value = []
    filterGrade.value = ''
    filterCapacity.value = ''
    searchKeyword.value = ''
    currentPage.value = 1

    await loadQuotationList()

    if (row) {
        const data = await (await getQuotationPriceConfigInfo(row.id)).data
        if (data) {
            Object.keys(formData).forEach((key: string) => {
                if (data[key] !== undefined) {
                    formData[key] = data[key]
                }
            })
        }
    }
}

const emit = defineEmits(['complete'])

defineExpose({
    showDialog,
    setFormData
})
</script>

<style scoped>
.sku-table-container {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    background: #fafafa;
}
</style>
