<template>
    <view class="bg-[#fff] min-h-screen overflow-hidden" :style="themeColor()" v-if="Object.keys(detail).length">
        <view class="sidebar-margin">
            <view class="text-[24rpx] my-[30rpx]">订单号：{{ detail.order_no }}</view>
            <view class="mb-[50rpx]">
                <view class="flex mb-[20rpx]" v-for="(item,index) in  goodsDataArr" :key="index">
                    <text v-if="item.disabled" class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 ':item.checked,'bg-[#F5F5F5]':!item.checked}"></text>
                    <text v-else class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0 box-border border-solid border-[2rpx]" :class="{ 'iconxuanze1 text-primary':item.checked,'border-[#ddd]':!item.checked}" @click="changeItem(item)"></text>
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
            <view class="border-0 border-t-[2rpx] border-solid border-[#eee] p-[20rpx] mb-[20rpx]" v-if="detail.delivery_type != 'virtual'">
                <view class="text-[24rpx] leading-[34rpx]">{{ detail.taker_name }}，{{ detail.taker_mobile }}，{{ detail.taker_full_address }}</view>
            </view>
            <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
                <view class="delivery-wrap">
                    <template v-if="formData.delivery_type == 'express' || formData.delivery_type == 'none_express'">
                        <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                            <u-form-item label="配送方式" prop="delivery_type" labelWidth="140rpx">
                                <view class="flex items-center flex-1" @click="showDeliveryTypePopup = true">
                                    <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.delivery_type }" >{{ formData.delivery_type_name ? formData.delivery_type_name : '请选择发货方式' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                        </view>
                        <template v-if="formData.delivery_type == 'express'">
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="物流公司" prop="express_company_id" labelWidth="140rpx">
                                    <view class="flex items-center flex-1" @click="showPopup = true">
                                        <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.express_company_id }" >{{ formData.express_company_id_name ? formData.express_company_id_name : '请选择物流公司' }}</view>
                                        <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                    </view>
                                </u-form-item>
                            </view>
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="物流单号" prop="express_number" labelWidth="140rpx">
                                    <u-input fontSize="28rpx" v-model.trim="formData.express_number" border="none" clearable maxlength="25" placeholderStyle="color: #888" placeholder="请输入物流单号" />
                                </u-form-item>
                            </view>
                        </template>
                    </template>
                    <template v-if="formData.delivery_type == 'local_delivery'">
                        <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                            <u-form-item label="发货方式" prop="delivery_way" labelWidth="180rpx">
                                <view class="flex items-center flex-1" @click="showDeliveryWayPopup = true">
                                    <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.delivery_way }" >{{ formData.delivery_way_name ? formData.delivery_way_name : '请选择发货方式' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                            <u-form-item label="提货点" prop="store_id" labelWidth="180rpx">
                                <view class="flex items-center flex-1" @click="showStoreIdPopup = true">
                                    <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.store_id }" >{{ formData.store_id_name ? formData.store_id_name : '请选择提货点' }}</view>
                                    <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                </view>
                            </u-form-item>
                        </view>
                        <template v-if="formData.delivery_way == 'store_delivery'">
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="配送员" prop="local_deliver_id" labelWidth="180rpx">
                                    <view class="flex items-center flex-1" @click="showLocalDeliverIdPopup = true">
                                        <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.local_deliver_id }" >{{ formData.local_deliver_id_name ? formData.local_deliver_id_name : '请选择配送员' }}</view>
                                        <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                    </view>
                                </u-form-item>
                            </view>
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="配送员手机号" prop="deliver_mobile" labelWidth="180rpx">
                                    <view :class="{'text-[#999]': !formData.deliver_mobile }">{{ formData.deliver_mobile ? formData.deliver_mobile : '请输入配送员手机号' }}</view>
                                </u-form-item>
                            </view>
                        </template>
                        <template v-if="formData.delivery_way == 'third_party_delivery'">
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="配送平台" prop="local_delivery_type" labelWidth="180rpx">
                                    <view class="flex items-center flex-1" @click="showLocalDeliveryTypePopup = true">
                                        <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.local_delivery_type }" >{{ formData.local_delivery_type_name ? formData.local_delivery_type_name : '请选择配送平台' }}</view>
                                        <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                                    </view>
                                </u-form-item>
                            </view>
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="商品重量" prop="goods_weight" labelWidth="180rpx">
                                    <u-input fontSize="28rpx" v-model.trim="formData.goods_weight" border="none" maxlength="25" placeholderStyle="color: #888" placeholder="请输入商品重量" @blur="thirdDeliveryChange">
                                        <template #suffix>
                                            <text class="text-[24rpx]">kg</text>
                                        </template>
                                    </u-input>
                                </u-form-item>
                            </view>
                            <view class="h-[88rpx] w-full px-[20rpx] rounded-[16rpx] box-border bg-[#F6F6F6] mb-[30rpx]">
                                <u-form-item label="配送费用" prop="goods_weight" labelWidth="180rpx">
                                    <text class="flex-1">{{ deliveryFee }}</text>
                                    <text  class="text-[24rpx]">元</text>
                                </u-form-item>
                            </view>
                        </template>
                    </template>
                </view>
            </u-form>
        </view>
        <view class="w-full footer">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
                <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] text-[26rpx] font-500"  @click="save"  :loading="operateLoading">确定</button>
            </view>
        </view>
        <!-- 发货方式 -->
        <u-popup :show="showDeliveryTypePopup" @close="showDeliveryTypePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">发货方式</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.delivery_type" placement="column" iconPlacement="right">
                       <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliveryType" :key="index" :label="item" :name="index"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeDeliveryType">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 物流公司 -->
        <u-popup :show="showPopup" @close="showPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">物流公司</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.express_company_id" placement="column" iconPlacement="right">
                       <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in companyData" :key="index" :label="item.company_name" :name="item.company_id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="changeExpressCompany">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 同城配送的发货方式 -->
        <u-popup :show="showDeliveryWayPopup" @close="showDeliveryWayPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">发货方式</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.delivery_way" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}"  label="商家配送" name="store_delivery"></u-radio>
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}"  label="第三方配送" name="third_party_delivery"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="handleDeliveryWay">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 提货点 -->
        <u-popup :show="showStoreIdPopup" @close="showStoreIdPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">提货点</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.store_id" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliveryStoreData" :key="index" :label="item.store_name" :name="item.store_id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="handleStoreId">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 配送员 -->
        <u-popup :show="showLocalDeliverIdPopup" @close="showLocalDeliverIdPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">配送员</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.local_deliver_id" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliverData" :key="index" :label="item.deliver_name" :name="item.deliver_id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="handleLocalDeliverId">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 配送平台 -->
        <u-popup :show="showLocalDeliveryTypePopup" @close="showLocalDeliveryTypePopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">配送平台</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.local_delivery_type" placement="column" iconPlacement="right">
                        <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in thirdDeliveryData" :key="index" :label="item.name" :name="item.key"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="handleLocalDeliveryType">确定</button>
                </view>
            </view>
        </u-popup>
        <loading-page :loading="loading"></loading-page>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, nextTick } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderDetail, getOrderDeliveryType, orderDelivery, getSelectOrderGoodsWeight, getDeliveryFee } from '@/addon/mall/api/order';
