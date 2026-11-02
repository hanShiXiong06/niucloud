<template>
    <section>
        <LeadSectionHeader title="运营工作台" description="先看待办，再安排客资；让每条需求都有下一步。"><el-button type="primary" @click="$emit('navigate', 'leads')">录入 / 分配客资</el-button></LeadSectionHeader>
        <div class="lead-stats">
            <HsxStatCard title="待分配客资" :value="Number(data.leads_pending || 0)" unit="条" description="当前可见归属范围" tone="primary" />
            <HsxStatCard title="在售有货快照" :value="Number(data.inventory || 0)" unit="条" description="全站共享，仅计最新可售库存" tone="success" />
            <HsxStatCard title="商户反馈成单" :value="Number(data.reported_deals || 0)" unit="笔" description="客户直接与门店交易" tone="warning" />
            <HsxStatCard title="分享待到账" :value="money(data.commission_unpaid)" description="实际到账后再登记" tone="info" />
        </div>
        <div class="lead-overview-grid">
            <div class="lead-card lead-todo-card">
                <div class="lead-card-heading"><div><h3>需要你处理</h3><p class="lead-muted">按业务优先级处理，零待办不打扰。</p></div><el-tag v-if="activeTodos.length" type="warning" effect="light">{{ activeTodos.length }} 类待办</el-tag><el-tag v-else type="success" effect="light">当前已处理</el-tag></div>
                <div v-if="activeTodos.length" class="lead-mini-tasks">
                    <div v-for="item in activeTodos" :key="item.key" class="lead-mini-task">
                        <span class="lead-todo-count" :class="'is-' + item.tone">{{ item.value }}</span>
                        <div class="lead-todo-copy"><strong>{{ item.title }}</strong><p class="lead-muted">{{ item.description }}</p></div>
                        <el-button link type="primary" @click="$emit('navigate', item.tab, item.filter)">{{ item.action }}<el-icon><ArrowRight /></el-icon></el-button>
                    </div>
                </div>
                <div v-else class="lead-empty lead-todo-empty"><el-icon :size="30"><CircleCheck /></el-icon><h4>没有待处理事项</h4><p class="lead-muted">可录入新客资，或查看已有业务进度。</p></div>
                <el-button v-if="inactiveTodos.length" link class="lead-other-entries" :aria-expanded="showOther" @click="showOther = !showOther">{{ showOther ? '收起其他入口' : '查看其他业务入口' }}<el-icon><ArrowUp v-if="showOther" /><ArrowDown v-else /></el-icon></el-button>
                <div v-if="showOther" class="lead-other-grid"><el-button v-for="item in inactiveTodos" :key="item.key" @click="$emit('navigate', item.tab, item.filter)">{{ item.title }} · {{ item.value }}</el-button></div>
            </div>
            <aside class="lead-card lead-overview-aside">
                <div class="lead-card-heading"><div><h3>协同概况</h3><p class="lead-muted">{{ viewerScope.all_data === true ? '全站客资与分享收益口径' : '本人归属客资与分享收益口径' }}，不随列表筛选变化</p></div></div>
                <dl class="lead-overview-facts"><div><dt>共享合作商户</dt><dd>{{ data.merchants || 0 }} <small>家</small></dd></div><div><dt>跟进中工单</dt><dd>{{ data.tasks_following || 0 }} <small>条</small></dd></div><div><dt>分享已到账</dt><dd>{{ money(data.commission_received) }}</dd></div></dl>
                <div class="lead-flow-note"><strong>从客资到收益</strong><ol><li>录入客户需求</li><li>匹配并派给门店</li><li>门店反馈成单 / 未成单</li><li>自愿分享，核对实际到账</li></ol></div>
                <el-popover placement="bottom" trigger="click" :width="280"><template #reference><el-button link class="lead-boundary-help" :icon="InfoFilled">数据与使用说明</el-button></template><p class="lead-muted">商户使用现有会员身份，按绑定门店隔离。飞书资料人工收集，本插件不连接飞书。库存快照不等于锁货；客户直接向门店付款，平台只记录自愿分享的收益，不代收、不自动扣款。</p></el-popover>
            </aside>
        </div>
        <div class="lead-card lead-recent-card"><div class="lead-card-heading"><h3>最近工单</h3><el-button link type="primary" @click="$emit('navigate', 'leads', { mode: 'tasks' })">查看{{ viewerScope.all_data === true ? '全部工单' : '本人工单' }}<el-icon><ArrowRight /></el-icon></el-button></div><HsxTable :columns="columns" :data="data.recent_tasks || []" empty-text="暂无工单，录入客资并分配门店后将在这里展示"><template #customer="{ row }"><div class="lead-cell"><strong>{{ row.customer_name || row.lead?.customer_name || row.task_no || `工单 #${row.id}` }}</strong><small>{{ row.model || row.lead?.model || '' }}</small></div></template><template #owner="{ row }"><span>{{ row.owner_name || '未分配（历史资料）' }}</span></template><template #status="{ row }"><el-tag :type="tone(row.status)">{{ label(row.status) }}</el-tag></template><template #updated="{ row }"><LeadTimestamp :value="row.update_time || row.update_at || row.create_time" /></template></HsxTable></div>
    </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { ArrowRight, ArrowUp, ArrowDown, CircleCheck, InfoFilled } from '@element-plus/icons-vue'
