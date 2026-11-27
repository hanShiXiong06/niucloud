<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="page">
                <div class="page-head">
                    <div>
                        <div class="text-page-title">卡种管理</div>
                        <p>配置售价、有效期和贴膜次数。已产生订单的卡种只允许停用，不删除历史。</p>
                    </div>
                    <el-button type="primary" @click="openEdit()">
                        <el-icon><Plus /></el-icon>
                        新建卡种
                    </el-button>
                </div>

                <div class="toolbar">
                    <el-input v-model="query.keyword" clearable placeholder="卡种名称 / 编号" @keyup.enter="load" />
                    <el-select v-model="query.status" clearable placeholder="全部状态">
                        <el-option label="草稿" value="draft" />
                        <el-option label="已启用" value="enabled" />
                        <el-option label="已停用" value="disabled" />
                    </el-select>
                    <el-button type="primary" @click="load">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                </div>

                <el-table :data="rows" v-loading="loading">
                    <el-table-column label="卡种" min-width="230">
                        <template #default="{ row }">
                            <b>{{ row.product_name }}</b>
                            <div class="sub">{{ row.product_no }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="售价" width="130">
                        <template #default="{ row }">
                            <b>¥{{ money(row.sale_price) }}</b>
                            <div v-if="Number(row.market_price) > 0" class="sub line">¥{{ money(row.market_price) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="服务权益" min-width="210">
                        <template #default="{ row }">
                            <div>{{ row.item?.item_name || '—' }} · {{ row.item?.usage_mode === 'unlimited' ? '不限次' : `${row.item?.total_times || 0} 次` }}</div>
                            <div class="sub">{{ bindingText(row.item) }}<span v-if="Number(row.item?.daily_limit || 0) > 0"> · 每日限 {{ row.item.daily_limit }} 次</span></div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="validity_text" label="有效期" min-width="150" />
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }"><MemberCardStatusTag :value="row.status" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="210" align="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
                            <el-button v-if="row.status !== 'enabled'" link type="success" @click="changeStatus(row, 'enabled')">启用</el-button>
                            <el-button v-else link type="warning" @click="changeStatus(row, 'disabled')">停用</el-button>
                            <el-button v-if="row.status === 'draft'" link type="danger" @click="remove(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination">
                    <el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total,prev,pager,next" :total="total" @current-change="load" />
                </div>
                <MemberCardProductDialog ref="productDialogRef" @saved="load" />
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { deleteCardProduct, disableCardProduct, enableCardProduct, getCardProducts } from '../../api'
import MemberCardProductDialog from '../../components/MemberCardProductDialog.vue'
import MemberCardStatusTag from '../../components/MemberCardStatusTag.vue'

const query = reactive({ keyword: '', status: '', page: 1, limit: 15 })
const rows = ref<any[]>([])
const total = ref(0)
const loading = ref(false)
const productDialogRef = ref<InstanceType<typeof MemberCardProductDialog>>()
const money = (value: any) => Number(value || 0).toFixed(2)
const bindingText = (item: any) => ({ imei: '绑定指定 IMEI', model: '限定产品型号', member: '按会员本人' }[item?.binding_mode || 'member'] || '按会员本人')

const load = async () => {
    loading.value = true
    try {
        const data: any = (await getCardProducts(query)).data || {}
        rows.value = data.data || []
        total.value = data.total || 0
    } finally {
        loading.value = false
    }
}

const reset = () => {
    Object.assign(query, { keyword: '', status: '', page: 1 })
    load()
}

const openEdit = (row?: any) => productDialogRef.value?.open(Number(row?.id || 0))

const changeStatus = async (row: any, status: string) => {
    await ElMessageBox.confirm(
        status === 'enabled' ? '启用后即可用于开卡，确认启用？' : '停用后不能新开卡，历史卡不受影响，确认停用？',
        '敏感操作确认',
        { type: 'warning' }
    )
    await (status === 'enabled' ? enableCardProduct(row.id) : disableCardProduct(row.id))
    ElMessage.success('状态已更新')
    load()
}

const remove = async (row: any) => {
    await ElMessageBox.confirm('只允许删除未启用且无历史订单的草稿，确认删除？', '删除卡种', { type: 'warning' })
    await deleteCardProduct(row.id)
    ElMessage.success('已删除')
    load()
}

onMounted(load)
</script>

<style scoped>
.page-head, .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.page-head { align-items: flex-start; margin-bottom: 20px; }
.page-head p { margin: 6px 0 0; color: var(--el-text-color-secondary); }
.toolbar { justify-content: flex-start; margin-bottom: 14px; padding: 14px 16px; border-radius: 4px; background: var(--el-fill-color-lighter); }
.toolbar .el-input { width: 280px; }
.toolbar .el-select { width: 150px; }
.sub { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }
.line { text-decoration: line-through; }
.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
</style>
