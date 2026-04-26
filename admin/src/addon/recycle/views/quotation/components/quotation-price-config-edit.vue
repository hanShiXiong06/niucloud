<template>
    <el-dialog v-model="showDialog" :title="formData.id ? '编辑价格配置' : '添加价格配置'" width="1200px" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
            <el-form-item label="标题" prop="title">
                <el-input v-model="formData.title"  clearable placeholder="请输入标题" class="input-width" />
            </el-form-item>
            <el-form-item label="配置类型" prop="config_type">
                <el-select
                    v-model="formData.config_type"
                    clearable
                    placeholder="请选择配置类型"
                    class="input-width"
                    @change="onConfigTypeChange"
                    :disabled="!!(formData.id && formData.sku_list && Array.isArray(formData.sku_list) && formData.sku_list.length > 0)"
                >
                    <el-option label="SKU级别" :value="1">
                        <span>SKU级别（型号+内存+配置项）</span>
                    </el-option>
                    <el-option label="型号+内存" :value="2">
                        <span>型号+内存（批量管理）</span>
                    </el-option>
                    <el-option label="型号" :value="3">
                        <span>型号</span>
                    </el-option>
                    <el-option label="分组" :value="4">
                        <span>分组</span>
                    </el-option>
                </el-select>
                <div v-if="formData.id && formData.sku_list && Array.isArray(formData.sku_list) && formData.sku_list.length > 0" class="text-xs text-gray-500 mt-1">
                    此配置使用SKU列表，无法修改配置类型
                </div>
            </el-form-item>

            <!-- SKU级别配置：支持批量SKU选择 -->
            <template v-if="formData.config_type === 1">
                <!-- 编辑模式：显示已有的SKU列表（可删除） -->
                <div v-if="formData.id && formData.sku_list && Array.isArray(formData.sku_list) && formData.sku_list.length > 0" class="mb-4">
                    <el-divider content-position="left">
                        <span class="text-sm">当前SKU列表（{{ formData.sku_list.length }}个）</span>
                    </el-divider>

                    <div class="border rounded bg-gray-50 mb-4">
                        <!-- 操作栏 -->
                        <div class="flex items-center justify-between p-2 border-b bg-white">
                            <div class="flex items-center gap-2">
                                <el-checkbox
                                    v-model="selectAllExistingSkus"
                                    :indeterminate="isIndeterminate"
                                    @change="handleSelectAllExisting"
                                >
                                    全选
                                </el-checkbox>
                                <span v-if="selectedExistingSkuIndexes.length > 0" class="text-xs text-blue-600">
                                    已选 {{ selectedExistingSkuIndexes.length }} 个
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- 快速筛选 -->
                                <el-dropdown v-if="existingGrades.length > 0" trigger="click" @command="handleQuickDeleteByGrade">
                                    <el-button size="small" text type="primary">
                                        按等级删除 <el-icon class="ml-1"><ArrowDown /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="grade in existingGrades"
                                                :key="grade"
                                                :command="grade"
                                            >
                                                {{ grade }} ({{ getExistingGradeCount(grade) }}个)
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-dropdown v-if="existingCapacities.length > 0" trigger="click" @command="handleQuickDeleteByCapacity">
                                    <el-button size="small" text type="success">
                                        按容量删除 <el-icon class="ml-1"><ArrowDown /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="capacity in existingCapacities"
                                                :key="capacity"
                                                :command="capacity"
                                            >
                                                {{ capacity }} ({{ getExistingCapacityCount(capacity) }}个)
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-button
                                    v-if="selectedExistingSkuIndexes.length > 0"
                                    type="danger"
                                    size="small"
                                    @click="batchRemoveSkusFromList"
                                >
                                    批量删除 ({{ selectedExistingSkuIndexes.length }})
                                </el-button>
                            </div>
                        </div>

                        <!-- SKU列表 -->
                        <div class="max-h-[280px] overflow-y-auto p-2">
                            <div class="grid grid-cols-1 gap-1">
                                <div
                                    v-for="(sku, index) in formData.sku_list"
                                    :key="`existing-${index}`"
                                    class="flex items-center justify-between text-sm p-2 bg-white rounded border hover:bg-blue-50 transition-colors"
                                    :class="{ 'bg-blue-50 border-blue-300': selectedExistingSkuIndexes.includes(index) }"
                                >
                                    <div class="flex items-center gap-2 flex-1">
                                        <el-checkbox
                                            :model-value="selectedExistingSkuIndexes.includes(index)"
                                            @change="handleExistingSkuSelect(index, $event)"
                                        />
                                        <span class="font-medium text-gray-800">{{ sku.goods_name || ('型号ID: ' + sku.goods_id) }}</span>
                                        <span class="text-gray-300">|</span>
                                        <el-tag size="small" type="success" effect="plain" class="!py-0">{{ sku.capacity || '-' }}</el-tag>
                                        <template v-if="sku.config_item_name">
                                            <span class="text-gray-300">|</span>
                                            <el-tag size="small" type="primary" effect="plain" class="!py-0">{{ sku.config_item_name }}</el-tag>
                                        </template>
                                    </div>
                                    <el-button
                                        type="danger"
                                        size="small"
                                        text
                                        @click="removeSkuFromList(index)"
                                        :icon="Delete"
                                    >
                                        删除
                                    </el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SKU选择区域（添加模式或编辑模式添加新SKU） -->
                <div class="mb-4">
                    <el-divider content-position="left">批量SKU选择</el-divider>

                    <!-- 报价单选择 -->
                    <el-form-item label="报价单" prop="quotation_id">
                        <el-select
                            v-model="selectedQuotationId"
                            placeholder="请选择报价单"
                            style="width: 400px"
                            @change="handleQuotationChange"
                            clearable
                        >
                            <el-option
                                v-for="quotation in quotationList"
                                :key="quotation.id"
                                :label="`${quotation.quotation_id} - ${quotation.price_name}`"
                                :value="quotation.id"
                            >
                                <div>
                                    <div>{{ quotation.quotation_id }} - {{ quotation.price_name }}</div>
                                    <div style="font-size: 12px; color: #909399;">{{ quotation.config_name || '未命名配置' }}</div>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>

                    <!-- 型号选择 -->
                    <el-form-item label="型号" prop="model_ids" v-if="selectedQuotationId">
                        <el-select
                            v-model="selectedModelIds"
                            multiple
                            filterable
                            placeholder="请选择型号（可多选）"
                            style="width: 400px"
                            :loading="modelListLoading"
                            @change="handleModelChange"
                            clearable
                        >
                            <el-option
                                v-for="model in modelList"
                                :key="model.goods_id"
                                :label="model.goods_name"
                                :value="model.goods_id"
                            />
                        </el-select>
                        <el-button v-if="selectedModelIds.length > 0" @click="clearModelSelection" size="small" style="margin-left: 10px">清空选择</el-button>
                    </el-form-item>

                    <!-- SKU选择区域 -->
                    <div v-if="selectedModelIds.length > 0" class="mb-4">
                        <!-- 标题栏 -->
                        <div class="flex items-center justify-between mb-2 pb-2 border-b">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-700">SKU选择</span>
                                <el-tag size="small" type="info" effect="plain">型号+容量+等级</el-tag>
                                <span v-if="selectedSkuKeys.length > 0" class="text-xs text-blue-600">
                                    已选 {{ selectedSkuKeys.length }} 个
                                </span>
                            </div>
                            <div class="flex gap-1">
                                <el-button size="small" @click="selectAllFilteredSkus">全选筛选结果</el-button>
                                <el-button size="small" @click="clearSkuSelection">清空</el-button>
                                <el-button
                                    v-if="formData.id && selectedSkuKeys.length > 0"
                                    type="primary"
                                    size="small"
                                    @click="addSkusToList"
                                >
                                    添加 ({{ selectedSkuKeys.length }})
                                </el-button>
                            </div>
                        </div>

                        <!-- 紧凑型筛选区域 -->
                        <div class="mb-2 p-2 bg-gray-50 rounded border border-gray-200">
                            <div class="grid grid-cols-2 gap-3">
                                <!-- 等级筛选 -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs font-medium text-gray-600 flex items-center gap-1">
                                            <el-icon class="text-blue-500" :size="14"><PriceTag /></el-icon>
                                            等级
                                            <span v-if="selectedGradeFilters.length > 0" class="text-blue-600">
                                                ({{ selectedGradeFilters.length }})
                                            </span>
                                        </span>
                                        <el-button
                                            v-if="selectedGradeFilters.length > 0"
                                            size="small"
                                            text
                                            type="primary"
                                            @click="clearGradeFilters"
                                            class="h-5 text-xs"
                                        >
                                            清除
                                        </el-button>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag
                                            v-for="grade in availableGrades"
                                            :key="grade"
                                            :type="selectedGradeFilters.includes(grade) ? 'primary' : ''"
                                            :effect="selectedGradeFilters.includes(grade) ? 'dark' : 'plain'"
                                            class="cursor-pointer transition-all hover:scale-105"
                                            size="small"
                                            @click="toggleGradeFilter(grade)"
                                        >
                                            {{ grade }} <span class="text-xs opacity-75">({{ getGradeCount(grade) }})</span>
                                        </el-tag>
                                        <span v-if="availableGrades.length === 0" class="text-xs text-gray-400">暂无</span>
                                    </div>
                                </div>

                                <!-- 容量筛选 -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-xs font-medium text-gray-600 flex items-center gap-1">
                                            <el-icon class="text-green-500" :size="14"><Coin /></el-icon>
                                            容量
                                            <span v-if="selectedCapacityFilters.length > 0" class="text-green-600">
                                                ({{ selectedCapacityFilters.length }})
                                            </span>
                                        </span>
                                        <el-button
                                            v-if="selectedCapacityFilters.length > 0"
                                            size="small"
                                            text
                                            type="success"
                                            @click="clearCapacityFilters"
                                            class="h-5 text-xs"
                                        >
                                            清除
                                        </el-button>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag
                                            v-for="capacity in availableCapacities"
                                            :key="capacity"
                                            :type="selectedCapacityFilters.includes(capacity) ? 'success' : ''"
                                            :effect="selectedCapacityFilters.includes(capacity) ? 'dark' : 'plain'"
                                            class="cursor-pointer transition-all hover:scale-105"
                                            size="small"
                                            @click="toggleCapacityFilter(capacity)"
                                        >
                                            {{ capacity }} <span class="text-xs opacity-75">({{ getCapacityCount(capacity) }})</span>
                                        </el-tag>
                                    </div>
                                </div>
                            </div>

                            <!-- 筛选结果统计 - 紧凑显示 -->
                            <div v-if="selectedGradeFilters.length > 0 || selectedCapacityFilters.length > 0"
                                 class="mt-2 pt-2 border-t border-gray-200 flex items-center justify-between">
                                <span class="text-xs text-gray-600 flex items-center gap-1">
                                    <el-icon :size="12"><Filter /></el-icon>
                                    筛选结果：
                                    <span class="font-medium text-orange-600">{{ filteredSkuList.length }}</span>
                                    <span class="text-gray-400">/</span>
                                    <span>{{ skuList.length }}</span>
                                </span>
                                <el-button
                                    size="small"
                                    text
                                    type="warning"
                                    @click="clearAllFilters"
                                    class="h-5 text-xs"
                                >
                                    清除所有筛选
                                </el-button>
                            </div>
                        </div>

                        <!-- SKU列表 - 紧凑显示 -->
                        <div v-loading="skuListLoading" class="max-h-[320px] overflow-y-auto border rounded bg-white">
                            <el-checkbox-group v-model="selectedSkuKeys" @change="handleSkuChange">
                                <div v-for="sku in filteredSkuList" :key="sku.key"
                                     class="px-3 py-1.5 border-b border-gray-100 hover:bg-blue-50 transition-colors last:border-b-0">
                                    <el-checkbox :label="sku.key" :disabled="isSkuInList(sku)" class="w-full">
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="font-medium text-gray-800">{{ sku.goods_name }}</span>
                                            <span class="text-gray-300">|</span>
                                            <el-tag size="small" type="success" effect="plain" class="!py-0">{{ sku.capacity }}</el-tag>
                                            <template v-if="sku.config_item_name">
                                                <span class="text-gray-300">|</span>
                                                <el-tag size="small" type="primary" effect="plain" class="!py-0">{{ sku.config_item_name }}</el-tag>
                                            </template>
                                            <el-tag v-if="isSkuInList(sku)" size="small" type="info" effect="plain" class="!py-0">已添加</el-tag>
                                        </div>
                                    </el-checkbox>
                                </div>
                            </el-checkbox-group>
                            <div v-if="filteredSkuList.length === 0 && !skuListLoading" class="text-center py-6">
                                <el-icon class="text-3xl text-gray-300 mb-1"><DocumentDelete /></el-icon>
                                <div class="text-xs text-gray-400">
                                    {{ skuList.length > 0 ? '没有符合筛选条件的SKU' : '暂无SKU数据' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- 其他配置类型：使用单个字段输入 -->
            <template v-else>
                <el-form-item label="设备ID" prop="goods_id" v-if="formData.config_type === 2 || formData.config_type === 3">
                    <el-input-number v-model="formData.goods_id" :min="0" placeholder="请输入设备ID" class="input-width" style="width: 100%" />
                </el-form-item>

                <el-form-item label="容量" prop="capacity" v-if="formData.config_type === 2">
                    <el-input v-model="formData.capacity" clearable placeholder="请输入容量" class="input-width" />
                </el-form-item>

                <el-form-item label="分组标识" prop="group_key" v-if="formData.config_type === 4">
                    <el-input v-model="formData.group_key" clearable placeholder="请输入分组标识" class="input-width" />
                </el-form-item>
            </template>

            <el-form-item label="调整类型" prop="adjustment_type">
                <el-select v-model="formData.adjustment_type" clearable placeholder="请选择调整类型" class="input-width">
                    <el-option label="固定金额" :value="1">
                        <span>固定金额（正数增加，负数减少）</span>
                    </el-option>
                    <el-option label="百分比" :value="2">
                        <span>百分比（正数增加，负数减少）</span>
                    </el-option>
                    <el-option label="直接覆盖" :value="3">
                        <span>直接覆盖（直接设置为指定价格）</span>
                    </el-option>
                </el-select>
            </el-form-item>

            <el-form-item label="调整值" prop="adjustment_value">
                <el-input-number
                    v-model="formData.adjustment_value"
                    :precision="2"
                    :min="formData.adjustment_type === 2 ? -100 : undefined"
                    :max="formData.adjustment_type === 2 ? 100 : undefined"
                    placeholder="请输入调整值"
                    class="input-width"
                    style="width: 100%"
                >
                    <template #append v-if="formData.adjustment_type === 2">%</template>
                    <template #append v-else-if="formData.adjustment_type !== 2">元</template>
                </el-input-number>
                <div class="text-xs text-gray-500 mt-1">
                    <span v-if="formData.adjustment_type === 1">固定金额：正数增加价格，负数减少价格</span>
                    <span v-else-if="formData.adjustment_type === 2">百分比：正数增加百分比，负数减少百分比（范围：-100% ~ 100%）</span>
                    <span v-else-if="formData.adjustment_type === 3">直接覆盖：直接将价格设置为该值</span>
                </div>
            </el-form-item>

            <el-form-item label="是否启用" prop="is_enable">
                <el-switch v-model="formData.is_enable" :active-value="1" :inactive-value="0" />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">
                    确定
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import type { FormInstance } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Select, Close, Plus, PriceTag, Coin, Filter, Check, DocumentDelete, ArrowDown, Search } from '@element-plus/icons-vue'
import { addQuotationPriceConfig, editQuotationPriceConfig, getQuotationPriceConfigInfo, batchAddSkuPriceConfig, getQuotationConfigList, getQuotationDataCascadeOptions } from '@/addon/recycle/api/quotation'

const showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    config_type: '',
    goods_id: '',
    capacity: '',
    capacity_answer_id: '',
    config_item_name: '',
    group_key: '',
    adjustment_type: '',
    adjustment_value: '',
    is_enable: 1,
    sku_list: null,
    title: ''
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 报价单和型号选择
const selectedQuotationId = ref<number | null>(null)
const quotationList = ref<Array<{ id: number, quotation_id: number, price_name: string, config_name: string }>>([])
const selectedModelIds = ref<number[]>([])
const modelList = ref<Array<{ goods_id: number, goods_name: string }>>([])
const modelListLoading = ref(false)

// SKU列表和选择
const skuList = ref<Array<{
    key: string,
    goods_id: number,
    goods_name: string,
    capacity: string,
    capacity_answer_id: number,
    config_item_name: string
}>>([])
const selectedSkuKeys = ref<string[]>([])
const skuListLoading = ref(false)

// 筛选条件
const selectedGradeFilters = ref<string[]>([])
const selectedCapacityFilters = ref<string[]>([])

// 已有SKU列表的批量选择
const selectedExistingSkuIndexes = ref<number[]>([])
const selectAllExistingSkus = ref(false)

// 当前选中的报价单信息
const currentQuotationInfo = computed(() => {
    return quotationList.value.find(q => q.id === selectedQuotationId.value) || null
})

const currentQuotationId = computed(() => {
    return currentQuotationInfo.value?.quotation_id || null
})

const currentPriceName = computed(() => {
    return currentQuotationInfo.value?.price_name || ''
})

// 可用的等级列表（从SKU列表中提取）
const availableGrades = computed(() => {
    const grades = new Set<string>()
    skuList.value.forEach(sku => {
        if (sku.config_item_name) {
            grades.add(sku.config_item_name)
        }
    })
    return Array.from(grades).sort()
})

// 可用的容量列表（从SKU列表中提取）
const availableCapacities = computed(() => {
    const capacities = new Set<string>()
    skuList.value.forEach(sku => {
        if (sku.capacity) {
            capacities.add(sku.capacity)
        }
    })
    // 自定义排序：按容量大小排序
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

    // 按等级筛选
    if (selectedGradeFilters.value.length > 0) {
        filtered = filtered.filter(sku =>
            selectedGradeFilters.value.includes(sku.config_item_name)
        )
    }

    // 按容量筛选
    if (selectedCapacityFilters.value.length > 0) {
        filtered = filtered.filter(sku =>
            selectedCapacityFilters.value.includes(sku.capacity)
        )
    }

    return filtered
})

// 已有SKU列表中的等级列表
const existingGrades = computed(() => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return []
    const grades = new Set<string>()
    formData.sku_list.forEach((sku: any) => {
        if (sku.config_item_name) {
            grades.add(sku.config_item_name)
        }
    })
    return Array.from(grades).sort()
})

