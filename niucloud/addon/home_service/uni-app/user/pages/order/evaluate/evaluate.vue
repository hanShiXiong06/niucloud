<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<!-- 主体内容 -->
		<view class="px-[25rpx] py-[25rpx]">
			<!-- 服务信息 -->
			<view class="bg-white rounded-lg p-[25rpx] mb-[25rpx]">
				<view class="order-goods-item flex" v-for="(goodsItem, goodsIndex) in orderInfo?.item"
					:key="goodsIndex" @click="toDetail(item)">
					<view class="w-[160rpx] h-[160rpx] flex-2">
						<up-image class="rounded-[10rpx] overflow-hidden" width="160rpx" height="160rpx"
							:src="img(goodsItem.item_image_thumb_small ? goodsItem.item_image_thumb_small : '')"
							model="aspectFit" shape="radius" radius="16rpx">
							<template #error>
								<u-icon name="photo" color="#999" size="50"></u-icon>
							</template>
						</up-image>
					</view>
					<view class="ml-[20rpx] flex flex-1 flex-col justify-between">
						<view class="flex justify-between items-center">
							<text
								class="text-[28rpx] text-item  leading-[40rpx] max-h-[80rpx] w-[360rpx] multi-hidden">{{ goodsItem.item_name }}</text>
							<text class="text-right text-[24rpx]">x{{ goodsItem.num }}</text>
						</view>
						<view class="text-[#999999] text-[24rpx]">{{goodsItem.sku_name}}</view>
						<view class="text-[28rpx] ">
							<text class="text-[22rpx] leading-[28rpx] ">订单金额：</text>
							<text class="text-[20rpx] price-font text-[#ff0000]  font-bold">￥</text>
							 
							<text class="price-font text-[34rpx] leading-[1] text-[#ff0000]">{{ Number(orderInfo?.item?.[0]?.price).toString().split('.')[0] }}</text>
							<text
								class="price-font text-[24rpx] font-bold text-[#ff0000]">.{{ Number(orderInfo?.item?.[0]?.price).toFixed(2).split('.')[1] }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 星级评分改为一行显示并使用u-rate组件 -->
			<view class="bg-white rounded-lg p-[25rpx] mb-[25rpx]">
				<!-- 修改justify-between为flex-start，并调整评分部分布局 -->
				<view class="flex items-center">
					<view class="!text-[28rpx] mr-4">{{ t('serviceEvaluation') }}</view>
					<view class="flex items-center flex: 1;">
						<!-- 调整u-rate组件的样式，确保在一行显示 -->
						<u-rate :count="5" v-model="scores" @change="setScore" size="25" gap="4"
							style="margin-right: 4px; flex: 1;margin-bottom: 5rpx;"></u-rate>
						<view class="!text-[26rpx] font-bold text-[#999] ml-1 whitespace-nowrap">{{ getScoreText(scores) }}
						</view>
					</view>
				</view>
			</view>

			<!-- 将评价内容、图片上传和匿名评价合并到一个div中 -->
			<view class="bg-white rounded-lg p-[25rpx] mb-[25rpx]">
				<!-- 评价内容 -->
				<view class="mb-4">
					<view class="text-[28rpx] mb-3">{{ t('pleaseInputComment') }}</view>
					<up-textarea
					    class="border border-gray-200 rounded-md h-[200rpx] text-[28rpx] resize-none bg-[#f9f9f9] p-[24rpx] border-box"
					    v-model="content"
					    placeholder="请输入内容..."
					    :maxlength="200"
					 	:height="180"
					></up-textarea>
				</view>

				<!-- 图片上传 - 使用系统标准upload-img组件 -->
				<view class="mb-[25rpx]">
					<text
						class="text-base text-[28rpx] font-medium text-gray-800 block mb-[25rpx]">{{ t('addPhotos') }}</text>
					<upload-img v-model="images" :max-count="maxImages" :multiple="true" />
				</view>

				<!-- 匿名评价 - 修复点击问题并改进样式 -->
				<view class="pb-[24rpx] border-b border-gray-100">
					<!-- 修改匿名评价按钮部分 -->
					<view class="flex items-center justify-start w-full" @tap="toggleAnonymous">
						<view v-if="isAnonymous" class="iconfont iconxuanze font-bold !text-[var(--primary-color)]"></view>
						<view v-else class="iconfont iconcheckbox_nol !text-[var(--primary-color)]"></view>
						<view class="text-[28rpx] ml-[15rpx]">{{ t('anonymousEvaluation') }}</view>
					</view>
				</view>
			</view>
		</view>

		<!-- 底部提交按钮 - 减少高度 -->
		<view class="fixed bottom-0 left-0 right-0 p-[25rpx] bg-[#fff]">
			<u-button type="info" class="flex-1 mr-[25rpx] !bg-[var(--primary-color)] !text-[#fff] !rounded-[15rpx]"
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
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '订单评价', topStatusBar: { textColor: '#333' ,rollBgColor:"#ffffff"} })
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

		// 获取技师ID
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