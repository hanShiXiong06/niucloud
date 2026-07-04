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
                    <text class="modal-title">确认付款</text>
                    <text class="modal-subtitle">{{ partyName }}</text>
                </view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

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
                    <text class="summary-label">已选设备</text>
                    <text class="summary-value">{{ checkedCount }} 台</text>
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
            <scroll-view v-else scroll-y class="items-list">
                <view v-for="item in items" :key="item.id" class="device-row">
                    <view class="device-row__check">
                        <u-checkbox
                            v-model="item.checked"
                            :disabled="Number(item.allocated_remain || 0) <= 0"
                            @change="onCheck(item)"
                        />
                    </view>
                    <view class="device-row__info">
                        <text class="device-row__model">{{ item.model }}</text>
                        <text class="device-row__sub">{{ item.spec || '-' }} · {{ item.imei }}</text>
                        <view class="device-row__amounts">
                            <text class="amt-tiny">应付 ¥{{ money(item.total_cost || item.payable_amount) }}</text>
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
            </scroll-view>

            <!-- 备注 -->
            <view class="remark-row">
                <u-input v-model="form.remark" placeholder="备注（可选）" :customStyle="remarkStyle" />
            </view>

            <!-- 底部按钮 -->
            <view class="modal-actions">
                <u-button @click="close" :customStyle="{flex:'1'}">取消</u-button>
                <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">
                    确认付款 ¥{{ money(totalPaying) }}
                </u-button>
            </view>
        </view>

        <!-- 账户弹窗 -->
        <u-popup :show="showAccountPicker" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="showAccountPicker = false">
            <view class="account-popup">
                <view class="account-popup__title">选择账户</view>
                <view v-for="a in accounts" :key="a.id" class="account-item" :class="{ selected: a.id === form.capital_account_id }" @click="selectAccount(a)">
                    <text class="account-item__name">{{ a.account_name }}</text>
                    <text class="account-item__balance">余额 ¥{{ money(a.balance) }}</text>
                </view>
            </view>
        </u-popup>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { getMobilePayablePartyItems, confirmMobilePayableItems } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    show: boolean
    partyId?: number
    partyName?: string
    purchaseOrderId?: number
    accounts?: any[]
}>(), {
    show: false,
    partyId: 0,
    partyName: '',
    purchaseOrderId: 0,
    accounts: () => [],
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'success'): void
}>()

const items = ref<any[]>([])
const loadingItems = ref(false)
const submitting = ref(false)
const showAccountPicker = ref(false)
const form = ref({ capital_account_id: 0, remark: '' })
const remarkStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx', marginTop: '12rpx' }

const totalPaying = computed(() => items.value.reduce((s, i) => s + (i.checked ? Number(i.pay_amount || 0) : 0), 0))
const totalAfter = computed(() => items.value.reduce((s, i) => {
    const remain = Number(i.allocated_remain || 0)
    const paying = i.checked ? Number(i.pay_amount || 0) : 0
    return s + Math.max(0, remain - paying)
}, 0))
const checkedCount = computed(() => items.value.filter(i => i.checked && Number(i.pay_amount || 0) > 0).length)
const canSubmit = computed(() => totalPaying.value > 0 && form.value.capital_account_id > 0)
const selectedAccountLabel = computed(() => {
    const a = props.accounts.find(a => a.id === form.value.capital_account_id)
    return a ? `${a.account_name}（¥${money(a.balance)}）` : ''
})

watch(() => props.show, (v) => {
    if (v && props.partyId > 0) {
        form.value = { capital_account_id: props.accounts[0]?.id || 0, remark: '' }
        loadItems()
    }
})

async function loadItems() {
    loadingItems.value = true
    try {
        const params: any = { limit: 50 }
        if (props.purchaseOrderId) params.purchase_order_id = props.purchaseOrderId
        const res: any = await getMobilePayablePartyItems(props.partyId, params)
        items.value = (res?.data?.data || []).map((row: any) => ({
            ...row,
            checked: Number(row.allocated_remain || 0) > 0,
            pay_amount: Number(Number(row.allocated_remain || 0).toFixed(2)),
        }))
    } finally { loadingItems.value = false }
}

