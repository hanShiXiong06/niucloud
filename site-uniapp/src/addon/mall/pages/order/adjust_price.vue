<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()" v-if="detail">
        <view class="bg-[#eee] h-[60rpx] px-[24rpx] flex items-center mb-[20rpx]">
            <text class="text-[22rpx]">订单编号： {{ detail.order_no }}</text>
            <text class="ml-[20rpx] text-[22rpx]">{{ detail.create_time }}</text>
        </view>
        <view class="sidebar-margin">
            <view class="card-template mb-[20rpx]" v-for="(item, index) in detail.order_goods" :key="index">
                <view class="flex mb-[30rpx]">
                    <up-image class="rounded-[var(--goods-rounded-big)] overflow-hidden" width="100rpx" height="100rpx" :src="img(item.goods_image)" mode="aspectFill">
                        <template #error>
                            <image class="w-[100rpx] h-[100rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                        </template>
                    </up-image>
                    <view class="flex flex flex-col justify-between ml-[16rpx]">
                        <view class="text-[26rpx] using-hidden">{{ item.goods_name }}</view>
                        <view class="text-[24rpx] text-[#999]">￥{{ item.goods_money }} = {{ item.price }} × {{  item.num }} </view>
                        <view class="text-[24rpx] text-[#999]">
                            <text>优惠： ￥{{ item.discount_money }}</text>
                            <text class="ml-[20rpx]">平台优惠：￥{{ parseFloat(item.mall_discount_money).toFixed(2) }}</text>
                        </view>
                    </view>
                </view>
                <view class="flex items-center">
                    <view class="flex items-center h-[40rpx] bg-[var(--page-bg-color)] text-[24rpx] rounded-[8rpx] mr-[30rpx]">
                        <text class="w-[80rpx] h-full rounded-[8rpx] leading-[40rpx] text-center" :class="{'text-[#fff] bg-[#38cb33]': item.type == 'jian'}" @click="handleType(item, 'jian')">减</text>
                        <text class="w-[80rpx] h-full rounded-[8rpx] leading-[40rpx] text-center" :class="{'text-[#fff] bg-[#f98249]': item.type == 'jia'}" @click="handleType(item, 'jia')">涨</text>
                    </view>
                    <view class="w-[140rpx] h-[40rpx]  bg-[var(--page-bg-color)] text-[24rpx] rounded-[8rpx] ">
                        <input class="text-[24rpx] h-full text-center" type="digit" placeholder="0.00" v-model="item.adjust_money" @input="(event) => {handleInput(event, item)}" />
                    </view>
                </view>
            </view>
        </view>
        <view class="tab-bar-placeholder"></view>
        <view class="fixed bottom-[0] left-[0] right-[0] px-[24rpx] py-[30rpx] bg-[#fff] tab-bar">
            <view class="flex items-center mb-[30rpx]">
                <view class="flex items-center mr-[30rpx]">
                    <text class="text-[26rpx] mr-[20rpx]">免邮</text>
                    <up-switch v-model="detail.is_free" size="20"  @change="handleFree"></up-switch>
                </view>
                <view class="flex items-center">
                    <text class="text-[26rpx] mr-[20rpx]">运费</text>
                    <view class="w-[160rpx] h-[50rpx]  bg-[var(--page-bg-color)] text-[24rpx] rounded-[8rpx] px-[20rpx]">
                        <input class="text-[24rpx] h-full" placeholder="0.00" v-model="deliveryMoney" :disabled="detail.is_free" />
                    </view>
                </view>
            </view>
            <view class="text-[24rpx] mb-[20rpx]">
                <text>计价方式（￥）：{{detail.all_goods_money }} + {{ deliveryMoney }}</text>
                <text class="text-[var(--price-text-color)]">{{ adjustMoney }}</text>
                <text v-if="Number(detail.discount_money)"> - {{ detail.discount_money }}</text>
                <text v-if="Number(detail.mall_discount_money)"> - {{ detail.mall_discount_money }}</text>
            </view>
            <view class="text-[24rpx] mb-[20rpx]">实收金额（￥）：<text class="text-[var(--price-text-color)]">{{ total }}</text></view>
            <view class="bg-[var(--page-bg-color)] p-[20rpx] rounded-[16rpx] mb-[20rpx]">
                <view class="flex  text-[24rpx] mb-[20rpx]">
                    <text class="w-[120rpx] flex-shrink-0 text-[var(--text-color-light6)] leading-[30rpx]">收货地址</text>
                    <text class="leading-[30rpx]">{{ detail.taker_full_address }}</text>
                </view>
                <view class="flex items-center text-[24rpx] mb-[20rpx]">
                    <text class="w-[120rpx] flex-shrink-0 text-[var(--text-color-light6)]">买家备注</text>
                    <text class="leading-[30rpx]">{{ detail.member_remark || '-' }}</text>
                </view>
                <view class="flex items-center text-[24rpx]">
                    <text class="w-[120rpx] flex-shrink-0 text-[var(--text-color-light6)]">卖家备注</text>
                    <text class="leading-[30rpx]">{{ detail.shop_remark || '-' }}</text>
                </view>
            </view>
            <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] text-[26rpx] font-500"  @click="save">确定</button>
        </view>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed,nextTick } from 'vue';
