<template>
    <view v-if="p.summary">
        <view v-if="showEntry" class="payment-summary">
            <view class="row"><text class="heading">付款同步</text><button v-if="p.canCapture || p.summary.task_id" class="link" :disabled="p.locked" @click="p.open()">{{ p.entryLabel }} ›</button><button v-else class="link" @click="emit('configure')">设置付款同步 ›</button></view>
            <text v-if="p.summaryMessage" class="warning">{{ p.summaryMessage }}</text>
        </view>
        <u-popup :show="p.visible" mode="bottom" :round="8" :z-index="11000" closeable :close-on-click-overlay="false" @close="close">
            <view class="payment-sheet" :class="{'receipt-sheet': p.registrationDone && !p.candidateMode && !p.detailsOpen}"><view class="sheet-head"><text class="heading">{{ p.lookup ? (p.lookup === 'creator_id' ? '选择制单人' : '选择记账账户') : p.registrationDone && !p.candidateMode ? '付款回执' : '同步付款记录到博远' }}</text></view><scroll-view scroll-y class="sheet-body">
                <text v-if="!p.uatContext" class="muted">当前账套只可查看，不可同步付款。</text>
                <template v-if="p.lookup"><text class="muted">仅显示可用档案；记账账户须属于原采购公司。</text><u-search v-model="p.keyword" bg-color="var(--boyuan-soft)" color="var(--boyuan-text)" placeholder-color="var(--boyuan-muted)" :show-action="false" placeholder="搜索已有档案名称 / 编码" @search="p.loadChoices(false)" @clear="p.loadChoices(false)" /><text v-if="p.lookupError" class="warning">{{ p.lookupError }}</text><button v-for="item in p.choices" :key="item.id" class="choice" :disabled="p.locked || !p.editable" @click="p.choose(item)"><view><text>{{ item.name }}</text><text class="muted">{{ item.extra?.company_name || '公司归属由后台复核' }}</text></view><text>选择 ›</text></button><u-loading-icon v-if="p.choiceLoading" text="读取已有档案" /><text v-else-if="!p.choices.length && !p.lookupError" class="muted">{{ p.lookup === 'account_id' ? '没有本采购公司的可用测试账户，请同步或核对基础资料' : '没有匹配的当前启用档案' }}</text><u-button v-if="p.cursor" size="small" :loading="p.choiceLoading" @click="p.loadChoices(true)">加载更多档案</u-button></template>
                <template v-else-if="p.candidateMode"><text class="subheading">选择 ERP 已确认的付款</text><text class="muted">选中后还可核对，不会立即同步。</text><view v-for="item in p.candidates" :key="item.settlement_id" class="candidate"><view class="row"><text>{{ item.settlement_no }}</text><text class="amount">{{ purchaseMoney(item.amount) }}</text></view><text class="muted">确认时间 {{ purchaseCheckedAt(item.confirmed_at) }}</text><button v-if="item.eligible === true && !item.task_id" class="link" :disabled="p.locked || !p.canCapture" @click="p.capture(item)">核对这笔付款 ›</button><text v-else-if="!item.task_id" class="warning">{{ item.reason || '不符合登记条件' }}</text><button v-if="item.task_id" class="link" :disabled="p.locked" @click="p.openExisting(item)">查看原记录</button></view><text v-if="!p.loading && !p.candidates.length && !p.error" class="muted">暂无符合条件的已确认付款</text><u-button v-if="p.candidateMore" size="small" :loading="p.loading" @click="p.loadCandidates(true)">加载更多已确认付款</u-button></template>
                <template v-else-if="p.taskView">
                    <text :class="p.isUnknown ? 'warning' : 'subheading'">{{ p.resultMessage }}</text>
                    <view class="facts"><text class="amount">{{ p.registrationDone ? '同步金额' : '本笔金额' }}　{{ purchaseMoney(p.taskView.source.amount) }}</text><text v-if="p.taskView.task.external_no">博远付款单号　{{ p.taskView.task.external_no }}</text>
                        <template v-if="p.editable"><text>付款对象　{{ p.labels.supplier?.name || '未取得' }}</text><text>记账公司　{{ p.labels.company?.name || '未取得' }}</text></template>
                    </view>
                    <template v-if="p.editable">
                        <button class="choice" :disabled="p.locked" @click="p.openLookup('account_id')"><text>博远记账账户</text><text>{{ p.labels.account?.name || '选择账户 ›' }}</text></button>
                        <button class="choice" :disabled="p.locked" @click="p.openLookup('creator_id')"><text>制单人</text><text>{{ p.labels.creator?.name || '选择制单人 ›' }}</text></button>
                        <text class="muted">只同步 ERP 付款记录，不会再次扣款。</text>
                        <view v-if="p.needsExperiment && !p.saveFirst" class="experiment"><u-checkbox :used-alone="true" :checked="p.experimentAck" :disabled="p.locked" :label="PAYMENT_EXPERIMENT_WARNING" :label-size="13" @change="p.experimentAck = !p.experimentAck" /></view>
                    </template>
                    <text v-if="p.taskView.task.status === 'awaiting_confirmation' && !p.editable && !p.isUnknown" class="muted">当前只可查看。请有权限的财务核对账套及同步设置。</text>
                    <text v-for="issue in p.issues" :key="issue" class="warning">{{ issue }}</text>
                    <button class="link" @click="p.detailsOpen = !p.detailsOpen">{{ p.detailsOpen ? '收起明细' : '查看明细' }}</button>
                    <view v-if="p.detailsOpen" class="details">
                        <text class="muted">ERP 付款号　{{ p.taskView.source.settlement_no || '未取得' }}</text><text class="muted">博远采购单号　{{ p.taskView.source.external_purchase_no || '未取得' }}</text><text class="muted">财务确认时间　{{ purchaseCheckedAt(p.taskView.source.confirmed_at) }}</text>
                        <text v-if="!p.registrationDone && !p.editable && p.taskView.task.reason" class="muted">{{ p.taskView.task.reason }}</text>
                        <text class="muted">博远应付形成：{{ paymentApLabel(p.apEvidence) }}。采购核销由博远处理；回执不是核销结果或银行转账凭证。</text>
                        <button v-if="p.canChooseAnother" class="link" :disabled="p.locked || p.dirty" @click="p.loadCandidates(false)">查看本批其他付款</button>
                    </view>
                </template>
                <u-loading-icon v-if="p.loading" text="读取付款核对" /><text v-if="p.error" class="warning">{{ p.error }}</text>
            </scroll-view><view class="sheet-footer"><u-button v-if="p.lookup" @click="p.exitLookup">返回核对</u-button>
                <u-button v-else-if="p.registrationDone && !p.candidateMode" type="primary" @click="close">完成</u-button>
                <template v-else-if="p.editable && !p.candidateMode">
                    <u-button :disabled="p.locked" @click="close">关闭</u-button>
                    <u-button v-if="p.saveFirst" type="primary" :disabled="p.locked || !p.selectionChanged" :loading="p.busy" @click="p.save">保存核对（不发送）</u-button>
                    <u-button v-else type="primary" :disabled="!p.canSubmit || p.locked" :loading="p.busy" @click="submit">同步到博远</u-button>
                </template>
                <template v-else><u-button :disabled="p.locked" @click="close">关闭</u-button><u-button v-if="!p.candidateMode || p.error" :disabled="p.locked || p.dirty" :loading="p.loading" @click="p.refresh">刷新状态</u-button></template></view></view>
        </u-popup>
    </view>
