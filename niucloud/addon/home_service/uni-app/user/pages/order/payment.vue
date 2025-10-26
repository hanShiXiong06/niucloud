<template>
	<view :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS --> 
		<u-navbar   title="待付款订单"   leftIconSize="15px" autoBack  :fixed="true" placeholder>
		</u-navbar>
			<!-- #endif -->
	
		<view class="bg-[#F6F8FA] min-h-screen overflow-hidden py-[20rpx] px-[24rpx]" :style="wxxcxstytle" v-if="orderData">
			<!-- 上门地址 -->
			<view class="bg-[#fff] rounded-lg py-[10rpx]">
				<view class="flex items-center py-[24rpx] px-[24rpx]  border-bottom-style" @click="toSelectAddress">
					<view class="flex-1 w-0 flex">
						<text class="nc-iconfont nc-icon-dingweiV6xx-1 text-[28rpx] mr-[20rpx]"></text>
						<view v-if="!$u.test.isEmpty(orderData.delivery.take_address)">
							<view class="font-500 text-[30rpx] mb-[10rpx]">
								{{ orderData.delivery.take_address.name }}
								<text
									class="text-[30rpx]">{{ mobileHide(orderData.delivery.take_address.mobile) }}</text>
							</view>
							<view class="text-[28rpx] text-gray-subtitle mt-[10rpx] leading-[40rpx] line-feed">
								{{ orderData.delivery.take_address.full_address }}
							</view>
						</view>
						<view v-else class="text-[28rpx]">{{ t('addHomeAddress') }}</view>
					</view>
					<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)]"></text>
				</view>
				<!-- 预约时间 -->
				<view class="flex items-center px-[24rpx]">
					<view class="flex justify-between items-center box-border py-[24rpx] w-[100%]">
						<view class="flex-align">
							<text
								class="text-[28rpx] text-[#4D4D4D] font-bold nc-iconfont nc-icon-a-shijianV6xx-36"></text>
							<text class="text-[28rpx] ml-2">服务时间</text>
						</view>
						<view class="flex-align text-[#63676D]" @click="handleTime">
							<view class="text-[28rpx] ml-2 text-right"
								:class="createData.reserve_service_time ? 'text-[var(--primary-color)]' : 'text-[#999]'">
								{{ createData.reserve_service_time ? createData.reserve_service_time : t('selectAddTimePlaceholder') }}
							</view>
							<text
								class="text-[26rpx] text-[var(--text-color-light6)] nc-iconfont nc-icon-youV6xx"></text>
						</view>
					</view>
				</view>
			</view>

			<ns-select-time ref="selectTime" :rules="service_time" :isQuantum="true" @change="getTime"
				@getStamp="getStamp" v-if="Object.keys(service_time).length"></ns-select-time>
			<view class="outline-border" v-for="(item, index) in orderData.goods_data"  @click="toDetail(item.sku_id)">
				<up-image width="168rpx" height="168rpx" radius="5" class="rounded-[50%]" :src="img(item.sku_image)" model="aspectFill">
					<template #error>
						<u-icon name="photo" color="#999" size="50"></u-icon>
					</template>
				</up-image>
				<view class="flex flex-col py-1 flex-1 ml-[10rpx]">
					<view class="text-ellipsis text-[#333] text-[26rpx] leading-normal font-500">
						{{ item.goods.goods_name }}
					</view>
					<view class="flex justify-between">
						<view class="flex items-center mt-[10rpx]">
							<view class="text-[#999] text-[24rpx]">
								{{item.goods.goods_subtitle}}
							</view>
						</view>
						<view class="font-500 text-sm text-[#999]"> <text class="text-[26rpx]">x</text>{{ item.num }}
						</view>
					</view>
					<view class="flex justify-between mt-auto " v-if="!createData.card_data?.member_card_item_id">
						<view class="text-[var(--price-text-color)] text-[28rpx] font-bold flex items-end">
							<text class="text-[22rpx] price-font  !leading-[1.2]">￥</text>
							<text
								class="price-font text-[36rpx] !leading-[1] ">{{ Number( moneyFormat(item.price)).toString().split('.')[0] }}</text>
							<text
								class="price-font text-[24rpx] !leading-[1.1]">.{{ Number( moneyFormat(item.price)).toFixed(2).split('.')[1] }}</text>
							<text v-if="item.sku_unit" class="!text-[26rpx] ml-[5rpx]">/{{ item.sku_unit }}</text>
						</view>
					</view>
				</view>
			</view>
			<view class="bg-[#fff] px-3 mt-[-10rpx] pt-[10rpx] rounded-bl-22rpx rounded-br-22rpx" v-if="!createData.card_data?.member_card_item_id">
				<!-- 备注 -->
				<view class="flex justify-between items-center box-border py-[24rpx]">
					<text class="text-[28rpx]">{{ t('buysMessage') }}</text>
					<view class="flex-align text-[#63676D]">
						<input class="uni-input text-[28rpx] ml-2 text-right" :placeholder="t('messagePlaceholder')"
							v-model="createData.member_remark" />
						<!-- <text class="text-[26rpx] text-[var(--text-color-light6)] nc-iconfont nc-icon-youV6xx"></text> -->
					</view>
				</view>
			</view>
			<view class="mt-[20rpx] p-[24rpx] rounded-md bg-white" v-if="!createData.card_data?.member_card_item_id">
				<view class="text-[30rpx] font-bold mb-[15rpx]">
					{{t('priceDetail')}}
				</view>
				<view class="flex  py-[10rpx] items-center">
					<view class="text-[28rpx]">{{ t('goodsMoney') }}</view>
					<view class="flex-1 w-0 text-right  price-font">
						<text class="text-[24rpx]">￥</text>
						<text>{{ moneyFormat(orderData.basic.goods_money) }}</text>
					</view>
				</view>
				<view class="flex  py-[10rpx] items-center" v-if="couponRef && couponList.length">
					<view class="text-[28rpx]">优惠券</view>
					<view class="flex-1 w-0 text-right  price-font flex justify-end items-center"
						@click="couponRef.open(createData.discount.coupon_id)" v-if="couponList.length">
						<view v-if="orderData.discount && orderData.discount.coupon"
							class="text-[var(--price-text-color)] text-[28rpx] truncate flex items-center justify-end">
							<view class="bg-[var(--price-text-color)] text-[#fff] text-[20rpx] px-[15rpx] py-[10rpx] rounded-[10rpx] mb-[2rpx]">
								{{ orderData.discount.coupon.title }}
							</view>
							<view class="text-[26rpx] ml-[10rpx] " v-if="orderData.basic.discount_money">	-￥{{moneyFormat(orderData.basic.discount_money)}}</view>
							</view>
						<text class="text-[28rpx] text-gray-subtitle" v-else>请选择优惠券</text>
						<text
							class="nc-iconfont nc-icon-youV6xx -mb-[2rpx] text-[26rpx] text-[var(--text-color-light9)] ml-[5rpx]"></text>
					</view>
				</view>

				<view class="flex  py-[10rpx] items-center">
					<view class="text-[28rpx]">{{ t('payMoney') }}</view>
					<view class="flex-1 w-0 text-right price-font text-[var(--price-text-color)]">
						<text class="text-[24rpx]">￥</text>
						<text
							class="">{{ moneyFormat(orderData.basic.pay_money) }}</text>
					</view>
				</view>
			</view>
			<select-coupon :order-key="createData.order_key" ref="couponRef" @confirm="confirmSelectCoupon" />
			<view class="h-[148rpx] w-screen"></view>
			<u-tabbar :fixed="true" :placeholder="true" :safeAreaInsetBottom="true">
				<view class="flex-1 flex items-center justify-between">
					<view class="whitespace-nowrap px-[30rpx] flex items-end" v-if="!createData.card_data?.member_card_item_id">
						<text class="text-[#333333] text-[26rpx] mr-[10rpx] flex items-end h-[36rpx]">{{ t('payAll') }}</text>
						<view class="text-[var(--price-text-color)] flex items-end">
							<text class="text-[22rpx] price-font h-[36rpx] flex items-end">￥</text>
							<text
								class="price-font text-[36rpx] flex items-end align-bottom" style="margin-bottom: -2rpx;">{{ Number(moneyFormat(orderData.basic.order_money)).toString().split('.')[0] }}</text>
							<text
								class="price-font text-[24rpx] h-[36rpx] flex items-end">.{{ Number(moneyFormat(orderData.basic.order_money)).toFixed(2).split('.')[1] }}</text>
						</view>
					</view>
					<button
						class="!px-[40rpx] !h-[60rpx] text-[24rpx] mr-[30rpx] leading-[60rpx] rounded-[20rpx] text-white bg-[var(--primary-color)]"
						@click="create">{{ t('submit') }}</button>
				</view>
			</u-tabbar>

			<pay ref="payRef" @close="payClose"></pay>
		</view>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { onLoad, onShow } from '@dcloudio/uni-app'
	import { img, redirect, urlDeconstruction, moneyFormat, mobileHide, isWeixinBrowser } from '@/utils/common'
	import { getTechnicianGoods } from '@/addon/home_service/user/api/technician'
	import { orderCalculate, orderCreate } from '@/addon/home_service/user/api/order'
	import { getReserveConfig } from '@/addon/home_service/user/api/goods'
	import { t } from '@/locale'
	import useMemberStore from '@/stores/member'
	import { cloneDeep } from 'lodash-es'
	import nsSelectTime from '@/addon/home_service/user/components/ns-select-time'
	import { wechatSync } from '@/app/api/system'
	import selectCoupon from '@/addon/home_service/user/components/select-coupon/select-coupon'
	import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
	const loading = ref<boolean>(false)
	const userList = ref([[]]); // 师傅列表
	const userShow = ref(false) // 控制师傅列表
	const service_time = ref({}) //获取配置时间
	const orderData = ref(null)
	const couponRef = ref()
	const createLoading = ref(false)
	// 向订单计算提交
	const createData = ref({
		order_key: '',
		technician_id: '',
		reserve_service_time: '',
		reserve_service_time_stamp: '',
		card_data:{
		},
		member_remark: '',
		discount:{},
		delivery: {
			take_address_id: ''
		}
	})
	uni.getStorageSync('o2oCreateData') && Object.assign(createData.value, uni.getStorageSync('o2oCreateData'))
	const goodsId = ref('')
	onLoad((option) => {
		goodsId.value = option.id
		getReserveConfigFn()
	})
	/**
	 * 选择优惠券
	 */
	const confirmSelectCoupon = (coupon : any) => {
		createData.value.discount.coupon_id = coupon ? coupon.id : 0
		calculate()
	}

	const couponList = computed(() => {
		return couponRef.value?.couponList || []
	})


