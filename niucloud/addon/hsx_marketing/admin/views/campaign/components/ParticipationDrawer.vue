<template>
    <HsxDrawer v-model="visible" title="任务详情与参与记录" :subtitle="campaign?.title || '营销任务'" size="xl">
        <div class="participation">
            <template v-if="!selectedClaimId">
                <div v-if="campaign" class="task-summary">
                    <strong>{{ campaign.title }}</strong>
                    <span>当前目标 {{ number(campaign.target_value) }} {{ campaign.target_unit }}</span>
                    <span>{{ campaign.participation_mode === 'auto' ? '自动参与' : '客户领取任务' }} · {{ campaign.grant_mode === 'auto' ? '自动发奖' : '客户领取奖励' }}</span>
                    <span>{{ date(campaign.start_at) }} 至 {{ date(campaign.end_at) }}</span>
                </div>
                <el-collapse v-if="campaign?.subtitle || campaign?.description" class="rule-description"><el-collapse-item title="活动说明（当前配置）" name="description"><div>{{ campaign.subtitle }}</div><div class="description-text">{{ campaign.description }}</div><div class="muted">每期目标与进度请以下方参与记录为准，修改活动配置不会在这里重算历史记录。</div></el-collapse-item></el-collapse>
                <HsxSearchPanel title="筛选参与记录" :summary="filterSummary">
                    <template #default="{ labelPosition }">
                        <el-form inline :label-position="labelPosition" @submit.prevent="search">
                            <el-form-item label="参与人"><el-input v-model="query.keyword" clearable placeholder="姓名 / 手机 / 会员ID / 参与单号" class="participant-input" @keyup.enter="search" /></el-form-item>
                            <el-form-item label="任务状态"><el-select v-model="query.status" clearable placeholder="全部状态" class="state-input"><el-option v-for="(label, value) in claimStatuses" :key="value" :label="label" :value="value" /></el-select></el-form-item>
                            <el-form-item label="奖励结果"><el-select v-model="query.reward_status" clearable placeholder="全部结果" class="state-input"><el-option label="需关注（含撤回异常）" value="attention" /><el-option label="尚未生成奖励" value="none" /><el-option v-for="(label, value) in rewardStatuses" :key="value" :label="label" :value="value" /></el-select></el-form-item>
                            <el-form-item label="时间维度"><el-select v-model="query.time_type" class="state-input"><el-option label="参与时间" value="create_at" /><el-option label="达标时间" value="completed_at" /><el-option label="奖励发放时间" value="granted_at" /></el-select></el-form-item>
                            <el-form-item label="日期范围"><el-date-picker v-model="query.dates" type="daterange" value-format="YYYY-MM-DD" start-placeholder="开始日期" end-placeholder="结束日期" class="date-input" /></el-form-item>
                        </el-form>
                    </template>
                    <template #actions><span class="muted filter-note">北京时间；奖励条件命中任一笔奖励即可</span><el-button @click="reset">重置</el-button><el-button type="primary" :loading="loading" @click="search">查询</el-button></template>
                </HsxSearchPanel>
                <HsxNotice v-if="error" type="error" :title="error" :closable="false"><template #actions><el-button link type="primary" @click="load">重新加载</el-button></template></HsxNotice>
                <div v-if="!error" class="metrics" aria-label="当前筛选统计">
                    <div><span>参与记录</span><strong>{{ summary.record_count ?? '—' }}</strong></div>
                    <div><span>参与人数（去重）</span><strong>{{ summary.member_count ?? '—' }}</strong></div>
                    <div><span>当前已达标记录</span><strong>{{ summary.reached_count ?? '—' }}</strong></div>
                    <div><span>奖励需关注记录</span><strong>{{ summary.attention_count ?? '—' }}</strong></div>
                </div>
                <p v-if="!error" class="muted">统计按当前筛选，不只本页。同一会员每个周期一条记录；达标不等于奖励已到账。</p>
                <el-table v-if="!error" :data="rows" v-loading="loading" row-key="id" :empty-text="loading ? '正在加载参与记录…' : '暂无匹配的参与记录，可调整筛选条件'" class="records-table">
                    <el-table-column label="参与人" min-width="155"><template #default="{ row }"><strong>{{ row.member_name || '未记录姓名' }}</strong><div class="muted">{{ row.member_mobile || '未记录手机号' }}</div></template></el-table-column>
                    <el-table-column label="参与周期 / 时间" min-width="220"><template #default="{ row }"><div>{{ day(row.cycle_start_at) }} ~ {{ day(row.cycle_end_at) }}</div><div class="muted">参与 {{ date(row.create_at) }}</div></template></el-table-column>
                    <el-table-column label="进度 / 状态" min-width="180"><template #default="{ row }"><div>{{ number(row.progress_value) }} / {{ number(row.target_value) }} {{ campaign?.target_unit }}</div><el-tag size="small" :type="claimType(row.status)">{{ row.status_name }}</el-tag><div v-if="Number(row.completed_at)" class="muted">达标 {{ date(row.completed_at) }}</div></template></el-table-column>
                    <el-table-column label="奖励结果" min-width="165"><template #default="{ row }"><div v-if="!row.rewards.statuses.length" class="muted">尚未生成奖励</div><div v-for="reward in row.rewards.statuses" :key="reward.status">{{ reward.name }} {{ reward.count }} 笔</div><span v-if="row.rewards.attention_count" class="warning">{{ row.rewards.attention_count }} 笔需关注</span></template></el-table-column>
                    <el-table-column label="操作" width="105" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="openDetail(row.id)">查看明细</el-button></template></el-table-column>
                </el-table>
                <el-pagination v-if="total" v-model:current-page="page" :page-size="15" layout="total, prev, pager, next" :pager-count="5" :total="total" class="pager" @current-change="load" />
            </template>
            <template v-else>
                <div class="detail-toolbar"><el-button @click="back">返回参与记录</el-button><el-button :loading="detailLoading" @click="loadDetail">刷新明细</el-button></div>
                <HsxNotice v-if="detailError" type="error" :title="detailError" :closable="false" />
                <div v-loading="detailLoading" class="detail-content">
                    <template v-if="detail">
                        <div class="claim-card">
                            <div><strong>{{ detail.claim.member_name || '未记录姓名' }}</strong><span>{{ detail.claim.member_mobile || '未记录手机号' }}</span><el-tag :type="claimType(detail.claim.status)">{{ detail.claim.status_name }}</el-tag></div>
                            <div>任务进度 {{ number(detail.claim.progress_value) }} / {{ number(detail.claim.target_value) }} {{ detail.campaign.target_unit }}</div>
                            <div class="muted">周期 {{ date(detail.claim.cycle_start_at) }} 至 {{ date(detail.claim.cycle_end_at) }}</div>
                            <div class="muted">参与 {{ date(detail.claim.create_at) }} · 达标 {{ date(detail.claim.completed_at) }}</div>
                        </div>
                        <HsxNotice title="业务明细按实际计入凭据展示" :description="detail.trace_note" :reset-key="selectedClaimId" />
                        <h3>奖励记录 <span class="muted">{{ detail.rewards.total }} 笔</span></h3>
                        <el-table :data="detail.rewards.data" empty-text="本次任务尚未生成奖励记录">
                            <el-table-column label="奖励" min-width="170"><template #default="{ row }"><strong>{{ row.reward_name }}</strong><div class="muted">{{ rewardValue(row) }} · {{ row.grant_mode === 'auto' ? '自动发放' : '用户领取' }}</div></template></el-table-column>
                            <el-table-column label="结果" min-width="200"><template #default="{ row }"><el-tag :type="row.needs_attention ? 'danger' : row.status === 'success' ? 'success' : 'info'">{{ row.status_name }}</el-tag><div v-if="row.needs_attention" class="warning">{{ row.failure_reason || '处理存在异常，请在奖励台账核对' }}</div></template></el-table-column>
                            <el-table-column label="时间（北京时间）" min-width="210"><template #default="{ row }"><div>生成 {{ date(row.create_at) }}</div><div>发放 {{ date(row.granted_at) }}</div><div v-if="row.status === 'cancelled'" class="muted">撤回 / 取消更新 {{ date(row.update_at) }}</div><div v-if="Number(row.claim_expire_at)" class="muted">领取截止 {{ date(row.claim_expire_at) }}</div></template></el-table-column>
                        </el-table>
                        <el-pagination v-if="detail.rewards.total > 15" v-model:current-page="rewardPage" :page-size="15" :total="detail.rewards.total" layout="total, prev, pager, next" :pager-count="5" class="pager" @current-change="loadDetail" />
                        <h3>计入的业务 <span class="muted">{{ detail.facts.total }} 条（含冲减）</span></h3>
                        <el-table :data="detail.facts.data" empty-text="没有可确认归属的业务明细；旧记录可能未保存计入凭据">
                            <el-table-column label="来源业务 / 设备" min-width="230"><template #default="{ row }"><strong>{{ row.business_no || '未记录业务单号' }}</strong><div v-if="row.imei" class="muted">IMEI {{ row.imei }}</div><div class="muted">{{ row.order_source_name }}<template v-if="row.amount !== null"> · 成交 ¥{{ number(row.amount) }}</template></div></template></el-table-column>
                            <el-table-column label="计入 / 冲减" min-width="130"><template #default="{ row }"><el-tag :type="row.fact_type === 'reversal' ? 'warning' : 'success'">{{ row.fact_type === 'reversal' ? '冲减' : '计入' }} {{ number(row.quantity) }} {{ detail.campaign.target_unit }}</el-tag></template></el-table-column>
                            <el-table-column label="业务时间 / 处理时间" min-width="205"><template #default="{ row }"><div>{{ date(row.occurred_at) }}</div><div class="muted">处理 {{ date(row.processed_at) }}</div></template></el-table-column>
                        </el-table>
                        <el-pagination v-if="detail.facts.total > 15" v-model:current-page="factPage" :page-size="15" :total="detail.facts.total" layout="total, prev, pager, next" :pager-count="5" class="pager" @current-change="loadDetail" />
                        <el-collapse class="trace-collapse"><el-collapse-item title="核对用编号（平时无需关注）" name="trace"><div>会员 ID：{{ detail.claim.member_id }}</div><div>参与单号：{{ detail.claim.claim_no }}</div><div v-for="reward in detail.rewards.data" :key="reward.id">奖励单号：{{ reward.reward_no }}<template v-if="reward.failure_code"> · 异常代码 {{ reward.failure_code }}</template></div></el-collapse-item></el-collapse>
                    </template>
                </div>
            </template>
        </div>
        <template #footer><el-button @click="visible = false">关闭</el-button></template>
    </HsxDrawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