import HsxStatCard from '@/addon/hsx_components/components/HsxStatCard/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import LeadSectionHeader from './LeadSectionHeader.vue'
import LeadTimestamp from './LeadTimestamp.vue'
import { label, money, tone, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ data: Row; viewerScope?: Row }>(), { viewerScope: () => ({ all_data: false, can_transfer: false }) })
defineEmits<{ (e: 'navigate', tab: string, filter?: Row): void }>()
const showOther = ref(false)
const todos = computed(() => [
    { key: 'overdue', title: '接单已超时', value: Number(props.data.tasks_overdue || 0), active: Number(props.data.tasks_overdue) > 0, description: '原接单已失效，请重新安排承接门店。', action: '处理超时', tab: 'leads', filter: { todo: 'overdue' }, tone: 'danger' },
    { key: 'verification', title: '历史申报待处理', value: Number(props.data.deals_pending || 0), active: Number(props.data.deals_pending) > 0, description: '仅处理原有申报，不影响新工单反馈结果。', action: '查看历史', tab: 'leads', filter: { todo: 'verification' }, tone: 'warning' },
    { key: 'pending', title: '客资等待安排', value: Number(props.data.leads_pending || 0), active: Number(props.data.leads_pending) > 0, description: '根据客户地区和需求选择门店。', action: '去分配', tab: 'leads', filter: { status: 'pending' }, tone: 'primary' },
    { key: 'partial', title: '历史需求未结束', value: Number(props.data.tasks_partial || 0), active: Number(props.data.tasks_partial) > 0, description: '继续跟进剩余台数，或明确结束剩余。', action: '跟进剩余', tab: 'leads', filter: { todo: 'partial' }, tone: 'primary' },
    { key: 'sync', title: '门店同步需处理', value: Number(props.data.sync_failed || 0), active: Number(props.data.sync_failed) > 0, description: '核对授权与同步失败原因。', action: '查看接入', tab: 'merchants', filter: { ready_state: 'attention' }, tone: 'warning' },
    { key: 'commission', title: '分享收益待到账', value: money(props.data.commission_unpaid), active: Number(props.data.commission_unpaid) > 0, description: '只核对商户自愿分享的收益，不代收客户货款。', action: '查看收益', tab: 'commissions', filter: { status: 'unpaid' }, tone: 'primary' }
])
const activeTodos = computed(() => todos.value.filter(item => item.active))
const inactiveTodos = computed(() => todos.value.filter(item => !item.active))
const columns = [{ prop: 'customer_name', label: '客户 / 需求', slot: 'customer', minWidth: 220 }, { prop: 'merchant_name', label: '承接门店', minWidth: 160 }, { prop: 'owner_name', label: '归属员工', slot: 'owner', minWidth: 130 }, { prop: 'status', label: '当前状态', slot: 'status', width: 135 }, { prop: 'update_time', label: '更新时间', slot: 'updated', width: 160 }]
</script>
