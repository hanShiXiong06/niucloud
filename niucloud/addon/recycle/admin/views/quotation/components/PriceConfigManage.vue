<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <span class="text-lg">价格配置管理</span>
            <div class="flex gap-2">
                <el-button type="danger" @click="clearAllConfig" :disabled="table.total === 0">
                    清空所有配置
                </el-button>
                <el-button type="danger" @click="batchDeleteConfig" :disabled="selectedRowIds.length === 0">
                    批量删除（{{ selectedRowIds.length }}）
                </el-button>
                <el-button type="primary" @click="addEvent">添加价格配置</el-button>
            </div>
        </div>

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
                    <el-button @click="resetForm">重置</el-button>
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
                <el-table-column prop="title" label="标题" min-width="120" />
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

        <edit ref="editDialog" @complete="loadList" />
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import type { FormInstance } from 'element-plus'
import { ElMessageBox, ElMessage } from 'element-plus'
import { 
    getQuotationPriceConfigList, 
    deleteQuotationPriceConfig, 
    modifyQuotationPriceConfigStatus, 
    batchDeleteQuotationPriceConfig, 
    clearAllQuotationPriceConfig 
} from '@/addon/recycle/api/quotation'
import Edit from '@/addon/recycle/views/quotation/components/quotation-price-config-edit.vue'

const searchFormRef = ref<FormInstance>()
const editDialog = ref<InstanceType<typeof Edit>>()

// 表格选中行
const selectedRowIds = ref<number[]>([])

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
 * 禁用/开启
 */
const modifyStatusEvent = (row: any) => {
    const status = row.is_enable === 1 ? 0 : 1
    modifyQuotationPriceConfigStatus(row.id, { is_enable: status }).then(() => {
        loadList()
    }).catch(() => {})
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
        `确定要清空所有 ${table.total} 条价格配置吗？此操作不可恢复！`,
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
            loadList()
        }).catch(() => {})
    })
}

const resetForm = () => {
    if (!searchFormRef.value) return
    searchFormRef.value.resetFields()
    loadList()
}

// 初始化加载
loadList()

// 暴露方法供父组件调用
defineExpose({
    loadList,
})
</script>

<style scoped>
</style>