import { getCompanyList, getShopDeliverList, getInUseLocalDeliveryList, getDeliveryStoreListAll } from '@/app/api/delivery_site'
import { deepClone, img, redirect } from '@/utils/common';

const orderId = ref('')
const showType = ref('')
const loading = ref(false)
const detail = reactive<any>({})
const isHasVirtual = ref(false)
const deliveryType = ref([]) //配送方式
const goodsDataArr = ref<any>([]) //商品数据
const companyData = ref<any>([]) //物流公司
const formRef = ref<any>(null)
const formData = reactive<any>({
    order_id: 0,
    delivery_type: '',
    delivery_type_name: '',
    delivery_way: 'manual_write',
    delivery_way_name: '商家配送',
    local_delivery_type: 'merchant',
    local_delivery_type_name: '',
    goods_weight: 1,
    local_deliver_id: '',
    local_deliver_id_name: '',
    deliver_mobile: '',
    express_company_id: '',
    express_company_id_name: '',
    express_number: '',
    order_goods_ids: [],
    store_id: '',
    store_id_name: '',
})

onLoad((options: any) => {
    orderId.value = options.order_id
    showType.value = options.type
    orderDetailFn(orderId.value)
})
// 物流公司
getCompanyList({}).then((res: any) => {
    companyData.value = res.data
})
/**
 * 配送员
 */
