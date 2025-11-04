<template>
    <div class="quotation-data-page">
        <!-- 页面标题 -->
        <div class="page-header">
            <span class="page-title">报价数据管理</span>
        </div>

        <!-- 筛选表单 -->
        <el-card shadow="never" class="search-card">
            <el-form :inline="true" :model="searchForm" class="search-form">
                <el-form-item label="报价类型">
                    <el-select
                        v-model="searchForm.price_name"
                        placeholder="请选择报价类型"
                        clearable
                        style="width: 200px"
                    >
                        <el-option
                            v-for="item in priceTypeOptions"
                            :key="item"
                            :label="item"
                            :value="item"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="商品名称">
                    <el-input
                        v-model="searchForm.goods_name"
                        placeholder="请输入商品名称"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="容量">
                    <el-input
                        v-model="searchForm.capacity"
                        placeholder="请输入容量"
                        clearable
                        style="width: 150px"
                    />
                </el-form-item>

                <el-form-item label="报价日期">
                    <el-date-picker
                        v-model="searchForm.price_date"
                        type="date"
                        placeholder="选择日期"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width: 200px"
                    />
                </el-form-item>

                <el-form-item label="是否当前">
                    <el-select
                        v-model="searchForm.is_current"
                        placeholder="全部"
                        style="width: 120px"
                    >
                        <el-option label="全部" :value="null" />
                        <el-option label="是" :value="1" />
                        <el-option label="否" :value="0" />
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

        <!-- 数据表格 -->
        <el-card shadow="never" class="table-card">
            <!-- 表格工具栏 -->
            <div class="table-toolbar">
                <div class="toolbar-left">
                    <span class="data-count">共 {{ tableData.length }} 条数据</span>
                </div>
                <div class="toolbar-right">
                    <el-button
                        v-if="!editMode"
                        type="primary"
                        @click="enterEditMode"
                        :disabled="tableData.length === 0"
                    >
                        <el-icon><Edit /></el-icon>
                        批量修改价格
                    </el-button>
                    <template v-else>
                        <el-button type="success" @click="saveChanges" :loading="saving">
                            <el-icon><Check /></el-icon>
                            保存修改 ({{ changedCount }})
                        </el-button>
                        <el-button @click="cancelEdit">
                            <el-icon><Close /></el-icon>
                            取消
                        </el-button>
                    </template>
                </div>
            </div>

            <!-- 空数据提示 -->
            <el-empty v-if="tableData.length === 0 && !loading" description="暂无数据" />

            <!-- 多表格展示 -->
            <div v-else class="multi-tables-container">
                <div
                    v-for="(table, tableIdx) in groupedTables"
                    :key="table.id"
                    class="table-group"
                >
                    <!-- 表格标题 -->
                    <div class="table-group-header">
                        <div class="table-title">
                            <span class="title-text">配置组 {{ tableIdx + 1 }}</span>
                            <span class="title-meta">
                                {{ table.modelCount }} 个型号 · {{ table.rows.length }} 条数据 · {{ table.configColumns.length }} 个配置项
                            </span>
                        </div>
                        <div class="config-tags">
                            <el-tag
                                v-for="config in table.configColumns"
                                :key="config"
                                size="small"
                                type="info"
                            >
                                {{ config }}
                            </el-tag>
                        </div>
                    </div>

                    <!-- Excel风格表格 -->
                    <div class="excel-table-wrapper">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th class="fixed-col col-model">型号</th>
                                    <th class="fixed-col col-capacity">容量</th>
                                    <th
                                        v-for="configName in table.configColumns"
                                        :key="configName"
                                        class="col-price"
                                    >
                                        {{ configName }}
                                    </th>
                                    <th class="col-remark">备注说明</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in table.rows" :key="row.id">
                                    <td
                                        v-if="row.showModel"
                                        class="fixed-col col-model model-merged"
                                        :rowspan="row.modelRowspan"
                                    >
                                        {{ row.goods_name }}
                                    </td>
                                    <td class="fixed-col col-capacity">{{ row.capacity }}</td>
                                    <td
                                        v-for="configName in table.configColumns"
                                        :key="configName"
                                        class="col-price"
                                    >
                                        <div v-if="row.prices[configName]" class="price-cell">
                                            <!-- 查看模式 -->
                                            <div v-if="!editMode" class="price-view">
                                                <span class="price-value">¥{{ row.prices[configName] }}</span>
                                            </div>

                                            <!-- 编辑模式 -->
                                            <div v-else class="price-edit">
                                                <el-input
                                                    v-model="row.editPrices[configName]"
                                                    type="number"
                                                    size="small"
                                                    :min="0"
                                                    @input="onPriceChange(row, configName)"
                                                >
                                                    <template #prefix>¥</template>
                                                </el-input>
                                                <div
                                                    v-if="isPriceChanged(row, configName)"
                                                    class="price-diff"
                                                    :class="getPriceDiffClass(row, configName)"
                                                >
                                                    {{ getPriceDiff(row, configName) }}
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="empty-cell">-</span>
                                    </td>
                                    <td
                                        v-if="row.showRemark"
                                        class="col-remark remark-merged"
                                        :rowspan="row.remarkRowspan"
                                    >
                                        <div class="remark-content">
                                            {{ row.value_info || '-' }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 加载状态 -->
            <div v-if="loading" v-loading="loading" class="loading-wrapper" />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Edit, Check, Close } from '@element-plus/icons-vue'
