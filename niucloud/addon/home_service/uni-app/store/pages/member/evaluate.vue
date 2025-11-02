<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
	<!-- #endif -->
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()" v-if="!loading">
		<!-- 评价类型和筛选条件合并到同一行 -->
		<view class="flex px-[30rpx] py-[20rpx] border-b border-[var(--border-color)] box-border z-index-99 justify-between items-center fixed w-[100vw] bg-[#fff]" :style="{'top': systemStore.topTabbarInfo.fullHeight || 0}">
			<view class="flex items-center">
				<text class="text-[30rpx] font-bold text-[var(--store-bg-one)]">已评价</text>
				<text class="ml-[10rpx] text-sm text-gray-500">({{ totalCount }})</text>
			</view>
			<view class="flex flex-nowrap">
				<!-- 为综合按钮添加动态样式，根据currentFilter状态切换 -->
				<view
					class="flex items-center justify-center mr-[20rpx] w-[90rpx] py-[5rpx] rounded-[16rpx] border-[2rpx] border-style box-border"
					:class="currentFilter === 'comprehensive' ? 'bg-[#E0EAFF] active-border' : 'bg-transparent border-transparent'"
					@click="switchFilter('comprehensive')">
					<text class="!text-[26rpx]"
						:class="currentFilter === 'comprehensive' ? 'text-[var(--store-bg-one)]' : 'text-gray-500'">
						综合
					</text>
				</view>
				<!-- 时间筛选按钮 -->
				<view
					class="flex items-center justify-center w-[90rpx] py-[8rpx] rounded-[16rpx] border-[2rpx] px-[10rpx] border-style"
					:class="currentFilter === 'time' ? 'bg-[#E0EAFF] active-border' : 'bg-transparent border-transparent'"
					@click="switchFilter('time')">
					<text class="!text-[26rpx]"
						:class="currentFilter === 'time' ? 'text-[var(--store-bg-one)]' : 'text-gray-500'">时间</text>
					<view class="ml-[8rpx] text-sm transition-transform duration-300 flex flex-col">
						<text class="iconfont iconshangjiantou text-[20rpx] mb-[-5rpx]"
							:class="sortDirection == 'desc' && currentFilter === 'time' ? 'text-[var(--store-bg-one)] ' : 'text-[#999999]'"></text>
						<text class="iconfont iconxiajiantou text-[20rpx]"
							:class="sortDirection == 'asc' && currentFilter === 'time' ? 'text-[var(--store-bg-one)]' : ' text-[#999999]'"></text>
					</view>
				</view>
			</view>
		</view>

		<!-- 评价列表 -->
		<mescroll-body ref="mescrollRef" top="" @init="mescrollInit" :down="{ use: false }" @up="getEvaluateList">
			<view class="pt-[100rpx]"></view>
			<view class="bg-white mt-[20rpx] mb-[20rpx] mx-[25rpx] rounded-lg py-[10rpx]"
				v-for="(item, index) in evaluateList" :key="index">
				<!-- 评分 - 使用图片替换Unicode符号 -->
				<view class="flex items-center justify-between px-[30rpx] pt-[20rpx]">
					<u-rate :count="5" v-model="item.scores"></u-rate>
					<text class=" text-[24rpx] text-[#999999]">发布于{{ item.create_time }}</text>
					<text v-if="item.is_anonymous === 1" class="text-[24rpx] text-[#999999]">{{item.member_name}}</text>
				</view>

				<!-- 服务信息 -->
				<view class="px-[30rpx] py-[25rpx] flex items-center bg-[#F4F8FF] mx-[25rpx] rounded-lg my-[15rpx]">
					<image v-if="item.order && item.order.item_image_thumb_small"
						class="w-[60rpx] h-[60rpx] mr-[18rpx] rounded-[8rpx]"
						:src="img(item.order.item_image_thumb_small)" mode="aspectFill"
						@error="handleServiceImageError(item)" />
					<text v-if="item.order" class="text-base">{{ item.order.order_name }}</text>
				</view>

				<!-- 评价内容 -->
				<view class="px-[30rpx] py-[10rpx]">
					<text class="text-base text-gray-600">{{ item.content }}</text>
				</view>
				<!-- 评价图片 -->
				<view v-if="item.image_mid && item.image_mid.length > 0"
					class="px-[30rpx] py-[10rpx] pb-[20rpx]  grid grid-cols-4 gap-4">
					<view v-for="(imageUrl, imgIndex) in item.image_mid" :key="imgIndex">
						<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
							@click="previewImage(item,imgIndex)">
							<view slot="error" style="font-size: 24rpx;">加载失败</view>
						</u--image>
					</view>
				</view>
			</view>

			<!-- 无数据提示 -->
			<mescroll-empty v-if="evaluateList.length === 0 && !loading" :option="{tip: '暂无评价'}"></mescroll-empty>
		</mescroll-body>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'  // 导入onMounted
	import { onShow } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img, goback } from '@/utils/common'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import { getEvaluateList as getEvaluateListAPI } from '@/addon/home_service/store/api/evaluate'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '评价管理', topStatusBar: { textColor: '#333' } })
	// 返回上一页
	const onBack = () => {
		if (getCurrentPages().length > 1) {
			uni.navigateBack({ delta: 1 })
		} else {
			goback({ url: '/app/pages/index/index', mode: 'redirectTo' })
		}
	}

	// 评价数据接口定义
	interface OrderItem {
		order_id : number
		order_name : string
		item_image : string
		item_image_thumb_small : string
	}

	interface EvaluateItem {
		evaluate_id : number
		site_id : number
		order_id : number
		goods_id : number
		member_id : number
		member_name : string
		member_head : string
		content : string
		images : string[]
		is_anonymous : number
		scores : number
		is_audit : number
		explain_first : string
		create_time : string
		update_time : number
		order : OrderItem
	}

	// 列表数据
	const evaluateList = ref<EvaluateItem[]>([])
	const loading = ref<boolean>(false)
	const currentFilter = ref<string>('comprehensive')
	const sortDirection = ref<'asc' | 'desc'>('desc') // 排序方向，默认为降序
	const totalCount = ref<number>(0)
	const mescrollRef = ref<any>(null)
	let mescrollInstance : any = null

	// 初始化 mescroll
	const mescrollInit = (mescroll : any) => {
		mescrollInstance = mescroll
	}

	// 获取评价列表 - 对接真实API
	const getEvaluateList = (mescroll ?: any) => {
		loading.value = true

		// API请求参数
		const params : Record<string, any> = {
			page: mescroll?.num || 1,
			limit: mescroll?.size || 10
		}

		// 根据筛选条件设置对应的排序字段和方向
		if (currentFilter.value === 'comprehensive') {
			// 综合排序 - 使用scores字段
			params.order = 'scores'
			params.sort = sortDirection.value
		} else if (currentFilter.value === 'time') {
			// 时间排序 - 使用create_time字段
			params.order = 'create_time'
			params.sort = sortDirection.value
		}

		// 调用真实API
		getEvaluateListAPI(params).then((res : any) => {
			// 根据接口返回格式处理数据
			const data = res.data || {}
			let list = (data.data || []) as any[]

			// 对数据进行转换处理
			list = list.map(item => {
				// 处理图片路径，移除反引号和空格
				const processImageUrl = (url : string) => {
					if (typeof url === 'string') {
						// 移除前后的反引号和空格
						return url.replace(/^[`\s]+|[`\s]+$/g, '')
					}
					return url
				}

				// 处理图片字段映射和格式
				const processedItem = { ...item }

				// 为了防止原有的images字段有问题，也进行处理
				if (item.images && Array.isArray(item.images)) {
					processedItem.images = item.images.map(processImageUrl)
				}

				return processedItem
			})

			// 设置列表数据
			if (params.page === 1) {
				evaluateList.value = []
			}
			evaluateList.value = evaluateList.value.concat(list)

			// 更新总数
			totalCount.value = data.total || 0

			// 结束加载
			if (mescroll) {
				mescroll.endSuccess(list.length, {
					hasNext: evaluateList.value.length < totalCount.value
				})
			}
		}).catch((error : any) => {
			console.error('获取评价列表失败:', error)
			if (mescroll) {
				mescroll.endErr()
			}
		}).finally(() => {
			loading.value = false
		})
	}

	// 切换筛选条件
	const switchFilter = (filter : string) => {
		if (currentFilter.value === filter) {
			// 如果点击的是同一个筛选条件，则切换排序方向
			sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
		} else {
			currentFilter.value = filter
			// 切换筛选条件时，重置排序方向为默认的降序
			sortDirection.value = 'desc'
		}

		evaluateList.value = []

		if (mescrollInstance) {
			// 重置上拉加载，并且立即重新获取数据
			mescrollInstance.resetUpScroll()
		} else {
			// 如果mescrollInstance还未初始化，直接调用数据加载函数
			getEvaluateList()
		}
	}

	// 处理图片加载错误
	const handleStarImageError = (event : any, isFilled : boolean) => {
		// 使用默认的星星图片路径
		event.target.src = isFilled ?
			img('/addon/home_service/store/member/evaluate/sx.png') :
			img('/addon/home_service/store/member/evaluate/kx.png')
	}

	const handleServiceImageError = (item : EvaluateItem) => {
		// 设置默认服务图片
		item.order.item_image_thumb_small = img('static/resource/images/diy/shop_default.jpg')
	}

	const handleEvaluateImageError = (item : EvaluateItem, imgIndex : number) => {
		// 设置默认评价图片
		item.images[imgIndex] = img('static/resource/images/diy/shop_default.jpg')
	}

	// 组件挂载时立即加载数据
	onMounted(() => {
		// 不依赖mescroll，直接加载数据
		getEvaluateList()
	})

	// 页面显示时获取数据
	onShow(() => {
		// 主动触发数据加载
		if (mescrollInstance) {
			mescrollInstance.resetUpScroll()
		} else {
			// 如果mescrollInstance还未初始化，直接调用数据加载函数
			getEvaluateList()
		}
	})
	const previewImage = (image : string, index : number) => {
		var urls = []
		for (let i = 0; i < image.image_mid.length; i++) {
			urls.push(img(image.image_mid[i]))
		}
		console.log(urls)
		// return
		uni.previewImage({
			urls: urls,
			current: index,
			indicator: 'none'
		})
	}
</script>

<style lang="scss">
	@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	.border-style {
		border: 2rpx solid #cccccc;
	}

	.active-border {
		border: 2rpx solid #004FFF;
	}
</style>