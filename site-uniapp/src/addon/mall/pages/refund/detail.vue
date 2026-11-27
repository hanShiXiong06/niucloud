<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()" v-if="detail">
        <view class="card-template sidebar-margin my-[20rpx]">
            <view class="flex items-center">
                <text class="nc-iconfont nc-icon-tuikuanV6xx text-[#fb723a]"></text>
                <text class="ml-[10rpx]">{{ detail.status_name }}</text>
            </view>
        </view>
        <view class="card-template sidebar-margin my-[20rpx]">
            <view class="title">退款信息</view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">买家昵称</view>
                <view class="flex items-center">
                    <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(detail.member.headimg)" :size="'30rpx'" leftIcon="none" />
                    <text v-if="detail.member" class="text-[28rpx] ml-[10rpx]">{{ detail.member.nickname }}</text>
                </view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">售后类型</view>
                <view class="text-[28rpx]">{{ detail.refund_type_name }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">退款金额</view>
                <view class="text-[28rpx]">{{ detail.apply_money }}</view>
            </view>
        </view>
        <view class="sidebar-margin mb-[var(--top-m)] card-template">
            <view class="justify-between text-[28rpx] card-template-item"  @click="redirect({url: '/addon/mall/pages/refund/log', param: { refund_id: detail.refund_id }})">
                <view>协商历史</view>
                <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[var(--text-color-light9)] pt-[2rpx]"></text>
            </view>
        </view>
        <view class="sidebar-margin mb-[var(--top-m)] card-template">
            <view class="title">退款商品</view>
            <view class="flex box-border mb-[20rpx]" v-for="(item, index) in [detail.order_goods]" :key="index">
                <up-image width="150rpx" height="150rpx" :radius="'var(--goods-rounded-big)'" :src="img(item.goods_image ? item.goods_image : '')" mode="aspectFill">
                    <template #error>
                        <image class="w-[150rpx] h-[150rpx] rounded-[var(--goods-rounded-big)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                    </template>
                </up-image>
                <view class="ml-[20rpx] flex flex-1 flex-col box-border">
                    <view class="flex justify-between items-baseline">
                        <view class="max-w-[360rpx] text-[28rpx] leading-[40rpx] font-400 truncate text-[#303133]">{{ item.goods_name }}</view>
                        <view class="text-right leading-[42rpx] ml-[10rpx] price-font">
                            <text class="text-[22rpx]">￥</text>
                            <text class="text-[32rpx] font-500">{{parseFloat(item.price).toFixed(2).split('.')[0] }}</text>
                            <text class="text-[22rpx] font-500">.{{parseFloat(item.price).toFixed(2).split('.')[1] }}</text>
                        </view>
                    </view>
                    <view class="flex  justify-between items-baseline text-[#303133] mt-[14rpx]">
                        <view>
                            <view class="text-[24rpx] text-[var(--text-color-light6)] font-400 truncate leading-[34rpx] max-w-[369rpx] mb-[10rpx]" v-if="item.sku_name">{{ item.sku_name }}</view>
                        </view>
                        <text class="text-right text-[26rpx] font-400 w-[90rpx] leading-[36rpx]">x{{ item.num }}</text>
                    </view>
                </view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">退款原因</view>
                <view class="text-[28rpx]">{{ detail.reason }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">申请退款金额</view>
                <view class="text-[28rpx]">￥{{ detail.apply_money }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">实际退款金额</view>
                <view class="text-[28rpx]">￥{{ detail.money }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">申请时间</view>
                <view class="text-[28rpx]">{{ detail.create_time }}</view>
            </view>
            <view class="justify-between card-template-item">
                <view class="text-[28rpx]">退款编号</view>
                <view class="text-[28rpx]">{{ detail.order_refund_no }}</view>
            </view>
            <view class="justify-between card-template-item" v-if="detail.remark">
                <view class="text-[28rpx]">退款描述</view>
                <view class="text-[28rpx]">{{ detail.remark }}</view>
            </view>
            <view class="justify-between card-template-item !items-start" v-if="detail.voucher && detail.voucher.length">
                <view class="text-[28rpx] flex-shrink-0">退款凭证</view>
                <view class="flex flex-wrap justify-end">
                    <image class="w-[90rpx] h-[90rpx] rounded-[var(--goods-rounded-big)] overflow-hidden ml-[20rpx] mb-[20rpx]" :src="img(item)" mode="aspectFill" v-for="(item,index) in detail.voucher" :key="index" @click="handleImage(index)"></image>
                </view>
            </view>
        </view>
        <view class="tab-bar-placeholder"></view>
        <view  class="flex z-2 justify-end items-center bg-[#fff] fixed left-0 right-0 bottom-0 min-h-[110rpx] px-[30rpx]  pb-ios" v-if="(detail.status == 1 || (detail.status == 4 && detail.refund_type == 2))&& !detail.order_main.supply_id">
            <view v-if="detail.status == 1 && !detail.order_main.supply_id" class="list-grey-solid-btn ml-[20rpx]"  @click="agreeEvent">同意</view>
            <view v-if="detail.status == 1 && !detail.order_main.supply_id" class="list-grey-solid-btn ml-[20rpx]"  @click="refuseEvent">拒绝</view>
            <view v-if="detail.status == 4 && detail.refund_type == 2 && !detail.order_main.supply_id" class="list-grey-solid-btn ml-[20rpx]"  @click="deliverEvent">确认收货</view>
            <view v-if="detail.status == 4 && detail.refund_type == 2 && !detail.order_main.supply_id" class="list-grey-solid-btn ml-[20rpx]"  @click="deliveryRefuseEvent">拒绝</view>
        </view>
        <!-- 同意退款 -->
        <u-popup :show="agreeRefundPopup" @close="agreeRefundPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">同意退款</view>
                <scroll-view scroll-y="true" class="h-[380rpx] px-[30rpx] box-border refund-wrap">
                    <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
                        <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                            <u-form-item label="申请金额" labelWidth="140rpx">
                                <view>￥{{ formData.apply_money }}</view>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                            <u-form-item label="退款金额" prop="money" labelWidth="140rpx" required>
                                <text>￥</text>
                                <u-input fontSize="28rpx" v-model.trim="formData.money" border="none" clearable maxlength="25" placeholderStyle="color: #888" placeholder="请输入退款金额" />
                            </u-form-item>
                        </view>
                        <view class="w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6]" v-if="formData.refund_type == 2">
                            <u-form-item label="退货地址" prop="refund_address_id" labelWidth="140rpx">
                                <view class="flex items-center flex-1" @click="addressShowPopup = true">
                                    <view class="flex-1 leading-[36rpx]" :class="{'text-[var(--text-color-light9)]': !formData.refund_address_id }" >{{ formData.refund_address_id_name ? formData.refund_address_id_name : '请选择退货地址' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                        </view>
                    </u-form>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="agreeRefundEvent">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 拒绝退款 -->
        <u-popup :show="refuseRefundPopup" @close="refuseRefundPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">拒绝退款</view>
                <scroll-view scroll-y="true" class="h-[300rpx] px-[30rpx] box-border refund-wrap refuse-wrap">
                    <u-form labelPosition="left" :model="formData" errorType='toast' :rules="refundFormRules" ref="refuseFormRef">
                        <u-form-item label="拒绝原因" prop="shop_reason" labelWidth="140rpx" required>
                            <view class="h-[120rpx] flex-1 mt-[10rpx] bg-[#F6F6F6] p-[20rpx] rounded-[16rpx]">
                                <textarea class="leading-[1.5] h-[100%] w-[100%] text-[28rpx]" v-model.trim="formData.shop_reason" :maxlength="200" cols="30" rows="5" placeholder="请输入拒绝原因" placeholder-class="text-[26rpx] !text-[var(--text-color-light9)]"></textarea>
                            </view>
                        </u-form-item>
                    </u-form>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="refuseRefundEvent">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 退货地址 -->
        <u-popup :show="addressShowPopup" @close="addressShowPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">退货地址</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.refund_address_id" placement="column" iconPlacement="right">
                       <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in refundAddress" :key="index" :label="item.full_address" :name="item.id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeAddress">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 拒绝收货 -->
        <u-popup :show="deliveryRefusePopup" @close="deliveryRefusePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">拒绝退款</view>
                <scroll-view scroll-y="true" class="h-[300rpx] px-[30rpx] box-border refund-wrap refuse-wrap">
                    <u-form labelPosition="left" :model="deliveryRefuseFormData" errorType='toast' :rules="deliveryRefundFormRules" ref="deliveryRefuseFormRef">
                        <u-form-item label="拒绝原因" prop="shop_reason" labelWidth="140rpx" required>
                            <view class="h-[120rpx] flex-1 mt-[10rpx] bg-[#F6F6F6] p-[20rpx] rounded-[16rpx]">
                                <textarea class="leading-[1.5] h-[100%] w-[100%] text-[28rpx]" v-model.trim="deliveryRefuseFormData.shop_reason" :maxlength="200" cols="30" rows="5" placeholder="请输入拒绝原因" placeholder-class="text-[26rpx] !text-[var(--text-color-light9)]"></textarea>
                            </view>
                        </u-form-item>
                    </u-form>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="refundDeliveryFn">确定</button>
                </view>
            </view>
        </u-popup>
        <loading-page :loading="loading"></loading-page>
        <!-- 提示框 -->
        <tips-popup ref="tipsRef" />
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { img, redirect, copy, goback } from '@/utils/common';
import { orderRefundDetail, auditRefund, refundDelivery  } from '@/addon/mall/api/refund';
import { getOrderRefundAddress } from '@/app/api/delivery_site'


const detail = ref<any>(null);
const loading = ref<boolean>(true);
const refundId = ref('')

onLoad((option:any) => {
    if (option.refund_id) {
        refundId.value = option.refund_id;
        orderRefundDetailFn(refundId.value);
    } else {
        let parameter = {
            url: '/addon/mall/pages/refund/list',
            title: '缺少订单id'
        };
        goback(parameter)
    }
	
});

const orderRefundDetailFn = (id: any) => {
	loading.value = true;
	orderRefundDetail(id).then((res: any) => {
		detail.value = res.data;
		loading.value = false;
	}).catch(() => {
		loading.value = false;
	})
}

const formRef = ref<any>(null)
const formData = reactive({
    money: '',
    apply_money: 0.00,
    shop_reason: '',
    refund_address_id: '',
    refund_address_id_name: '',
    refund_type: 1
}); 
// 同意退款
const agreeRefundPopup = ref(false);
const agreeEvent = () => { 
    formData.refund_type = detail.value.refund_type
    formData.apply_money = detail.value.apply_money
    formData.money = detail.value.apply_money
    agreeRefundPopup.value = true
    if (detail.value.refund_type == 2) {
        getRefundAddress()
    }
}

const rules = computed(() => {
    return {
        money: [
            {
                required: true,
                message: '请输入退款金额',
                trigger: 'blur'
            }
        ]
    }
})

const addressShowPopup = ref(false);
const  changeAddress = () => {
    if (!formData.refund_address_id) {
        uni.showToast({
            title: '请选择退货地址',
            icon: 'none'
        })
        return
    }
    formData.refund_address_id_name = refundAddress.value.find(item => item.id == formData.refund_address_id)?.full_address
    addressShowPopup.value = false
}
const operateLoading = ref(false)
const  agreeRefundEvent = () => {
    formRef.value.validate().then(() => {
        
        if (operateLoading.value) return
        operateLoading.value = true

        auditRefund({
            order_refund_no: detail.value.order_refund_no,
            money: formData.money,
            is_agree: 1,
            refund_address_id: formData.refund_address_id
        }).then(() => {
            operateLoading.value = false
            agreeRefundPopup.value = false
            orderRefundDetailFn(refundId.value);
        }).catch(() => {
            operateLoading.value = false
        })
    })
}
// 拒绝退款
const refuseRefundPopup = ref(false);
const refuseFormRef = ref<any>(null)
const refuseEvent = () => {
    formData.shop_reason = ''
    refuseRefundPopup.value = true
}
const refundFormRules = computed(() => {
    return {
        shop_reason: [
            {
                required: true,
                message: '请输入拒绝原因',
                trigger: 'blur'
            }
        ]
    }
})

const  refuseRefundEvent = () => {
    refuseFormRef.value.validate().then(() => {
        if (operateLoading.value) return
        operateLoading.value = true
        auditRefund({
            order_refund_no: detail.value.order_refund_no,
            is_agree: 0,
            shop_reason: formData.shop_reason
        }).then(res => {
            orderRefundDetailFn(refundId.value);
            operateLoading.value = false
            refuseRefundPopup.value = false
        }).catch(() => {
            operateLoading.value = false
            refuseRefundPopup.value = false
        })
    })
}
// 获取退货地址
const refundAddress = ref<any[]>([])
const getRefundAddress = () => {
    getOrderRefundAddress().then((res: any) => {
        refundAddress.value = res.data
        res.data.forEach((item:any) => {
            if (item.is_default_refund == 1 && item.is_refund_address == 1) {
                formData.refund_address_id = item.id
                formData.refund_address_id_name = item.full_address
            }
        })
        if (!formData.refund_address_id) {
            formData.refund_address_id = res.data[0].id
            formData.refund_address_id_name = res.data[0].full_address
        }
    })
}

// 确认收货
const tipsRef = ref<any>(null)
const deliverEvent = () => {
    tipsRef.value.open('确定商品收到了吗？', () => {
		refundDelivery({
            order_refund_no: detail.value.order_refund_no,
            is_agree: 1
        }).then(() => {
            orderRefundDetailFn(refundId.value);
        })
	})
}
// 拒绝收货
const deliveryRefuseFormRef = ref<any>(null)
const deliveryRefusePopup = ref<boolean>(false)
const deliveryRefuseFormData = reactive<any>({
    shop_reason: ''
})
const deliveryRefundFormRules = computed(() => {
    return {
        shop_reason: [
            {
                required: true,
                message: '请输入拒绝原因',
                trigger: 'blur'
            }
        ]
    }
})
const deliveryRefuseEvent = () => {
    deliveryRefuseFormData.shop_reason = ''
    deliveryRefusePopup.value = true
}

const refundDeliveryFn = () => {
	deliveryRefuseFormRef.value.validate().then(() => {
		if (operateLoading.value) return
		operateLoading.value = true
		refundDelivery({
			order_refund_no: detail.value.order_refund_no,
			is_agree: 0,
			shop_reason: deliveryRefuseFormData.shop_reason
		}).then(() => {
			orderRefundDetailFn(refundId.value);
			operateLoading.value = false
			deliveryRefusePopup.value = false
		}).catch(() => {
			operateLoading.value = false
			deliveryRefusePopup.value = false
		})
	})
}
// 查看图片
const handleImage = (index: any) => {
    let imageList = detail.value.voucher.map((item: any) => {
        return img(item)
    })
    console.log(imageList, index)
    uni.previewImage({
        indicator: "number",
        current: index,
        loop: true,
        urls: imageList
    })
}

// 关闭预览图片
onUnload(() => {
    // #ifdef  H5 || APP
    try {
        uni.closePreviewImage()
    } catch (e) {

    }
    // #endif
})
</script>

<style lang="scss" scoped>
.tab-bar-placeholder {
	padding-bottom: calc(constant(safe-area-inset-bottom) + 110rpx);
	padding-bottom: calc(env(safe-area-inset-bottom) + 110rpx);
}
.refund-wrap :deep(.u-form-item__body__left__content__label) {
    font-size: 28rpx !important;
}
.refuse-wrap :deep(.u-form-item__body){
    flex-direction: column !important;
}
:deep(.u-radio__icon-wrap){
    flex-shrink: 0 !important;
}
.refund-wrap :deep(.u-form-item__body__right__content){
	height: 100%   !important;
}
</style>