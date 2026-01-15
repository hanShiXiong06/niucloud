<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()" v-if="!loading">
        <view class="sidebar-margin my-[var(--top-m)] card-template">
            <view class="title">支付信息</view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">交易流水号</view>
                <view class="text-[28rpx]">{{ payInfoData.out_trade_no }}</view>
            </view>
            <view class="justify-between card-template-item" v-if="payInfoData.trade_no && payInfoData.type !== 'balancepay'">
                <view class="text-[28rpx]">外部交易号</view>
                <view class="text-[28rpx]">{{ payInfoData.trade_no }}</view>
            </view>
            <view class="justify-between card-template-item !items-start">
                <view class="text-[28rpx] leading-[34rpx] flex-shrink-0 mr-[20rpx]">商品信息描述</view>
                <view class="text-[28rpx] leading-[34rpx] text-right">{{ payInfoData.body }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">支付金额</view>
                <view class="text-[28rpx]">{{ payInfoData.money }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">支付渠道</view>
                <view class="text-[28rpx]">{{ payInfoData.channel_name }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">支付类型</view>
                <view class="text-[28rpx]">{{ payInfoData.type_name }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">支付状态</view>
                <view class="text-[28rpx]">{{ payInfoData.status_name }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">支付时间</view>
                <view class="text-[28rpx]">{{ payInfoData.pay_time }}</view>
            </view>
            <view class="justify-between card-template-item" v-if="payInfoData.type == 'huifu_wechatpay' || payInfoData.type == 'huifu_alipay'">
                <view class="text-[28rpx]">汇付交易流水号</view>
                <view class="text-[28rpx]">{{ payInfoData.json?.hf_seq_id }}</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { getSitePayInfo } from '@/app/api/system'
import { onLoad } from '@dcloudio/uni-app'

const payInfoData = ref<any>({})
const loading = ref(true)
let order_id = ref('')
let order_type = ref('')

onLoad((option: any) => {
    order_id.value = option.order_id || ''
    order_type.value = option.order_type || ''
    getSitePayInfoFn({ trade_id: order_id.value, trade_type: order_type.value })
})
const getSitePayInfoFn = (data: any) => {
    loading.value = true
    getSitePayInfo(data).then((res: any) => {
        payInfoData.value = res.data
        if (payInfoData.value.json) {
            payInfoData.value.json = JSON.parse(payInfoData.value.json)
        }
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
</script>

<style scoped>

</style>