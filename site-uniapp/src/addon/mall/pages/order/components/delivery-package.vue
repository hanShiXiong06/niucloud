<template>
    <view @touchmove.prevent.stop>
        <u-popup :show="show" @close="show = false" mode="bottom" :round="10">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">查看物流</view>
                <scroll-view :scroll-y="true" class="h-[60vh]">
                    <scroll-view :scroll-x="true" scroll-with-animation :scroll-into-view="'id' + (actvie > 3 ? actvie - 2 : 0)">
                        <view class="flex p-[20rpx] whitespace-nowrap" v-if="packageList.length > 1">
                            <view :id="'id' + index" class="w-[120rpx] h-[50rpx] flex-center bg-[var(--page-bg-color)] rounded-[12rpx] text-[26rpx] border-solid border-[1rpx] border-[#999] text-[#999] mr-[30rpx]" :class="{'!text-primary !border-primary': item.id ==  curData.id}" v-for="(item,index) in packageList" :key="index" @click="handleClick(item,index)">{{ item.name }}</view>
                        </view>
                    </scroll-view>
                    <view class="sidebar-margin p-[20rpx] bg-[#E0EEFF] rounded-[var(--rounded-big)] box-border mb-[30rpx]" v-if="packageData">
                        <view class="flex items-center text-[24rpx] font-500 mb-[20rpx]">
                            <text class="mr-[10rpx]" v-if="packageData.company">{{ packageData.company.company_name }}</text>
                            <view class="flex items-center" @click="copy(packageData.express_number)" v-if="packageData.express_number">
                                <text class="mr-[10rpx]">{{ packageData.express_number }}</text>
                                <text class="nc-iconfont nc-icon-fuzhiV6xx1 text-primary text-[24rpx]"></text>
                            </view>
                        </view>
                        <view>
                            <view class="bg-[var(--page-bg-color)] p-[20rpx] rounded-[var(--rounded-big)] flex border-solid border-[1rpx] border-[#fff]" :class="{'mb-[20rpx]': (index + 1) != packageData.order_goods.length }" v-for="(item,index) in packageData.order_goods">
                                <up-image class="rounded-[var(--goods-rounded-small)] overflow-hidden" width="60rpx" height="60rpx" :src="img(item.goods_image)" mode="aspectFill">
                                    <template #error>
                                        <image class="w-[60rpx] h-[60rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                    </template>
                                </up-image>
                                <view class="ml-[20rpx] flex-1 flex flex-col justify-between">
                                    <view class="flex items-center justify-between text-[24rpx]">
                                        <text class="max-w-[400rpx] truncate">{{ item.goods_name }}</text>
                                        <text class="text-[#999]">{{ item.price }}</text>
                                    </view>
                                    <view class="flex items-center justify-between text-[24rpx]">
                                        <view class="flex-1 flex items-center">
                                            <text class="text-[#999] max-w-[200rpx] mr-[30rpx]" v-if="item.sku_name">{{ item.sku_name }}</text>
                                            <text class="mr-[10rpx]">合计</text>
                                            <text class="font-500">￥{{ (Number(item.price) * Number(item.num)).toFixed(2) }}</text>
                                        </view>
                                        <text class="text-[#999] text-[24rpx]">×{{ item.num }}</text>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                    <view class="sidebar-margin parcel" v-if="packageData">
                        <scroll-view  :scroll-y="true" class="" v-if="packageData.traces">
                            <u-steps current="0" dot direction="column" activeColor="var(--primary-color)" v-if="packageData.traces.list">
                                <template v-for="(item,index) in packageData.traces.list">
                                    <u-steps-item :title="item.remark" :desc="item.datetime"></u-steps-item>
                                </template>
                            </u-steps>
                            <view>{{ packageData.traces.reason}}</view>
                        </scroll-view>
                        <view style="height: 53vh;" v-else>
                            <view class="h-[56vh] flex-col flex items-center justify-center">
                                <text class="nc-iconfont nc-icon-daishouhuoV6xx text-[180rpx] text-[#bfbfbf]"></text>
                                <view class="text-[28rpx] text-[#bfbfbf] leading-8">无需物流～～</view>
                            </view>
                        </view>
                    </view>
                </scroll-view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import {img, redirect, copy} from '@/utils/common';
import { deliveryPackage } from '@/addon/mall/api/order'
const show = ref(false)
const  packageList = reactive<any>([]) // 快递列表
const packageData = ref<any>(null)
const curData = ref<any>(null)
const actvie = ref(0)

const open = (data: any) => {
    Object.assign(packageList, data)
	curData.value = packageList[0]
    deliveryPackageFn(curData.value)
    show.value = true
}

const handleClick = (item: any,index:number) => {
    curData.value = item
    actvie.value = index
    deliveryPackageFn(item)
}


const deliveryPackageFn = (data: any) => {
    deliveryPackage({
        id: data.id,
        mobile: data.mobile
    }).then((res: any) => {
        packageData.value = res.data
    })
}

defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.parcel :deep(.u-text__value) {
    font-size: 24rpx !important;
    line-height: 42rpx !important;
}

.parcel :deep(.u-steps-item__content) {
    margin-left: 20rpx !important;
}
</style>