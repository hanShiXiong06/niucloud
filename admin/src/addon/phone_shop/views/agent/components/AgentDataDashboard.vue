<template>
    <div v-loading="loading" class="agent-data-dashboard">
        <div class="flex flex-wrap items-start justify-between gap-[12px]">
            <div>
                <div class="text-[16px] font-semibold text-[#111827]">货盘数据</div>
                <div class="mt-[4px] text-[12px] text-[#64748b]">数字直接来自当前数据库，不依赖上一次同步提示</div>
            </div>
            <el-button :loading="loading" @click="load">刷新数据</el-button>
        </div>

        <div class="mt-[14px] grid grid-cols-2 gap-[10px] xl:grid-cols-4">
            <div class="metric primary">
                <span>小程序当前可展示</span>
                <strong>{{ frontendSellable }}</strong>
                <small>自营可售 + 代理可售</small>
            </div>
            <div class="metric">
                <span>我的自营商品</span>
                <strong>{{ data.self_total }}</strong>
                <small>其中可售 {{ data.self_sellable }}</small>
            </div>
            <div class="metric">
                <span>当前主站代理货盘</span>
                <strong>{{ data.proxy_total }}</strong>
                <small>其中可售 {{ data.proxy_sellable }}</small>
            </div>
            <div class="metric" :class="Number(data.proxy_unavailable) > 0 ? 'danger' : 'success'">
                <span>代理不可售</span>
                <strong>{{ data.proxy_unavailable }}</strong>
                <small>{{ Number(data.proxy_unavailable) ? '请查看下方具体原因' : '当前代理货盘状态正常' }}</small>
            </div>
            <div class="metric subtle">
                <span>主站全部商品</span>
                <strong>{{ data.master_total }}</strong>
                <small>当前可售 {{ data.master_sellable }}</small>
            </div>
            <div class="metric subtle" :class="Number(data.missing_count) > 0 ? 'warning' : ''">
                <span>主站可售但子站缺失</span>
                <strong>{{ data.missing_count }}</strong>
                <small>应通过全量校准补齐</small>
            </div>
            <div class="metric subtle" :class="Number(data.mapping_pending) > 0 ? 'warning' : ''">
                <span>分类待处理</span>
                <strong>{{ data.mapping_pending }}</strong>
                <small>未映射分类不会强行上架</small>
            </div>
            <div class="metric subtle">
                <span>历史代理记录</span>
                <strong>{{ Number(data.proxy_history_total || 0) }}</strong>
                <small>已售/下架记录，仅保留历史关联</small>
            </div>
        </div>

        <div v-if="lastRun" class="mt-[14px] rounded-[10px] border border-[#e5e7eb] bg-white p-[14px]">
            <div class="flex flex-wrap items-center justify-between gap-[10px]">
                <div class="flex items-center gap-[8px]">
                    <span class="font-medium text-[#1f2937]">最近同步 #{{ lastRun.run_id }}</span>
                    <el-tag :type="runMeta.type" effect="light">{{ runMeta.label }}</el-tag>
                    <span class="text-[12px] text-[#94a3b8]">{{ triggerName(lastRun.trigger_type) }}</span>
                </div>
                <span class="text-[12px] text-[#94a3b8]">{{ timeText(lastRun.update_time) }}</span>
            </div>
            <div class="mt-[12px] grid grid-cols-3 gap-[10px] text-center">
                <div class="run-count"><span>已扫描</span><strong>{{ lastRun.scanned_count || 0 }}</strong></div>
                <div class="run-count success"><span>成功</span><strong>{{ lastRun.success_count || 0 }}</strong></div>
                <div class="run-count danger"><span>失败</span><strong>{{ lastRun.failed_count || 0 }}</strong></div>
            </div>
            <el-progress v-if="isRunning && Number(data.master_sellable || 0) > 0" class="mt-[12px]" :percentage="syncPercent" :stroke-width="9" />
            <div v-if="isRunning" class="mt-[12px] flex items-center gap-[8px] text-[12px] text-[#2563eb]">
                <span class="h-[7px] w-[7px] animate-pulse rounded-full bg-[#2563eb]"></span>
                {{ lastRun.trigger_type === 'manual' ? '浏览器正在分批校准，数据会自动刷新' : '后台任务正在处理，数据会自动刷新' }}
            </div>
            <div v-if="lastRun.error_message" class="mt-[10px] rounded-[8px] bg-[#fef2f2] px-[12px] py-[9px] text-[12px] text-[#b91c1c]">
                {{ lastRun.error_message }}
            </div>
            <div v-if="failureEntries.length" class="mt-[10px] flex flex-wrap gap-[6px]">
                <el-tag v-for="item in failureEntries" :key="item[0]" type="danger" effect="plain">
                    {{ failureName(item[0]) }} {{ item[1] }} 台
                </el-tag>
            </div>
        </div>

        <div class="mt-[14px] overflow-hidden rounded-[10px] border border-[#e5e7eb] bg-white">
            <div class="border-b border-[#eef2f7] px-[14px] py-[12px]">
                <div class="font-medium text-[#1f2937]">为什么不能上架？</div>
                <div class="mt-[3px] text-[12px] text-[#94a3b8]">只展示当前真实存在的问题，数量为 0 的原因自动隐藏</div>
            </div>
            <el-table :data="data.issues || []" size="large">
                <template #empty><span class="text-[#16a34a]">没有发现代理商品上架问题</span></template>
                <el-table-column label="原因" min-width="130"><template #default="{ row }"><span class="font-medium">{{ row.name }}</span></template></el-table-column>
                <el-table-column label="数量" width="110"><template #default="{ row }"><span class="font-semibold text-[#ef4444]">{{ row.count }} {{ row.unit || '台' }}</span></template></el-table-column>
                <el-table-column prop="message" label="系统判断" min-width="260" />
                <el-table-column label="怎么处理" min-width="210">
                    <template #default="{ row }">
                        <el-button v-if="row.code === 'category_mapping'" type="primary" link @click="$emit('open-category')">进入分类映射</el-button>
                        <span v-else class="text-[13px] text-[#475569]">{{ row.action }}</span>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <div v-if="errorSamples.length" class="mt-[14px] overflow-hidden rounded-[10px] border border-[#fecaca] bg-[#fffafa]">
            <div class="border-b border-[#fee2e2] px-[14px] py-[11px] font-medium text-[#991b1b]">最近失败商品样例</div>
            <el-table :data="errorSamples" size="small">
                <el-table-column prop="goods_id" label="主站商品ID" width="120" />
                <el-table-column label="类型" width="130"><template #default="{ row }">{{ failureName(row.reason_code) }}</template></el-table-column>
                <el-table-column prop="message" label="失败原因" min-width="300" />
            </el-table>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { getAgentDashboard } from '@/addon/phone_shop/api/agent'

