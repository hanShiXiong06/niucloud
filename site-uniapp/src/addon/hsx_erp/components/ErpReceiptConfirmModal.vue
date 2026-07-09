<!--
  ErpReceiptConfirmModal - 应收款逐台确认弹窗（对齐PC端：可调售价 + 逐台收款）

  PC端关键逻辑：
  - 每台设备可修改销售价（调价后重新计算应收）
  - 每台独立填写本次收款金额
  - 合计 = sum(item.receipt_amount)

  用法：
    <ErpReceiptConfirmModal
      v-model:show="showReceiptModal"
      :receivableId="row.id"
      :partyName="row.party_name"
      :accounts="accounts"
      @success="onReceiptSuccess"
    />
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="modal-wrap">
            <view class="modal-header">
                <view>
                    <text class="modal-title">确认收款</text>
                    <text class="modal-subtitle">{{ partyName }}</text>
                </view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <!-- 汇总行 -->
            <view class="summary-row">
                <view class="summary-item">
                    <text class="summary-label">本次收款</text>
                    <text class="summary-value blue">¥{{ money(totalReceipt) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">收后剩余</text>
                    <text class="summary-value orange">¥{{ money(totalAfter) }}</text>
                </view>
                <view class="summary-item">
                    <text class="summary-label">已选设备</text>
                    <text class="summary-value">{{ checkedCount }} 台</text>
                </view>
            </view>

            <!-- 账户选择 -->
            <view class="account-row" @click="showAccountPicker = true">
                <text class="account-label required">收款账户</text>
                <view class="account-select" :class="{ 'account-select--on': form.capital_account_id }">
                    <text :class="form.capital_account_id ? 'account-text' : 'account-placeholder'">
                        {{ selectedAccountLabel || '点击选择账户' }}
                    </text>
                    <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                </view>
            </view>

            <!-- 设备明细（对齐PC端：可调售价） -->
            <view v-if="loadingItems" class="items-loading"><u-loading-icon size="24" /></view>
            <scroll-view v-else scroll-y class="items-list">
                <view v-for="item in items" :key="item.id" class="device-row">
                    <view class="device-row__check">
                        <u-checkbox
                            :checked="item.checked"
                            :disabled="Number(item.allocated_remain || 0) <= 0"
                            @change="onCheck(item, $event)"
                        />
                    </view>
                    <view class="device-row__info">
                        <text class="device-row__model">{{ item.model }}</text>
                        <text class="device-row__sub">{{ item.spec || '-' }} · {{ item.imei }}</text>
                        <text v-if="sourceType === 'purchase_return'" class="device-row__sub">
                            退货金额 ¥{{ money(item.return_cost) }} · 已付 ¥{{ money(item.paid_amount) }}
                        </text>
                        <text v-if="item.reason" class="device-row__reason">原因：{{ item.reason }}</text>
                        <view class="device-row__amounts">
                            <text class="amt-tiny">已收 ¥{{ money(item.allocated_settled) }}</text>
                            <text class="amt-tiny blue">余 ¥{{ money(itemRemain(item)) }}</text>
                        </view>
                    </view>
                    <view class="device-row__input">
                        <text class="price-edit-label">{{ amountLabel }}</text>
                        <u-input
                            v-model="item.sale_price"
                            type="number"
                            :disabled="sourceType !== 'sale'"
                            :customStyle="priceInputStyle"
                            @blur="onPriceChange(item)"
                        />
                        <text class="price-edit-label mt">本次收款</text>
                        <u-input
                            v-model="item.receipt_amount"
                            type="number"
                            :disabled="!item.checked"
                            :placeholder="money(itemRemain(item))"
                            :customStyle="priceInputStyle"
                            @blur="capReceiptAmount(item)"
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
                <view class="action-btn action-btn--minor">
                    <u-button @click="close">取消</u-button>
                </view>
                <view class="action-btn action-btn--major">
                    <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">
                        确认收款 ¥{{ money(totalReceipt) }}
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
            </view>
        </u-popup>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { getMobileReceivableItems, confirmMobileSaleReceipt } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    show: boolean
    receivableId?: number
    partyName?: string
    accounts?: any[]
}>(), {
    show: false,
    receivableId: 0,
    partyName: '',
    accounts: () => [],
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'success'): void
}>()

