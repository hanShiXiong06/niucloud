<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <span class="text-page-title">费用统计</span>

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
                    <el-form-item label="日期范围">
                        <el-date-picker
                            v-model="dateRange"
                            type="daterange"
                            range-separator="至"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            value-format="YYYY-MM-DD"
                        />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadStatsList()">查询</el-button>
                        <el-button @click="resetForm">重置</el-button>
                    </el-form-item>
                </el-form>

                <!-- 汇总统计卡片 -->
                <div class="grid grid-cols-5 gap-4 mb-4">
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">总调用次数</div>
                        <div class="text-2xl font-bold mt-2">{{ summary.total_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">成功次数</div>
                        <div class="text-2xl font-bold mt-2 text-green-500">{{ summary.success_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">失败次数</div>
                        <div class="text-2xl font-bold mt-2 text-red-500">{{ summary.failed_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">总费用</div>
                        <div class="text-2xl font-bold mt-2 text-blue-500">¥{{ summary.total_cost || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">平均耗时</div>
                        <div class="text-2xl font-bold mt-2">{{ summary.avg_duration || 0 }}ms</div>
                    </el-card>
                </div>

                <!-- 数据表格 -->
                <el-table v-loading="loading" :data="statsList" stripe style="width: 100%" show-summary>
                    <el-table-column prop="date" label="日期" width="120" />
                    <el-table-column prop="service_type" label="服务类型" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.service_type === 'device_query'" type="primary" size="small">设备查询</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_order'" type="success" size="small">快递下单</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_query'" type="info" size="small">快递查询</el-tag>
                            <el-tag v-else size="small">{{ row.service_type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="provider_name" label="服务商" width="120" />
                    <el-table-column prop="total_calls" label="总调用" width="100" sortable />
                    <el-table-column prop="success_calls" label="成功" width="100" sortable>
                        <template #default="{ row }">
                            <span class="text-green-500">{{ row.success_calls }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="failed_calls" label="失败" width="100" sortable>
                        <template #default="{ row }">
                            <span class="text-red-500">{{ row.failed_calls }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="success_rate" label="成功率" width="100">
                        <template #default="{ row }">
                            {{ calculateSuccessRate(row) }}%
                        </template>
                    </el-table-column>
                    <el-table-column prop="total_cost" label="总费用" width="120" sortable>
                        <template #default="{ row }">
                            <span class="text-blue-500 font-semibold">¥{{ row.total_cost }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="avg_duration" label="平均耗时" width="120" sortable>
                        <template #default="{ row }">
                            {{ row.avg_duration }}ms
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
                        @size-change="loadStatsList"
                        @current-change="loadStatsList"
                    />
                </div>
            </el-card>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { apiThirdPartyCostStatsLists } from '@/addon/hsx_recycle/api/third_party'

const loading = ref(false)
const dateRange = ref([])

const formData = reactive({
    service_type: '',
    provider_name: '',
    start_date: '',
    end_date: '',
    page: 1,
    limit: 10
})

const statsList = ref([])
const total = ref(0)
const summary = ref({
    total_calls: 0,
    success_calls: 0,
    failed_calls: 0,
    total_cost: 0,
    avg_duration: 0
})

// 监听时间范围变化
watch(dateRange, (val) => {
    if (val && val.length === 2) {
        formData.start_date = val[0]
        formData.end_date = val[1]
    } else {
        formData.start_date = ''
        formData.end_date = ''
    }
})

// 加载统计列表
const loadStatsList = async () => {
    loading.value = true
    try {
        const res = await apiThirdPartyCostStatsLists(formData)
        statsList.value = res.data.data
        total.value = res.data.total
        if (res.data.summary) {
            summary.value = res.data.summary
        }
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
    formData.start_date = ''
    formData.end_date = ''
    dateRange.value = []
    formData.page = 1
    loadStatsList()
}

// 计算成功率
const calculateSuccessRate = (row: any) => {
    if (row.total_calls === 0) return 0
    return ((row.success_calls / row.total_calls) * 100).toFixed(2)
}

onMounted(() => {
    // 默认查询最近7天
    const endDate = new Date()
    const startDate = new Date()
    startDate.setDate(startDate.getDate() - 7)

    dateRange.value = [
        startDate.toISOString().split('T')[0],
        endDate.toISOString().split('T')[0]
    ]

    loadStatsList()
})
</script>

<style scoped lang="scss">
.text-page-title {
    font-size: 18px;
    font-weight: 600;
}
</style>
