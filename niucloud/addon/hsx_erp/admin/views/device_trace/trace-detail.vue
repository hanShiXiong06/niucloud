<template>
    <el-drawer v-model="show" title="设备全链路" size="760px" @open="onOpen" @closed="data = null">
        <div v-loading="loading">
            <template v-if="data">
                <!-- 概览 -->
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                        <span class="font-medium">{{ data.overview.model }}</span>
                        <span class="text-sm text-gray-600">IMEI：{{ data.overview.imei || '-' }}</span>
                        <span class="text-sm text-gray-600">资产号：{{ data.overview.asset_no || '-' }}</span>
                        <el-tag size="small" effect="light">{{ statusText(data.overview.inventory_status) }}</el-tag>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                        <span>从谁收的：<b>{{ data.overview.customer_name || '-' }}</b><span v-if="data.overview.customer_phone" class="text-gray-400"> · {{ data.overview.customer_phone }}</span>
                            <span v-if="data.overview.customer_entity" class="text-gray-500">（主体：<b class="cursor-pointer text-[var(--el-color-primary)]" @click="openEntity(data.overview.customer_entity_id)">{{ data.overview.customer_entity }}</b>）</span>
                        </span>
                        <span>卖给了谁：<b>{{ data.overview.buyer_name || '-' }}</b><span v-if="data.overview.buyer_mobile" class="text-gray-400"> · {{ data.overview.buyer_mobile }}</span>
                            <span v-if="data.overview.buyer_entity" class="text-gray-500">（主体：<b class="cursor-pointer text-[var(--el-color-primary)]" @click="openEntity(data.overview.buyer_entity_id)">{{ data.overview.buyer_entity }}</b>）</span>
                        </span>
                        <span v-if="data.overview.order_no">回收单：{{ data.overview.order_no }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-4 gap-3 text-center">
                        <div><div class="text-xs text-gray-500">回收价</div><div class="mt-1 font-semibold">{{ money(data.overview.recycle_price) }}</div></div>
                        <div><div class="text-xs text-gray-500">整备费</div><div class="mt-1 font-semibold">{{ money(data.overview.refurbish_cost) }}</div></div>
                        <div><div class="text-xs text-gray-500">成本合计</div><div class="mt-1 font-semibold text-orange-600">{{ money(data.overview.current_cost) }}</div></div>
                        <div><div class="text-xs text-gray-500">售价</div><div class="mt-1 font-semibold text-blue-600">{{ data.overview.sale_price > 0 ? money(data.overview.sale_price) : '-' }}</div></div>
                        <div><div class="text-xs text-gray-500">毛利</div><div class="mt-1 font-semibold" :class="data.overview.profit >= 0 ? 'text-green-600' : 'text-red-600'">{{ money(data.overview.profit) }}</div></div>
                        <div><div class="text-xs text-gray-500">其它成本</div><div class="mt-1 font-semibold">{{ money(data.overview.other_cost) }}</div></div>
                        <div><div class="text-xs text-gray-500">已收</div><div class="mt-1 font-semibold text-green-600">{{ money(data.overview.received) }}</div></div>
                        <div><div class="text-xs text-gray-500">未收</div><div class="mt-1 font-semibold" :class="data.overview.unreceived > 0 ? 'text-orange-600' : 'text-gray-400'">{{ money(data.overview.unreceived) }}</div></div>
                    </div>
                </div>

                <!-- 周转 / 各段耗时 -->
                <div v-if="data.overview.turnaround" class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg bg-gray-50 px-4 py-2 text-sm">
                    <span class="font-medium text-gray-700">周转耗时：</span>
                    <span>回收→入库 <b>{{ tDays(data.overview.turnaround.recycle_to_instock) }}</b></span>
                    <span>{{ data.overview.turnaround.sold ? '在库→售出' : '在库至今' }}
                        <b :class="!data.overview.turnaround.sold && Number(data.overview.turnaround.instock_to_end) >= 30 ? 'text-red-600' : 'text-gray-800'">{{ tDays(data.overview.turnaround.instock_to_end) }}</b>
                    </span>
                    <span>{{ data.overview.turnaround.sold ? '总周转' : '累计' }} <b class="text-[var(--el-color-primary)]">{{ tDays(data.overview.turnaround.total) }}</b></span>
                </div>

                <!-- 关键财务节点摘要(日志多时一眼看清钱的走向) -->
                <div v-if="finEvents.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2">
                    <div class="mb-1 text-xs font-medium text-amber-700">关键财务节点</div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span v-if="finSummary.payable > 0" class="text-orange-600">应付 ¥{{ finSummary.payable.toFixed(2) }}</span>
                        <span v-if="finSummary.paid > 0" class="text-orange-600">已付 ¥{{ finSummary.paid.toFixed(2) }}</span>
                        <span v-if="finSummary.receivable > 0" class="text-green-600">应收 ¥{{ finSummary.receivable.toFixed(2) }}</span>
                        <span v-if="finSummary.received > 0" class="text-green-600">已收 ¥{{ finSummary.received.toFixed(2) }}</span>
                        <span v-if="finSummary.settle > 0" class="text-[var(--el-color-primary)]">结算 {{ finSummary.settle }} 笔</span>
                    </div>
                </div>

                <!-- 时间线 -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">全链路时间线</span>
                        <el-tag v-if="visibleEvents.length" size="small" type="info" effect="plain">{{ visibleEvents.length }} 条</el-tag>
                    </div>
                    <div class="flex items-center gap-3">
                        <el-checkbox v-model="finOnly" label="只看财务" />
                        <el-checkbox v-model="showDetail" :disabled="finOnly" label="显示细节节点" />
                    </div>
                </div>
                <!-- 固定高度内滚动，避免整页拉得过长 -->
                <div class="trace-timeline-scroll mt-3">
                    <el-timeline>
                        <el-timeline-item v-for="(e, i) in visibleEvents" :key="i" :timestamp="formatTime(e.time)" placement="top"
                            :type="stageType(e.stage)" :hollow="!e.key">
                            <div :class="e.stage === '财务' ? 'rounded-md border-l-[3px] border-amber-400 bg-amber-50 px-2.5 py-1.5' : ''">
                                <div class="flex items-center gap-2">
                                    <el-tag size="small" :type="stageType(e.stage)" :effect="e.stage === '财务' ? 'dark' : 'plain'">{{ e.stage }}</el-tag>
                                    <span class="font-medium" :class="e.stage === '财务' ? 'text-amber-700' : ''">{{ e.title }}</span>
                                    <el-tag v-if="Number(e.amount) > 0" size="small" :type="e.stage === '财务' ? 'warning' : 'info'" effect="light">¥{{ Number(e.amount).toFixed(2) }}</el-tag>
                                </div>
                                <clamp-text v-if="e.detail" :text="e.detail" :rows="3" class="mt-0.5 text-sm text-gray-600" />
                                <div class="mt-0.5 text-xs text-gray-400">
                                    <span v-if="e.operator_name">操作人：{{ e.operator_name }}</span>
                                    <span v-if="e.no"> · 单号：{{ e.no }}</span>
                                </div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                    <div v-if="!visibleEvents.length" class="py-6 text-center text-sm text-gray-400">暂无可展示的链路节点</div>
                </div>
            </template>
        </div>

        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" />
    </el-drawer>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'
import ClampText from '@/addon/hsx_erp/components/clamp-text.vue'
import { getDeviceTraceDetail } from '@/addon/hsx_erp/api/device_trace'

const props = defineProps<{ modelValue: boolean; assetId?: number; deviceId?: number }>()
const emit = defineEmits(['update:modelValue'])
const show = computed({ get: () => props.modelValue, set: (v: boolean) => emit('update:modelValue', v) })

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const tDays = (v: any) => (v === null || v === undefined || v === '') ? '-' : `${v} 天`
const formatTime = (t: any) => {
    const n = Number(t || 0)
    // 0 或明显非法的早期时间(2000年前)都当空，避免显示 1970-01-01
    if (!n || n < 946684800) return '-'
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
const statusText = (s: string) => ({
    in_stock: '在库', refurbishing: '整备中', pending_pricing: '待定价', available_for_sale: '可售',
    locked: '已售/下架', outbound: '已售/下架', pending_in: '待入库'
}[s] || s || '-')
const stageType = (s: string) => (s === '回收' ? 'success' : s === '中台' ? 'primary' : 'warning')

const loading = ref(false)
const data = ref<any>(null)
const showDetail = ref(true)
const finOnly = ref(false)
const visibleEvents = computed(() => {
    const evs = data.value?.events || []
    if (finOnly.value) return evs.filter((e: any) => e.stage === '财务')
    return showDetail.value ? evs : evs.filter((e: any) => e.key)
})
// 财务节点 + 摘要(应付/已付/应收/已收/结算)
const finEvents = computed(() => (data.value?.events || []).filter((e: any) => e.stage === '财务'))
const finSummary = computed(() => {
    const out = { payable: 0, paid: 0, receivable: 0, received: 0, settle: 0 }
    for (const e of finEvents.value) {
        const t = String(e.title || '')
        const a = Number(e.amount || 0)
        if (t.includes('应付')) out.payable += a
        else if (t.includes('付款')) out.paid += a
        else if (t.includes('应收')) out.receivable += a
        else if (t.includes('收款')) out.received += a
        else if (t.includes('结算')) out.settle += 1
    }
    return out
})
async function onOpen() {
    loading.value = true
    data.value = null
    try {
        const res: any = await getDeviceTraceDetail({ asset_id: props.assetId || 0, device_id: props.deviceId || 0 })
        data.value = res.data || null
    } finally {
        loading.value = false
    }
}

const entityDrawer = reactive<any>({ visible: false, id: 0 })
function openEntity(id: number) {
    if (!id) return
    entityDrawer.id = id
    entityDrawer.visible = true
}
</script>

<style lang="scss" scoped>
/* 时间线固定高度内滚动，长链路不再撑长整页 */
.trace-timeline-scroll {
    max-height: 420px;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-right: 6px;

    &::-webkit-scrollbar { width: 5px; }
    &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    &::-webkit-scrollbar-track { background: transparent; }
}
</style>
