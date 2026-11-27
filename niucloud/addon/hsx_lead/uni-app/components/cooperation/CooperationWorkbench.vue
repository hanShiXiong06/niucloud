<template>
    <view class="hsx-cooperation" :style="themeColor()">
        <template v-if="!task">
        <CooperationNav :title="workspaceTitle" :actions="[{ key: 'refresh', icon: 'reload', ariaLabel: '刷新当前业务数据', loading: ui.loading.value || visitorLoading, disabled: ui.loading.value || visitorLoading }]" @action="refresh" />
        <view v-if="leadControlsVisible" class="coop-delivery-controls" :class="{ 'is-pending': !leadListVisible }" :aria-busy="!leadListVisible">
            <view class="coop-list-tools">
                <CooperationSearch v-model="leadKeyword" placeholder="搜索客户或意向型号" :disabled="!leadListVisible || locked" :show-action="true" action-text="查询" @search="searchLeads" @custom="searchLeads" @clear="clearLeadSearch" />
                <scroll-view scroll-x class="coop-status-tabs"><view class="coop-status-tabs__inner"><text v-for="item in visibleStatusOptions" :key="item.value" :class="{ active: ui.query.status === item.value }" @tap="filterStatus(item.value)">{{ item.label }}</text></view></scroll-view>
            </view>
        </view>
        <view v-if="leadListVisible" class="coop-main-paging"><z-paging :key="leadPagingRevision" ref="leadPagingRef" :model-value="ui.rows.value" class="coop-lead-pager" height="100%" :fixed="false" :auto="true" :concat="false" :default-page-size="20" :scrollable="!overlay && !childOverlay" :refresher-enabled="!overlay && !childOverlay" :auto-clean-list-when-reload="false" :safe-area-inset-bottom="false" :call-network-reject="false" :show-loading-more-no-more-line="false" loading-more-fail-text="读取失败，点击重试" loading-more-no-more-text="已显示全部交付" @query="leadPagingQuery">
                    <view v-if="ui.profile.value.blocked_at" class="coop-warning">合作已停止。历史交付与已形成收益仍保留，请到“我的”查看原因。</view>
                    <view v-else-if="!ui.config.value.enabled" class="coop-warning">本站尚未开启商户协作，发单前请联系平台完成配置。</view>
                    <view v-else-if="needsMembershipEntitlement" class="coop-payment-status"><text>当前会员尚无此项协作权限</text><text @tap="openMembership">查看会员权益 ›</text></view>
                    <view v-if="paymentId" class="coop-payment-status">
<text>{{ paymentMessage || '上笔支付结果待核对，不要重复下单' }}</text>
<text @tap="resumePayment">核对 / 继续支付 ›</text>
</view>
                    <view v-if="ui.writeError.value && !overlay" class="coop-warning">{{ ui.writeError.value }}</view>
<view v-if="ui.error.value" class="coop-warning">{{ ui.error.value }}</view>
<view class="coop-work-overview">
<template v-if="ui.direction.value === 'outgoing'"><view @tap="focusTasks('outgoing', 'selecting')"><view class="coop-work-overview__icon is-select"><u-icon name="home" size="20" color="#a67b41" /></view><view><text>{{ ui.stats.value.selecting ?? '—' }}</text><text>待选门店</text></view></view><view @tap="focusTasks('outgoing', '')"><view class="coop-work-overview__icon is-follow"><u-icon name="order" size="20" color="#5a8a74" /></view><view><text>{{ ui.stats.value.outgoing ?? '—' }}</text><text>发出线索进度</text></view></view></template>
<template v-else><view @tap="focusTasks('incoming', 'assigned')"><view class="coop-work-overview__icon is-receive"><u-icon name="order" size="20" color="#617eaa" /></view><view><text>{{ ui.stats.value.assigned ?? '—' }}</text><text>等待接单</text></view></view><view @tap="focusTasks('incoming', 'following')"><view class="coop-work-overview__icon is-follow"><u-icon name="chat" size="20" color="#5a8a74" /></view><view><text>{{ ui.stats.value.following ?? '—' }}</text><text>跟进客户</text></view></view><view @tap="focusTasks('incoming', 'unpaid')"><view class="coop-work-overview__icon is-select"><u-icon name="order" size="20" color="#a67b41" /></view><view><text>{{ ui.stats.value.unpaid ?? '—' }}</text><text>成交待付佣</text></view></view></template>
</view>
<view class="coop-subscribe-entry" role="button" :aria-busy="subscribeState.loading.value" @tap="subscribe"><u-icon name="bell" size="17" color="#ad8245" /><text>及时接收交付与成交进度</text><text>{{ subscribeState.loading.value ? '准备中…' : '订阅通知 ›' }}</text></view>
<view v-if="ui.listError.value && ui.rows.value.length" class="coop-warning">{{ ui.listError.value }}，请点击列表底部重试。</view>
<view class="coop-lead-list" v-if="ui.rows.value.length"><CoopLeadCard v-for="lead in ui.rows.value" :key="lead.id" :lead="lead" :direction="ui.direction.value" :active="countdownActive" @open="openDetail" @select="openSelection" @cancel="cancelLead" /></view>
            <template #empty="{ isLoadFailed }"><view class="coop-lead-empty"><CoopState :error="isLoadFailed ? ui.listError.value || ui.error.value || '交付读取失败，请重试' : ''" :title="ui.query.keyword || ui.query.status ? '没有符合条件的线索' : ui.direction.value === 'outgoing' ? '还没有发出的客户需求' : '还没有收到的客户交付'" :hint="ui.query.keyword || ui.query.status ? '试试其他关键词，或切换状态查看' : ui.direction.value === 'outgoing' ? '发布客户需求后，再为这条需求选择合适门店' : '其他门店交付给你的客户，会在这里接单并跟进'" @retry="reloadLeads" /></view></template>
        </z-paging></view>
        <scroll-view v-else :scroll-y="!overlay && !childOverlay && !onboarding" class="coop-main-scroll" @touchmove.stop>
            <template v-if="!ui.loggedIn.value">
                <CoopGuestWorkspace v-if="managedDeliveries" :direction="ui.direction.value" :services="visitorServices" :enabled="visitorEnabled" :loading="visitorLoading" :error="visitorError" @action="guestAction" @retry="refresh" />
                <template v-else><CoopState title="登录后查看我的协作" hint="登录后可查看自己的门店与收益" icon="account" /><view class="coop-login"><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="登录" @click="login()" /></view></template>
            </template>
            <CoopState v-else-if="!ui.ready.value || ui.error.value" :loading="ui.loading.value" :error="ui.error.value" @retry="refresh" />
            <template v-else-if="ui.loggedIn.value && ui.profile.value.id">
                    <view v-if="ui.profile.value.blocked_at" class="coop-warning">合作已停止。历史交付与已形成收益仍保留，请到“我的”查看原因。</view>
                    <view v-else-if="!ui.config.value.enabled" class="coop-warning">本站尚未开启商户协作，发单前请联系平台完成配置。</view>
                    <view v-if="paymentId" class="coop-payment-status">
