<template>
    <view class="hsx-cooperation" :style="themeColor()">
        <view class="coop-page-head"><view><view class="coop-store-context">{{ ui.profile.value.name || '商户协作' }}<text v-if="ui.profile.value.region_text"> · {{ ui.profile.value.region_text }}</text></view><view class="coop-page-title">{{ titles[ui.tab.value] }}</view></view><button class="coop-icon-button" :disabled="ui.loading.value" aria-label="刷新当前业务数据" @tap="refresh"><u-icon name="reload" size="21" color="#737e8c" /></button></view>
        <scroll-view :scroll-y="!overlay && !childOverlay && !onboarding" class="coop-main-scroll" @scrolltolower="loadMore" @touchmove.stop>
            <CoopState v-if="!ui.loggedIn.value" title="登录后参与商户协作" hint="使用本站会员身份，无需另设一套商户账号" icon="account" /><view v-if="!ui.loggedIn.value" class="coop-login"><u-button type="primary" shape="circle" text="使用会员身份登录" @click="login" /></view>
            <template v-else>
                <CoopState v-if="ui.loading.value && !ui.ready.value || ui.error.value" :loading="ui.loading.value" :error="ui.error.value" @retry="refresh" />
                <template v-else-if="ui.ready.value && ui.profile.value.id">
                    <view v-if="ui.profile.value.blocked_at" class="coop-warning">合作已停止。历史交付与已形成收益仍保留，请到“我的”查看原因。</view>
                    <view v-else-if="!ui.config.value.enabled" class="coop-warning">本站尚未开启商户协作，发单前请联系平台完成配置。</view>
                    <view v-if="paymentId" class="coop-payment-status"><text>{{ paymentMessage || '上笔支付结果待核对，不要重复下单' }}</text><text @tap="resumePayment">核对 / 继续支付 ›</text></view>
                    <view v-if="ui.writeError.value && !overlay" class="coop-warning">{{ ui.writeError.value }}</view><template v-if="ui.tab.value === 'deliveries'">
                        <view class="coop-direction-tabs"><view :class="{ active: ui.direction.value === 'outgoing' }" @tap="ui.changeDirection('outgoing')">我发出的</view><view :class="{ active: ui.direction.value === 'incoming' }" @tap="ui.changeDirection('incoming')">我收到的</view></view><view class="coop-list-tools"><u-search v-model="ui.query.keyword" placeholder="搜索客户或意向型号" :show-action="true" action-text="查询" @search="ui.loadLeads()" @custom="ui.loadLeads()" @clear="ui.loadLeads()" /><scroll-view scroll-x class="coop-status-tabs"><view class="coop-status-tabs__inner"><text v-for="item in statusOptions" :key="item.value" :class="{ active: ui.query.status === item.value }" @tap="filterStatus(item.value)">{{ item.label }}</text></view></scroll-view></view><view class="coop-lead-list"><CoopState v-if="ui.listLoading.value && !ui.rows.value.length || ui.listError.value" :loading="ui.listLoading.value" :error="ui.listError.value" @retry="ui.loadLeads()" /><template v-else><CoopLeadCard v-for="lead in ui.rows.value" :key="lead.id" :lead="lead" :direction="ui.direction.value" @open="openDetail" @select="openSelection" /><CoopState v-if="!ui.rows.value.length" title="暂无符合条件的交付" :hint="ui.direction.value === 'outgoing' ? '发布客户需求，选一家合适门店交付' : '门店交付给你的客户需求会显示在这里'" /><view v-if="ui.rows.value.length" class="coop-loadmore" @tap="loadMore">{{ ui.listLoading.value ? '正在读取…' : ui.canLoadMore.value ? '点击加载更多' : '已显示全部交付' }}</view></template></view>
                    </template>
                    <template v-else-if="ui.tab.value === 'stores'"><view class="coop-store-discovery"><view class="coop-section-title">找到合适的交付门店</view><view class="coop-muted">优先按客户所在区县匹配，必要时扩大到同城、全省。靠谱认证和等级分开展示，不代表平台担保。</view><u-button type="primary" shape="circle" text="按地区查找门店" @click="browseStores" /></view><view v-if="ui.profile.value.connection" class="coop-form-section"><view class="coop-section-title">本店库存采集</view><view class="coop-muted">{{ ui.profile.value.connection.last_sync_message || '连接 ERP 后完整同步库存，供其他商户匹配参考。' }}</view><view class="coop-menu-row" @tap="overlay = 'connection'"><text>查看连接与同步进度</text><u-icon name="arrow-right" size="14" color="#929ba8" /></view></view></template>
                    <CoopWalletPanel v-else-if="ui.tab.value === 'wallet'" :key="'wallet-' + ui.profile.value.id" :wallet="ui.wallet.value" :loading="ui.walletLoading.value" :error="ui.walletError.value" @reload="ui.loadWallet()" @changed="ui.loadWallet()" @more="ui.loadWallet(true)" @overlay="childOverlay = $event" />
                    <view v-else class="coop-profile-wrap"><CoopProfilePanel ref="profileRef" :key="'profile-' + ui.profile.value.id" :profile="ui.profile.value" :config="ui.config.value" :providers="ui.providers.value" :busy="ui.busy.value" :error="ui.writeError.value" @submit="saveProfile" @connection="overlay = 'connection'" @purchase="purchase" @subscribe="subscribe" @overlay="childOverlay = $event" /><button class="coop-legacy-entry" @tap="$emit('legacy')">查看原平台派发工单 ›</button></view>
                </template>
                <CoopProfilePanel v-if="onboarding" :profile="{}" :config="ui.config.value" :providers="ui.providers.value" onboarding :busy="ui.busy.value" :error="ui.writeError.value" @submit="saveProfile" />
            </template>
        </scroll-view>
        <view v-if="ui.ready.value && ui.profile.value.id && ui.tab.value === 'deliveries'" class="coop-publish-dock"><u-button type="primary" shape="circle" text="＋ 发布新线索" :disabled="ui.busy.value || !ui.config.value.enabled || !!ui.profile.value.blocked_at" @click="openPublish" /></view>
        <view v-if="ui.profile.value.id" class="coop-bottom-nav"><view v-for="item in tabs" :key="item.value" :class="{ active: ui.tab.value === item.value }" @tap="switchTab(item.value)"><u-icon :name="item.icon" size="23" :color="ui.tab.value === item.value ? 'var(--primary-color, #d93833)' : '#929aa6'" /><text>{{ item.label }}</text></view></view>
        <CoopPublishForm ref="publishRef" :key="'publish-' + (ui.profile.value.id || 0)" :show="overlay === 'publish'" :busy="ui.busy.value" :error="ui.writeError.value" @close="closeOverlay" @submit="publish" />
        <CoopStorePicker :show="overlay === 'select' || overlay === 'stores'" :lead="overlay === 'select' ? ui.detail.value : undefined" :profile="ui.profile.value" :busy="ui.busy.value" @close="closeOverlay" @assign="assign" />
        <CoopTaskDetail :show="overlay === 'detail'" :lead="ui.detail.value" :config="ui.config.value" :loading="ui.detailLoading.value" :error="ui.detailError.value || ui.writeError.value" :busy="locked" @close="closeOverlay" @reload="reloadDetail" @select="overlay = 'select'" @pay="overlay = 'commission'" @action="act" />
        <CoopCommissionForm :show="overlay === 'commission'" :lead="ui.detail.value" :config="ui.config.value" :busy="ui.busy.value" :error="ui.writeError.value" @close="overlay = 'detail'" @submit="payCommission" />
        <CoopConnectionPanel :show="overlay === 'connection'" :profile="ui.profile.value" :providers="ui.providers.value" @close="closeOverlay" @changed="ui.bootstrap" />
        <pay ref="payRef" :ignore-pay="['friendspay']" :reformat="pageUrl" @confirm="verifyPayment" @close="verifyPayment" />
    </view>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getToken } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useCooperation } from '../../composables/useCooperation'
