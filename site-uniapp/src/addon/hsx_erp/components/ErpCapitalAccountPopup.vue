<template>
    <u-popup
        :show="show"
        mode="bottom"
        :safe-area-inset-bottom="true"
        border-radius="24"
        @close="close"
    >
        <view class="account-popup">
            <view class="account-popup__head">
                <view>
                    <text class="account-popup__title">{{ title }}</text>
                    <text v-if="subtitle" class="account-popup__subtitle">{{ subtitle }}</text>
                </view>
                <view class="account-popup__close" @click="close">
                    <u-icon name="close" color="#64748b" size="20" />
                </view>
            </view>

            <scroll-view scroll-y class="account-popup__body">
                <u-cell-group v-if="accounts.length" :border="false">
                    <u-cell
                        v-for="account in accounts"
                        :key="account.id"
                        :title="account.account_name || '未命名账户'"
                        :label="'可用余额 ¥' + money(account.balance)"
                        clickable
                        @click="selectAccount(account)"
                    >
                        <template #value>
                            <u-icon
                                v-if="Number(account.id) === Number(modelValue)"
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
</template>

<script setup lang="ts">
withDefaults(defineProps<{
    show?: boolean
    modelValue?: number
    accounts?: any[]
    title?: string
    subtitle?: string
}>(), {
    show: false,
    modelValue: 0,
    accounts: () => [],
    title: '选择资金账户',
    subtitle: '',
})

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void
    (e: 'update:modelValue', value: number): void
    (e: 'change', account: any): void
}>()

function close() {
    emit('update:show', false)
}

function selectAccount(account: any) {
    emit('update:modelValue', Number(account?.id || 0))
    emit('change', account)
    close()
}

const money = (value: any) => Number(value || 0).toFixed(2)
</script>

<style scoped lang="scss">
.account-popup {
    background: #fff;
    border-radius: 28rpx 28rpx 0 0;
    overflow: hidden;
}
.account-popup__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    padding: 28rpx 28rpx 20rpx;
    border-bottom: 1rpx solid #eef2f7;
}
.account-popup__title,
.account-popup__subtitle {
    display: block;
}
.account-popup__title {
    color: #0f172a;
    font-size: 31rpx;
    font-weight: 750;
}
.account-popup__subtitle {
    margin-top: 6rpx;
    color: #64748b;
    font-size: 22rpx;
}
.account-popup__close {
    display: flex;
    width: 56rpx;
    height: 56rpx;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f8fafc;
}
.account-popup__body {
    max-height: 62vh;
    min-height: 220rpx;
    padding-bottom: 24rpx;
}
</style>