// 从组件库公开入口导入，自带主题；不能依赖先访问 ERP 等其他页面加载样式。
import { HsxDrawer, HsxSearchPanel, HsxNotice } from '@/addon/hsx_components/core'
import { getMarketingParticipant, getMarketingParticipants } from '../../../api'

const props = defineProps<{ modelValue: boolean; campaignId: number }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const defaults = () => ({ keyword: '', status: '', reward_status: '', time_type: 'create_at', dates: [] as string[] | null })
const query = reactive(defaults())
const activeFilters = ref<any>({}), campaign = ref<any>(null), summary = ref<any>({}), rows = ref<any[]>([])
const claimStatuses = ref<Record<string, string>>({}), rewardStatuses = ref<Record<string, string>>({})
const page = ref(1), total = ref(0), loading = ref(false), error = ref('')
const selectedClaimId = ref(0), factPage = ref(1), rewardPage = ref(1), detail = ref<any>(null), detailLoading = ref(false), detailError = ref('')
let listSequence = 0, detailSequence = 0
const timeNames: Record<string, string> = { create_at: '参与时间', completed_at: '达标时间', granted_at: '奖励发放时间' }
const filterSummary = computed(() => [activeFilters.value.keyword, claimStatuses.value[activeFilters.value.status], activeFilters.value.reward_status ? '已筛选奖励结果' : '', activeFilters.value.start_date ? `${timeNames[activeFilters.value.time_type]} ${activeFilters.value.start_date} ~ ${activeFilters.value.end_date}` : '不限日期'].filter(Boolean).join(' · '))
const number = (value: unknown) => Number.isFinite(Number(value)) ? Number(value).toLocaleString('zh-CN', { maximumFractionDigits: 2 }) : '—'
const date = (value: unknown) => Number(value) > 0 ? new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(Number(value) * 1000) : '—'
const day = (value: unknown) => date(value).split(' ')[0]
const claimType = (status: string): 'success' | 'info' | 'primary' => ['completed', 'rewarded'].includes(status) ? 'success' : status === 'running' ? 'primary' : 'info'
const rewardValue = (reward: any) => {
    const unit = ({ point: '积分', growth: '成长值', balance: '元' } as Record<string, string>)[reward.reward_type]
    return unit ? `${number(reward.reward_value)} ${unit} × ${reward.reward_quantity}` : `${reward.reward_quantity} 份`
}
const message = (e: any) => String(e?.msg || e?.message || '加载失败，请检查网络或活动详情权限后重试')
function search() {
    activeFilters.value = { keyword: query.keyword.trim(), status: query.status || '', reward_status: query.reward_status || '', time_type: query.time_type, start_date: query.dates?.[0] || '', end_date: query.dates?.[1] || '' }
    page.value = 1
    void load()
}
function reset() { Object.assign(query, defaults()); search() }
async function load() {
    const sequence = ++listSequence, id = props.campaignId
    loading.value = true; error.value = ''; rows.value = []; summary.value = {}; total.value = 0
    try {
        const result = (await getMarketingParticipants(id, { ...activeFilters.value, page: page.value, limit: 15 })).data
        if (sequence !== listSequence || !visible.value || id !== props.campaignId) return
        campaign.value = result.campaign; summary.value = result.summary; rows.value = result.records.data; total.value = Number(result.records.total)
        claimStatuses.value = result.claim_statuses; rewardStatuses.value = result.reward_statuses
    } catch (e) { if (sequence === listSequence) error.value = message(e) }
    finally { if (sequence === listSequence) loading.value = false }
}
function openDetail(id: number) { selectedClaimId.value = Number(id); factPage.value = 1; rewardPage.value = 1; void loadDetail() }
function back() { ++detailSequence; selectedClaimId.value = 0; detail.value = null; detailLoading.value = false; detailError.value = '' }
async function loadDetail() {
    const sequence = ++detailSequence, id = props.campaignId, claimId = selectedClaimId.value
    detailLoading.value = true; detailError.value = ''; detail.value = null
    try {
        const result = (await getMarketingParticipant(id, claimId, { fact_page: factPage.value, reward_page: rewardPage.value })).data
        if (sequence !== detailSequence || !visible.value || id !== props.campaignId || claimId !== selectedClaimId.value) return
        detail.value = result
    } catch (e) { if (sequence === detailSequence) detailError.value = message(e) }
    finally { if (sequence === detailSequence) detailLoading.value = false }
}
watch(() => [props.modelValue, props.campaignId], () => {
    ++listSequence; back(); loading.value = false
    if (!props.modelValue || !props.campaignId) return
    campaign.value = null; claimStatuses.value = {}; rewardStatuses.value = {}; reset()
}, { immediate: true })
</script>

