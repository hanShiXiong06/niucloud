<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class">
		<!-- 日期切换标签 -->
		<view class="px-4 py-3">
			<view class="flex space-x-6 font-bold">
				<view class="relative flex items-center tab-item" :class="isTodayView ? 'tab-item-active' : ''"
					@click="switchToToday">
					<text class="">
						{{ t('todayOrders') }}
					</text>
				</view>
				<view class="relative flex items-center tab-item" :class="!isTodayView ? 'tab-item-active' : ''"
					@click="switchToMonth">
					<text class="">
						{{ t('monthOrders') }}
					</text>
				</view>
			</view>
		</view>

		<!-- 统计卡片 - 蓝色背景 -->
		<view class="px-4 mx-[24rpx] rounded-lg mt-[10rpx] stats-card-container">
			<view class="stats-card rounded-lg overflow-hidden">
				<!-- 月份选择器 - 仅在月视图显示 -->
				<view v-if="!isTodayView" class="px-1 py-[24rpx] flex justify-between items-center"
					@click="toggleMonthSelector">
					<text class="text-white text-[30rpx] font-bold">{{ selectedYear }}年 {{ selectedMonth }}月</text>
					<text class="text-white text-sm flex items-center transition-transform duration-300" 
						:class="showMonthSelector ? 'rotate-180' : ''">
						<text class="iconfont iconxiajiantou" v-if="!showMonthSelector"></text>
						<text class="iconfont iconshangjiantou" v-else></text>
					</text>
				</view>

				<!-- 月份选择器面板 -->
				<view v-if="!isTodayView && showMonthSelector" class="bg-blue-400 p-4">
					<view class="flex justify-between mb-3">
						<text class="text-white" @click="prevYear"><text
							class="iconfont iconjiantou3 text-[24rpx]"></text></text>
						<text class="text-white font-medium">{{ currentYear }}年</text>
						<text class="text-white" @click="nextYear"><text class="iconfont iconarrow-right"></text></text>
					</view>
					<view class="grid grid-cols-3 gap-2">
						<text v-for="month in 12" :key="month" class="text-center py-2 rounded" 
							:class="currentYear === selectedYear && month === selectedMonth ? 'bg-white text-blue-500' : 'text-white'" 
							@click="selectMonth(month)">
							{{ month }}月
						</text>
					</view>
				</view>

				<!-- 统计数据 - 根据视图类型显示不同数据 -->
				<view class="flex px-4 py-4">
					<!-- 今日订单视图 -->
					<template v-if="isTodayView">
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ todayOrderStats.wait_service_count }}</text>
							<text class="text-white text-sm">{{ t('pendingService') }}</text>
						</view>
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ todayOrderStats.wait_check_count }}</text>
							<text class="text-white text-sm">{{ t('pendingAcceptance') }}</text>
						</view>
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ todayOrderStats.finish_count }}</text>
							<text class="text-white text-sm">{{ t('completedOrders') }}</text>
						</view>
					</template>

					<!-- 月订单视图 -->
					<template v-else>
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ monthOrderStats.finish_count }}</text>
							<text class="text-white text-sm">{{ t('completedOrders') }}</text>
						</view>
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ monthOrderStats.close_count }}</text>
							<text class="text-white text-sm">{{ t('cancelled') }}</text>
						</view>
						<view class="flex-1 text-center">
							<text 
								class="text-white text-2xl font-bold block mb-1">{{ monthOrderStats.refund_count }}</text>
							<text class="text-white text-sm">{{ t('refund') }}</text>
						</view>
					</template>
				</view>
			</view>
		</view>

		<!-- 订单明细 -->
		<view class="mt-2">
			<text 
				class="text-base px-4 py-2 block border-b border-gray-100 font-bold">{{ t('orderDetails') }}</text>

			<!-- 订单状态筛选 - 固定显示已完成、退款/售后、已关闭 -->
			<view class="flex mx-[24rpx] mt-[10rpx] border-b border-gray-100">
				<view class="bg-[#fff] rounded-[5rpx] p-[4rpx]">
					<text class="filter-btn" 
						:class="currentStatus === 'finish' ? 'filter-btn-active' : 'filter-btn-inactive'" 
						@click="changeStatus('finish')">
						{{ t('completed') }}
					</text>
					<text class="filter-btn" 
						:class="currentStatus === 'refund' ? 'filter-btn-active' : 'filter-btn-inactive'" 
						@click="changeStatus('refund')">
						{{ t('refund') }}
					</text>
					<text class="filter-btn" 
						:class="currentStatus === 'close' ? 'filter-btn-active' : 'filter-btn-inactive'" 
						@click="changeStatus('close')">
						{{ t('cancelled') }}
					</text>
				</view>
			</view>

			<!-- 订单列表 -->
			<mescroll-body ref="mescrollRef" :down="{ use: false }" @init="mescrollInit" @up="getListFn">
				<view v-if="!loading && filteredOrderList.length > 0">
					<view class="order-list">
						<view v-for="(item, index) in filteredOrderList" :key="index" @click="orderDetail(item)"
							class="px-4 py-[24rpx] border-b bg-[#fff] m-[24rpx] rounded-lg">
							<view class="flex justify-between items-center mb-2">
								<text class="text-[28rpx] text-[#999]">{{ item.create_time }}</text>
								<text class="text-[28rpx]" 
									:class="getOrderStatusClass(item.order_status)">{{ item.status_name }}</text>
							</view>
							<view class="flex justify-between items-center my-[24rpx]">
								<text class="text-[30rpx] font-bold">{{ item.order_name }}</text>
								<text class="iconfont iconarrow-right text-[24rpx]"></text>
							</view>
							<text class="text-[26rpx] mb-[10rpx] text-[#666] block">{{ item.taker_full_address }}</text>
							<text 
								class="text-[26rpx] text-[#999]">{{ t('orderNumber') }}{{ item.order_no }}</text>
						</view>
					</view>
				</view>
				<!-- 使用mescroll-empty组件替代原有的暂无数据提示 -->
				<mescroll-empty v-if="!loading && !filteredOrderList.length"
					:option="{tip: t('noBillData'), btnText: t('refresh')}"
					@emptyclick="refreshData"></mescroll-empty>
				<!-- 底部空间 -->
				<view class="h-[60rpx]"></view>
			</mescroll-body>
		</view>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { getDayOrderStat, getMonthOrderStat, getMonthorderPage } from '@/addon/home_service/store/api/account';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { img, redirect, getToken } from '@/utils/common'
	// 类型定义
	interface OrderStats {
		wait_service_count : number
		wait_check_count : number
		finish_count : number
		close_count ?: number
		refund_count ?: number
		[propName : string] : any
	}

	interface OrderItem {
		id : number
		order_no : string
		order_name : string
		create_time : string
		status_name : string
		order_status : string
		taker_full_address : string
		[propName : string] : any
	}

	interface MescrollStructure {
		num : number
		size : number
		endSuccess : (length : number) => void
		endErr : () => void
		resetUpScroll : () => void
	}

	// 页面加载动画
	const loading = ref<boolean>(true)
	// 列表加载动画
	const listLoading = ref<boolean>(false)

	// 标签页状态
	const isTodayView = ref(true)

	// 订单状态筛选 - 默认为已完成
	const currentStatus = ref('finish')

	// 月份选择器状态
	const showMonthSelector = ref(false)
	const selectedYear = ref(new Date().getFullYear())
	const selectedMonth = ref(new Date().getMonth() + 1)
	const currentYear = ref(new Date().getFullYear())

	// 今日订单统计数据
	const todayOrderStats = ref<OrderStats>({})

	// 月订单统计数据
	const monthOrderStats = ref<OrderStats>({})

	// 订单列表数据
	const filteredOrderList = ref<OrderItem[]>([])

	// Mescroll相关
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)

	/**
	 * 切换订单状态筛选
	 * @param status 订单状态
	 */
	const changeStatus = (status : string) : void => {
		currentStatus.value = status
		getMescroll().resetUpScroll()
	}

	/**
	 * 获取今日订单统计数据
	 */
	const getDayOrderStatFn = (): void => {
		getDayOrderStat().then((res : any) => {
			todayOrderStats.value = res.data
		}).catch(() => {
			console.error('获取今日订单统计数据失败')
		})
	}

	/**
	 * 获取月度订单统计数据
	 */
	const getMonthOrderStatFn = (): void => {
		let params = {
			date: selectedYear.value + '-' + (selectedMonth.value < 10 ? '0' + selectedMonth.value : selectedMonth.value)
		}
		getMonthOrderStat(params).then((res : any) => {
			monthOrderStats.value = res.data
		}).catch(() => {
			console.error('获取月度订单统计数据失败')
		})
	}

	/**
	 * 获取订单列表数据
	 * @param mescroll Mescroll实例
	 */
	const getListFn = (mescroll : MescrollStructure): void => {
		loading.value = true
		listLoading.value = false
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			date_type: isTodayView.value ? 'day' : 'month',
			date: selectedYear.value + '-' + (selectedMonth.value < 10 ? '0' + selectedMonth.value : selectedMonth.value),
			status: currentStatus.value
		}
		getMonthorderPage(data).then((res : any) => {
			let newArr = res.data.data as OrderItem[]
			//设置列表数据
			if (mescroll.num == 1) {
				filteredOrderList.value = [] //如果是第一页需手动制空列表
			}
			filteredOrderList.value = filteredOrderList.value.concat(newArr)
			loading.value = false
			mescroll.endSuccess(newArr.length)
			if (!filteredOrderList.value.length) listLoading.value = true
		}).catch(() => {
			loading.value = false
			listLoading.value = true
			mescroll.endErr() // 请求失败, 结束加载
		})
	}

	/**
	 * 切换到今日订单
	 */
	const switchToToday = (): void => {
		isTodayView.value = true
		getMonthOrderStatFn()
		getMescroll().resetUpScroll()
	}

	/**
	 * 切换到月订单
	 */
	const switchToMonth = (): void => {
		isTodayView.value = false
		getMonthOrderStatFn()
		getMescroll().resetUpScroll()
	}

	/**
	 * 根据订单状态获取样式类
	 * @param status 订单状态
	 * @returns 样式类名
	 */
	const getOrderStatusClass = (status : string): string => {
		switch (status) {
			case 'finish':
				return 'text-[#000000] text-sm'
			case 'refund':
				return '!text-[#ff0000] text-sm'
			case 'close':
				return 'text-red-500 text-sm'
			default:
				return 'text-gray-500 text-sm'
		}
	}

	/**
	 * 显示/隐藏月份选择器
	 */
	const toggleMonthSelector = (): void => {
		showMonthSelector.value = !showMonthSelector.value
	}

	/**
	 * 切换到上一年
	 */
	const prevYear = (): void => {
		currentYear.value--
	}

	/**
	 * 切换到下一年
	 */
	const nextYear = (): void => {
		currentYear.value++
	}

	/**
	 * 选择月份
	 * @param month 月份
	 */
	const selectMonth = (month : number): void => {
		selectedYear.value = currentYear.value
		selectedMonth.value = month
		showMonthSelector.value = false
		getMonthOrderStatFn()
		getMescroll().resetUpScroll()
	}

	/**
	 * 刷新数据
	 */
	const refreshData = (): void => {
		getDayOrderStatFn()
		getMonthOrderStatFn()
		getMescroll().resetUpScroll()
	}

	// 页面加载时获取数据
	onLoad(() => {
		getDayOrderStatFn()
		getMonthOrderStatFn()
	})
	const orderDetail = (item : OrderItem): void => {
		 redirect({url:'/addon/home_service/store/pages/order/detail',param:{order_id:item.order_id}})
	}
 </script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	page {
		background-color: var(--page-bg-color);
	}

	.order-list .mescroll-body {
		padding-bottom: constant(safe-area-inset-bottom) !important;
		padding-bottom: env(safe-area-inset-bottom) !important;
	}

	/* 添加一些动画效果，提升用户体验 */
	.fade-enter-active,
	.fade-leave-active {
		transition: opacity 0.3s;
	}

	.fade-enter-from,
	.fade-leave-to {
		opacity: 0;
	}

	/* 统计卡片样式 */
	.stats-card-container {
		background: var(--store-bg-one);
	}

	.stats-card {
		background: var(--store-bg-one);
		/* 确保卡片内部背景与容器一致 */
	}

	/* 筛选按钮样式优化 */
	.filter-btn {
		padding: 8rpx 24rpx;
		font-size: 26rpx;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		line-height: 1.4;
		font-weight: 500;
	}

	/* 选中状态：蓝色背景，白色文字 */
	.filter-btn-active {
		background-color: var(--store-bg-one);
		color: white;
		border-radius: 5rpx;
		box-shadow: 0 2rpx 8rpx rgba(22, 116, 255, 0.25);
	}

	/* 未选中状态：白色背景，黑色文字，灰色边框 */
	.filter-btn-inactive {
		color: var(--text-color);
	}

	/* 按钮悬停效果 */
	.filter-btn-inactive:active {
		background-color: #f5f5f5;
	}

	/* 标签页样式优化 */
	.tab-item {
		position: relative;
		padding: 8rpx 0;
		margin-right: 40rpx;
	}

	/* 移除文字颜色设置，只保留蓝色下划线效果 */
	.tab-item-active::after {
		content: '';
		position: absolute;
		bottom: 0;
		left: 50%;
		transform: translateX(-50%);
		width: 50rpx;
		height: 6rpx;
		background-color: var(--primary-color);
		border-radius: 3rpx;
	}
</style>