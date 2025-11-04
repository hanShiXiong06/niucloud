<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <div class="flex gap-2">
                    <el-button
                        v-if="!editMode"
                        type="primary"
                        @click="enterEditMode"
                        :disabled="table.data.length === 0"
                    >
                        <el-icon class="mr-1"><Edit /></el-icon>
                        批量修改报价
                    </el-button>
                    <template v-else>
                        <el-button type="success" @click="saveAllChanges" :loading="saving">
                            <el-icon class="mr-1"><Check /></el-icon>
                            保存修改 ({{ Object.keys(priceChanges).length }})
                        </el-button>
                        <el-button @click="cancelEdit">
                            <el-icon class="mr-1"><Close /></el-icon>
                            取消
                        </el-button>
                    </template>
                </div>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="table.searchParam" ref="searchFormRef">
                    <el-form-item label="报价单名称" prop="price_name">
                        <el-select
                            v-model="table.searchParam.price_name"
                            placeholder="请选择报价单"
                            clearable
                            filterable
                            style="width: 200px"
                        >
                            <el-option
                                v-for="item in priceNameOptions"
                                :key="item"
                                :label="item"
                                :value="item"
                            />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="价格日期" prop="price_date">
                        <el-date-picker
                            v-model="table.searchParam.price_date"
                            type="date"
                            placeholder="选择日期"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            clearable
                        />
                    </el-form-item>
                    <el-form-item label="报价ID" prop="quotation_id">
                        <el-input v-model="table.searchParam.quotation_id" placeholder="请输入报价ID" clearable />
                    </el-form-item>
                    <el-form-item label="型号名称" prop="goods_name">
                        <el-input v-model="table.searchParam.goods_name" placeholder="请输入型号名称" clearable />
                    </el-form-item>
                    <el-form-item label="内存容量" prop="capacity">
                        <el-input v-model="table.searchParam.capacity" placeholder="请输入内存容量" clearable />
                    </el-form-item>
                    <el-form-item label="是否最新" prop="is_current">
                        <el-select v-model="table.searchParam.is_current" clearable placeholder="请选择">
                            <el-option label="全部" value=""></el-option>
                            <el-option label="是" :value="1"></el-option>
                            <el-option label="否" :value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">查询</el-button>
                        <el-button @click="resetForm(searchFormRef)">重置</el-button>
                    </el-form-item>
                </el-form>
                <div class="text-sm text-gray-500 mt-2">
                    <el-alert
                        v-if="table.data.length === 0 && !table.loading"
                        type="warning"
                        :closable="false"
                        show-icon
                    >
                        <template #title>
                            <div>暂无报价数据。请先在"报价请求"页面发送报价请求获取数据。</div>
                            <div class="text-xs mt-1">提示：如果已有数据，请尝试清空"价格日期"和"是否最新"筛选条件，或选择其他日期。</div>
                        </template>
                    </el-alert>
                </div>
            </el-card>

            <!-- 数据统计 -->
            <div v-if="!table.loading && table.data.length > 0" class="mb-4 text-sm text-gray-600">
                共 {{ table.data.length }} 条数据，{{ groupedByPriceName.length }} 个报价单类型
            </div>

            <!-- 按价格名称分组的表格 -->
            <div v-loading="table.loading">
                <div v-if="!table.loading && groupedByPriceName.length === 0" class="text-center py-10 text-gray-400">
                    暂无数据
                </div>

                <!-- 每个分组一个独立表格（可能包含多个子表） -->
                <div v-for="(group, index) in groupedByPriceName" :key="index" class="">
                    <!-- 主表格标题（首次出现或与上一个报价单不同时显示） -->
                    <div
                        v-if="index === 0 || groupedByPriceName[index - 1].price_name !== group.price_name"
                        class="flex items-center justify-between mb-3 pb-2 border-b-2 border-blue-500"
                    >
                        <h3 class="text-xl font-bold text-blue-700">
                            {{ group.price_name }}
                            <span class="text-sm font-normal text-gray-500 ml-2">
                                (报价单ID: {{ group.quotation_id }})
                            </span>
                        </h3>
                    </div>

                    <!-- 子表标题（如果同一报价单有多个配置组合） -->
                    <!-- <div v-if="group.isSubTable" class="mb-2 pl-4">
                        <div class="text-sm text-gray-600">
                            <el-tag type="success" size="small" class="mr-2">等级组 {{ getSubTableIndex(index) }}</el-tag>
                            配置项: {{ group.configs.join(', ') }}
                        </div>
                    </div> -->

                    <!-- Excel风格表格 -->
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th class="sticky-col header-cell" width="200">型号</th>
                                    <th class="header-cell capacity-header" width="100">内存</th>
                                    <th
                                        v-for="config in group.configs"
                                        :key="config"
                                        class="header-cell config-header"
                                    >
                                        {{ config }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rowIndex) in group.excelData" :key="rowIndex" class="data-row">
                                    <td
                                        v-if="row.showModel"
                                        class="sticky-col data-cell model-cell"
                                        :rowspan="row.modelRowspan"
                                    >
                                        {{ row.goods_name }}
                                    </td>
                                    <td class="data-cell capacity-cell">{{ row.capacity }}</td>
                                    <td v-for="config in group.configs" :key="config + '_data'" class="data-cell price-cell">
                                        <div v-if="row.prices[config]" class="price-box">
                                            <!-- 编辑模式 -->
                                            <div v-if="editMode" class="edit-mode-box">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs text-gray-500">原价:</span>
                                                    <span class="text-sm text-gray-600">¥{{ row.prices[config].original_price }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-xs ">新价:</span>
                                                    <el-input
                                                        v-model="row.prices[config].editPrice"
                                                        :min="0"
                                                        :precision="2"
                                                        :step="10"
                                                        size="small"
                                                        class="edit-price-input"
                                                        @change="onPriceChange(row, config, group)"
                                                    />
                                                </div>
                                                <div
                                                    v-if="isPriceChanged(row, config)"
                                                    class="text-xs mt-1"
                                                    :class="{
                                                        'text-red-600': row.prices[config].editPrice > row.prices[config].original_price,
                                                        'text-green-600': row.prices[config].editPrice < row.prices[config].original_price
                                                    }"
                                                >
                                                    {{ row.prices[config].editPrice > row.prices[config].original_price ? '↑' : '↓' }}
                                                    {{ calculatePriceChange(row.prices[config].original_price, row.prices[config].editPrice) }}
                                                </div>
                                            </div>

                                            <!-- 查看模式 -->
                                            <template v-else>
                                                <!-- 价格未变化：只显示一个价格 -->
                                                <div v-if="row.prices[config].price_status === 0" class="price-row single-price">
                                                    <span class="price-value text-gray-800 font-medium">¥{{ row.prices[config].final_price }}</span>
                                                </div>

                                                <!-- 价格有变化：显示原价和调价后，带颜色和箭头 -->
                                                <template v-else>
                                                    <div class="price-row original-price">
                                                        <span class="price-label text-gray-500">原价:</span>
                                                        <span class="price-value text-gray-600">¥{{ row.prices[config].original_price }}</span>
                                                    </div>
                                                    <div class="price-row final-price">
                                                        <span class="price-label text-gray-500">调价:</span>
                                                        <span
                                                            class="price-value font-semibold"
                                                            :class="{
                                                                'text-red-600': row.prices[config].price_status === 1,
                                                                'text-green-600': row.prices[config].price_status === -1
                                                            }"
                                                        >
                                                            <span v-if="row.prices[config].price_status === 1" class="mr-1">↑</span>
                                                            <span v-if="row.prices[config].price_status === -1" class="mr-1">↓</span>
                                                            ¥{{ row.prices[config].final_price }}
                                                        </span>
                                                        <el-tag
                                                            :type="row.prices[config].price_status === 1 ? 'danger' : 'success'"
                                                            size="small"
                                                            class="ml-1"
                                                        >
                                                            {{ Math.abs(row.prices[config].price_diff_percent) }}%
                                                        </el-tag>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Check, Close } from '@element-plus/icons-vue'
