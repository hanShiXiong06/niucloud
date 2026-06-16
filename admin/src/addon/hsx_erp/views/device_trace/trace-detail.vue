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

                <!-- 时间线 -->
                <div class="mt-4 flex items-center justify-between">
                    <span class="font-medium">全链路时间线</span>
                    <el-checkbox v-model="showDetail" label="显示细节节点" />
                </div>
                <el-timeline class="mt-3">
                    <el-timeline-item v-for="(e, i) in visibleEvents" :key="i" :timestamp="formatTime(e.time)" placement="top"
                        :type="stageType(e.stage)" :hollow="!e.key">
                        <div class="flex items-center gap-2">
                            <el-tag size="small" :type="stageType(e.stage)" effect="plain">{{ e.stage }}</el-tag>
                            <span class="font-medium">{{ e.title }}</span>
                            <span v-if="Number(e.amount) > 0" class="text-sm text-gray-600">¥{{ Number(e.amount).toFixed(2) }}</span>
                        </div>
                        <div v-if="e.detail" class="mt-0.5 text-sm text-gray-600">{{ e.detail }}</div>
                        <div class="mt-0.5 text-xs text-gray-400">
                            <span v-if="e.operator_name">操作人：{{ e.operator_name }}</span>
                            <span v-if="e.no"> · 单号：{{ e.no }}</span>
                        </div>
                    </el-timeline-item>
                </el-timeline>
                <div v-if="!visibleEvents.length" class="py-6 text-center text-sm text-gray-400">暂无可展示的链路节点</div>
            </template>
        </div>

        <entity-drawer v-model="entityDrawer.visible" :entity-id="entityDrawer.id" />
    </el-drawer>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import EntityDrawer from '@/addon/hsx_erp/views/finance/entity-drawer.vue'
import { getDeviceTraceDetail } from '@/addon/hsx_erp/api/device_trace'

const props = defineProps<{ modelValue: boolean; assetId?: number; deviceId?: number }>()
const emit = defineEmits(['update:modelValue'])
const show = computed({ get: () => props.modelValue, set: (v: boolean) => emit('update:modelValue', v) })

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
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
const visibleEvents = computed(() => {
    const evs = data.value?.events || []
    return showDetail.value ? evs : evs.filter((e: any) => e.key)
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