const items = ref<any[]>([])
const sourceType = ref('')
const loadingItems = ref(false)
const submitting = ref(false)
const showAccountPicker = ref(false)
const form = ref({ capital_account_id: 0, remark: '' })

const priceInputStyle = { width: '140rpx', background: '#f8fafc', borderRadius: '8rpx', padding: '6rpx 12rpx' }
const remarkStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx', marginTop: '12rpx' }

const itemRemain = (item: any) => Math.max(0, Number(item.sale_price || 0) - Number(item.allocated_settled || 0))
const totalReceipt = computed(() => items.value.reduce((s, i) => s + (i.checked ? Number(i.receipt_amount || 0) : 0), 0))
const totalAfter = computed(() => items.value.reduce((s, i) => {
    const remain = itemRemain(i)
    const receipt = i.checked ? Number(i.receipt_amount || 0) : 0
    return s + Math.max(0, remain - receipt)
}, 0))
const checkedCount = computed(() => items.value.filter(i => i.checked && Number(i.receipt_amount || 0) > 0).length)
const canSubmit = computed(() => totalReceipt.value > 0 && form.value.capital_account_id > 0)
const selectedAccountLabel = computed(() => {
    const a = props.accounts.find(a => a.id === form.value.capital_account_id)
    return a ? `${a.account_name}（¥${money(a.balance)}）` : ''
})
const amountLabel = computed(() => sourceType.value === 'purchase_return' ? '应退金额' : '售价')

let loadToken = 0

watch(
    () => [props.show, props.receivableId],
    () => {
        if (props.show && props.receivableId > 0) openModal()
    },
    { immediate: true }
)

watch(() => props.accounts, () => {
    if (props.show && !form.value.capital_account_id) {
        form.value.capital_account_id = props.accounts[0]?.id || 0
    }
})

function openModal() {
    form.value = { capital_account_id: props.accounts[0]?.id || 0, remark: '' }
    items.value = []
    sourceType.value = ''
    loadItems()
}

async function loadItems() {
    const token = ++loadToken
    loadingItems.value = true
    try {
        const res: any = await getMobileReceivableItems(props.receivableId)
        if (token !== loadToken) return
        const data = res?.data || {}
        sourceType.value = data.source_type || ''
        items.value = (data.items || []).map((row: any) => ({
            ...row,
            sale_price: Number(row.sale_price || 0),
            checked: Number(row.allocated_remain) > 0,
            receipt_amount: Number(Number(row.allocated_remain || 0).toFixed(2)),
        }))
    } finally {
        if (token === loadToken) loadingItems.value = false
    }
}

function onCheck(item: any, checked: any) {
    item.checked = normalizeChecked(checked)
    if (item.checked) {
        item.receipt_amount = Number(Number(itemRemain(item)).toFixed(2))
    } else {
        item.receipt_amount = 0
    }
}

function normalizeChecked(checked: any) {
    if (typeof checked === 'boolean') return checked
    if (checked && typeof checked === 'object' && 'value' in checked) return !!checked.value
    if (checked && typeof checked === 'object' && 'detail' in checked) return !!checked.detail?.value
    return !!checked
}

function onPriceChange(item: any) {
    if (sourceType.value !== 'sale') return
    // 售价变化后重新计算本次收款上限
    const remain = itemRemain(item)
    if (item.checked) {
        item.receipt_amount = Number(remain.toFixed(2))
    } else if (Number(item.receipt_amount) > remain) {
        item.receipt_amount = Number(remain.toFixed(2))
    }
}

function capReceiptAmount(item: any) {
    const max = itemRemain(item)
    if (Number(item.receipt_amount) > max) {
        item.receipt_amount = Number(max.toFixed(2))
        uni.showToast({ title: `不能超过剩余应收 ¥${money(max)}`, icon: 'none' })
    }
    if (Number(item.receipt_amount) < 0) item.receipt_amount = 0
}

function selectAccount(a: any) {
    form.value.capital_account_id = a.id
    showAccountPicker.value = false
}