function onCheck(item: any) {
    if (item.checked) {
        item.pay_amount = Number(Number(item.allocated_remain || 0).toFixed(2))
    } else {
        item.pay_amount = 0
    }
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
    const payItems = items.value
        .filter(i => i.checked && Number(i.pay_amount || 0) > 0)
        .map(i => ({ payable_id: Number(i.payable_id || i.asset_payable_id || 0), amount: Number(i.pay_amount) }))
        .filter(i => i.payable_id > 0)

    if (!payItems.length) {
        uni.showToast({ title: '请选择要付款的设备', icon: 'none' })
        return
    }
    submitting.value = true
    try {
        await confirmMobilePayableItems(props.partyId, {
            items: payItems,
            capital_account_id: form.value.capital_account_id,
            remark: form.value.remark || '手机端确认付款',
        })
        uni.showToast({ title: '付款已确认', icon: 'success' })
        emit('success')
        close()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '付款失败', icon: 'none' })
    } finally { submitting.value = false }
}

function close() { emit('update:show', false) }
const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
.modal-wrap { max-height: 90vh; display: flex; flex-direction: column; padding-bottom: env(safe-area-inset-bottom); }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 28rpx 32rpx 16rpx; }
.modal-title { font-size: 32rpx; font-weight: 700; color: #0f172a; display: block; }
.modal-subtitle { font-size: 26rpx; color: #64748b; display: block; margin-top: 4rpx; }
.summary-row { display: flex; gap: 0; margin: 0 32rpx 16rpx; background: #f8fafc; border-radius: 12rpx; overflow: hidden; }
.summary-item { flex: 1; padding: 14rpx 0; text-align: center; border-right: 1rpx solid #e2e8f0; &:last-child { border-right: none; } }
.summary-label { font-size: 22rpx; color: #94a3b8; display: block; }
.summary-value { font-size: 26rpx; font-weight: 600; color: #0f172a; display: block; margin-top: 4rpx; }
.summary-value.red { color: #dc2626; }
.summary-value.orange { color: #ea580c; }
.account-row { display: flex; align-items: center; gap: 16rpx; padding: 0 32rpx 16rpx; }
.account-label { font-size: 26rpx; color: #374151; width: 100rpx; flex-shrink: 0; }
.account-label.required::before { content: '*'; color: #dc2626; margin-right: 4rpx; }
.account-select { flex: 1; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border-radius: 8rpx; padding: 10rpx 16rpx; }
.account-text { font-size: 26rpx; color: #0f172a; }
.account-placeholder { font-size: 26rpx; color: #94a3b8; }
.account-arrow { font-size: 32rpx; color: #94a3b8; }
.items-loading { display: flex; justify-content: center; padding: 32rpx; }
.items-list { flex: 1; max-height: 420rpx; padding: 0 32rpx; }
.device-row { display: flex; align-items: flex-start; gap: 12rpx; padding: 16rpx 0; border-bottom: 1rpx solid #f1f5f9; }
.device-row__check { padding-top: 4rpx; }
.device-row__info { flex: 1; }
.device-row__model { font-size: 26rpx; font-weight: 600; color: #0f172a; }
.device-row__sub { font-size: 22rpx; color: #64748b; display: block; margin-top: 4rpx; }
.device-row__amounts { display: flex; gap: 12rpx; margin-top: 6rpx; flex-wrap: wrap; }
.amt-tiny { font-size: 22rpx; color: #94a3b8; }
.amt-tiny.orange { color: #ea580c; }
.device-row__input { flex-shrink: 0; }
.remark-row { padding: 0 32rpx 12rpx; }
.modal-actions { display: flex; gap: 16rpx; padding: 16rpx 32rpx; border-top: 1rpx solid #f1f5f9; }
.account-popup { padding: 32rpx; }
.account-popup__title { font-size: 30rpx; font-weight: 700; margin-bottom: 20rpx; }
.account-item { display: flex; justify-content: space-between; padding: 20rpx 0; border-bottom: 1rpx solid #f1f5f9; &.selected { color: #3b6ef5; } }
.account-item__name { font-size: 28rpx; }
.account-item__balance { font-size: 24rpx; color: #64748b; }
</style>