<style scoped>
.participation { min-width: 0; font-size: 14px; line-height: 1.6; }
.task-summary { display: flex; flex-wrap: wrap; gap: 4px 18px; align-items: center; }
.task-summary strong { font-size: 16px; }
.task-summary span, .muted { color: var(--el-text-color-secondary); font-size: 12px; }
.participant-input { width: 270px; }
.state-input { width: 185px; }
.date-input { width: 270px; }
.filter-note { margin-right: auto; align-self: center; }
.metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border: 1px solid var(--el-border-color-light); border-radius: 6px; }
.metrics > div { display: flex; flex-direction: column; gap: 2px; padding: 12px 16px; }
.metrics span { color: var(--el-text-color-secondary); font-size: 12px; }
.metrics strong { font-size: 22px; }
.warning { color: var(--el-color-danger); overflow-wrap: anywhere; font-size: 12px; margin-top: 4px; }
.pager { justify-content: flex-end; margin-top: 16px; max-width: 100%; }
.detail-toolbar { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.detail-content { min-height: 160px; }
.claim-card { border: 1px solid var(--el-border-color-light); padding: 14px 16px; border-radius: 6px; margin-bottom: 12px; }
.claim-card > div { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; }
.claim-card strong { font-size: 17px; }
h3 { font-size: 15px; margin: 22px 0 10px; }
.trace-collapse { margin-top: 20px; overflow-wrap: anywhere; }
.rule-description { margin-top: 12px; }
.description-text { white-space: pre-wrap; overflow-wrap: anywhere; }
@media (max-width: 700px) { .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .metrics > div { padding: 10px; } .pager { justify-content: flex-start; } }
</style>
