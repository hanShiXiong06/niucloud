<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
		<!-- 主体内容 -->
		<view class="px-[25rpx] py-[25rpx]">
			<!-- 服务信息 -->
			<view class="bg-white rounded-lg p-[30rpx] mb-4">
				<view class="flex items-center">
					<image
						v-if="orderInfo && orderInfo.item && orderInfo.item[0] && orderInfo.item[0].item_image_thumb_small"
						class="w-[120rpx] h-[120rpx] mr-[20rpx] rounded-md"
						:src="img(orderInfo.item[0].item_image_thumb_small)" mode="aspectFill"
						@error="handleServiceImageError"  />
					<view class="flex-1">
						<view class="text-[28rpx] font-medium mb-1">
							{{ orderInfo?.item?.[0]?.item_name || t('serviceItem') }}</view>
						<!-- 调整SKU名称、数量和价格的布局 -->
						<view class="flex justify-between items-center !text-[28rpx] text-[#999999] mb-1">
							<view>{{ orderInfo?.item?.[0]?.sku_name || '' }}</view>
							<view>×{{ orderInfo?.item?.[0]?.num || 1 }}</view>
						</view>
						<!-- 修复价格显示，添加小数点 -->
						<view class="text-[#EF000C]">
							<text class="text-base">{{ t('currency') }}</text>
							<text
								class="text-base">{{ formatPriceBeforeDecimal(orderInfo?.item?.[0]?.price || '0.00') }}</text>
							<text class="text-base">.</text>
							<text
								class="text-sm">{{ formatPriceAfterDecimal(orderInfo?.item?.[0]?.price || '0.00') }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 星级评分改为一行显示并使用u-rate组件 -->
			<view class="bg-white rounded-lg p-4 mb-4">
				<!-- 修改justify-between为flex-start，并调整评分部分布局 -->
				<view class="flex items-center">
					<view class="!text-[28rpx] mr-4">{{ t('serviceEvaluation') }}</view>
					<view class="flex items-center flex: 1;">
						<!-- 调整u-rate组件的样式，确保在一行显示 -->
						<u-rate :count="5" v-model="scores" @change="setScore" size="20" gap="4"
							style="margin-right: 4px; flex: 1;"></u-rate>

						<view class="!text-[28rpx] text-gray-500 ml-2 whitespace-nowrap">{{ getScoreText(scores) }}
						</view>
					</view>
				</view>
			</view>

			<!-- 将评价内容、图片上传和匿名评价合并到一个div中 -->
			<view class="bg-white rounded-lg p-[24rpx] mb-4">
				<!-- 评价内容 -->
				<view class="mb-4">
					<view class="text-[30rpx] mb-3">{{ t('pleaseInputComment') }}</view>
					<up-textarea
					    class="border border-gray-200 rounded-md h-[200rpx] text-[28rpx] resize-none bg-[#f9f9f9] p-[24rpx] border-box"
					    v-model="content"
					    placeholder="请输入内容..."
					    :maxlength="200"
					 	:height="180"
					></up-textarea>
				</view>

				<!-- 图片上传 - 使用系统标准upload-img组件 -->
				<view class="mb-4">
					<text
						class="text-base text-[30rpx] font-medium text-gray-800 block mb-[25rpx]">{{ t('addPhotos') }}</text>
					<upload-img v-model="images" :max-count="maxImages" :multiple="true" />
				</view>

				<!-- 匿名评价 - 修复点击问题并改进样式 -->
				<view class="py-[24rpx] border-b border-gray-100">
					<!-- 修改匿名评价按钮部分 -->
					<view class="flex items-center justify-end w-full" @tap="toggleAnonymous">
						<view
							class="w-[40rpx] h-[40rpx] border-2 border-[#999999] rounded-full flex items-center justify-center mr-1 bg-[#EEEEEE] transition-all duration-200"
							:class="{
        '!bg-[#004FFF]': isAnonymous,
        '!border-[#004FFF]': isAnonymous
      }">
							<text v-if="isAnonymous" class="text-white text-xs font-bold">✓</text>
						</view>
						<view class="text-[28rpx]">{{ t('anonymousEvaluation') }}</view>
					</view>
				</view>
			</view>
		</view>

		<!-- 底部提交按钮 - 减少高度 -->
		<view class="fixed bottom-0 left-0 right-0 p-3">.
			<u-button type="info" class="flex-1 mr-[25rpx] !bg-[#004FFF] !text-[#fff] !rounded-[15rpx]"
				@tap="submitEvaluateForm" :disabled="submitting">
				{{ submitting ? t('submitting') : t('submit') }}
			</u-button>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'
	import { onShow, onLoad } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import { getOrderDetail } from '@/addon/home_service/user/api/order'
	import { submitEvaluate } from '@/addon/home_service/user/api/evaluate'
	// 导入系统标准upload-img组件
	import uploadImg from '@/addon/home_service/user/components/upload-img/upload-img.vue'

	// 评分数据
	const scores = ref<number>(5)
	// 评价内容
	const content = ref<string>('')
	// 图片列表
	const images = ref<string[]>([])
	// 最大图片数量
	const maxImages = ref<number>(9)
	// 是否匿名评价
	const isAnonymous = ref<boolean>(false)
	// 订单信息
	const orderInfo = ref<any>(null)
	// 提交状态
	const submitting = ref<boolean>(false)
	// 添加缺失的loading变量声明
	const loading = ref<boolean>(false)

	// 切换匿名评价状态
	const toggleAnonymous = () => {
		isAnonymous.value = !isAnonymous.value;
		console.log('匿名状态已切换为:', isAnonymous.value); // 可选：用于调试
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

	let orderId = 0
	onLoad((option : any) => {
		orderId = option.order_id || 0
		getOrderDetailFn()
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

	// 设置评分
	const setScore = (score : number) => {
		scores.value = score
	}

	// 获取评分对应的文本
	const getScoreText = (score : number) => {
		const scoreTexts = [
			t('veryPoor'),
			t('poor'),
			t('average'),
			t('satisfied'),
			t('verySatisfied')
		]
		return scoreTexts[score - 1] || t('average')
	}

	// 处理服务图片加载错误
	const handleServiceImageError = () => {
		if (orderInfo.value && orderInfo.value.item && orderInfo.value.item[0]) {
			orderInfo.value.item[0].item_image_thumb_small = 'static/resource/images/diy/shop_default.jpg'
		}
	}

	// 提交评价表单
	const submitEvaluateForm = () => {
		// 验证评价内容
		if (!content.value.trim()) {
			uni.showToast({ title: t('commentEmpty'), icon: 'none' })
			return
		}

		// 验证评分
		if (!scores.value || scores.value === 0) {
			uni.showToast({ title: t('selectRating'), icon: 'none' })
			return
		}

		// 获取订单ID
		const orderId = getOrderId()
		if (!orderId) {
			uni.showToast({ title: t('orderIdNotFound'), icon: 'none' })
			return
		}

		// 获取商品ID（从item[0]获取）
		const goodsId = orderInfo.value?.item?.[0]?.goods_id || 0
		if (!goodsId) {
			uni.showToast({ title: t('goodsInfoNotFound'), icon: 'none' })
			return
		}

		// 获取师傅ID
		const technicianId = orderInfo.value?.technician_id || 0
		const storeId = orderInfo.value?.store_id || 0
		// 准备提交数据
		// 直接使用images数组，不再转换为字符串
		const submitData = {
			order_id: orderId,
			goods_id: goodsId,
			content: content.value.trim(),
			images: images.value, // 直接传递数组
			scores: scores.value,
			is_anonymous: isAnonymous.value ? 1 : 2, // 1表示匿名，2表示不匿名
			store_id: storeId,
			technician_id: technicianId
		}
		submitting.value = true
		submitEvaluate(submitData).then((res : any) => {
			submitting.value = false
			if (res.code === 1) {
				uni.showToast({
					title: t('commentSuccess'),
					icon: 'none',
					success: () => {
						// 延迟跳转回订单列表或详情页
						setTimeout(() => {
							const pages = getCurrentPages()
							if (pages.length > 1) {
								uni.navigateBack({ delta: 1 })
							} else {
								redirect({ url: '/addon/home_service/user/pages/order/list' })
							}
						}, 1500)
					}
				})
			}
		}).catch(() => {
			submitting.value = false
		})
	}

	// 页面加载时获取订单详情
	onMounted(() => {
	})
	onShow(() => {
	})

	// 主题颜色函数（与参考页面保持一致）
	const themeColor = () => {
		return {} // 可以根据项目需求返回主题颜色样式
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

	/* 星级评分文字不换行 */
	.whitespace-nowrap {
		white-space: nowrap;
	}

	/* 增强匿名评价按钮的点击区域和样式 */
	.anonymous-container {
		padding: 20rpx 0;
	}

	/* 匿名评价圆圈样式优化 */
	.anonymous-checkbox {
		border-width: 2px !important;
		border-color: #004FFF !important;
	}
</style>