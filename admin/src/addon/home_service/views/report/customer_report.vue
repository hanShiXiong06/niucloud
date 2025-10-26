<template>
	<div class="main-container min-h-screen">
		<!-- 顶部统计时间选择 -->
		<div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px]">
			<el-row :gutter="20" class="items-center">
				<el-col class="mb-[15px]">
					<div class="mb-[10px] font-bold">统计时间：</div>
					<el-radio-group v-model="dateType" size="large" @change="handleTimeRangeChange">
						<!-- <el-radio-button label="day">日报</el-radio-button> -->
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

 

		<!-- 2个折线图区域 -->
		<!-- <el-row :gutter="20" class="mb-[20px]"> -->
			<!-- 近12个月会员注册趋势 -->
			<!-- <el-col :span="11">
				<div class="bg-white p-[20px] rounded-lg shadow-sm h-full">
					<div class="flex justify-between items-center mb-[20px]">
						<h3 class="font-bold">近12个月会员注册趋势</h3>
					</div>
					<div class="store-income-chart" ref="storeIncomeChartRef" style="height: 300px;"></div>
				</div>
			</el-col> -->

			<!-- 客户金额占比 -->
			<!-- <el-col :span="11">
				<div class="bg-white p-[20px] rounded-lg shadow-sm h-full">
					<div class="flex justify-between items-center mb-[20px]">
						<h3 class="font-bold">客户金额占比</h3>
					</div>
					<div class="technician-income-chart" ref="technicianIncomeChartRef" style="height: 300px;"></div>
				</div>
			</el-col> -->

		<!-- </el-row> -->
        <div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px] flex justify-between items-center">
            <div class="flex gap-4">
				<div>
					<div class="text-gray-500 text-sm mb-1">总金额数</div>
					<div class="text-xl font-bold">¥ {{ (totalMoney) }}</div>
				</div>
			 
			</div>
			<!-- <el-button type="primary" @click="handleExport">导出数据</el-button> -->
        </div>
		<!-- 表格 -->
		<div class="bg-white p-[20px] rounded-lg shadow-sm mb-[20px]">
			<!-- <div class="flex justify-between items-center mb-[20px]">
				<h2 class="text-xl font-bold">收支盈利分析</h2>
			</div> -->
			<el-table :data="financialAnalysisList" size="large" v-loading="loading">
				<template #empty>
					<span>{{ !loading ? t("emptyData") : "" }}</span>
				</template>
				<el-table-column prop="member.nickname" :label="'客户名称'"  align="center" />
				<el-table-column prop="total_orders" :label="'订单数量'" align="center" />
				<el-table-column prop="completed_orders" :label="'完成订单数'" align="center" />
				<el-table-column prop="wait_check_orders" :label="'待核验数'" align="center" />
				<el-table-column prop="completed_amount" :label="'完成金额数'" align="center">
					<template #default="{ row }">
						<span class="text-green-500">{{ formatNumber(row.completed_amount || 0) }}</span>
					</template>
				</el-table-column>
				<el-table-column prop="completed_refund_orders" :label="'售后总数'" align="center">
					<template #default="{ row }">
						<span class="text-red-500">{{ row.completed_refund_orders || 0 }}</span>
					</template>
				</el-table-column>
			</el-table>
			<div class="mt-[20px] flex justify-end">
				<el-pagination
					v-model:current-page="pageData.page"
					v-model:page-size="pageData.limit"
					:total="pageData.total"
					:page-sizes="[15, 30, 50, 100]"
					layout="total, sizes, prev, pager, next, jumper"
					@size-change="handleSizeChange"
					@current-change="handlePageChange"
				/>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref, onMounted, nextTick } from 'vue'
	import { t } from '@/lang'
	import { img } from '@/utils/common'
	import {
		 
		getStoreIncomeTrend,
		getTechnicianIncomeTrend,
		getRefundExpenditure,
		getMemberStats
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
	const storeIncomeChartRef = ref<HTMLElement>()
	const technicianIncomeChartRef = ref<HTMLElement>()

	// 图表实例
	let storeIncomeChart: echarts.ECharts
	let technicianIncomeChart: echarts.ECharts

	// 收支盈利分析数据
	const financialAnalysisList = ref<any[]>([])
	
	// 统计数据
	const totalMoney = ref(0)
	
	// 分页数据
	const pageData = ref({
		page: 1,
		limit: 15,
		total: 0
	})

	// 方法
	const handleTimeRangeChange = () => {
		// 根据时间范围重新加载数据
		if (dateType.value !== 'custom') {
			// loadAllCharts()
			loadFinancialAnalysis()
		}
	}

	const handleCustomDateConfirm = () => {
		// 点击确定按钮后加载数据
		if (customDateRange.value && customDateRange.value.length === 2) {
			// loadAllCharts()
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
			date_type: dateType.value,
			page: pageData.value.page,
			limit: pageData.value.limit
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
		
		 
		// 近12个月会员注册趋势
		await loadStoreIncomeTrend(params)
		// 客户金额占比
		await loadTechnicianIncomeTrend(params)
  	}

 

	/**
	 * 加载近12个月会员注册趋势数据
	 */
	const loadStoreIncomeTrend = async (params: Record<string, any>) => {
		try {
			const res = await getStoreIncomeTrend(params)
			// 由于接口返回数据格式与预期不符，暂时使用模拟数据
			const mockXAxis = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月']
			const mockSeries = [0, 0, 0, 0, 0, 0, 0, 0, 3000, 0, 0, 0]
			
			if (storeIncomeChart) {
				storeIncomeChart.setOption({
					xAxis: {
						type: 'category',
						data: mockXAxis
					},
					yAxis: {
						type: 'value',
						axisLabel: {
							formatter: '￥{value}'
						}
					},
					series: [{
						data: mockSeries,
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
			console.error('加载近12个月会员注册趋势数据失败:', error)
		}
	}

	/**
	 * 加载客户金额占比数据
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
			console.error('加载客户金额占比数据失败:', error)
		}
	}

 

	/**
	 * 加载收支盈利分析数据
	 */
	const loadFinancialAnalysis = async () => {
		loading.value = true
		const params = prepareParams()
		
		try {
			const res = await getMemberStats(params)
			console.log('加载收支盈利分析数据成功:', res)
			totalMoney.value = res.data.total_money
			// 处理返回的数据
			if (res.data && res.data.data) {
				financialAnalysisList.value = res.data.data
				// 更新分页信息
				pageData.value.total = res.data.total || 0
				pageData.value.page = res.data.current_page || 1
				pageData.value.limit = res.data.per_page || 15
			} else {
				financialAnalysisList.value = []
				pageData.value.total = 0
			}
		} catch (error) {
			console.error('加载收支盈利分析数据失败:', error)
			financialAnalysisList.value = []
		} finally {
			loading.value = false
		}
	}

	/**
	 * 处理分页大小变化
	 */
	const handleSizeChange = (size: number) => {
		pageData.value.limit = size
		pageData.value.page = 1
		loadFinancialAnalysis()
	}

	/**
	 * 处理页码变化
	 */
	const handlePageChange = (page: number) => {
		pageData.value.page = page
		loadFinancialAnalysis()
	}

	/**
	 * 初始化图表
	 */
	const initCharts = () => {
		
		if (storeIncomeChartRef.value) {
			storeIncomeChart = echarts.init(storeIncomeChartRef.value)
		}
		if (technicianIncomeChartRef.value) {
			technicianIncomeChart = echarts.init(technicianIncomeChartRef.value)
		}
	

		// 设置图表响应式
		window.addEventListener('resize', () => {
			storeIncomeChart?.resize()
			technicianIncomeChart?.resize()
		})
	}

	// 生命周期
	onMounted(async () => {
		await nextTick()
		// 初始化图表
		// initCharts()
		// 加载数据
		// loadAllCharts()
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