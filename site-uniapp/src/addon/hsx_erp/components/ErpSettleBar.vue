<!--
  ErpSettleBar - 结算区域组件（挂账/现结切换 + 金额 + 账户）

  对齐 PC 端：settle_mode 切换时自动同步金额；金额变化时 cash 模式同步

  用法：
    <ErpSettleBar
      v-model:settle-mode="form.settle_mode"
      v-model:amount="form.paid_amount"
      v-model:account-id="form.capital_account_id"
      :total="totalCost"
      :accounts="accounts"
      label-cash="本次付款"     // 或 "本次收款"
      label-credit="全部挂账"
    />
-->
<template>
    <view class="settle-bar">
        <!-- 挂账/现结 切换 -->
        <view class="settle-mode-row">
            <text class="settle-label">结算方式</text>
            <view class="settle-tabs">
                <view
                    class="settle-tab"
                    :class="{ active: settleMode === 'credit' }"
                    @click="setMode('credit')"
                >
                    挂账
                </view>
                <view
                    class="settle-tab"
                    :class="{ active: settleMode === 'cash' }"
                    @click="setMode('cash')"
                >
                    现结
                </view>
            </view>
        </view>

        <!-- 现结时展开：金额 + 账户 -->
        <template v-if="settleMode === 'cash'">
            <view class="settle-amount-row">
                <text class="settle-label required">{{ labelCash }}</text>
                <view class="settle-amount-input">
                    <u-input
                        v-model="localAmount"
                        type="number"
                        :placeholder="'最多 ¥' + money(total)"
                        :customStyle="inputStyle"
                        @blur="onAmountBlur"
                    />
                    <text class="settle-max" @click="fillMax">全额</text>
                </view>
            </view>
            <view class="settle-account-row">
                <text class="settle-label required">付款账户</text>
                <view class="settle-account-select" :class="{ 'settle-account-select--on': accountId }" @click="showAccountPicker = true">
                    <text :class="accountId ? 'settle-account-text' : 'settle-placeholder'">
                        {{ selectedAccountLabel || '点击选择账户' }}
                    </text>
                    <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                </view>
            </view>
        </template>

        <!-- 挂账提示 -->
        <view v-else class="settle-credit-hint">
            <text class="settle-credit-text">全部挂账，后续通过应付款确认付款</text>
        </view>

        <!-- 金额汇总 -->
        <view class="settle-summary">
            <view class="settle-summary-item">
                <text class="settle-summary-label">合计</text>
                <text class="settle-summary-value">¥{{ money(total) }}</text>
            </view>
            <view class="settle-summary-item" v-if="settleMode === 'cash'">
                <text class="settle-summary-label">本次{{ labelCash.replace('本次', '') }}</text>
                <text class="settle-summary-value green">¥{{ money(localAmount) }}</text>
            </view>
            <view class="settle-summary-item" v-if="settleMode === 'cash'">
                <text class="settle-summary-label">剩余挂账</text>
                <text class="settle-summary-value orange">¥{{ money(remaining) }}</text>
            </view>
        </view>

        <!-- 账户选择弹窗 -->
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
                                v-if="Number(a.id) === Number(accountId)"
                                name="checkmark-circle-fill"
                                color="#3b6ef5"
                                size="20"
                            />
                        </template>
                    </u-cell>
                </u-cell-group>
                <u-empty v-else mode="data" text="暂无账户" />
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = withDefaults(defineProps<{
    settleMode?: 'credit' | 'cash'
    amount?: number
    accountId?: number
    total?: number
    accounts?: any[]
    labelCash?: string
    labelCredit?: string
}>(), {
    settleMode: 'credit',
    amount: 0,
    accountId: 0,
    total: 0,
    accounts: () => [],
    labelCash: '本次付款',
    labelCredit: '全部挂账',
})

const emit = defineEmits<{
    (e: 'update:settleMode', v: 'credit' | 'cash'): void
    (e: 'update:amount', v: number): void
    (e: 'update:accountId', v: number): void
}>()

const showAccountPicker = ref(false)
const localAmount = ref(props.amount)
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx', flex: '1' }

const remaining = computed(() => Math.max(0, props.total - Number(localAmount.value || 0)))
const selectedAccountLabel = computed(() => {
    const a = props.accounts.find(a => a.id === props.accountId)
    return a ? `${a.account_name}（¥${money(a.balance)}）` : ''
})

// PC 端对齐：切换到 cash 时自动填满
function setMode(mode: 'credit' | 'cash') {
    emit('update:settleMode', mode)
    if (mode === 'cash') {
        localAmount.value = props.total
        emit('update:amount', props.total)
    } else {
        localAmount.value = 0
        emit('update:amount', 0)
        emit('update:accountId', 0)
    }
}

// PC 端对齐：total 变化时，cash 模式同步更新金额
watch(() => props.total, (newTotal) => {
    if (props.settleMode === 'cash') {
        localAmount.value = newTotal
        emit('update:amount', newTotal)
    }
})

watch(localAmount, (v) => emit('update:amount', Number(v || 0)))

function onAmountBlur() {
    const max = props.total
    if (Number(localAmount.value) > max) {
        localAmount.value = max
        emit('update:amount', max)
    }
}

function fillMax() {
    localAmount.value = props.total
    emit('update:amount', props.total)
}

function selectAccount(a: any) {
    emit('update:accountId', a.id)
    showAccountPicker.value = false
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
.settle-bar {
    background: #fff;
    border-radius: 28rpx;
    padding: 20rpx 24rpx;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,.04);
}
.settle-mode-row, .settle-amount-row, .settle-account-row {
    display: flex;
    align-items: center;
    gap: 16rpx;
    margin-bottom: 16rpx;
}
.settle-label {
    font-size: 26rpx;
    color: #374151;
    width: 150rpx;
    flex-shrink: 0;
    &.required::before { content: '*'; color: #dc2626; margin-right: 4rpx; }
}
.settle-tabs { display: flex; border: 1rpx solid #e2e8f0; border-radius: 8rpx; overflow: hidden; }
.settle-tab {
    padding: 8rpx 28rpx;
    font-size: 26rpx;
    color: #64748b;
    &.active { background: #3b6ef5; color: #fff; }
}
.settle-amount-input { flex: 1; display: flex; align-items: center; gap: 12rpx; }
.settle-max { font-size: 24rpx; color: #3b6ef5; white-space: nowrap; }
.settle-account-select {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    background: #f8fafc;
    border: 2rpx solid transparent;
    border-radius: 12rpx;
    min-height: 72rpx;
    padding: 0 18rpx;
    box-sizing: border-box;
}
.settle-account-select--on { background: #f8fbff; border-color: #3b6ef5; }
.settle-account-text { flex: 1; min-width: 0; font-size: 26rpx; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.settle-placeholder { flex: 1; min-width: 0; font-size: 26rpx; color: #94a3b8; }
.settle-credit-hint {
    background: #f0fdf4;
    border-radius: 8rpx;
    padding: 12rpx 16rpx;
    margin-bottom: 16rpx;
}
.settle-credit-text { font-size: 24rpx; color: #16a34a; }
.settle-summary {
    border-top: 1rpx solid #f1f5f9;
    padding-top: 12rpx;
    display: flex;
    gap: 24rpx;
    flex-wrap: wrap;
}
.settle-summary-item { display: flex; gap: 8rpx; align-items: baseline; }
.settle-summary-label { font-size: 24rpx; color: #94a3b8; }
.settle-summary-value { font-size: 28rpx; font-weight: 600; color: #0f172a; }
.settle-summary-value.green { color: #16a34a; }
.settle-summary-value.orange { color: #ea580c; }
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
