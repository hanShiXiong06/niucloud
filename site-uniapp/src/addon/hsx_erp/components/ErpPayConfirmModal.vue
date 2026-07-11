<!--
  ErpPayConfirmModal - 应付款逐台确认弹窗（对齐PC端 pay dialog）

  PC端逻辑：勾选设备 → 填本次付款金额 → 选账户 → 提交
  每台设备可单独设置本次付款金额，默认填满剩余应付

  用法：
    <ErpPayConfirmModal
      v-model:show="showPayModal"
      :partyId="row.party_id"
      :partyName="row.party_name"
      :purchaseOrderId="row.purchase_order_id"
      :accounts="accounts"
      @success="onPaySuccess"
    />
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="modal-wrap">
            <view class="modal-header">
                <view>
                    <text class="modal-title">{{ isNonDevicePay ? '确认经营付款' : '确认付款' }}</text>
                    <text class="modal-subtitle">{{ partyName }}</text>
                </view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <scroll-view scroll-y class="modal-body" :show-scrollbar="true">

            <view class="source-wrap"><ErpFinanceSourceSummary :row="sourceRow" direction="payable" /></view>

            <!-- 汇总行 -->
            <view class="summary-row">
                <view class="summary-item">
                    <text class="summary-label">本次付款</text>
                    <text class="summary-value red">¥{{ money(totalPaying) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">付后仍欠</text>
                    <text class="summary-value orange">¥{{ money(totalAfter) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">{{ isNonDevicePay ? '费用明细' : '已选设备' }}</text>
                    <text class="summary-value">{{ checkedCount }} {{ isNonDevicePay ? '笔' : '台' }}</text>
                </view>
            </view>

            <!-- 账户选择 -->
            <view class="account-row" @click="showAccountPicker = true">
                <text class="account-label required">付款账户</text>
                <view class="account-select">
                    <text :class="form.capital_account_id ? 'account-text' : 'account-placeholder'">
                        {{ selectedAccountLabel || '点击选择账户' }}
                    </text>
                    <text class="account-arrow">›</text>
                </view>
            </view>

            <!-- 设备明细（对齐PC端设备表格） -->
            <view v-if="loadingItems" class="items-loading"><u-loading-icon size="24" /></view>
            <view v-else class="items-list">
                <u-checkbox-group v-model="selectedPayableIds" placement="column" @change="onSelectionChange">
                    <view v-for="item in items" :key="item.id" class="device-row">
                        <view class="device-row__check">
                            <u-checkbox
                                :name="payableIdentity(item)"
                                :disabled="Number(item.allocated_remain || 0) <= 0"
                            />
                        </view>
                        <view class="device-row__info">
                            <text class="device-row__model">{{ isNonDevicePay ? (item.category_name || '经营支出') : (item.model || '未填写设备名称') }}</text>
                            <text class="device-row__sub">{{ isNonDevicePay ? (item.source_no || item.payable_no || '经营费用') : deviceIdentityLine(item) }}</text>
                            <text v-if="item.business_reason || item.remark" class="device-row__reason">{{ item.business_reason || item.remark }}</text>
                            <view class="device-row__amounts">
                                <text class="amt-tiny">应付 ¥{{ money(item.payable_amount) }}</text>
                                <text class="amt-tiny">已付 ¥{{ money(item.allocated_paid) }}</text>
                                <text class="amt-tiny orange">余 ¥{{ money(item.allocated_remain) }}</text>
                            </view>
                        </view>
                        <view class="device-row__input">
                            <u-input
                                v-model="item.pay_amount"
                                type="number"
                                :disabled="!item.checked"
                                :placeholder="money(item.allocated_remain)"
                                :customStyle="{ width: '140rpx', background: '#f8fafc', borderRadius: '8rpx', padding: '6rpx 12rpx' }"
                                @blur="capPayAmount(item)"
                            />
                        </view>
                    </view>
                </u-checkbox-group>
            </view>

            <!-- 备注 -->
            <view class="remark-row">
                <u-input v-model="form.remark" placeholder="备注（可选）" :customStyle="remarkStyle" />
            </view>
            <view class="voucher-row">
                <ErpVoucherUploader v-model="form.voucher_urls" @uploading="voucherUploading = $event" />
            </view>
            </scroll-view>

            <!-- 底部按钮 -->
            <view class="modal-actions">
                <view class="action-btn action-btn--minor">
                    <u-button @click="close">取消</u-button>
                </view>
                <view class="action-btn action-btn--major">
                    <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">
                        确认付款 ¥{{ money(totalPaying) }}
                    </u-button>
                </view>
            </view>
        </view>

        <!-- 账户弹窗 -->
        <u-popup :show="showAccountPicker" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="showAccountPicker = false">
            <view class="account-popup">
                <view class="account-popup__head">
                    <text class="account-popup__title">选择账户</text>
                    <view class="account-popup__close" @click="showAccountPicker = false">
                        <u-icon name="close" color="#64748b" size="20" />
                    </view>
                </view>
                <scroll-view scroll-y class="account-popup__body">
                <u-cell-group v-if="accounts.length" :border="false">
                    <u-cell v-for="a in accounts" :key="a.id" :title="a.account_name" :label="'余额 ¥' + money(a.balance)" @click="selectAccount(a)">
                        <template #value>
                            <u-icon
                                v-if="Number(a.id) === Number(form.capital_account_id)"
                                name="checkmark-circle-fill"
                                color="#3b6ef5"
                                size="20"
                            />
                        </template>
                    </u-cell>
                </u-cell-group>
                <u-empty v-else mode="data" text="暂无可用资金账户" />
                </scroll-view>
            </view>
        </u-popup>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { getMobilePayablePartyItems, confirmMobilePayableItems } from '@/addon/hsx_erp/api/erp'
import ErpFinanceSourceSummary from '@/addon/hsx_erp/components/ErpFinanceSourceSummary.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import { erpFinanceSourceMeta } from '@/addon/hsx_erp/hooks/useErpFinanceSource'
import { cloneErpSubmitSnapshot, confirmErpPopupAction } from '@/addon/hsx_erp/hooks/useErpPopupConfirm'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'

const props = withDefaults(defineProps<{
    show: boolean
    partyId?: number
    partyName?: string
    purchaseOrderId?: number
    sourceType?: string
    sourceRow?: any
    accounts?: any[]
}>(), {
    show: false,
    partyId: 0,
    partyName: '',
    purchaseOrderId: 0,
    sourceType: '',
    sourceRow: () => ({}),
    accounts: () => [],
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'success'): void
}>()

const items = ref<any[]>([])
const loadingItems = ref(false)
const submitting = ref(false)
const voucherUploading = ref(false)
const showAccountPicker = ref(false)
const selectedPayableIds = ref<number[]>([])
const form = ref({ capital_account_id: 0, remark: '', voucher_urls: '' })
const remarkStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx', marginTop: '12rpx' }

const totalPaying = computed(() => items.value.reduce((s, i) => s + (i.checked ? Number(i.pay_amount || 0) : 0), 0))
const totalAfter = computed(() => items.value.reduce((s, i) => {
    const remain = Number(i.allocated_remain || 0)
    const paying = i.checked ? Number(i.pay_amount || 0) : 0
    return s + Math.max(0, remain - paying)
}, 0))
const checkedCount = computed(() => items.value.filter(i => i.checked && Number(i.pay_amount || 0) > 0).length)
const isNonDevicePay = computed(() => {
    const row = props.sourceRow || {}
    return String(props.sourceType || row.source_type || '').includes('operating_') || String(row.biz_scene || '').includes('operating_') || String(row.category_statement_group || '').includes('operating_')
})
const canSubmit = computed(() => totalPaying.value > 0 && form.value.capital_account_id > 0 && !voucherUploading.value)
const selectedAccountLabel = computed(() => {
    const a = props.accounts.find(a => Number(a.id) === Number(form.value.capital_account_id))
    return a ? `${a.account_name}（¥${money(a.balance)}）` : ''
})

let loadToken = 0
let preserveOnReopen = false

watch(
    () => [props.show, props.partyId, props.purchaseOrderId, props.sourceType],
    () => {
        if (!props.show || props.partyId <= 0) return
        if (preserveOnReopen) {
            preserveOnReopen = false
            return
        }
        openModal()
    },
    { immediate: true }
)

watch(() => props.accounts, () => {
    if (props.show && !form.value.capital_account_id) {
        form.value.capital_account_id = props.accounts[0]?.id || 0
    }
})

function openModal() {
    const source = erpFinanceSourceMeta(props.sourceRow, 'payable')
    form.value = { capital_account_id: props.accounts[0]?.id || 0, remark: source.business_reason, voucher_urls: '' }
    items.value = []
    selectedPayableIds.value = []
    loadItems()
}

async function loadItems() {
    const token = ++loadToken
    loadingItems.value = true
    try {
        const params: any = { limit: 50 }
        if (props.purchaseOrderId) params.purchase_order_id = props.purchaseOrderId
        if (props.sourceType) params.source_type = props.sourceType
        const res: any = await getMobilePayablePartyItems(props.partyId, params)
        if (token !== loadToken) return
        items.value = (res?.data?.data || []).map((row: any) => ({
            ...row,
            checked: Number(row.allocated_remain || 0) > 0,
            pay_amount: Number(Number(row.allocated_remain || 0).toFixed(2)),
        }))
        selectedPayableIds.value = items.value.filter(item => item.checked).map(payableIdentity)
    } finally {
        if (token === loadToken) loadingItems.value = false
    }
}

function onSelectionChange(values: Array<string | number>) {
    const selected = new Set((values || []).map(Number))
    items.value.forEach(item => {
        const wasChecked = Boolean(item.checked)
        item.checked = selected.has(payableIdentity(item))
        if (item.checked && !wasChecked) item.pay_amount = Number(Number(item.allocated_remain || 0).toFixed(2))
        if (!item.checked) item.pay_amount = 0
    })
}

function capPayAmount(item: any) {
    const max = Number(item.allocated_remain || 0)
    if (Number(item.pay_amount) > max) item.pay_amount = Number(max.toFixed(2))
    if (Number(item.pay_amount) < 0) item.pay_amount = 0
}

function selectAccount(a: any) {
    form.value.capital_account_id = a.id
    showAccountPicker.value = false
}

async function submit() {
    if (submitting.value) return
    const payItems = items.value
        .filter(i => i.checked && Number(i.pay_amount || 0) > 0)
        .map(i => ({ payable_id: Number(i.payable_id || i.asset_payable_id || 0), amount: Number(i.pay_amount) }))
        .filter(i => i.payable_id > 0)

    if (!payItems.length) {
        uni.showToast({ title: isNonDevicePay.value ? '请选择要付款的费用明细' : '请选择要付款的设备', icon: 'none' })
        return
    }
    const source = erpFinanceSourceMeta(props.sourceRow, 'payable')
    const account = props.accounts.find(item => Number(item.id) === Number(form.value.capital_account_id))
    const snapshot = cloneErpSubmitSnapshot({
        partyId: Number(props.partyId),
        items: payItems,
        amount: payItems.reduce((sum, item) => sum + Number(item.amount || 0), 0),
        capital_account_id: Number(form.value.capital_account_id),
        remark: form.value.remark || '手机端确认付款',
        voucher_urls: form.value.voucher_urls,
        partyName: props.partyName || '-',
        financeTypeName: source.finance_type_name,
        partyRoleLabel: source.party_role_label || '付款对象',
        sourceNo: source.source_no || '-',
        accountName: account?.account_name || '所选账户',
    })
    submitting.value = true
    const confirmed = await confirmErpPopupAction({
        title: isNonDevicePay.value ? '确认经营付款' : '确认设备级付款',
        content: `${snapshot.partyRoleLabel}：${snapshot.partyName}\n业务类型：${snapshot.financeTypeName}\n来源单：${snapshot.sourceNo}\n${isNonDevicePay.value ? '费用明细' : '设备'}：${snapshot.items.length} ${isNonDevicePay.value ? '笔' : '台'}\n付款金额：¥${money(snapshot.amount)}\n付款账户：${snapshot.accountName}\n确认后写入资金流水，不能直接删除。`,
        confirmText: '确认付款',
        closePopup: closeForConfirm,
        reopenPopup: reopenAfterConfirm,
    })
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        await confirmMobilePayableItems(snapshot.partyId, {
            items: snapshot.items,
            capital_account_id: snapshot.capital_account_id,
            remark: snapshot.remark,
            voucher_urls: snapshot.voucher_urls,
        })
        uni.showToast({ title: '付款已确认', icon: 'success' })
        emit('success')
    } catch (e: any) {
        uni.showToast({ title: e?.message || '付款失败', icon: 'none' })
        reopenAfterConfirm()
    } finally { submitting.value = false }
}

function close() { emit('update:show', false) }
function closeForConfirm() {
    showAccountPicker.value = false
    emit('update:show', false)
}
function reopenAfterConfirm() {
    preserveOnReopen = true
    emit('update:show', true)
}
function payableIdentity(item: any) {
    return Number(item?.payable_id || item?.asset_payable_id || item?.id || 0)
}
function deviceIdentityLine(item: any) {
    return erpDeviceIdentityLine(item, '未填写规格或 IMEI')
}
const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
.modal-wrap { height: 90vh; max-height: 1120rpx; display: flex; flex-direction: column; padding-bottom: env(safe-area-inset-bottom); overflow:hidden; }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 28rpx 32rpx 16rpx; }
.modal-body { flex:1; min-height:0; width:100%; box-sizing:border-box; }
.modal-title { font-size: 32rpx; font-weight: 700; color: #0f172a; display: block; }
.modal-subtitle { font-size: 26rpx; color: #64748b; display: block; margin-top: 4rpx; }
.source-wrap { margin:0 32rpx 16rpx; }
.summary-row { display: flex; gap: 0; margin: 0 32rpx 16rpx; background: #f8fafc; border-radius: 12rpx; overflow: hidden; }
.summary-item { flex: 1; padding: 14rpx 0; text-align: center; border-right: 1rpx solid #e2e8f0; &:last-child { border-right: none; } }
.summary-label { font-size: 22rpx; color: #94a3b8; display: block; }
.summary-value { font-size: 26rpx; font-weight: 600; color: #0f172a; display: block; margin-top: 4rpx; }
.summary-value.red { color: #dc2626; }
.summary-value.orange { color: #ea580c; }
.account-row { display: flex; align-items: center; gap: 16rpx; padding: 0 32rpx 16rpx; }
.account-label { font-size: 26rpx; color: #374151; width: 150rpx; flex-shrink: 0; }
.account-label.required::before { content: '*'; color: #dc2626; margin-right: 4rpx; }
.account-select { flex: 1; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border-radius: 8rpx; padding: 10rpx 16rpx; }
.account-text { font-size: 26rpx; color: #0f172a; }
.account-placeholder { font-size: 26rpx; color: #94a3b8; }
.account-arrow { font-size: 32rpx; color: #94a3b8; }
.items-loading { display: flex; justify-content: center; padding: 32rpx; }
.items-list { padding: 0 32rpx; box-sizing: border-box; }
.device-row { display: flex; align-items: flex-start; gap: 12rpx; padding: 16rpx 0; border-bottom: 1rpx solid #f1f5f9; }
.device-row__check { padding-top: 4rpx; }
.device-row__info { flex: 1; }
.device-row__model { font-size: 26rpx; font-weight: 600; color: #0f172a; }
.device-row__sub { font-size: 22rpx; color: #64748b; display: block; margin-top: 4rpx; }
.device-row__reason { display:block; margin-top:5rpx; color:#9a3412; font-size:20rpx; line-height:1.4; }
.device-row__amounts { display: flex; gap: 12rpx; margin-top: 6rpx; flex-wrap: wrap; }
.amt-tiny { font-size: 22rpx; color: #94a3b8; }
.amt-tiny.orange { color: #ea580c; }
.device-row__input { flex-shrink: 0; }
.remark-row { padding: 0 32rpx 12rpx; }
.voucher-row { padding: 0 32rpx 14rpx; }
.modal-actions { display: flex; gap: 16rpx; padding: 16rpx 32rpx; border-top: 1rpx solid #f1f5f9; }
.action-btn { min-width: 0; }
.action-btn--minor { flex: 1; }
.action-btn--major { flex: 2; }
.account-popup {
    height: 60vh;
    max-height: 820rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.account-popup__body { flex:1; min-height:0; width:100%; }
.account-popup__head {
    min-height: 96rpx;
    padding: 0 28rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}
.account-popup__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
}
.account-popup__close {
    width: 56rpx;
    height: 56rpx;
    border-radius: 28rpx;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
