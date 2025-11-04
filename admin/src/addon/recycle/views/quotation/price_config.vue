<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <div class="flex gap-2">
                    <el-button type="danger" @click="clearAllConfig" :disabled="table.total === 0">
                        清空所有配置
                    </el-button>
                    <el-button type="danger" @click="batchDeleteConfig" :disabled="selectedRowIds.length === 0">
                        批量删除（{{ selectedRowIds.length }}）
                    </el-button>
                    <el-button type="primary" @click="addEvent">
                        添加价格配置
                    </el-button>
                </div>
            </div>

            <!-- 报价单和型号选择区域 + 批量配置表单 -->
            <el-card class="box-card !border-none my-[10px]" shadow="never">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-4">
                        <span class="font-medium">选择报价单：</span>
                        <el-select
                            v-model="selectedQuotationId"
                            placeholder="请选择报价单"
                            style="width: 300px"
                            @change="handleQuotationChange"
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
                    </div>
                    
                    <!-- 批量配置表单 -->
                    <div v-if="selectedQuotationId" class="border-t pt-4">
                        <div class="flex items-center gap-4 mb-4">
                            <span class="font-medium">选择型号：</span>
                            <el-select
                                v-model="selectedModelIds"
                                multiple
                                filterable
                                placeholder="请选择型号（可多选）"
                                style="width: 400px"
                                :loading="modelListLoading"
                                @change="handleModelChange"
                            >
                                <el-option
                                    v-for="model in modelList"
                                    :key="model.goods_id"
                                    :label="model.goods_name"
                                    :value="model.goods_id"
                                />
                            </el-select>
                            <el-button v-if="selectedModelIds.length > 0" @click="clearSelection">清空选择</el-button>
                        </div>
                        
                        <!-- SKU选择区域 -->
                        <div v-if="selectedModelIds.length > 0" class="border-t pt-4">
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium">选择SKU（型号+内存+规格）：</span>
                                    <div class="flex gap-2">
                                        <el-button size="small" @click="selectAllSkus">全选</el-button>
                                        <el-button size="small" @click="clearSkuSelection">清空</el-button>
                                    </div>
                                </div>
                                <div v-loading="skuListLoading" class="max-h-[400px] overflow-y-auto border rounded p-4">
                                    <el-checkbox-group v-model="selectedSkus" @change="handleSkuChange">
                                        <div v-for="sku in skuList" :key="sku.key" class="mb-2">
                                            <el-checkbox :label="sku.key">
                                                <span class="font-medium">{{ sku.goods_name }}</span>
                                                <span class="mx-2 text-gray-500">/</span>
                                                <span>{{ sku.capacity }}</span>
                                                <span v-if="sku.config_item_name" class="mx-2 text-gray-500">/</span>
                                                <span v-if="sku.config_item_name" class="text-blue-600">{{ sku.config_item_name }}</span>
                                            </el-checkbox>
                                        </div>
                                    </el-checkbox-group>
                                    <div v-if="skuList.length === 0 && !skuListLoading" class="text-center text-gray-400 py-4">
                                        暂无SKU数据
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 配置规则表单 -->
                        <div v-if="selectedSkus.length > 0" class="border-t pt-4">
                            <el-form :model="batchConfigForm" label-width="120px" ref="batchConfigFormRef" :rules="batchConfigRules">
                                <el-form-item label="调整类型" prop="adjustment_type">
                                    <el-select v-model="batchConfigForm.adjustment_type" placeholder="请选择调整类型" style="width: 300px">
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
                                        v-model="batchConfigForm.adjustment_value" 
                                        :precision="2" 
                                        :min="batchConfigForm.adjustment_type === 2 ? -100 : undefined"
                                        :max="batchConfigForm.adjustment_type === 2 ? 100 : undefined"
                                        placeholder="请输入调整值" 
                                        style="width: 300px"
                                    >
                                        <template #append v-if="batchConfigForm.adjustment_type === 2">%</template>
                                        <template #append v-else-if="batchConfigForm.adjustment_type !== 2">元</template>
                                    </el-input-number>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span v-if="batchConfigForm.adjustment_type === 1">固定金额：正数增加价格，负数减少价格</span>
                                        <span v-else-if="batchConfigForm.adjustment_type === 2">百分比：正数增加百分比，负数减少百分比（范围：-100% ~ 100%）</span>
                                        <span v-else-if="batchConfigForm.adjustment_type === 3">直接覆盖：直接将价格设置为该值</span>
                                    </div>
                                </el-form-item>
                                
                                <el-form-item label="是否启用" prop="is_enable">
                                    <el-switch v-model="batchConfigForm.is_enable" :active-value="1" :inactive-value="0" />
                                </el-form-item>
                                
                                <el-form-item>
                                    <el-button type="primary" @click="saveBatchConfig" :loading="batchSaving">
                                        批量保存配置（{{ selectedSkus.length }}个SKU）
                                    </el-button>
                                    <el-button @click="resetBatchConfig">重置表单</el-button>
                                </el-form-item>
                            </el-form>
                            
                            <!-- 已选SKU列表 -->
                            <div class="mt-4">
                                <div class="text-sm text-gray-600 mb-2">已选择 {{ selectedSkus.length }} 个SKU：</div>
                                <div class="flex flex-wrap gap-2">
                                    <el-tag 
                                        v-for="skuKey in selectedSkus" 
                                        :key="skuKey"
                                        closable
                                        @close="removeSku(skuKey)"
                                    >
                                        {{ getSkuDisplayName(skuKey) }}
                                    </el-tag>
                                </div>
                            </div>
                            
                            <!-- 配置预览 -->
                            <div v-if="selectedSkus.length > 0 && batchConfigForm.adjustment_type && batchConfigForm.adjustment_value !== 0" class="mt-4 p-4 bg-gray-50 rounded">
                                <div class="text-sm font-medium text-gray-700 mb-2">配置预览：</div>
                                <div class="text-sm text-gray-600">
                                    <div>将为以下 {{ selectedSkus.length }} 个SKU应用配置：</div>
                                    <div class="mt-2">
                                        <span class="font-medium">调整类型：</span>
                                        <span>{{ getAdjustmentTypeText(batchConfigForm.adjustment_type) }}</span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="font-medium">调整值：</span>
                                        <span>{{ batchConfigForm.adjustment_type === 2 ? batchConfigForm.adjustment_value + '%' : '¥' + batchConfigForm.adjustment_value }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </el-card>

            <!-- 配置列表视图：始终显示 -->
            <div class="mt-[10px]">
                <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                    <el-form :inline="true" :model="table.searchParam" ref="searchFormRef">
                        <el-form-item label="配置类型" prop="config_type">
                            <el-select v-model="table.searchParam.config_type" clearable placeholder="请选择配置类型">
                                <el-option label="全部" value=""></el-option>
                                <el-option label="SKU级别" :value="1"></el-option>
                                <el-option label="型号+内存" :value="2"></el-option>
                                <el-option label="型号" :value="3"></el-option>
                                <el-option label="分组" :value="4"></el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item label="设备ID" prop="goods_id">
                            <el-input v-model="table.searchParam.goods_id" placeholder="请输入设备ID" />
                        </el-form-item>
                        <el-form-item label="容量" prop="capacity">
                            <el-input v-model="table.searchParam.capacity" placeholder="请输入容量" />
                        </el-form-item>
                        <el-form-item label="配置项名称" prop="config_item_name">
                            <el-input v-model="table.searchParam.config_item_name" placeholder="请输入配置项名称" />
                        </el-form-item>
                        <el-form-item label="分组标识" prop="group_key">
                            <el-input v-model="table.searchParam.group_key" placeholder="请输入分组标识" />
                        </el-form-item>
                        <el-form-item label="状态" prop="is_enable">
                            <el-select v-model="table.searchParam.is_enable" clearable placeholder="请选择状态">
                                <el-option label="全部" value=""></el-option>
                                <el-option label="启用" :value="1"></el-option>
                                <el-option label="禁用" :value="0"></el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="loadList()">查询</el-button>
                            <el-button @click="resetForm(searchFormRef)">重置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <div class="mt-[10px]">
                    <el-table 
                        :data="table.data" 
                        size="large" 
                        v-loading="table.loading"
                        @selection-change="handleSelectionChange"
                    >
                        <template #empty>
                            <span>{{ !table.loading ? '暂无数据' : '' }}</span>
                        </template>
                        <el-table-column type="selection" width="55" />
                        <el-table-column prop="id" label="ID" min-width="80" />
                        <el-table-column prop="config_type" label="配置类型" min-width="120">
                            <template #default="{ row }">
                                {{ getConfigTypeText(row.config_type) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="SKU信息" min-width="300">
                            <template #default="{ row }">
                                <!-- 如果有sku_list，显示SKU列表 -->
                                <div v-if="row.sku_list_parsed && row.sku_list_parsed.length > 0">
                                    <div class="mb-1">
                                        <el-tag type="info" size="small">{{ row.sku_count }}个SKU</el-tag>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <div v-for="(sku, index) in row.sku_list_parsed" :key="index" class="truncate">
                                            {{ sku.goods_name || '型号ID:' + sku.goods_id }} / {{ sku.capacity || '-' }} / {{ sku.config_item_name || '-' }}
                                        </div>
                                    </div>
                                </div>
                                <!-- 兼容旧数据：单个SKU显示 -->
                                <div v-else>
                                    <div v-if="row.goods_name" class="font-medium">{{ row.goods_name }}</div>
                                    <div v-else class="text-gray-400">型号ID: {{ row.goods_id || '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ row.capacity || '-' }} / {{ row.config_item_name || '-' }}
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="capacity" label="内存" min-width="100" v-if="false">
                            <template #default="{ row }">
                                {{ row.capacity || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="config_item_name" label="规格" min-width="150" v-if="false">
                            <template #default="{ row }">
                                {{ row.config_item_name || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="group_key" label="分组标识" min-width="120">
                            <template #default="{ row }">
                                {{ row.group_key || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="adjustment_type" label="调整类型" min-width="120">
                            <template #default="{ row }">
                                {{ getAdjustmentTypeText(row.adjustment_type) }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="adjustment_value" label="调整值" min-width="120">
                            <template #default="{ row }">
                                {{ row.adjustment_type === 2 ? row.adjustment_value + '%' : '¥' + row.adjustment_value }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="is_enable" label="状态" min-width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.is_enable === 1 ? 'success' : 'danger'">
                                    {{ row.is_enable === 1 ? '启用' : '禁用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="create_time" label="创建时间" min-width="180" />
                        <el-table-column label="操作" fixed="right" min-width="200">
                            <template #default="{ row }">
                                <el-button type="primary" link @click="editEvent(row)">编辑</el-button>
                                <el-button type="primary" link @click="modifyStatusEvent(row)">
                                    {{ row.is_enable === 1 ? '禁用' : '启用' }}
                                </el-button>
                                <el-button type="primary" link @click="deleteEvent(row.id)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-[16px] flex justify-end">
                        <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                            layout="total, sizes, prev, pager, next, jumper" :total="table.total"
                            @size-change="loadList()" @current-change="loadList" />
                    </div>
                </div>
            </div>

            <edit ref="editDialog" @complete="loadList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import type { FormInstance } from 'element-plus'
import { ElMessageBox, ElMessage } from 'element-plus'
import { getQuotationPriceConfigList, deleteQuotationPriceConfig, modifyQuotationPriceConfigStatus, addQuotationPriceConfig, batchDeleteQuotationPriceConfig, clearAllQuotationPriceConfig, batchAddSkuPriceConfig } from '@/addon/recycle/api/quotation'
import { getQuotationConfigList } from '@/addon/recycle/api/quotation'
import { getQuotationDataCascadeOptions } from '@/addon/recycle/api/quotation'
import Edit from '@/addon/recycle/views/quotation/components/quotation-price-config-edit.vue'

const route = useRoute()
const pageName = route.meta.title

const selectedQuotationId = ref<number | null>(null)
const quotationList = ref<Array<{ id: number, quotation_id: number, price_name: string, config_name: string }>>([])
const selectedModelIds = ref<number[]>([])
const modelList = ref<Array<{ goods_id: number, goods_name: string }>>([])
const modelListLoading = ref(false)
const batchSaving = ref(false)

// 表格选中行
const selectedRowIds = ref<number[]>([])

// SKU列表和选择
const skuList = ref<Array<{
    key: string,
    goods_id: number,
    goods_name: string,
    capacity: string,
    capacity_answer_id: number,
    config_item_name: string
}>>([])
const selectedSkus = ref<string[]>([])
const skuListLoading = ref(false)

// 批量配置表单
const batchConfigForm = reactive({
    adjustment_type: 1, // 默认固定金额
    adjustment_value: 0,
    is_enable: 1
})

const batchConfigFormRef = ref<FormInstance>()

// 批量配置表单验证规则
const batchConfigRules = {
    adjustment_type: [
        { required: true, message: '请选择调整类型', trigger: 'change' }
    ],
    adjustment_value: [
        { required: true, message: '请输入调整值', trigger: 'blur' }
    ]
}

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

const table = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        config_type: '',
        goods_id: '',
        capacity: '',
        config_item_name: '',
        group_key: '',
        is_enable: ''
    }
})

const searchFormRef = ref<FormInstance>()
const editDialog = ref<InstanceType<typeof Edit>>()

/**
 * 加载报价单列表
 */
const loadQuotationList = async () => {
    try {
        // 使用较小的 limit，或者循环获取所有数据
        const res = await getQuotationConfigList({ limit: 120 })
        quotationList.value = (res.data.data || []).map((item: any) => ({
            id: item.id,
            quotation_id: item.quotation_id,
            price_name: item.price_name,
            config_name: item.config_name || ''
        }))
        
        // 如果还有更多数据，继续获取
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
 * 型号选择变化 - 加载SKU列表
 */
const handleModelChange = async (modelIds: number[]) => {
    selectedModelIds.value = modelIds
    selectedSkus.value = [] // 清空SKU选择
    
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
        
        // 根据选中的型号，提取所有SKU
        const skus: Array<{
            key: string,
            goods_id: number,
            goods_name: string,
            capacity: string,
            capacity_answer_id: number,
            config_item_name: string
        }> = []
        
        cascadeOptions.forEach((model: any) => {
            // 只处理选中的型号
            if (!selectedModelIds.value.includes(model.goods_id)) {
                return
            }
            
            const capacities = model.children || []
            capacities.forEach((capacity: any) => {
                const configItems = capacity.children || []
                
                // 如果有配置项，为每个配置项创建一个SKU
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
                    // 如果没有配置项，也创建一个SKU（只有型号+内存）
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
    selectedSkus.value = selected
}

/**
 * 获取SKU显示名称
 */
const getSkuDisplayName = (skuKey: string) => {
    const sku = skuList.value.find(s => s.key === skuKey)
    if (!sku) return skuKey
    
    let display = `${sku.goods_name} / ${sku.capacity}`
    if (sku.config_item_name) {
        display += ` / ${sku.config_item_name}`
    }
    return display
}

/**
 * 移除SKU
 */
const removeSku = (skuKey: string) => {
    const index = selectedSkus.value.indexOf(skuKey)
    if (index > -1) {
        selectedSkus.value.splice(index, 1)
    }
}

/**
 * 全选SKU
 */
const selectAllSkus = () => {
    selectedSkus.value = skuList.value.map(sku => sku.key)
}

/**
 * 清空SKU选择
 */
const clearSkuSelection = () => {
    selectedSkus.value = []
}

/**
 * 批量保存配置（智能合并）
 */
const saveBatchConfig = async () => {
    if (!batchConfigFormRef.value) return
    
    await batchConfigFormRef.value.validate(async (valid: boolean) => {
        if (!valid) return
        
        if (selectedSkus.value.length === 0) {
            ElMessage.warning('请至少选择一个SKU')
            return
        }
        
        batchSaving.value = true
        try {
            // 收集SKU数据
            const skus = selectedSkus.value.map(skuKey => {
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
                return
            }
            
            // 调用批量添加接口（会自动合并相同配置）
            const result = await batchAddSkuPriceConfig({
                skus: skus,
                adjustment_type: batchConfigForm.adjustment_type,
                adjustment_value: batchConfigForm.adjustment_value,
                is_enable: batchConfigForm.is_enable
            })

            const conflictInfo = result.data?.conflict_info || {}
            const removedConfigs = conflictInfo.removed_from_configs || []
            const duplicateSkus = conflictInfo.duplicate_skus || []
            const newSkus = conflictInfo.new_skus || []

            // 构建提示信息
            let message = `成功为 ${selectedSkus.value.length} 个SKU创建价格配置（已自动合并相同配置）`
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
            
            // 重置表单和选择
            resetBatchConfig()
            selectedSkus.value = []
            selectedModelIds.value = []
            
            // 刷新配置列表
            loadList()
        } catch (error) {
            console.error('批量保存失败:', error)
            ElMessage.error('批量保存失败')
        } finally {
            batchSaving.value = false
        }
    })
}

/**
 * 重置批量配置表单
 */
const resetBatchConfig = () => {
    batchConfigForm.adjustment_type = 1
    batchConfigForm.adjustment_value = 0
    batchConfigForm.is_enable = 1
    batchConfigFormRef.value?.resetFields()
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
 * 报价单选择变化
 */
const handleQuotationChange = async (quotationConfigId: number | null) => {
    selectedQuotationId.value = quotationConfigId
    selectedModelIds.value = [] // 清空型号选择
    selectedSkus.value = [] // 清空SKU选择
    resetBatchConfig() // 重置表单
    
    if (quotationConfigId && currentQuotationInfo.value) {
        // 加载该报价单下的型号列表
        await loadModelList()
    } else {
        modelList.value = []
        skuList.value = []
    }
}

/**
 * 清空选择
 */
const clearSelection = () => {
    selectedModelIds.value = []
    selectedSkus.value = []
    resetBatchConfig()
}

/**
 * 获取配置类型文本
 */
const getConfigTypeText = (type: number) => {
    const map: Record<number, string> = {
        1: 'SKU级别',
        2: '型号+内存',
        3: '型号',
        4: '分组'
    }
    return map[type] || '-'
}

/**
 * 获取调整类型文本
 */
const getAdjustmentTypeText = (type: number) => {
    const map: Record<number, string> = {
        1: '固定金额',
        2: '百分比',
        3: '直接覆盖'
    }
    return map[type] || '-'
}

/**
 * 获取列表
 */
const loadList = (page: number = 1) => {
    table.loading = true
    table.page = page

    getQuotationPriceConfigList({
        page: table.page,
        limit: table.limit,
        ...table.searchParam
    }).then(res => {
        table.loading = false
        table.data = res.data.data
        table.total = res.data.total
    }).catch(() => {
        table.loading = false
    })
}

/**
 * 添加
 */
const addEvent = () => {
    editDialog.value?.setFormData()
    editDialog.value!.showDialog = true
}

/**
 * 编辑
 */
const editEvent = (data: any) => {
    editDialog.value?.setFormData(data)
    editDialog.value!.showDialog = true
}

/**
 * 表格选择变化
 */
const handleSelectionChange = (selection: any[]) => {
    selectedRowIds.value = selection.map(item => item.id)
}

/**
 * 批量删除配置
 */
const batchDeleteConfig = () => {
    if (selectedRowIds.value.length === 0) {
        ElMessage.warning('请至少选择一条配置')
        return
    }
    
    ElMessageBox.confirm(
        `确定要删除选中的 ${selectedRowIds.value.length} 条价格配置吗？此操作不可恢复！`,
        '批量删除确认',
        {
            confirmButtonText: '确定删除',
            cancelButtonText: '取消',
            type: 'warning',
            dangerouslyUseHTMLString: false
        }
    ).then(() => {
        batchDeleteQuotationPriceConfig(selectedRowIds.value).then(() => {
            ElMessage.success(`成功删除 ${selectedRowIds.value.length} 条配置`)
            selectedRowIds.value = []
            loadList()
        }).catch(() => {})
    }).catch(() => {})
}

/**
 * 清空所有配置
 */
const clearAllConfig = () => {
    if (table.total === 0) {
        ElMessage.warning('没有可清空的配置')
        return
    }
    
    ElMessageBox.confirm(
        `确定要清空所有 ${table.total} 条价格配置吗？此操作不可恢复！<br/><br/><strong style="color: red;">建议：如果配置很多且混乱，可以使用此功能一键清空，然后重新配置。</strong>`,
        '清空所有配置',
        {
            confirmButtonText: '确定清空',
            cancelButtonText: '取消',
            type: 'warning',
            dangerouslyUseHTMLString: true
        }
    ).then(() => {
        clearAllQuotationPriceConfig().then(() => {
            ElMessage.success('已清空所有价格配置')
            selectedRowIds.value = []
            loadList()
        }).catch(() => {})
    }).catch(() => {})
}

/**
 * 删除单个配置
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm('确定要删除该价格配置吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
    }).then(() => {
        deleteQuotationPriceConfig(id).then(() => {
            ElMessage.success('删除成功')
            loadList()
        }).catch(() => {})
    }).catch(() => {})
}

// 初始化加载
onMounted(() => {
    loadQuotationList()
    loadList()
})
</script>

<style lang="scss" scoped>
</style>