<text>{{ paymentMessage || '上笔支付结果待核对，不要重复下单' }}</text>
<text @tap="resumePayment">核对 / 继续支付 ›</text>
</view>
                    <view v-if="ui.writeError.value && !overlay" class="coop-warning">{{ ui.writeError.value }}</view>
                    <template v-if="ui.tab.value === 'wallet'"><CoopState v-if="ui.profile.value.permissions?.can_withdraw === false" title="门店收益由负责人管理" hint="成员共享协作权益，不共享负责人账户或提现权限" icon="lock" /><CoopWalletPanel v-else :key="'wallet-' + ui.profile.value.id" :wallet="ui.wallet.value" :loading="ui.walletLoading.value" :error="ui.walletError.value" @reload="ui.loadWallet()" @more="ui.loadWallet(true)" /></template>
                    <view v-else class="coop-profile-wrap">
<CoopProfilePanel ref="profileRef" :key="'profile-' + ui.profile.value.id" :profile="ui.profile.value" :onboarding-inviter="ui.onboardingInviter.value" :config="ui.config.value" :providers="ui.providers.value" :busy="ui.busy.value" :error="ui.writeError.value" @submit="saveProfile" @edit="editProfile" @members="openMembers" @promotion="openPromotion" @connection="openTask('connection')" @inventory="openTask('inventory')" @membership="openMembership" @subscribe="subscribe" @overlay="childOverlay = $event" />
</view>
            </template>
            <view v-else-if="onboarding" class="coop-onboarding-entry"><CoopState :title="ui.pendingEntity.value.status === 'pending' ? '等待负责人确认' : '创建或加入门店'" :hint="ui.pendingEntity.value.status === 'pending' ? ui.pendingEntity.value.name : '已有门店的同事可使用主体码加入'" /><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" :text="ui.pendingEntity.value.status === 'pending' ? '查看加入申请' : '创建我的门店'" @click="ui.pendingEntity.value.status === 'pending' ? openMembers() : editProfile()" /><button v-if="ui.pendingEntity.value.status !== 'pending'" class="coop-onboarding-existing" @tap="openMembers">加入已有门店</button></view>
        </scroll-view>
        <view v-if="leadControlsVisible && ui.direction.value === 'outgoing'" class="coop-publish-dock">
<u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" :text="needsMembershipEntitlement ? '查看会员权益，开始协作' : '＋ 发布客户需求，找门店交付'" :disabled="!leadListVisible || locked || !!overlay || childOverlay || !ui.config.value.enabled || !!ui.profile.value.blocked_at" @click="openPublish" />
</view>
        <view v-if="reserveFrameworkTabbar" class="coop-framework-tabbar">
<view v-if="showFrameworkTabbar" class="coop-framework-tabbar-mount"><FrameworkTabbar addon="hsx_lead" /></view>
</view>
        </template>
        <CoopPendingEntry :active="pendingEntryReady && !task && managedDeliveries && ui.loggedIn.value && ui.ready.value && !!ui.profile.value.id && !overlay && !childOverlay && !locked && !canonicalPending" :member-id="ui.memberId.value" @publish="openPublish" />
        <template v-if="task && !overlay"><CooperationNav title="商户协作" back @back="closeOverlay" /><CoopState :loading="ui.loading.value" :error="ui.error.value" :title="ui.loading.value ? '正在读取…' : '请先完善门店资料'" @retry="refresh" /><u-button :custom-style="cooperationButtonStyle" v-if="ui.ready.value && !ui.profile.value.id && task !== 'profile-edit'" text="完善门店资料" @click="editProfile" /></template>
        <view class="coop-overlay-root" :class="{ 'is-task-page': !!task }">
<CoopPromotionPanel ref="promotionRef" :page="!!task" :show="overlay === 'promotion'" :initial-tab="promotionTab" @close="closeOverlay" @changed="markChanged" />
<CoopPublishForm ref="publishRef" :page="!!task" :initial-business-type="initialBusinessType" :merchant-id="ui.profile.value.id" :key="'publish-' + (ui.profile.value.id || 0)" :show="overlay === 'publish'" :config="ui.config.value" :busy="ui.busy.value" :error="ui.writeError.value" @close="closeOverlay" @submit="publish" />
        <CoopStorePicker :page="!!task" :config="ui.config.value" :show="overlay === 'select'" :lead="ui.detail.value" :profile="ui.profile.value" :busy="locked" :submission-error="ui.writeError.value || ui.detailError.value" @close="closeOverlay" @assign="assign" @reload="reloadDetail" />
        <CoopTaskDetail :page="!!task" :show="overlay === 'detail'" :lead="ui.detail.value" :config="ui.config.value" :loading="ui.detailLoading.value" :error="ui.detailError.value || ui.writeError.value" :busy="locked" :active="countdownActive" @close="closeOverlay" @reload="reloadDetail" @select="openSelection(Number(ui.detail.value.id))" @reselect="reselectStore" @membership="openMembership" @pay="openTask('commission', { collaboration_id: ui.detail.value.id })" @action="act" />
        <CoopCommissionForm v-if="!commissionPaymentClosed" ref="commissionRef" :page="!!task" :show="overlay === 'commission'" :lead="commissionLead" :order="paymentOrder" :config="ui.config.value" :busy="ui.busy.value || paymentChecking || paymentResuming" :blocked="!!paymentContextError" :error="paymentContextError || ui.detailError.value || ui.writeError.value" @payment-help-ready="paymentHelpReady = $event" @close="closeOverlay" @submit="payCommission" />
        <CoopTaskSheet v-else :page="!!task" :show="overlay === 'commission'" title="付款单已关闭" @close="closeOverlay"><CoopState title="原付款单已关闭" :hint="paymentContextError || paymentMessage || '请查看付款单并核对最新状态，不会按原金额重新下单。'" icon="order" /><template #footer><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="查看我的付款单" @click="openPaymentOrders" /></template></CoopTaskSheet>
        <CoopTaskSheet v-if="overlay === 'erp-disabled'" page :show="true" title="门店协作" @close="closeOverlay"><CoopState title="该功能暂未开放" /><template #footer><u-button :custom-style="cooperationButtonStyle" text="返回协作" @click="closeOverlay" /></template></CoopTaskSheet>
        <CoopConnectionPanel v-if="ui.config.value.erp_visible !== false" :page="!!task" :show="overlay === 'connection'" :profile="ui.profile.value" :providers="ui.providers.value" @close="closeOverlay" @changed="changedConnection" />
        <CoopStoreInventory v-if="ui.config.value.erp_visible !== false" :page="!!task" :show="overlay === 'inventory'" :store="initialStoreId ? { id: initialStoreId } : ui.profile.value" @close="closeOverlay" />
        <CoopProfilePanel v-if="task === 'profile-edit' && ui.ready.value" ref="profileRef" page editing :profile="ui.profile.value" :onboarding-inviter="ui.onboardingInviter.value" :config="ui.config.value" :providers="ui.providers.value" :onboarding="!ui.profile.value.id" :busy="ui.busy.value" :error="ui.writeError.value" @submit="saveProfile" @close="closeOverlay" @promotion="openPromotion" @connection="openTask('connection')" />
        <view class="coop-cashier-mount"><pay ref="payRef" :ignore-pay="['friendspay']" :reformat="cashierReturnUrl" @confirm="verifyPayment" @close="handlePaymentClose" @check="handlePaymentCheck"><template #help="{ busy }"><button v-if="paymentHelpReady && overlay === 'commission'" class="coop-cashier-help" :disabled="busy || locked || paymentChecking || paymentResuming" @tap.stop="contactPaymentAdmin(busy)"><text>支付遇到问题？</text><text>联系管理员 ›</text></button></template></pay></view>
