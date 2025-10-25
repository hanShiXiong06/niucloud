<template>
    <div class="main-container">
         <!-- 数据概况统计 -->
 <DataStatistics 
                :auto-refresh="false"
                @filter-change="handleStatisticsFilterChange"
                ref="dataStatisticsRef"
            />
        <el-card class="box-card rounded-lg !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ t('device_query.result.title') }}</span>
                
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="queryParams" ref="searchFormRef" class="search-form">
                    <el-form-item :label="t('device_query.result.queryCode')" prop="query_code">
                        <el-input
                            v-model="queryParams.query_code"
                            :placeholder="t('device_query.result.queryCodePlaceholder')"
                            class="input-width"
                        />
                    </el-form-item>
                    <el-form-item :label="t('device_query.result.queryType')" prop="query_type">
                        <el-select v-model="queryParams.query_type" :placeholder="t('device_query.result.queryTypePlaceholder')" class="input-width">
                            <el-option :label="t('device_query.result.all')" value="" />
                            <el-option :label="t('device_query.result.queryTypes.imei')" value="imei" />
                            <el-option :label="t('device_query.result.queryTypes.serial')" value="serial" />
                            <el-option :label="t('device_query.result.queryTypes.model')" value="model" />
                            <el-option :label="t('device_query.result.queryTypes.coverage')" value="coverage" />
                            <el-option :label="t('device_query.result.queryTypes.activationlock')" value="activationlock" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('device_query.result.status')" prop="status">
                        <el-select v-model="queryParams.status" :placeholder="t('device_query.result.statusPlaceholder')" class="input-width">
                            <el-option :label="t('device_query.result.all')" value="" />
                            <el-option :label="t('device_query.result.statusOptions.success')" :value="1" />
                            <el-option :label="t('device_query.result.statusOptions.failed')" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">{{ t('device_query.result.search') }}</el-button>
                        <el-button @click="resetSearch">{{ t('device_query.result.reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

           

            <div class="mt-[10px]">
                <el-table :data="list" size="large" v-loading="loading" @selection-change="handleSelectionChange">
                    <el-table-column type="selection" width="55" />
                    <el-table-column :label="t('device_query.result.queryCode')" prop="query_code" min-width="150" />
                    <el-table-column :label="t('device_query.result.queryType')" prop="query_type_name" min-width="100" />
                    <el-table-column :label="t('device_query.result.api_endpoint')" prop="api_endpoint" min-width="200" />
                    <el-table-column :label="t('device_query.result.status')" prop="status" min-width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
                                {{ row.status_name }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.result.cost')" prop="cost_amount" min-width="80">
                        <template #default="{ row }">
                            ¥{{ row.cost_amount }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.result.responseTime')" prop="response_time" min-width="80">
                        <template #default="{ row }">
                            {{ row.response_time }}ms
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.result.operator')" prop="operator_name" min-width="100" />
                    <el-table-column :label="t('device_query.result.queryTime')" prop="create_at" min-width="160">
                        <template #default="{ row }">
                            {{ formatTime(row.create_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.result.operation')" fixed="right" width="180">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="viewEvent(row)">{{ t('device_query.result.view') }}</el-button>
                            <el-button type="warning" link @click="requeryEvent(row)" v-if="row.status !== 1">{{ t('device_query.result.requery') }}</el-button>
                            <el-button type="danger" link @click="deleteEvent(row.id)">{{ t('device_query.result.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="mt-[16px] flex justify-between">
                    <div>
                        <el-button type="danger" @click="batchDelete" :disabled="selectedIds.length === 0">
                            {{ t('device_query.result.batchDelete') }} ({{ selectedIds.length }})
                        </el-button>
                    </div>
                    <el-pagination
                        v-model:current-page="queryParams.page"
                        v-model:page-size="queryParams.limit"
                        layout="total, sizes, prev, pager, next, jumper"
                        :total="total"
                        @size-change="loadList"
                        @current-change="loadList"
                    />
                </div>
            </div>
        </el-card>

        <!-- 查询结果详情对话框 -->
        <el-dialog v-model="showViewDialog" :title="t('device_query.result.detailDialog')" width="90%" max-height="80vh">
            <div v-if="viewData">
                <!-- 如果是保修查询结果，使用专门的保修信息展示组件 -->
                <div v-if="isWarrantyQuery(viewData)" class="warranty-result-container">
                    <WarrantyInfoDisplay :warrantyData="parseWarrantyData(viewData.query_result)" />
                </div>
                
                <!-- 其他类型的查询结果，使用原有的展示方式 -->
                <div v-else>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('device_query.result.queryCode')">{{ viewData.query_code }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.queryType')">{{ viewData.query_type_name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.apiEndpoint')">{{ viewData.api_endpoint }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.apiName')">{{ viewData.api_name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.status')">{{ viewData.status_name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.cost')">¥{{ viewData.cost_amount }}</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.responseTime')">{{ viewData.response_time }}ms</el-descriptions-item>
                        <el-descriptions-item :label="t('device_query.result.operator')">{{ viewData.operator_name }}</el-descriptions-item>
                    </el-descriptions>

                    <div class="mt-4">
                        <el-tabs>
                            <el-tab-pane :label="t('device_query.result.queryResult')">
                                <pre class="bg-gray-100 p-4 rounded max-h-96 overflow-auto">{{ JSON.stringify(viewData.query_result, null, 2) }}</pre>
                            </el-tab-pane>
                            <el-tab-pane :label="t('device_query.result.rawResponse')">
                                <pre class="bg-gray-100 p-4 rounded max-h-96 overflow-auto">{{ JSON.stringify(viewData.raw_response, null, 2) }}</pre>
                            </el-tab-pane>
                        </el-tabs>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { t } from '@/lang'
import {
    getDeviceQueryResultList,
    getDeviceQueryResultInfo,
    deleteDeviceQueryResult,
    batchDeleteDeviceQueryResult,
    cleanDeviceQueryCache,
    requeryDeviceQuery,
    exportDeviceQueryResult,
    getTotalConsumption
} from '@/addon/recycle/api/device_query_result'
import WarrantyInfoDisplay from '@/addon/recycle/components/WarrantyInfoDisplay.vue'
import DataStatistics from '@/addon/recycle/components/DataStatistics.vue'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const showViewDialog = ref(false)
const viewData = ref<any>(null)
const selectedIds = ref<number[]>([])
const searchFormRef = ref()
const dataStatisticsRef = ref()

// 查询参数
const queryParams = reactive({
    query_code: '',
    query_type: '',
    status: '',
    page: 1,
    limit: 10,
    create_at: [] as string[]
})

// 加载列表数据
const loadList = async () => {
    loading.value = true
    try {
        const data = await getDeviceQueryResultList(queryParams)
        list.value = data.data.data
        total.value = data.data.total
    } catch (error) {
        console.error(error)
    }
    loading.value = false
}

// 查看详情
const viewEvent = async (row: any) => {
    try {
        const data = await getDeviceQueryResultInfo(row.id)
        viewData.value = data.data
        showViewDialog.value = true
    } catch (error) {
        console.error(error)
    }
}

// 删除
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('device_query.result.deleteConfirm'), t('device_query.result.warning'), {
        confirmButtonText: t('device_query.result.confirmText'),
        cancelButtonText: t('device_query.result.cancelText'),
        type: 'warning'
    }).then(async () => {
        try {
            await deleteDeviceQueryResult(id)
            ElMessage.success(t('device_query.result.deleteSuccess'))
            await loadList()
        } catch (error) {
            console.error(error)
        }
    })
}

// 批量删除
const batchDelete = () => {
    if (selectedIds.value.length === 0) return
    
    ElMessageBox.confirm(t('device_query.result.batchDeleteConfirm').replace('{count}', selectedIds.value.length.toString()), t('device_query.result.batchDeleteTitle'), {
        confirmButtonText: t('device_query.result.confirmText'),
        cancelButtonText: t('device_query.result.cancelText'),
        type: 'warning'
    }).then(async () => {
        try {
            await batchDeleteDeviceQueryResult(selectedIds.value)
            ElMessage.success(t('device_query.result.deleteSuccess'))
            selectedIds.value = []
            await loadList()
        } catch (error) {
            console.error(error)
        }
    })
}

// 重新查询
const requeryEvent = async (row: any) => {
    try {
        await requeryDeviceQuery(row.id)
        ElMessage.success(t('device_query.result.requerySuccess'))
        await loadList()
    } catch (error) {
        console.error(error)
    }
}

// 清理缓存
const cleanCache = () => {
    ElMessageBox.confirm(t('device_query.result.cleanCacheConfirm'), t('device_query.result.warning'), {
        confirmButtonText: t('device_query.result.confirmText'),
        cancelButtonText: t('device_query.result.cancelText'),
        type: 'warning'
    }).then(async () => {
        try {
            const data = await cleanDeviceQueryCache()
            ElMessage.success(t('device_query.result.cleanSuccess').replace('{count}', data.data.cleaned_count.toString()))
        } catch (error) {
            console.error(error)
        }
    })
}

// 导出数据
const exportData = async () => {
    try {
        const data = await exportDeviceQueryResult(queryParams)
        ElMessage.success(t('device_query.result.exportSuccess').replace('{count}', data.data.count.toString()))
        // 这里可以添加下载逻辑
    } catch (error) {
        console.error(error)
    }
}

// 重置搜索
const resetSearch = () => {
    queryParams.query_code = ''
    queryParams.query_type = ''
    queryParams.status = ''
    queryParams.create_at = []
    loadList()
}

// 选择变更
const handleSelectionChange = (selection: any[]) => {
    selectedIds.value = selection.map(item => item.id)
}

// 格式化时间
const formatTime = (timestamp: number) => {
    return new Date(timestamp * 1000).toLocaleString()
}

// 判断是否为保修查询结果
const isWarrantyQuery = (data: any) => {
    // 判断是否为苹果保修查询结果
    if (!data.query_result || typeof data.query_result !== 'object') {
        return false
    }
    
    const result = data.query_result
    // 检查是否包含苹果保修查询的特征字段
    return result.sn && result.model && result.coverage && result.purchase
}

// 解析保修数据
const parseWarrantyData = (queryResult: any) => {
    if (!queryResult || typeof queryResult !== 'object') {
        return getDefaultWarrantyData()
    }
    
    try {
        // 如果queryResult是字符串，先解析JSON
        const data = typeof queryResult === 'string' ? JSON.parse(queryResult) : queryResult
        
        // 确保所有必需字段都有默认值
        return {
            sn: data.sn || '',
            model: data.model || '未知型号',
            capacity: data.capacity || '',
            color: data.color || '',
            modelnumber: data.modelnumber || '',
            identifier: data.identifier || '',
            activated: Boolean(data.activated),
            image: data.image || '',
            fmi: data.fmi || '',
            locked: data.locked || '',
            purchase: {
                date: data.purchase?.date || '',
                validated: Boolean(data.purchase?.validated)
            },
            activation: {
                date: data.activation?.date || ''
            },
            coverage: {
                status: data.coverage?.status || '未知',
                description: data.coverage?.description || '',
                date: data.coverage?.date || '',
                'days-remaining': Number(data.coverage?.['days-remaining']) || 0
            },
            support: data.support || '',
            applecare: Boolean(data.applecare),
            'applecare-eligible': data['applecare-eligible'] || 'N',
            brightstar: data.brightstar || 'No',
            'pre-activated': Boolean(data['pre-activated']),
            registered: data.registered || 'N',
            loaner: data.loaner || 'N',
            replaced: Boolean(data.replaced),
            manufacture: {
                date: data.manufacture?.date || ''
            },
            manufacturer: data.manufacturer || '',
            source: data.source || '',
            balance: Number(data.balance) || 0,
            maintenance: Boolean(data.maintenance)
        }
    } catch (error) {
        console.error('解析保修数据失败:', error)
        return getDefaultWarrantyData()
    }
}

// 获取默认保修数据
const getDefaultWarrantyData = () => {
    return {
        sn: '',
        model: '未知型号',
        capacity: '',
        color: '',
        modelnumber: '',
        identifier: '',
        activated: false,
        image: '',
        fmi: '',
        locked: '',
        purchase: {
            date: '',
            validated: false
        },
        activation: {
            date: ''
        },
        coverage: {
            status: '未知',
            description: '',
            date: '',
            'days-remaining': 0
        },
        support: '',
        applecare: false,
        'applecare-eligible': 'N',
        brightstar: 'No',
        'pre-activated': false,
        registered: 'N',
        loaner: 'N',
        replaced: false,
        manufacture: {
            date: ''
        },
        manufacturer: '',
        source: '',
        balance: 0,
        maintenance: false
    }
}

// 处理数据统计过滤器变化
const handleStatisticsFilterChange = (filters: any) => {
    // 将统计组件的筛选条件同步到查询参数
    if (filters.dateRange && filters.dateRange.length === 2) {
        // 将日期范围添加到查询参数，使用create_at格式
        queryParams.create_at = filters.dateRange
        console.log('日期范围:', filters.dateRange)
    }else{
        queryParams.create_at = []
    }
    
    if (filters.queryType) {
        queryParams.query_type = filters.queryType
    }
    
    if (filters.status !== '') {
        queryParams.status = filters.status
    }
    
    // 重新加载列表数据
    loadList()
}

onMounted(() => {
    loadList()
})
</script>

<script lang="ts">
export default {
    name: 'DeviceQueryResult'
}
</script>

<style lang="scss" scoped>
.input-width {
    width: 180px;
}

pre {
    max-height: 400px;
    overflow-y: auto;
}
</style> 