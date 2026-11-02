<template>
    <view class="merchant-lead-page" :style="themeColor()">
        <view v-if="entryError" class="error-card">{{ entryError }}<view class="text-link" @tap="entryError = ''">查看本店工单列表</view></view>
        <view v-if="!loggedIn" class="empty-card">
            <u-icon name="account" size="42" color="#8a9ab5" />
            <view class="empty-title">登录后查看我的工单</view>
            <view class="muted">使用已绑定门店的会员账号登录，无需另设商户密码。</view>
            <u-button type="primary" text="使用会员身份登录" :custom-style="loginButtonStyle" @click="redirectLogin" />
        </view>
        <template v-else>
            <view class="merchant-card">
                <view class="merchant-heading"><view><view class="eyebrow">商户工作台</view><view class="merchant-name">{{ merchant.name || '我的门店' }}</view><view class="muted">{{ merchant.region_text || regionText(merchant) || '仅展示当前会员绑定的门店' }}</view></view><view class="icon-button" aria-label="刷新本店数据" @tap="refresh"><u-icon name="reload" size="22" color="#78879c" /></view></view>
                <view class="todo-grid"><view @tap="filterTasks('assigned')"><view class="todo-number">{{ stats.assigned ?? '—' }}</view><view class="muted">待接单 <text class="todo-arrow">›</text></view></view><view @tap="filterTasks('following')"><view class="todo-number">{{ stats.following ?? '—' }}</view><view class="muted">待反馈结果 <text class="todo-arrow">›</text></view></view></view>
            </view>
            <view class="scope-note"><u-icon name="lock" color="#8793a4" size="14" /><text>仅本店数据</text><text class="text-link subscribe-entry" @tap="subscribeNotices">订阅工单通知</text></view>
            <view class="main-tabs"><view v-for="item in tabs" :key="item.key" :class="['main-tab', { active: tab === item.key }]" @tap="changeTab(item.key)">{{ item.label }}</view></view>
            <view class="search-card"><u-search v-model="query.keyword" :placeholder="tab === 'workorders' ? '搜索客户、需求或工单号' : tab === 'inventory' ? '搜索商品型号或规格' : '搜索关联工单号'" :show-action="true" action-text="查询" @search="search" @custom="search" @clear="search" /><picker v-if="tab === 'workorders'" :range="statusOptions" range-key="label" :value="statusIndex" @change="changeStatus"><view class="status-picker"><text>{{ statusOptions[statusIndex].label }}</text><u-icon name="arrow-down" size="12" color="#8a96a9" /></view></picker></view>
            <view v-if="error" class="error-card">{{ error }}<view class="text-link" @tap="refresh">重新加载</view></view>
            <view v-if="loading && !activeRows.length" class="loading-card"><u-loading-icon mode="circle" color="var(--primary-color, #4264ec)" /><text>正在读取本店数据…</text></view>
            <template v-else-if="!error">
                <template v-if="tab === 'workorders'">
                    <view v-for="task in activeRows" :key="task.id" class="record-card" @tap="openTask(task.id)">
                        <view class="record-head"><view class="record-title">{{ task.customer_name || task.lead?.customer_name || '客户需求' }}</view><text :class="['status-chip', 'status-' + task.status]">{{ taskLabel(task) }}</text></view>
                        <view class="need-title">{{ demandTitle(task) }}</view>
                        <view class="compact-line">{{ demandHint(task.lead || task) }}</view>
                        <view class="compact-line">{{ task.region_text || task.lead?.region_text || regionText(task.lead || task) || '地区未填写' }}</view>
                        <view class="record-foot"><text>{{ task.status === 'assigned' ? deadlineLabel(task.response_deadline, now) : dateTime(task.update_time || task.create_time) }}</text><text class="text-link">{{ task.status === 'assigned' ? '查看并接单' : task.status === 'following' ? '反馈结果' : '查看记录' }} ›</text></view>
                    </view>
                </template>
                <template v-else-if="tab === 'inventory'">
                    <view class="info-note">只显示来源确认在售且有货的设备。下架、无货和状态待核实的快照不展示；接单前仍需核对实物，不会自动锁货。</view>
                    <view v-for="item in activeRows" :key="item.id" class="record-card"><view class="record-head"><view class="record-title">{{ item.model || item.name || '未识别型号' }}</view><view class="inventory-statuses"><text class="status-chip" :class="item.is_sellable === true ? 'status-completed' : 'status-unpaid'">{{ item.sale_status_label || '状态待核实' }}</text><text v-if="item.is_stale" class="status-chip status-unpaid">快照已过期</text></view></view><view class="compact-line">{{ [item.memory, item.color, item.grade].filter(Boolean).join(' · ') || '规格未返回' }}</view><view class="record-line"><text>快照价格</text><text class="price">{{ item.price == null || item.price === '' ? '—' : money(item.price) }}</text></view><view class="record-line"><text>来源库存</text><text>{{ item.stock_status_label || '库存待核实' }}</text></view><view class="record-foot"><text>最近同步 {{ dateTime(item.last_sync_at) }}</text></view></view>
                </template>
                <template v-else>
                    <view class="info-note">只展示已经登记的收益分享，不会自动扣款。客户货款由客户直接支付给门店，不经过平台。</view>
                    <view v-for="item in activeRows" :key="item.id" class="record-card"><view class="record-head"><view class="record-title">{{ item.revenue_source === 'voluntary_share' ? '我分享的收益' : '历史佣金记录' }}</view><text :class="['status-chip', 'status-' + item.status]">{{ shareLabel(item.status) }}</text></view><view class="record-line"><text>分享金额</text><text class="price">{{ money(item.amount) }}</text></view><view class="compact-line">关联工单 {{ item.task_no || item.workorder_id }}</view><view class="compact-line">{{ item.status === 'unpaid' ? '已登记，等待平台确认到账；并未自动扣款。' : item.status === 'paid' ? '平台已确认这笔分享到账。' : '以平台登记的当前状态为准。' }}</view></view>
                </template>
                <view v-if="!activeRows.length && !loading" class="empty-card"><u-icon :name="tab === 'workorders' ? 'order' : tab === 'inventory' ? 'bag' : 'rmb-circle'" color="#b4bece" size="40" /><view class="empty-title">{{ tab === 'workorders' ? '暂无符合条件的工单' : tab === 'inventory' ? '暂无在售有货的设备' : '暂无收益分享记录' }}</view><view class="muted">{{ tab === 'workorders' ? '平台分配给本店的客户需求会显示在这里。' : tab === 'inventory' ? '可调整搜索词，或联系运营核对来源上架状态、库存与同步结果。' : '成单后可自愿分享，也可以暂不分享。' }}</view></view>
                <view v-if="activeRows.length" class="loadmore" @tap="loadMore"><u-loadmore :status="loading ? 'loading' : activeRows.length < totals[tab] ? 'loadmore' : 'nomore'" loadmore-text="点击加载更多" nomore-text="没有更多记录了" /></view>
            </template>
        </template>

        <u-popup :show="taskVisible" mode="bottom" :round="18" :safe-area-inset-bottom="false" :close-on-click-overlay="!saving" @close="closeTask">
            <view class="task-sheet">
                <view class="sheet-header"><view class="sheet-back" v-if="action" @tap="cancelAction"><u-icon name="arrow-left" size="18" color="#566579" /></view><view class="sheet-heading"><view class="sheet-title">{{ action ? actionTitles[action] : '工单详情' }}</view><view class="muted">{{ action ? '只需完成当前这一步' : taskLabel(detail) }}</view></view><view class="icon-button" @tap="closeTask"><u-icon name="close" size="20" color="#8995a9" /></view></view>
                <scroll-view scroll-y class="sheet-body" :scroll-top="sheetScrollTop">
                    <view v-if="detailLoading" class="loading-card"><u-loading-icon mode="circle" color="var(--primary-color, #4264ec)" /></view>
                    <view v-else-if="detailError" class="error-card">{{ detailError }}<view class="text-link" @tap="loadTask">重新读取</view></view>
                    <template v-else-if="detail.id">
                        <template v-if="!action">
                            <view class="detail-card customer-card"><view class="record-head"><view class="record-title">{{ lead.customer_name || '客户需求' }}</view><view v-if="lead.mobile" class="contact-button" @tap="callCustomer"><u-icon name="phone" size="17" color="var(--primary-color, #4264ec)" /><text>联系客户</text></view></view><view class="compact-line">{{ lead.region_text || regionText(lead) || '地区未填写' }}</view><view class="customer-mobile">{{ lead.mobile || '联系电话未提供' }}</view><view v-if="detail.next_action" class="next-step">{{ detail.next_action }}</view><view v-else-if="detail.status === 'assigned'" class="next-step">{{ deadlineLabel(detail.response_deadline, now) }}</view></view>
                            <view v-if="!isSimple" class="info-note">这是历史协作记录，仅保留查询。需要继续处理或核对时，请联系平台。</view>
                            <view class="detail-card requirements-wrap"><RequirementDetails v-if="taskRequirements(detail).length" :items="taskRequirements(detail)" :mode="detail.demand_mode || lead.demand_mode" /><template v-else><view class="section-title">客户购机需求</view><view class="need-title">{{ demandTitle(detail) }}</view><view class="record-line"><text>客户预算</text><text>{{ budget(lead) }}</text></view></template><view v-if="lead.remark" class="detail-note">{{ lead.remark }}</view></view>
                            <view v-if="isSimple && detail.outcome" class="detail-card"><view class="section-title">{{ detail.outcome === 'deal' ? '已反馈成单' : '已反馈未成单' }}</view><view class="muted">客户货款由客户与门店直接结算，平台不代收。</view><view v-if="detail.has_shared_revenue" class="record-line"><text>我分享的收益</text><text class="price">{{ money(detail.share_amount) }}</text></view><view v-if="detail.has_shared_revenue" class="muted">{{ detail.share_status_label || shareLabel(detail.share_status) }} · {{ detail.share_is_received ? '平台已确认到账' : '分享已登记，不代表平台已收到款项' }}</view><view v-else-if="detail.outcome === 'deal'" class="muted">暂未登记收益分享，可自愿选择，之后也可以分享。</view></view>
                            <view class="detail-card"><view class="fold-heading" @tap="historyExpanded = !historyExpanded"><text>编号与历史记录</text><u-icon :name="historyExpanded ? 'arrow-up' : 'arrow-down'" size="13" color="#8793a4" /></view><template v-if="historyExpanded"><view class="record-line"><text>工单编号</text><text>{{ detail.task_no || detail.id }}</text></view><view class="record-line"><text>分配时间</text><text>{{ dateTime(detail.create_time) }}</text></view><view v-if="detail.status === 'assigned'" class="record-line"><text>接单期限</text><text>{{ dateTime(detail.response_deadline) }}</text></view><view v-if="detail.completion_status" class="muted">{{ completionLabels[detail.completion_status] || detail.result_label || '历史状态已保留' }}</view><view v-if="!isSimple && detail.deal_no" class="record-line"><text>历史销售单号</text><text>{{ detail.deal_no }}</text></view><view v-if="!isSimple && detail.evidence" class="detail-note">{{ detail.evidence }}</view><view v-if="!detail.followups?.length" class="muted history-empty">暂无处理记录</view><view v-for="item in detail.followups || []" :key="item.id" class="follow-item"><view class="follow-content">{{ item.content }}</view><view class="compact-line">{{ dateTime(item.create_time || item.create_at) }} · {{ item.operator_name || '经办人' }}</view></view></template></view>
                        </template>
                        <view v-else class="action-body">
                            <template v-if="action === 'accept'"><view class="action-lead">确认接下这位客户的需求？</view><view class="muted">请先核对库存与服务地区。接单后由您联系客户，成交后反馈结果即可；不会自动锁货。</view><view class="action-customer">{{ lead.customer_name || '客户' }} · {{ demandTitle(detail) }}</view></template>
                            <template v-if="action === 'reject'"><view class="action-lead">暂不接这笔工单</view><view class="muted">工单将退回平台重新安排，不会记为您接单后的未成单结果。</view><text class="field-label">备注 <text class="optional">选填</text></text><u-textarea :disabled="saving" v-model="form.remark" placeholder="可说明暂时不便接单的原因" :maxlength="1000" /></template>
                            <template v-if="action === 'outcome'">
                                <view class="action-lead">这次客户需求，结果怎么样？</view>
                                <view class="outcome-options"><view v-for="option in outcomeOptions" :key="option.value" :class="['outcome-option', { selected: form.outcome === option.value }]" @tap="selectOutcome(option.value)"><view class="outcome-copy"><view class="option-title">{{ option.label }}</view><view class="muted">{{ option.hint }}</view></view><u-icon :name="form.outcome === option.value ? 'checkmark-circle-fill' : 'circle'" size="24" :color="form.outcome === option.value ? 'var(--primary-color, #4264ec)' : '#cbd2dc'" /></view></view>
                                <template v-if="form.outcome === 'deal'"><view class="share-choice"><view><view class="option-title">自愿分享收益</view><view class="muted">不是必填，也可成单后再分享</view></view><u-switch v-model="form.shareNow" :disabled="saving" size="22" active-color="var(--primary-color, #4264ec)" /></view><template v-if="form.shareNow"><text class="field-label">我分享的金额 <text class="optional">选填</text></text><u-input :disabled="saving" v-model="form.share_amount" type="digit" placeholder="可留空，最多两位小数" prefix-icon="rmb" maxlength="12" /><view class="field-help">填正数仅登记待确认到账，不会自动扣款；留空或填 0 均不建立账目。</view></template></template>
                                <text class="field-label">补充说明 <text class="optional">选填</text></text><u-textarea :disabled="saving" v-model="form.remark" placeholder="如客户已到店、暂时不购买等" :maxlength="1000" />
                            </template>
                            <template v-if="action === 'share'"><view class="action-lead">自愿分享这次成单的收益</view><view class="muted">填写您愿意分享的金额，仅作登记，不会自动扣款。实际转款后，由平台确认到账。</view><text class="field-label">我分享的金额</text><u-input :disabled="saving" v-model="form.share_amount" type="digit" placeholder="请输入大于 0 的金额" prefix-icon="rmb" maxlength="12" /><text class="field-label">备注 <text class="optional">选填</text></text><u-textarea :disabled="saving" v-model="form.remark" placeholder="可补充转款说明" :maxlength="1000" /><view class="field-help">一笔工单只登记一次收益分享，请核对金额后提交。</view></template>
                            <view v-if="actionError" class="inline-error">{{ actionError }}</view>
                        </view>
                    </template>
                </scroll-view>
                <view class="sheet-footer">
                    <template v-if="action"><view class="footer-secondary"><u-button text="返回" :disabled="saving" @click="cancelAction" /></view><view class="footer-primary"><u-button type="primary" :text="submitLabel" :loading="saving" loading-text="正在提交" :disabled="saving || (action === 'outcome' && !form.outcome)" @click="submitAction" /></view></template>
                    <template v-else-if="isSimple && detail.status === 'assigned' && !detailError"><view class="footer-secondary"><u-button text="暂不接单" :disabled="saving" @click="chooseAction('reject')" /></view><view class="footer-primary"><u-button type="primary" :text="isExpired(detail, now) ? '接单已超时' : '接下工单'" :disabled="saving || isExpired(detail, now)" @click="chooseAction('accept')" /></view></template>
                    <view v-else-if="canReportOutcome && !detailError" class="footer-primary"><u-button type="primary" text="反馈结果" :disabled="saving" @click="chooseAction('outcome')" /></view>
                    <template v-else-if="canShare && !detailError"><view class="footer-secondary"><u-button text="分享收益" @click="chooseAction('share')" /></view><view class="footer-primary"><u-button type="primary" text="完成，返回列表" @click="closeTask" /></view></template>
                    <view v-else class="footer-primary"><u-button text="关闭详情" :disabled="saving" @click="closeTask" /></view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onReachBottom, onShow, onUnload } from '@dcloudio/uni-app'
