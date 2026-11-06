<template>
    <view class="coop-payment-summary" :class="{'is-embedded':embedded}">
        <view class="coop-payment-purchase">
            <view class="coop-payment-kind"><view class="coop-payment-symbol"><u-icon :name="completed ? 'checkmark-circle' : 'order'" size="23" color="var(--primary-color, #3478cc)" /></view><view><text class="coop-payment-name">协作佣金</text><text class="coop-payment-caption">客户货款由门店与客户另行结算</text></view></view>
            <view class="coop-payment-total"><text>{{ completed ? '本单已付款' : orderCreated ? '本单实付金额' : '本次实付' }}</text><view class="coop-payment-amount">{{ coopMoney(amount) }}</view><text v-if="loading" class="coop-payment-loading">正在核对本笔付款结果…</text></view>
        </view>
        <view v-if="error" class="coop-payment-feedback is-error" role="alert">{{ error }}</view>
        <view v-else-if="message" class="coop-payment-feedback" :class="{ 'is-confirmed': completed }" role="status">{{ message }}</view>
        <view v-else-if="!loading" class="coop-payment-next">{{ orderCreated ? '按原付款单继续支付，请勿重复创建。' : '确认金额后创建协作佣金付款单。' }}</view>
    </view>
</template>
<script setup lang="ts">
import { coopMoney } from '../../api/cooperation'
withDefaults(defineProps<{purpose?:'commission';amount?:string|number;loading?:boolean;orderCreated?:boolean;completed?:boolean;message?:string;error?:string;embedded?:boolean}>(),{purpose:'commission',embedded:false})
</script>
<style scoped lang="scss">
.coop-payment-summary { padding: 32rpx 28rpx; color: #28384e; }
.coop-payment-summary.is-embedded { padding: 0; }.coop-payment-summary.is-embedded .coop-payment-purchase { padding: 0; }.coop-payment-summary.is-embedded .coop-payment-total { padding: 20rpx 0; }
.coop-payment-purchase { background: #fff; padding: 34rpx 30rpx 8rpx; border-radius: 16rpx; }
.coop-payment-kind { display: flex; gap: 20rpx; align-items: center; }
.coop-payment-symbol { width: 76rpx; height: 76rpx; flex: none; background: #edf4ff; border-radius: 16rpx; display: flex; align-items: center; justify-content: center; }
.coop-payment-name { display: block; font-size: 32rpx; font-weight: 600; line-height: 1.6; }
.coop-payment-caption { display: block; margin-top: 4rpx; font-size: 23rpx; line-height: 1.7; color: #8190a3; }
.coop-payment-total { padding: 42rpx 0 32rpx; }
.coop-payment-total>text { font-size: 24rpx; color: #738297; }
.coop-payment-amount { margin-top: 12rpx; font-size: 60rpx; line-height: 1.4; color: #24394f; font-weight: 650; overflow-wrap: anywhere; }
.coop-payment-loading { display:block; margin-top:12rpx; font-size:23rpx; line-height:1.7; color:#7d8c9e; }
.coop-payment-feedback { margin-top: 24rpx; padding: 24rpx; border-radius: 12rpx; background: #fff3e9; color: #906548; font-size: 25rpx; line-height: 1.8; overflow-wrap: anywhere; }
.coop-payment-feedback.is-confirmed { background: #edf6f1; color: #447c60; }
.coop-payment-feedback.is-error { background: #fff0ee; color: #a45348; }
.coop-payment-next { padding: 24rpx 4rpx; font-size: 24rpx; line-height: 1.8; color: #7d8c9e; }
</style>