import { getQuotationDataAll, batchUpdateQuotationPrice } from '@/addon/recycle/api/quotation'

// ==================== 响应式数据 ====================

// 搜索表单
const searchForm = reactive({
    quotation_id: '',
    price_name: '花机/内爆',
    goods_name: '',
    capacity: '',
    price_date: getCurrentDate(),
    is_current: 1
})

// 报价类型选项
const priceTypeOptions = ref<string[]>([])

// 表格数据
const tableData = ref<any[]>([])
const loading = ref(false)

// 编辑模式
const editMode = ref(false)
const saving = ref(false)
const priceChanges = ref<Record<string, any>>({})

// ==================== 计算属性 ====================

// 配置项排序优先级
const CONFIG_SORT_ORDER = [
    // 第一组：全套充新系列
    ['全套充新    橙色', '全套充新    白色', '全套充新    蓝色'],
    // 第二组：靓机-单机系列
    ['靓机-单机100🔋在保100+', '高保靓充50次内在保280+', '靓机-单机 95电池＋在保60+','小花电池95+保修无要求'],
    // 第三组：保靓充系列
    ['高保靓充100次内在保250+', '中保靓充100🔋在保100+', '靓机', '小花'],
    // 第四组：靓机/小花
    ['小花', '靓机'],
    // 第五组：花机/内爆
    ['花机', '内爆可测']
].flat()

// 配置项排序函数
function sortConfigItems (configItems: string[]): string[] {
    return configItems.sort((a, b) => {
        const indexA = CONFIG_SORT_ORDER.indexOf(a)
        const indexB = CONFIG_SORT_ORDER.indexOf(b)

        // 两者都在排序列表中
        if (indexA !== -1 && indexB !== -1) {
            return indexA - indexB
        }

        // 只有a在排序列表中，a优先
        if (indexA !== -1) return -1

        // 只有b在排序列表中，b优先
        if (indexB !== -1) return 1

        // 都不在排序列表中，按字母排序
        return a.localeCompare(b, 'zh-CN')
    })
}

// 修改数量
const changedCount = computed(() => {
    return Object.keys(priceChanges.value).length
})

// 按配置项组合分组的表格数据
const groupedTables = computed(() => {
    const data = tableData.value
    if (data.length === 0) return []

    // 1. 按配置项组合分组
    const configGroupMap = new Map<string, any[]>()

    data.forEach(row => {
        if (row.prices) {
            // 获取该行的配置项列表并按自定义顺序排序
            const configKeys = sortConfigItems(Object.keys(row.prices))
            const configKey = configKeys.join('|||')

            if (!configGroupMap.has(configKey)) {
                configGroupMap.set(configKey, [])
            }
            configGroupMap.get(configKey)!.push(row)
        }
    })

    // 2. 为每个分组生成表格数据
    const tables: any[] = []
    let tableIndex = 0

    configGroupMap.forEach((rows, configKey) => {
        tableIndex++
        const configColumns = configKey.split('|||')

        // 处理跨行合并（型号和备注）
        const processedRows: any[] = []
        let currentModel = ''
        let modelStartIndex = 0
        let currentRemark = ''
        let remarkStartIndex = 0

        rows.forEach((row, index) => {
            const processedRow = { ...row }

            // 处理型号跨行
            if (row.goods_name !== currentModel) {
                // 计算上一个型号的跨行数
                if (modelStartIndex < index) {
                    for (let i = modelStartIndex; i < index; i++) {
                        processedRows[i].modelRowspan = index - modelStartIndex
                    }
                }

                currentModel = row.goods_name
                modelStartIndex = index
                processedRow.showModel = true
                processedRow.modelRowspan = 1
            } else {
                processedRow.showModel = false
                processedRow.modelRowspan = 0
            }

            // 处理备注跨行
            const remark = row.add_value_info || ''
            if (remark !== currentRemark) {
                // 计算上一个备注的跨行数
                if (remarkStartIndex < index) {
                    for (let i = remarkStartIndex; i < index; i++) {
                        processedRows[i].remarkRowspan = index - remarkStartIndex
                    }
                }

                currentRemark = remark
                remarkStartIndex = index
                processedRow.showRemark = true
                processedRow.remarkRowspan = 1
            } else {
                processedRow.showRemark = false
                processedRow.remarkRowspan = 0
            }

            processedRows.push(processedRow)
        })

        // 处理最后一个型号的跨行
        if (modelStartIndex < processedRows.length) {
            for (let i = modelStartIndex; i < processedRows.length; i++) {
                processedRows[i].modelRowspan = processedRows.length - modelStartIndex
            }
        }

        // 处理最后一个备注的跨行
        if (remarkStartIndex < processedRows.length) {
            for (let i = remarkStartIndex; i < processedRows.length; i++) {
                processedRows[i].remarkRowspan = processedRows.length - remarkStartIndex
            }
        }

        tables.push({
            id: tableIndex,
            configColumns,
            rows: processedRows,
            modelCount: new Set(rows.map(r => r.goods_name)).size
        })
    })

    // 3. 按型号数量降序排序（让数据多的表格在前面）
    return tables
})