const deliverNameChange = (value: any) => {
    if (value) {
        deliverData.value.forEach((item: any) => {
            if (item.deliver_id == value) {
                formData.deliver_mobile = item.deliver_mobile
            }
        })
    } else {
        formData.deliver_mobile = ''
        formData.local_deliver_id = ''
    }
}

const deliverData = ref([])
getShopDeliverList({}).then((res: any) => {
    deliverData.value = res.data
})

/**
 * 三方配送列表
 */
const thirdDeliveryData = ref([])
const getInUseLocalDeliveryListFn = (value: any) => {
    getInUseLocalDeliveryList({store_id: value}).then((res: any) => {
        thirdDeliveryData.value = res.data
    })
}

/**
 * 获取提货点列表
 */
const deliveryStoreData = ref([])
getDeliveryStoreListAll({
    pick_up_type: 'local_delivery'
}).then((res: any) => {
    deliveryStoreData.value = res.data
})

const deliveryStoreChange = (value: any) => {
    if (formData.delivery_way == 'third_party_delivery' && value) {
        formData.local_delivery_type = ''
        formData.local_delivery_type_name = ''
        deliveryFee.value = 0
        getInUseLocalDeliveryListFn(value)
    } else {
        thirdDeliveryData.value = []
    }
}
const orderDetailFn = (id: any) => {
	loading.value = true;
	getOrderDetail(id).then(async (res: any) => {
        Object.assign(detail, res.data)
        formData.order_id = detail.order_id
        formData.delivery_type = ''
        formData.delivery_way = 'manual_write'
        formData.local_delivery_type = 'merchant'
        formData.store_id = detail.take_store_id
        formData.store_id_name = detail.store?.store_name
        isHasVirtual.value = false
		loading.value = false;
        await getOrderDeliveryType({
            delivery_type: detail.delivery_type
        }).then((res: any) => {
            deliveryType.value = res.data
            for (const v in res.data) {
                formData.delivery_type = v
                formData.delivery_type_name = res.data[v]
                break
            }
            deliveryChange()
        })
        for (let i = 0; i < detail.order_goods.length; i++) {
            if (detail.order_goods[i].goods_type == 'virtual') {
                isHasVirtual.value = true
                break
            }
        }
        if (isHasVirtual.value) {
            Object.assign(deliveryType.value, { virtual: '虚拟发货' })
        }
        if (formData.delivery_type == 'local_delivery') {
            if (detail.status == 2) {
                goodsDataArr.value.forEach((item: any, index: any) => {
                	item.checked = true
                })
            } else if (detail.status == 3) {
            	goodsDataArr.value.forEach((item: any, index: any) => {
                    if (item.orderDelivery.local_delivery_status == '-2' || item.orderDelivery.local_delivery_status == '-1' || item.orderDelivery.local_delivery_status == '8') {
                        item.checked = true
                    }
                })
            }
            getSelectOrderGoodsWeightFn()
            getInUseLocalDeliveryListFn(formData.store_id)
        }
	}).catch(() => {
		loading.value = false;
	})
}