import { getToken } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import RequirementDetails from './components/RequirementDetails.vue'
import { completionLabels, deadlineLabel, isExpired, taskRequirements } from '../../utils/deal'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { getMerchantTask, getMerchantWorkbench, merchantTaskAction } from '../../api'
type Row = Record<string, any>
type Tab = 'workorders' | 'inventory' | 'commissions'
type Action = '' | 'accept' | 'reject' | 'outcome' | 'share'
type Outcome = '' | 'deal' | 'no_deal'
const pageUrl = '/addon/hsx_lead/pages/workbench/index'
const legacyProps = defineProps<{ initialTaskId?: number }>()
const pendingTaskId = ref(Number(legacyProps.initialTaskId || 0)), entryError = ref('')
const login = useLogin(), loggedIn = ref(false), merchant = ref<Row>({}), stats = ref<Row>({}), error = ref(''), loading = ref(false)
const loginButtonStyle = { marginTop: '24px', maxWidth: '260px' }
const tab = ref<Tab>('workorders'), tabs = [{ key: 'workorders' as Tab, label: '我的工单' }, { key: 'inventory' as Tab, label: '库存查询' }, { key: 'commissions' as Tab, label: '收益分享' }]
const lists = reactive<Record<Tab, Row[]>>({ workorders: [], inventory: [], commissions: [] }), totals = reactive<Record<Tab, number>>({ workorders: 0, inventory: 0, commissions: 0 })
const activeRows = computed(() => lists[tab.value]), query = reactive({ page: 1, limit: 20, keyword: '', status: '', inventory_scope: 'sellable' })
const labels: Record<string, string> = { assigned: '待接单', following: '待反馈结果', deal_pending: '历史成交待核', completed: '已成单', rejected: '未接单', reassigned: '已转派', closed: '已结束', cancelled: '已撤销' }
const statusOptions = [{ value: '', label: '全部状态' }, ...['assigned', 'following', 'completed', 'closed', 'rejected', 'cancelled', 'deal_pending'].map(value => ({ value, label: labels[value] }))]
const statusIndex = computed(() => Math.max(0, statusOptions.findIndex(item => item.value === query.status)))
const taskLabel = (task: Row) => task.result_label || labels[task.status] || '工单记录'
const shareLabels: Record<string, string> = { none: '未登记分享', unpaid: '待确认到账', paid: '已确认到账', void: '已作废', refund_pending: '待退回', refunded: '已退回' }
const shareLabel = (value: string) => shareLabels[value] || '待核对'
const money = (value: any) => value === null || value === undefined || value === '' || !Number.isFinite(Number(value)) ? '—' : '¥' + Number(value).toFixed(2)
const budget = (row: Row) => Number(row.budget_min) > 0 && Number(row.budget_max) > 0 ? money(row.budget_min) + ' – ' + money(row.budget_max) : Number(row.budget_max) > 0 ? '最高 ' + money(row.budget_max) : Number(row.budget_min) > 0 ? money(row.budget_min) + ' 起' : '预算未确定'
const demandHint = (row: Row) => row.requirements?.length ? (row.demand_mode === 'any' ? '备选 · ' : '') + '共 ' + row.requirements.length + ' 款设备 · 数量与预算' : budget(row)
const demandTitle = (task: Row) => { const data = task.lead || task; return [task.model || data.model, task.memory || data.memory].filter(Boolean).join(' · ') || taskRequirements(task).map((item: Row) => item.title).filter(Boolean).slice(0, 2).join(' / ') || '查看客户购机需求' }
const regionText = (value: Row) => [value.province_name, value.city_name, value.district_name].filter(Boolean).join(' / ')
const dateTime = (value: any) => { if (!value) return '尚未记录'; const date = new Date(/^\d+$/.test(String(value)) ? Number(value) * 1000 : value); if (Number.isNaN(date.getTime())) return '尚未记录'; return date.getFullYear() + '/' + String(date.getMonth() + 1).padStart(2, '0') + '/' + String(date.getDate()).padStart(2, '0') + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') }
const message = (cause: any, fallback: string) => String(cause?.msg || cause?.message || cause?.errMsg || fallback)
function parseTaskId(value: unknown): number {
    if ((typeof value !== 'string' && typeof value !== 'number') || !/^[1-9][0-9]{0,9}$/.test(String(value))) return 0
    const id = Number(value)
    return Number.isSafeInteger(id) && id <= 4294967295 ? id : 0
}
function redirectLogin() { const id = pendingTaskId.value || (taskVisible.value ? selectedId.value : 0); login.setLoginBack({ url: pageUrl, ...(id ? { param: { task_id: id } } : {}), mode: 'redirectTo' }) }
let sequence = 0, disposed = false, active = true, lastIdentity = ''
async function load(append = false) {
    if (!getToken()) { loggedIn.value = false; return false }
    const token = ++sequence, currentTab = tab.value, nextPage = append ? query.page + 1 : 1
    loading.value = true; error.value = ''
    try {
        const response: any = await getMerchantWorkbench({ ...query, page: nextPage, tab: currentTab })
        if (token !== sequence || disposed) return false
        const data = response.data || {}; merchant.value = data.merchant || {}; stats.value = data.stats || {}
        for (const key of ['workorders', 'inventory', 'commissions'] as Tab[]) {
            const source = data[key] || {}, rows = Array.isArray(source) ? source : source.data || []
            if (!append || key === currentTab) { lists[key] = append ? [...lists[key], ...rows.filter((row: Row) => !lists[key].some(item => item.id === row.id))] : rows; totals[key] = Number(source.total ?? rows.length) }
        }
        query.page = nextPage
        return true
    } catch (cause) { if (token === sequence && !disposed) error.value = message(cause, '读取失败，请检查会员是否已绑定门店并重试。'); return false }
    finally { if (token === sequence && !disposed) loading.value = false }
}
async function refresh() { if (!loading.value) await load(false) }
function search() { void load(false) }
function changeTab(value: Tab) { if (tab.value === value) return; tab.value = value; query.status = ''; query.keyword = ''; void load(false) }
function filterTasks(status: string) { tab.value = 'workorders'; query.status = status; query.keyword = ''; void load(false) }
function changeStatus(event: any) { query.status = statusOptions[Number(event.detail.value)]?.value || ''; void load(false) }
function loadMore() { if (!loading.value && activeRows.value.length < totals[tab.value]) void load(true) }
const taskVisible = ref(false), detailLoading = ref(false), detailError = ref(''), selectedId = ref(0), detail = ref<Row>({}), action = ref<Action>(''), saving = ref(false), actionError = ref(''), historyExpanded = ref(false), sheetScrollTop = ref(0)
const lead = computed(() => detail.value.lead || detail.value), isSimple = computed(() => detail.value.collaboration_mode === 'simple')
const canReportOutcome = computed(() => isSimple.value && detail.value.status === 'following' && detail.value.can_report_outcome === true)
const canShare = computed(() => isSimple.value && detail.value.status === 'completed' && detail.value.outcome === 'deal' && detail.value.can_share === true)
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
function startClock() { if (clock) clearInterval(clock); now.value = Date.now(); clock = setInterval(() => { now.value = Date.now() }, 30000) }
function stopClock() { if (clock) clearInterval(clock); clock = undefined }
function subscribeNotices() {
    // #ifdef MP-WEIXIN
    useSubscribeMessage().request('hsx_lead_task_assigned,hsx_lead_task_changed')
    // #endif
    // #ifndef MP-WEIXIN
    uni.showToast({ title: '请在微信小程序内点击订阅，结果以微信授权为准', icon: 'none' })
    // #endif
}
const actionTitles: Record<string, string> = { accept: '接下工单', reject: '暂不接单', outcome: '反馈结果', share: '分享收益' }
const outcomeOptions = [{ value: 'deal' as Outcome, label: '已成单', hint: '客户与门店已完成交易' }, { value: 'no_deal' as Outcome, label: '未成单', hint: '结束本次跟进，保留记录' }]
const form = reactive({ outcome: '' as Outcome, shareNow: false, share_amount: '', remark: '' })
const submitLabel = computed(() => action.value === 'outcome' ? form.outcome === 'no_deal' ? '确认未成单' : form.outcome === 'deal' ? '确认已成单' : '确认结果' : action.value === 'share' ? '确认登记分享' : actionTitles[action.value])
let detailSequence = 0
async function openTask(value: unknown) { const id = parseTaskId(value); if (!id) { uni.showToast({ title: '工单链接无效，请从本店列表查找', icon: 'none' }); return }; selectedId.value = id; taskVisible.value = true; detail.value = {}; action.value = ''; historyExpanded.value = false; await loadTask() }
async function loadTask() { if (!getToken()) { pendingTaskId.value = selectedId.value; loggedIn.value = false; redirectLogin(); return }; const token = ++detailSequence; detailLoading.value = true; detailError.value = ''; try { const response: any = await getMerchantTask(selectedId.value); if (token === detailSequence && taskVisible.value) detail.value = response.data || {} } catch (cause) { if (token === detailSequence) detailError.value = message(cause, '工单读取失败，可能已无权访问。') } finally { if (token === detailSequence) detailLoading.value = false } }
function closeTask() { if (saving.value) return; taskVisible.value = false; detailSequence++; detailLoading.value = false; action.value = ''; actionError.value = '' }
function actionAllowed(value: Action): boolean { return isSimple.value && !detailLoading.value && !detailError.value && (value === 'accept' ? detail.value.status === 'assigned' && !isExpired(detail.value, now.value) : value === 'reject' ? detail.value.status === 'assigned' : value === 'outcome' ? canReportOutcome.value : value === 'share' ? canShare.value : false) }
async function resetScroll() { sheetScrollTop.value = 1; await nextTick(); sheetScrollTop.value = 0 }
async function chooseAction(value: Action) { if (saving.value || !actionAllowed(value)) return; Object.assign(form, { outcome: '', shareNow: false, share_amount: '', remark: '' }); actionError.value = ''; action.value = value; await resetScroll() }
function cancelAction() { if (saving.value) return; action.value = ''; actionError.value = ''; void resetScroll() }
function selectOutcome(value: Outcome) { if (saving.value || !['deal', 'no_deal'].includes(value)) return; form.outcome = value; if (value !== 'deal') { form.shareNow = false; form.share_amount = '' }; actionError.value = '' }
function callCustomer() { if (lead.value.mobile) uni.makePhoneCall({ phoneNumber: String(lead.value.mobile), fail: () => uni.showToast({ title: '未能拨打，请手动联系客户', icon: 'none' }) }) }
function normalizedShare(value: unknown, required: boolean): { amount?: string, error?: string } {
    const text = String(value ?? '').trim()
    if (!text && !required) return {}
    if (!/^(0|[1-9][0-9]{0,7})(\.[0-9]{1,2})?$/.test(text)) return { error: required ? '请输入大于 0 的分享金额，最多两位小数' : '分享金额须为非负金额，最多两位小数；也可以留空' }
    if (Number(text) === 0) return required ? { error: '分享金额须大于 0；暂不分享可返回详情' } : {}
    return { amount: Number(text).toFixed(2) }
}
async function submitAction() {
    if (!action.value || saving.value) return
    actionError.value = ''
    if (!actionAllowed(action.value)) { actionError.value = '工单状态已变化，请返回详情刷新后再处理'; return }
    const current = action.value, id = selectedId.value, identity = String(getToken() || '')
    if (!identity) { redirectLogin(); return }
    const body: Row = {}
    if (current === 'reject') body.reason = form.remark.trim() || '商户暂不接单'
    if (current === 'outcome') {
        if (!['deal', 'no_deal'].includes(form.outcome)) { actionError.value = '请选择已成单或未成单'; return }
        body.outcome = form.outcome; body.remark = form.remark.trim()
        if (form.outcome === 'deal' && form.shareNow) { const share = normalizedShare(form.share_amount, false); if (share.error) { actionError.value = share.error; return }; if (share.amount) body.share_amount = share.amount }
    }
    if (current === 'share') { const share = normalizedShare(form.share_amount, true); if (share.error) { actionError.value = share.error; return }; body.share_amount = share.amount; body.remark = form.remark.trim() }
    saving.value = true
    try {
        await merchantTaskAction(id, current, body)
        if (disposed || !active || identity !== String(getToken() || '') || selectedId.value !== id) return
        uni.showToast({ title: current === 'reject' ? '已退回平台安排' : current === 'accept' ? '已接单，请联系客户' : current === 'share' ? '分享已登记，待确认到账' : body.outcome === 'deal' ? '已反馈成单' : '已反馈未成单', icon: 'none' })
        action.value = ''; await loadTask(); await load(false)
    } catch (cause) { if (!disposed && active && identity === String(getToken() || '') && selectedId.value === id) actionError.value = message(cause, '处理未完成，请保留填写内容并刷新核对后重试。') }
    finally { saving.value = false }
}
onLoad(options => { if (options?.task_id === undefined) return; const id = parseTaskId(options.task_id); if (id) pendingTaskId.value = id; else entryError.value = '通知中的工单链接无效，未读取工单；请从本店工单列表查找。' })
async function resumePage() { if (loading.value) return; startClock(); disposed = false; active = true; const identity = String(getToken() || ''); loggedIn.value = !!identity; if (!identity && taskVisible.value) pendingTaskId.value = selectedId.value; if (identity !== lastIdentity) { detailSequence++; merchant.value = {}; stats.value = {}; lists.workorders = []; lists.inventory = []; lists.commissions = []; taskVisible.value = false; action.value = ''; detail.value = {}; lastIdentity = identity }; if (!loggedIn.value) { redirectLogin(); return }; if (pendingTaskId.value) tab.value = 'workorders'; const loaded = await load(false); if (!loaded || disposed) return; if (pendingTaskId.value) { const id = pendingTaskId.value; pendingTaskId.value = 0; await openTask(id) } else if (taskVisible.value) await loadTask() }
function pausePage() { active = false; stopClock(); sequence++; detailSequence++; loading.value = false; detailLoading.value = false }
onMounted(resumePage)
onShow(resumePage)
onHide(pausePage)
onUnload(() => { pausePage(); disposed = true })
onBeforeUnmount(() => { pausePage(); disposed = true })
defineExpose({ resume: resumePage, pause: pausePage, refresh, loadMore })
</script>

<style scoped lang="scss">
.inventory-statuses { display: flex; flex: none; flex-wrap: wrap; justify-content: flex-end; gap: 8rpx; max-width: 46%; }
.merchant-lead-page { --lead-primary: var(--primary-color, #4264ec); min-height: 100vh; padding: 24rpx 24rpx calc(40rpx + env(safe-area-inset-bottom)); background: #f5f6f8; color: #253047; box-sizing: border-box; }
.merchant-card, .record-card, .empty-card, .detail-card { background: #fff; border-radius: 22rpx; }
.merchant-card { padding: 28rpx 30rpx 0; }.merchant-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; }.eyebrow { color: #8b96a6; font-size: 22rpx; margin-bottom: 12rpx; }.merchant-name { font-size: 36rpx; font-weight: 650; line-height: 1.45; margin-bottom: 10rpx; word-break: break-word; }.icon-button { padding: 16rpx; margin: -8rpx -12rpx 0 0; flex: none; }.todo-grid { display: flex; margin-top: 28rpx; border-top: 1rpx solid #f0f2f5; padding: 25rpx 0; }.todo-grid > view { flex: 1; padding-left: 20rpx; }.todo-grid > view + view { border-left: 1rpx solid #edf0f3; padding-left: 40rpx; }.todo-number { font-size: 44rpx; line-height: 1.2; font-weight: 650; margin-bottom: 8rpx; }.todo-arrow { margin-left: 10rpx; color: #abb4c0; }
.scope-note { display: flex; gap: 8rpx; align-items: center; color: #8793a4; font-size: 21rpx; padding: 22rpx 6rpx; }.subscribe-entry { margin-left: auto; }.main-tabs { display: flex; border-bottom: 1rpx solid #e8ecf2; margin-top: 6rpx; }.main-tab { position: relative; flex: 1; text-align: center; padding: 22rpx 0; color: #8793a4; font-size: 27rpx; }.main-tab.active { color: #253047; font-weight: 650; }.main-tab.active::after { content: ''; position: absolute; bottom: -1rpx; left: 35%; right: 35%; height: 5rpx; border-radius: 5rpx; background: var(--lead-primary); }.search-card { padding: 24rpx 0 4rpx; }.status-picker { display: inline-flex; align-items: center; gap: 12rpx; padding: 20rpx 4rpx; font-size: 23rpx; color: #66758a; }
.record-card { margin: 18rpx 0; padding: 28rpx; }.record-head { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; }.record-title { font-size: 29rpx; font-weight: 650; min-width: 0; word-break: break-word; line-height: 1.5; }.need-title { margin: 20rpx 0 12rpx; font-size: 28rpx; font-weight: 550; line-height: 1.6; word-break: break-word; }.compact-line { color: #8b96a6; font-size: 23rpx; margin-top: 10rpx; line-height: 1.7; word-break: break-word; }.record-line { display: flex; justify-content: space-between; align-items: flex-start; gap: 24rpx; line-height: 1.7; margin: 18rpx 0; font-size: 24rpx; }.record-line > text:first-child { color: #8b96a6; flex: none; }.record-line > text:last-child { text-align: right; word-break: break-word; min-width: 0; }.record-foot { display: flex; justify-content: space-between; gap: 16rpx; font-size: 21rpx; color: #8b96a6; border-top: 1rpx solid #f0f2f5; margin-top: 24rpx; padding-top: 20rpx; align-items: center; }.text-link, .price { color: var(--lead-primary); }.price { font-weight: 650; }.status-chip { flex: none; padding: 6rpx 12rpx; border-radius: 8rpx; font-size: 21rpx; background: #f1f3f6; color: #8793a4; }.status-assigned, .status-unpaid, .status-refund_pending { background: #fff5e8; color: #b67a23; }.status-following { background: var(--primary-color-light, #edf1ff); color: var(--lead-primary); }.status-completed, .status-paid { color: #31866c; background: #eef8f3; }
.empty-card { margin-top: 20rpx; padding: 64rpx 30rpx; text-align: center; }.empty-card :deep(.u-icon) { justify-content: center; }.empty-title { font-size: 29rpx; font-weight: 600; margin: 22rpx 0 16rpx; }.muted { color: #8b96a6; font-size: 23rpx; line-height: 1.7; word-break: break-word; }.loading-card { display: flex; align-items: center; flex-direction: column; gap: 20rpx; padding: 70rpx 0; color: #8b96a6; font-size: 24rpx; }.error-card { margin: 20rpx 0; padding: 24rpx; border-radius: 16rpx; background: #fff0ef; color: #bb5d58; font-size: 24rpx; line-height: 1.8; }.info-note { margin: 20rpx 0; padding: 22rpx; border-radius: 14rpx; background: #eff2f6; color: #7d899b; font-size: 23rpx; line-height: 1.7; }.loadmore { padding: 24rpx 0; }
.task-sheet { height: 88vh; display: flex; flex-direction: column; background: #f5f6f8; border-radius: 30rpx 30rpx 0 0; overflow: hidden; }.sheet-header { display: flex; align-items: center; gap: 16rpx; padding: 27rpx 30rpx 22rpx; background: #fff; flex: none; }.sheet-heading { flex: 1; min-width: 0; }.sheet-title { font-size: 32rpx; font-weight: 650; margin-bottom: 4rpx; }.sheet-back { padding: 18rpx 14rpx 18rpx 0; }.sheet-body { flex: 1; height: 0; min-height: 0; box-sizing: border-box; }.detail-card { margin: 20rpx 24rpx; padding: 28rpx; }.customer-mobile { margin-top: 12rpx; font-size: 26rpx; color: #687589; }.contact-button { display: flex; align-items: center; gap: 8rpx; font-size: 24rpx; color: var(--lead-primary); flex: none; }.next-step { padding-top: 20rpx; margin-top: 22rpx; border-top: 1rpx solid #f0f2f5; color: #67758a; font-size: 23rpx; line-height: 1.7; }.sheet-body .info-note { margin-left: 24rpx; margin-right: 24rpx; }.section-title { font-size: 27rpx; font-weight: 650; margin-bottom: 18rpx; }.requirements-wrap :deep(.requirements-detail) { padding: 0; border: 0; }.requirements-wrap :deep(.requirements-title) { font-size: 27rpx; }.requirements-wrap :deep(.requirements-mode) { background: #f3f5f8; color: #67758a; }.requirements-wrap :deep(.requirement-row) { border-color: #edf0f3; }.requirements-wrap :deep(.requirement-quantity) { color: var(--lead-primary); }.detail-note { margin-top: 20rpx; padding: 20rpx; background: #f6f7f9; color: #687589; border-radius: 10rpx; font-size: 24rpx; white-space: pre-wrap; word-break: break-word; line-height: 1.75; }.fold-heading { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; color: #687589; font-size: 25rpx; }.history-empty { margin-top: 24rpx; }.follow-item { margin: 24rpx 0 8rpx; padding-left: 20rpx; border-left: 3rpx solid #e5e9f0; }.follow-content { color: #67758a; white-space: pre-wrap; word-break: break-word; font-size: 24rpx; line-height: 1.7; }
.action-body { background: #fff; padding: 34rpx 32rpx 50rpx; min-height: 100%; box-sizing: border-box; }.action-lead { font-size: 32rpx; font-weight: 650; margin-bottom: 18rpx; line-height: 1.6; }.action-customer { background: #f5f6f8; border-radius: 12rpx; padding: 24rpx; margin-top: 32rpx; font-size: 26rpx; line-height: 1.7; word-break: break-word; }.outcome-options { margin-top: 30rpx; }.outcome-option { border: 1rpx solid #e6eaf0; border-radius: 18rpx; padding: 28rpx 24rpx; display: flex; justify-content: space-between; align-items: center; gap: 20rpx; margin: 20rpx 0; }.outcome-option.selected { border-color: var(--lead-primary); background: var(--primary-color-light, #f6f8ff); }.outcome-copy { min-width: 0; }.option-title { font-size: 28rpx; font-weight: 550; margin-bottom: 6rpx; }.share-choice { display: flex; justify-content: space-between; align-items: center; gap: 20rpx; margin: 34rpx 0 18rpx; padding-top: 30rpx; border-top: 1rpx solid #f0f2f5; }.share-choice :deep(.u-switch) { flex: none; }.field-label { display: block; margin: 30rpx 0 16rpx; font-size: 25rpx; color: #526078; }.optional { color: #9aa5b4; font-size: 22rpx; font-weight: 400; margin-left: 10rpx; }.field-help { color: #909aab; font-size: 22rpx; margin-top: 14rpx; line-height: 1.75; }.inline-error { margin-top: 24rpx; padding: 18rpx; color: #bb5d58; background: #fff1f0; border-radius: 10rpx; font-size: 24rpx; line-height: 1.7; }.sheet-footer { flex: none; display: flex; align-items: center; gap: 20rpx; padding: 22rpx 28rpx calc(22rpx + env(safe-area-inset-bottom)); background: #fff; border-top: 1rpx solid #edf0f3; }.footer-secondary { flex: 0 0 29%; min-width: 0; }.footer-primary { flex: 1; min-width: 0; }.sheet-footer :deep(.u-button) { border-radius: 14rpx; height: 88rpx; font-size: 27rpx; }.sheet-footer :deep(.u-button--primary) { background: var(--lead-primary); border-color: var(--lead-primary); }.sheet-footer :deep(.u-button--info) { background: #f3f5f8; border-color: #f3f5f8; color: #687589; }
</style>