</view>
    </view>
</template>
<script setup lang="ts">
import CooperationSearch from './CooperationSearch.vue'
import ZPaging from 'z-paging/components/z-paging/z-paging.vue'
import CooperationNav from './CooperationNav.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getToken } from '@/utils/common'
import FrameworkTabbar from '@/components/tabbar/tabbar.vue'
import { useLogin } from '@/hooks/useLogin'
import { useCooperationSubscribe } from '../../composables/useCooperationSubscribe'
import { useCooperation } from '../../composables/useCooperation'
import { cooperationAccess, cooperationOrder, cooperationPageUrls, coopError, coopMoney, coopRequestId, type CooperationTab, type CoopRow, type LeadAction } from '../../api/cooperation'
import CoopState from './CoopState.vue'
import CoopGuestWorkspace from './CoopGuestWorkspace.vue'
import CoopPendingEntry from './CoopPendingEntry.vue'
import CoopLeadCard from './CoopLeadCard.vue'
import CoopPublishForm from './CoopPublishForm.vue'
import CoopStorePicker from './CoopStorePicker.vue'
import CoopStoreInventory from './CoopStoreInventory.vue'
import CoopTaskDetail from './CoopTaskDetail.vue'
import CoopCommissionForm from './CoopCommissionForm.vue'
import CoopWalletPanel from './CoopWalletPanel.vue'
import CoopProfilePanel from './CoopProfilePanel.vue'
import CoopConnectionPanel from './CoopConnectionPanel.vue'
import CoopPromotionPanel from './CoopPromotionPanel.vue'
import CoopTaskSheet from './CoopTaskSheet.vue'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { backCooperationTask, cooperationTaskLink, cooperationTaskUrl, openCooperationTask } from '../../utils/cooperation-navigation'
import { leadFeeSnapshot } from '../../utils/cooperation-business'
import { cooperationPaymentContext, cooperationOrderContext, sameCooperationPaymentContext, type CooperationPaymentContext } from '../../utils/cooperation-payment'
import { cooperationPaymentReplacementId, cooperationPaymentReplacementMatches } from '../../utils/cooperation-payment-orders'
import { membershipPageUrl } from '../../utils/entity-membership'
const props = withDefaults(defineProps<{ initialId?: number; initialTab?: CooperationTab; initialInviter?: number; initialPromotion?: boolean; task?: string; initialStoreId?: number; initialPaymentId?: number; initialPromotionTab?: string; initialBusinessType?: string; initialDirection?: 'outgoing' | 'incoming' }>(), { initialId: 0, initialTab: 'deliveries', initialInviter: 0, initialPromotion: false, task: '', initialStoreId: 0, initialPaymentId: 0, initialPromotionTab: 'overview', initialBusinessType: '', initialDirection: 'outgoing' })
const ui = useCooperation(props.initialTab === 'stores' ? 'deliveries' : props.initialTab), pageUrl = cooperationPageUrls[props.initialTab], overlay = ref(''), childOverlay = ref(false), payRef = ref<any>(null), publishRef = ref<any>(null), profileRef = ref<any>(null), promotionRef = ref<any>(null), promotionTab = ref('overview'), confirming = ref(false), paymentId = ref(0), paymentMessage = ref(''), paymentChecking = ref(false), selectedId = ref(0), canonicalPending = ref(props.initialId > 0)
ui.direction.value = props.initialDirection
const paymentOrder = ref<CoopRow>({}), paymentCompleted = ref(false)
const commissionRef = ref<any>(null), paymentHelpReady = ref(false)
const visitorServices = ref<{ key: string; name: string; dimension?: string }[]>([]), visitorEnabled = ref<boolean | null>(null), visitorLoading = ref(false), visitorError = ref('')
const leadPagingRef = ref<any>(null), leadPagingRevision = ref(0), leadKeyword = ref(''), leadFirstPageReady = ref(false)
const managedDeliveries = computed(() => !props.task && ui.tab.value === 'deliveries')
const leadListVisible = computed(() => managedDeliveries.value && ui.loggedIn.value && ui.ready.value && !!ui.profile.value.id)
// Keep role controls/dock in the same positions while the initial identity's
// metadata loads. They become usable only after its profile is verified.
const leadControlsVisible = computed(() => managedDeliveries.value && ui.loggedIn.value && (!ui.ready.value || !!ui.profile.value.id))
type LeadPagingQuery = { page: number; size: number; from: string; readingList: boolean; signature: string }
let leadPagingEpoch = 0, pageActive = true, leadPagingPlanned = false, pendingLeadPaging: LeadPagingQuery | null = null, interruptedLeadPaging: LeadPagingQuery | null = null, appliedLeadSignature = ''
const countdownActive = ref(true)
const pendingEntryReady = ref(false)
const leadPagingSignature = () => JSON.stringify([identity, ui.direction.value, ui.query.keyword, ui.query.status])
// The request hook owns rows. Do not accept z-paging's delayed model emissions as
// business data; an old instance can emit after its nextTick/setTimeout boundary.
const leadPagingQuery = computed(() => { const revision = leadPagingRevision.value; return (page: number, size: number, from = '') => queryLeads(page, size, from, revision) })
async function finishLeadPaging(success: boolean) {
    if (success && !ui.listError.value) { leadFirstPageReady.value = true; await leadPagingRef.value?.completeByNoMore([...ui.rows.value], !ui.canLoadMore.value) }
    // With call-network-reject=false, z-paging intentionally leaves its failed
    // completion promise pending. The request lifecycle must still finish.
    else void leadPagingRef.value?.complete(false)
}
async function queryLeads(page: number, size: number, from = '', revision = leadPagingRevision.value) {
    if (!pageActive || !leadListVisible.value || revision !== leadPagingRevision.value) return
    leadPagingPlanned = false
    // z-paging's footer increments pageNo even after a first-page failure. Never
    // append until page one of this committed query has actually succeeded.
    if (page > 1 && !leadFirstPageReady.value) return reloadLeads()
    if (page === 1) { leadFirstPageReady.value = false; if (appliedLeadSignature !== leadPagingSignature()) { ui.rows.value = []; ui.total.value = 0; appliedLeadSignature = leadPagingSignature() } }
    const epoch = ++leadPagingEpoch, request = { page, size, from, readingList: false, signature: leadPagingSignature() }
    pendingLeadPaging = request
    try {
        if (page === 1 && from === 'user-pull-down' && !await ui.bootstrap()) { if (epoch === leadPagingEpoch && pageActive) await finishLeadPaging(false); return }
        if (epoch !== leadPagingEpoch || !pageActive) return
        ui.query.limit = size
        if (page > 1) ui.query.page = page - 1
        request.readingList = true
        const success = await ui.loadLeads(page > 1)
        if (epoch !== leadPagingEpoch || !pageActive || request.signature !== leadPagingSignature()) return
        await finishLeadPaging(success)
    } finally { if (pendingLeadPaging === request) pendingLeadPaging = null }
}
function reloadLeads() {
    if (!pageActive || !managedDeliveries.value) return
    leadPagingEpoch++; pendingLeadPaging = null; leadFirstPageReady.value = false
    const signature = leadPagingSignature(), changed = appliedLeadSignature !== signature
    if (changed) { ui.rows.value = []; ui.total.value = 0; ui.listError.value = '' }
    appliedLeadSignature = signature
    // Native component refs can arrive after the parent's nextTick. The keyed
    // pager owns its one initial query from mounted; never silently drop it via
    // an early optional ref.reload(), nor race that call against auto loading.
    leadPagingPlanned = true
    leadPagingRevision.value++
}
function searchLeads(value?: string | number) {
    if (!leadListVisible.value || locked.value) return
    // u-search emits its submitted value before its watched v-model can flush
    // on H5/Weixin. Commit this event, not yesterday's draft, to the pager.
    const keyword = String(typeof value === 'string' || typeof value === 'number' ? value : leadKeyword.value).trim()
    const unchanged = ui.query.keyword === keyword
    leadKeyword.value = keyword; ui.query.keyword = keyword
    if (unchanged && (leadPagingPlanned || !!pendingLeadPaging || ui.listLoading.value)) return
    return reloadLeads()
}
function clearLeadSearch() { return searchLeads('') }
function changeLeadDirection(direction: 'outgoing' | 'incoming') {
    if (locked.value || direction === ui.direction.value) return
    ui.direction.value = direction; leadKeyword.value = ''; ui.query.keyword = ''; ui.query.status = ''; ui.rows.value = []; ui.total.value = 0
    if (managedDeliveries.value) uni.setNavigationBarTitle?.({ title: workspaceTitle.value })
    reloadLeads()
}
async function refreshBusinessData() {
    if (!managedDeliveries.value) return ui.refresh()
    const loaded = await ui.bootstrap()
    if (loaded && ui.profile.value.id && pageActive) reloadLeads()
    return loaded
}
const paymentContextError = ref(''), paymentResuming = ref(false)
const expectedPaymentContext = computed(() => props.task === 'commission' ? cooperationPaymentContext('commission', props.initialId) : null)
const commissionPaymentClosed = computed(() => paymentOrder.value.status === 'closed' && Number(paymentOrder.value.lead_id) === Number(ui.detail.value.id))
const commissionLead = computed(() => paymentOrder.value.status === 'unpaid' && Number(paymentOrder.value.lead_id) === Number(ui.detail.value.id) ? { ...ui.detail.value, payment_order_id: Number(paymentOrder.value.id), commission_amount: paymentOrder.value.amount, fee_amount: paymentOrder.value.fee_amount, net_amount: paymentOrder.value.net_amount } : ui.detail.value)
const cashierReturnUrl = computed(() => props.task ? cooperationTaskLink(props.task, { collaboration_id: props.initialId || undefined, payment_order_id: paymentId.value || paymentOrder.value.id || props.initialPaymentId || undefined }) : pageUrl)
const locked = computed(() => ui.busy.value || confirming.value), onboarding = computed(() => ui.loggedIn.value && ui.ready.value && !ui.profile.value.id && !ui.error.value && overlay.value !== 'promotion')
// The actual framework bar may be hidden by an overlay. Its layout reserve is
// independent of asynchronous metadata and write state, so the list never
// expands/contracts underneath a confirmation or every reLaunch tab switch.
const reserveFrameworkTabbar = computed(() => !props.task && (managedDeliveries.value || (ui.ready.value && !!ui.profile.value.id)))
const showFrameworkTabbar = computed(() => reserveFrameworkTabbar.value && !locked.value && !overlay.value && !childOverlay.value && !onboarding.value && !canonicalPending.value)
const titles: Record<string, string> = { deliveries: '找客户', stores: '找门店', wallet: '我的收益', profile: '我的门店' }
const workspaceTitle = computed(() => ui.tab.value === 'deliveries' ? ui.direction.value === 'outgoing' ? '找门店' : '找客户' : titles[ui.tab.value])
const statusOptions = [{ value: '', label: '全部' }, { value: 'selecting', label: '待选门店' }, { value: 'assigned', label: '待接单' }, { value: 'following', label: '跟进中' }, { value: 'unpaid', label: '待付佣' }, { value: 'paid', label: '已付佣' }, { value: 'no_deal', label: '未成交' }, { value: 'cancelled', label: '已取消' }]
const visibleStatusOptions = computed(() => statusOptions.filter(item => ui.direction.value === 'outgoing' || !['selecting', 'cancelled'].includes(item.value)).map(item => ui.direction.value === 'outgoing' && item.value === 'assigned' ? { ...item, label: '待对方接单' } : ui.direction.value === 'outgoing' && item.value === 'unpaid' ? { ...item, label: '待对方付佣' } : item))
function needsMembershipNow(capability:'can_publish'|'can_accept' = ui.direction.value==='outgoing'?'can_publish':'can_accept') {
    return ui.config.value.publishing_required === true && ui.profile.value[capability] !== true
}
const needsMembershipEntitlement = computed(() => needsMembershipNow())

