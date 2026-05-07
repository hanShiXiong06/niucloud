<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">API调用日志</span>
                <el-button type="danger" @click="handleCleanLogs">
                    <template #icon>
                        <el-icon><Delete /></el-icon>
                    </template>
                    清理日志
                </el-button>
            </div>

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
                    <el-form-item label="状态">
                        <el-select v-model="formData.status" placeholder="请选择" clearable>
                            <el-option label="成功" :value="1" />
                            <el-option label="失败" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="时间范围">
                        <el-date-picker
                            v-model="dateRange"
                            type="datetimerange"
                            range-separator="至"
                            start-placeholder="开始时间"
                            end-placeholder="结束时间"
                            value-format="YYYY-MM-DD HH:mm:ss"
                        />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadLogList()">查询</el-button>
                        <el-button @click="resetForm">重置</el-button>
                    </el-form-item>
                </el-form>

                <!-- 统计卡片 -->
                <div class="grid grid-cols-4 gap-4 mb-4">
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">总调用次数</div>
                        <div class="text-2xl font-bold mt-2">{{ stats.total_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">成功次数</div>
                        <div class="text-2xl font-bold mt-2 text-green-500">{{ stats.success_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">失败次数</div>
                        <div class="text-2xl font-bold mt-2 text-red-500">{{ stats.failed_calls || 0 }}</div>
                    </el-card>
                    <el-card shadow="hover">
                        <div class="text-sm text-gray-500">平均耗时</div>
                        <div class="text-2xl font-bold mt-2">{{ stats.avg_duration || 0 }}ms</div>
                    </el-card>
                </div>

                <!-- 数据表格 -->
                <el-table v-loading="loading" :data="logList" stripe style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80" />
                    <el-table-column prop="service_type" label="服务类型" width="120">
                        <template #default="{ row }">
                            <el-tag v-if="row.service_type === 'device_query'" type="primary" size="small">设备查询</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_order'" type="success" size="small">快递下单</el-tag>
                            <el-tag v-else-if="row.service_type === 'express_query'" type="info" size="small">快递查询</el-tag>
                            <el-tag v-else size="small">{{ row.service_type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="provider_name" label="服务商" width="120" />
                    <el-table-column prop="method" label="方法" width="150" />
                    <el-table-column prop="cost" label="费用" width="100">
                        <template #default="{ row }">
                            <span v-if="row.cost > 0">¥{{ row.cost }}</span>
                            <span v-else class="text-gray-400">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="duration" label="耗时" width="100">
                        <template #default="{ row }">
                            {{ row.duration }}ms
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag v-if="row.status === 1" type="success" size="small">成功</el-tag>
                            <el-tag v-else type="danger" size="small">失败</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_at" label="调用时间" width="180">
                        <template #default="{ row }">
                            {{ timeStampTurnTime(row.create_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" width="120">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="handleViewDetail(row)">详情</el-button>
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
                        @size-change="loadLogList"
                        @current-change="loadLogList"
                    />
                </div>
            </el-card>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="API调用详情" width="800px">
            <el-descriptions :column="2" border>
                <el-descriptions-item label="服务类型">{{ detailData.service_type }}</el-descriptions-item>
                <el-descriptions-item label="服务商">{{ detailData.provider_name }}</el-descriptions-item>
                <el-descriptions-item label="方法">{{ detailData.method }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag v-if="detailData.status === 1" type="success">成功</el-tag>
                    <el-tag v-else type="danger">失败</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="费用">¥{{ detailData.cost || 0 }}</el-descriptions-item>
                <el-descriptions-item label="耗时">{{ detailData.duration }}ms</el-descriptions-item>
                <el-descriptions-item label="调用时间" :span="2">
                    {{ timeStampTurnTime(detailData.create_at) }}
                </el-descriptions-item>
                <el-descriptions-item label="错误信息" :span="2" v-if="detailData.error_msg">
                    <span class="text-red-500">{{ detailData.error_msg }}</span>
                </el-descriptions-item>
            </el-descriptions>

            <el-divider content-position="left">请求参数</el-divider>
            <pre class="json-code">{{ JSON.stringify(detailData.request_params, null, 2) }}</pre>

            <el-divider content-position="left">响应数据</el-divider>
            <pre class="json-code">{{ JSON.stringify(detailData.response_data, null, 2) }}</pre>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import { timeStampTurnTime } from '@/utils/common'
import { apiThirdPartyApiLogLists, apiThirdPartyApiLogInfo, apiThirdPartyApiLogClean } from '@/addon/recycle/api/third_party'

const loading = ref(false)
const detailVisible = ref(false)
const dateRange = ref([])

const formData = reactive({
    service_type: '',
    provider_name: '',
    status: '',
    start_time: '',
    end_time: '',
    page: 1,
    limit: 10
})

const logList = ref([])
const total = ref(0)
const stats = ref({
    total_calls: 0,
    success_calls: 0,
    failed_calls: 0,
    avg_duration: 0
})

const detailData = ref({})

// 监听时间范围变化
watch(dateRange, (val) => {
    if (val && val.length === 2) {
        formData.start_time = val[0]
        formData.end_time = val[1]
    } else {
        formData.start_time = ''
        formData.end_time = ''
    }
})

// 加载日志列表
const loadLogList = async () => {
    loading.value = true
    try {
        const res = await apiThirdPartyApiLogLists(formData)
        logList.value = res.data.data
        total.value = res.data.total
        if (res.data.stats) {
            stats.value = res.data.stats
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
    formData.status = ''
    formData.start_time = ''
    formData.end_time = ''
    dateRange.value = []
    formData.page = 1
    loadLogList()
}

// 查看详情
const handleViewDetail = async (row: any) => {
    try {
        const res = await apiThirdPartyApiLogInfo(row.id)
        detailData.value = res.data
        detailVisible.value = true
    } catch (error) {
        ElMessage.error('加载详情失败')
    }
}

// 清理日志
const handleCleanLogs = () => {
    ElMessageBox.prompt('请输入要保留的天数（将删除N天前的日志）', '清理日志', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        inputPattern: /^\d+$/,
        inputErrorMessage: '请输入有效的天数'
    }).then(async ({ value }) => {
        try {
            await apiThirdPartyApiLogClean({ days: parseInt(value) })
            ElMessage.success('清理成功')
            loadLogList()
        } catch (error) {
            ElMessage.error('清理失败')
        }
    })
}

onMounted(() => {
    loadLogList()
})
</script>

<style scoped lang="scss">
.text-page-title {
    font-size: 18px;
    font-weight: 600;
}

.json-code {
    background-color: #f5f7fa;
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 400px;
    overflow-y: auto;
}
</style>
