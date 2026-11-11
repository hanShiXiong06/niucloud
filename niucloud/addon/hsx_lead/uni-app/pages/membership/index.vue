<template>
    <view class="membership-page" :style="themeColor()">
        <CoopTaskSheet page :show="true" title="门店会员" :busy="state.busy.value" full @close="backCooperationTask">
            <CoopState v-if="state.loading.value && !state.ready.value" loading />
            <view v-else-if="state.error.value" class="membership-content"><CoopState :error="state.error.value" @retry="refresh" /></view>
            <view v-else-if="state.ready.value" class="membership-content">
                <CoopPaymentHelp :order="order" :spacing="12" />
                <view class="membership-current" :style="currentSkinStyle">
                    <view class="membership-current__hero">
                        <image v-if="currentSkin.cardImage" class="membership-card-art" :src="img(currentSkin.cardImage)" mode="aspectFill" aria-hidden="true" />
                        <view class="membership-current__head"><view class="membership-current__identity"><text class="membership-eyebrow">当前门店会员</text><text class="membership-current__title">{{ summary.level_name || '普通会员' }}</text><text class="membership-current__expiry">{{ currentMembershipCaption }}</text></view><CoopLevelBadge v-if="membershipBadgeRank(summary)>0" :level="membershipBadgeRank(summary)" :appearance="summary.level_visual" :state="depositFrozen || cooperationExited ? 'locked' : membershipState(summary)" variant="emblem" size="small" /></view>
                    </view>
                    <view v-if="summary.experience && showExperienceGrowth" class="membership-current__growth"><CoopExperienceProgress :experience="summary.experience" :next-level="summary.next_level" :maxed="experienceMaxed" :color="summary.level_color" /></view>
                </view>
                <view v-if="summary.cost_review_required" class="membership-error" role="alert"><text>{{ summary.cost_review_message || '存在尚未核对的付款，请联系平台核实原单，暂不重复付款或申请退押金。' }}</text><button class="membership-refresh-payment" :disabled="state.loading.value || state.busy.value || checking || quoting || recovering || depositConfirming" @tap="refresh">重新核对付款与权益</button></view>
                <view class="membership-seats" @tap="openTeam"><view><text class="membership-seats__label">门店账号</text><text class="membership-seats__count">{{ summary.used_accounts ?? '—' }}<text> / {{ entityAccounts(summary) }}</text></text></view><view class="membership-seats__manage"><text>{{ summary.can_manage ? '管理成员' : '查看成员' }}</text><u-icon name="arrow-right" size="13" color="#7b91ae" /></view></view>
                <view class="membership-deposit"><view><text>已缴押金（未退）</text><text>{{ coopMoney(summary.deposit_balance) }}</text></view><u-collapse :border="false"><u-collapse-item title="押金如何退回？" name="deposit"><text>不再合作时可联系平台申请退回，具体可退金额与处理结果需经平台核对。这里展示已缴且尚未退回的押金，不代表已获退款批准。</text></u-collapse-item></u-collapse></view>
                <view class="membership-heading"><text>选择会员等级</text><text class="membership-heading__hint">左右滑动查看</text></view>
                <swiper v-if="tiers.length" class="membership-swiper" :current="selectedIndex" :disable-touch="paymentLocked || state.busy.value" previous-margin="12rpx" next-margin="36rpx" @change="selectIndex(Number($event.detail.current))">
                    <swiper-item v-for="tier in tiers" :key="tier.code">
                        <view class="membership-tier" :style="tierStyle(tier)" :class="{ 'is-selected': selected === tier.code, 'is-disabled': !membershipCycles(tier).length }">
                            <image v-if="tierSkin(tier).cardImage" class="membership-card-art" :src="img(tierSkin(tier).cardImage)" mode="aspectFill" aria-hidden="true" />
                            <view class="membership-tier__content">
                                <view class="membership-tier__top"><view><text class="membership-tier__name">{{ tier.name }}</text></view><text class="membership-tier__state">{{ summary.level_code === tier.code ? currentTierStatus : !membershipCycles(tier).length ? '暂未开放' : '未开通' }}</text></view>
                                <view v-if="cycleFor(tier)" class="membership-tier__price"><text>{{ coopMoney(cycleFor(tier)?.price) }}</text><text>服务费 / {{ cycleFor(tier)?.name }}</text></view><view v-else class="membership-tier__unavailable">暂未开放购买</view>
                                <view class="membership-tier__accounts">{{ entityAccounts(tier) }}共同使用 · 包含负责人</view><view v-if="!experienceGateEnabled(tier)" class="membership-tier__experience">无需经验门槛</view><view v-else-if="tier.is_entry" class="membership-tier__experience">入门等级 · 无成交经验门槛</view><view v-else-if="tier.experience_required !== undefined" class="membership-tier__experience" :class="{ 'is-met': tier.experience_eligible === true }"><u-icon :name="tier.experience_eligible === true ? 'checkmark-circle' : 'lock'" size="12" :color="tierSkin(tier).text" /><text>{{ tier.experience_eligible === false ? '还差 ' + experienceText(tier.experience_remaining) + ' 经验' : '需 ' + experienceText(tier.experience_required) + ' 经验' }}</text></view>
                            </view>
                        </view>
                    </swiper-item>
                </swiper>
                <CoopState v-else title="暂未开放会员等级" hint="请联系平台核对配置，当前不会创建付款单" />
                <view v-if="cycles.length > 1" class="membership-cycle"><button v-for="period in cycles" :key="period.key" :class="{ 'is-active': cycle === period.key }" :disabled="paymentLocked || state.busy.value" @tap="selectCycle(period.key)">{{ period.name }}</button></view>
                <view v-if="experienceOffer && summary.can_manage && !order.id" class="membership-experience-offer"><view class="membership-experience-offer__row"><view><text>补足当前等级经验</text><text>还差 {{ experienceText(chosen?.experience_remaining) }} 经验 · {{ coopMoney(chosen?.experience_unit_price) }}/经验</text></view><u-switch :model-value="buyExperience" :disabled="quoting || state.busy.value || recovering" @change="selectExperience" /></view><view class="membership-experience-offer__hint">自主选择，不默认购买；补足经验不计入实际成交与交付单数。</view></view>
                <view v-if="displayBenefits.length" class="membership-section"><view class="membership-section__title">{{ chosen?.name || summary.level_name }} 权益</view><view v-for="benefit in displayBenefits" :key="benefit.key" class="membership-right"><CoopBusinessIcon :name="benefitIcon(benefit.key)" :disabled="!benefit.enabled" size="small" /><view><text>{{ benefit.title }}</text><text>{{ benefit.desc }}</text></view><text v-if="!benefit.enabled" class="membership-right__excluded">不包含</text></view></view>
                <view v-if="chosen && summary.can_manage && !order.id" class="membership-section"><view class="membership-section__title">费用核对</view><CoopState v-if="quoting" loading /><template v-else-if="quote.can_purchase && displayedCosts"><view class="membership-money-row"><text>本次押金</text><text>{{ coopMoney(displayedCosts.deposit) }}</text></view><view class="membership-money-row"><text>本期服务费</text><text>{{ coopMoney(displayedCosts.service) }}</text></view><view v-if="Number(quote.service_credit_amount) > 0" class="membership-cost-credit">原价 {{ coopMoney(quote.service_full_amount) }}，已抵扣 {{ coopMoney(quote.service_credit_amount) }}</view><view v-if="displayedCosts.bought || Number(displayedCosts.experience) > 0" class="membership-money-row"><text>补足经验<text v-if="displayedCosts.bought">（{{ experienceText(displayedCosts.quantity) }}）</text></text><text>{{ coopMoney(displayedCosts.experience) }}</text></view><view class="membership-money-row membership-money-row--total"><text>合计实付</text><text class="membership-money-row__total">{{ coopMoney(displayedCosts.total) }}</text></view><view class="membership-quote-period">{{ entityExpiry(quote.period_start_at) }} 至 {{ entityExpiry(quote.period_end_at) }}</view></template><view v-else class="membership-muted">{{ quoteError || quote.reason || '正在核对当前套餐费用' }}</view></view>
                <view v-if="quoteError && !order.id" class="membership-error" role="alert">{{ quoteError }}<button class="membership-refresh-payment" :disabled="quoting || state.busy.value" @tap="loadQuote">重新核对费用</button></view>
                <view v-if="order.id" class="membership-section"><view class="membership-section__title">原付款单</view><view class="membership-money-row"><text>{{ order.level_name || order.level_code || '会员开通' }}</text><text>{{ coopMoney(order.amount) }}</text></view><view v-if="displayedCosts" class="membership-order-costs"><view class="membership-money-row"><text>本次押金</text><text>{{ coopMoney(displayedCosts.deposit) }}</text></view><view class="membership-money-row"><text>服务费（抵扣后）</text><text>{{ coopMoney(displayedCosts.service) }}</text></view><view v-if="displayedCosts.bought || Number(displayedCosts.experience) > 0" class="membership-money-row"><text>补足经验<text v-if="displayedCosts.bought">（{{ experienceText(displayedCosts.quantity) }}）</text></text><text>{{ coopMoney(displayedCosts.experience) }}</text></view></view><view class="membership-payment-message" :class="{ 'is-error': !!order.requires_manual_review || orderReadFailed }" role="status">{{ paymentMessage || '请核对原单支付结果，不要重复创建付款。' }}</view><button class="membership-refresh-payment" :disabled="checking || recovering || state.busy.value" @tap="retryPaymentOrder">{{ checking || recovering ? '核对中…' : orderReadFailed ? '重新核对付款单' : '刷新付款结果' }}</button><button v-if="order.status === 'unpaid' && summary.can_manage && !orderReadFailed" class="membership-cancel-payment" :disabled="checking || recovering || state.busy.value || closing" @tap="closeOrder">取消待支付订单</button></view>
                <view v-if="state.writeError.value" class="membership-error" role="alert">{{ state.writeError.value }}</view>
                <view class="membership-section membership-deposit-exit"><u-collapse :border="false" @change="toggleDeposit"><u-collapse-item title="退出与押金" name="exit"><view class="membership-muted">申请后暂停新的接单和发单。仅申请退回押金，服务费及补足经验费用不退；实际退款需平台审核与支付渠道确认。</view><CoopState v-if="depositLoading && !depositLoaded" loading /><template v-if="depositLoaded"><view class="membership-money-row"><text>已缴押金（未退）</text><text>{{ coopMoney(depositInfo.balance) }}</text></view><view v-if="visibleDepositRequest" class="membership-deposit-status"><text>{{ visibleDepositRequest.status_name || '状态待核对' }}</text><text>申请金额 {{ coopMoney(visibleDepositRequest.amount) }}</text><text v-if="visibleDepositRequest.review_reason">{{ visibleDepositRequest.review_reason }}</text><view v-if="visibleDepositRequest.sources?.length" class="membership-refund-sources"><view v-for="source in visibleDepositRequest.sources" :key="source.order_id"><text>{{ coopMoney(source.amount) }}</text><text>{{ source.status_label || source.status_name || '待核对' }}</text></view></view></view><view v-if="depositInfo.request && !visibleDepositRequest" class="membership-muted">押金状态已更新，请刷新明细核对。</view><view v-if="depositInfo.blocking_reasons?.length" class="membership-deposit-blocking"><view v-for="reason in depositInfo.blocking_reasons" :key="reason.key">{{ reason.title }}<text v-if="Number(reason.count)>0"> · {{ reason.count }} 项</text></view></view><template v-if="depositInfo.is_owner && depositInfo.can_apply && !depositUncertain"><u-textarea v-model="depositReason" maxlength="200" placeholder="申请说明（选填）" :disabled="state.busy.value || depositConfirming" /><button class="membership-deposit-apply" :disabled="summary.cost_review_required === true || state.busy.value || depositLoading || depositConfirming || !!depositError" @tap="applyDeposit">{{ depositConfirming ? '处理中…' : '申请退出并退回押金' }}</button></template><view v-if="!depositInfo.is_owner" class="membership-muted">由门店负责人申请与核对</view><view v-if="cooperationExited" class="membership-muted">已退出协作，可重新开通；历史记录保留</view></template><view v-if="depositUncertain" class="membership-error">申请结果尚未确认，请先刷新核对，不要重复申请。</view><view v-if="depositError" class="membership-error">{{ depositError }}</view><button class="membership-refresh-payment" :disabled="depositLoading || state.busy.value || depositConfirming" @tap="loadDeposit">{{ depositLoading ? '核对中…' : '刷新押金状态' }}</button></u-collapse-item></u-collapse></view>
                <view class="membership-section membership-help"><u-collapse :border="false"><u-collapse-item v-if="showExperienceGrowth" title="经验怎么累计、怎么升级？" name="experience"><view>已确认的协作佣金按每实付 1 元累计 1 点真实付佣经验，退款后相应扣回。仅设置经验门槛的等级需在开通、升级、续费时核验；无门槛的等级可直接付费开通。平台允许时可自选补足经验，不代表实际交付；押金与服务费不计入经验。</view></u-collapse-item><u-collapse-item title="账号与会员如何共用？" name="account"><view>会员归属于当前门店，不需要每位同事重复购买。总账号数包含负责人；同事加入需负责人确认。</view></u-collapse-item><u-collapse-item title="到期或人数超过上限怎么办？" name="expiry"><view>到期不会删除成员或历史业务。新增成员和新业务按当前有效权益控制，已有记录仍可核对处理。</view></u-collapse-item></u-collapse></view>
            </view>
            <template v-if="state.ready.value" #footer><view v-if="!summary.can_manage" class="membership-footer-note">会员由门店负责人统一开通或续费</view><view v-else><view v-if="paymentLocked" class="membership-footer-note">{{ orderReadFailed ? '原付款单已保留，重试核对不会重复下单' : order.requires_manual_review ? '款项已收到，等待平台核对权益' : order.quote_expired ? '先自动核对上次付款，再更新套餐费用' : '继续使用原付款单，不重复创建开通订单' }}</view><u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" :text="mainLabel" :loading="state.busy.value || checking || quoting || recovering" :disabled="summary.cost_review_required === true || state.busy.value || checking || quoting || recovering || depositConfirming || closing || (depositFrozen && !order.id) || (!chosen && !order.id) || (!order.id && !quote.can_purchase) || !!order.requires_manual_review" @click="submitMembershipPayment" /></view></template>
        </CoopTaskSheet>
        <view class="membership-cashier"><Pay ref="payRef" :ignore-pay="['friendspay']" :reformat="cashierReturn" @confirm="verifyPayment" @close="verifyPayment" /></view>
    </view>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { onHide, onLoad, onShow } from '@dcloudio/uni-app'