const orderRequests = new Map<string, string>(); let reviewNotifiedId = 0; let identity = '', disposed = false, starting: Promise<void> | null = null, pendingId = props.initialId, handledInitialId = 0, resumeSequence = 0, leadIntentOpening = false
let pendingPromotion = props.initialPromotion, pendingInviter = props.initialInviter
const storageKey = (context: CooperationPaymentContext | null = null) => Number.isSafeInteger(ui.memberId.value) && ui.memberId.value > 0 && ui.siteId() ? `hsx_lead_coop_pay:${ui.siteId()}:${ui.memberId.value}:${ui.profile.value.id || 0}${context ? ':' + context.purpose + ':' + context.leadId : ''}` : ''
function login(intent?: { task?: 'publish' | 'profile-edit'; business_type?: string }) {
    const targetTask = intent?.task || props.task
    useLogin().setLoginBack({ url: targetTask ? cooperationTaskUrl : pageUrl, param: { ...(targetTask ? { task: targetTask, payment_order_id: props.initialPaymentId || undefined, business_type: intent?.business_type || props.initialBusinessType, store_id: props.initialStoreId, tab: props.initialPromotionTab, mid: props.initialInviter } : {}), ...(pendingId ? { collaboration_id: pendingId } : {}), ...(pendingPromotion ? { promotion_invite: 1, ...(pendingInviter ? { mid: pendingInviter } : {}) } : {}) }, mode: 'redirectTo' })
}
function guestAction(event: { kind?: string; business_type?: string } = {}) {
    if (locked.value || getToken() || visitorEnabled.value === false) return
    if (event.kind === 'progress') { login(); return }
    const business = visitorServices.value.find(item => item.key === event.business_type)
    login({ task: ui.direction.value === 'outgoing' ? 'publish' : 'profile-edit', ...(business ? { business_type: business.key } : {}) })
}
async function initialize() {
    if (props.task === 'stores') { uni.redirectTo({ url: cooperationPageUrls.stores }); return }
    if (starting) return starting
    const turn = ++resumeSequence
    starting = (async () => {
        pageActive = true
        pendingEntryReady.value = false
        countdownActive.value = true
        if (managedDeliveries.value) uni.setNavigationBarTitle?.({ title: workspaceTitle.value })
        const token = ui.resume()
        if (token !== identity) { identity = token; overlay.value = ''; childOverlay.value = false; selectedId.value = 0; leadKeyword.value = ''; paymentId.value = 0; paymentMessage.value = ''; paymentOrder.value = {}; paymentCompleted.value = false; paymentContextError.value = ''; visitorServices.value = []; visitorEnabled.value = null; visitorError.value = ''; orderRequests.clear() }
        if (!token) {
            if(props.task){login();return}
            // A public visit never starts authentication, including an old site's
            // saved entry-prompt flag. Only deliberate business actions do so.
            // Depend on the framework's established setLoginBack, not optional
            // cancellation methods missing from otherwise supported versions.
            if (managedDeliveries.value) {
                const site = ui.siteId(), current = () => !disposed && pageActive && turn === resumeSequence && !getToken() && site === ui.siteId()
                visitorLoading.value = true; visitorError.value = ''
                try {
                    const access = await cooperationAccess()
                    if (!current()) return
                    if (!access?.data || typeof access.data !== 'object' || Array.isArray(access.data)) throw new Error('公开业务信息未完整返回')
                    const visitor = access.data.visitor
                    // Older backends contain only the two presentation flags.
                    // Keep the genuine introduction available, never invent a
                    // service list or force login to disguise a failed request.
                    if (visitor !== undefined) {
                        if (!visitor || typeof visitor.enabled !== 'boolean' || !Array.isArray(visitor.businesses)) throw new Error('公开业务信息未完整返回')
                        visitorEnabled.value = visitor.enabled
                        visitorServices.value = visitor.enabled ? visitor.businesses.filter((item: CoopRow) => item && typeof item.key === 'string' && /^[a-z][a-z0-9_]{0,39}$/.test(item.key) && typeof item.name === 'string' && item.name.trim()).slice(0, 30).map((item: CoopRow) => ({ key: item.key, name: item.name.slice(0, 30), dimension: item.dimension })) : []
                    }
                } catch (cause) { if (current()) visitorError.value = coopError(cause, '公开业务信息暂未更新') }
                finally { if (!disposed && turn === resumeSequence) visitorLoading.value = false }
            }
            return
        }
        // Login may return to an already mounted guest page. Refresh discovery
        // independently; neither its result nor a native prompt gates business.
        void subscribeState.prepare()
        const resumed = ui.ready.value, dirty = Number(uni.getStorageSync('hsx_lead_coop_changed') || 0)
        if (resumed && !dirty) {
            const interrupted = interruptedLeadPaging
            interruptedLeadPaging = null
            await ui.resumeInterruptedReads()
            if (interrupted && managedDeliveries.value && pageActive && interrupted.signature === leadPagingSignature()) {
                if (interrupted.readingList) await finishLeadPaging(!ui.listError.value)
                else await queryLeads(interrupted.page, interrupted.size, interrupted.from)
            }
            if (disposed || turn !== resumeSequence) return
            // Bootstrap readiness is not list readiness. A page can be hidden
            // before its native pager mounts; resume that missing first read,
            // but retain a completed empty page and an already planned query.
            if (managedDeliveries.value && pageActive && !leadFirstPageReady.value && !leadPagingPlanned && !pendingLeadPaging && !ui.listLoading.value && !ui.listError.value) reloadLeads()
            if (!overlay.value && ['detail', 'select', 'commission'].includes(props.task)) overlay.value = ui.detail.value.id ? props.task : 'detail'
            if (paymentId.value) await verifyPayment()
            if (!props.task) await processEntryIntents()
            return // Keep the mounted list, filter, scroll position and form draft.
        }
        const loaded = props.task ? await ui.bootstrap() : await refreshBusinessData()
        if (!loaded || disposed || turn !== resumeSequence) return
        if (!props.task) uni.removeStorageSync('hsx_lead_coop_changed')
        if (props.task) {
            if (['connection','inventory'].includes(props.task) && ui.config.value.erp_visible === false) { overlay.value = 'erp-disabled'; return }
            if (!ui.profile.value.id && !['profile-edit', 'promotion'].includes(props.task)) return
            selectedId.value = props.initialId
            let detailLoaded = true
            if (['detail', 'select', 'commission'].includes(props.task)) detailLoaded = await ui.loadDetail(props.initialId)
            if (disposed || turn !== resumeSequence) return
            promotionTab.value = props.initialPromotionTab
            if (['publish', 'select'].includes(props.task) && !(props.task === 'select' && ui.detail.value.status === 'assigned' && ui.detail.value.can_reassign === true) && needsMembershipNow('can_publish')) { uni.redirectTo({ url: membershipPageUrl }); return }
            overlay.value = detailLoaded ? props.task : 'detail'
            if (props.task === 'promotion' && props.initialInviter) { await nextTick(); await promotionRef.value?.openInvitation?.(props.initialInviter) }
        } else {
            await processEntryIntents()
        }
        if (!ui.profile.value.id) return
        const context = expectedPaymentContext.value
        const savedKey = storageKey(context)
        const saved = Number(props.initialPaymentId || ((!props.task || context) && savedKey ? uni.getStorageSync(savedKey) : 0) || (context ? ui.detail.value.payment_order_id : 0) || 0)
        if (Number.isSafeInteger(saved) && saved > 0) { paymentId.value = saved; await verifyPayment() }
    })().finally(() => { if (turn === resumeSequence) starting = null })
    return starting
}
async function refresh() { if (ui.loading.value) return; if (!ui.loggedIn.value || !ui.ready.value) await initialize(); else { const loaded = await refreshBusinessData(); if (!loaded || disposed || !pageActive) return; if (props.task && selectedId.value) await reloadDetail(); else if (!props.task) await processEntryIntents() } }
function markChanged() { uni.setStorageSync('hsx_lead_coop_changed', Date.now()) }
function openTask(task: string, params: CoopRow = {}) { if (['connection','inventory'].includes(task) && ui.config.value.erp_visible === false) { uni.showToast({title:'该功能暂未开放',icon:'none'}); return false }; return !locked.value && openCooperationTask(task, params) }
function processLeadIntent() {
    if (props.task || !pendingId || pendingId === handledInitialId || leadIntentOpening || locked.value || disposed || !pageActive || !ui.ready.value || !ui.profile.value.id) return false
    const id = pendingId, who = identity
    canonicalPending.value = true; leadIntentOpening = true
    const started = openCooperationTask('detail', { collaboration_id: id }, opened => {
        leadIntentOpening = false
        if (disposed || who !== identity || id !== pendingId) return
        // Do not consume a card intent when native navigation failed. A normal
        // resume or refresh can retry it, without duplicating a successful push.
        if (opened) { handledInitialId = id; canonicalPending.value = false }
    })
    if (!started) leadIntentOpening = false
    return started
}
async function processEntryIntents() {
    if (disposed || !pageActive || props.task) return
    // A notification's concrete order always wins over promotion or a general
    // pending summary; never issue two competing native navigations.
    if (pendingId && pendingId !== handledInitialId) { pendingPromotion = false; pendingInviter = 0; processLeadIntent(); return }
    if (pendingPromotion) { await processPromotionIntent(); return }
    pendingEntryReady.value = true
}
function closeOverlay() { if (locked.value) return; ui.writeError.value = ''; if (props.task) { backCooperationTask(); return }; overlay.value = ''; canonicalPending.value = false }
async function handlePaymentClose() { await verifyPayment() }
function paymentPageCurrent() { return !disposed && pageActive && !!getToken() && !!identity && identity === ui.sessionScope() }
function handlePaymentCheck() {
    if (!paymentPageCurrent()) return
    // The framework close event already rechecks the retained order. Do not
    // start a second check, a replacement payment, or a financial close here.
    if (typeof payRef.value?.close === 'function') payRef.value.close()
    else void verifyPayment()
}
function contactPaymentAdmin(nativeBusy = false) {
    if (nativeBusy || !paymentPageCurrent() || overlay.value !== 'commission' || !paymentHelpReady.value || locked.value || paymentChecking.value || paymentResuming.value) return
    // Reuse the form's one current-tenant helper; opening it must succeed
    // before the cashier is dismissed and its existing read-back runs.
    if (commissionRef.value?.openPaymentHelp?.() !== true) {
        paymentHelpReady.value = false
        uni.showToast({ title: '管理员联系方式暂不可用，请重新读取页面', icon: 'none' })
        return
    }
    if (typeof payRef.value?.close === 'function') payRef.value.close()
}
function filterStatus(value: string) { if (!leadListVisible.value || locked.value || ui.query.status === value) return; ui.query.status = value; reloadLeads() }
async function focusTasks(direction: 'outgoing' | 'incoming', status: string) { if (ui.listLoading.value) return; if (ui.direction.value !== direction) { ui.direction.value = direction; leadKeyword.value = ''; ui.query.keyword = '' }; ui.query.status = status; reloadLeads() }
function editProfile() { openTask('profile-edit') }
function openPromotion(tab = 'overview') { openTask('promotion', { tab }) }
function openMembership() { if (!locked.value) uni.navigateTo({ url: membershipPageUrl }) }
function openPaymentOrders() { if (!locked.value) uni.navigateTo({ url: '/addon/hsx_lead/pages/orders/index' }) }
function openMembers() { openTask('members') }
async function processPromotionIntent() { if (!pendingPromotion || !ui.loggedIn.value || !ui.ready.value || locked.value || disposed) return; const inviter = pendingInviter; pendingPromotion = false; pendingInviter = 0; openTask('promotion', { mid: inviter }) }
async function openInvitation(mid: number) { const inviter = Number(mid); if (!Number.isSafeInteger(inviter) || inviter <= 0 || inviter > 4294967295) return; pendingInviter = inviter; pendingPromotion = true; await processPromotionIntent() }
async function cancelLead(id: number) { if (locked.value || ui.detailLoading.value) return; const loaded = await ui.loadDetail(Number(id)); if (loaded && ui.detail.value.can_cancel) { selectedId.value = Number(id); await act('cancel') } else if (loaded) uni.showToast({ title: '线索状态已变化，请刷新后查看', icon: 'none' }) }
function loadMore() { /* z-paging owns pull-to-refresh and reach-bottom for the role lists. */ }
async function openDetail(id: number) { openTask('detail', { collaboration_id: id }) }
async function reloadDetail() { if (selectedId.value) { ui.writeError.value = ''; await ui.loadDetail(selectedId.value) } }
async function openSelection(id: number) { if (!(Number(ui.detail.value.id) === id && ui.detail.value.status === 'assigned' && ui.detail.value.can_reassign === true) && needsMembershipNow('can_publish')) { openMembership(); return }; openTask('select', { collaboration_id: id }) }
function reselectStore() {
    if (!locked.value && pageActive && ui.detail.value.can_reassign === true) void openSelection(Number(ui.detail.value.id))
}
function openPublish() { if (!ui.loggedIn.value || !ui.ready.value || !ui.profile.value.id || locked.value) return; ui.writeError.value = ''; if (needsMembershipNow('can_publish')) { openMembership(); return }; openTask('publish') }
async function publish(data: CoopRow) { if (needsMembershipNow('can_publish')) { openMembership(); return }; const value = await ui.createLead(data); if (!value) return; const id = Number(value.id || value.lead_id); if (!id) { ui.writeError.value = '已提交但未返回有效线索，请关闭后刷新列表核对，不要重复发布'; return }; publishRef.value?.clear(); markChanged(); uni.redirectTo({ url: cooperationTaskLink('select', { collaboration_id: id }) }) }
async function assign(store: CoopRow) {
    if (locked.value || !pageActive || ui.writeError.value || ui.detailError.value) return
    const lead = ui.detail.value, id = Number(lead.id), version = Number(lead.assignment_version), target = Number(store.id), assignee = Number(store.assignee_member_id || 0), actor = ui.sessionScope(), replace = lead.status === 'assigned'
    if (!id || !target || replace && lead.can_reassign !== true) return
    if (!replace && needsMembershipNow('can_publish')) { openMembership(); return }
    if (replace) {
        confirming.value = true
        const confirmed: any = await new Promise(resolve => {
            uni.showModal({
                title: '确认更换交付门店',
                content: `将本条线索从“${lead.receiver?.name || '原门店'}”更换给“${store.name || '所选门店'}”。确认前仍保留原交付；如对方已接单，本次更换不会生效。`,
                confirmText: '确认更换',
                cancelText: '暂不更换',
                success: resolve,
                fail() { resolve({ confirm: false }) }
            })
        })
        confirming.value = false
        if (!confirmed.confirm || disposed || !pageActive || actor !== ui.sessionScope() || id !== Number(ui.detail.value.id) || version !== Number(ui.detail.value.assignment_version) || ui.detail.value.can_reassign !== true) return
    }
    const value = await ui.assign(id, target, version, assignee, replace)
    if (!value || disposed || !pageActive || actor !== ui.sessionScope()) {
        if (ui.writeError.value) uni.showToast({ title: ui.writeError.value, icon: 'none' })
        return
    }
    if (Number(value.id) !== id || value.status !== 'assigned' || Number(value.receiver_id) !== target) {
        ui.writeError.value = '交付结果尚未完整核实，请刷新当前交付，暂不重复提交'
        return
    }
    ui.detail.value = value
    markChanged(); closeOverlay()
    uni.showToast({ title: replace ? '已更换门店，等待对方确认' : '已交付，等待对方确认', icon: 'none' })
}
async function act(value: LeadAction) {
    if (locked.value || !ui.detail.value.id) return
    const internal = ui.detail.value.is_internal === true
    if (value === 'accept' && !internal && needsMembershipNow('can_accept')) { openMembership(); return }
    if (!internal && ['accept', 'deal'].includes(value) && !leadFeeSnapshot(ui.detail.value).known) { ui.writeError.value = '本单收费快照尚未核实，请刷新详情后再确认'; return }
    const minimum = coopMoney(ui.detail.value.commission_minimum)
    const messages: Record<string, string> = { accept: `接单后由你联系客户，成交后最低支付 ${minimum} 协作佣金，未成交不收协作佣金。确认承接？`, reject: '本次交付将退回发布方重新选择门店。', withdraw: '撤回后，对方将不能再接受这次交付，可重新选店。', cancel: '取消这条尚未交付的线索？历史记录会保留。', deal: `确认已与客户成交？按本单发布时规则，最低支付 ${minimum} 协作佣金。`, no_deal: '确认本次未成交？将结束本次交付，平台可能进行客户回访。' }
    if (internal) { messages.accept='接单后，由本店成员联系客户并处理。不收取协作费，确认接单？'; messages.deal='确认本店已完成交付？本店内部处理，不收取协作费。' }
    confirming.value = true
    const result: any = await new Promise(resolve => uni.showModal({ title: value === 'cancel' ? '取消线索' : value === 'deal' ? '确认成交' : '确认操作', content: messages[value], confirmText: value === 'cancel' ? '确认取消' : '确认', success: resolve, fail: () => resolve({ confirm: false }) }))
    confirming.value = false
    if (!result.confirm || disposed) return
    const changed = await ui.action(value); if (!changed) return
    markChanged()
    if (['reject', 'withdraw', 'no_deal', 'cancel'].includes(value)) { if (props.task) closeOverlay(); else await refreshBusinessData() }
    else { await reloadDetail(); if (value === 'deal' && !internal) openTask('commission', { collaboration_id: selectedId.value }) }
    uni.showToast({ title: value === 'cancel' ? '线索已取消' : value === 'accept' ? '已接单，请联系客户' : value === 'deal' ? internal ? '本店交付已完成' : '已确认成交，请支付佣金' : '操作已完成', icon: 'none' })
}
async function saveProfile(data: CoopRow) {
    const value = await ui.saveProfile(data)
    if (!value) return
    profileRef.value?.clearDraft?.(); markChanged(); await ui.bootstrap()
    const invitationMessage = typeof value.onboarding_inviter?.message === 'string' ? value.onboarding_inviter.message.trim() : ''
    uni.showToast({ title: invitationMessage || '门店资料已保存', icon: 'none', duration: invitationMessage ? 3000 : 1500 })
    if (ui.config.value.erp_visible !== false && data.provider && ui.profile.value.id && !ui.profile.value.connection?.connected) uni.redirectTo({ url: cooperationTaskLink('connection') })
    else if (props.task) closeOverlay()
}
async function changedConnection() { markChanged(); await ui.bootstrap() }
function rememberPayment(context: CooperationPaymentContext, id: number) {
    const scoped = storageKey(context), latest = storageKey()
    if (!scoped || !latest) return
    uni.setStorageSync(scoped, id)
    uni.setStorageSync(latest, id) // Last-pending pointer is only used by the main tabs.
}
function releasePayment(context: CooperationPaymentContext, id: number) {
    for (const key of [storageKey(context), storageKey()]) if (key && Number(uni.getStorageSync(key)) === id) uni.removeStorageSync(key)
    orderRequests.delete(`${context.purpose}:${context.leadId}`)
}
function paymentIdentityIssue() {
    if (!Number.isSafeInteger(ui.memberId.value) || ui.memberId.value <= 0) return '当前登录信息未能核实，请重新进入后再试；本次未发起付款'
    if (!ui.siteId()) return '当前页面未识别到所属站点，请重新进入后再试；本次未发起付款'
    return ''
}
function validatePaymentOrder(order: CoopRow, id: number, expected = expectedPaymentContext.value) {
    const identityIssue = paymentIdentityIssue()
    if (identityIssue) { paymentContextError.value = identityIssue; paymentMessage.value = identityIssue; ui.writeError.value = identityIssue; return null }
    const context = Number(order.id) === id ? cooperationOrderContext(order, ui.profile.value.id) : null
    if (!context || Number(order.member_id) !== ui.memberId.value || expected && !sameCooperationPaymentContext(context, expected)) {
        paymentContextError.value = '原付款单与当前门店、业务或工单不一致，未打开收银台。请返回原业务核对付款链接。'
        paymentMessage.value = paymentContextError.value
        ui.writeError.value = paymentContextError.value
        return null
    }
    paymentContextError.value = ''
    return context
}
async function startOrder(purpose: string, amount?: string) {
    if (locked.value || paymentChecking.value || paymentResuming.value || paymentContextError.value || paymentCompleted.value) return
    if (paymentId.value) { await resumePayment(); return }
    const leadId = purpose === 'commission' ? Number(ui.detail.value.id) : 0
    const context = cooperationPaymentContext(purpose, leadId)
    if (!context || expectedPaymentContext.value && !sameCooperationPaymentContext(context, expectedPaymentContext.value)) { ui.writeError.value = '付款用途或工单与当前页面不一致，请返回原业务重新打开'; return }
    if (purpose === 'commission' && ui.detail.value.status === 'paid') { overlay.value = 'detail'; return }
    if (!storageKey(context)) { ui.writeError.value = paymentIdentityIssue() || '付款信息已变化，请重新进入本单后再试；本次未发起付款'; return }
    const key = `${purpose}:${leadId}`
    if (!orderRequests.has(key)) orderRequests.set(key, coopRequestId())
    const value = await ui.order({ purpose, ...(leadId ? { lead_id: leadId, amount } : {}), request_id: orderRequests.get(key) })
    if (!value?.id) return
    const id = Number(value.id)
    paymentId.value = id
    // A returned ID is protected from duplicate creation even while its context needs investigation.
    const pendingKey = storageKey(context); if (pendingKey) uni.setStorageSync(pendingKey, id)
    if (!validatePaymentOrder(value, id, context)) return
    paymentOrder.value = value
    rememberPayment(context, id)
    paymentMessage.value = '支付尚未确认，请勿重复创建订单'
    if (value.status !== 'unpaid') { await verifyPayment(); return }
    await resumePayment()
}
function payCommission(amount: string) { return startOrder('commission', amount) }
async function resumePayment() {
    if (!paymentId.value || paymentChecking.value || paymentResuming.value) return
    paymentResuming.value = true
    try {
        if (!await verifyPayment()) return
        const id = paymentId.value
        if (disposed || !id || paymentContextError.value || Number(paymentOrder.value.id) !== id || paymentOrder.value.status !== 'unpaid') return
        const context = validatePaymentOrder(paymentOrder.value, id)
        if (!context) return
        if (!expectedPaymentContext.value) {
            openTask('commission', { collaboration_id: context.leadId, payment_order_id: id })
            return
        }
        payRef.value?.open('hsx_lead_cooperation', id, cashierReturnUrl.value)
    } finally { paymentResuming.value = false }
}
async function verifyPayment() {
    if (!paymentId.value || paymentChecking.value || !getToken()) return false
    paymentChecking.value = true
    const who = ui.sessionScope(), id = paymentId.value
    try {
        let target = id, previous: CoopRow | null = null, order: CoopRow = {}, context: CooperationPaymentContext | null = null; const seen = new Set<number>()
        const current = () => !disposed && pageActive && who === ui.sessionScope() && id === paymentId.value
        for (let hop = 0; hop < 6; hop++) {
            if (seen.has(target)) throw new Error('付佣订单替换关系异常，请查看付款单核对'); seen.add(target)
            const response: any = await cooperationOrder(target)
            if (!current()) return false
            order = response.data || {}; context = validatePaymentOrder(order, target)
            if (!context) return false
            if (previous && !cooperationPaymentReplacementMatches(previous, order)) throw new Error('调整后的付款单与原门店、工单或付款人不一致，未打开收银台')
            if (order.requires_manual_review || order.status === 'paid') break
            const loaded = await ui.loadDetail(context.leadId)
            if (!current()) return false
            if (!loaded || Number(ui.detail.value.id) !== context.leadId) throw new Error(ui.detailError.value || '未能读取当前工单，暂不继续支付')
            let replacement = cooperationPaymentReplacementId(order)
            // The current lead pointer plus the replacement's reverse link can
            // prove the new trade even if an older response lacked its forward pointer.
            if (order.status === 'closed' && !replacement) {
                const linked = Number(ui.detail.value.payment_order_id)
                if (Number.isSafeInteger(linked) && linked > 0 && linked !== target) replacement = linked
            }
            if (replacement) { previous = { ...order, replacement_order_id: replacement }; target = replacement; if (hop === 5) throw new Error('付款单多次调整，请查看付款单核对最新状态'); continue }
            if (order.status === 'unpaid' && (Number(ui.detail.value.payment_order_id) !== target || ui.detail.value.status !== 'unpaid')) throw new Error('工单的当前付款单或成交状态已变化，未打开原收银台，请刷新核对')
            break
        }
        if (!context || !current()) return false
        paymentOrder.value = order
        if (target !== id) { releasePayment(context, id); paymentId.value = target }
        rememberPayment(context, target)
        if (order.status === 'paid') {
            if (order.requires_manual_review) {
                paymentMessage.value = order.review_message || '款项已收到，但协作佣金入账待核对，请联系平台，勿重复支付'
                await refreshBusinessData()
                if (reviewNotifiedId !== target) { reviewNotifiedId = target; uni.showModal({ title: '已收款，需平台核对', content: paymentMessage.value, showCancel: false }) }
                return false
            }
            paymentMessage.value = '支付已确认'
            paymentCompleted.value = true
            releasePayment(context, target)
            paymentId.value = 0
            markChanged()
            await refreshBusinessData()
            if (selectedId.value) await reloadDetail()
            if (props.task === 'commission') overlay.value = 'detail'
            uni.showToast({ title: '支付已确认，业务状态已更新', icon: 'none' })
        } else if (order.status === 'closed') {
            paymentCompleted.value = false
            paymentMessage.value = '原支付单已关闭。请到“我的付款单”核对是否有调整后的订单，不会按原金额重新下单。'
            if (props.task === 'commission') ui.writeError.value = paymentMessage.value
        } else paymentMessage.value = order.status === 'unpaid' ? previous ? '平台已调整付佣金额，请核对新付款单后继续支付。' : '原单尚未确认支付，可刷新核对' : '原单状态尚未核实，保留原单，请稍后核对，勿重复付款'
        return order.status === 'unpaid' && !order.requires_manual_review && order.can_pay === true
    } catch (cause) { if (!disposed && who === ui.sessionScope() && id === paymentId.value) { paymentMessage.value = coopError(cause, '支付结果暂未确认，请稍后刷新，勿重复付款'); ui.writeError.value = paymentMessage.value }; return false }
    finally { paymentChecking.value = false }
}
const subscribeState = useCooperationSubscribe()
function subscribe() { return subscribeState.request() }
watch(() => props.initialId, id => { if (Number.isSafeInteger(id) && id > 0 && id <= 4294967295) { pendingEntryReady.value = false; canonicalPending.value = true; pendingId = id; processLeadIntent() } })
watch(() => props.initialDirection, direction => { if (props.task || ui.tab.value !== 'deliveries' || direction === ui.direction.value) return; if (ui.ready.value && ui.profile.value.id) changeLeadDirection(direction); else ui.direction.value = direction })
watch(() => [props.initialPromotion, props.initialInviter] as const, ([show, mid]) => { if (!show) return; pendingPromotion = true; pendingInviter = Number(mid) || 0; void processEntryIntents() })
watch(locked, value => { if (!value) void processEntryIntents() })
onMounted(initialize)
function pausePage() { pendingEntryReady.value = false; countdownActive.value = false; if (pendingLeadPaging) interruptedLeadPaging = { ...pendingLeadPaging, readingList: ui.listLoading.value }; pendingLeadPaging = null; leadPagingPlanned = false; visitorLoading.value = false; pageActive = false; leadPagingEpoch++; resumeSequence++; ui.pause(); starting = null }
defineExpose({ resume: initialize, pause: pausePage, refresh, loadMore, openInvitation })
onBeforeUnmount(() => { pendingEntryReady.value = false; countdownActive.value = false; disposed = true; ui.dispose(); orderRequests.clear() })
</script>
<style lang="scss" scoped>@import './cooperation';.coop-onboarding-existing { margin:10px 0 0;line-height:44px;font-size:14px;color:#6685ae;background:transparent; }.coop-onboarding-existing::after { border:0; }
.coop-cashier-help { display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;width:100%;margin:8px 0 0;padding:8px 12px;border:1px solid #eedab7;border-radius:10px;background:#fff8e9;line-height:1.5;color:#765321;font-size:13px;box-sizing:border-box; }
.coop-cashier-help>text:last-child { font-weight:600; }.coop-cashier-help::after { border:0; }
</style>