</template>
<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import * as api from '../api/index'
import { purchaseCheckedAt, purchaseMoney } from '../utils/purchase-form'
import { PAYMENT_EXPERIMENT_WARNING, paymentApLabel, usePurchasePayment } from '../utils/purchase-payment'
const props = defineProps<{ purchase: any, config: any, originalPaymentTaskId?: number }>()
const emit = defineEmits<{ (event: 'changed'): void, (event: 'configure'): void }>()
const p = reactive(usePurchasePayment(props, api, text => uni.showToast({ title: text, icon: 'none' }), () => emit('changed')))
// A workbench receipt link must open the selected payment, not a later payment on the same purchase.
watch(() => props.originalPaymentTaskId, id => { if (id && props.purchase?.payment) void p.open(id) }, { immediate: true })
const showEntry = computed(() => p.summary?.status !== 'permission_denied' && (p.summary?.task_id || p.canCapture || (p.hasPayment && p.uatContext && !p.enabled && props.purchase?.can_operate !== false && props.purchase?.task?.status === 'succeeded')))
const confirm = (text: string, title: string, button: string) => new Promise<boolean>(resolve => uni.showModal({ title, content: text, confirmText: button, cancelText: '返回核对', success: result => resolve(result.confirm), fail: () => resolve(false) }))
function submit() { return p.submit(text => confirm(text, '同步这笔付款记录？', '确认同步')) }
function close() { return p.close(() => confirm('尚有未保存的选择，关闭后将丢弃。不会发起同步。', '放弃本次修改？', '放弃修改')) }
</script>
<style scoped lang="scss">
.payment-summary { color: var(--boyuan-text,#20252d); padding: 14px 0; border-block: 1px solid var(--boyuan-line,#e7ebf0); }.row { display: flex; justify-content: space-between; align-items: center; gap: 12px; }.heading { font-size: 15px; font-weight: 600; }.muted { display: block; font-size: 12px; line-height: 1.8; color: var(--boyuan-muted,#626d7c); margin-top: 6px; }button { margin: 0; border: 0; padding: 0; background: transparent; color: inherit; font-size: 13px; line-height: 1.7; &::after { border: 0; } }.link { min-height: 42px; color: var(--boyuan-brand,#2563eb); font-size: 12px; text-align: left; }.setting-link { display: block; }.ap-note { display: block; font-size: 12px; line-height: 1.8; color: var(--boyuan-warning,#93511d); margin: 10px 0; }.payment-sheet { color: var(--boyuan-text,#20252d); background: var(--boyuan-surface,#fff); border-radius: 8px 8px 0 0; max-height: 92vh; display: flex; flex-direction: column; }.sheet-head { padding: 20px 46px 14px 18px; border-bottom: 1px solid var(--boyuan-line,#e7ebf0); flex-shrink: 0; }.sheet-head .heading { display: block; }.sheet-body { height: 62vh; min-height: 0; flex-shrink: 1; padding: 0 18px 14px; box-sizing: border-box; }.sheet-footer { display: flex; gap: 10px; flex-shrink: 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid var(--boyuan-line,#e7ebf0); }.sheet-footer :deep(.u-button) { min-height: 44px; }.warning { display: block; padding: 10px 12px; margin: 12px 0; color: var(--boyuan-warning,#93511d); background: var(--boyuan-warning-bg,#fff4e7); font-size: 12px; line-height: 1.8; overflow-wrap: anywhere; }.subheading { display: block; font-size: 15px; font-weight: 600; margin: 16px 0 8px; line-height: 1.7; }.choice { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; text-align: left; min-height: 50px; padding: 12px 0; border-bottom: 1px solid var(--boyuan-line,#e7ebf0); }.choice > text:last-child { text-align: right; overflow-wrap: anywhere; }.choice > view { min-width: 0; }.candidate { padding: 16px 0; border-bottom: 1px solid var(--boyuan-line,#e7ebf0); font-size: 13px; }.amount { font-weight: 600; white-space: nowrap; }.facts { padding: 12px 0; border-block: 1px solid var(--boyuan-line,#e7ebf0); }.facts > text { display: block; margin: 7px 0; font-size: 13px; line-height: 1.7; overflow-wrap: anywhere; }.facts .amount { font-size: 17px; white-space: normal; }.experiment { padding: 12px; margin: 16px 0; border: 1px solid var(--boyuan-warning,#93511d); border-radius: 4px; }.experiment :deep(.u-checkbox__label) { white-space: normal; }@media(max-height:640px) { .sheet-body { height: 54vh; } }
.payment-sheet { --boyuan-surface: var(--el-bg-color,#fff); --boyuan-soft: var(--el-fill-color-light,#f4f6f8); --boyuan-text: var(--el-text-color-primary,#20252d); --boyuan-muted: var(--el-text-color-regular,#626d7c); --boyuan-line: var(--el-border-color-lighter,#e7ebf0); --boyuan-brand: var(--primary-color,#2563eb); --boyuan-warning: #93511d; --boyuan-warning-bg: #fff4e7; }@media(prefers-color-scheme:dark) { .payment-sheet { --boyuan-surface: var(--el-bg-color,#191d24); --boyuan-soft: var(--el-fill-color-light,#252b35); --boyuan-text: var(--el-text-color-primary,#edf0f5); --boyuan-muted: var(--el-text-color-regular,#bac4d1); --boyuan-line: var(--el-border-color-lighter,#39414d); --boyuan-warning: #f1ba80; --boyuan-warning-bg: #36291c; } }
.receipt-sheet .sheet-body { height: 228px; max-height: 52vh; }.details { padding-bottom: 8px; }.row > text:first-child { flex-shrink: 0; }.candidate .row { overflow-wrap: anywhere; }.link { min-height: 44px; }
</style>