import { cooperationOrder, coopError, coopMoney, coopRequestId, type CoopRow, type LeadAction } from '../../api/cooperation'
import CoopState from './CoopState.vue'
import CoopLeadCard from './CoopLeadCard.vue'
import CoopPublishForm from './CoopPublishForm.vue'
import CoopStorePicker from './CoopStorePicker.vue'
import CoopTaskDetail from './CoopTaskDetail.vue'
import CoopCommissionForm from './CoopCommissionForm.vue'
import CoopWalletPanel from './CoopWalletPanel.vue'
import CoopProfilePanel from './CoopProfilePanel.vue'
import CoopConnectionPanel from './CoopConnectionPanel.vue'
const props = withDefaults(defineProps<{ initialId?: number }>(), { initialId: 0 })
defineEmits<{ (event: 'legacy'): void }>()
const ui = useCooperation(), pageUrl = '/addon/hsx_lead/pages/workbench/index', overlay = ref(''), childOverlay = ref(false), payRef = ref<any>(null), publishRef = ref<any>(null), profileRef = ref<any>(null), confirming = ref(false), paymentId = ref(0), paymentMessage = ref(''), paymentChecking = ref(false), selectedId = ref(0)
const locked = computed(() => ui.busy.value || confirming.value), onboarding = computed(() => ui.loggedIn.value && ui.ready.value && !ui.profile.value.id && !ui.error.value)
const tabs = [{ value: 'deliveries', label: '交付', icon: 'order' }, { value: 'stores', label: '找门店', icon: 'home' }, { value: 'wallet', label: '收益', icon: 'rmb-circle' }, { value: 'profile', label: '我的', icon: 'account' }]
const titles: Record<string, string> = { deliveries: '我的交付', stores: '找交付门店', wallet: '我的收益', profile: '我的门店' }
const statusOptions = [{ value: '', label: '全部' }, { value: 'selecting', label: '待选门店' }, { value: 'assigned', label: '待接单' }, { value: 'following', label: '跟进中' }, { value: 'unpaid', label: '待付佣' }, { value: 'paid', label: '已付佣' }, { value: 'no_deal', label: '未成交' }]
const orderRequests = new Map<string, string>(); let reviewNotifiedId = 0; let identity = '', disposed = false, starting: Promise<void> | null = null, pendingId = props.initialId, handledInitialId = 0
const storageKey = () => `hsx_lead_coop_pay:${uni.getStorageSync('wap_site_id') || import.meta.env.VITE_SITE_ID || 'current'}:${ui.profile.value.id || 0}`
function login() { useLogin().setLoginBack({ url: pageUrl, ...(pendingId ? { param: { collaboration_id: pendingId } } : {}), mode: 'redirectTo' }) }
async function initialize() { if (starting) return starting; starting = (async () => { const token = ui.resume(); if (token !== identity) { identity = token; overlay.value = ''; childOverlay.value = false; selectedId.value = 0; paymentId.value = 0; paymentMessage.value = ''; orderRequests.clear(); publishRef.value?.clear() }; if (!token) return; const loaded = await ui.refresh(); if (!loaded || disposed || !ui.profile.value.id) return; const saved = Number(uni.getStorageSync(storageKey()) || 0); if (Number.isSafeInteger(saved) && saved > 0) { paymentId.value = saved; await verifyPayment() }; if (pendingId && pendingId !== handledInitialId) { const id = pendingId; handledInitialId = id; await openDetail(id) } })().finally(() => { starting = null }); return starting }
async function refresh() { if (ui.loading.value) return; await ui.refresh() }
async function switchTab(value: string) { if (overlay.value || childOverlay.value || locked.value) return; await ui.changeTab(value) }
function closeOverlay() { if (locked.value) return; overlay.value = ''; ui.writeError.value = '' }
function filterStatus(value: string) { if (ui.listLoading.value) return; ui.query.status = value; void ui.loadLeads() }
function loadMore() { if (!ui.listLoading.value && ui.canLoadMore.value && ui.tab.value === 'deliveries') void ui.loadLeads(true) }
async function openDetail(id: number) { if (locked.value) return; selectedId.value = Number(id); ui.writeError.value = ''; overlay.value = 'detail'; await ui.loadDetail(Number(id)) }
async function reloadDetail() { if (selectedId.value) await ui.loadDetail(selectedId.value) }
async function openSelection(id: number) { if (locked.value) return; selectedId.value = Number(id); const loaded = await ui.loadDetail(Number(id)); if (loaded) overlay.value = 'select'; else { overlay.value = 'detail' } }
function browseStores() { ui.writeError.value = ''; overlay.value = 'stores' }
function openPublish() { ui.writeError.value = ''; if (ui.config.value.publishing_required && Number(ui.profile.value.publisher_until) * 1000 <= Date.now()) { uni.showModal({ title: '发单权益尚未开通', content: '请先在“我的”购买发单权益，开通后有效一个月。', confirmText: '去开通', success: result => { if (result.confirm) void ui.changeTab('profile') } }); return }; overlay.value = 'publish' }
async function publish(data: CoopRow) { const value = await ui.createLead(data); if (!value) return; const id = Number(value.id || value.lead_id); if (!id) { ui.writeError.value = '已提交但未返回有效线索，请关闭后刷新列表核对，不要重复发布'; return }; publishRef.value?.clear(); ui.direction.value = 'outgoing'; await ui.loadLeads(); await openSelection(id) }
async function assign(store: CoopRow) { const value = await ui.assign(Number(ui.detail.value.id), Number(store.id), Number(ui.detail.value.assignment_version || 0)); if (!value) { if (ui.writeError.value) uni.showToast({ title: ui.writeError.value, icon: 'none' }); return }; overlay.value = ''; await ui.refresh(); uni.showToast({ title: '已交付，等待门店确认', icon: 'none' }) }
async function act(value: LeadAction) { if (locked.value || !ui.detail.value.id) return; const messages: Record<string, string> = { accept: '接单后由你联系客户，成交后需支付协作佣金，未成交不收费。确认承接？', reject: '本次交付将退回发布方重新选择门店。', withdraw: '撤回后，对方将不能再接受这次交付，可重新选店。', deal: '确认已与客户成交？确认后需要支付协作佣金，最低 50 元。', no_deal: '确认本次未成交？将结束本次交付，平台可能进行客户回访。' }; confirming.value = true; const result: any = await new Promise(resolve => uni.showModal({ title: value === 'deal' ? '确认成交' : '确认操作', content: messages[value], confirmText: '确认', success: resolve, fail: () => resolve({ confirm: false }) })); confirming.value = false; if (!result.confirm || disposed) return; const changed = await ui.action(value); if (!changed) return; if (value === 'reject' || value === 'withdraw' || value === 'no_deal') { overlay.value = ''; ui.detail.value = {}; await ui.loadLeads() } else { await ui.loadDetail(selectedId.value); await ui.loadLeads(); if (value === 'deal') overlay.value = 'commission' }; uni.showToast({ title: value === 'accept' ? '已接单，请联系客户' : value === 'deal' ? '已确认成交，请支付佣金' : '操作已完成', icon: 'none' }) }
async function saveProfile(data: CoopRow) { const value = await ui.saveProfile(data); if (!value) return; await ui.bootstrap(); uni.showToast({ title: '门店资料已保存', icon: 'none' }); if (ui.profile.value.id && !ui.profile.value.connection?.connected) overlay.value = 'connection' }
async function startOrder(purpose: string, amount?: string) { if (locked.value) return; const leadId = purpose === 'commission' ? Number(ui.detail.value.id) : 0, key = `${purpose}:${leadId}`; if (!orderRequests.has(key)) orderRequests.set(key, coopRequestId()); const value = await ui.order({ purpose, ...(leadId ? { lead_id: leadId, amount } : {}), request_id: orderRequests.get(key) }); if (!value?.id) return; paymentId.value = Number(value.id); uni.setStorageSync(storageKey(), paymentId.value); overlay.value = ''; paymentMessage.value = '支付尚未确认，请勿重复创建订单'; if (value.status === 'paid') { await verifyPayment(); return }; payRef.value?.open('hsx_lead_cooperation', Number(value.id), pageUrl) }
function payCommission(amount: string) { return startOrder('commission', amount) }
async function purchase(purpose: string) { if (locked.value) return; confirming.value = true; const price = coopMoney(purpose === 'publishing' ? ui.config.value.publishing_price : ui.config.value.certification_price); const result: any = await new Promise(resolve => uni.showModal({ title: purpose === 'publishing' ? '购买发单权益' : '申请靠谱认证', content: purpose === 'publishing' ? `支付 ${price}，权益从本次付款确认起延长一个月。` : `支付认证申请费 ${price}；支付不代表已通过认证，需平台核验。`, success: resolve, fail: () => resolve({ confirm: false }) })); confirming.value = false; if (result.confirm && !disposed) await startOrder(purpose) }
async function resumePayment() { if (!paymentId.value || paymentChecking.value) return; const id = paymentId.value; await verifyPayment(); if (paymentId.value === id && !disposed) { try { const response: any = await cooperationOrder(id); if (response.data?.status === 'unpaid' && paymentId.value === id) payRef.value?.open('hsx_lead_cooperation', id, pageUrl) } catch (cause) { paymentMessage.value = coopError(cause, '原单状态读取失败，请稍后核对') } } }
async function verifyPayment() { if (!paymentId.value || paymentChecking.value || !getToken()) return; paymentChecking.value = true; const who = identity, id = paymentId.value; try { const response: any = await cooperationOrder(id); if (disposed || who !== String(getToken() || '') || id !== paymentId.value) return; if (response.data?.status === 'paid') { if (response.data.requires_manual_review) { paymentMessage.value = response.data.review_message || '款项已收到，但权益未授权，请联系平台核对，不要重复支付'; await ui.refresh(); if (reviewNotifiedId !== id) { reviewNotifiedId = id; uni.showModal({ title: '已收款，需平台核对', content: paymentMessage.value, showCancel: false }) }; return }; paymentMessage.value = '支付已确认'; uni.removeStorageSync(storageKey()); orderRequests.delete(`${response.data.purpose}:${response.data.lead_id || 0}`); paymentId.value = 0; await ui.refresh(); uni.showToast({ title: '支付已确认，业务状态已更新', icon: 'none' }) } else paymentMessage.value = response.data?.status === 'closed' ? '原支付单已关闭，可核对后重新申请' : '原单尚未确认支付，可刷新核对'; } catch (cause) { paymentMessage.value = coopError(cause, '支付结果暂未确认，请稍后刷新，勿重复付款') } finally { paymentChecking.value = false } }
function subscribe() {
    // #ifdef MP-WEIXIN
    useSubscribeMessage().request('hsx_lead_task_assigned,hsx_lead_task_changed')
    // #endif
    // #ifndef MP-WEIXIN
    uni.showToast({ title: '请在微信小程序内订阅，结果以微信授权为准', icon: 'none' })
    // #endif
}
watch(() => props.initialId, id => { if (Number.isSafeInteger(id) && id > 0) { pendingId = id; if (ui.ready.value && ui.profile.value.id) void openDetail(id) } })
onMounted(initialize)
function pausePage() { ui.pause(); starting = null }
defineExpose({ resume: initialize, pause: pausePage, refresh })
onBeforeUnmount(() => { disposed = true; ui.dispose(); orderRequests.clear() })
</script>
<style lang="scss">@import './cooperation.scss';</style>
