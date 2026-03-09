<template>
    <div class="points-orders-container">
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="订单号">
                    <el-input v-model="searchForm.order_no" placeholder="请输入订单号" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
                        <el-option label="待发货" :value="0" />
                        <el-option label="已发货" :value="1" />
                        <el-option label="已完成" :value="2" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="order_no" label="订单号" width="200" />
                <el-table-column prop="goods_name" label="商品" min-width="180" />
                <el-table-column prop="points_price" label="积分" width="100" />
                <el-table-column label="收货信息" min-width="250">
                    <template #default="{ row }">
                        <div>{{ row.receiver_name }} {{ row.receiver_phone }}</div>
                        <div style="color: #999; font-size: 12px;">{{ row.receiver_address }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="status_text" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ row.status_text }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="下单时间" width="170">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="物流信息" min-width="180">
                    <template #default="{ row }">
                        <div v-if="row.express_no">
                            <div>{{ row.express_company }}</div>
                            <div style="color: #1890ff; font-size: 12px;">{{ row.express_no }}</div>
                        </div>
                        <span v-else style="color: #999;">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 0" type="primary" link size="small" @click="handleShip(row)">发货</el-button>
                        <el-button v-if="row.status >= 1 && row.express_no" type="warning" link size="small" @click="viewLogistics(row)">物流</el-button>
                        <el-button v-if="row.status === 1" type="success" link size="small" @click="handleComplete(row)">完成</el-button>
                        <span v-if="row.status === 2" style="color: #999; font-size: 12px;">已完成</span>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <!-- 发货弹窗 -->
        <el-dialog v-model="shipDialogVisible" title="发货" width="500px">
            <el-form :model="shipForm" label-width="100px">
                <el-form-item label="快递公司" required>
                    <el-select v-model="shipForm.express_company" placeholder="请选择快递公司" filterable>
                        <el-option label="顺丰速运" value="顺丰速运" />
                        <el-option label="中通快递" value="中通快递" />
                        <el-option label="圆通速递" value="圆通速递" />
                        <el-option label="韵达快递" value="韵达快递" />
                        <el-option label="申通快递" value="申通快递" />
                        <el-option label="邮政包裹" value="邮政包裹" />
                        <el-option label="EMS" value="EMS" />
                        <el-option label="京东快递" value="京东快递" />
                        <el-option label="极兔快递" value="极兔快递" />
                        <el-option label="德邦快递" value="德邦快递" />
                        <el-option label="百世快递" value="百世快递" />
                        <el-option label="天天快递" value="天天快递" />
                        <el-option label="其他" value="其他" />
                    </el-select>
                </el-form-item>
                <el-form-item label="快递单号" required>
                    <el-input v-model="shipForm.express_no" placeholder="请输入快递单号" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="shipDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmShip" :loading="shipLoading">确认发货</el-button>
            </template>
        </el-dialog>

        <!-- 物流信息弹窗 -->
        <el-dialog v-model="logisticsVisible" title="物流信息" width="600px">
            <div v-loading="logisticsLoading">
                <div class="mb-4">
                    <p><strong>快递公司：</strong>{{ logisticsData.express_company || '-' }}</p>
                    <p><strong>快递单号：</strong>{{ logisticsData.express_no || '-' }}</p>
                    <p><strong>物流状态：</strong><el-tag>{{ logisticsData.status_text }}</el-tag></p>
                </div>
                <el-timeline v-if="logisticsData.traces && logisticsData.traces.length > 0">
                    <el-timeline-item
                        v-for="(trace, index) in logisticsData.traces"
                        :key="index"
                        :timestamp="trace.ftime || trace.time"
                        placement="top"
                        :type="index === 0 ? 'primary' : ''"
                    >
                        {{ trace.context }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无物流信息" />
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPointsOrderList, setPointsOrderStatus, shipPointsOrder, getPointsLogistics } from '@/addon/sd_xiaoyuan/api/pointsMall'

const loading = ref(false)
const list = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })
const searchForm = reactive({ order_no: '', status: '' })

// 发货相关
const shipDialogVisible = ref(false)
const shipLoading = ref(false)
const currentShipOrder = ref<any>(null)
const shipForm = reactive({ express_company: '', express_no: '' })

// 物流相关
const logisticsVisible = ref(false)
const logisticsLoading = ref(false)
const logisticsData = ref<any>({})

onMounted(() => { loadList() })

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getPointsOrderList({ ...searchForm, page: pagination.page, limit: pagination.limit })
        list.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } catch (e) { console.error(e) }
    finally { loading.value = false }
}

const handleReset = () => {
    searchForm.order_no = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const statusTagType = (status: number) => {
    const map: Record<number, string> = { 0: 'warning', 1: '', 2: 'success' }
    return map[status] || 'info'
}

const handleShip = (row: any) => {
    currentShipOrder.value = row
    shipForm.express_company = ''
    shipForm.express_no = ''
    shipDialogVisible.value = true
}

const confirmShip = async () => {
    if (!shipForm.express_company) {
        ElMessage.warning('请选择快递公司')
        return
    }
    if (!shipForm.express_no) {
        ElMessage.warning('请输入快递单号')
        return
    }
    shipLoading.value = true
    try {
        await shipPointsOrder(currentShipOrder.value.id, shipForm.express_company, shipForm.express_no)
        ElMessage.success('发货成功')
        shipDialogVisible.value = false
        loadList()
    } catch (e: any) {
        ElMessage.error(e.message || '发货失败')
    } finally {
        shipLoading.value = false
    }
}

const viewLogistics = async (row: any) => {
    logisticsLoading.value = true
    logisticsVisible.value = true
    logisticsData.value = {
        express_company: row.express_company || '',
        express_no: row.express_no || '',
        traces: [],
        status_text: '加载中...'
    }
    try {
        const res: any = await getPointsLogistics(row.id)
        if (res.data && res.data.success) {
            logisticsData.value = {
                express_company: res.data.express_company || row.express_company || '',
                express_no: res.data.express_no || row.express_no || '',
                traces: res.data.data?.traces || [],
                status_text: res.data.data?.status_text || '运输中'
            }
        } else {
            logisticsData.value.status_text = res.data?.message || '查询失败'
        }
    } catch (e: any) {
        logisticsData.value.status_text = e.message || '查询失败'
    } finally {
        logisticsLoading.value = false
    }
}

const handleComplete = (row: any) => {
    ElMessageBox.confirm(`确认将订单 ${row.order_no} 标记为已完成？`, '完成确认', { type: 'warning' }).then(async () => {
        try {
            await setPointsOrderStatus(row.id, 2)
            ElMessage.success('已标记为已完成')
            loadList()
        } catch (e: any) { ElMessage.error(e.message || '操作失败') }
    }).catch(() => {})
}

</script>

<style scoped>
.points-orders-container { padding: 20px; }
.search-card { margin-bottom: 20px; }
</style>
