<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="offset-modal">
            <view class="modal-header">
                <view>
                    <text class="modal-title">应收应付折账</text>
                    <text class="modal-subtitle">{{ partyName || '-' }}</text>
                </view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <view class="offset-summary">
                <view class="summary-item">
                    <text class="summary-label">勾选应付</text>
                    <text class="summary-value red">¥{{ money(payableChecked) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">勾选应收</text>
                    <text class="summary-value blue">¥{{ money(receivableChecked) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">可抵扣</text>
                    <text class="summary-value orange">¥{{ money(offsetMax) }}</text>
                </view>
            </view>

            <view v-if="loading" class="offset-loading"><u-loading-icon size="28" /></view>
            <scroll-view v-else scroll-y class="offset-body">
                <view class="offset-section">
                    <view class="offset-section__head">
                        <view><text>客户给我的设备</text><text class="offset-section__caption">应付：我要付给客户</text></view>
                        <text>¥{{ money(payableChecked) }}</text>
                    </view>
                    <u-checkbox-group v-model="selectedPayableIds" placement="column" @change="onPayableSelectionChange">
                        <view v-for="item in payables" :key="'p'+item.id" class="offset-row" @click="togglePayable(item)">
                            <u-checkbox :name="Number(item.id)" :disabled="Number(item.remain || 0) <= 0" @click.stop />
                            <view class="offset-row__main">
                                <text class="offset-row__title">{{ payableTitle(item) }}</text>
                                <text class="offset-row__sub">{{ payableIdentityLine(item) }}</text>
                                <view v-if="financeDevices(item).length" class="offset-devices">
                                    <view v-for="(device, index) in financeDevices(item)" :key="`pd-${device.asset_id || device.id || index}`" class="offset-device">
                                        <text class="offset-device__title">{{ device.model || '未填写型号' }}</text>
                                        <text class="offset-device__serial">{{ deviceSerial(device) }}</text>
                                        <text v-if="deviceSpec(device)" class="offset-device__spec">{{ deviceSpec(device) }}</text>
                                    </view>
                                </view>
                                <text v-else class="offset-row__non-device">非设备业务，无关联 IMEI</text>
                            </view>
                            <text class="offset-row__amount">¥{{ money(item.remain) }}</text>
                        </view>
                    </u-checkbox-group>
                    <view v-if="!payables.length" class="offset-empty">暂无可折应付</view>
                </view>

                <view class="offset-section">
                    <view class="offset-section__head">
                        <view><text>我给客户的设备</text><text class="offset-section__caption">应收：客户要付给我</text></view>
                        <text>¥{{ money(receivableChecked) }}</text>
                    </view>
                    <u-checkbox-group v-model="selectedReceivableIds" placement="column" @change="onReceivableSelectionChange">
                        <view v-for="item in receivables" :key="'r'+item.id" class="offset-row" @click="toggleReceivable(item)">
                            <u-checkbox :name="Number(item.id)" :disabled="Number(item.remain || 0) <= 0" @click.stop />
                            <view class="offset-row__main">
                                <text class="offset-row__title">{{ item.source_no || item.sale_no || item.receivable_no || '应收款' }}</text>
                                <text class="offset-row__sub">{{ receivableIdentityLine(item) }}</text>
                                <view v-if="financeDevices(item).length" class="offset-devices">
                                    <view v-for="(device, index) in financeDevices(item)" :key="`rd-${device.asset_id || device.id || index}`" class="offset-device">
                                        <text class="offset-device__title">{{ device.model || '未填写型号' }}</text>
                                        <text class="offset-device__serial">{{ deviceSerial(device) }}</text>
                                        <text v-if="deviceSpec(device)" class="offset-device__spec">{{ deviceSpec(device) }}</text>
                                    </view>
                                </view>
                                <text v-else class="offset-row__non-device">非设备业务，无关联 IMEI</text>
                            </view>
                            <text class="offset-row__amount blue">¥{{ money(item.remain) }}</text>
                        </view>
                    </u-checkbox-group>
                    <view v-if="!receivables.length" class="offset-empty">暂无可折应收</view>
                </view>

                <view class="offset-form">
                    <view class="offset-form__item">
                        <text class="pay-label">本次抵扣</text>
                        <u-input v-model="form.amount" type="number" :placeholder="'最多 ¥'+money(offsetMax)" :customStyle="inputStyle" @blur="capAmount" />
                    </view>
                    <view v-if="diffAmount > 0" class="offset-diff">
                        <view class="offset-diff__line" @click="toggleSettleDiff">
                            <u-checkbox-group v-model="settleDiffSelection" @change="onSettleDiffSelectionChange">
                                <u-checkbox name="settle_diff" @click.stop />
                            </u-checkbox-group>
                            <text>{{ diffText }}</text>
                        </view>
                        <view v-if="form.settle_diff" class="account-row" @click="showAccountPicker = true">
                            <text class="account-label required">{{ diffDirection === 'payable' ? '付款账户' : '收款账户' }}</text>
                            <view class="account-select" :class="{ 'account-select--on': form.capital_account_id }">
                                <text :class="form.capital_account_id ? 'account-text' : 'account-placeholder'">{{ selectedAccountLabel || '点击选择账户' }}</text>
                                <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                            </view>
                        </view>
                    </view>
                    <view class="offset-form__item">
                        <text class="pay-label">备注</text>
                        <u-input v-model="form.remark" placeholder="如：同行往来对冲" :customStyle="inputStyle" />
                    </view>
                    <ErpVoucherUploader
                        v-if="form.settle_diff"
                        v-model="form.voucher_urls"
                        :title="diffDirection === 'payable' ? '差额付款凭证' : '差额收款凭证'"
                        @uploading="voucherUploading = $event"
                    />
                </view>
            </scroll-view>

            <view class="action-bar">
                <view class="action-btn action-btn--minor">
                    <u-button @click="close">取消</u-button>
                </view>
                <view class="action-btn action-btn--major">
                    <u-button type="warning" :loading="submitting" :disabled="!canSubmit" @click="submit">
                        确认折账 ¥{{ money(form.amount) }}
                    </u-button>
                </view>
            </view>
        </view>

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
import { computed, ref, watch } from 'vue'
import { confirmMobileOffset, getMobilePayablePartyItems, getMobileReceivableList } from '@/addon/hsx_erp/api/erp'
import { cloneErpSubmitSnapshot, confirmErpPopupAction } from '@/addon/hsx_erp/hooks/useErpPopupConfirm'
import { erpFinanceSourceMeta } from '@/addon/hsx_erp/hooks/useErpFinanceSource'
import { erpSpecLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'

const props = withDefaults(defineProps<{
    show: boolean
    partyId?: number
    partyName?: string
    accounts?: any[]
}>(), {
    show: false,
    partyId: 0,
    partyName: '',
    accounts: () => [],
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'success'): void
}>()

const loading = ref(false)
const submitting = ref(false)
const voucherUploading = ref(false)
const showAccountPicker = ref(false)
const payables = ref<any[]>([])
const receivables = ref<any[]>([])
const selectedPayableIds = ref<number[]>([])
const selectedReceivableIds = ref<number[]>([])
const settleDiffSelection = ref<string[]>([])
const form = ref({ amount: 0, settle_diff: false, capital_account_id: 0, remark: '', voucher_urls: '' })
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '12rpx 16rpx' }

const payableChecked = computed(() => payables.value.reduce((sum, row) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const receivableChecked = computed(() => receivables.value.reduce((sum, row) => row.checked ? sum + Number(row.remain || 0) : sum, 0))
const offsetMax = computed(() => Math.min(payableChecked.value, receivableChecked.value))
const diffAmount = computed(() => Math.abs(payableChecked.value - receivableChecked.value))
const diffDirection = computed(() => payableChecked.value > receivableChecked.value ? 'payable' : 'receivable')
const diffText = computed(() => {
    if (diffAmount.value <= 0) return ''
    return diffDirection.value === 'payable'
        ? `抵扣后仍需付款 ¥${money(diffAmount.value)}，本次一并结清`
        : `抵扣后仍需收款 ¥${money(diffAmount.value)}，本次一并结清`
})
const selectedAccountLabel = computed(() => {
    const account = props.accounts.find((a: any) => Number(a.id) === Number(form.value.capital_account_id))
    return account ? `${account.account_name}（余额 ¥${money(account.balance)}）` : ''
})
const canSubmit = computed(() =>
    Number(form.value.amount) > 0 &&
    Number(form.value.amount) <= offsetMax.value + 0.0001 &&
    (!form.value.settle_diff || Number(form.value.capital_account_id) > 0) && !voucherUploading.value
)

let loadToken = 0
let preserveOnReopen = false
watch(() => [props.show, props.partyId], () => {
    if (!props.show || props.partyId <= 0) return
    if (preserveOnReopen) {
        preserveOnReopen = false
        return
    }
    openModal()
}, { immediate: true })

function openModal() {
    form.value = { amount: 0, settle_diff: false, capital_account_id: props.accounts[0]?.id || 0, remark: '', voucher_urls: '' }
    payables.value = []
    receivables.value = []
    selectedPayableIds.value = []
    selectedReceivableIds.value = []
    settleDiffSelection.value = []
    loadItems()
}

async function loadItems() {
    const token = ++loadToken
    loading.value = true
    try {
        const [payableRes, receivableRes]: any[] = await Promise.all([
            getMobilePayablePartyItems(props.partyId, { page: 1, limit: 100 }),
            getMobileReceivableList({ party_id: props.partyId, status: '', page: 1, limit: 100 }),
        ])
        if (token !== loadToken) return
        payables.value = (payableRes?.data?.data || []).map((row: any) => {
            const remain = Number(row.allocated_remain ?? row.remain_amount ?? 0)
            return { ...row, id: Number(row.payable_id || row.id || 0), remain, checked: remain > 0 }
        }).filter((row: any) => row.id > 0 && row.remain > 0)
        receivables.value = (receivableRes?.data?.data || []).map((row: any) => {
            const remain = Number(row.remain_amount ?? (Number(row.amount || 0) - Number(row.settled_amount || 0)))
            return { ...row, remain, checked: remain > 0 }
        }).filter((row: any) => Number(row.id || 0) > 0 && row.remain > 0)
        selectedPayableIds.value = payables.value.filter(row => row.checked).map(row => Number(row.id))
        selectedReceivableIds.value = receivables.value.filter(row => row.checked).map(row => Number(row.id))
        syncAmount()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '折账数据加载失败', icon: 'none' })
    } finally {
        if (token === loadToken) loading.value = false
    }
}

function syncAmount() {
    form.value.amount = Number(offsetMax.value.toFixed(2))
}

function togglePayable(item: any) {
    if (Number(item.remain || 0) <= 0) return
    selectedPayableIds.value = toggleId(selectedPayableIds.value, Number(item.id))
    onPayableSelectionChange(selectedPayableIds.value)
}

function toggleReceivable(item: any) {
    if (Number(item.remain || 0) <= 0) return
    selectedReceivableIds.value = toggleId(selectedReceivableIds.value, Number(item.id))
    onReceivableSelectionChange(selectedReceivableIds.value)
}

function onPayableSelectionChange(values: Array<string | number>) {
    const selected = new Set((values || []).map(Number))
    payables.value.forEach(item => { item.checked = selected.has(Number(item.id)) })
    syncAmount()
}

function onReceivableSelectionChange(values: Array<string | number>) {
    const selected = new Set((values || []).map(Number))
    receivables.value.forEach(item => { item.checked = selected.has(Number(item.id)) })
    syncAmount()
}

function onSettleDiffSelectionChange(values: Array<string | number>) {
    form.value.settle_diff = (values || []).includes('settle_diff')
}

function toggleSettleDiff() {
    settleDiffSelection.value = form.value.settle_diff ? [] : ['settle_diff']
    form.value.settle_diff = !form.value.settle_diff
}

function capAmount() {
    const max = offsetMax.value
    const amount = Number(form.value.amount || 0)
    if (amount > max) form.value.amount = Number(max.toFixed(2))
    if (amount < 0) form.value.amount = 0
}

function selectAccount(account: any) {
    form.value.capital_account_id = Number(account.id || 0)
    showAccountPicker.value = false
}

async function submit() {
    if (submitting.value) return
    capAmount()
    if (!canSubmit.value) return
    const payableIds = unique(payables.value.filter(row => row.checked).map(row => Number(row.id || 0)).filter(Boolean))
    const receivableIds = unique(receivables.value.filter(row => row.checked).map(row => Number(row.id || 0)).filter(Boolean))
    if (!payableIds.length || !receivableIds.length) {
        uni.showToast({ title: '请同时选择应付和应收', icon: 'none' })
        return
    }
    const snapshot = cloneErpSubmitSnapshot({
        payable_ids: payableIds,
        receivable_ids: receivableIds,
        amount: Number(form.value.amount),
        settle_diff: Boolean(form.value.settle_diff),
        capital_account_id: form.value.settle_diff ? Number(form.value.capital_account_id) : 0,
        remark: form.value.remark || '手机端应收应付折账',
        voucher_urls: form.value.voucher_urls,
        partyName: props.partyName || '-',
    })
    submitting.value = true
    const confirmed = await confirmErpPopupAction({
        title: '确认应收应付折账',
        content: `往来主体：${snapshot.partyName}\n应付 ${snapshot.payable_ids.length} 笔 / 应收 ${snapshot.receivable_ids.length} 笔\n折账金额：¥${money(snapshot.amount)}\n该操作会同时核销应收和应付，不产生真实收付款；确认后流水不能直接删除。`,
        confirmText: '确认折账',
        closePopup: closeForConfirm,
        reopenPopup: reopenAfterConfirm,
    })
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        await confirmMobileOffset({
            payable_ids: snapshot.payable_ids,
            receivable_ids: snapshot.receivable_ids,
            amount: snapshot.amount,
            settle_diff: snapshot.settle_diff,
            capital_account_id: snapshot.capital_account_id,
            remark: snapshot.remark,
            voucher_urls: snapshot.voucher_urls,
        })
        uni.showToast({ title: '折账已确认', icon: 'success' })
        emit('success')
    } catch (e: any) {
        uni.showToast({ title: e?.message || '折账失败，请重试', icon: 'none' })
        reopenAfterConfirm()
    } finally {
        submitting.value = false
    }
}

function close() {
    emit('update:show', false)
    showAccountPicker.value = false
}

function closeForConfirm() {
    showAccountPicker.value = false
    emit('update:show', false)
}

function reopenAfterConfirm() {
    preserveOnReopen = true
    emit('update:show', true)
}

function toggleId(values: number[], id: number) {
    return values.includes(id) ? values.filter(value => value !== id) : [...values, id]
}

function unique(list: number[]) {
    return Array.from(new Set(list))
}

const money = (v: any) => Number(v || 0).toFixed(2)
const sourceLabel = (s: string) => ({ sale: '销售应收', purchase_return: '采购退货应收' }[s] || '其他应收')
function payableTitle(item: any) {
    const meta = erpFinanceSourceMeta(item, 'payable')
    return [meta.finance_type_name, item.model].filter(Boolean).join(' · ') || '应付款'
}
function payableIdentityLine(item: any) {
    const meta = erpFinanceSourceMeta(item, 'payable')
    return [meta.source_no, item.imei ? `IMEI ${item.imei}` : '', item.payable_no || ''].filter(Boolean).join(' · ') || '-'
}
function receivableIdentityLine(item: any) {
    return [erpFinanceSourceMeta(item, 'receivable').finance_type_name || sourceLabel(item.source_type), item.receivable_no || ''].filter(Boolean).join(' · ') || '应收款'
}
function financeDevices(item: any) {
    if (Array.isArray(item?.devices) && item.devices.length) return item.devices
    return item?.model || item?.imei || item?.sn || item?.asset_no ? [item] : []
}
function deviceSerial(device: any) {
    if (device?.imei) return `IMEI ${device.imei}`
    if (device?.sn) return `SN ${device.sn}`
    return 'IMEI 未填写'
}
const deviceSpec = (device: any) => erpSpecLine(device?.spec, '')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.offset-modal { height: 88vh; max-height: 1100rpx; display: flex; flex-direction: column; background: #fff; overflow:hidden; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:32rpx 32rpx 20rpx; }
.modal-title { display:block; font-size:34rpx; font-weight:700; color:#0f172a; }
.modal-subtitle { display:block; margin-top:6rpx; font-size:24rpx; color:#64748b; }
.offset-summary { display:flex; gap:14rpx; padding:0 32rpx 20rpx; }
.summary-item { flex:1; background:#f8fafc; border-radius:16rpx; padding:16rpx; }
.summary-label { display:block; font-size:22rpx; color:#94a3b8; }
.summary-value { display:block; margin-top:8rpx; font-size:28rpx; font-weight:700; color:#0f172a; }
.summary-value.red { color:#dc2626; }
.summary-value.blue { color:#2563eb; }
.summary-value.orange { color:#ea580c; }
.offset-loading { display:flex; justify-content:center; padding:80rpx 0; }
.offset-body { flex:1; min-height:0; width:100%; padding:0 32rpx; box-sizing:border-box; }
.offset-section { margin-bottom:22rpx; }
.offset-section__head { display:flex; align-items:flex-start; justify-content:space-between; gap:20rpx; color:#334155; font-size:26rpx; font-weight:700; margin-bottom:12rpx; }
.offset-section__head > view { min-width:0; }
.offset-section__head > text:last-child { flex:none; }
.offset-section__caption { display:block; margin-top:4rpx; color:#94a3b8; font-size:20rpx; font-weight:400; }
.offset-row { display:flex; align-items:center; gap:16rpx; padding:18rpx 0; border-bottom:1rpx solid #f1f5f9; }
.offset-row__main { flex:1; min-width:0; }
.offset-row__title { display:block; font-size:27rpx; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.offset-row__sub { display:block; margin-top:4rpx; font-size:22rpx; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.offset-devices { margin-top:12rpx; display:flex; flex-direction:column; gap:8rpx; }
.offset-device { padding:12rpx 14rpx; border-radius:12rpx; background:#f8fafc; }
.offset-device__title,.offset-device__serial,.offset-device__spec { display:block; line-height:1.45; }
.offset-device__title { color:#334155; font-size:23rpx; font-weight:600; }
.offset-device__serial { margin-top:3rpx; color:#0f172a; font-size:22rpx; font-weight:600; word-break:break-all; }
.offset-device__spec { margin-top:3rpx; color:#64748b; font-size:21rpx; word-break:break-all; }
.offset-row__non-device { display:block; margin-top:9rpx; color:#94a3b8; font-size:21rpx; }
.offset-row__amount { font-size:27rpx; font-weight:700; color:#dc2626; }
.offset-row__amount.blue { color:#2563eb; }
.offset-empty { text-align:center; color:#94a3b8; font-size:25rpx; padding:36rpx 0; }
.offset-form { padding:8rpx 0 24rpx; }
.offset-form__item { margin-bottom:20rpx; }
.pay-label { display:block; margin-bottom:8rpx; font-size:25rpx; color:#374151; }
.offset-diff { background:#fff7ed; border-radius:16rpx; padding:18rpx; margin-bottom:20rpx; }
.offset-diff__line { display:flex; align-items:center; gap:10rpx; color:#9a3412; font-size:25rpx; margin-bottom:14rpx; }
.account-row { margin-top:8rpx; }
.account-label { display:block; font-size:25rpx; color:#374151; margin-bottom:8rpx; }
.account-select { min-height:72rpx; display:flex; justify-content:space-between; align-items:center; gap:16rpx; background:#fff; border:2rpx solid transparent; border-radius:12rpx; padding:0 18rpx; box-sizing:border-box; }
.account-select--on { background:#f8fbff; border-color:#3b6ef5; }
.account-text { flex:1; min-width:0; font-size:25rpx; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.account-placeholder { flex:1; min-width:0; font-size:25rpx; color:#94a3b8; }
.action-btn { min-width:0; }
.action-btn--minor { flex:1; }
.action-btn--major { flex:2; }
.account-popup {
    height:60vh;
    max-height:820rpx;
    padding-bottom:calc(20rpx + env(safe-area-inset-bottom));
    background:#fff;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.account-popup__body { flex:1; min-height:0; width:100%; }
.account-popup__head {
    min-height:96rpx;
    padding:0 28rpx;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20rpx;
}
.account-popup__title { font-size:30rpx; font-weight:700; color:#0f172a; }
.account-popup__close {
    width:56rpx;
    height:56rpx;
    border-radius:28rpx;
    background:#f8fafc;
    display:flex;
    align-items:center;
    justify-content:center;
}
</style>