import type { FormInstance } from 'element-plus'
import { getQuotationDataAll, batchUpdateQuotationPrice } from '@/addon/recycle/api/quotation'

const route = useRoute()
const pageName = route.meta.title

const table = reactive<{
    loading: boolean
    data: any[]
    searchParam: {
        quotation_id: string
        price_name: string
        goods_name: string
        capacity: string
        price_date: string
        is_current: string | number
    }
}>({
    loading: true,
    data: [],
    searchParam: {
        quotation_id: '',
        price_name: '',
        goods_name: '',
        capacity: '',
        price_date: '',
        is_current: ''
    }
})

const searchFormRef = ref<FormInstance>()

/**
 * 报价单名称选项列表
 */
const priceNameOptions = ref<string[]>([])

/**
 * 编辑模式状态
 */
const editMode = ref(false)
const saving = ref(false)
const priceChanges = ref<Record<string, any>>({})

/**
 * 解析JSON字符串字段
 */
const parseJsonField = (jsonString: string) => {
    if (!jsonString) return null
    try {
        return JSON.parse(jsonString)
    } catch (e) {
        console.error('JSON解析失败:', e)
        return null
    }
}

/**
 * 从数据中提取唯一的报价单名称
 */
const extractPriceNames = (data: any[]) => {
    const names = new Set<string>()
    data.forEach(item => {
        if (item.price_name) {
            names.add(item.price_name)
        }
    })
    priceNameOptions.value = Array.from(names).sort()
}