const toDetail = (sku_id) => {
    redirect({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: sku_id }, mode: 'navigateTo' })
}

	// 获取选择时间计算
	const getReserveConfigFn = () => {
		getReserveConfig().then((res) => {
			service_time.value = res.data.reserve
		})
	}
	// 选择地址之后跳转回来
	const selectAddress = uni.getStorageSync('selectAddressCallback')
	if (selectAddress) {
		createData.value.delivery.take_address_id = selectAddress.address_id
		uni.removeStorage({ key: 'selectAddressCallback' })
	}

	/**
	 * 选择地址
	 */
	const toSelectAddress = () => {
		uni.setStorage({
			key: 'selectAddressCallback',
			data: {
				back: `/addon/home_service/user/pages/order/payment?id=${goodsId.value}`
			},
			success() {
				redirect({ url: '/addon/home_service/user/pages/address/index' })
			}
		})
	}

	// 验证地址方法
	const createVerify = () => {
		if (!orderData.value.delivery.take_address) {
			uni.showToast({ title: '请选择上门地址', icon: 'none' })
			return false
		}
		return true
	}

	// 验证上门时间
	const timeVerify = () => {
		if (!createData.value.reserve_service_time) {
			uni.showToast({ title: '请选择上门时间', icon: 'none' })
			return false
		}
		return true
	}

	/**
	 * 订单计算
	 */
	const calculate = () => {
		let data = cloneDeep(createData.value)
		data.sku = JSON.stringify(data.sku)
		orderCalculate(data).then((res) => {
			orderData.value = res.data
			createData.value.order_key = res.data.order_key
		}).catch((err) => {
		  if(err?.data?.message=='HOME_SERVICE_GOODS_NOT_EXIST'){
		    setTimeout(()=>{
          redirect({ url: '/addon/home_service/user/pages/index'})
        },1500);
      }
    })
	}
	calculate()

	let orderId = 0
	// 支付
	const payRef = ref(null)
	/**
	 * 订单创建
	 */
	const create = () => {
		if (!createVerify() || !timeVerify() || createLoading.value) return
		createLoading.value = true
		let data = cloneDeep(createData.value)
		orderCreate(data).then(({ data }) => {
			orderId = data.order_id
			if(!createData.value.card_data?.member_card_item_id){
				if (orderData.value.basic.order_money == 0) {
					redirect({ url: '/addon/home_service/user/pages/order/detail', param: { order_id: orderId }, mode: 'redirectTo' })
				} else {
					payRef.value?.open(data.trade_type, data.order_id, `/addon/home_service/user/pages/order/detail?order_id=${data.order_id}`)
					useSubscribeMessage().request('home_service_order_service')
				}
			}else{
				redirect({ url: '/addon/home_service/user/pages/order/list'})
			}
		}).catch(() => {
			createLoading.value = false
		})
	}

	const selectTime = ref(null)
	const handleTime = () => {
		selectTime.value.show = true
	}
	// 时间(月日时间段)
	const getTime = (e) => {
		createData.value.reserve_service_time = e
		calculate()
	}
	// 时间(年-月-日)
	const getStamp = (e) => {
		createData.value.reserve_service_time_stamp = new Date(e).getTime() / 1000
	}
	/**
	 * 支付弹窗关闭
	 */
	const payClose = () => {
		redirect({ url: '/addon/home_service/user/pages/order/detail', param: { order_id: orderId }, mode: 'redirectTo' })
	}

	// 会员信息
	const memberStore = useMemberStore()

	// #ifdef H5
	const { query } = urlDeconstruction(location.href)
	if (query.code && isWeixinBrowser()) {
		wechatSync({ code: query.code }).then((res) => {
			memberStore.getMemberInfo()
		})
	}
	// #endif
	// #ifdef MP-WEIXIN || APP-PLUS
	const wxxcxstytle = 'padding-top: 25rpx;'
	// #endif
</script>

<style lang="scss" scoped>
	.text-color {
		color: $u-primary;
	}

	.bg-color {
		background-color: $u-primary;
	}

	.text-scale {
		transform: scale(0.8);
	}

	.outline-border {
		@apply flex bg-[#fff] rounded-lg mt-4 p-3;
	}

	.flex-justify {
		width: calc(100% - 48rpx);
		@apply flex justify-between items-center box-border;
	}

	.flex-align {
		@apply flex items-center;
	}

	uni-button:after {
		border: none !important;
	}

	.time-picker :deep(.uni-icons) {
		display: none;
	}

	.time-picker :deep(.uni-calendar-item--checked) {
		background-color: var(--primary-color);
	}

	.time-picker :deep(.uni-datetime-picker--btn) {
		background-color: var(--primary-color);
	}

	.line-feed {
		word-wrap: break-word;
		word-break: break-all;
	}

	.border-bottom-style {
		border-bottom: 2rpx solid #efefef;
	}
</style>