// ==================== 方法 ====================

// 获取当前日期
function getCurrentDate () {
    const today = new Date()
    const year = today.getFullYear()
    const month = String(today.getMonth() + 1).padStart(2, '0')
    const day = String(today.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

// 加载数据
async function loadData () {
    try {
        loading.value = true

        const params: any = {}
        if (searchForm.quotation_id) params.quotation_id = searchForm.quotation_id
        if (searchForm.price_name) params.price_name = searchForm.price_name
        if (searchForm.goods_name) params.goods_name = searchForm.goods_name
        if (searchForm.capacity) params.capacity = searchForm.capacity
        if (searchForm.price_date) params.price_date = searchForm.price_date
        if (searchForm.is_current !== null) params.is_current = searchForm.is_current

        const res = await getQuotationDataAll(params)

        if (res.data && Array.isArray(res.data)) {
            // 排序：按商品名称和容量排序，确保相同型号的记录在一起
            const sortedData = res.data
            // .sort((a, b) => {
            //     // 先按商品名称排序
            //     if (a.goods_name !== b.goods_name) {
            //         return a.goods_name.localeCompare(b.goods_name, 'zh-CN')
            //     }
            //     // 再按容量排序（提取数字部分）
            //     const getCapacityValue = (capacity: string) => {
            //         const match = capacity.match(/(\d+)/)
            //         return match ? parseInt(match[1]) : 0
            //     }
            //     return getCapacityValue(a.capacity) - getCapacityValue(b.capacity)
            // })

            // 处理数据：添加编辑用的价格字段
            tableData.value = sortedData.map(item => ({
                ...item,
                editPrices: { ...item.prices } // 复制一份用于编辑
            }))

            // 提取报价类型选项
            extractPriceTypes(res.data)
        } else {
            tableData.value = []
        }
    } catch (error) {
        console.error('加载数据失败:', error)
        ElMessage.error('加载数据失败')
        tableData.value = []
    } finally {
        loading.value = false
    }
}

// 提取报价类型选项
function extractPriceTypes (data: any[]) {
    // 提取报价类型选项
    // 1. 花机/内爆
    // 2. 靓机/小花
    priceTypeOptions.value = ['花机/内爆', '靓机/小花']
}

// 搜索
function handleSearch () {
    loadData()
}

// 重置
function handleReset () {
    searchForm.quotation_id = ''
    searchForm.price_name = '花机/内爆'
    searchForm.goods_name = ''
    searchForm.capacity = ''
    searchForm.price_date = getCurrentDate()
    searchForm.is_current = 1
    loadData()
}

// 进入编辑模式
function enterEditMode () {
    editMode.value = true
    priceChanges.value = {}

    // 重置所有编辑价格为原始价格
    tableData.value.forEach(row => {
        row.editPrices = { ...row.prices }
    })
}

// 取消编辑
function cancelEdit () {
    ElMessageBox.confirm('确定要取消修改吗？未保存的数据将会丢失', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(() => {
        editMode.value = false
        priceChanges.value = {}

        // 恢复原始价格
        tableData.value.forEach(row => {
            row.editPrices = { ...row.prices }
        })
    }).catch(() => {
        // 取消操作
    })
}

// 价格变化
function onPriceChange (row: any, configName: string) {
    const newPrice = parseFloat(row.editPrices[configName])
    const originalPrice = row.prices[configName]

    const changeKey = `${row.id}_${configName}`

    if (!isNaN(newPrice) && newPrice !== originalPrice && newPrice >= 0) {
        priceChanges.value[changeKey] = {
            id: row.id,
            goods_id: row.goods_id,
            capacity: row.capacity,
            config_name: configName,
            new_price: newPrice,
            original_price: originalPrice
        }
    } else {
        delete priceChanges.value[changeKey]
    }
}

// 判断价格是否改变
function isPriceChanged (row: any, configName: string) {
    const changeKey = `${row.id}_${configName}`
    return changeKey in priceChanges.value
}

// 获取价格差异
function getPriceDiff (row: any, configName: string) {
    const newPrice = parseFloat(row.editPrices[configName])
    const originalPrice = row.prices[configName]
    const diff = newPrice - originalPrice
    const percent = ((diff / originalPrice) * 100).toFixed(1)

    const arrow = diff > 0 ? '↑' : '↓'
    return `${arrow} ¥${Math.abs(diff)} (${Math.abs(parseFloat(percent))}%)`
}

// 获取价格差异样式
function getPriceDiffClass (row: any, configName: string) {
    const newPrice = parseFloat(row.editPrices[configName])
    const originalPrice = row.prices[configName]
    return newPrice > originalPrice ? 'price-up' : 'price-down'
}

// 保存修改
async function saveChanges () {
    if (changedCount.value === 0) {
        ElMessage.warning('没有需要保存的修改')
        return
    }

    try {
        saving.value = true

        const items = Object.values(priceChanges.value)
        await batchUpdateQuotationPrice({ items })

        ElMessage.success(`成功修改 ${items.length} 条价格`)

        editMode.value = false
        priceChanges.value = {}

        // 重新加载数据
        await loadData()
    } catch (error) {
        console.error('保存失败:', error)
        ElMessage.error('保存失败，请重试')
    } finally {
        saving.value = false
    }
}

// ==================== 生命周期 ====================

onMounted(() => {
    loadData()
})
</script>

<style scoped lang="scss">
.quotation-data-page {
    padding: 20px;
    background: #f5f7fa;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 20px;

    .page-title {
        font-size: 20px;
        font-weight: 600;
        color: #303133;
    }
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #ebeef5;

    .toolbar-left {
        .data-count {
            font-size: 14px;
            color: #606266;
        }
    }

    .toolbar-right {
        display: flex;
        gap: 10px;
    }
}

.loading-wrapper {
    height: 400px;
}

// 多表格容器
.multi-tables-container {
    padding: 20px;
}

.table-group {
    margin-bottom: 40px;

    &:last-child {
        margin-bottom: 0;
    }
}

.table-group-header {
    margin-bottom: 16px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px 8px 0 0;
    color: white;

    .table-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;

        .title-text {
            font-size: 16px;
            font-weight: 600;
        }

        .title-meta {
            font-size: 13px;
            opacity: 0.9;
        }
    }

    .config-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;

        :deep(.el-tag) {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }
    }
}

// Excel表格样式
.excel-table-wrapper {
    overflow-x: auto;
    border: 1px solid #e5e7eb;
    border-radius: 0 0 8px 8px;
}

.excel-table {

    border-collapse: collapse;
    background: white;
    font-size: 14px;

    th, td {
        border: 1px solid #dcdfe6;
        padding: 12px 16px;
        text-align: center;
    }

    thead {
        th {
            background: #f5f7fa;
            color: #303133;
            font-weight: 600;
            white-space: nowrap;
        }
    }

    tbody {
        tr {
            transition: background-color 0.2s;

            &:hover {
                background: #f5f7fa;
            }
        }

        td {
            color: #606266;
        }
    }

    .fixed-col {
        // position: sticky;
        background: white;
        z-index: 1;

        &.col-model {
            left: 0;
            width: 200px;
            font-weight: 500;
            color: #303133;

            &.model-merged {
                vertical-align: middle;
                background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
                font-weight: 600;
                font-size: 15px;
                border-right: 2px solid #e5e7eb;
            }
        }

        &.col-capacity {
            left: 200px;
            width: 100px;
            font-weight: 500;
        }
    }

    .col-price {
        min-width: 100px;
    }

    .col-remark {
        min-width: 150px;
        max-width: 400px;
        background: #fffbf0;

        &.remark-merged {
            vertical-align: top;
            padding: 16px;
        }
    }

    .empty-cell {
        color: #c0c4cc;
    }
}

// 备注内容
.remark-content {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.8;
    font-size: 13px;
    color: #606266;
    text-align: left;
    padding: 4px 0;
}

// 价格单元格
.price-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.price-view {
    .price-value {
        font-size: 16px;
        font-weight: 600;
        color: #409eff;
    }
}

.price-edit {
    width: 100%;

    :deep(.el-input) {
        width: 100%;
    }

    .price-diff {
        font-size: 12px;
        font-weight: 500;

        &.price-up {
            color: #f56c6c;
        }

        &.price-down {
            color: #67c23a;
        }
    }
}
</style>
