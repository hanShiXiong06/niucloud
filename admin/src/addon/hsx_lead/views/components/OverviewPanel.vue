<template>
    <section>
        <div class="lead-page-title"><div><h2>运营工作台</h2><p>先处理客资分配与成交核验，让每条需求都有明确的下一步。</p></div><el-button type="primary" @click="$emit('navigate', 'leads')">录入 / 分配客资</el-button></div>
        <div class="lead-stats"><HsxStatCard title="待分配客资" :value="Number(data.leads_pending || 0)" unit="条" description="人工录入，地区与库存辅助匹配" tone="primary" /><HsxStatCard title="库存快照" :value="Number(data.inventory || 0)" unit="条" description="仅供参考，实际可售待门店确认" tone="success" /><HsxStatCard title="成交待核验" :value="Number(data.deals_pending || 0)" unit="笔" description="核验通过后生成应收佣金" tone="warning" /><HsxStatCard title="待收佣金" :value="money(data.commission_unpaid)" description="固定金额手填，不自动收费" tone="info" /></div>
        <div class="lead-grid-2">
            <div class="lead-card"><h3>需要你处理</h3><div class="lead-mini-tasks">
                <div class="lead-mini-task"><div><strong>{{ data.tasks_overdue || 0 }} 条接单已超时</strong><p class="lead-muted">直接定位过期工单，重新安排承接门店。</p></div><el-button @click="$emit('navigate', 'leads', { todo: 'overdue' })">处理超时</el-button></div><div class="lead-mini-task"><div><strong>{{ data.tasks_partial || 0 }} 条需求部分成交</strong><p class="lead-muted">继续跟进剩余数量，或明确结束剩余。</p></div><el-button @click="$emit('navigate', 'leads', { todo: 'partial' })">跟进剩余</el-button></div><div class="lead-mini-task"><div><strong>{{ data.leads_pending || 0 }} 条客资等待安排</strong><p class="lead-muted">先看服务地区和库存，再确认派单。</p></div><el-button @click="$emit('navigate', 'leads')">去分配</el-button></div>
                <div class="lead-mini-task"><div><strong>{{ data.deals_pending || 0 }} 笔成交等待核验</strong><p class="lead-muted">核对成交金额、单号及凭证。</p></div><el-button @click="$emit('navigate', 'leads', { todo: 'verification' })">核验成交</el-button></div>
                <div class="lead-mini-task"><div><strong>{{ data.sync_failed || 0 }} 家门店同步需处理</strong><p class="lead-muted">检查授权及同步原因，失败不会冒充空库存。</p></div><el-button @click="$emit('navigate', 'merchants', { ready_state: 'attention' })">查看接入</el-button></div>
                <div class="lead-mini-task"><div><strong>{{ money(data.commission_unpaid) }} 等待登记收款</strong><p class="lead-muted">确认实际到账后再登记，不发起真实扣款。</p></div><el-button @click="$emit('navigate', 'commissions')">查看佣金</el-button></div>
            </div></div>
            <div class="lead-card"><h3>协同概况</h3><el-descriptions :column="1" border><el-descriptions-item label="合作商户">{{ data.merchants || 0 }} 家</el-descriptions-item><el-descriptions-item label="跟进中工单">{{ data.tasks_following || 0 }} 条</el-descriptions-item><el-descriptions-item label="已登记收佣">{{ money(data.commission_received) }}</el-descriptions-item><el-descriptions-item label="商户入口">现有会员身份登录 · 按绑定门店隔离</el-descriptions-item></el-descriptions><el-alert title="飞书仅用于线下资料收集。本插件不连接飞书，也不将快照视为锁货结果。" type="info" :closable="false" style="margin-top: 20px" /></div>
        </div>
        <div class="lead-card"><h3>最近工单</h3><HsxTable :columns="columns" :data="data.recent_tasks || []" empty-text="暂无工单，录入客资并分配门店后将在这里展示"><template #customer="{ row }"><div class="lead-cell"><strong>{{ row.customer_name || row.lead?.customer_name || row.task_no || `工单 #${row.id}` }}</strong><small>{{ row.model || row.lead?.model || '' }}</small></div></template><template #status="{ row }"><el-tag :type="tone(row.status)">{{ label(row.status) }}</el-tag></template><template #updated="{ row }">{{ time(row.update_time || row.update_at || row.create_time) }}</template></HsxTable></div>
    </section>
</template>
<script setup lang="ts">
import HsxStatCard from '@/addon/hsx_components/components/HsxStatCard/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { label, money, time, tone, type Row } from '../../composables/useLeadUi'
defineProps<{ data: Row }>()
defineEmits<{ (e: 'navigate', tab: string, filter?: Row): void }>()
const columns = [{ prop: 'customer_name', label: '客户 / 需求', slot: 'customer', minWidth: 220 }, { prop: 'merchant_name', label: '承接门店', minWidth: 180 }, { prop: 'status', label: '当前状态', slot: 'status', width: 135 }, { prop: 'update_time', label: '更新时间', slot: 'updated', minWidth: 180 }]
</script>
