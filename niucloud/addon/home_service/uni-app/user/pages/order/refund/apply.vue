<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<!-- 主体内容 -->
		<view class="m-[24rpx]">
			<!-- 服务信息 -->
			<view class="bg-white rounded-lg p-[24rpx] mb-[24rpx]">
				<view class="flex items-center">
					<image
						v-if="orderInfo && orderInfo.item && orderInfo.item[0] && orderInfo.item[0].item_image_thumb_small"
						class="w-[120rpx] h-[120rpx] mr-[20rpx] rounded-md"
						:src="img(orderInfo.item[0].item_image_thumb_small)" mode="aspectFill"
						@error="handleServiceImageError" />
					<view class="flex-1">
						<!-- 调整商品名称和数量在同一行 -->
						<view class="flex justify-between items-center">
							<view class="text-[28rpx] mb-1">
								{{ orderInfo?.item?.[0]?.item_name || t('serviceItem') }}
							</view>
							<view class="text-sm text-[#999999]">×{{ orderInfo?.item?.[0]?.num || 1 }}</view>
						</view>

						<!-- 服务时间单独成行 -->
						<view class="text-[24rpx] text-[#999999] mb-1">时间：{{ orderInfo?.create_time || '' }}</view>

						<!-- 修复价格显示，添加小数点，并使实付款文本为黑色 -->
						<view class="flex items-baseline">
							<view class="text-[24rpx] text-black mr-1">实付款</view>
							<text class="text-xs text-[#EF000C]">{{ t('currency') }}</text>
							<text
								class="text-lg text-[#EF000C]">{{ formatPriceBeforeDecimal(orderInfo?.pay_money || 0) }}</text>
							<text class="text-xs text-[#EF000C]">.</text>
							<text
								class="text-xs text-[#EF000C]">{{ formatPriceAfterDecimal(orderInfo?.pay_money || 0) }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 选择退款原因 - 使用uView的radio组件 -->
			<view class="bg-white rounded-lg p-[24rpx] mb-4">
				<view class="text-[30rpx] mb-[24rpx]">{{ t('selectRefundReason') }}</view>
				<u-radio-group v-model="refundReason" class="refund-reason-group">
					<view v-for="(reason, index) in refundReasons" :key="index" class="reason-item">
						<text class="reason-text">{{ reason.label }}</text>
						<u-radio :name="reason.value" active-color="var(--primary-color)" inactive-color="#999999"  shape="circle" size="15"></u-radio>
					</view>
				</u-radio-group>
			</view>

			<!-- 补充说明和凭证上传 -->
			<view class="bg-white rounded-lg p-[24rpx] mb-[24rpx]">
				<!-- 补充说明 -->
				<view class="mb-[24rpx]">
					<view class="text-[30rpx] mb-3">补充描述</view>
					<textarea
						class="w-[100%] p-[24rpx] box-border border-style rounded-md h-[200rpx] text-[30rpx] resize-none text-[26rpx]"
						v-model="description" :placeholder="t('descriptionPlaceholder')" maxlength="200"></textarea>
					<view class="text-right text-sm text-gray-400 mt-1">{{ description.length }}/200</view>
				</view>
				<!-- 凭证上传 -->
				<view>
					<text
						class="text-[30rpx] text-[30rpx] font-medium text-gray-800 block mb-[25rpx]">{{ t('uploadProof') }}</text>
					<upload-img v-model="images" :max-count="maxImages" :multiple="true" />
					<view class="text-sm text-gray-400 mt-2">最多上传6张图片</view>
				</view>
			</view>
		</view>

		<!-- 提交按钮 -->
		<view class="w-full footer bg-[#fff]" >
			<view
				class="pb-[35rpx] px-[var(--sidebar-m)] bg-[#fff] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class=" !text-[#fff] !rounded-lg  !text-[#fff] !bg-[var(--primary-color)] h-[80rpx] !leading-[80rpx] rounded-[10rpx] !text-[26rpx] font-500"
					@click="submitRefundForm" :disabled="btnDisabled" :loading="operateLoading"
					:class="{'opacity-50': btnDisabled}">
					{{ submitting ? t('submitting') : t('submitRefundApply') }}
				</button>
				<view class="text-center text-xs text-gray-400 mt-2">{{ t('submitNotice') }}</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { t } from '@/locale'
	import { onLoad } from '@dcloudio/uni-app'
	import { img, redirect } from '@/utils/common'
	import { getOrderDetail } from '@/addon/home_service/user/api/order'
	import { submitRefund, getRefundReasonList } from '@/addon/home_service/user/api/refund'
	// 导入系统标准upload-img组件
	import uploadImg from '@/addon/home_service/user/components/upload-img/upload-img.vue'
	// 导入uView的radio组件
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '申请售后', topStatusBar: { textColor: '#333' ,rollBgColor:"#ffffff"} })
	// 订单信息
	const orderInfo = ref<any>(null)
	// 退款原因（绑定radio-group的值）
	const refundReason = ref(null)
	// 退款类型
	const refundType = ref<string>('refund') // refund: 申请退款, complain: 服务投诉, rework: 再次维修, other: 其他问题
	// 退款原因列表
	const refundReasons = ref([])
	const changeReason = (e:any) =>{
		console.log(e)
	}
	// 补充说明
	const description = ref<string>('')
	// 图片列表
	const images = ref<string[]>([])
	// 最大图片数量
	const maxImages = ref<number>(6)
	// 提交状态
	const submitting = ref<boolean>(false)
	// 加载状态
	const loading = ref<boolean>(false)
	// 按钮禁用状态
	const btnDisabled = ref<boolean>(false)
	// 操作加载状态
	const operateLoading = ref<boolean>(false)

	let orderId = 0
	onLoad((option : any) => {
		orderId = option.order_id || 0
		getOrderDetailFn()
		// 获取退款原因列表
		const getRefundReasonsFn = () => {
			getRefundReasonList().then((res : any) => {
				Object.keys(res.data).forEach((item,index)=>{
					let obj = {
						label:res.data[item],
						value:item
					}
					refundReasons.value.push(obj)
				})
			}).catch(() => {
				// 保持默认的退款原因列表
			})
		}
		getRefundReasonsFn()
	})

	// 获取订单ID函数
	const getOrderId = () => {
		return orderId
	}

	// 获取订单详情
	const getOrderDetailFn = () => {
		loading.value = true
		getOrderDetail(Number(orderId)).then((res : any) => {
			loading.value = false
			if (res.code === 1 && res.data) {
				orderInfo.value = res.data
			}
		}).catch(() => {
			loading.value = false
		})
	}

	// 处理服务图片加载错误
	const handleServiceImageError = () => {
		if (orderInfo.value && orderInfo.value.item && orderInfo.value.item[0]) {
			orderInfo.value.item[0].item_image_thumb_small = 'static/resource/images/diy/shop_default.jpg'
		}
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

	// 提交退款申请
	const submitRefundForm = () => {
		// 验证退款原因
		if (!refundReason.value) {
			uni.showToast({ title: t('selectRefundReason'), icon: 'none' })
			return
		}

		// 验证补充说明
		if (!description.value.trim()) {
			uni.showToast({ title: t('pleaseInputDescription'), icon: 'none' })
			return
		}

		// 获取订单ID
		const orderId = getOrderId()
		if (!orderId) {
			uni.showToast({ title: t('orderIdNotFound'), icon: 'none' })
			return
		}

		// 准备提交数据 - 将description改为remark
		const submitData = {
			order_id: orderId,
			type: refundType.value,
			reason: refundReason.value,
			remark: description.value.trim(), // 修正为正确的字段名remark
			voucher: images.value.join(','), // 将图片数组转换为逗号分隔的字符串
			goods_id: orderInfo.value?.item?.[0]?.goods_id || 0,
			technician_id: orderInfo.value?.technician_id || 0,
			store_id: orderInfo.value?.store_id || 0
		}

		submitting.value = true
		operateLoading.value = true
		btnDisabled.value = true
		submitRefund(submitData).then((res : any) => {
			submitting.value = false
			operateLoading.value = false
			btnDisabled.value = false
			if (res.code === 1) {
				uni.showToast({
					title: t('refundApplySuccess'),
					icon: 'none',
					success: () => {
						// 延迟跳转回订单列表或详情页
						setTimeout(() => {
							redirect({ url: '/addon/home_service/user/pages/order/refund/list' })
						}, 1500)
					}
				})
			}
		}).catch(() => {
			submitting.value = false
			operateLoading.value = false
			btnDisabled.value = false
		})
	}
</script>

<style lang="scss" scoped>
	/* 安全区域内边距 */
	.bottom-safe-area {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	/* 调整textarea的placeholder样式 */
	textarea::placeholder {
		color: #999999;
		font-size: 28rpx;
	}

	/* 优化按钮禁用状态 */
	button:disabled {
		opacity: 0.6;
	}

	/* 网格布局 */
	.grid {
		display: grid;
	}

	.grid-cols-2 {
		grid-template-columns: repeat(2, 1fr);
	}

	.gap-4 {
		gap: 16rpx;
	}

	/* 空间间隔 */
	.space-y-4>*+* {
		margin-top: 16rpx;
	}

	.border-style {
		border: 2rpx solid #efefef
	}

	// 底部安全区域适配
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}

	/* 退款原因选择区域样式 */
	.refund-reason-group {
		width: 100%;
	}
	
	.reason-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 10rpx 0;
		width: 100%;
	}
	
	.reason-text {
		flex: 1;
		text-size: 28rpx;
		color: #333333;
	}
	
	/* 调整radio组件样式 */
	::v-deep .u-radio {
		width: auto;
	}
</style>