<template>
	<div class="main-container min-h-screen">
		<!-- 顶部统计时间选择 -->
		<div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px]">
			<el-row :gutter="20" class="items-center">
				<el-col class="mb-[15px]">
					<div class="mb-[10px] font-bold">统计时间：</div>
					<el-radio-group v-model="dateType" size="large" @change="handleTimeRangeChange">
						<el-radio-button label="week">周报</el-radio-button>
						<el-radio-button label="month">月报</el-radio-button>
						<el-radio-button label="year">年报</el-radio-button>
						<el-radio-button label="custom">自定义</el-radio-button>
					</el-radio-group>
				</el-col>
				<el-col v-if="dateType === 'custom'" class="flex items-center">
					<el-date-picker v-model="customDateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"
						value-format="YYYY-MM-DD" format="YYYY-MM-DD" style="width: 360px; margin-right: 10px;" />
					<el-button type="primary" @click="handleCustomDateConfirm">确定</el-button>
				</el-col>
			</el-row>
		</div>

		<!-- 收入趋势分析 -->
		<div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px]">
			<div class="flex justify-between items-center mb-[20px]">
				<h2 class="text-xl font-bold">收入趋势分析</h2>
			</div>
			<div class="income-trend-chart" ref="incomeTrendChartRef" style="height: 400px;"></div>
		</div>

		<!-- 三个折线图区域 -->
		<el-row :gutter="20" class="mb-[20px]">
			<!-- 门店收益 -->
			<el-col :span="8">
				<div class="bg-white p-[20px] rounded-lg shadow-sm h-full">
					<div class="flex justify-between items-center mb-[20px]">
						<h3 class="font-bold">门店收益</h3>
					</div>
					<div class="store-income-chart" ref="storeIncomeChartRef" style="height: 300px;"></div>
				</div>
			</el-col>

			<!-- 师傅收益 -->
			<el-col :span="8">
				<div class="bg-white p-[20px] rounded-lg shadow-sm h-full">
					<div class="flex justify-between items-center mb-[20px]">
						<h3 class="font-bold">师傅收益</h3>
					</div>
					<div class="technician-income-chart" ref="technicianIncomeChartRef" style="height: 300px;"></div>
				</div>
			</el-col>

			<!-- 售后支出 -->
			<el-col :span="8">
				<div class="bg-white p-[20px] rounded-lg shadow-sm h-full">
					<div class="flex justify-between items-center mb-[20px]">
						<h3 class="font-bold">售后支出</h3>
					</div>
					<div class="refund-expenditure-chart" ref="refundExpenditureChartRef" style="height: 300px;"></div>
				</div>
			</el-col>
		</el-row>

		<!-- 收支盈利分析 -->
		<div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px]">
			<div class="flex justify-between items-center mb-[20px]">
				<h2 class="text-xl font-bold">收支盈利分析</h2>
			</div>
			<el-table :data="financialAnalysisList" size="large" v-loading="loading">
				<template #empty>
					<span>{{ !loading ? t("emptyData") : "" }}</span>
				</template>
				<el-table-column prop="date" :label="'时间'" min-width="120" align="center" />
				<el-table-column prop="order_count" :label="'订单数'" width="120" align="center" />
				<el-table-column prop="income_amount" :label="'收入金额'" width="150" align="center">
					<template #default="{ row }">
						<span class="text-green-500">{{ formatNumber(row.income_amount || 0) }}</span>
					</template>
				</el-table-column>
				<el-table-column prop="refund_amount" :label="'售后金额'" width="150" align="center">
					<template #default="{ row }">
						<span class="text-red-500">{{ formatNumber(row.refund_amount || 0) }}</span>
					</template>
				</el-table-column>
				<el-table-column prop="withdraw_amount" :label="'提现金额'" width="150" align="center">
					<template #default="{ row }">
						<span class="text-orange-500">{{ formatNumber(row.withdraw_amount || 0) }}</span>
					</template>
				</el-table-column>
				<el-table-column prop="profit" :label="'利润'" width="120" align="center">
					<template #default="{ row }">
						<span :class="{ 'text-green-500': row.profit > 0, 'text-red-500': row.profit < 0 }">{{ formatNumber(row.profit || 0) }}</span>
					</template>
				</el-table-column>
				<el-table-column prop="profit_calculation" :label="'利润计算'" min-width="200" align="center">
					<template #default="{ row }">
						<span>{{ formatNumber(row.income_amount || 0) }} - {{ formatNumber(row.refund_amount || 0) }} - {{ formatNumber(row.withdraw_amount || 0) }} = {{ formatNumber(row.profit || 0) }}</span>
					</template>
				</el-table-column>
			</el-table>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref, onMounted, nextTick } from 'vue'
	import { t } from '@/lang'
	import { img } from '@/utils/common'
	import {
		getIncomeTrend,
		getStoreIncomeTrend,
		getTechnicianIncomeTrend,
		getRefundExpenditure,
		getReceiptExpenditure
	} from '@/addon/home_service/api/report'
	import * as echarts from 'echarts'
	/**
	 * 格式化数字，添加千分位分隔符
	 */
	const formatNumber = (num: number | string): string => {
		if (typeof num === 'string') num = parseFloat(num)
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
	}
	
	// 响应式数据
	const dateType = ref('month')
	const customDateRange = ref([])
	const loading = ref(false)

	// 图表引用
	const incomeTrendChartRef = ref<HTMLElement>()
	const storeIncomeChartRef = ref<HTMLElement>()
	const technicianIncomeChartRef = ref<HTMLElement>()
	const refundExpenditureChartRef = ref<HTMLElement>()

	// 图表实例
	let incomeTrendChart: echarts.ECharts
	let storeIncomeChart: echarts.ECharts
	let technicianIncomeChart: echarts.ECharts
	let refundExpenditureChart: echarts.ECharts

	// 收支盈利分析数据
	const financialAnalysisList = ref<any[]>([])

	// 方法
	const handleTimeRangeChange = () => {
		// 根据时间范围重新加载数据
		if (dateType.value !== 'custom') {
			loadAllCharts()
			loadFinancialAnalysis()
		}
	}

	const handleCustomDateConfirm = () => {
		// 点击确定按钮后加载数据
		if (customDateRange.value && customDateRange.value.length === 2) {
			loadAllCharts()
			loadFinancialAnalysis()
		} else {
			// 提示用户选择日期范围
			uni.showToast({
				title: '请选择日期范围',
				icon: 'none'
			})
		}
	}

	/**
	 * 准备请求参数
	 */
	const prepareParams = () => {
		const params: Record<string, any> = {
			date_type: dateType.value
		}

		// 自定义模式下传递时间范围
		if (dateType.value === 'custom' && customDateRange.value && customDateRange.value.length === 2) {
			params.date = [
				(customDateRange.value[0]),
				(customDateRange.value[1])
			]
		}

		return params
	}

	/**
	 * 加载所有图表数据
	 */
	const loadAllCharts = async () => {
		const params = prepareParams()
		
		// 收入趋势
		await loadIncomeTrend(params)
		// 门店收益
		await loadStoreIncomeTrend(params)
		// 师傅收益
		await loadTechnicianIncomeTrend(params)
		// 售后支出
		await loadRefundExpenditureData(params)
	}

	/**
	 * 加载收入趋势数据
	 */
	const loadIncomeTrend = async (params: Record<string, any>) => {
		try {
			const res = await getIncomeTrend(params)
			if (res.data && incomeTrendChart) {
				incomeTrendChart.setOption({
					xAxis: {
						type: 'category',
						data: res.data.xAxis || []
					},
					yAxis: {
						type: 'value',
						axisLabel: {
							formatter: '￥{value}'
						}
					},
					series: [{
						data: res.data.series || [],
						type: 'line',
						smooth: true,
						lineStyle: {
							width: 3,
							color: '#1890ff'
						},
						itemStyle: {
							color: '#1890ff'
						},
						areaStyle: {
							color: {
								type: 'linear',
								x: 0, y: 0, x2: 0, y2: 1,
								colorStops: [
									{offset: 0, color: 'rgba(24, 144, 255, 0.3)'},
									{offset: 1, color: 'rgba(24, 144, 255, 0.1)'}
								]
							}
						}
					}]
				})
			}
		} catch (error) {
			console.error('加载收入趋势数据失败:', error)
		}
	}

	/**
	 * 加载门店收益数据
	 */
	const loadStoreIncomeTrend = async (params: Record<string, any>) => {
		try {
			const res = await getStoreIncomeTrend(params)
			if (res.data && storeIncomeChart) {
				storeIncomeChart.setOption({
					xAxis: {
						type: 'category',
						data: res.data.xAxis || []
					},
					yAxis: {
						type: 'value',
						axisLabel: {
							formatter: '￥{value}'
						}
					},
					series: [{
						data: res.data.series || [],
						type: 'line',
						smooth: true,
						lineStyle: {
							width: 3,
							color: '#52c41a'
						},
						itemStyle: {
							color: '#52c41a'
						},
						areaStyle: {
							color: {
								type: 'linear',
								x: 0, y: 0, x2: 0, y2: 1,
								colorStops: [
									{offset: 0, color: 'rgba(82, 196, 26, 0.3)'},
									{offset: 1, color: 'rgba(82, 196, 26, 0.1)'}
								]
							}
						}
					}]
				})
			}
		} catch (error) {
			console.error('加载门店收益数据失败:', error)
		}
	}

	/**
	 * 加载师傅收益数据
	 */
	const loadTechnicianIncomeTrend = async (params: Record<string, any>) => {
		try {
			const res = await getTechnicianIncomeTrend(params)
			if (res.data && technicianIncomeChart) {
				technicianIncomeChart.setOption({
					xAxis: {
						type: 'category',
						data: res.data.xAxis || []
					},
					yAxis: {
						type: 'value',
						axisLabel: {
							formatter: '￥{value}'
						}
					},
					series: [{
						data: res.data.series || [],
						type: 'line',
						smooth: true,
						lineStyle: {
							width: 3,
							color: '#fa8c16'
						},
						itemStyle: {
							color: '#fa8c16'
						},
						areaStyle: {
							color: {
								type: 'linear',
								x: 0, y: 0, x2: 0, y2: 1,
								colorStops: [
									{offset: 0, color: 'rgba(250, 140, 22, 0.3)'},
									{offset: 1, color: 'rgba(250, 140, 22, 0.1)'}
								]
							}
						}
					}]
				})
			}
		} catch (error) {
			console.error('加载师傅收益数据失败:', error)
		}
	}

	/**
	 * 加载售后支出数据
	 */
	const loadRefundExpenditureData = async (params: Record<string, any>) => {
		try {
			const res = await getRefundExpenditure(params)
			if (res.data && refundExpenditureChart) {
				refundExpenditureChart.setOption({
					xAxis: {
						type: 'category',
						data: res.data.xAxis || []
					},
					yAxis: {
						type: 'value',
						axisLabel: {
							formatter: '￥{value}'
						}
					},
					series: [{
						data: res.data.series || [],
						type: 'line',
						smooth: true,
						lineStyle: {
							width: 3,
							color: '#f5222d'
						},
						itemStyle: {
							color: '#f5222d'
						},
						areaStyle: {
							color: {
								type: 'linear',
								x: 0, y: 0, x2: 0, y2: 1,
								colorStops: [
									{offset: 0, color: 'rgba(245, 34, 45, 0.3)'},
									{offset: 1, color: 'rgba(245, 34, 45, 0.1)'}
								]
							}
						}
					}]
				})
			}
		} catch (error) {
			console.error('加载售后支出数据失败:', error)
		}
	}

	/**
	 * 加载收支盈利分析数据
	 */
	const loadFinancialAnalysis = async () => {
		loading.value = true
		const params = prepareParams()
		
		try {
			const res = await getReceiptExpenditure(params)
			if (res.data && Array.isArray(res.data)) {
				// 使用接口返回的实际数据，并进行字段映射和利润计算
				financialAnalysisList.value = res.data.map(item => {
					const income_amount = parseFloat(item.total_income || '0')
					const refund_amount = parseFloat(item.refund_money || '0')
					const withdraw_amount = parseFloat(item.total_commission || '0')
					const profit = income_amount - refund_amount - withdraw_amount
					
					return {
						date: item.date,
						order_count: item.order_count || 0,
						income_amount,
						refund_amount,
						withdraw_amount,
						profit,
						// 利润计算表达式将由表格模板自动生成，无需在此处设置
					}
				})
			} else {
				financialAnalysisList.value = []
			}
		} catch (error) {
			console.error('加载收支盈利分析数据失败:', error)
			financialAnalysisList.value = []
		} finally {
			loading.value = false
		}
	}

	/**
	 * 初始化图表
	 */
	const initCharts = () => {
		if (incomeTrendChartRef.value) {
			incomeTrendChart = echarts.init(incomeTrendChartRef.value)
		}
		if (storeIncomeChartRef.value) {
			storeIncomeChart = echarts.init(storeIncomeChartRef.value)
		}
		if (technicianIncomeChartRef.value) {
			technicianIncomeChart = echarts.init(technicianIncomeChartRef.value)
		}
		if (refundExpenditureChartRef.value) {
			refundExpenditureChart = echarts.init(refundExpenditureChartRef.value)
		}

		// 设置图表响应式
		window.addEventListener('resize', () => {
			incomeTrendChart?.resize()
			storeIncomeChart?.resize()
			technicianIncomeChart?.resize()
			refundExpenditureChart?.resize()
		})
	}

	// 生命周期
	onMounted(async () => {
		await nextTick()
		// 初始化图表
		initCharts()
		// 加载数据
		loadAllCharts()
		loadFinancialAnalysis()
	})
</script>

<style scoped>
	.main-container {
		padding: 20px;
		background-color: #f5f7fa;
	}

	/* 图表容器样式 */
	.income-trend-chart,
	.store-income-chart,
	.technician-income-chart,
	.refund-expenditure-chart {
		width: 100%;
	}

	/* 表格样式 */
	:deep(.el-table__row:hover) {
		background-color: #f5f7fa;
	}
</style>