<template>
    <div class="house-order-list">
        <el-card class="search-card">
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
                        <el-option label="待处理" :value="0" />
                        <el-option label="已接受" :value="1" />
                        <el-option label="已拒绝" :value="2" />
                        <el-option label="已取消" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="tableData" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="房源" min-width="200">
                    <template #default="{ row }">
                        <div v-if="row.house">
                            <div class="font-bold">{{ row.house.title }}</div>
                            <div class="text-gray text-sm">{{ row.house.address }}</div>
                            <div class="text-price">¥{{ row.house.rent_price }}/月</div>
                        </div>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="预约人" width="180">
                    <template #default="{ row }">
                        <div>{{ row.contact_name }}</div>
                        <div class="text-gray text-sm">{{ row.contact_mobile }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="会员" width="150">
                    <template #default="{ row }">
                        <div v-if="row.member" class="flex-center">
                            <el-avatar :src="row.member.headimg" :size="28" class="mr-1" />
                            <span>{{ row.member.nickname }}</span>
                        </div>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="message" label="留言" min-width="150" show-overflow-tooltip />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 0" type="warning">待处理</el-tag>
                        <el-tag v-else-if="row.status === 1" type="success">已接受</el-tag>
                        <el-tag v-else-if="row.status === 2" type="danger">已拒绝</el-tag>
                        <el-tag v-else type="info">已取消</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="预约时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <template v-if="row.status === 0">
                            <el-button size="small" type="success" @click="handleOrder(row, 1)">接受</el-button>
                            <el-button size="small" type="danger" @click="handleOrder(row, 2)">拒绝</el-button>
                        </template>
                        <template v-else-if="row.status === 1">
                            <el-button size="small" type="warning" @click="handleRefundDeposit(row)" v-if="row.deposit_refunded !== 1">退押金</el-button>
                            <el-button size="small" type="danger" @click="handleRefundAll(row)" v-if="row.refund_status !== 1">全额退款</el-button>
                            <span v-if="row.refund_status === 1" class="text-gray">已退款</span>
                        </template>
                        <span v-else class="text-gray">已处理</span>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadData"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getHouseOrderList, handleHouseOrder, refundHouseDeposit, refundHouseAll } from '../../api/admin'

const searchForm = ref({ status: '' })
const tableData = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

onMounted(() => loadData())

const loadData = async () => {
    loading.value = true
    try {
        const res: any = await getHouseOrderList({ ...searchForm.value, page: page.value, limit: limit.value })
        if (res.code === 1) {
            tableData.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const handleSearch = () => { page.value = 1; loadData() }
const handleReset = () => { searchForm.value = { status: '' }; handleSearch() }


const handleOrder = async (row: any, status: number) => {
    const action = status === 1 ? '接受' : '拒绝'
    await ElMessageBox.confirm(`确定${action}该预约？`, '提示')
    const res: any = await handleHouseOrder({ id: row.id, status })
    if (res.code === 1) {
        ElMessage.success('操作成功')
        loadData()
    }
}

const handleRefundDeposit = async (row: any) => {
    await ElMessageBox.confirm('确定退还押金？', '提示')
    const res: any = await refundHouseDeposit({ id: row.id })
    if (res.code === 1) {
        ElMessage.success('押金退款成功')
        loadData()
    }
}

const handleRefundAll = async (row: any) => {
    await ElMessageBox.confirm('确定全额退款（押金+支付金额）？', '提示')
    const res: any = await refundHouseAll({ id: row.id })
    if (res.code === 1) {
        ElMessage.success('全额退款成功')
        loadData()
    }
}
</script>

<style scoped>
.search-card { margin-bottom: 16px; }
.text-gray { color: #999; }
.text-sm { font-size: 12px; }
.text-price { color: #ff4d4f; font-weight: bold; }
.font-bold { font-weight: bold; }
.flex-center { display: flex; align-items: center; }
.mr-1 { margin-right: 8px; }
</style>
