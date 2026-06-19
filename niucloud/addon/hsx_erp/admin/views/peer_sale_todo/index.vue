<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-lg font-medium">卖同行待办</span>
                    <div class="mt-1 text-sm text-gray-500">挂单卖给同行的机器在这里回填出货价并收款；一处闭环，不用再去出库管理/财务中心来回找。</div>
                </div>
            </div>

            <!-- 状态切换 -->
            <el-tabs v-model="filter.state" class="mt-3" @tab-change="onSearch">
                <el-tab-pane label="全部" name="all" />
                <el-tab-pane label="需处理" name="todo" />
                <el-tab-pane label="待回填" name="pending_fill" />
                <el-tab-pane label="待收款" name="pending_collect" />
                <el-tab-pane label="已完成" name="done" />
            </el-tabs>

            <!-- 筛选 -->
            <el-form :inline="true" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input v-model="filter.keyword" placeholder="出库单号/同行/快递单号" clearable class="!w-[200px]" @keyup.enter="onSearch" />
                </el-form-item>
                <el-form-item label="IMEI">
                    <el-input v-model="filter.imei" placeholder="设备 IMEI" clearable class="!w-[160px]" @keyup.enter="onSearch" />
                </el-form-item>
                <el-form-item label="快递单号">
                    <el-input v-model="filter.express_no" placeholder="快递单号" clearable class="!w-[160px]" @keyup.enter="onSearch" />
                </el-form-item>
                <el-form-item label="操作人">
                    <el-input v-model="filter.operator" placeholder="经手人姓名" clearable class="!w-[140px]" @keyup.enter="onSearch" />
                </el-form-item>
                <el-form-item label="出库时间">
                    <el-date-picker v-model="filter.dateRange" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="开始" end-placeholder="结束" class="!w-[240px]" />
                </el-form-item>
                <el-form-item label="台数">
                    <div class="flex items-center gap-1">
                        <el-input-number v-model="filter.qty_min" :min="0" :controls="false" placeholder="最小" class="!w-[80px]" />
                        <span class="text-gray-400">-</span>
                        <el-input-number v-model="filter.qty_max" :min="0" :controls="false" placeholder="最大" class="!w-[80px]" />
                    </div>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :icon="Search" @click="onSearch">查询</el-button>
                    <el-button @click="onReset">重置</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="list" v-loading="loading" row-key="id" empty-text="没有待办的同行挂单 🎉">
                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="px-8 py-2">
                            <el-table :data="row.items" size="small" border>
                                <el-table-column prop="model" label="型号" min-width="160" />
                                <el-table-column prop="imei" label="IMEI" min-width="150" />
                                <el-table-column label="成本" width="110" align="right"><template #default="{ row: it }">¥{{ money(it.cost) }}</template></el-table-column>
                                <el-table-column label="出货价" width="120" align="right"><template #default="{ row: it }">{{ it.sale_price > 0 ? '¥' + money(it.sale_price) : '待填' }}</template></el-table-column>
                            </el-table>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="outbound_no" label="出库单号" min-width="160" show-overflow-tooltip />
                <el-table-column prop="counterparty_name" label="同行(买家)" min-width="120" show-overflow-tooltip />
                <el-table-column label="快递单号" min-width="130">
                    <template #default="{ row }">
                        <span v-if="row.express_no">{{ row.express_no }}</span>
                        <span v-else class="text-gray-300">—</span>
                    </template>
                </el-table-column>
                <el-table-column label="台数" width="70" align="center"><template #default="{ row }">{{ row.qty }}</template></el-table-column>
                <el-table-column label="状态" width="150" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.price_pending" type="warning" effect="light">待回填价</el-tag>
                        <template v-else>
                            <el-tag v-if="row.unreceived > 0" type="danger" effect="light">待收款 ¥{{ money(row.unreceived) }}</el-tag>
                            <el-tag v-else type="success" effect="light">已收齐</el-tag>
                        </template>
                    </template>
                </el-table-column>
                <el-table-column label="金额" width="120" align="right">
                    <template #default="{ row }">
                        <div>应收 ¥{{ money(row.receivable_total || row.total_amount) }}</div>
                        <div v-if="row.received > 0" class="text-xs text-gray-400">已收 ¥{{ money(row.received) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="操作人" width="100" align="center">
                    <template #default="{ row }"><span class="text-sm text-gray-600">{{ row.operator_name || '—' }}</span></template>
                </el-table-column>
                <el-table-column label="出库时间" width="150" align="center">
                    <template #default="{ row }"><span class="text-sm text-gray-500">{{ fmtTime(row.out_at) }}</span></template>
                </el-table-column>
                <el-table-column label="操作" width="120" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.price_pending || row.unreceived > 0" v-permission="'hsx_erp_outbound_fill_price'" type="primary" link @click="openFill(row)">回填/收款</el-button>
                        <span v-else class="text-xs text-gray-300">已完成</span>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-3 flex justify-end">
                <el-pagination v-model:current-page="page" :page-size="limit" :total="total" layout="total, prev, pager, next" @current-change="load" />
            </div>
        </el-card>

        <!-- 回填/收款 -->
        <el-dialog v-model="fill.visible" title="回填出货价 / 收款" width="640px" destroy-on-close>
            <div class="mb-3 text-sm text-gray-500">
                出库单 <b>{{ fill.row?.outbound_no }}</b> · 同行 <b>{{ fill.row?.counterparty_name }}</b>
                <span v-if="fill.row?.express_no"> · 快递 {{ fill.row.express_no }}</span>
            </div>
            <el-table :data="fill.items" size="small" border>
                <el-table-column prop="model" label="型号" min-width="150" />
                <el-table-column prop="imei" label="IMEI" min-width="140" show-overflow-tooltip />
                <el-table-column label="成本" width="100" align="right"><template #default="{ row: it }">¥{{ money(it.cost) }}</template></el-table-column>
                <el-table-column label="出货价" width="150">
                    <template #default="{ row: it }">
                        <el-input-number v-model="it.sale_price" :min="0" :precision="2" controls-position="right" size="small" class="!w-full" />
                    </template>
                </el-table-column>
            </el-table>

            <el-form class="mt-4" label-width="92px">
                <el-form-item label="本单合计">
                    <span class="font-medium text-[var(--el-color-primary)]">¥{{ money(fillTotal) }}</span>
                </el-form-item>
                <el-form-item label="是否已收款">
                    <el-switch v-model="fill.collect_now" active-text="已收款" inactive-text="先挂应收" inline-prompt />
                    <span class="ml-2 text-xs text-gray-400">
                        <template v-if="fill.collect_now">收款入账并把设备转「已售/下架」；款进所选户头</template>
                        <template v-else>仅生成应收，留给财务在财务中心收款</template>
                    </span>
                </el-form-item>
                <el-form-item v-if="fill.collect_now" label="收款户头" required>
                    <el-select v-model="fill.capital_account_id" filterable class="w-full" placeholder="本单款项收进哪个户头">
                        <el-option v-for="acc in accountOptions" :key="acc.id" :value="acc.id" :label="`${acc.account_name}（余额 ¥${money(acc.balance)}）`" />
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="fill.visible = false">取消</el-button>
                <el-button type="primary" :loading="fill.submitting" @click="submitFill">
                    {{ fill.collect_now ? '回填并收款' : '回填(挂应收)' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { getErpPeerSaleTodo, fillErpOutboundPrice } from '@/addon/hsx_erp/api/outbound'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'

const money = (v: any) => Number(v || 0).toFixed(2)
const fmtTime = (t: any) => {
    const n = Number(t || 0)
    if (!n) return '—'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const filter = reactive<any>({ state: 'all', keyword: '', imei: '', express_no: '', operator: '', dateRange: [], qty_min: undefined, qty_max: undefined })

const load = async () => {
    loading.value = true
    try {
        const [s, e] = filter.dateRange || []
        const res: any = await getErpPeerSaleTodo({
            state: filter.state === 'all' ? '' : filter.state,
            keyword: filter.keyword,
            imei: filter.imei,
            express_no: filter.express_no,
            operator: filter.operator,
            start_time: s ? Math.floor(new Date(s + ' 00:00:00').getTime() / 1000) : 0,
            end_time: e ? Math.floor(new Date(e + ' 23:59:59').getTime() / 1000) : 0,
            qty_min: filter.qty_min ?? '',
            qty_max: filter.qty_max ?? '',
            page: page.value,
            limit: limit.value,
        })
        const d = res?.data || {}
        list.value = d.data || []
        total.value = d.total || 0
    } finally {
        loading.value = false
    }
}
const onSearch = () => { page.value = 1; load() }
const onReset = () => {
    Object.assign(filter, { state: 'all', keyword: '', imei: '', express_no: '', operator: '', dateRange: [], qty_min: undefined, qty_max: undefined })
    page.value = 1
    load()
}

const accountOptions = ref<any[]>([])
const loadAccounts = async () => {
    try {
        const res: any = await getCapitalAccounts()
        accountOptions.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || [])
    } catch { accountOptions.value = [] }
}

const fill = reactive<any>({ visible: false, submitting: false, row: null, items: [], collect_now: true, capital_account_id: 0 })
const fillTotal = computed(() => (fill.items || []).reduce((s: number, it: any) => s + Number(it.sale_price || 0), 0))

const openFill = (row: any) => {
    fill.row = row
    fill.items = (row.items || []).map((it: any) => ({ ...it }))
    fill.collect_now = true
    fill.capital_account_id = accountOptions.value[0]?.id || 0
    fill.visible = true
    if (!accountOptions.value.length) loadAccounts()
}

const submitFill = async () => {
    if (fill.items.some((it: any) => Number(it.sale_price) <= 0)) {
        ElMessage.warning('请为每台填写出货价')
        return
    }
    if (fill.collect_now && !Number(fill.capital_account_id)) {
        ElMessage.warning('选择了「已收款」，请指定收款户头')
        return
    }
    if (fill.collect_now) {
        await ElMessageBox.confirm('确认收款入账？设备将转「已售/下架」。', '回填并收款', { type: 'warning' })
    }
    fill.submitting = true
    try {
        const items = fill.items.map((it: any) => ({ item_id: it.item_id, sale_price: Number(it.sale_price) }))
        await fillErpOutboundPrice(fill.row.id, items, {
            collect_now: fill.collect_now ? 1 : 0,
            capital_account_id: fill.capital_account_id || 0,
        })
        ElMessage.success(fill.collect_now ? '已回填并收款，设备已售下架' : '已回填，应收待财务收款')
        fill.visible = false
        await load()
    } finally {
        fill.submitting = false
    }
}

onMounted(() => { load(); loadAccounts() })
</script>