import Pay from '@/components/pay/pay.vue'
import { img } from '@/utils/common'
import CoopTaskSheet from '../../components/cooperation/CoopTaskSheet.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import CoopLevelBadge from '../../components/cooperation/CoopLevelBadge.vue'
import CoopBusinessIcon from '../../components/cooperation/CoopBusinessIcon.vue'
import CoopExperienceProgress from '../../components/cooperation/CoopExperienceProgress.vue'
import CoopPaymentHelp from '../../components/cooperation/CoopPaymentHelp.vue'
import { entityCloseOrder, entityMembership, entityQuote, entityDeposit, entityApplyDepositRefund } from '../../api/entity'
import { resumeCooperationOrder, createCooperationOrder, coopError, coopMoney, coopRequestId, type CoopRow } from '../../api/cooperation'
import { useEntityPage } from '../../composables/useEntityPage'
import { entityAccounts, entityExpiry, entityLevel, entityPageUrl, membershipPageUrl, membershipState, membershipCycles, membershipCycleKey, membershipBadgeRank, membershipBenefits, experienceText, experienceGateEnabled, membershipCosts, quoteMatches } from '../../utils/entity-membership'
import { backCooperationTask } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { membershipSkin, membershipSkinVars } from '../../utils/membership-skin'
import { cooperationPaymentReplacementId, cooperationPaymentReplacementMatches } from '../../utils/cooperation-payment-orders'
const state = useEntityPage(entityMembership), summary = state.data, selected = ref(''), cycle = ref(''), buyExperience = ref(false), quote = ref<CoopRow>({}), quoteError = ref(''), quoting = ref(false), order = ref<CoopRow>({}), paymentMessage = ref(''), checking = ref(false), closing = ref(false), recovering = ref(false), orderCheckError = ref(''), orderCheckKind = ref(''), payRef = ref<any>(null)
const orderReadFailed = computed(() => !!order.value.id && !!orderCheckError.value)
const tiers = computed<CoopRow[]>(() => (Array.isArray(summary.value.tiers) ? summary.value.tiers : []).filter((tier: CoopRow) => !!entityLevel(tier.code))), chosen = computed(() => tiers.value.find(tier => tier.code === selected.value)), selectedIndex = computed(() => Math.max(0, tiers.value.findIndex(tier => tier.code === selected.value))), paymentLocked = computed(() => !!order.value.id && (orderReadFailed.value || !!order.value.requires_manual_review || !['closed', 'refunded', 'paid'].includes(String(order.value.status)))), cashierReturn = computed(() => `${membershipPageUrl}?payment_order_id=${Number(order.value.id || 0)}`)
const mainLabel = computed(() => orderReadFailed.value ? '重新核对付款单' : order.value.requires_manual_review ? '已收款，需平台核对' : summary.value.cost_review_required ? '款项待核对' : order.value.membership_refund_status === 'refunded' || order.value.status === 'refunded' ? '退款已确认，返回' : order.value.status === 'closed' ? '重新核对套餐费用' : order.value.id && order.value.status === 'unpaid' ? order.value.can_pay === true ? '继续原单付款' : '核对并继续付款' : order.value.status === 'paid' ? '支付已确认，返回' : depositFrozen.value ? '协作暂停，请核对押金申请' : experienceGateEnabled(chosen.value) && chosen.value?.experience_eligible === false && !quote.value.can_purchase ? '经验未达标' : cooperationExited.value ? '重新开通会员' : summary.value.level_code === selected.value && summary.value.active ? '确认续费' : summary.value.active && summary.value.level_code ? '确认补差升级' : '确认开通')
const cycles = computed(() => membershipCycles(chosen.value)), displayBenefits = computed(() => membershipBenefits(chosen.value || summary.value))
const showExperienceGrowth = computed(() => !tiers.value.length || tiers.value.some(tier => experienceGateEnabled(tier)))
const experienceOffer = computed(() => experienceGateEnabled(chosen.value) && chosen.value?.experience_purchase_enabled === true && Number(chosen.value?.experience_remaining) > 0)
const displayedCosts = computed(() => membershipCosts(order.value.id ? order.value : quote.value))
const depositInfo = ref<CoopRow>({}), depositLoading = ref(false), depositLoaded = ref(false), depositError = ref(''), depositExpanded = ref(false), depositConfirming = ref(false), depositUncertain = ref(false), depositReason = ref('')
const depositRequestStatus = computed(() => typeof summary.value.deposit_request_status === 'string' ? summary.value.deposit_request_status : String(depositInfo.value.request?.status || ''))
// A completed exit is historical state, not an unresolved refund. The fresh
// server summary takes precedence over a collapsed panel's cached request.
const depositFrozen = computed(() => depositUncertain.value || (typeof summary.value.deposit_purchase_blocked === 'boolean' ? summary.value.deposit_purchase_blocked : ['pending','approved','refunding','attention'].includes(depositRequestStatus.value)))
const cooperationExited = computed(() => !depositFrozen.value && (typeof summary.value.cooperation_exited === 'boolean' ? summary.value.cooperation_exited : depositInfo.value.cooperation_exited === true || depositRequestStatus.value === 'refunded'))
const visibleDepositRequest = computed(() => typeof summary.value.deposit_request_status === 'string' && summary.value.deposit_request_status !== String(depositInfo.value.request?.status || '') ? null : depositInfo.value.request)
const currentMembershipCaption = computed(() => { if (depositFrozen.value) { const status = depositRequestStatus.value; return status === 'pending' ? '退押金申请审核中' : ['approved','refunding'].includes(status) ? '押金退回处理中' : status === 'attention' ? '押金处理需平台核对' : '协作已暂停' }; if (summary.value.cost_review_required) return '原付费权益需平台核对'; if (cooperationExited.value) return '已退出协作，可重新开通'; return summary.value.active ? '有效期至 ' + entityExpiry(summary.value.expires_at) : summary.value.level_code ? '权益已到期' : '尚未开通' })
const currentTierStatus = computed(() => depositFrozen.value ? '协作暂停' : summary.value.cost_review_required ? '待核对' : cooperationExited.value ? '已退出' : summary.value.active ? '使用中' : '已到期')
const experienceMaxed = computed(() => tiers.value.length > 0 && Number(summary.value.rank) > 0 && !tiers.value.some(tier => Number(tier.rank) > Number(summary.value.rank)))
const currentSkin = computed(() => membershipSkin(summary.value.active === true ? summary.value.level_visual : {}, summary.value.active === true ? membershipBadgeRank(summary.value) : 0))
const currentSkinStyle = computed(() => membershipSkinVars(summary.value.active === true ? summary.value.level_visual : {}, summary.value.active === true ? membershipBadgeRank(summary.value) : 0))
function tierSkin(tier: CoopRow) { return membershipSkin(tier.level_visual, membershipBadgeRank(tier)) }
function tierStyle(tier: CoopRow) { return membershipSkinVars(tier.level_visual, membershipBadgeRank(tier)) }
let pageActive = true, viewEpoch = 0, paymentEpoch = 0, recoveryEpoch = 0, quoteEpoch = 0, incomingOrder = 0, requestId = '', requestKey = '', identity = '', depositEpoch = 0, depositRequestId = ''
function invalidatePaymentReads() { paymentEpoch++; recoveryEpoch++; checking.value = false; recovering.value = false }
onLoad(options => { const id = Number(options?.payment_order_id || 0); incomingOrder = Number.isSafeInteger(id) && id > 0 ? id : 0 })
onShow(() => { pageActive = true; void refresh() })
function pauseMembership() { pageActive = false; viewEpoch++; invalidatePaymentReads(); quoteEpoch++; depositEpoch++; quoting.value = false; depositLoading.value = false; state.pause() }
onHide(pauseMembership)
onBeforeUnmount(pauseMembership)
function cycleFor(tier: CoopRow) { const available = membershipCycles(tier); return available.find((period: CoopRow) => period.key === cycle.value) || available[0] }
function benefitIcon(key: string): 'store' | 'customer' | 'stock' | 'member' { return ({ can_publish:'store',can_accept:'customer',can_view_inventory:'stock',all_models:'member' } as const)[key] || 'member' }
function ensureCycle() { if (!cycles.value.some((period: CoopRow) => period.key === cycle.value)) cycle.value = cycles.value[0]?.key || '' }
async function refresh() {
    const turn = ++viewEpoch, who = state.identity()
    invalidatePaymentReads(); quoteEpoch++; quoting.value = false
    if (who !== identity) { if (identity) incomingOrder = 0; identity = who; order.value = {}; quote.value = {}; buyExperience.value = false; requestId = ''; requestKey = ''; paymentMessage.value = ''; orderCheckError.value = ''; orderCheckKind.value = ''; depositEpoch++; depositInfo.value = {}; depositLoaded.value = false; depositError.value = ''; depositUncertain.value = false; depositReason.value = ''; depositRequestId = '' }
    const ok = await state.refresh()
    if (!ok || !pageActive || turn !== viewEpoch || !state.current(who)) return
    if (typeof summary.value.deposit_purchase_blocked === 'boolean' && typeof summary.value.deposit_request_status === 'string') depositUncertain.value = false
    if (depositExpanded.value) void loadDeposit()
    if (!selected.value || !tiers.value.some(tier => tier.code === selected.value)) selected.value = entityLevel(summary.value.level_code) || tiers.value[0]?.code || ''
    if (!tiers.value.some(tier => tier.code === selected.value)) selected.value = tiers.value[0]?.code || ''
    ensureCycle()
    if (!experienceOffer.value && !order.value.id) buyExperience.value = false
    if (incomingOrder) { const id = incomingOrder; incomingOrder = 0; order.value = { id }; await verifyPayment(); return }
    if (order.value.id) { await verifyPayment(); return }
    await loadQuote()
}
function selectIndex(index: number) { const tier = tiers.value[index]; if (!tier || selected.value === tier.code) return; if (paymentLocked.value || state.busy.value || recovering.value) { uni.showToast({ title: '请先核对原付款单', icon: 'none' }); return }; selected.value = tier.code; buyExperience.value = false; order.value = {}; orderCheckError.value = ''; orderCheckKind.value = ''; ensureCycle(); void loadQuote() }
function selectCycle(value: string) { if (paymentLocked.value || state.busy.value || recovering.value || cycle.value === value || !cycles.value.some((period: CoopRow) => period.key === value)) return; cycle.value = value; order.value = {}; orderCheckError.value = ''; orderCheckKind.value = ''; void loadQuote() }
function selectExperience(value: unknown) { if (order.value.id || paymentLocked.value || state.busy.value || recovering.value || quoting.value || !experienceOffer.value || !summary.value.can_manage) return; buyExperience.value = value === true; void loadQuote() }
async function loadQuote(resumeExisting = true) {
    const turn = ++quoteEpoch, who = state.identity(), level = selected.value, period = cycle.value, supplement = buyExperience.value
    quote.value = {}; quoteError.value = ''
    if (!summary.value.can_manage || !level || !period) { quote.value = { can_purchase: false, reason: '当前等级暂未开放购买周期' }; return }
    quoting.value = true
    try {
        const response: any = await entityQuote(level, period, supplement)
        if (!pageActive || turn !== quoteEpoch || !state.current(who) || level !== selected.value || period !== cycle.value || supplement !== buyExperience.value) return
        const value = response?.data || {}
        if (Number(value.merchant_id) !== Number(summary.value.merchant_id) || value.purpose !== 'membership') throw new Error('报价与当前门店不一致，请重新核对')
        if (Number(value.existing_order_id) > 0) {
            order.value = { id: Number(value.existing_order_id), status: 'unpaid' }
            if (resumeExisting !== false) await verifyPayment()
            else paymentMessage.value = '已找到待支付订单，点击下方按钮继续核对。'
            return
        }
        if (value.can_purchase && !quoteMatches(value, Number(summary.value.merchant_id), level, period, supplement)) throw new Error('报价未完整返回或已失效，请重新核对')
        quote.value = value
    } catch (cause) { if (pageActive && turn === quoteEpoch && state.current(who)) quoteError.value = coopError(cause, '费用未能核对，请刷新重试，当前未创建付款单') }
    finally { if (turn === quoteEpoch) quoting.value = false }
}
function validateOrder(value: CoopRow, id: number) { if (!Number.isSafeInteger(id) || id <= 0 || Number(value.id) !== id || value.purpose !== 'membership' || Number(value.merchant_id) !== Number(summary.value.merchant_id) || !entityLevel(value.level_code) || !membershipCycleKey(value.cycle) || !Number.isSafeInteger(value.entitlement_months) || value.entitlement_months < 1 || value.entitlement_months > 120) throw new Error('原付款单与当前门店或会员业务不一致，未打开收银台'); if (value.status === 'unpaid' && !value.requires_manual_review && !value.quote_expired && (!membershipCosts(value) || String(value.amount) !== String(value.payable_amount))) throw new Error('原付款单的押金、服务费或经验费用尚未完整核对，未打开收银台'); return value }
async function verifyPayment() {
    const id = Number(order.value.id), who = state.identity(), view = viewEpoch, merchant = Number(summary.value.merchant_id)
    if (!id || checking.value || !merchant || !pageActive || !state.current(who)) return false
    const turn = ++paymentEpoch
    let ownedOrderId = id
    const current = () => pageActive && view === viewEpoch && turn === paymentEpoch && state.current(who) && merchant === Number(summary.value.merchant_id) && ownedOrderId === Number(order.value.id)
    checking.value = true
    try {
        let target = id, previous: CoopRow | null = null, value: CoopRow = {}; const seen = new Set<number>()
        for (let hop = 0; hop < 6; hop++) {
            if (seen.has(target)) throw new Error('会员付款单替换关系异常，请联系平台核对'); seen.add(target)
            const response: any = await resumeCooperationOrder(target)
            if (!current()) return false
            value = validateOrder(response?.data || {}, target)
            if (previous && !cooperationPaymentReplacementMatches(previous, value)) throw new Error('调整后会员单与原门店或套餐不一致，当前未打开收银台')
            const replacement = cooperationPaymentReplacementId(value)
            if (!replacement || value.requires_manual_review) break
            previous = value; target = replacement
            if (hop === 5) throw new Error('会员付款单多次调整，请联系平台核对最新订单')
        }
        ownedOrderId = Number(value.id); order.value = value; selected.value = value.level_code; cycle.value = value.cycle; buyExperience.value = value.buy_experience === true; orderCheckError.value = ''; orderCheckKind.value = ''
        if (value.requires_manual_review) { paymentMessage.value = value.review_message || '款项已收到，权益需平台核对，请勿重复付款'; return false }
        if (value.membership_refund_status === 'refunded') { paymentMessage.value = '原单已退款，请核对当前门店权益。'; await state.refresh(); return current() }
        if (value.status === 'paid') { paymentMessage.value = '支付已确认，门店权益已更新'; await state.refresh() }
        else if (value.status === 'unpaid') paymentMessage.value = value.payment_recovery?.message || value.close_message || (value.quote_expired ? '上次报价已过期，正在核对原支付。确认原单关闭后，会重新计算套餐费用。' : previous ? '平台已调整服务费，请核对下方新付款单及费用，再继续支付。' : '上次付款尚未完成，可继续当前订单付款。')
        else if (value.status === 'closed') {
            requestId = ''; requestKey = ''; await state.refresh()
            if (!current()) return false
            // Authoritative business closure releases the form, not a network
            // failure or a locally dismissed WeChat cashier. A fresh quote still
            // requires the customer's explicit confirmation before any new order.
            order.value = {}; paymentMessage.value = ''; quote.value = {}; quoteError.value = ''
            await loadQuote(false)
            return false
        }
        else if (value.status === 'refunded') { paymentMessage.value = '原单已退款，请核对当前门店权益。'; requestId = ''; requestKey = ''; await state.refresh() }
        else { paymentMessage.value = '原单状态暂未确认，请稍后重试核对，不会重复付款。'; orderCheckError.value = paymentMessage.value; orderCheckKind.value = 'unknown' }
        return current() && (value.status === 'paid' || (value.status === 'unpaid' && value.can_pay === true && !value.quote_expired))
    } catch (cause) { if (current()) { const message = coopError(cause, '连接未完成，请重新核对原付款单，勿重复付款'); orderCheckError.value = message; orderCheckKind.value = /记录不存在|付款订单不存在|不属于(?:本站|当前站点|当前会员)/.test(message) ? 'missing' : 'unknown'; paymentMessage.value = orderCheckKind.value === 'missing' ? message + '。请重新核对，系统不会直接重新下单。' : message }; return false }
    finally { if (view === viewEpoch && turn === paymentEpoch) checking.value = false }
}
async function retryPaymentOrder() {
    if (recovering.value || checking.value || quoting.value || state.busy.value || closing.value || !order.value.id) return
    recovering.value = true
    const id = Number(order.value.id), who = state.identity(), view = viewEpoch, recovery = ++recoveryEpoch
    const current = () => pageActive && view === viewEpoch && recovery === recoveryEpoch && state.current(who) && id === Number(order.value.id)
    try {
        await verifyPayment()
        // Network/unknown results retain the original order. Only an explicit
        // server "no pending order" response may release a missing-order stub.
        if (!current() || !orderReadFailed.value || orderCheckKind.value !== 'missing') return
        const level = selected.value, period = cycle.value, supplement = buyExperience.value, turn = ++quoteEpoch
        if (!summary.value.can_manage || !level || !period) return
        const response: any = await entityQuote(level, period, supplement)
        if (!current() || turn !== quoteEpoch || level !== selected.value || period !== cycle.value || supplement !== buyExperience.value) return
        const value = response?.data || {}
        if (value.purpose !== 'membership' || Number(value.merchant_id) !== Number(summary.value.merchant_id) || value.level_code !== level || value.cycle !== period || !Number.isSafeInteger(value.existing_order_id) || value.existing_order_id < 0) throw new Error('当前门店的原单状态未完整返回，请继续核对，不会重新下单')
        if (value.existing_order_id > 0) { if (value.existing_order_id !== id) order.value = { id: value.existing_order_id }; await verifyPayment(); return }
        if (value.can_purchase && !quoteMatches(value, Number(summary.value.merchant_id), level, period, supplement)) throw new Error('新费用尚未完整核对，当前不会创建付款单')
        order.value = {}; orderCheckError.value = ''; orderCheckKind.value = ''; paymentMessage.value = ''; requestId = ''; requestKey = ''; quote.value = value; quoteError.value = ''
        uni.showToast({ title: '已核对暂无待付款订单，请重新确认费用', icon: 'none' })
    } catch (cause) { if (current()) paymentMessage.value = coopError(cause, '当前门店付款状态仍未核实，请重试或联系平台，当前未创建新付款单') }
    finally { if (view === viewEpoch && recovery === recoveryEpoch) recovering.value = false }
}
async function submitMembershipPayment() {
    if (!summary.value.can_manage || summary.value.cost_review_required === true || state.busy.value || checking.value || quoting.value || recovering.value || depositConfirming.value || closing.value || order.value.requires_manual_review) return
    const view = viewEpoch, who = state.identity(), merchant = Number(summary.value.merchant_id)
    const current = () => pageActive && view === viewEpoch && state.current(who) && merchant === Number(summary.value.merchant_id)
    if (orderReadFailed.value) { await retryPaymentOrder(); return }
    if (depositFrozen.value && !order.value.id) return
    if (order.value.status === 'closed') { await verifyPayment(); return }
    if (order.value.id && (order.value.status === 'paid' || order.value.status === 'refunded' || order.value.membership_refund_status === 'refunded')) { backCooperationTask(); return }
    if (order.value.id && order.value.status === 'unpaid') { const verified = await verifyPayment(); if (verified && current() && order.value.status === 'unpaid' && !order.value.requires_manual_review && !order.value.quote_expired) payRef.value?.open('hsx_lead_cooperation', Number(order.value.id), cashierReturn.value); return }
    if (!quoteMatches(quote.value, Number(summary.value.merchant_id), selected.value, cycle.value, buyExperience.value)) { await loadQuote(); return }
    const snapshot = { ...quote.value }, key = `${summary.value.merchant_id}:${selected.value}:${cycle.value}:${snapshot.quote_token}`
    if (requestKey !== key) { requestKey = key; requestId = coopRequestId() }
    const value = await state.write(() => createCooperationOrder({ purpose: 'membership', level_code: selected.value, cycle: cycle.value, buy_experience: buyExperience.value, quote_token: snapshot.quote_token, request_id: requestId }))
    if (!value || !current()) return
    const id = Number(value.id)
    try { validateOrder(value, id) } catch (cause) { state.writeError.value = coopError(cause); return }
    order.value = value; paymentMessage.value = '付款单已建立，等待支付结果确认。'
    if (String(value.amount) !== String(snapshot.payable_amount) || value.buy_experience !== snapshot.buy_experience) { paymentMessage.value = '已找到原付款单，请核对原单金额与经验选项后继续，当前未打开收银台。'; return }
    if (value.status === 'paid') { await verifyPayment(); return }
    if (value.status === 'unpaid') {
        if (value.can_pay === true && !value.quote_expired) { payRef.value?.open('hsx_lead_cooperation', id, cashierReturn.value); return }
        const verified = await verifyPayment()
        if (verified && current() && order.value.status === 'unpaid' && order.value.can_pay === true) payRef.value?.open('hsx_lead_cooperation', Number(order.value.id), cashierReturn.value)
    }
}
async function closeOrder() {
    if (closing.value || checking.value || recovering.value || orderReadFailed.value || state.busy.value || order.value.status !== 'unpaid') return
    const who = state.identity(), id = Number(order.value.id), merchant = Number(summary.value.merchant_id), view = viewEpoch
    const current = () => pageActive && view === viewEpoch && state.current(who) && Number(summary.value.merchant_id) === merchant && Number(order.value.id) === id && order.value.status === 'unpaid' && !orderReadFailed.value
    if (!Number.isSafeInteger(id) || id <= 0 || !current()) return
    closing.value = true
    try {
        const confirmed = await new Promise<boolean>(resolve => uni.showModal({ title: order.value.close_requires_check ? '核对支付后取消？' : '取消待支付订单？', content: '仅取消这笔未支付的开通或续费订单，不影响现有有效权益。系统会先核对原支付，已收款不会取消，结果不明时保留原单。', confirmText: order.value.close_requires_check ? '核对后取消' : '确认取消', success: result => resolve(!!result.confirm), fail: () => resolve(false) }))
        if (!confirmed || !current()) return
        const result = await state.write(() => { if (!current()) throw new Error('原付款单或登录已变化，请重新核对，未执行取消'); return entityCloseOrder(id) })
        if (result && current()) {
            const value = validateOrder(result, id); order.value = value; requestId = ''; requestKey = ''
            paymentMessage.value = value.close_message || (value.status === 'closed' ? '付款单已取消，不会自动创建新付款单。' : value.status === 'paid' ? '原付款已确认，不能取消，请勿重复支付。' : '取消结果尚未确认，请刷新核对原单。')
            if (value.status === 'closed' && cooperationPaymentReplacementId(value)) await verifyPayment()
            else await state.refresh()
        }
    } finally { closing.value = false }
}
function checkedDeposit(value: CoopRow) {
    if (Number(value.merchant_id) !== Number(summary.value.merchant_id) || typeof value.is_owner !== 'boolean' || typeof value.can_apply !== 'boolean' || !/^\d+\.\d{2}$/.test(String(value.balance)) || !Array.isArray(value.blocking_reasons) || !(value.request === null || value.request && Number.isSafeInteger(value.request.id) && value.request.id > 0 && ['pending','approved','refunding','refunded','rejected','attention'].includes(value.request.status))) throw new Error('押金状态未完整返回，请刷新核对，当前不发起申请')
    return value
}
async function refreshDepositPurchaseGuard(current: () => boolean, resolveUncertainty = true) {
    depositUncertain.value = true
    const refreshed = await state.refresh()
    if (!current()) return
    depositUncertain.value = !refreshed || !resolveUncertainty
    if (!refreshed) depositError.value = state.error.value || '最新门店状态未能核对，请刷新后重试，暂不重复申请或付款'
}
function toggleDeposit(value: any) { depositExpanded.value = Array.isArray(value) && value.some((entry: any) => entry?.name === 'exit' && entry?.status === 'open'); if (depositExpanded.value) void loadDeposit() }
async function loadDeposit() {
    if (!pageActive || depositLoading.value || !state.ready.value || !summary.value.merchant_id) return
    const who = state.identity(), turn = ++depositEpoch, view = viewEpoch
    depositLoading.value = true; depositError.value = ''
    try {
        const response: any = await entityDeposit()
        if (!pageActive || view !== viewEpoch || !state.current(who) || turn !== depositEpoch) return
        const value = checkedDeposit(response?.data || {}), unresolvedApplication = depositUncertain.value && !value.request; depositInfo.value = value; depositLoaded.value = true
        if (value.request) { depositUncertain.value = false; if (value.request.status === 'rejected') depositRequestId = '' }
        // Refresh the authoritative purchase guard as well as the drawer.
        // Otherwise a newly submitted/pending request could retain an older
        // false guard, or a completed refund could retain an older true one.
        await refreshDepositPurchaseGuard(() => pageActive && view === viewEpoch && state.current(who) && turn === depositEpoch, !unresolvedApplication)
        if (pageActive && view === viewEpoch && state.current(who) && turn === depositEpoch && !depositFrozen.value && !order.value.id) await loadQuote()
    } catch (cause) { if (pageActive && view === viewEpoch && state.current(who) && turn === depositEpoch) depositError.value = coopError(cause, '押金状态未能核对，请重试，当前未提交申请') }
    finally { if (turn === depositEpoch) depositLoading.value = false }
}
async function applyDeposit() {
    if (!depositLoaded.value || summary.value.cost_review_required === true || !depositInfo.value.is_owner || !depositInfo.value.can_apply || depositUncertain.value || !!depositError.value || depositLoading.value || depositConfirming.value || state.busy.value || checking.value || recovering.value) return
    const who = state.identity(), merchant = Number(summary.value.merchant_id), view = viewEpoch
    const current = () => pageActive && view === viewEpoch && state.current(who) && Number(summary.value.merchant_id) === merchant && Number(depositInfo.value.merchant_id) === merchant
    if (!Number.isSafeInteger(merchant) || merchant <= 0 || !current()) return
    depositConfirming.value = true
    try {
        const confirmed = await new Promise<boolean>(resolve => uni.showModal({ title: '申请退出协作？', content: '提交后暂停新的接单和发单。仅申请退回押金，服务费及补足经验费用不退；需平台审核，到账以支付渠道确认结果为准。', confirmText: '确认申请', success: value => resolve(!!value.confirm), fail: () => resolve(false) }))
        if (!confirmed || !current() || summary.value.cost_review_required === true || !depositInfo.value.can_apply || !!depositError.value) return
        if (!depositRequestId) depositRequestId = coopRequestId()
        const value = await state.write(() => { if (!current()) throw new Error('当前门店已变化，请重新读取押金状态，未提交申请'); return entityApplyDepositRefund(depositRequestId, depositReason.value.trim()) })
        if (!current()) return
        if (!value) { depositUncertain.value = true; depositError.value = state.writeError.value; state.writeError.value = ''; await loadDeposit(); return }
        depositInfo.value = checkedDeposit(value); depositLoaded.value = true; depositUncertain.value = false; depositError.value = ''
        await refreshDepositPurchaseGuard(current)
    } catch (cause) { if (current()) { depositUncertain.value = true; depositError.value = coopError(cause, '申请结果尚未确认，请先刷新押金状态，不要重复提交') } }
    finally { depositConfirming.value = false }
}
function openTeam() { uni.navigateTo({ url: entityPageUrl }) }
</script>
<style scoped lang="scss">
.membership-page { height:calc(100vh - var(--window-top,0px) - var(--window-bottom,0px));min-height:0;display:flex;flex-direction:column;overflow:hidden;box-sizing:border-box;background:#f6f6f6;color:#202124; }
/* #ifdef H5 */
@supports(height:100dvh){.membership-page{height:calc(100dvh - var(--window-top,0px) - var(--window-bottom,0px));}}
/* #endif */
.membership-content { padding:12px 12px 24px; }
.membership-current { background:#fff;border-radius:14px;overflow:hidden; }
.membership-current__hero { position:relative;padding:20px;background:var(--lead-member-gradient);color:var(--lead-member-text);overflow:hidden; }
.membership-current__hero::after { content:'';position:absolute;width:170px;height:170px;right:-65px;top:-100px;border:28px solid rgba(255,255,255,.17);border-radius:50%;pointer-events:none; }
.membership-current__head { position:relative;display:flex;align-items:center;justify-content:space-between;gap:12px; }
.membership-card-art { position:absolute;top:0;left:0;width:100%;height:100%;opacity:.55;pointer-events:none; }
.membership-current__growth { padding:0 16px 16px; }
.membership-current__identity { min-width:0;flex:1; }
.membership-eyebrow { display:block;font-size:12px;line-height:18px;opacity:.72; }
.membership-current__title { display:block;font-size:21px;line-height:29px;font-weight:650;margin:5px 0;overflow-wrap:break-word; }
.membership-current__expiry { display:block;font-size:12px;line-height:20px;opacity:.78; }
.membership-seats { display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:56px;background:#fff;margin:8px 0;border-radius:8px;padding:12px 16px;box-sizing:border-box; }
.membership-deposit { background:#fff;border-radius:8px;margin:0 0 20px;padding:12px 16px 0; }
.membership-deposit>view:first-child { display:flex;justify-content:space-between;gap:12px;color:#777;font-size:13px;line-height:22px; }
.membership-deposit>view:first-child>text:last-child { color:#333;font-weight:600; }
.membership-deposit :deep(.u-collapse-item__content__text) { color:#777;font-size:12px;line-height:21px; }
.membership-experience-offer { background:#fff;border:1px solid #ededed;padding:14px 16px;border-radius:8px;margin:0 0 16px; }
.membership-experience-offer__row { display:flex;align-items:center;justify-content:space-between;gap:12px; }
.membership-experience-offer__row>view { min-width:0;flex:1; }
.membership-experience-offer__row text { display:block;font-size:12px;color:#777;line-height:20px; }
.membership-experience-offer__row text:first-child { font-size:14px;font-weight:500;color:#333;margin-bottom:3px; }
.membership-experience-offer__hint { margin-top:9px;color:#999;font-size:11px;line-height:18px; }
.membership-seats>view:first-child { display:flex;align-items:baseline;gap:14px;min-width:0; }
.membership-seats__label { font-size:13px;color:#555;line-height:20px; }
.membership-seats__count { font-size:22px;line-height:28px;font-weight:650;white-space:nowrap; }
.membership-seats__count>text { font-size:12px;font-weight:400;color:#777; }
.membership-seats__manage { display:flex;align-items:center;gap:5px;flex:none;min-height:44px;color:#555;font-size:12px; }
.membership-heading { display:flex;justify-content:space-between;align-items:center;margin:0 2px 12px;gap:10px;font-size:17px;font-weight:650; }
.membership-heading__hint { font-size:12px;color:#888;font-weight:400; }
.membership-swiper { height:220px;margin:0 -6px; }
.membership-tier { position:relative;overflow:hidden;height:204px;margin:2px 8px 0 2px;padding:18px;box-sizing:border-box;border:1px solid transparent;border-radius:14px;background:var(--lead-member-gradient);color:var(--lead-member-text); }
.membership-tier.is-selected { border-color:var(--lead-member-accent);box-shadow:0 2px 8px rgba(32,36,42,.09); }
.membership-tier.is-disabled { opacity:.7; }
.membership-tier__content { position:relative; }
.membership-tier__top { display:flex;justify-content:space-between;align-items:flex-start;gap:10px; }
.membership-tier__top>view:first-child { min-width:0;flex:1; }
.membership-tier__name { display:block;font-size:17px;font-weight:650;line-height:24px;overflow-wrap:break-word; }
.membership-tier__state { flex:none;padding:3px 8px;border-radius:20px;background:rgba(255,255,255,.28);font-size:11px;line-height:18px; }
.membership-tier__price { display:flex;align-items:baseline;gap:4px;margin-top:12px;line-height:30px;white-space:nowrap; }
.membership-tier__price>text:first-child { font-size:25px;font-weight:700; }
.membership-tier__price>text:last-child:not(:first-child) { font-size:12px;font-weight:400;opacity:.72; }
.membership-tier__accounts { margin-top:3px;font-size:12px;line-height:20px;opacity:.78; }
.membership-tier__experience { display:flex;align-items:center;gap:5px;opacity:.8;font-size:12px;line-height:20px;margin-top:6px; }
.membership-tier__experience.is-met { opacity:1; }
.membership-tier__unavailable { font-size:13px;opacity:.72;line-height:30px;margin-top:12px; }
.membership-cycle { display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px; }
.membership-cycle>button { flex:1 0 27%;min-width:72px;margin:0;padding:0 8px;height:40px;line-height:40px;background:#fff;border:1px solid #e5e5e5;border-radius:6px;color:#555;font-size:14px;white-space:nowrap; }
.membership-cycle>button::after,.membership-refresh-payment::after,.membership-cancel-payment::after { border:0; }
.membership-cycle>button.is-active { color:var(--primary-color,#ff4444);border-color:var(--primary-color,#ff4444);font-weight:600; }
.membership-section { padding:16px;background:#fff;border-radius:8px;margin:12px 0; }
.membership-section__title { font-size:16px;font-weight:650;line-height:24px;margin-bottom:8px;overflow-wrap:break-word; }
.membership-right { display:flex;gap:12px;align-items:center;padding:13px 0;border-bottom:1px solid #f2f2f2; }
.membership-right:last-child { border-bottom:0;padding-bottom:4px; }
.membership-right>view:nth-child(2) { min-width:0;flex:1; }
.membership-right>view:nth-child(2)>text { display:block;font-size:12px;color:#777;line-height:19px; }
.membership-right>view:nth-child(2)>text:first-child { font-size:14px;color:#333;font-weight:500;line-height:22px;margin-bottom:2px; }
.membership-right__excluded { flex:none;font-size:11px;color:#999; }
.membership-money-row { display:flex;align-items:center;justify-content:space-between;gap:12px;margin:12px 0;font-size:13px;color:#666; }
.membership-money-row>text:last-child { color:#222;font-weight:600; }
.membership-money-row>text.membership-money-row__total { font-size:24px;color:var(--price-text-color,var(--primary-color,#ff4444)); }
.membership-money-row--total { border-top:1px solid #f1f1f1;padding-top:16px;margin-top:16px; }
.membership-cost-credit { color:#999;font-size:11px;line-height:18px;text-align:right;margin-top:-6px; }
.membership-order-costs { border-top:1px solid #f2f2f2;margin-top:12px; }
.membership-deposit-exit { padding:0 8px; }
.membership-deposit-exit .membership-muted { margin-bottom:12px; }
.membership-deposit-status { background:#f6f7f9;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:21px;color:#666; }
.membership-deposit-status>text { display:block; }
.membership-deposit-status>text:first-child { color:#333;font-size:14px;font-weight:600;margin-bottom:3px; }
.membership-refund-sources { border-top:1px solid #e9eaed;margin-top:8px;padding-top:8px; }
.membership-refund-sources>view { display:flex;justify-content:space-between;gap:12px; }
.membership-deposit-blocking { margin:12px 0;padding:10px 12px;background:#fff7ed;color:#9d7344;font-size:12px;line-height:21px;border-radius:6px; }
.membership-deposit-apply { min-height:44px;line-height:44px;margin:12px 0;color:var(--primary-color,#ff4444);background:#f6f6f6;font-size:14px;border-radius:6px; }
.membership-deposit-apply::after { border:0; }
.membership-quote-period { font-size:12px;color:#777;line-height:20px;text-align:right;padding-top:4px; }
.membership-muted { font-size:13px;color:#777;line-height:22px; }
.membership-help { padding:2px 8px;color:#777;font-size:12px;line-height:22px; }
.membership-payment-message { padding:12px;background:#f5f7fa;color:#4e5969;border-radius:6px;font-size:13px;line-height:21px; }
.membership-payment-message.is-error,.membership-error { color:#a34f34;background:#fff4ed; }
.membership-error { font-size:13px;line-height:21px;padding:12px;border-radius:6px; }
.membership-refresh-payment { font-size:13px;line-height:44px;color:var(--primary-color,#ff4444);background:transparent;margin:4px 0 0; }
.membership-footer-note { color:#777;font-size:12px;line-height:19px;text-align:center;margin:0 0 8px; }
.membership-footer-note:last-child { margin:0; }
.membership-cashier { height:0;flex:none; }
.membership-cancel-payment { background:transparent;color:#777;font-size:12px;line-height:44px; }
</style>
