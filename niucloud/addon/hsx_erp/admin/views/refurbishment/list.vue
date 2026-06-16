<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">整备工作台</div>
                    <div class="mt-1 text-sm text-gray-500">
                        一次记录整备项目和实际费用，完工后自动更新单机总成本并进入待销售定价。
                    </div>
                </div>
                <el-button type="primary" @click="openCreate()">发起整备</el-button>
            </div>

            <el-alert class="mt-4" type="info" :closable="false"
                title="建议只记录实际发生的单机费用。整备完成后的成本不可直接修改，纠错应通过成本冲销。" />

            <el-form :inline="true" class="mt-5" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable class="!w-[260px]"
                        placeholder="工单号 / IMEI / 型号 / 负责人" @keyup.enter="handleSearch" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="search.status" clearable class="!w-[130px]" @change="handleSearch">
                        <el-option label="整备中" value="processing" />
                        <el-option label="已完成" value="completed" />
                        <el-option label="已取消" value="cancelled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="负责人">
                    <el-select v-model="search.assigned_uid" clearable filterable class="!w-[150px]" placeholder="全部负责人" @change="handleSearch">
                        <el-option v-for="item in userOptions" :key="item.uid" :label="item.username || item.nickname || ('员工#' + item.uid)" :value="item.uid" />
                    </el-select>
                </el-form-item>
                <el-form-item label="创建时间">
                    <el-date-picker v-model="search.dateRange" type="daterange" value-format="X" range-separator="至"
                        start-placeholder="开始" end-placeholder="结束" style="width: 240px" @change="handleSearch" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="table.data" v-loading="table.loading" size="large" @sort-change="onSort">
                <el-table-column prop="order_no" label="整备单号" min-width="190" sortable="custom" />
                <el-table-column label="设备" min-width="240">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.asset?.model || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500">IMEI：{{ row.asset?.imei || '-' }}</div>
                        <div class="text-xs text-gray-500">资产：{{ row.asset?.asset_no || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="整备项目" min-width="220">
                    <template #default="{ row }">
                        <el-tag v-for="item in row.items || []" :key="item.id" class="mr-1 mb-1" effect="plain">
                            {{ item.item_name }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="assigned_name" label="负责人" width="120" />
                <el-table-column label="费用" width="120" align="right">
                    <template #default="{ row }">¥{{ money(row.total_cost) }}</template>
                </el-table-column>
                <el-table-column label="当前总成本" width="130" align="right">
                    <template #default="{ row }">¥{{ money(row.asset?.current_cost) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)">{{ statusName(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="开始时间" width="170" prop="create_at" sortable="custom">
                    <template #default="{ row }">{{ formatTime(row.started_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="180" align="center">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'processing'" type="primary" link @click="openComplete(row)">
                            完工验收
                        </el-button>
                        <el-button v-if="row.status === 'processing'" type="danger" link @click="cancelOrder(row)">
                            取消
                        </el-button>
                        <el-button type="primary" link @click="openDetail(row)">详情</el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.status"
                        icon="search"
                        title="没有符合条件的整备工单"
                        description="换个关键词或状态筛选再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="document"
                        title="还没有整备工单"
                        description="在库设备需要维修/翻新时发起整备；完工并确认费用后，设备进入待销售定价。"
                    >
                        <template #action>
                            <el-button type="primary" @click="openCreate()">发起整备</el-button>
                        </template>
                    </EmptyState>
                </template>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next" :total="table.total"
                    @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <el-dialog v-model="createDialog.visible" title="发起整备" width="680px" destroy-on-close>
            <el-form label-width="100px">
                <el-form-item label="选择设备" required>
                    <el-select v-model="createDialog.form.asset_id" filterable class="w-full"
                        placeholder="只显示当前在库、可发起整备的设备">
                        <el-option v-for="item in assetOptions" :key="item.id"
                            :label="assetLabel(item)" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="常用项目" required>
                    <el-checkbox-group v-model="createDialog.form.preset_keys" class="grid grid-cols-4 gap-2">
                        <el-checkbox v-for="item in presets" :key="item.key" :value="item.key" border>
                            {{ item.name }}
                        </el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item label="其他项目">
                    <el-input v-model.trim="createDialog.form.custom_item" placeholder="例如：更换听筒、防水处理" />
                </el-form-item>
                <el-form-item label="负责人" required>
                    <el-select v-model="createDialog.form.assigned_uid" filterable class="w-full">
                        <el-option v-for="item in userOptions" :key="item.uid"
                            :label="userName(item)" :value="item.uid" />
                    </el-select>
                </el-form-item>
                <el-form-item label="计划完成">
                    <el-date-picker v-model="createDialog.form.planned_finish_at" type="datetime"
                        value-format="X" class="!w-full" placeholder="可选" />
                </el-form-item>
                <el-form-item label="整备说明">
                    <el-input v-model.trim="createDialog.form.remark" type="textarea" :rows="3"
                        placeholder="只填写需要特别注意的问题" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="createDialog.loading" @click="submitCreate">开始整备</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="completeDialog.visible" title="完工验收与成本确认" width="760px" destroy-on-close>
            <el-alert class="mb-4" type="warning" :closable="false"
                title="请填写实际发生金额。确认后费用会立即计入该设备成本，并进入待销售定价。" />
            <el-table :data="completeDialog.items" border>
                <el-table-column label="项目" min-width="180">
                    <template #default="{ row }"><el-input v-model.trim="row.item_name" /></template>
                </el-table-column>
                <el-table-column label="费用类型" width="140">
                    <template #default="{ row }">
                        <el-select v-model="row.item_type">
                            <el-option label="配件" value="part" />
                            <el-option label="人工" value="labor" />
                            <el-option label="外修" value="external" />
                            <el-option label="物流" value="logistics" />
                            <el-option label="检测" value="inspection" />
                            <el-option label="其他" value="other" />
                        </el-select>
                    </template>
                </el-table-column>
                <el-table-column label="实际金额" width="150">
                    <template #default="{ row }">
                        <el-input-number v-model="row.amount" :min="0" :precision="2" class="!w-full" />
                    </template>
                </el-table-column>
                <el-table-column label="说明" min-width="180">
                    <template #default="{ row }"><el-input v-model.trim="row.remark" /></template>
                </el-table-column>
                <el-table-column width="60" align="center">
                    <template #default="{ $index }">
                        <el-button type="danger" link @click="completeDialog.items.splice($index, 1)">删</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-3 flex items-center justify-between">
                <el-button @click="addCompleteItem">添加项目</el-button>
                <div class="text-base font-medium">本次增加成本：¥{{ money(completeTotal) }}</div>
            </div>
            <el-form class="mt-4" label-width="100px">
                <el-form-item label="验收说明">
                    <el-input v-model.trim="completeDialog.completion_remark" type="textarea" :rows="3"
                        placeholder="例如：功能复检通过、外观清洁完成" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="completeDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="completeDialog.loading" @click="submitComplete">
                    确认完工
                </el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detail.visible" title="整备详情" size="680px">
            <el-descriptions v-if="detail.data.order" :column="2" border>
                <el-descriptions-item label="工单号">{{ detail.data.order.order_no }}</el-descriptions-item>
                <el-descriptions-item label="状态">{{ statusName(detail.data.order.status) }}</el-descriptions-item>
                <el-descriptions-item label="设备">{{ detail.data.asset?.model || '-' }}</el-descriptions-item>
                <el-descriptions-item label="IMEI">{{ detail.data.asset?.imei || '-' }}</el-descriptions-item>
                <el-descriptions-item label="负责人">{{ detail.data.order.assigned_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="整备费用">¥{{ money(detail.data.order.total_cost) }}</el-descriptions-item>
                <el-descriptions-item label="当前总成本">¥{{ money(detail.data.asset?.current_cost) }}</el-descriptions-item>
                <el-descriptions-item label="开始时间">{{ formatTime(detail.data.order.started_at) }}</el-descriptions-item>
            </el-descriptions>
            <div class="mt-5 font-medium">项目与费用</div>
            <el-table class="mt-3" :data="detail.data.items || []" size="small">
                <el-table-column prop="item_name" label="项目" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">{{ itemTypeName(row.item_type) }}</template>
                </el-table-column>
                <el-table-column label="金额" width="120" align="right">
                    <template #default="{ row }">¥{{ money(row.amount) }}</template>
                </el-table-column>
                <el-table-column prop="remark" label="说明" />
            </el-table>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { getErpAssetList } from '@/addon/hsx_erp/api/asset'
import {
    cancelErpRefurbishment, completeErpRefurbishment, createErpRefurbishment,
    getErpRefurbishmentInfo, getErpRefurbishmentList, getErpRefurbishmentUsers
} from '@/addon/hsx_erp/api/refurbishment'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const presets = [
    { key: 'clean', name: '清洁消毒', type: 'labor' },
    { key: 'battery', name: '更换电池', type: 'part' },
    { key: 'screen', name: '更换屏幕', type: 'part' },
    { key: 'housing', name: '更换外壳', type: 'part' },
    { key: 'repair', name: '功能维修', type: 'labor' },
    { key: 'external', name: '外部维修', type: 'external' },
    { key: 'inspection', name: '复检', type: 'inspection' },
    { key: 'logistics', name: '整备物流', type: 'logistics' }
]
const route = useRoute()
const router = useRouter()
const search = reactive({ keyword: '', status: '', assigned_uid: '' as any, dateRange: [] as any, sort_field: '', sort_order: '' })
const table = reactive({ data: [] as any[], total: 0, page: 1, limit: 20, loading: false })
const assetOptions = ref<any[]>([])
const userOptions = ref<any[]>([])
const createDialog = reactive<any>({
    visible: false, loading: false,
    form: { asset_id: 0, assigned_uid: 0, planned_finish_at: '', preset_keys: [], custom_item: '', remark: '' }
})
const completeDialog = reactive<any>({
    visible: false, loading: false, order_id: 0, items: [], completion_remark: ''
})
const detail = reactive<any>({ visible: false, data: {} })
const completeTotal = computed(() =>
    completeDialog.items.reduce((sum: number, item: any) => sum + Number(item.amount || 0), 0)
)

const loadList = async () => {
    table.loading = true
    try {
        const params: any = {
            keyword: search.keyword, status: search.status, assigned_uid: search.assigned_uid,
            sort_field: search.sort_field, sort_order: search.sort_order,
            page: table.page, limit: table.limit,
        }
        if (Array.isArray(search.dateRange) && search.dateRange.length === 2) {
            params.start_time = search.dateRange[0]
            params.end_time = search.dateRange[1]
        }
        const res: any = await getErpRefurbishmentList(params)
        table.data = res.data?.data || []
        table.total = Number(res.data?.total || 0)
    } finally {
        table.loading = false
    }
}
const onSort = ({ prop, order }: { prop: string; order: string | null }) => {
    search.sort_field = order ? prop : ''
    search.sort_order = order === 'ascending' ? 'asc' : order === 'descending' ? 'desc' : ''
    table.page = 1
    loadList()
}
const loadOptions = async () => {
    const [assetRes, userRes]: any[] = await Promise.all([
        getErpAssetList({ inventory_status: 'in_stock', page: 1, limit: 100 }),
        getErpRefurbishmentUsers()
    ])
    assetOptions.value = assetRes.data?.data || []
    userOptions.value = userRes.data || []
}
const openCreate = async (assetId = 0) => {
    await loadOptions()
    createDialog.form = {
        asset_id: Number(assetId || 0),
        assigned_uid: Number(userOptions.value[0]?.uid || 0),
        planned_finish_at: '',
        preset_keys: ['clean', 'inspection'],
        custom_item: '',
        remark: ''
    }
    createDialog.visible = true
}
const submitCreate = async () => {
    if (!createDialog.form.asset_id) return ElMessage.warning('请选择设备')
    if (!createDialog.form.assigned_uid) return ElMessage.warning('请选择负责人')
    const items = createDialog.form.preset_keys.map((key: string) => {
        const item: any = presets.find(row => row.key === key)
        return { item_type: item.type, item_name: item.name, remark: '' }
    })
    if (createDialog.form.custom_item) {
        items.push({ item_type: 'other', item_name: createDialog.form.custom_item, remark: '' })
    }
    if (!items.length) return ElMessage.warning('请至少选择一个整备项目')
    createDialog.loading = true
    try {
        await createErpRefurbishment({
            ...createDialog.form,
            planned_finish_at: Number(createDialog.form.planned_finish_at || 0),
            items
        })
        ElMessage.success('整备工单已创建')
        createDialog.visible = false
        await loadList()
    } finally {
        createDialog.loading = false
    }
}
const openComplete = (row: any) => {
    completeDialog.order_id = Number(row.id)
    completeDialog.items = (row.items || []).map((item: any) => ({
        item_type: item.item_type, item_name: item.item_name, amount: Number(item.amount || 0), remark: item.remark || ''
    }))
    completeDialog.completion_remark = ''
    completeDialog.visible = true
}
const addCompleteItem = () => completeDialog.items.push({
    item_type: 'other', item_name: '', amount: 0, remark: ''
})
const submitComplete = async () => {
    const items = completeDialog.items.filter((item: any) => item.item_name)
    if (!items.length) return ElMessage.warning('请至少保留一个整备项目')
    completeDialog.loading = true
    try {
        await completeErpRefurbishment(completeDialog.order_id, {
            items, completion_remark: completeDialog.completion_remark
        })
        ElMessage.success('整备已完成，设备已进入待销售定价')
        completeDialog.visible = false
        await loadList()
    } finally {
        completeDialog.loading = false
    }
}
const cancelOrder = async (row: any) => {
    const { value } = await ElMessageBox.prompt('请输入取消原因', '取消整备', {
        confirmButtonText: '确认取消', cancelButtonText: '返回', inputPlaceholder: '例如：无需维修、工单重复'
    })
    await cancelErpRefurbishment(Number(row.id), { remark: value || '' })
    ElMessage.success('整备已取消，设备已恢复在库')
    await loadList()
}
const openDetail = async (row: any) => {
    const res: any = await getErpRefurbishmentInfo(Number(row.id))
    detail.data = res.data || {}
    detail.visible = true
}
const handleSearch = () => { table.page = 1; loadList() }
const resetSearch = () => { Object.assign(search, { keyword: '', status: '', assigned_uid: '', dateRange: [], sort_field: '', sort_order: '' }); handleSearch() }
const money = (value: any) => Number(value || 0).toFixed(2)
const formatTime = (value: any) => {
    if (!value) return '-'
    const date = new Date(Number(value) * 1000)
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString()
}
const assetLabel = (item: any) => `${item.model || '未命名设备'} / ${item.imei || item.asset_no}`
const userName = (item: any) => item.real_name || item.username || `员工${item.uid}`
const statusName = (status: string) => ({ processing: '整备中', completed: '已完成', cancelled: '已取消' }[status] || status)
const statusType = (status: string) => ({ processing: 'warning', completed: 'success', cancelled: 'info' }[status] || 'info')
const itemTypeName = (type: string) => ({
    part: '配件', labor: '人工', external: '外修', logistics: '物流', inspection: '检测', other: '其他'
}[type] || type)

onMounted(async () => {
    await loadList()
    const assetId = Number(route.query.asset_id || 0)
    if (assetId > 0) {
        await openCreate(assetId)
        await router.replace({ path: route.path, query: {} })
    }
})
</script>