/**
 * 按价格名称分组，并智能拆分不同等级配置的子表
 */
const groupedByPriceName = computed(() => {
    // 第一步：按 price_name 分组
    const groups = new Map<string, any>()

    table.data.forEach(item => {
        const key = `${item.quotation_id}_${item.price_name}`

        if (!groups.has(key)) {
            groups.set(key, {
                quotation_id: item.quotation_id,
                price_name: item.price_name,
                data: []
            })
        }

        groups.get(key).data.push(item)
    })

    // 第二步：为每个报价单生成智能分组的子表
    const result: any[] = []

    Array.from(groups.values()).forEach(group => {
        // 收集该报价单下所有的配置项和型号内存组合
        const allConfigs = new Set<string>()
        const rowConfigMap = new Map<string, Set<string>>() // rowKey -> 该行有数据的配置项集合

        group.data.forEach((item: any) => {
            const rowKey = `${item.goods_id}_${item.capacity}`
            const priceDetail = item.price_detail || {}
            const configSelected = parseJsonField(item.config_selected)

            if (configSelected && Array.isArray(configSelected)) {
                configSelected.forEach((config: string) => allConfigs.add(config))
            }

            // 记录该行实际有数据的配置项
            if (!rowConfigMap.has(rowKey)) {
                rowConfigMap.set(rowKey, new Set<string>())
            }
            Object.keys(priceDetail).forEach(configName => {
                rowConfigMap.get(rowKey)!.add(configName)
            })
        })

        // 第三步：根据配置项的出现模式，智能分组
        // 找出不同的配置项组合模式
        const configPatterns = new Map<string, Set<string>>() // pattern -> configs

        rowConfigMap.forEach((configs, rowKey) => {
            const pattern = Array.from(configs).sort().join('|||')
            if (!configPatterns.has(pattern)) {
                configPatterns.set(pattern, new Set())
            }
            configPatterns.get(pattern)!.add(rowKey)
        })

        // 第四步：为每个配置模式生成一个子表
        configPatterns.forEach((rowKeys, pattern) => {
            const configs = pattern.split('|||')
            const excelMap = new Map<string, any>()

            // 只处理属于当前模式的行
            group.data.forEach((item: any) => {
                const rowKey = `${item.goods_id}_${item.capacity}`

                if (!rowKeys.has(rowKey)) {
                    return // 跳过不属于当前模式的行
                }

                if (!excelMap.has(rowKey)) {
                    excelMap.set(rowKey, {
                        goods_id: item.goods_id,
                        goods_name: item.goods_name,
                        capacity: item.capacity,
                        prices: {},
                        dataId: item.id // 保存原始数据的ID
                    })
                }

                const row = excelMap.get(rowKey)
                const priceDetail = item.price_detail || {}

                // 如果当前item有price_detail且不为空，更新dataId
                if (item.price_detail && typeof item.price_detail === 'object' && Object.keys(item.price_detail).length > 0) {
                    row.dataId = item.id
                }

                // 只收集当前模式的配置项数据
                configs.forEach(configName => {
                    if (priceDetail[configName]) {
                        row.prices[configName] = priceDetail[configName]
                    }
                })
            })

            const excelData = Array.from(excelMap.values())

            // 计算型号的 rowspan
            const modelRowspanMap = new Map<string, number>()
            excelData.forEach(row => {
                const modelName = row.goods_name
                modelRowspanMap.set(modelName, (modelRowspanMap.get(modelName) || 0) + 1)
            })

            // 标记每行是否应该显示型号单元格
            let currentModel = ''
            excelData.forEach((row) => {
                if (row.goods_name !== currentModel) {
                    currentModel = row.goods_name
                    row.showModel = true
                    row.modelRowspan = modelRowspanMap.get(row.goods_name) || 1
                } else {
                    row.showModel = false
                    row.modelRowspan = 0
                }
            })

            // 只有当excelData有数据时才添加
            if (excelData.length > 0) {
                result.push({
                    quotation_id: group.quotation_id,
                    price_name: group.price_name,
                    configs,
                    excelData,
                    isSubTable: configPatterns.size > 1 // 标记是否为子表
                })
            }
        })
    })

    return result
})

