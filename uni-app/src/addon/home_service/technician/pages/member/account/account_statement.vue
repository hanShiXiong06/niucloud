<template>
	<view class="overflow-hidden component-class bg-[#f6f6f6]" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<view class="fixed w-full z-index-99">
			<!-- 标签切换 -->
			<view class=" flex bg-[#F9F9F9] border-b border-[#EEEEEE] bg-white">
				<view class="flex-1 py-[20rpx] text-center"
					@click="switchTab('detail')">
					<text class="text-[32rpx]">{{ t('accountDetails') }}</text>
					<view v-if="currentTab == 'detail'"
						class="w-[40rpx] h-[6rpx] bg-[var(--technician-bg-one)] rounded-full mx-auto mt-[12rpx]"></view>
				</view>
				<view class="flex-1 py-[20rpx] text-center"
					@click="switchTab('statistics')">
					<text class="text-[32rpx]">{{ t('receiptStatistics') }}</text>
					<view v-if="currentTab == 'statistics'"
						class="w-[40rpx] h-[6rpx] bg-[var(--technician-bg-one)] rounded-full mx-auto mt-[12rpx]"></view>
				</view>
			</view>
			<!-- 筛选条件区域 - 仅在账户明细tab显示 -->
			<view v-if="currentTab == 'detail'"
				class="filter-bar bg-[#F9F9F9] px-[24rpx] py-[20rpx] flex justify-between items-center border-b border-[#EEEEEE]">
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
			<!-- 日汇总/月汇总切换 - 仅在收支统计tab显示 -->
			<view v-else class="bg-[#F9F9F9] px-[24rpx] py-[20rpx] border-b border-[#EEEEEE]">
				<view class="flex justify-start">
					<view class="flex">
						<view
							class="relative text-base py-2 pt-0 px-4 transition-all duration-200 flex items-center justify-center text-[28rpx]"
							@click="switchToDay">
							<text>日汇总</text>
							<view v-if="isDayView"
								class="absolute bottom-0 left-1/4 right-1/4 h-1 bg-[var(--technician-bg-one)] rounded-full"></view>
						</view>
						<view
							class="relative text-base py-2 pt-0 px-4 transition-all duration-200 flex items-center justify-center text-[28rpx]"
							@click="switchToMonth">
							<text>月汇总</text>
							<view v-if="!isDayView"
								class="absolute bottom-0 left-1/4 right-1/4 h-1 bg-[var(--technician-bg-one)] rounded-full"></view>
						</view>
					</view>
				</view>
			</view>
		</view>
		
		<mescroll-body ref="mescrollRef" top="170rpx" @init="mescrollInit" :down="{ use: false }" @up="getBillList" v-if="currentTab == 'detail'" >
			<!-- 账户明细内容 -->
			<view >
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
		</mescroll-body>
		<view v-if="currentTab != 'detail'" class="bg-[var(--page-bg-color)] overflow-hidden px-[25rpx] mt-[170rpx]">
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
					<qiun-data-charts type="column" :opts="opts" :chartData="chartData" :reshow="reshowChart" v-show="chartVisible" style="z-index: 1;" />
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
	import qiunDataCharts from '@/addon/home_service/technician/components/qiun-data-charts/components/qiun-data-charts/qiun-data-charts.vue'
	import { getAccountTypeList, getAccountStatusList, getTechnicianAccountList, getTechnicianincomeAndExpenseStatChartt, getTechnicIanincomeAndExpenseStat } from '@/addon/home_service/technician/api/account';
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '账户明细', topStatusBar: { textColor: '#333'} })
	const pageLoading = ref(true)
	const isDayView = ref(true)
	const chartData = ref({})
	const todayData = ref<any>({})
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	const mescrollRef = ref(null);
	const typeColumns = ref<any[]>([[]])
	const paymentColumns = ref<any[]>([[]])
	const loading = ref(false);
	const currentTab = ref('detail')
	const timeValue = ref(Date.now())
	const timeValueName = ref('')
	const typeValue = ref('')
	const typeValueName = ref('')
	const paymentValue = ref()
	const paymentValueName = ref('')
	const showTypeFilter = ref(false)
	const showDateFilter = ref(false)
	const showStatusFilter = ref(false)
	const typeFilterText = ref('全部类型')
	const dateFilterText = ref('全部日期')
	const statusFilterText = ref('到账情况')
	const billList = ref<any[]>([])
	const chartVisible  = ref(true)
	const reshowChart = ref(false)
	const confrimType = (e : any) => {
		typeValue.value = e.value[0].value
		typeValueName.value = e.value[0].label
		getMescroll().resetUpScroll();
		showTypeFilter.value = false
	}

	const confrimPayment = (e : any) => {
		paymentValue.value = e.value[0].value
		paymentValueName.value = e.value[0].label
		getMescroll().resetUpScroll();
		showStatusFilter.value = false
	}

	const confrimTime = (e : any) => {
		timeValue.value = e.value / 1000
		timeValueName.value = timeStampTurnTime(timeValue.value, 'Y-m').split('-')[0] + '-' + timeStampTurnTime(timeValue.value, 'Y-m').split('-')[1] 
		showDateFilter.value = false
		getMescroll().resetUpScroll();
	}

	const groupedBillList = computed(() => {
		const groups : any[] = []
		const dateMap : Record<string, any> = {}
		billList.value.forEach(item => {
			const date = item.create_time.split(' ')[0]
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
			const amount = parseFloat(item.account_data)
			if (amount > 0) {
				const currentIncome = parseFloat(dateMap[date].totalIncome)
				dateMap[date].totalIncome = (currentIncome + amount).toFixed(2)
			} else {
				const currentExpense = parseFloat(dateMap[date].totalExpense)
				dateMap[date].totalExpense = (currentExpense + Math.abs(amount)).toFixed(2)
			}
		})
		groups.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
		return groups
	})

	const closeFilter = () => {
		showTypeFilter.value = false
		showDateFilter.value = false
		showStatusFilter.value = false
	}

	const getBillList = (mescroll : any) => {
		loading.value = true;
		const params: any = {
			page: mescroll.num,
			limit: mescroll.size
		}
		if (typeValue.value) {
			params.from_type = typeValue.value
		}
		if (timeValueName.value) {
			params.date = timeValueName.value.split('-').slice(0, 2).join('-')
		}
		if (paymentValue.value) {
			params.status = paymentValue.value
		}
		pageLoading.value = true
		getTechnicianAccountList(params).then((res : any) => {
			loading.value = false;
			pageLoading.value = false
			if (res.code == 1) {
				const newData = res.data || []
				if (mescroll.num == 1) {
					billList.value = [];
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

	const refreshData = () => {
		getMescroll()?.resetUpScroll();
	}

	const themeColor = () => {
		return {}
	}

	const switchTab = (tab: string) => {
		currentTab.value = tab
	}

	const opts = ref({
		color: ["#1890FF", "#91CB74"],
		padding: [15, 15, 30, 5],
		enableScroll: true,
		legend: { show: false },
		animation: false,
		canvas2d: true,
		xAxis: {
			itemCount: 8,
			gridColor: "#CCCCCC",
			gridType: "solid",
			dashLength: 4,
			scrollShow: false,
			scrollAlign: "left",
			scrollColor: "#A6A6A6",
			scrollBackgroundColor: "#EFEBEF",
			fontSize: 12,
			rotateLabel: false
		},
		yAxis: {
			disableGrid: false,
			gridType: "dash",
			gridColor: '#f6f6f6',
			dashLength: 2,
			splitNumber: 5,
			axisLineStyle: { color: 'transparent', width: 0 }
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
	})

	const updateChartOpts = () => {
		const o = opts.value
		o.xAxis.itemCount = isDayView.value ? 8 : 6 // 月汇总仅显示 6 项，更多项滚动
		o.xAxis.rotateLabel = false // 不旋转标签，使用滚动避免重叠
		o.xAxis.scrollShow = !isDayView.value // 月汇总开启滚动
		o.xAxis.fontSize = 12 // 统一字号，避免切换时抖动
	}

	let _chartDataFetchTimer : any = null

	const switchToDay = () => {
		isDayView.value = true
		chartVisible.value = false
		if (_chartDataFetchTimer) clearTimeout(_chartDataFetchTimer)
		_chartDataFetchTimer = setTimeout(() => {
			getServerData(true)
		}, 0)
	}

	const switchToMonth = () => {
		isDayView.value = false
		chartVisible.value = false
		if (_chartDataFetchTimer) clearTimeout(_chartDataFetchTimer)
		_chartDataFetchTimer = setTimeout(() => {
			getServerData(true)
		}, 0)
	}

	const getServerData = (silent = false) => {
		let params = { date_type: isDayView.value ? 'day' : 'month' }
		if (!silent) pageLoading.value = true
		getTechnicianincomeAndExpenseStatChartt(params).then((res: any) => {
			if (!silent) pageLoading.value = false
			if (!res.data || !res.data.xAxis || !res.data.series || !res.data.series.income || !res.data.series.expense) {
				chartVisible.value = true
				reshowChart.value = true
				setTimeout(() => { reshowChart.value = false }, 0)
				return
			}
			// 更新坐标配置在数据就绪后，避免旧数据+新坐标的中间态
			updateChartOpts()
			let formattedData = {
				categories: res.data.xAxis || [],
				series: [
					{
						name: t('income'),
						data: res.data.series.income.map((item: any) => {
							if (item === null || item === undefined || isNaN(Number(item))) return null
							return Number(item) == 0 ? null : Number(item)
						}),
						color: '#1890FF',
						label: { show: isDayView.value, position: 'top', fontSize: 10 }
					},
					{
						name: t('expense'),
						data: res.data.series.expense.map((item: any) => {
							if (item === null || item === undefined || isNaN(Number(item))) return null
							return Number(item) == 0 ? null : Number(item)
						}),
						color: '#91CB74',
						label: { show: isDayView.value, position: 'top', fontSize: 10 }
					}
				]
			}
			opts.value.update = true
			chartData.value = { ...formattedData };
			// 数据与配置同步到位后再显示，避免旧图闪现，并触发重绘
			setTimeout(() => {
				chartVisible.value = true
				reshowChart.value = true
				setTimeout(() => { reshowChart.value = false }, 0)
			}, 30)
		}).catch(() => {
			if (!silent) pageLoading.value = false
			chartVisible.value = true
			reshowChart.value = true
			setTimeout(() => { reshowChart.value = false }, 0)
		})
	}

	const getTechnicIanincomeAndExpenseStatFn = () => {
		getTechnicIanincomeAndExpenseStat({}).then((res: any) => {
			todayData.value = res.data
			pageLoading.value = false
		}).catch(() => {
			pageLoading.value = false
		})
	}

	onMounted(() => {
		updateChartOpts()
		getServerData();
		getTechnicIanincomeAndExpenseStatFn()
		getAccountTypeList().then((res : any) => {
			if (res.code == 1 && res.data) {
				const typeOptions = Object.entries(res.data).map(([key, value]) => ({
					label: value,
					value: key
				}))
				typeColumns.value = [typeOptions]
			}
		})
		getAccountStatusList().then((res : any) => {
			if (res.code == 1 && res.data) {
				const statusOptions = res.data.map((status : string, index : number) => ({
					label: status,
					value: index.toString()
				}))
				paymentColumns.value = [statusOptions]
			}
		})
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
	page{
		background:#f6f6f6 !important;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>