const deliveryChange = () => {
    let arr: any = []
    if (formData.delivery_type && formData.delivery_type == 'virtual') {
        detail.order_goods.forEach((item: any) => {
            if (item.goods_type == 'virtual') {
                arr.push(item)
            }
        })
    } else if (formData.delivery_type && formData.delivery_type != 'virtual') {
        detail.order_goods.forEach((item: any) => {
            if (item.goods_type != 'virtual') {
                arr.push(item)
            }
        })
    }
    goodsDataArr.value = deepClone(arr)
    if (formData.delivery_type && formData.delivery_type == 'express') {
        formData.delivery_way = 'manual_write'
    } else if (formData.delivery_type && formData.delivery_type == 'local_delivery') {
        formData.delivery_way = 'store_delivery'
    }
    goodsDataArr.value.forEach((item: any) => {
    	if((item.status == 2 || item.delivery_status != 'wait_delivery' || item.status == 3) && (showType.value == 'add' || !showType.value)){
    		item.disabled = true
    	}
        if (item.orderDelivery && item.orderDelivery.delivery_service == 'dada' && (item.orderDelivery.local_delivery_status == '-2' || item.orderDelivery.local_delivery_status == '-1' || item.orderDelivery.local_delivery_status == '8')) {
           item.disabled = true
        }
        if (formData.delivery_type == 'local_delivery') {
           item.disabled = true
        }
    })
    // console.log(goodsDataArr.value)
}
const getSelectOrderGoodsWeightFn = () => {
    getSelectOrderGoodsWeight({
        order_goods_ids: formData.order_goods_ids
    }).then((res: any) => {
        formData.goods_weight = res.data
    })
}

// 计算配送费用
const deliveryFee = ref<any>(0)
const getDeliveryFeeFn = () => {
    getDeliveryFee(formData).then((res: any) => {
        deliveryFee.value = res.data
    })
}
const thirdDeliveryChange = (value: any) => {
    if (formData.delivery_way == 'third_party_delivery' && value) {
        getDeliveryFeeFn()
    } else {
        deliveryFee.value = 0
    }
}

// 发货方式
const showDeliveryTypePopup = ref(false)
const changeDeliveryType = () => {
    if (!formData.delivery_type) {
        uni.showToast({
            title: '请选择发货方式',
            icon: 'none'
        })
        return
    }
    formData.delivery_type_name = deliveryType.value[formData.delivery_type]
    showDeliveryTypePopup.value = false
    deliveryChange()
}


// 物流公司
const showPopup = ref(false)
const changeExpressCompany = () => {
    if (!formData.express_company_id) {
        uni.showToast({
            title: '请选择物流公司',
            icon: 'none'
        })
        return
    }
    const company = companyData.value.find((item: any) => item.company_id == formData.express_company_id)
    formData.express_company_id_name = company.company_name
    showPopup.value = false
}
// 发货方式
const showDeliveryWayPopup = ref(false)
const handleDeliveryWay = () => {
    if(formData.delivery_way == 'store_delivery'){
        formData.delivery_way_name = '商家配送'
        formData.local_delivery_type = 'merchant'
    } else if(formData.delivery_way == 'third_party_delivery') {
        formData.delivery_way_name = '第三方配送'
        formData.local_delivery_type = ''
    }
    showDeliveryWayPopup.value = false
}
// 提货点
const showStoreIdPopup = ref(false)
const handleStoreId = () => {
    if (!formData.store_id) {
        uni.showToast({
            title: '请选择提货点',
            icon: 'none'
        })
        return
    }
    const store = deliveryStoreData.value.find((item: any) => item.store_id == formData.store_id)
    formData.store_id_name = store.store_name
    if(formData.delivery_way == 'third_party_delivery'){
        deliveryStoreChange(formData.store_id)
    }
    showStoreIdPopup.value = false
}
// 配送员
const showLocalDeliverIdPopup = ref(false)
const handleLocalDeliverId = () => {
    if (!formData.local_deliver_id) {
        uni.showToast({
            title: '请选择配送员',
            icon: 'none'
        })
        return
    }
    const person = deliverData.value.find((item: any) => item.deliver_id == formData.local_deliver_id)
    formData.local_deliver_id_name = person.deliver_name
    deliverNameChange(formData.local_deliver_id)
    showLocalDeliverIdPopup.value = false
}
// 配送平台
const showLocalDeliveryTypePopup = ref(false)
const handleLocalDeliveryType = () => {
    if (!formData.local_delivery_type) {
        uni.showToast({
            title: '请选择配送平台',
            icon: 'none'
        })
        return
    }
    formData.local_delivery_type_name = thirdDeliveryData.value.find((item: any) => item.key == formData.local_delivery_type).name
    thirdDeliveryChange(formData.local_delivery_type)
    showLocalDeliveryTypePopup.value = false
}