/**
 * 获取所有报价单名称选项（用于下拉菜单）
 */
const loadPriceNameOptions = () => {
    // 获取所有数据以提取报价单名称
    getQuotationDataAll({
        is_current: 1 // 只获取最新的数据来提取报价单名称
    }).then(res => {
        const allData = res.data || []
        extractPriceNames(allData)
    }).catch(() => {
        console.error('获取报价单名称失败')
    })
}

/**
 * 获取列表（所有数据，不分页）
 */
const loadList = () => {
    table.loading = true

    getQuotationDataAll({
        ...table.searchParam
    }).then(res => {
        table.loading = false
        // getQuotationDataAll 直接返回数组，不是分页格式
        table.data = res.data || []

        // 如果是首次加载且没有指定报价单，提取所有报价单名称
        if (priceNameOptions.value.length === 0) {
            extractPriceNames(table.data)
        }
    }).catch(() => {
        table.loading = false
    })
}

/**
 * 初始化：设置默认查询条件
 */
const initDefaultSearch = () => {
    // 默认查询今天的最新报价
    const today = new Date()
    const year = today.getFullYear()
    const month = String(today.getMonth() + 1).padStart(2, '0')
    const day = String(today.getDate()).padStart(2, '0')

    table.searchParam.price_date = `${year}-${month}-${day}`
    table.searchParam.is_current = 1
}

// 初始化
initDefaultSearch()
loadPriceNameOptions()
loadList()

/**
 * 重置表单
 */
const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    // 重置后恢复默认查询条件
    initDefaultSearch()
    loadList()
}

/**
 * 进入编辑模式
 */
const enterEditMode = () => {
    editMode.value = true
    priceChanges.value = {}

    // 为所有价格数据初始化编辑价格
    groupedByPriceName.value.forEach(group => {
        group.excelData.forEach((row: any) => {
            Object.keys(row.prices).forEach(config => {
                // 使用 final_price 作为初始编辑价格
                row.prices[config].editPrice = row.prices[config].final_price
            })
        })
    })
}

/**
 * 取消编辑
 */
const cancelEdit = () => {
    ElMessageBox.confirm(
        '确定要取消编辑吗？所有未保存的修改将丢失。',
        '提示',
        {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }
    ).then(() => {
        editMode.value = false
        priceChanges.value = {}
    }).catch(() => {
        // 用户点击取消
    })
}

/**
 * 价格变化回调
 */
const onPriceChange = (row: any, config: string, group: any) => {
    const key = `${row.goods_id}_${row.capacity}_${config}`
    const newPrice = row.prices[config].editPrice
    const originalPrice = row.prices[config].original_price

    if (newPrice !== originalPrice && newPrice > 0) {
        // 直接使用row中保存的dataId
        if (!row.dataId) {
            console.error('未找到数据ID', {
                goods_id: row.goods_id,
                capacity: row.capacity,
                config
            })
            return
        }

        priceChanges.value[key] = {
            id: row.dataId,
            goods_id: row.goods_id,
            capacity: row.capacity,
            config_name: config,
            new_price: newPrice,
            original_price: originalPrice
        }
    } else {
        delete priceChanges.value[key]
    }
}

