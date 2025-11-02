<template>
	<view :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<view class="bg-[#f8f8f8] min-h-screen overflow-hidden" v-if="!loading && Object.keys(detail).length">
			<!-- 主体内容 -->
			<view class="p-[25rpx]">
				<!-- 售后状态 -->
				<view class="bg-white rounded-[10rpx] p-[25rpx] mb-[25rpx]">
					<text class="text-[32rpx] font-bold text-[#333]">{{detail.status_name}}</text>
					<view class="text-[26rpx] text-[#666666] mt-[5rpx]" v-if="detail.refund_no">退款编号：{{detail.refund_no}}</view>
				</view>

				<!-- 处理结果 -->
				<view class="bg-white rounded-[10rpx] p-[25rpx] mb-[25rpx]"  v-if="detail.money !== undefined && detail.money !== null && parseFloat(detail.money) > 0">
					<text class="text-[30rpx] font-bold text-[#333] mb-[20rpx] block">处理结果</text>
					<view class="text-[28rpx] text-[#666] mb-[20rpx]">
						<text>经协商退款金额</text>
						<text
							class="text-[34rpx] font-bold text-red-500 ml-[20rpx]">¥{{ parseFloat(detail.money).toFixed(2) }}</text>
					</view>
					<view v-if="detail.transfer_time" class="text-[26rpx] text-[#666] mb-[20rpx]">
						<text>{{ detail.transfer_time }}前按原支付路径退回</text>
					</view>
					<view v-if="detail.coupon_money" class="text-[26rpx] text-[#666]">
						<text>退回优惠券</text>
						<text
							class="text-[34rpx] font-bold text-red-500 ml-[20rpx]">¥{{ parseFloat(detail.coupon_money).toFixed(2) }}</text>
					</view>
					<view v-if="detail.coupon_valid_time" class="text-[26rpx] text-[#666] mt-[10rpx]">
						<text>有效期至{{ detail.coupon_valid_time }}</text>
					</view>
				</view>

				<!-- 售后申请详情 -->
				<view class="bg-white rounded-[10rpx] p-[25rpx] mb-[30rpx]">
					<text class="text-[30rpx] font-bold text-[#333] mb-[30rpx] block">售后申请详情</text>
					<view class="text-[28rpx] text-[#666] mb-[30rpx]">
						<text class="text-[#999999]">退款原因:</text>
						<text
							class="ml-[20rpx] text-[#111111] ">{{ detail.reason_name || detail.reason || '--' }}</text>
					</view>
					<view class="text-[28rpx] text-[#666]">
						<text class="text-[#999999] block mb-[10rpx]">退款说明</text>
						<view class="text-[#666] bg-[#f6f6f6] p-[25rpx] rounded-lg text-[26rpx] mt-[20rpx]">
							{{ detail.remark || '--' }}</view>
					</view>
					<view class="text-[28rpx] text-[#666] mt-[25rpx]" v-if="detail.refuse_reason">
						<text class="text-[#999999] block mb-[10rpx]">拒绝理由</text>
						<view class="text-[#ff0000] bg-[#f6f6f6] p-[25rpx] rounded-lg text-[26rpx] mt-[20rpx]">
							{{ detail.refuse_reason || '--' }}</view>
					</view>
					<view v-if="detail.voucher" class="mt-[30rpx]">
						<text class="text-[28rpx] text-[#999999] block mb-[10rpx]">退款照片</text>
						<view class="flex flex-wrap">
							<template v-for="(item,index) in detail.voucher.split(',')">
								<view class="w-[160rpx] h-[160rpx] mr-[20rpx] mb-[20rpx]" @click="handleImg(item)">
									<up-image class="rounded-[10rpx] overflow-hidden" width="160rpx" height="160rpx"
										:src="img(item ? item : '')" model="aspectFill">
										<template #error>
											<u-icon name="photo" color="#999" size="50"></u-icon>
										</template>
									</up-image>
								</view>
							</template>
						</view>
					</view>
				</view>

				<!-- 订单信息 -->
				<view class="bg-white rounded-[10rpx] p-[25rpx] mb-[25rpx]">
					<text class="text-[30rpx] font-bold text-[#333] mb-[30rpx] block">订单信息</text>
					<view v-if="detail.orderMain" class="text-[28rpx] text-[#666] mb-[20rpx]">
						<text class="text-[#999999]">订单编号</text>
						<text class="ml-[20rpx]">{{ detail.orderMain.order_no }}</text>
					</view>
					<view v-if="detail.orderMain" class="text-[28rpx] text-[#666] mb-[20rpx]">
						<text class="text-[#999999]">服务时间</text>
						<text class="ml-[20rpx]">{{ detail.orderMain.reserve_service_time }}</text>
					</view>
					<view v-if="detail.orderMain" class="text-[28rpx] text-[#666] mb-[20rpx]">
						<text class="text-[#999999]">客户信息</text>
						<text class="ml-[20rpx]">{{detail.orderMain.taker_name}}
							{{ maskPhone(detail.orderMain.taker_mobile) }}</text>
					</view>
					<view v-if="detail.orderMain" class="text-[28rpx] text-[#666] mb-[20rpx]">
						<text class="text-[#999999]">服务地址</text>
						<text class="ml-[20rpx]">{{detail.orderMain.taker_address}}</text>
					</view>
				</view>

				<!-- 服务项目 -->
				<view class="bg-white rounded-[10rpx] p-[25rpx] mb-[25rpx]">
					<text class="text-[30rpx] font-bold text-[#333] mb-[30rpx] block">服务项目</text>
					<view class="bg-white rounded-lg ">
						<view class="flex items-center p-[24rpx] boder-style rounded-lg" @click="toGoodsDetail(detail?.order_item?.[0]?.goods_id)">
							<image
								v-if="detail && detail.order_item "
								class="w-[160rpx] h-[160rpx] mr-[20rpx] rounded-md"
								:src="img(detail.order_item[0].item_image_thumb_small)" mode="aspectFill"
								@error="handleServiceImageError" />
							<view class="flex-1 h-[160rpx] flex flex-col justify-between">
								<!-- 调整商品名称和数量在同一行 -->
								<view class="flex justify-between items-center">
									<view class="text-[28rpx] mb-1">
										{{ detail.order_item[0].item_name || t('serviceItem') }}
									</view>
									<view class="text-sm text-[#999999]">×{{ detail.order_item[0].num || 1 }}</view>
								</view>
							
								<!-- 服务时间单独成行 -->
								<view class="text-[24rpx] text-[#999999] mb-1">时间：{{ detail.create_time || '' }}</view>
							
								<!-- 修复价格显示，添加小数点，并使实付款文本为黑色 -->
								<view class="flex items-baseline">
									<view class="text-[24rpx] text-black mr-1">实付款</view>
									<text class="text-xs text-[#EF000C]">{{ t('currency') }}</text>
									<text
										class="text-lg text-[#EF000C]">{{ formatPriceBeforeDecimal(detail.order_item[0].price || 0) }}</text>
									<text class="text-xs text-[#EF000C]">.</text>
									<text
										class="text-xs text-[#EF000C]">{{ formatPriceAfterDecimal(detail.order_item[0].price || 0) }}</text>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
		<view class="w-full footer bg-[#fff]" v-if="detail.status == 'wait_refund'">
			<view 
				class="py-[var(--top-m)] px-[var(--sidebar-m)] bg-[#fff] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="!bg-[var(--primary-color)] !text-[#fff] !rounded-lg  !text-[#fff] !bg-[var(--primary-color)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
					@click="refundBtnFn(detail)"
					:class="{'opacity-50': btnDisabled}">{{ t('refundApply') }}
				</button>
			</view>
		</view>
		<view class="w-full footer bg-[#fff]" v-if="detail.status == 'refund_refuse'">
			<view 
				class="py-[var(--top-m)] px-[var(--sidebar-m)] bg-[#fff] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="!bg-[var(--primary-color)] !text-[#fff] !rounded-lg  !text-[#fff] !bg-[var(--primary-color)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
					@click="rsetApply(detail)"
					:class="{'opacity-50': btnDisabled}">重新申请
				</button>
			</view>
		</view>
		<loading-page :loading="loading"></loading-page>
		<u-modal :show="cancelRefundshow" confirmColor="var(--primary-color)" :content="t('cancelRefundContent')" :showCancelButton="true" :closeOnClickOverlay="true" @cancel="refundCancel" @confirm="refundConfirm"></u-modal>
	</view>
</template>

<script setup lang="ts">
	import { ref, reactive } from 'vue';
	import { onLoad, onUnload } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common';
	import { getRefundDetail,cancelRefund, getRefundStatus } from '@/addon/home_service/user/api/refund';
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '售后详情', topStatusBar: { textColor: '#333' ,rollBgColor:"#ffffff"} })
	const cancelRefundshow = ref(false);
	const detail = ref<Object>({});
	const loading = ref<boolean>(true);
	const refundNo = ref('');
	const refundConfirm = ()=>{
		cancelRefund(currRefundId).then((res) => {
			cancelRefundshow.value = false;
			refundDetailFn(refundNo.value);
		}).catch(() => {
			cancelRefundshow.value = false;
		})
	}
	
	const refundCancel = ()=>{
		cancelRefundshow.value = false;
	}

	
	onLoad((option) => {
		refundNo.value = option.refund_no;
		refundDetailFn(refundNo.value);
	});


  const toGoodsDetail = (id: string | number) => {
    redirect({ url: '/addon/home_service/user/pages/goods/detail', param: { goods_id: id }, mode: 'navigateTo' })
  }

	// 处理服务图片加载错误
	const handleServiceImageError = () => {
	  if (detail.value && detail.value.order_item && detail.value.order_item[0]) {
	    detail.value.order_item[0].item_image_thumb_small = 'static/resource/images/diy/shop_default.jpg'
	  }
	}
	let currRefundId = "";
	const refundBtnFn = (data) => {
		currRefundId = data.refund_id;
		cancelRefundshow.value = true;
	}
	// 价格格式化函数
	const formatPriceBeforeDecimal = (price : string | number) : string => {
		const priceStr = typeof price === 'number' ? price.toFixed(2) : price
		const parts = priceStr.split('.')
		return parts[0] || '0'
	}

	const formatPriceAfterDecimal = (price : string | number) : string => {
		const priceStr = typeof price === 'number' ? price.toFixed(2) : price
		const parts = priceStr.split('.')
		return parts[1] || '00'
	}
	const rsetApply = (data:any) =>{
		redirect({ url: '/addon/home_service/user/pages/order/refund/apply', param: { order_id: data.order_id } })
	}
	const handleBack = () => {
		uni.navigateBack();
	}

	const refundDetailFn = (refundNo) => {
		loading.value = true;
		getRefundDetail(refundNo).then((res) => {
			detail.value = res.data;
			loading.value = false;
		}).catch(() => {
			loading.value = false;
		})
	}

	// 手机号脱敏
	const maskPhone = (phone : any) => {
		if (!phone) return '';
		if (!/^1[3-9]\d{9}$/.test(phone)) {
			return phone;
		}
		return phone.replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2');
	}

	const handleImg = (url) => {
		let tmp = [];
		if (detail.value.voucher) {
			tmp = detail.value.voucher.split(',').map(item => {
				return img(item)
			})
		}
		uni.previewImage({
			current: img(url),
			urls: tmp,
			indicator: "number",
			loop: true
		})
	}

	// 关闭预览图片
	onUnload(() => {
		// #ifdef  H5 || APP
		uni.closePreviewImage()
		// #endif
	})
</script>
<style lang="scss" scoped>
	.text-item {
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.text-color {
		color: $u-primary;
	}

	.multi-hidden {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.price-font {
		font-family: 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
		font-weight: bold;
	}

	/* 底部安全区域适配 */
	.body-bottom {
		padding-bottom: env(safe-area-inset-bottom, 0);
		padding-bottom: constant(safe-area-inset-bottom, 0);
	}
	.boder-style{
		border: 2rpx solid #efefef;
	}
	// 底部安全区域适配
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>