<template>
    <view class="bg-[var(--page-bg-color)]  min-h-screen overflow-hidden" :style="themeColor()" v-if="detail">
        <view class="bg-linear pb-[340rpx]">
            <view class="text-[#fff] text-[32rpx] leading-[80rpx] text-center mb-[30rpx]">{{ detail.time }}</view>
            <view class="px-[var(--sidebar-m)]">
                <view class="text-[26rpx] text-[#fff] mb-[20rpx]">{{ detail.type == 'day' ? '当日结余': '当月结余' }}</view>
                <view class="text-[52rpx] text-[#fff]">{{ detail.balance_money }}</view>
            </view>
        </view>
        <view class="relative top-[-300rpx] sidebar-margin card-template mb-[var(--top-m)]">
           <view class="flex items-center justify-between mb-[20rpx]">
                <view class="bill-wrap leading-[40rpx] pl-[6rpx] text-[30rpx] font-500">实际收入</view>
                <view class="text-[30rpx] font-500">{{ detail.income_money }}</view>
           </view>
           <view class="text-[#999] text-[24rpx] leading-[34rpx] mb-[30rpx]">(实际收入=订单应收+平台优惠券补贴-订单应退-退还平台优惠券补贴)</view>
           <view class="card-template bg-[var(--page-bg-color)]">
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">订单应收</view>
                    <view class="text-[28rpx]">{{ detail.order_receivable_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">平台优惠券补贴</view>
                    <view class="text-[28rpx]">{{ detail.mall_coupon_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">订单应退</view>
                    <view class="text-[28rpx]">{{ detail.order_refund_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">退还平台优惠券补贴</view>
                    <view class="text-[28rpx]">{{ detail.mall_refund_coupon_money }}</view>
                </view>
           </view>
        </view>
        <view class="mt-[-290rpx] sidebar-margin card-template mb-[var(--top-m)]">
           <view class="flex items-center justify-between mb-[20rpx]">
                <view class="bill-wrap leading-[40rpx] pl-[6rpx] text-[30rpx] font-500">实际支出</view>
                <view class="text-[30rpx] font-500">{{ detail.outlay_money }}</view>
           </view>
           <view class="text-[#999] text-[24rpx] leading-[34rpx] mb-[30rpx]">(实际支出=支付手续费-退还手续费+支付佣金-退还佣金+支付供货商货款-退还供货商货款)</view>
           <view class="card-template bg-[var(--page-bg-color)]">
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">支付手续费</view>
                    <view class="text-[28rpx]">{{ detail.mall_service_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">退还手续费</view>
                    <view class="text-[28rpx]">{{ detail.mall_refund_service_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">支付佣金</view>
                    <view class="text-[28rpx]">{{ detail.shop_commission }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">退还佣金</view>
                    <view class="text-[28rpx]">{{ detail.shop_refund_commission }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">支付供货商货款</view>
                    <view class="text-[28rpx]">{{ detail.supply_money }}</view>
                </view>
                <view class="justify-between card-template-item">
                    <view class="text-[28rpx]">退还供货商货款</view>
                    <view class="text-[28rpx]">{{ detail.supply_refund_money }}</view>
                </view>
           </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { timeStampTurnTime } from '@/utils/common'
import { onLoad } from '@dcloudio/uni-app';
import { getFinanceAccountsDetail } from '@/app/api/stat'

const date = ref('')
const type = ref('')
const detail = ref<any>(null)
const loading = ref(true)
onLoad((option: any) => {
    date.value = option.date;
    type.value = option.type;
    getFinanceAccountsDetailFn()
})

const  getFinanceAccountsDetailFn = () => {
    loading.value = true;
    getFinanceAccountsDetail({
        date: date.value,
        type: type.value
    }).then((res: any) => {
        detail.value = res.data
        let date= new Date(detail.value.time)
        detail.value.time = date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日'
        loading.value = false;
    }).catch(() => {
        loading.value = false;
    })

}
</script>

<style lang="scss" scoped>
.bg-linear{
    background: linear-gradient(#067cf7 15%, var(--page-bg-color) 87%);
}
.bill-wrap{
    position: relative;
    &::after{
        content: '';
        position: absolute;
        left: -8rpx;
        top: 50%;
        transform: translateY(-50%);
        background-color: var(--primary-color);
        width: 6rpx;
        height: 30rpx;
        border-radius: 2rpx;
        
    }
}
</style>