// 已有SKU列表中的容量列表
const existingCapacities = computed(() => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return []
    const capacities = new Set<string>()
    formData.sku_list.forEach((sku: any) => {
        if (sku.capacity) {
            capacities.add(sku.capacity)
        }
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

// 全选状态的半选状态
const isIndeterminate = computed(() => {
    const total = formData.sku_list?.length || 0
    const selected = selectedExistingSkuIndexes.value.length
    return selected > 0 && selected < total
})

// 表单验证规则
const formRules = computed(() => {
    const rules: Record<string, any[]> = {
        config_type: [
            { required: true, message: '请选择配置类型', trigger: 'change' }
        ],
        adjustment_type: [
            { required: true, message: '请选择调整类型', trigger: 'change' }
        ],
        adjustment_value: [
            { required: true, message: '请输入调整值', trigger: 'blur' }
        ]
    }

    // 根据配置类型动态添加必填项
    if (formData.config_type === 2) {
        // 型号+内存：需要设备ID、容量
        rules.goods_id = [{ required: true, message: '请输入设备ID', trigger: 'blur' }]
        rules.capacity = [{ required: true, message: '请输入容量', trigger: 'blur' }]
    } else if (formData.config_type === 3) {
        // 型号：需要设备ID
        rules.goods_id = [{ required: true, message: '请输入设备ID', trigger: 'blur' }]
    } else if (formData.config_type === 4) {
        // 分组：需要分组标识
        rules.group_key = [{ required: true, message: '请输入分组标识', trigger: 'blur' }]
    }

    return rules
})

const emit = defineEmits(['complete'])

/**
 * 加载报价单列表
 */
const loadQuotationList = async () => {
    try {
        const res = await getQuotationConfigList({ limit: 120 })
        quotationList.value = (res.data.data || []).map((item: any) => ({
            id: item.id,
            quotation_id: item.quotation_id,
            price_name: item.price_name,
            config_name: item.config_name || ''
        }))

        if (res.data.total > 120) {
            const totalPages = Math.ceil(res.data.total / 120)
            for (let page = 2; page <= totalPages; page++) {
                const nextRes = await getQuotationConfigList({ limit: 120, page })
                const nextData = (nextRes.data.data || []).map((item: any) => ({
                    id: item.id,
                    quotation_id: item.quotation_id,
                    price_name: item.price_name,
                    config_name: item.config_name || ''
                }))
                quotationList.value.push(...nextData)
            }
        }
    } catch (error) {
        console.error('加载报价单列表失败:', error)
    }
}

/**
 * 报价单变化
 */
const handleQuotationChange = () => {
    selectedModelIds.value = []
    selectedSkuKeys.value = []
    skuList.value = []
    // 清除筛选条件
    selectedGradeFilters.value = []
    selectedCapacityFilters.value = []
    if (selectedQuotationId.value) {
        loadModelList()
    }
}

/**
 * 加载型号列表
 */
const loadModelList = async () => {
    if (!currentQuotationId.value || !currentPriceName.value) {
        modelList.value = []
        return
    }

    modelListLoading.value = true
    try {
        const res = await getQuotationDataCascadeOptions({
            quotation_id: currentQuotationId.value,
            price_name: currentPriceName.value
        })
        const data = res.data || {}
        const models = data.models || []
        modelList.value = models.map((item: any) => ({
            goods_id: item.goods_id,
            goods_name: item.goods_name
        }))
    } catch (error) {
        console.error('加载型号列表失败:', error)
        modelList.value = []
    } finally {
        modelListLoading.value = false
    }
}

/**
 * 型号选择变化 - 加载SKU列表
 */
const handleModelChange = async (modelIds: number[]) => {
    selectedModelIds.value = modelIds
    selectedSkuKeys.value = []
    // 清除筛选条件
    selectedGradeFilters.value = []
    selectedCapacityFilters.value = []

    if (modelIds.length > 0 && currentQuotationId.value && currentPriceName.value) {
        await loadSkuList()
    } else {
        skuList.value = []
    }
}

/**
 * 加载SKU列表
 */
const loadSkuList = async () => {
    if (!currentQuotationId.value || !currentPriceName.value || selectedModelIds.value.length === 0) {
        skuList.value = []
        return
    }

    skuListLoading.value = true
    try {
        const res = await getQuotationDataCascadeOptions({
            quotation_id: currentQuotationId.value,
            price_name: currentPriceName.value
        })
        const data = res.data || {}
        const cascadeOptions = data.cascade_options || []

        const skus: Array<{
            key: string,
            goods_id: number,
            goods_name: string,
            capacity: string,
            capacity_answer_id: number,
            config_item_name: string
        }> = []

        cascadeOptions.forEach((model: any) => {
            if (!selectedModelIds.value.includes(model.goods_id)) {
                return
            }

            const capacities = model.children || []
            capacities.forEach((capacity: any) => {
                const configItems = capacity.children || []

                if (configItems.length > 0) {
                    configItems.forEach((configItem: any) => {
                        const skuKey = `${model.goods_id}_${capacity.value}_${configItem.value}`
                        skus.push({
                            key: skuKey,
                            goods_id: model.goods_id,
                            goods_name: model.goods_name,
                            capacity: capacity.value,
                            capacity_answer_id: capacity.capacity_answer_id || 0,
                            config_item_name: configItem.value
                        })
                    })
                } else {
                    const skuKey = `${model.goods_id}_${capacity.value}_`
                    skus.push({
                        key: skuKey,
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
    } catch (error) {
        console.error('加载SKU列表失败:', error)
        skuList.value = []
    } finally {
        skuListLoading.value = false
    }
}

/**
 * SKU选择变化
 */
const handleSkuChange = (selected: string[]) => {
    selectedSkuKeys.value = selected
}

/**
 * 全选SKU（筛选后的）
 */
const selectAllFilteredSkus = () => {
    const filteredKeys = filteredSkuList.value
        .filter(sku => !isSkuInList(sku))
        .map(sku => sku.key)

    // 合并已选择的和新选择的（去重）
    const allKeys = new Set([...selectedSkuKeys.value, ...filteredKeys])
    selectedSkuKeys.value = Array.from(allKeys)
}

/**
 * 切换等级筛选（并自动选中/取消选中）
 */
const toggleGradeFilter = (grade: string) => {
    const index = selectedGradeFilters.value.indexOf(grade)

    if (index > -1) {
        // 取消筛选：移除筛选条件，并取消选中该等级的所有SKU
        selectedGradeFilters.value.splice(index, 1)

        // 取消选中该等级的SKU
        const gradeSkuKeys = skuList.value
            .filter(sku => sku.config_item_name === grade)
            .map(sku => sku.key)

        selectedSkuKeys.value = selectedSkuKeys.value.filter(
            key => !gradeSkuKeys.includes(key)
        )
    } else {
        // 添加筛选：添加筛选条件，并自动选中该等级的所有SKU
        selectedGradeFilters.value.push(grade)

        // 自动选中该等级的SKU（排除已在列表中的）
        const gradeSkuKeys = skuList.value
            .filter(sku => sku.config_item_name === grade && !isSkuInList(sku))
            .map(sku => sku.key)

        // 合并到已选择的SKU中（去重）
        const allKeys = new Set([...selectedSkuKeys.value, ...gradeSkuKeys])
        selectedSkuKeys.value = Array.from(allKeys)

        // 提示用户
        if (gradeSkuKeys.length > 0) {
            ElMessage.success(`已自动选中 ${gradeSkuKeys.length} 个"${grade}"等级的SKU`)
        }
    }
}

/**
 * 切换容量筛选（并自动选中/取消选中）
 */
const toggleCapacityFilter = (capacity: string) => {
    const index = selectedCapacityFilters.value.indexOf(capacity)

    if (index > -1) {
        // 取消筛选：移除筛选条件，并取消选中该容量的所有SKU
        selectedCapacityFilters.value.splice(index, 1)

        // 取消选中该容量的SKU
        const capacitySkuKeys = skuList.value
            .filter(sku => sku.capacity === capacity)
            .map(sku => sku.key)

        selectedSkuKeys.value = selectedSkuKeys.value.filter(
            key => !capacitySkuKeys.includes(key)
        )
    } else {
        // 添加筛选：添加筛选条件，并自动选中该容量的所有SKU
        selectedCapacityFilters.value.push(capacity)

        // 自动选中该容量的SKU（排除已在列表中的）
        const capacitySkuKeys = skuList.value
            .filter(sku => sku.capacity === capacity && !isSkuInList(sku))
            .map(sku => sku.key)

        // 合并到已选择的SKU中（去重）
        const allKeys = new Set([...selectedSkuKeys.value, ...capacitySkuKeys])
        selectedSkuKeys.value = Array.from(allKeys)

        // 提示用户
        if (capacitySkuKeys.length > 0) {
            ElMessage.success(`已自动选中 ${capacitySkuKeys.length} 个"${capacity}"容量的SKU`)
        }
    }
}

/**
 * 清除等级筛选
 */
const clearGradeFilters = () => {
    selectedGradeFilters.value = []
}

/**
 * 清除容量筛选
 */
const clearCapacityFilters = () => {
    selectedCapacityFilters.value = []
}

/**
 * 清除所有筛选
 */
const clearAllFilters = () => {
    selectedGradeFilters.value = []
    selectedCapacityFilters.value = []
}

/**
 * 获取指定等级的SKU数量
 */
const getGradeCount = (grade: string) => {
    return skuList.value.filter(sku => sku.config_item_name === grade).length
}

/**
 * 获取指定容量的SKU数量
 */
const getCapacityCount = (capacity: string) => {
    return skuList.value.filter(sku => sku.capacity === capacity).length
}

/**
 * 获取已有SKU列表中指定等级的数量
 */
const getExistingGradeCount = (grade: string) => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return 0
    return formData.sku_list.filter((sku: any) => sku.config_item_name === grade).length
}

/**
 * 获取已有SKU列表中指定容量的数量
 */
const getExistingCapacityCount = (capacity: string) => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return 0
    return formData.sku_list.filter((sku: any) => sku.capacity === capacity).length
}

/**
 * 全选/取消全选已有SKU
 */
const handleSelectAllExisting = (checked: boolean) => {
    if (checked) {
        selectedExistingSkuIndexes.value = formData.sku_list.map((_: any, index: number) => index)
    } else {
        selectedExistingSkuIndexes.value = []
    }
}

/**
 * 单个SKU选择变化
 */
const handleExistingSkuSelect = (index: number, checked: boolean) => {
    if (checked) {
        if (!selectedExistingSkuIndexes.value.includes(index)) {
            selectedExistingSkuIndexes.value.push(index)
        }
    } else {
        const idx = selectedExistingSkuIndexes.value.indexOf(index)
        if (idx > -1) {
            selectedExistingSkuIndexes.value.splice(idx, 1)
        }
    }

    // 更新全选状态
    const total = formData.sku_list?.length || 0
    selectAllExistingSkus.value = selectedExistingSkuIndexes.value.length === total
}

/**
 * 批量删除已选中的SKU
 */
const batchRemoveSkusFromList = async () => {
    if (selectedExistingSkuIndexes.value.length === 0) {
        ElMessage.warning('请先选择要删除的SKU')
        return
    }

    try {
        await ElMessageBox.confirm(
            `确定要删除选中的 ${selectedExistingSkuIndexes.value.length} 个SKU吗？`,
            '批量删除确认',
            {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }
        )

        if (!formData.sku_list || !Array.isArray(formData.sku_list)) {
            return
        }

        // 按索引从大到小排序，避免删除时索引错乱
        const sortedIndexes = [...selectedExistingSkuIndexes.value].sort((a, b) => b - a)

        sortedIndexes.forEach(index => {
            formData.sku_list.splice(index, 1)
        })

        // 如果列表为空，清空sku_list
        if (formData.sku_list.length === 0) {
            formData.sku_list = null
        }

        // 清空选择
        selectedExistingSkuIndexes.value = []
        selectAllExistingSkus.value = false

        ElMessage.success(`成功删除 ${sortedIndexes.length} 个SKU`)
    } catch {
        // 用户取消删除
    }
}

/**
 * 按等级快速删除
 */
const handleQuickDeleteByGrade = async (grade: string) => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return

    const count = getExistingGradeCount(grade)

    try {
        await ElMessageBox.confirm(
            `确定要删除所有"${grade}"等级的SKU吗？共 ${count} 个`,
            '快速删除确认',
            {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }
        )

        // 找出所有该等级的索引（从大到小）
        const indexes: number[] = []
        formData.sku_list.forEach((sku: any, index: number) => {
            if (sku.config_item_name === grade) {
                indexes.push(index)
            }
        })

        // 从大到小删除
        indexes.sort((a, b) => b - a).forEach(index => {
            formData.sku_list.splice(index, 1)
        })

        // 如果列表为空，清空sku_list
        if (formData.sku_list.length === 0) {
            formData.sku_list = null
        }

        // 清空选择
        selectedExistingSkuIndexes.value = []
        selectAllExistingSkus.value = false

        ElMessage.success(`成功删除 ${count} 个"${grade}"等级的SKU`)
    } catch {
        // 用户取消删除
    }
}

/**
 * 按容量快速删除
 */
const handleQuickDeleteByCapacity = async (capacity: string) => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) return

    const count = getExistingCapacityCount(capacity)

    try {
        await ElMessageBox.confirm(
            `确定要删除所有"${capacity}"容量的SKU吗？共 ${count} 个`,
            '快速删除确认',
            {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }
        )

        // 找出所有该容量的索引（从大到小）
        const indexes: number[] = []
        formData.sku_list.forEach((sku: any, index: number) => {
            if (sku.capacity === capacity) {
                indexes.push(index)
            }
        })

        // 从大到小删除
        indexes.sort((a, b) => b - a).forEach(index => {
            formData.sku_list.splice(index, 1)
        })

        // 如果列表为空，清空sku_list
        if (formData.sku_list.length === 0) {
            formData.sku_list = null
        }

        // 清空选择
        selectedExistingSkuIndexes.value = []
        selectAllExistingSkus.value = false

        ElMessage.success(`成功删除 ${count} 个"${capacity}"容量的SKU`)
    } catch {
        // 用户取消删除
    }
}

/**
 * 清空SKU选择
 */
const clearSkuSelection = () => {
    selectedSkuKeys.value = []
}

/**
 * 清空型号选择
 */
const clearModelSelection = () => {
    selectedModelIds.value = []
    selectedSkuKeys.value = []
    skuList.value = []
}

/**
 * 检查SKU是否已在列表中
 */
const isSkuInList = (sku: any): boolean => {
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) {
        return false
    }
    return formData.sku_list.some((item: any) => {
        return item.goods_id === sku.goods_id &&
               item.capacity === sku.capacity &&
               (item.config_item_name || '') === (sku.config_item_name || '')
    })
}

/**
 * 从列表中移除SKU
 */
const removeSkuFromList = async (index: number) => {
    try {
        await ElMessageBox.confirm(
            '确定要删除这个SKU吗？',
            '确认删除',
            {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }
        )

        if (!formData.sku_list || !Array.isArray(formData.sku_list)) {
            return
        }

        formData.sku_list.splice(index, 1)

        // 如果列表为空，清空sku_list
        if (formData.sku_list.length === 0) {
            formData.sku_list = null
        }

        ElMessage.success('删除成功')
    } catch {
        // 用户取消删除
    }
}

/**
 * 添加SKU到列表（编辑模式使用）
 */
const addSkusToList = () => {
    if (selectedSkuKeys.value.length === 0) {
        ElMessage.warning('请先选择要添加的SKU')
        return
    }

    // 确保sku_list是数组
    if (!formData.sku_list || !Array.isArray(formData.sku_list)) {
        formData.sku_list = []
    }

    let addedCount = 0
    selectedSkuKeys.value.forEach(skuKey => {
        const sku = skuList.value.find(s => s.key === skuKey)
        if (!sku) return

        // 检查是否已存在
        if (isSkuInList(sku)) {
            return
        }

        // 添加到列表
        formData.sku_list.push({
            goods_id: sku.goods_id,
            goods_name: sku.goods_name,
            capacity: sku.capacity,
            capacity_answer_id: sku.capacity_answer_id,
            config_item_name: sku.config_item_name
        })
        addedCount++
    })

    // 清空选择
    selectedSkuKeys.value = []

    if (addedCount > 0) {
        ElMessage.success(`成功添加 ${addedCount} 个SKU到列表`)
    } else {
        ElMessage.warning('所选SKU已存在于列表中')
    }
}

/**
 * 配置类型变化时的处理
 */
const onConfigTypeChange = () => {
    // 清空不需要的字段
    if (formData.config_type !== 1 && formData.config_type !== 2) {
        formData.capacity = ''
    }
    if (formData.config_type !== 1) {
        formData.config_item_name = ''
    }
    if (formData.config_type !== 4) {
        formData.group_key = ''
    }
    if (formData.config_type === 4) {
        formData.goods_id = ''
    }
    // 如果不是SKU级别，清空sku_list和相关选择
    if (formData.config_type !== 1) {
        formData.sku_list = null
        selectedQuotationId.value = null
        selectedModelIds.value = []
        selectedSkuKeys.value = []
        skuList.value = []
    }
}

/**
 * 确认
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return

    await formEl.validate(async (valid) => {
        if (valid) {
            // SKU级别配置的额外验证
            if (formData.config_type === 1) {
                // 添加模式：必须选择SKU
                if (!formData.id && selectedSkuKeys.value.length === 0) {
                    ElMessage.warning('请至少选择一个SKU')
                    return
                }
                // 编辑模式：如果没有已有SKU列表，也没有选择新SKU，提示错误
                if (formData.id && (!formData.sku_list || formData.sku_list.length === 0) && selectedSkuKeys.value.length === 0) {
                    ElMessage.warning('请至少保留或添加一个SKU')
                    return
                }
            }

            loading.value = true

            try {
                // SKU级别配置：如果有选中的SKU，使用批量保存接口
                if (formData.config_type === 1 && selectedSkuKeys.value.length > 0) {
                    // 收集SKU数据
                    const skus = selectedSkuKeys.value.map(skuKey => {
                        const sku = skuList.value.find(s => s.key === skuKey)
                        if (!sku) return null

                        return {
                            goods_id: sku.goods_id,
                            goods_name: sku.goods_name,
                            capacity: sku.capacity,
                            capacity_answer_id: sku.capacity_answer_id,
                            config_item_name: sku.config_item_name
                        }
                    }).filter(sku => sku !== null)

                    if (skus.length === 0) {
                        ElMessage.warning('SKU数据无效')
                        loading.value = false
                        return
                    }

                    // 使用批量添加接口
                    const result = await batchAddSkuPriceConfig({
                        skus,
                        adjustment_type: formData.adjustment_type,
                        adjustment_value: formData.adjustment_value,
                        is_enable: formData.is_enable
                    })

                    const conflictInfo = result.data?.conflict_info || {}
                    const removedConfigs = conflictInfo.removed_from_configs || []
                    const duplicateSkus = conflictInfo.duplicate_skus || []
                    const newSkus = conflictInfo.new_skus || []

                    // 构建提示信息
                    let message = `成功为 ${selectedSkuKeys.value.length} 个SKU创建价格配置`
                    const details: string[] = []

                    if (removedConfigs.length > 0) {
                        details.push(`已从 ${removedConfigs.length} 个其他配置中移除了重复的SKU（保留新配置）`)
                    }

                    if (duplicateSkus.length > 0) {
                        details.push(`${duplicateSkus.length} 个SKU已在相同配置规则中存在，已跳过`)
                    }

                    if (newSkus.length > 0) {
                        details.push(`新增 ${newSkus.length} 个SKU`)
                    }

                    if (details.length > 0) {
                        message += '\n' + details.join('\n')
                    }

                    ElMessage.success({
                        message,
                        duration: 5000,
                        showClose: true
                    })
                } else {
                    // 其他情况：使用普通保存接口
                    const save = formData.id ? editQuotationPriceConfig : addQuotationPriceConfig
                    const data = { ...formData }

                    // 清理空值
                    if (!data.goods_id) data.goods_id = 0
                    if (!data.capacity) data.capacity = ''
                    if (!data.config_item_name) data.config_item_name = ''
                    if (!data.group_key) data.group_key = 0
                    if (!data.capacity_answer_id) data.capacity_answer_id = 0

                    // 如果是SKU级别配置
                    if (data.config_type === 1) {
                        // 编辑模式：如果有sku_list且不为空，使用sku_list；否则清空
                        if (formData.id) {
                            // 编辑模式：如果sku_list为空数组或null，设置为null
                            if (!data.sku_list || (Array.isArray(data.sku_list) && data.sku_list.length === 0)) {
                                data.sku_list = null
                            }
                            // 如果有sku_list，清空其他字段
                            if (data.sku_list && Array.isArray(data.sku_list) && data.sku_list.length > 0) {
                        data.goods_id = 0
                        data.capacity = ''
                        data.config_item_name = ''
                            }
                        } else {
                            // 添加模式：如果没有选择SKU，清空sku_list
                            if (!selectedSkuKeys.value || selectedSkuKeys.value.length === 0) {
                        data.sku_list = null
                            }
                        }
                    } else {
                        // 非SKU级别配置，清空sku_list
                        data.sku_list = null
                    }

                    // 删除id字段（如果是添加）
                    if (!data.id) {
                        delete data.id
                    }

                    await save(data)
                }

                loading.value = false
                showDialog.value = false
                emit('complete')
            } catch (err) {
                loading.value = false
            }
        }
    })
}

const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    selectedQuotationId.value = null
    selectedModelIds.value = []
    selectedSkuKeys.value = []
    skuList.value = []
    // 清除筛选条件
    selectedGradeFilters.value = []
    selectedCapacityFilters.value = []
    loading.value = true

    // 加载报价单列表
    await loadQuotationList()

    if (row) {
        const data = await (await getQuotationPriceConfigInfo(row.id)).data
        if (data) {
            Object.keys(formData).forEach((key: string) => {
                if (data[key] != undefined) {
                    // 处理sku_list：如果是字符串，需要解析；如果是数组，直接使用；如果是对象，转换为数组
                    if (key === 'sku_list') {
                        if (typeof data[key] === 'string') {
                            try {
                                const parsed = JSON.parse(data[key])
                                formData[key] = Array.isArray(parsed) ? parsed : null
                            } catch {
                                formData[key] = null
                            }
                        } else if (Array.isArray(data[key])) {
                            formData[key] = data[key]
                        } else if (data[key] && typeof data[key] === 'object') {
                            formData[key] = [data[key]]
                        } else {
                            formData[key] = null
                        }
                    } else {
                        formData[key] = data[key]
                    }
                }
            })
            // 确保数值字段正确
            if (formData.goods_id === '' || formData.goods_id === null) formData.goods_id = 0
            if (formData.group_key === '' || formData.group_key === null) formData.group_key = 0
            if (formData.capacity_answer_id === '' || formData.capacity_answer_id === null) formData.capacity_answer_id = 0

            // 确保配置类型正确设置（如果是编辑且有sku_list，确保config_type为1）
            if (formData.id && formData.sku_list && Array.isArray(formData.sku_list) && formData.sku_list.length > 0) {
                formData.config_type = 1
            }
        }
    }
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped>
</style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
    height: auto !important;
}
</style>