const props = defineProps<{ agentSiteId?: number }>()
defineEmits(['open-category'])
const loading = ref(false)
const data = ref<any>({ issues: [] })
let timer: any = null
const lastRun = computed(() => data.value.last_run || null)
const isRunning = computed(() => ['queued', 'running'].includes(String(lastRun.value?.status || '')))
const frontendSellable = computed(() => Number(data.value.self_sellable || 0) + Number(data.value.proxy_sellable || 0))
const syncPercent = computed(() => {
    const total = Number(data.value.master_sellable || 0)
    return total > 0 ? Math.min(100, Math.round(Number(lastRun.value?.scanned_count || 0) * 100 / total)) : 0
})
const failureEntries = computed(() => Object.entries(lastRun.value?.failure_summary || {}))
const errorSamples = computed(() => lastRun.value?.error_samples || [])
const runMeta = computed(() => ({
    queued: { label: '等待队列', type: 'info' }, running: { label: '同步中', type: 'warning' },
    success: { label: '全部成功', type: 'success' }, partial: { label: '部分失败', type: 'danger' },
    failed: { label: '执行失败', type: 'danger' }
} as any)[String(lastRun.value?.status || '')] || { label: '未知', type: 'info' })
const triggerName = (v: string) => ({ manual: '手动全量校准', relation: '站点关系变化', category: '分类映射变化', auto: '自动同步' } as any)[v] || v || '-'
const failureName = (v: string) => ({ category_mapping: '分类未映射', schema: '表结构缺失', sku: 'SKU资料缺失', duplicate: '数据重复', relation: '关系异常', other: '其他异常' } as any)[v] || v
const timeText = (value: any) => {
    const n = Number(value || 0); if (!n) return '-'
    return new Date(n * 1000).toLocaleString('zh-CN', { hour12: false })
}
const schedule = () => {
    if (timer) clearTimeout(timer)
    if (isRunning.value) timer = setTimeout(() => load(false), 2000)
}
const load = async (showLoading = true) => {
    if (showLoading) loading.value = true
    try {
        const res: any = await getAgentDashboard({ agent_site_id: Number(props.agentSiteId || 0) })
        data.value = res.data || { issues: [] }
    } finally {
        loading.value = false
        schedule()
    }
}
onBeforeUnmount(() => { if (timer) clearTimeout(timer) })
defineExpose({ load })
</script>

<style lang="scss" scoped>
.metric { @apply min-h-[108px] rounded-[10px] border border-[#e5e7eb] bg-white px-[14px] py-[12px];
    span { @apply block text-[12px] text-[#64748b]; } strong { @apply mt-[7px] block text-[28px] font-semibold leading-none text-[#111827]; }
    small { @apply mt-[8px] block text-[11px] text-[#94a3b8]; }
    &.primary { @apply border-[#bfdbfe] bg-[#eff6ff]; strong { @apply text-[#2563eb]; } }
    &.success strong { @apply text-[#16a34a]; } &.warning strong { @apply text-[#d97706]; }
    &.danger { @apply border-[#fecaca] bg-[#fef2f2]; strong { @apply text-[#dc2626]; } }
    &.subtle { @apply bg-[#fafafa]; }
}
.run-count { @apply rounded-[8px] bg-[#f8fafc] px-[10px] py-[9px]; span { @apply text-[11px] text-[#94a3b8]; } strong { @apply ml-[6px] text-[16px] text-[#334155]; }
    &.success strong { @apply text-[#16a34a]; } &.danger strong { @apply text-[#dc2626]; }
}
</style>
