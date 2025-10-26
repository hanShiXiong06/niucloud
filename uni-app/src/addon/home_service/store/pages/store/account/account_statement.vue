<template>
	<view class="overflow-hidden component-class" :style="themeColor()">
		<view class="fixed w-full z-index-99">
			<!-- 标签切换 -->
			<view class=" flex border-b border-[#EEEEEE] bg-white">
				<view class="flex-1 py-[20rpx] text-center" :class="currentTab === 'detail' ? 'tab-active' : ''"
					@click="switchTab('detail')">
					<text class="text-[32rpx]">{{ t('accountDetails') }}</text>
					<view v-if="currentTab === 'detail'"
						class="w-[40rpx] h-[6rpx] bg-[var(--store-bg-one)] rounded-full mx-auto mt-[12rpx]"></view>
				</view>
				<view class="flex-1 py-[20rpx] text-center" :class="currentTab === 'statistics' ? 'tab-active' : ''"
					@click="switchTab('statistics')">
					<text class="text-[32rpx]">{{ t('receiptStatistics') }}</text>
					<view v-if="currentTab === 'statistics'"
						class="w-[40rpx] h-[6rpx] bg-[var(--store-bg-one)] rounded-full mx-auto mt-[12rpx]"></view>
				</view>
			</view>
			<!-- 筛选条件区域 - 仅在账户明细tab显示 -->
			<view v-if="currentTab == 'detail'"
				class="filter-bar bg-white px-[24rpx] py-[20rpx] flex justify-between items-center border-b border-[#EEEEEE]">
				<view class="filter-item flex items-center" @click="showTypeFilter = true">
					<text class="text-[28rpx] text-[#333]">{{ typeValueName ? typeValueName : typeFilterText }}</text>
					<image class="w-[35rpx] h-[35rpx] block"
						:src="img('addon/home_service/technician/member/account/account_statement/bottom-sanjiao.png')"
						mode="aspectFill" />
				</view>
				<view class="filter-item flex items-center" @click="showDateFilter = true">
					<text class="text-[28rpx] text-[#333]">{{ timeValueName ? timeValueName : dateFilterText }}</text>
					<image class="w-[35rpx] h-[35rpx] block"
						:src="img('addon/home_service/technician/member/account/account_statement/bottom-sanjiao.png')"
						mode="aspectFill" />
				</view>
				<view class="filter-item flex items-center" @click="showStatusFilter = true">
					<text
						class="text-[28rpx] text-[#333]">{{ paymentValueName ? paymentValueName :statusFilterText }}</text>
					<image class="w-[35rpx] h-[35rpx] block"
						:src="img('addon/home_service/technician/member/account/account_statement/bottom-sanjiao.png')"
						mode="aspectFill" />
				</view>
			</view>
			<view v-else>
					<view class="flex justify-start py-3">
					<view class="flex">
						<view
							class="relative text-base py-2 pt-0 px-4 transition-all duration-200 flex items-center justify-center text-[28rpx]"
							@click="switchToDay">
							<text>日汇总</text>
							<view v-if="isDayView"
								class="absolute bottom-0 left-1/4 right-1/4 h-1 bg-[var(--store-bg-one)] rounded-full"></view>
						</view>
						<view
							class="relative text-base py-2 pt-0 px-4 transition-all duration-200 flex items-center justify-center text-[28rpx]"
							@click="switchToMonth">
							<text>月汇总</text>
							<view v-if="!isDayView"
								class="absolute bottom-0 left-1/4 right-1/4 h-1 bg-[var(--store-bg-one)] rounded-full"></view>
						</view>
					</view>
				</view>
			</view>
		</view>
		
		<mescroll-body ref="mescrollRef" top="214rpx" @init="mescrollInit" :down="{ use: false }" @up="getBillList">
			<!-- 账户明细内容 -->
			<view v-if="currentTab == 'detail'">
				<!-- 账单列表 -->
				<view v-if="groupedBillList.length > 0" class="bill-list">
					<!-- 日期分组 -->
					<view v-for="(group, index) in groupedBillList" :key="index" >
						<!-- 日期标题 -->
						<view class="date-header px-[24rpx] py-[30rpx] bg-[#F9F9F9]">
							<view class="flex flex-col">
								<text class="text-[32rpx] font-bold  text-[#333]">{{ group.date }}</text>
								<view class="flex items-center mt-[10rpx]">
									<text class="text-[24rpx] text-[#999]">{{ t('income') }} ¥{{ group.totalIncome }}</text>
									<text class="text-[24rpx] text-[#999] ml-[20rpx]">{{ t('expense') }}
										¥{{ group.totalExpense }}</text>
								</view>
							</view>
						</view>

						<!-- 账单列表项 -->
						<view class="bill-items bg-white">
							<view v-for="(item, itemIndex) in group.items" :key="itemIndex" class="bill-item">
								<view class="px-[24rpx] py-[30rpx] border-bottom-style" >
									<view class="flex justify-between items-center mb-[10rpx]">
										<text class="text-[28rpx] font-500 text-[#333] truncate mr-[25rpx]">{{ item.memo }}</text>
										<text class="text-[28rpx] font-bold text-[#000]">
											{{ item.account_data }}
										</text>
									</view>
									<view class="flex justify-between items-center">
										<text class="text-[24rpx] text-[#999] mt-[10rpx]">{{ item.show_time }}</text>
										<text class="text-[24rpx] text-[#999]">{{ item.status_name }}</text>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- 使用mescroll-empty组件替代原有的暂无数据提示 -->
				<mescroll-empty v-else-if="!loading" :option="{tip: t('noBillData'), btnText: t('refresh')}"
					@emptyclick="refreshData"></mescroll-empty>

				<!-- 底部空间 -->
				<view class="h-[60rpx]"></view>
			</view>

			<!-- 收支统计内容 -->
			<view v-else class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden px-[25rpx]">
			

				<!-- 统计图表区域 -->
				<view class="bg-white mt-2 p-5 rounded-lg">
					<text class="text-xs text-gray-500 mb-2 block">单位：千元</text>

					<!-- 图表标题 -->
					<view class="flex justify-between items-center mb-2">
						<text class="text-sm font-medium"></text>
						<view class="flex items-center space-x-6">
							<view class="flex items-center">
								<view class="w-3 h-3 bg-[#1890FF] rounded-sm mr-1"></view>
								<text class="text-xs text-gray-500">收入</text>
							</view>
							<view class="flex items-center">
								<view class="w-3 h-3 bg-[#91CB74] rounded-sm mr-1"></view>
								<text class="text-xs text-gray-500">支出</text>
							</view>
						</view>
					</view>

					<!-- 使用uCharts柱状图 -->
					<view class="charts-box">
						<qiun-data-charts type="column" :opts="opts" :chartData="chartData" />
					</view>
				</view>

				<!-- 今日收入区域 -->
				<view class="bg-white mt-2 p-[25rpx] pt-[35rpx] rounded-lg">
					<text class="text-[30rpx] font-bold mb-4 block">今日收入</text>
					<text class="text-[40rpx] font-bold text-[var(--theme-color)] mb-4 block">+{{todayData.income || 0}}</text>
				</view>

				<!-- 今日支出区域 -->
				<view class="bg-white mt-2 p-[25rpx] pt-[35rpx] mb-5 rounded-lg">
					<text class="text-[30rpx] font-bold mb-4 block">今日支出</text>
					<text class="text-[40rpx] font-bold text-red-500 mb-4 block">{{todayData.expense || 0}}</text>
				</view>
			</view>
		</mescroll-body>

		<u-picker :show="showTypeFilter" :columns="typeColumns" keyName="label" @confirm="confrimType"
			@cancel="closeFilter"></u-picker>
		<!-- 时间选择器组件 - 确保只显示年月 -->
		<u-datetime-picker :show="showDateFilter" v-model="timeValue" mode="year-month" :fields="['year', 'month']"
			@confirm="confrimTime" @cancel="closeFilter">
		</u-datetime-picker>
		<u-picker :show="showStatusFilter" :columns="paymentColumns" keyName="label" @confirm="confrimPayment"
			@cancel="closeFilter"></u-picker>
	</view>
	<loading-page :loading="pageLoading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, computed, onMounted } from 'vue';
	import { t } from '@/locale';
	import { img, redirect, goback, timeStampTurnTime } from '@/utils/common';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app';
	import qiunDataCharts from '@/addon/home_service/store/components/qiun-data-charts/components/qiun-data-charts/qiun-data-charts.vue'
	// 使用绝对路径导入API
	import { getAccountTypeList, getAccountStatusList, storeAccountList, getIncomeAndExpenseStatChart, getIncomeAndExpenseStat } from '@/addon/home_service/store/api/account';
	
	const pageLoading = ref(true)
	
	// 收支统计相关
	const isDayView = ref(true)
	const chartData = ref({})
	const todayData = ref<any>({})
	// 初始化 mescroll
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	const mescrollRef = ref(null);
	const typeColumns = ref<any[]>([[
		// 将在onMounted中动态填充
	]]);
	const paymentColumns = ref<any[]>([[
		// 将在onMounted中动态填充
	]]);

	// 新增加载状态
	const loading = ref(false);

	// 标签页状态
	const currentTab = ref('detail')
	const timeValue = ref(Date.now())
	const timeValueName = ref('')
	const typeValue = ref('')
	const typeValueName = ref('')
	const confrimType = (e : any) => {
		typeValue.value = e.value[0].value
		typeValueName.value = e.value[0].label
		getMescroll().resetUpScroll();
		showTypeFilter.value = false
	}
	const paymentValue = ref()
	const paymentValueName = ref('')
	const confrimPayment = (e : any) => {
		paymentValue.value = e.value[0].value
		paymentValueName.value = e.value[0].label
		getMescroll().resetUpScroll();
		showStatusFilter.value = false
	}

	// 时间确认函数 - 已经使用'Y-M'格式处理时间
	const confrimTime = (e : any) => {
		timeValue.value = e.value / 1000
		timeValueName.value = timeStampTurnTime(timeValue.value, 'Y-m').split('-')[0] + '-' + timeStampTurnTime(timeValue.value, 'Y-m').split('-')[1] 
		console.log(timeValueName.value)
		showDateFilter.value = false
		getMescroll().resetUpScroll();
	}

	// 筛选状态
	const showTypeFilter = ref(false)
	const showDateFilter = ref(false)
	const showStatusFilter = ref(false)

	// 筛选文本
	const typeFilterText = ref(t('allTypes'))
	const dateFilterText = ref(t('allDates'))
	const statusFilterText = ref(t('paymentStatus'))

	// 账单数据
	const billList = ref<any[]>([])

	// 按日期分组的账单数据
	const groupedBillList = computed(() => {
		const groups : any[] = []
		const dateMap : Record<string, any> = {}

		// 遍历所有账单，按日期分组
		billList.value.forEach(item => {
			// 从create_time中提取日期部分
			const date = item.create_time.split(' ')[0] // 假设格式为 '2025-09-17 10:16:27'
			if (!dateMap[date]) {
				dateMap[date] = {
					date: date,
					items: [],
					totalIncome: '0.00',
					totalExpense: '0.00'
				}
				groups.push(dateMap[date])
			}

			dateMap[date].items.push(item)

			// 计算总收入和支出
			const amount = parseFloat(item.account_data)
			if (amount > 0) {
				const currentIncome = parseFloat(dateMap[date].totalIncome)
				dateMap[date].totalIncome = (currentIncome + amount).toFixed(2)
			} else {
				const currentExpense = parseFloat(dateMap[date].totalExpense)
				dateMap[date].totalExpense = (currentExpense + Math.abs(amount)).toFixed(2)
			}
		})

		// 按日期排序（最新的在前）
		groups.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())

		return groups
	})


	// 返回上一页
	const handleBack = () => {
		redirect({
			url: '/addon/home_service/store/pages/store/account/list'
		})
	}

	// 跳转到收支统计页面
	const navigateToIncomeExpenseSummary = () => {
		redirect({
			url: '/addon/home_service/store/pages/store/account/summary'
		})
	}

	// 关闭筛选弹窗
	const closeFilter = () => {
		showTypeFilter.value = false
		showDateFilter.value = false
		showStatusFilter.value = false
	}

	// 获取账单列表
	const getBillList = (mescroll : any) => {
		loading.value = true;
		const params: any = {
			page: mescroll.num,
			limit: mescroll.size
		}

		// 添加筛选条件
		if (typeValue.value) {
			params.from_type = typeValue.value
		}
		if (timeValueName.value) {
			params.date = timeValueName.value.split('-').slice(0, 2).join('-') // 格式化为 YYYY-MM
		}
		if (paymentValue.value) {
			params.status = paymentValue.value
		}
		pageLoading.value = true
		storeAccountList(params).then((res : any) => {
			loading.value = false;
			pageLoading.value = false
			if (res.code === 1) {
				const newData = res.data || []
				//设置列表数据
				if (mescroll.num == 1) {
					billList.value = []; //如果是第一页需手动制空列表
				}
				billList.value = billList.value.concat(newData)
				mescroll.endSuccess(newData.length)
			} else {
				mescroll.endErr()
			}
		}).catch(() => {
			loading.value = false;
			pageLoading.value = false
			mescroll.endErr()
		})
	}

	// 新增刷新数据函数
	const refreshData = () => {
		getMescroll()?.resetUpScroll();
	}

	// themeColor 函数
	const themeColor = () => {
		return {}
	}

	// Tab 切换函数
	const switchTab = (tab: string) => {
		currentTab.value = tab
	}

	// 图表配置
	const opts = computed(() => ({
		color: ["#1890FF", "#91CB74"],
		padding: [15, 15, 30, 5],
		enableScroll: true,
		legend: {
			show: false
		},
		xAxis: {
			itemCount: isDayView.value ? 8 : 12,
			gridColor: "#CCCCCC",
			gridType: "solid",
			dashLength: 4,
			scrollShow: !isDayView.value,
			scrollAlign: "left",
			scrollColor: "#A6A6A6",
			scrollBackgroundColor: "#EFEBEF",
			fontSize: 12,
			rotateLabel: isDayView.value
		},
		yAxis: {
			disableGrid: false,
			gridType: "dash",
			gridColor: '#f6f6f6',
			dashLength: 2,
			splitNumber: 5,
			axisLineStyle: {
				color: 'transparent',
				width: 0
			}
		},
		extra: {
			column: {
				type: "group",
				width: 15,
				activeBgColor: "#000000",
				activeBgOpacity: 0.08,
				barBorderCircle: true,
				minHeight: 5
			}
		}
	}))

	// 切换到日统计
	const switchToDay = () => {
		isDayView.value = true
		getServerData()
	}

	// 切换到月统计
	const switchToMonth = () => {
		isDayView.value = false
		getServerData()
	}

	// 获取图表数据
	const getServerData = () => {
		let params = {
			date_type: isDayView.value ? 'day' : 'month'
		}
		pageLoading.value = true
		getIncomeAndExpenseStatChart(params).then((res: any) => {
			pageLoading.value = false

			if (!res.data || !res.data.xAxis || !res.data.series ||
				!res.data.series.income || !res.data.series.expense) {
				console.error('数据格式错误', res.data)
				return
			}

			let formattedData = {
				categories: res.data.xAxis || [],
				series: [
					{
						name: t('income'),
						data: res.data.series.income.map((item: any) => {
							if (item === null || item === undefined || isNaN(Number(item))) {
								return null
							}
							return Number(item) == 0 ? null : Number(item)
						}),
						color: '#1890FF',
						label: {
							show: true,
							position: 'top',
							fontSize: 10
						}
					},
					{
						name: t('expense'),
						data: res.data.series.expense.map((item: any) => {
							if (item === null || item === undefined || isNaN(Number(item))) {
								return null
							}
							return Number(item) == 0 ? null : Number(item)
						}),
						color: '#91CB74',
						label: {
							show: true,
							position: 'top',
							fontSize: 10
						}
					}
				]
			}

			chartData.value = { ...formattedData };
		}).catch((err: any) => {
			pageLoading.value = false
			console.error('获取图表数据失败:', err);
		})
	}

	// 获取今日收支数据
	const getIncomeAndExpenseStatFn = () => {
		getIncomeAndExpenseStat({}).then((res: any) => {
			todayData.value = res.data
			pageLoading.value = false
		}).catch((err: any) => {
			pageLoading.value = false
		})
	}

	onMounted(() => {
		// 初始化收支统计数据
		getServerData();
		getIncomeAndExpenseStatFn()
		// 获取类型列表
		getAccountTypeList().then((res : any) => {
			if (res.code === 1 && res.data) {
				const typeOptions = Object.entries(res.data).map(([key, value]) => ({
					label: value,
					value: key
				}))
				typeColumns.value = [typeOptions]
			}
		})

		// 获取状态列表
		getAccountStatusList().then((res : any) => {
			if (res.code === 1 && res.data) {
				const statusOptions = res.data.map((status : string, index : number) => ({
					label: status,
					value: index.toString()
				}))
				paymentColumns.value = [statusOptions]
			}
		})

		// 获取当前月份
		const now = new Date()
		const year = now.getFullYear()
		const month = String(now.getMonth() + 1).padStart(2, '0')
		timeValueName.value = `${year}-${month}`
	})
		import { onLoad,onShow } from "@dcloudio/uni-app";
		onLoad((e) => {
			if(e.type ==2){
				currentTab.value = 'statistics'
			}
		})
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	.header-bar {
		background-color: #FFFFFF;
		position: relative;
		z-index: 10;
	}

	.tab-bar {
		background-color: #FFFFFF;
		position: relative;
		z-index: 10;
	}

	.filter-bar {
		position: relative;
		z-index: 5;
	}

	.filter-item {
		padding: 0 10rpx;
	}

	.filter-modal {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: rgba(0, 0, 0, 0.5);
		z-index: 1000;
	}

	.bill-list {
		background-color: #FFFFFF;
		margin-top: 10rpx;
	}

	.date-header {
		background-color: #F9F9F9;
	}

	.bill-items {
		background-color: #FFFFFF;
	}

	.bill-item {
		position: relative;
	}
	.border-bottom-style {
		border-bottom: 2rpx solid #f6f6f6;
	}

	/* 图表样式 */
	.charts-box {
		width: 100%;
		height: 400rpx;
		border-radius: 30rpx;
		background: #ffffff;
	}

	/* 自定义空数据刷新按钮样式 */
	// :deep(.mescroll-empty .btn) {
	// 	background-color: var(--store-bg-one) !important;
	// 	color: #ffffff !important;
	// 	border: none !important;
	// 	padding: 10rpx 40rpx;
	// 	border-radius: 50rpx;
	// }
</style>