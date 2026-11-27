<template>
    <view class="bg-[#fff] min-h-screen overflow-hidden" :style="themeColor()" v-if="Object.keys(detail).length">
        <view class="sidebar-margin">
            <view class="text-[24rpx] my-[30rpx]">订单号：{{ detail.order_no }}</view>
            <view class="mb-[50rpx]">
                <view class="flex mb-[20rpx]" v-for="(item,index) in  refundTable" :key="index">
                    <text v-if="item.disabled" class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 ':item.checked,'bg-[#F5F5F5]':!item.checked}"></text>
                    <text v-else class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0 box-border" :class="{ 'iconxuanze1 text-primary':item.checked,'border-[#ddd] border-solid border-[2rpx]':!item.checked}" @click="changeItem(item)"></text>
                    <view class="flex-1 flex">
                        <up-image class="rounded-[var(--goods-rounded-big)] overflow-hidden" width="100rpx" height="100rpx" :src="img(item.goods_image)" mode="aspectFill">
                            <template #error>
                                <image class="w-[100rpx] h-[100rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                            </template>
                        </up-image>
                        <view class="flex flex-1 flex-col justify-between ml-[20rpx]">
                            <view class="flex justify-between items-baseline">
                                <view class="text-[28rpx] truncate max-w-[400rpx] leading-[40rpx] text-[#333]">{{ item.goods_name }}</view>
                                <view class="text-right leading-[42rpx] ml-[10rpx] price-font">
                                    <text class="text-[22rpx]">￥</text>
                                    <text class="text-[32rpx] font-500">{{parseFloat(item.price).toFixed(2).split('.')[0] }}</text>
                                    <text class="text-[22rpx] font-500">.{{parseFloat(item.price).toFixed(2).split('.')[1] }}</text>
                                </view>
                            </view>
                             <view class="flex items-center justify-between mt-[14rpx]">
                                <view>
                                    <text v-if="item.sku_name" class="text-[22rpx]  text-[var(--text-color-light9)] truncate max-w-[320rpx] leading-[28rpx]">{{ item.sku_name }}</text>
                                </view>
                                <view class="text-right text-[26rpx]">x{{ item.num }}</view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
            <view class="border-0 border-t-[2rpx] border-solid border-[#eee] mb-[20rpx]"></view>
            <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules"  ref="formRef">
                <view class="refund-wrap">
                    <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                        <u-form-item label="退款金额"  labelWidth="140rpx">
                            <view>
                                <text class="text-[red]">￥{{ formData.refund_money }}</text>
                                <text v-if="isShowDelivery" class="text-[#999] text-[24rpx]">（运费:￥{{formData.delivery_money}}）</text>
                            </view>
                        </u-form-item>
                    </view>
                    <view class=" px-[var(--pad-sidebar-m)] rounded-[16rpx] bg-[#F6F6F6]">
                        <view class="py-[var(--pad-top-m)]">
                            <view class="text-[28rpx] flex items-center">
                                <text class="font-500">退款说明</text>
                            </view>
                            <view class="mt-[30rpx] h-[200rpx]">
                                <textarea class="leading-[1.5] h-[100%] w-[100%] text-[28rpx]" v-model="formData.shop_active_refund_remark" :maxlength="100" cols="30" rows="5" placeholder="请输入退款说明" placeholder-class="text-[26rpx] text-[var(--text-color-light9)]"></textarea>
                            </view>
                        </view>
                    </view>
                </view>
            </u-form>
        </view>
         <view class="w-full footer">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
                <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] text-[26rpx] font-500"  @click="save"  :loading="operateLoading">确定</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, nextTick } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { deepClone, img, redirect } from '@/utils/common';
import { getOrderDetail, shopActiveRefund } from '@/addon/mall/api/order';

const orderId = ref('')
const detail = reactive<any>({})
const refundTable = ref<any>([])
const formRef = ref<any>(null)
const loading = ref(false)
const initialFormData = {
    order_goods_ids: [],
    refund_money: 0,
    shop_active_refund_remark: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })
let refundItemNum = 0 //表示可退款项数量
let isShowDelivery = ref(false)

onLoad((options: any) => {
    orderId.value = options.order_id
    orderDetailFn(orderId.value)
})


const orderDetailFn = (id: any) => {
	loading.value = true;
	getOrderDetail(id).then(async (res: any) => {
        Object.assign(detail, res.data)
        refundItemNum = 0
        formData.refund_money = 0
        formData.order_goods_ids = []
        refundTable.value = detail.order_goods
        refundTable.value.forEach((item, index, arr) => {
            arr[index].refund_money = item.goods_money - item.discount_money
            if(item.status == 1){
                refundItemNum++
                formData.refund_money += arr[index].refund_money
                formData.order_goods_ids.push(item.order_goods_id)
            }
        })
        formData.delivery_money = detail.delivery_money
        formData.refund_money += parseFloat(detail.delivery_money)
        isShowDelivery.value = true

        formData.refund_money = formData.refund_money.toFixed(2)
        formData.shop_active_refund_remark = ''
        nextTick(() => {
            setTimeout(() => {
                refundTable.value.forEach((item) => {
                    item.checked = true
                    item.disabled = true
                    if (item.status == 1) {
                       item.disabled = false
                    }
                })
            }, 100)
        })
    loading.value = false
        
	}).catch(() => {
		loading.value = false;
	})
}

// 选择商品
const changeItem = (item: any) => {
	item.checked = !item.checked
    refundSelectChange()
}

const refundSelectChange = () => {
    formData.refund_money = 0
    isShowDelivery.value = false
    formData.order_goods_ids = []
    refundTable.value.forEach((item: any) => {
        if(item.checked){
            formData.refund_money += item.refund_money
            formData.order_goods_ids.push(item.order_goods_id)
        }
    })
    let arr = refundTable.value.filter((item: any) => {
        return item.checked == true
    })
    if(arr.length == refundItemNum){
        formData.refund_money += parseFloat(formData.delivery_money)
        isShowDelivery.value = true
    }
    formData.refund_money = formData.refund_money.toFixed(2)
}



const operateLoading = ref(false)
const save = () => {
    if (formData.order_goods_ids.length <= 0) {
        uni.showToast({
            title: '请选择要退款的商品',
            icon: 'none'
        })
        return
    }
    if (operateLoading.value) return
    operateLoading.value = true

    const data = deepClone(formData)
    data.shop_active_refund_money = formData.refund_money
    delete data.refund_money

    shopActiveRefund(data).then(() => {
        operateLoading.value = false
        if (getCurrentPages().length > 1) {
            uni.navigateBack({
                delta: 1
            });
        } else {
            redirect({url: '/addon/mall/pages/order/list'});
        }
    }).catch(() => {
        operateLoading.value = false
    })
}
</script>

<style lang="scss" scoped>
.refund-wrap :deep(.u-form-item__body__left__content__label) {
    font-size: 28rpx !important;
}
.footer {
    height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>