// 选择商品
const changeItem = (item: any) => {
	item.checked = !item.checked
    formData.order_goods_ids = goodsDataArr.value.filter((v: any) => v.checked).map((v: any) => v.order_goods_id)
    if (showType.value == 'edit') {
        formData.delivery_ids = goodsDataArr.value.filter((v: any) => v.checked).map((v: any) => v.delivery_id)
    }
}

// 正则表达式
const regExp: any = {
    required: /[\S]+/,
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/,
    special: /^\d{0,10}(.?\d{0,3})$/
}

const rules = computed(() => {
    return {
        'delivery_type': {
            type: 'string',
            required: true,
            message: '请选择配送方式',
            trigger: ['blur', 'change'],
        },
        'express_company_id':{
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'express' && value === '') {
                    callback(new Error('请选择物流公司'))
                } else {
                    callback()
                }
            }
        },
        'express_number': {
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'express' && formData.delivery_way == 'manual_write' && value === '') {
                    callback(new Error('请输入物流单号'))
                } else {
                    callback()
                }
            }
        },
        'local_deliver_id': {
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'local_delivery' && formData.delivery_way == 'store_delivery' && value == '') {
                    callback(new Error('请选择配送员'))
                } else {
                    callback()
                }
            }
        },
        'store_id': {
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'local_delivery' && value == '') {
                    callback(new Error('请选择提货点'))
                } else {
                    callback()
                }
            }
        },
        'local_delivery_type': {
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'local_delivery' && formData.delivery_way == 'third_party_delivery' && (value == '' || value == undefined)) {
                    callback(new Error('请选择配送平台'))
                } else {
                    callback()
                }
            }
        },
        'goods_weight': {
            validator(rule: any, value: any, callback: any) {
                if (formData.delivery_type == 'local_delivery' && formData.delivery_way == 'third_party_delivery' && value == '') {
                    callback(new Error('请输入商品重量'))
                } else if (isNaN(value) || !regExp.special.test(value)){
                    callback(new Error('请输入正确的重量'))
                } else if (value < 0) {
                    callback(new Error('请输入正确的重量'))
                }else {
                    callback()
                }
            }
        }
    }
})

const operateLoading = ref(false)
const save = () => {
    formData.order_goods_ids = goodsDataArr.value.filter((v: any) => v.checked).map((v: any) => v.order_goods_id)
    if (showType.value == 'edit') {
        formData.delivery_ids = goodsDataArr.value.filter((v: any) => v.checked).map((v: any) => v.delivery_id)
    }
    if (formData.order_goods_ids.length <= 0) {
        uni.showToast({
            title: '请选择要发货的商品',
            icon: 'none'
        })
        return
    }
    formRef.value.validate().then(() => {
        
        if (operateLoading.value) return
        operateLoading.value = true

        orderDelivery(formData).then(res => {
            operateLoading.value = false
            initFormData()
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
    })
}


const initFormData = () => {
    formData.order_id = 0
    formData.delivery_type = ''
    formData.express_company_id = ''
    formData.express_company_id_name = ''
    formData.express_number = ''
    formData.order_goods_ids = []
}
</script>

<style lang="scss" scoped>
.delivery-wrap :deep(.u-form-item__body__left__content__label) {
    font-size: 28rpx !important;
}
.footer {
    height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>