/**
 * 判断价格是否变化
 */
const isPriceChanged = (row: any, config: string) => {
    const editPrice = row.prices[config].editPrice
    const originalPrice = row.prices[config].original_price
    return editPrice !== originalPrice
}

/**
 * 计算价格变化百分比
 */
const calculatePriceChange = (originalPrice: number, newPrice: number) => {
    if (originalPrice === 0) return '0%'
    const change = ((newPrice - originalPrice) / originalPrice * 100).toFixed(2)
    return `${Math.abs(parseFloat(change))}%`
}

/**
 * 保存所有修改
 */
const saveAllChanges = () => {
    const changesCount = Object.keys(priceChanges.value).length

    if (changesCount === 0) {
        ElMessage.warning('没有需要保存的修改')
        return
    }

    ElMessageBox.confirm(
        `确定要保存 ${changesCount} 个价格修改吗？`,
        '确认保存',
        {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'info'
        }
    ).then(() => {
        saving.value = true
        const items = Object.values(priceChanges.value)

        batchUpdateQuotationPrice({ items }).then(() => {
            ElMessage.success('批量修改成功')
            editMode.value = false
            priceChanges.value = {}
            loadList() // 重新加载数据
        }).catch(() => {
            ElMessage.error('批量修改失败')
        }).finally(() => {
            saving.value = false
        })
    }).catch(() => {
        // 用户点击取消
    })
}
</script>

<style lang="scss" scoped>
.excel-table-container {
    overflow-x: auto;
    border: 1px solid #e5e7eb;
}

.excel-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    font-size: 13px;

    th, td {
        border: 1px solid #e5e7eb;
        padding: 8px 12px;
        text-align: center;
        min-width: 200px;
    }

    .header-cell {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
        position: sticky;
        top: 0;
        z-index: 10;
        padding: 12px;
    }

    .config-header {
        background: #3b82f6;
        color: white;
        font-size: 14px;
        font-weight: 600;
    }

    .capacity-header {
        background: #f9fafb;

    }

    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 20;
        background: white;
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }

    .model-cell {
        font-weight: 600;
        color: #1f2937;
        text-align: left;
        font-size: 14px;
        vertical-align: middle;

        // 跨行单元格样式优化
        &[rowspan] {
            background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
            border-right: 2px solid #e5e7eb;
        }
    }

    .capacity-cell {
        font-weight: 600;
        color: #4b5563;
        background: #fef3c7;
        min-width: 80px;
        font-size: 14px;
    }

    .data-row {
        transition: background-color 0.2s;

        &:hover {
            background: #f9fafb;

            .sticky-col {
                background: #f9fafb;
            }

            .capacity-cell {
                background: #fde68a;
            }
        }
    }

    .price-cell {
        font-family: 'Monaco', 'Menlo', monospace;
        padding: 10px 8px;
        min-width: 140px;
        vertical-align: middle;
    }

    .price-box {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: stretch;
    }

    .price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;

        &.single-price {
            justify-content: center;
            padding: 8px;
            background: #f3f4f6;
            font-size: 15px;
        }

        &.original-price {
            background: #f3f4f6;
            color: #6b7280;

            .price-label {
                font-size: 11px;
                color: #9ca3af;
            }

            .price-value {
                font-weight: 500;
                color: #6b7280;
            }
        }

        &.final-price {
            background: #ecfdf5;

            .price-label {
                font-size: 11px;
                color: #059669;
                font-weight: 500;
            }

            .price-value {
                font-weight: 700;
                color: #059669;
                font-size: 13px;
            }
        }
    }

    // 编辑模式样式
    .edit-mode-box {
        padding: 8px;
        background: #f0f9ff;
        border-radius: 4px;
        border: 1px solid #bfdbfe;
    }

    .edit-price-input {
        width: 120px;

        :deep(.el-input-number__decrease),
        :deep(.el-input-number__increase) {
            width: 24px;
        }

        :deep(.el-input__inner) {
            padding: 0 30px;
            font-size: 13px;
        }
    }
}
</style>