async function submit() {
    const overItem = items.value.find(i => i.checked && Number(i.receipt_amount || 0) > itemRemain(i) + 0.0001)
    if (overItem) {
        capReceiptAmount(overItem)
        return
    }
    submitting.value = true
    try {
        const receiptItems = items.value
            .filter(i => i.checked && Number(i.receipt_amount || 0) > 0)
            .map(i => ({
                id: i.id,
                sale_item_id: sourceType.value === 'sale' ? i.id : 0,
                asset_id: i.asset_id || i.id,
                sale_price: Number(i.sale_price),
                amount: Number(i.receipt_amount),
            }))

        await confirmMobileSaleReceipt(props.receivableId, {
            amount: totalReceipt.value,
            capital_account_id: form.value.capital_account_id,
            remark: form.value.remark || '手机端确认收款',
            items: receiptItems,
        })
        uni.showToast({ title: '收款已确认', icon: 'success' })
        emit('success')
        close()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '收款失败', icon: 'none' })
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
.summary-row { display: flex; margin: 0 32rpx 16rpx; background: #f8fafc; border-radius: 12rpx; overflow: hidden; }
.summary-item { flex: 1; padding: 14rpx 0; text-align: center; border-right: 1rpx solid #e2e8f0; &:last-child { border-right: none; } }
.summary-label { font-size: 22rpx; color: #94a3b8; display: block; }
.summary-value { font-size: 26rpx; font-weight: 600; color: #0f172a; display: block; margin-top: 4rpx; }
.summary-value.blue { color: #2563eb; }
.summary-value.orange { color: #ea580c; }
.account-row { display: flex; align-items: center; gap: 16rpx; padding: 0 32rpx 16rpx; }
.account-label { font-size: 26rpx; color: #374151; width: 150rpx; flex-shrink: 0; }
.account-label.required::before { content: '*'; color: #dc2626; margin-right: 4rpx; }
.account-select { flex: 1; min-height: 72rpx; display: flex; align-items: center; justify-content: space-between; gap: 16rpx; background: #f8fafc; border: 2rpx solid transparent; border-radius: 12rpx; padding: 0 18rpx; box-sizing: border-box; }
.account-select--on { background: #f8fbff; border-color: #3b6ef5; }
.account-text { flex: 1; min-width: 0; font-size: 26rpx; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.account-placeholder { flex: 1; min-width: 0; font-size: 26rpx; color: #94a3b8; }
.items-loading { display: flex; justify-content: center; padding: 32rpx; }
.items-list { flex: 1; max-height: 420rpx; padding: 0 32rpx; box-sizing: border-box; }
.device-row { display: flex; align-items: flex-start; gap: 12rpx; padding: 16rpx 0; border-bottom: 1rpx solid #f1f5f9; box-sizing: border-box; }
.device-row__check { padding-top: 4rpx; }
.device-row__info { flex: 1; }
.device-row__model { font-size: 26rpx; font-weight: 600; color: #0f172a; }
.device-row__sub { font-size: 22rpx; color: #64748b; display: block; margin-top: 4rpx; }
.device-row__reason { font-size: 22rpx; color: #ea580c; display: block; margin-top: 4rpx; }
.price-edit-row { display: flex; align-items: center; gap: 8rpx; margin-top: 8rpx; }
.price-edit-label { font-size: 22rpx; color: #94a3b8; }
.price-edit-label.mt { display: block; margin-top: 8rpx; }
.device-row__amounts { display: flex; gap: 16rpx; margin-top: 6rpx; }
.amt-tiny { font-size: 22rpx; color: #94a3b8; }
.amt-tiny.blue { color: #2563eb; }
.device-row__input { flex-shrink: 0; }
.remark-row { padding: 0 32rpx 12rpx; }
.modal-actions { display: flex; gap: 16rpx; padding: 16rpx 32rpx; border-top: 1rpx solid #f1f5f9; }
.action-btn { min-width: 0; }
.action-btn--minor { flex: 1; }
.action-btn--major { flex: 2; }
.account-popup {
    min-height: 36vh;
    max-height: 74vh;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
}
.account-popup__head {
    min-height: 96rpx;
    padding: 0 28rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}
.account-popup__title { font-size: 30rpx; font-weight: 700; color: #0f172a; }
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