import { onLoad } from '@dcloudio/uni-app'
import {  img, filterDigit, redirect } from '@/utils/common';
import { getOrderDetail, orderEditPrice } from '@/addon/mall/api/order';

const orderId = ref('')
const detail = ref<any>(null);
const loading = ref(true);
const deliveryMoney = ref<any>('')
onLoad((option:any) => {
    orderId.value = option.order_id;
    orderDetailFn(orderId.value);
});

const orderDetailFn = (id: any) => {
    loading.value = true;
    getOrderDetail(id).then((res: any) => {
        detail.value = res.data;
        deliveryMoney.value = Number(detail.value.delivery_money).toFixed(2);
        let val = 0
        detail.value.order_goods.forEach((item:any) => {
            item.type = 'jian'
            item.adjust_money = '';
            val+= Number(item.goods_money)
        })
        detail.value.all_goods_money = val
        if(Number(detail.value.delivery_money) > 0){
            detail.is_free = false
        } else {
            detail.is_free = true
        }
        loading.value = false;
    }).catch((err: any) => {
        loading.value = false;
    })
}

const handleType = (data: any, type: string) => {
    data.type = type;
}

const handleFree = (data: any) => {
    if(data){
        deliveryMoney.value = Number(0).toFixed(2);
    } else {
        deliveryMoney.value = Number(detail.value.delivery_money).toFixed(2);
    }
}
const handleInput = (e: any, data: any) => {
    nextTick(() => {
        data.adjust_money = filterDigit(e)
        if(data.type == 'jian'){
            if(Number(data.adjust_money) > (Number(data.goods_money)- Number(data.discount_money) - Number(data.mall_discount_money))) {
                data.adjust_money = (Number(data.goods_money)- Number(data.discount_money) - Number(data.mall_discount_money)).toFixed(2);
            }
        }
    })
    
}
const adjustMoney = computed(() => { 
    let val = 0
     detail.value && detail.value.order_goods.forEach((item:any) => {
        if(item.type == 'jia'){
            val += Number(item.adjust_money)
        } else {
            val -= Number(item.adjust_money)
        }
    })

    if(val >= 0){
        return ` + ${val.toFixed(2)}`
    } else {
       return `${val.toFixed(2)}` 
    }
})

const total = computed(() => { 
    let val = 0
    detail.value && detail.value.order_goods.forEach((item:any) => {
        val += (Number(item.goods_money) - Number(item.discount_money) - Number(item.mall_discount_money))
        if(item.type == 'jia'){
            val += Number(item.adjust_money)
        } else {
            val -= Number(item.adjust_money)
        }
    })
    if(!detail.value.is_free){
        val += Number(deliveryMoney.value)
    }
    return val.toFixed(2)
})

const isRepeat = ref(false)
const save = () => {
    
    if (isRepeat.value) return
    isRepeat.value = true

    let order_goods_data:any = {}
    detail.value.order_goods.forEach((item:any) => {
        if (item.adjust_money) {
            order_goods_data[item.order_goods_id] = {
                money: item.type == 'jia' ? item.adjust_money : -item.adjust_money
            }
        }
    })
    orderEditPrice({
        order_id:  detail.value.order_id,
        delivery_money: parseFloat(deliveryMoney.value),
        order_goods_data
    }).then((res: any) => {
        isRepeat.value = false
        if (getCurrentPages().length > 1) {
            uni.navigateBack({
                delta: 1
            });
        } else {
            redirect({url: '/addon/mall/pages/order/list'});
        }
    }).catch(() => {
        isRepeat.value = false
    })
}


</script>

<style lang="scss" scoped>
.tab-bar-placeholder {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 528rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 528rpx);
}

.tab-bar {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 30rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 30rpx);
}
</style>