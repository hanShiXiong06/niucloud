<template>
    <PremiumTheme class="overview-page">
        <el-card class="!border-none" shadow="never">
            <PageHeader title="第三方服务" description="每项能力独立配置、独立测试、独立查看运行记录。">
                <template #actions><el-button :loading="loading" @click="loadOverview">刷新状态</el-button></template>
            </PageHeader>

            <div class="summary-grid" v-loading="loading">
                <div v-for="item in summaryItems" :key="item.label" class="summary-card">
                    <div class="summary-label">{{ item.label }}</div>
                    <div class="summary-value" :class="item.className">{{ item.value }}</div>
                </div>
            </div>

            <div class="section-head">
                <div><h2>服务列表</h2><p>进入对应服务完成配置，页面不再混放无关参数。</p></div>
                <el-button @click="router.push('/third_party/api_log')">查看全部调用日志</el-button>
            </div>

            <div class="service-grid" v-loading="loading">
                <article v-for="item in services" :key="item.key" class="service-card" @click="openService(item)">
                    <div class="card-top">
                        <div class="service-icon" :class="`is-${item.key}`"><el-icon><component :is="item.icon" /></el-icon></div>
                        <el-tag :type="statusMeta(item.status).type">{{ statusMeta(item.status).label }}</el-tag>
                    </div>
                    <h3>{{ item.name }}</h3>
                    <p>{{ item.description }}</p>
                    <div class="provider-line"><span>当前服务商</span><strong>{{ item.provider_label }}</strong></div>
                    <div class="card-metrics">
                        <div><span>今日调用</span><strong>{{ item.today?.total_calls || 0 }}</strong></div>
                        <div><span>失败</span><strong :class="{ danger: Number(item.today?.failed_calls || 0) > 0 }">{{ item.today?.failed_calls || 0 }}</strong></div>
                        <div><span>耗时</span><strong>{{ item.today?.avg_duration || 0 }}ms</strong></div>
                    </div>
                    <div v-if="item.missing_fields?.length" class="warning">待补全：{{ item.missing_fields.join('、') }}</div>
                    <div class="card-footer">进入配置 <el-icon><ArrowRight /></el-icon></div>
                </article>
            </div>
        </el-card>
    </PremiumTheme>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Box, Connection, Location, Printer, Search } from '@element-plus/icons-vue'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import { apiThirdPartyConfigOverview } from '@/addon/hsx_recycle/api/third_party'

const router = useRouter()
const loading = ref(false)
const overview = ref<any>({ today: {}, capabilities: [] })
const metadata: Record<string, any> = {
    express_order: { icon: Box, description: '运费报价、快递下单、取消拦截、面单与资金查询。', route: '/third_party/express_order' },
    express_query: { icon: Connection, description: '查询快递轨迹并记录第三方调用结果。', route: '/third_party/express_query' },
    address_parse: { icon: Location, description: '从一段文本识别姓名、电话、省市区和详细地址。', route: '/third_party/address_parse' },
    device_query: { icon: Search, description: '管理 IMEI、保修、激活锁等设备查询渠道。', route: '/device_query/config' },
    printer: { icon: Printer, description: '配置云打印通道，并进入打印机和模板管理。', route: '/third_party/printer' }
}
const services = computed(() => (overview.value.capabilities || []).map((item: any) => ({ ...item, ...(metadata[item.key] || {}) })))
const summaryItems = computed(() => [
    { label: '今日总调用', value: overview.value.today?.total_calls || 0 },
    { label: '成功调用', value: overview.value.today?.success_calls || 0, className: 'success' },
    { label: '失败调用', value: overview.value.today?.failed_calls || 0, className: 'danger' },
    { label: '整体成功率', value: `${overview.value.today?.success_rate || 0}%` }
])
const statusMeta = (status: string) => ({ running: { label: '运行中', type: 'success' }, ready: { label: '配置就绪', type: 'success' }, error: { label: '最近失败', type: 'danger' }, incomplete: { label: '待配置', type: 'warning' }, disabled: { label: '已停用', type: 'info' } } as Record<string, any>)[status] || { label: '未知', type: 'info' }
const loadOverview = async () => { loading.value = true; try { const res = await apiThirdPartyConfigOverview(); overview.value = res.data || {} } finally { loading.value = false } }
const openService = (item: any) => item.route && router.push(item.route)
onMounted(loadOverview)
</script>

<style scoped lang="scss">
.overview-page { padding: 20px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 20px; }
.summary-card { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; }.summary-label { color: #64748b; font-size: 13px; }.summary-value { margin-top: 9px; color: #111827; font-size: 26px; font-weight: 700; }.summary-value.success { color: #16a34a; }.summary-value.danger { color: #dc2626; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; margin: 28px 0 14px; }.section-head h2 { margin: 0; color: #111827; font-size: 17px; }.section-head p { margin: 5px 0 0; color: #94a3b8; font-size: 12px; }
.service-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.service-card { position: relative; min-height: 260px; padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; cursor: pointer; transition: .2s; }.service-card:hover { transform: translateY(-2px); border-color: #93c5fd; box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }.card-top { display: flex; align-items: center; justify-content: space-between; }.service-icon { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; color: #2563eb; background: #eff6ff; font-size: 20px; }.service-card h3 { margin: 15px 0 6px; color: #111827; font-size: 16px; }.service-card > p { min-height: 40px; margin: 0; color: #64748b; font-size: 12px; line-height: 1.65; }.provider-line { display: flex; justify-content: space-between; margin-top: 14px; padding: 10px 0; border-top: 1px solid #f1f5f9; color: #94a3b8; font-size: 12px; }.provider-line strong { color: #475569; font-weight: 500; }.card-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }.card-metrics span { display: block; color: #94a3b8; font-size: 11px; }.card-metrics strong { display: block; margin-top: 4px; color: #334155; font-size: 14px; }.card-metrics strong.danger { color: #dc2626; }.warning { margin-top: 10px; color: #b45309; font-size: 11px; }.card-footer { position: absolute; right: 18px; bottom: 15px; display: flex; align-items: center; gap: 4px; color: var(--el-color-primary); font-size: 12px; }
@media (max-width: 1100px) { .service-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
