<template>
	<div class="main-container min-h-screen">
		<!-- 顶部指标卡片 -->
		<div class="">
			<el-row class="justify-between" :gutter="15">
				<el-col :xs="0" :sm="0" :md="0" :lg="24" :xl="24">
					<div class="flex flex-wrap justify-between gap-[15px]">
						<!-- 本月订单 -->
						<div class="flex-1 min-w-[calc(20%-12px)] bg-white rounded-lg p-[20px] shadow-sm">
							<div class="flex justify-between items-start">
								<span class="text-[#999999] text-[12px]">{{ t('thisMonthOrder') }}</span>
								<img :src="img('/addon/home_service/admin/icon-1.png')" class="w-[30px] h-[30px]" />
							</div>
							<div class="flex items-end">
								<span class="text-2xl font-bold">{{ metricsData.monthlyOrder.value }}</span>
							</div>
							<div class="mt-[10px]">
								<span :class="getTrendClass(metricsData.monthlyOrder.change)"
									class="text-sm flex items-center">
									<el-icon v-if="metricsData.monthlyOrder.change > 0">
										<Top />
									</el-icon>
									<el-icon v-else-if="metricsData.monthlyOrder.change < 0">
										<Bottom />
									</el-icon>
									{{ formatTrend(metricsData.monthlyOrder.change) }}%
									<span v-if="metricsData.monthlyOrder.change !== null"
										class="!text-[#999999] ml-[5px]">{{ t('lastMonth') }}</span>
								</span>
							</div>
						</div>
						<!-- 总订单数 -->
						<div class="flex-1 min-w-[calc(20%-12px)] bg-white rounded-lg p-[20px] shadow-sm">
							<div class="flex justify-between items-start">
								<span class="text-[#999999] text-[12px]">{{ t('totalOrder') }}</span>
								<img :src="img('/addon/home_service/admin/icon-2.png')" class="w-[30px] h-[30px]" />
							</div>
							<div class="flex items-end">
								<span class="text-2xl font-bold">{{ metricsData.totalOrder.value }}</span>
							</div>
							<div class="mt-[10px]">
								<span
									class="text-sm text-[#999999] flex items-center">自{{metricsData.site_create_time.value}}至今</span>
							</div>
						</div>
						<!-- 师傅人数 -->
						<div class="flex-1 min-w-[calc(20%-12px)] bg-white rounded-lg p-[20px] shadow-sm">
							<div class="flex justify-between items-start">
								<span class="text-[#999999] text-[12px]">{{ t('师傅人数') }}</span>
								<img :src="img('/addon/home_service/admin/icon-3.png')" class="w-[30px] h-[30px]" />
							</div>
							<div class="flex items-end">
								<span class="text-2xl font-bold">{{ metricsData.servicePeople.value }}</span>
							</div>
							<div class="mt-[10px]">
								<span :class="getTrendClass(metricsData.servicePeople.change)"
									class="text-sm flex items-center">
									<el-icon v-if="metricsData.servicePeople.change > 0">
										<Top />
									</el-icon>
									<el-icon v-else-if="metricsData.servicePeople.change < 0">
										<Bottom />
									</el-icon>
									{{ formatTrend(metricsData.servicePeople.change) }}%
									<span v-if="metricsData.servicePeople.change !== null"
										class="!text-[#999999] ml-[5px]">{{ t('lastMonth') }}</span>
								</span>
							</div>
						</div>
						<!-- 门店数量 -->
						<div class="flex-1 min-w-[calc(20%-12px)] bg-white rounded-lg p-[20px] shadow-sm">
							<div class="flex justify-between items-start">
								<span class="text-[#999999] text-[12px]">{{ t('storeCount') }}</span>
								<img :src="img('/addon/home_service/admin/icon-4.png')" class="w-[30px] h-[30px]" />
							</div>
							<div class="flex items-end">
								<span class="text-2xl font-bold">{{ metricsData.storeCount.value }}</span>
							</div>
							<div class="mt-[10px]">
								<span :class="getTrendClass(metricsData.storeCount.change)"
									class="text-sm flex items-center">
									<el-icon v-if="metricsData.storeCount.change > 0">
										<Top />
									</el-icon>
									<el-icon v-else-if="metricsData.storeCount.change < 0">
										<Bottom />
									</el-icon>
									{{ formatTrend(metricsData.storeCount.change) }}%
									<span v-if="metricsData.storeCount.change !== null"
										class="!text-[#999999] ml-[5px]">{{ t('lastMonth') }}</span>
								</span>
							</div>
						</div>
						<!-- 会员人数 -->
						<div class="flex-1 min-w-[calc(20%-12px)] bg-white rounded-lg p-[20px] shadow-sm">
							<div class="flex justify-between items-start">
								<span class="text-[#999999] text-[12px]">{{ t('会员人数') }}</span>
								<img :src="img('/addon/home_service/admin/icon-5.png')" class="w-[30px] h-[30px]" />
							</div>
							<div class="flex items-end">
								<span class="text-2xl font-bold">{{ metricsData.servicePeople.value }}</span>
							</div>
							<div class="mt-[10px]">
								<span :class="getTrendClass(metricsData.servicePeople.change)"
									class="text-sm flex items-center">
									<el-icon v-if="metricsData.servicePeople.change > 0">
										<Top />
									</el-icon>
									<el-icon v-else-if="metricsData.servicePeople.change < 0">
										<Bottom />
									</el-icon>
									{{ formatTrend(metricsData.servicePeople.change) }}%
									<span v-if="metricsData.servicePeople.change !== null"
										class="!text-[#999999] ml-[5px]">{{ t('lastMonth') }}</span>
								</span>
							</div>
						</div>
					</div>
				</el-col>
			</el-row>
		</div>

		<!-- 工单数据和待办总览 -->
		<div class="pt-[20px]">
			<el-row :gutter="20">
				<!-- 工单数据 -->
				<el-col :span="10">
					<el-card class="!rounded-lg" shadow="never">
						<template #header>
							<div class="flex justify-between items-center">
								<span>{{ t('workOrderData') }}</span>
								<el-link type="primary" :underline="false" class="text-sm" @click="toLink('/home_service/order/list')">{{ t('viewAll') }}</el-link>
							</div>
						</template>
						<el-row :gutter="20">
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px]" @click="toLink('/home_service/order/list?status=wait_dispatch')">
									<div class="text-[#999999] text-sm">{{ t('pendingOrder') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.wait_dispatch || 0 }}</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg" @click="toLink('/home_service/order/list?status=wait_service')">
									<div class="text-[#999999] text-sm">{{ t('pendingService') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.wait_service || 0 }}</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg" @click="toLink('/home_service/order/list?status=in_service')">
									<div class="text-[#999999] text-sm">{{ t('inService') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.in_service || 0 }}</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg" @click="toLink('/home_service/order/list?status=')">
									<div class="text-[#999999] text-sm">{{ t('timeoutOrder') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.abnormal_order || 0 }}</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg" @click="toLink('/home_service/order/list?status=')">
									<div class="text-[#999999] text-sm">{{ t('specialReceipt') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.wait_check || 0 }}</div>
								</div>
							</el-col>
							<el-col :span="8">
								<div class="text-left p-[15px] bg-[#f6f6f6] rounded-lg" @click="toLink('/home_service/order/list?status=finish')">
									<div class="text-[#999999] text-sm">{{ t('completed') }}</div>
									<div class="text-2xl font-bold mt-[5px]">{{ workOrderData.finish || 0 }}</div>
								</div>
							</el-col>
						</el-row>
					</el-card>
				</el-col>

				<!-- 待办总览 -->
				<el-col :span="14">
					<el-card class="!shadow-sm from-white" shadow="never">
						<template #header>
							<div class="flex justify-between items-center">
								<span>{{ t('pendingSummary') }}</span>
							</div>
						</template>
						<div class="grid grid-cols-3 gap-4 min-h-[188px] ">
							<!-- 待办订单 -->
							<div class="bg-gradient-to-b from-blue-50 to-white p-[15px] rounded-lg flex flex-col justify-around" style="height: 100%;">
								<div class="text-blue-800 mb-2 text-center font-bold">待办订单</div>
								<div class="flex items-center justify-around">
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold font-bold">{{ workTodoData.wait_dispatch || 0 }}单</div>
											<div class="text-[13px] my-[6px] text-[#999999]">待派单</div>
										</div>
										<el-button class="mt-[10px] !bg-[#fff] !text-[#273de3]" plain type="primary"
											@click="toLink('/home_service/order/list')">
											立即派单
										</el-button>
									</div>
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold font-bold">{{ workTodoData.wait_refund || 0 }}单</div>
											<div class="text-[13px] text-[#999999] my-[6px]">待退款</div>
										</div>
										<el-button  class="mt-[10px] !bg-[#fff] !text-[#273de3]" plain type="primary"
											@click="toLink('/home_service/order/list')">
											处理退款
										</el-button>
									</div>
								</div>
							</div>

							<!-- 待办审核 -->
							<div class="bg-gradient-to-b from-yellow-50 to-white p-[15px] rounded-lg flex flex-col justify-around" style="height: 100%;">
								<div class="text-yellow-800 mb-2 text-center font-bold">待办审核</div>
								<div class="flex items-center justify-around">
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold">{{ workTodoData.technician_application_count || 0 }}</div>
											<div class="text-[13px] my-[6px] text-[#999999]">师傅审核</div>
										</div>
										<el-button class="mt-[10px] !bg-[#fff] !text-[#FF6816] !border-[#FF6816]" plain type="primary"
											@click="toLink('/home_service/technician/examine')">
											立即审核
										</el-button>
									</div>
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold">{{ workTodoData.store_application_count || 0 }}</div>
											<div class="text-[13px] text-[#999999] my-[6px]">门店审核</div>
										</div>
										<el-button class="mt-[10px] !bg-[#fff] !text-[#FF6816] !border-[#FF6816]" plain type="primary"
											@click="toLink('/home_service/store/examine')">
											立即审核
										</el-button>
									</div>
								</div>
							</div>

							<!-- 资金受理 -->
							<div class="bg-gradient-to-b from-pink-50 to-white p-[15px] rounded-lg flex flex-col justify-around" style="height: 100%;">
								<div class="text-pink-800 mb-2 text-center font-bold">资金受理</div>
								<div class="flex items-center justify-around">
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold">¥{{ formatNumber(workTodoData.technician_cash_out_money || '0') }}</div>
											<div class="text-[13px] my-[6px] text-[#999999]">师傅提现</div>
										</div>
										<el-button class="mt-[10px] !bg-[#fff] !text-[#FF1616] !border-[#FF1616]" plain type="primary"
											@click="toLink('/home_service/finance/withdrawal_review')">
											立即处理
										</el-button>
									</div>
									<div class="flex flex-col items-center justify-center">
										<div>
											<div class="text-[18px] font-bold">¥{{ formatNumber(workTodoData.store_cash_out_money || '0') }}</div>
											<div class="text-[13px] text-[#999999] my-[6px]">门店提现</div>
										</div>
										<el-button class="mt-[10px] !bg-[#fff] !text-[#FF1616] !border-[#FF1616]" plain type="primary"
											@click="toLink('/home_service/finance/withdrawal_review')">
											立即处理
										</el-button>
									</div>
								</div>
							</div>
						</div>
					</el-card>
				</el-col>
			</el-row>
		</div>
		
		
		<!-- 快捷入口和交易趋势 -->
		<div class="pt-[20px]">
			<el-row :gutter="20">
				<!-- 快捷入口 -->
				<el-col :span="10">
					<el-card class="!rounded-lg" shadow="never">
						<template #header>
							<div class="flex justify-between items-center">
								<span>{{ t('quickAccess') }}</span>
							</div>
						</template>
						<el-row :gutter="24">
							<el-col :span="12">
								<div @click="toLink('/home_service/finance/store_finance')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col  cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-1.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">机构结算</div>
								</div>
							</el-col>
							<el-col :span="12">
								<div @click="toLink('/home_service/finance/technician_finance')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-2.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">师傅结算</div>
								</div>
							</el-col>
							<el-col :span="12">
								<div @click="toLink('/home_service/goods/list')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-3.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">{{t('serviceManagement')}}</div>
								</div>
							</el-col>
							<el-col :span="12">
								<div @click="toLink('/home_service/technician/edit')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-4.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">{{t('newTechnician')}}</div>
								</div>
							</el-col>
							<el-col :span="12">
								<div @click="toLink('/home_service/store/add')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-5.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">新增机构</div>
								</div>
							</el-col>
							<el-col :span="12">
								<div @click="toLink('/home_service/order/list')" class="text-left p-[15px] bg-[#f6f6f6] rounded-lg mb-[20px] flex items-center justify-center flex-col cursor-pointer">
									<img :src="img('/addon/home_service/admin/link_icon-6.png')" class="w-[44px] h-[44px]" />
									<div class="text-[16px] mt-[16px]">服务订单</div>
								</div>
							</el-col>
						</el-row>
					</el-card>
				</el-col>
		
				<!-- 待办总览 -->
				<el-col :span="14">
					<el-card class="!shadow-sm from-white !h-[488px]" shadow="never">
						<template #header>
						<div class="flex justify-between items-center">
							<span>{{ t('transactionTrend') }}</span>
							<div class="flex space-x-2">
								<el-button size="small" :type="selectedTimeRange === 'week' ? 'primary' : ''" :plain="selectedTimeRange !== 'week'" @click="handleTimeRangeChange('week')">周</el-button>
								<el-button size="small" :type="selectedTimeRange === 'month' ? 'primary' : ''" :plain="selectedTimeRange !== 'month'" @click="handleTimeRangeChange('month')">月</el-button>
								<el-button size="small" :type="selectedTimeRange === 'year' ? 'primary' : ''" :plain="selectedTimeRange !== 'year'" @click="handleTimeRangeChange('year')">年</el-button>
							</div>
						</div>
					</template>
						<div class="echarts-box" ref="transactionChartRef" style="height: 400px;"></div>
					</el-card>
				</el-col>
			</el-row>
		</div>
		
		
	
		<!-- echarts -->
		<div class="pt-[20px]">
			<el-row :gutter="24">
				<!-- 服务类型占比 -->
				<el-col :span="8">
					<el-card class="!shadow-sm" shadow="never">
						<template #header>
							<div class="flex justify-between items-center">
								<span>{{ t('serviceTypeRatio') }}</span>
							</div>
						</template>
						<div class="service-type-chart" ref="serviceTypeChartRef" style="height: 392px;"></div>
					</el-card>
				</el-col>

				<!-- 热门服务排行 -->
				<el-col :span="8">
					<el-card class="!shadow-sm" shadow="never">
						<template #header>
				<div class="flex justify-between items-center">
					<span>{{ t('popularServiceRanking') }}</span>
					<div class="flex space-x-2">
						<el-button size="small" :type="popularServiceType === 'order_count' ? 'primary' : ''" 
							:plain="popularServiceType !== 'order_count'" @click="handlePopularServiceTypeChange('order_count')">{{ t('orderQuantity') }}</el-button>
						<el-button size="small" :type="popularServiceType === 'positive_rate' ? 'primary' : ''" 
							:plain="popularServiceType !== 'positive_rate'" @click="handlePopularServiceTypeChange('positive_rate')">{{ t('satisfactionRate') }}</el-button>
					</div>
				</div>
			</template>
						<div class="echarts-box" ref="popularServiceChartRef" style="height: 392px;"></div>
					</el-card>
				</el-col>

				<!-- 师傅排行 榜-->
				<el-col :span="8">
					<el-card class="!shadow-sm" shadow="never">
						<template #header>
							<div class="flex justify-between items-center">
							<span>{{ t('technicianRanking') }}</span>
							<div class="flex space-x-2">
								<el-button size="small" :type="technicianRankType === 'order_count' ? 'primary' : ''"
									:plain="technicianRankType !== 'order_count'" @click="handleTechnicianRankTypeChange('order_count')">{{ t('orderQuantity') }}</el-button>
								<el-button size="small" :type="technicianRankType === 'evaluate_avg_scores' ? 'primary' : ''"
									:plain="technicianRankType !== 'evaluate_avg_scores'" @click="handleTechnicianRankTypeChange('evaluate_avg_scores')">{{ t('rating') }}</el-button>
							</div>
						</div>
						</template>
						<div v-for="(tech, index) in technicianRankList || []"  :key="tech.technician_id" class="flex items-center p-[10px] mb-[15px]" v-show="index <= 3" :class="{'bg-[#F9F9F9]': index < 3, 'bg-[#ffffff]': index >= 3}" style="border-radius: 8px;">
							<div
								class="w-[30px] h-[30px] text-white rounded-full flex items-center justify-center font-bold mr-[10px]"
								:class="{
									'bg-yellow-500': index === 0,
									'bg-[#A1A5AC]': index === 1,
									'bg-[#BFC3CA]': index === 2,
									'bg-[#DDDDDD]': index >= 3
								}">
								{{ index + 1 }}
							</div>
							<img :src="img(tech.headimg)" alt=""
								class="w-[60px] h-[60px] rounded-full mr-[10px]" />
							<div class="flex-1">
								<div class="flex items-center">
									<div class="font-bold mr-[5px]">{{ tech.real_name }}</div>
									<div class="text-yellow-500">
										<el-rate
										    v-model="tech.evaluate_avg_scores"
										    disabled
											size="large"
										    text-color="#ff9900"
										  />
									</div>
								</div>
								<div class="text-gray-500 text-[12px] mt-[5px]">服务次数: {{ tech.order_count || 0 }} | 擅长: {{ tech.category_name[0]?.category_name || '暂无' }}</div>
							</div>
							<span v-if="index === 0" class="text-[#ff9900]">金牌师傅</span>
							<span v-else-if="index === 1" class="text-[#999999]">银牌师傅</span>
							<span v-else-if="index === 2" class="text-[#999999]">铜牌师傅</span>
						</div>
					</el-card>
				</el-col>
			</el-row>
		</div>

	</div>
</template>

<script lang="ts" setup>
	import { ref, onMounted, nextTick } from 'vue'
	import { t } from '@/lang'
	import { useRouter } from 'vue-router'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import {
	getBasicData,
	getWorkOrderData,
	getWorkTodoData,
	tradingTrendData,
	categoryRatedData as getCategoryRateData,
	popularServiceRank,
	technicianRankData
} from '@/addon/home_service/api/index'
	import * as echarts from 'echarts'
	const router = useRouter()
	const rateValue = ref(4)
	// 交易趋势图表ref
	const transactionChartRef = ref<HTMLElement>()
	// 服务类型占比图表ref
	const serviceTypeChartRef = ref<HTMLElement>()
	// 热门服务排行图表ref
	const popularServiceChartRef = ref<HTMLElement>()

	// 加载状态
	const loading = ref(false)
	
	// 基础数据
	const basicData = ref({
		order_month: 0,
		order_total: 0,
		order_growth_rate: 0,
		technician_month: 0,
		technician_total: 0,
		technician_growth_rate: 0,
		store_month: 0,
		store_total: 0,
		store_growth_rate: 0,
		member_month: 0,
		member_total: 0,
		member_growth_rate: 0,
		site_create_time: ''
	})
	
	// 工单数据
	const workOrderData = ref({
		wait_dispatch: 0,
		wait_service: 0,
		in_service: 0,
		abnormal_order: 0,
		wait_check: 0,
		finish: 0
	})
	
	// 待办总览数据
	const workTodoData = ref({
		wait_dispatch: 0,
		wait_refund: 0,
		technician_application_count: 0,
		store_application_count: 0,
		technician_cash_out_money: '0.00',
		store_cash_out_money: '0.00'
	})
	
	// 交易趋势数据
	const transactionData = ref<any[]>([])
	
	// 服务类型占比数据
	const categoryRateData = ref<any[]>([])
	
	// 热门服务排行数据
	const popularServiceRankData = ref<any[]>([])
	
	// 师傅排行榜数据
	const technicianRankList = ref<any[]>([])

	// 当前选中的时间范围类型
	const selectedTimeRange = ref<'week' | 'month' | 'year'>('month')

	/**
	 * 指标数据类型定义
	 */
	interface MetricItem {
		value : string
		change : number | null
	}

	interface MetricsData {
		monthlyOrder : MetricItem
		totalOrder : MetricItem
		servicePeople : MetricItem
		storeCount : MetricItem
		memberCount:MetricItem
	}

	/**
	 * 从基础数据生成指标数据
	 */
	const metricsData = ref<MetricsData>({
		monthlyOrder: {
			value: '0',
			change: null
		},
		totalOrder: {
			value: '0',
			change: null
		},
		servicePeople: {
			value: '0',
			change: null
		},
		storeCount: {
			value: '0',
			change: null
		},
		memberCount:{
			value:0,
			change:null
		},
		site_create_time:{
			value: '',
			change: null
		}
	})

	/**
	 * 获取趋势颜色类名
	 * @param change 变化值
	 * @returns 颜色类名
	 */
	const getTrendClass = (change : number | null) : string => {
		if (change === null) {
			return 'text-[#999999]'
		}
		if (change > 0) {
			return 'text-green-500'
		}
		if (change < 0) {
			return 'text-red-500'
		}
		return 'text-[#999999]'
	}

	/**
	 * 格式化趋势值
	 * @param change 变化值
	 * @returns 格式化后的字符串
	 */
	const formatTrend = (change : number | null) : string => {
		if (change === null) {
			return '0'
		}
		return Math.abs(change).toFixed(1)
	}

	/**
	 * 热门服务排行类型
	 */// 热门服务排行类型
	const popularServiceType = ref<'order_count' | 'positive_rate'>('order_count')
	
	// 师傅排行类型
	const technicianRankType = ref<'order_count' | 'evaluate_avg_scores'>('order_count')

	/**
	 * 切换热门服务排行类型
	 */
	const handlePopularServiceTypeChange = async (type: 'order_count' | 'positive_rate') => {
		popularServiceType.value = type
		try {
			loading.value = true
			const result = await popularServiceRank({ stat_type: type })
			if (result.data && result.code === 1) {
				popularServiceRankData.value = result.data
				// 等待DOM渲染完成后重新初始化图表
				nextTick(() => {
					initPopularServiceChart()
				})
			}
		} catch (error) {
			console.error('获取热门服务排行数据失败:', error)
		} finally {
			loading.value = false
		}
	}
	
	/**
	 * 切换师傅排行类型
	 */
	const handleTechnicianRankTypeChange = async (type: 'order_count' | 'evaluate_avg_scores') => {
		technicianRankType.value = type
		try {
			loading.value = true
			const result = await technicianRankData({ stat_type: type })
			if (result.data && result.code === 1) {
				technicianRankList.value = result.data
			}
		} catch (error) {
			console.error('获取师傅排行榜数据失败:', error)
		} finally {
			loading.value = false
		}
	}
	/**
	 * 链接跳转
	 */
	const toLink = (link : string) => {
		router.push(link)
	}

	/**
	 * 初始化交易趋势图表
	 */
	const initTransactionChart = () => {
		if (!transactionChartRef.value) return

		// 销毁已存在的图表实例
		const chartInstance = echarts.getInstanceByDom(transactionChartRef.value)
		if (chartInstance) {
			chartInstance.dispose()
		}

		// 创建新的图表实例
		const chart = echarts.init(transactionChartRef.value)

		// 获取接口数据或使用默认数据
		const validData = transactionData.value && Array.isArray(transactionData.value) ? transactionData.value : []
		const dateList = validData.map(item => item.time_key)
		const orderData = validData.map(item => item.order_count || 0)
		const amountData = validData.map(item => item.total_amount || 0)

		// 图表配置
		const option = {
			tooltip: {
				trigger: 'axis',
				axisPointer: {
					type: 'cross',
					label: {
						backgroundColor: '#6a7985'
					}
				},
				formatter: function(params) {
					let result = params[0].axisValue + '<br/>'
					params.forEach(param => {
						result += `${param.marker}${param.seriesName}: ${param.seriesName.includes('金额') ? '¥' : ''}${formatNumber(param.value)}<br/>`
					})
					return result
				}
			},
			legend: {
				data: ['订单量', '交易金额(千元)'],
				right: '10%',
				top: '0%'
			},
			grid: {
				left: '3%',
				right: '4%',
				bottom: '3%',
				containLabel: true
			},
			xAxis: [
				{
					type: 'category',
					boundaryGap: false,
					data: dateList.length > 0 ? dateList : ['暂无数据'],
					axisLabel: {
						fontSize: 12,
						color: '#999'
					}
				}
			],
			yAxis: [
				{
					type: 'value',
					name: '数量/金额',
					splitLine: {
						lineStyle: {
							color: '#f0f0f0'
						}
					},
					axisLabel: {
						formatter: function(value) {
							return formatNumber(value)
						},
						fontSize: 12,
						color: '#999'
					}
				}
			],
			series: [
				{
					name: '订单量',
					type: 'line',
					stack: '总量',
					areaStyle: {
						opacity: 0.3,
						color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
							{
								offset: 0,
								color: 'rgba(24, 144, 255, 0.8)'
							},
							{
								offset: 1,
								color: 'rgba(24, 144, 255, 0.1)'
							}
						])
					},
					lineStyle: {
						width: 3,
						color: '#1890ff'
					},
					symbolSize: 6,
					itemStyle: {
						color: '#1890ff',
						borderColor: '#fff',
						borderWidth: 2
					},
					data: orderData.length > 0 ? orderData : [0]
				},
				{
					name: '交易金额(千元)',
					type: 'line',
					stack: '总量',
					areaStyle: {
						opacity: 0.3,
						color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
							{
								offset: 0,
								color: 'rgba(0, 192, 80, 0.8)'
							},
							{
								offset: 1,
								color: 'rgba(0, 192, 80, 0.1)'
							}
						])
					},
					lineStyle: {
						width: 3,
						color: '#00c050'
					},
					symbolSize: 6,
					itemStyle: {
						color: '#00c050',
						borderColor: '#fff',
						borderWidth: 2
					},
					data: amountData.length > 0 ? amountData : [0]
				}
			]
		}

		// 设置图表配置
		chart.setOption(option)

		// 监听窗口大小变化，自动调整图表大小
		window.addEventListener('resize', () => {
			chart.resize()
		})
	}

	/**
	 * 初始化热门服务排行横向柱状图
	 */
	const initPopularServiceChart = () => {
		if (!popularServiceChartRef.value) return

		// 销毁已存在的图表实例
		const chartInstance = echarts.getInstanceByDom(popularServiceChartRef.value)
		if (chartInstance) {
			chartInstance.dispose()
		}

		// 创建新的图表实例
		const chart = echarts.init(popularServiceChartRef.value)

		// 从接口数据提取并排序
			let sortedData = [...(popularServiceRankData.value || [])]
			.filter(item => {
				// 根据不同类型过滤数据
				if (popularServiceType.value === 'order_count') {
					return item.count > 0
				} else {
					return item.count > 0 && item.evaluate_count > 0
				}
			})
			.sort((a, b) => {
				// 根据不同类型排序
				return parseFloat(b.count) - parseFloat(a.count)
			})
			.slice(0, 5)

		// 如果没有数据，使用默认数据
		const defaultData = []

		const displayData = sortedData.length > 0 ? sortedData : defaultData

		// 获取服务名称和数值
		const serviceNames = displayData.map(item => item.category_name)
		const serviceValues = displayData.map(item => parseFloat(item.count))

		// 颜色配置
		const colors = ['#1890ff', '#00c050', '#ff9800', '#2f54eb', '#13c2c2']

		// 图表配置
		const option = {
			tooltip: {
				trigger: 'axis',
				axisPointer: {
					type: 'shadow'
				},
				formatter: (params) => {
					const data = params[0]
					const value = popularServiceType.value === 'order_count' 
						? data.value 
						: `${data.value}%`
					return `${data.axisValue}<br/>${data.marker}${data.seriesName}: ${value}`
				}
			},
			grid: {
				left: '3%',
				right: '4%',
				bottom: '3%',
				containLabel: true
			},
			xAxis: {
				type: 'value',
				boundaryGap: [0, 0.01],
				axisLine: {
					show: false
				},
				axisLabel: {
					formatter: (value) => {
						return popularServiceType.value === 'positive_rate' ? `${value}%` : value
					}
				}
			},
			yAxis: {
				type: 'category',
				data: serviceNames,
				axisTick: {
					show: false
				}
			},
			series: [
				{
					name: popularServiceType.value === 'order_count' ? '服务数量' : '满意度',
					type: 'bar',
					data: serviceValues,
					itemStyle: {
						color: function(params) {
							return colors[params.dataIndex]
						}
					},
					label: {
						show: true,
						position: 'right',
						formatter: (params) => {
							return popularServiceType.value === 'positive_rate' ? `${params.value}%` : params.value
						}
					}
				}
			]
		}

		// 设置图表配置
		chart.setOption(option)

		// 监听窗口大小变化，自动调整图表大小
		window.addEventListener('resize', () => {
			chart.resize()
		})
	}

	/**
	 * 初始化服务类型占比环形图
	 */
	const initServiceTypeChart = () => {
		if (!serviceTypeChartRef.value) return

		// 销毁已存在的图表实例
		const chartInstance = echarts.getInstanceByDom(serviceTypeChartRef.value)
		if (chartInstance) {
			chartInstance.dispose()
		}

		// 创建新的图表实例
		const chart = echarts.init(serviceTypeChartRef.value)

		// 从接口数据提取(过滤掉total_money为0的项)
		const validData = (categoryRateData.value || []).filter(item => parseFloat(item.total_money || '0') > 0)

		// 如果没有数据，使用默认数据
		const defaultData = []

		const displayData = validData.length > 0 ? validData : defaultData

		// 环形图数据
		const data = displayData.map(item => ({
			value: parseFloat(item.total_money || '0'),
			name: item.category_name
		}))

		// 颜色配置
		const colors = ['#1890ff', '#00c050', '#ff9800', '#2f54eb', '#13c2c2', '#f5222d']

		// 获取图例数据
		const legendData = displayData.map(item => item.category_name)

		// 图表配置
		const option = {
			tooltip: {
				trigger: 'item',
				formatter: '{a} <br/>{b}: ¥{c} ({d}%)'
			},
			legend: {
				orient: 'vertical',
				right: 0,
				top: 'center',
				data: legendData
			},
			series: [
				{
					name: '服务类型',
					type: 'pie',
					radius: ['40%', '70%'],
					avoidLabelOverlap: false,
					itemStyle: {
						borderColor: '#fff',
						borderWidth: 2
					},
					label: {
						show: false,
						position: 'center'
					},
					emphasis: {
						label: {
							show: true,
							fontSize: '18',
							fontWeight: 'bold'
						}
					},
					labelLine: {
						show: false
					},
					data: data,
					color: colors
				}
			]
		}

		// 设置图表配置
		chart.setOption(option)

		// 监听窗口大小变化，自动调整图表大小
		window.addEventListener('resize', () => {
			chart.resize()
		})
	}

	/**
	 * 格式化数字，添加千分位分隔符
	 */
	const formatNumber = (num: number | string): string => {
		if (typeof num === 'string') num = parseFloat(num)
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
	}

	/**
	 * 获取所有数据
	 */
	const fetchAllData = async () => {
		try {
			loading.value = true

			// 并行获取所有数据
			const [
				basicResult,
				workOrderResult,
				workTodoResult,
				transactionResult,
				categoryRateResult,
				popularServiceResult,
				technicianRankResult
			] = await Promise.all([
			getBasicData({}),
			getWorkOrderData({}),
			getWorkTodoData({}),
			tradingTrendData({ date_type: selectedTimeRange.value }),
			getCategoryRateData({}),
			popularServiceRank({ stat_type: 'order_count' }),
			technicianRankData({ stat_type: technicianRankType.value })
		])

			// 更新数据
			if (basicResult.data && basicResult.code === 1) {
				basicData.value = basicResult.data
				// 更新指标数据
				metricsData.value = {
					monthlyOrder: {
						value: formatNumber(basicData.value.order_month),
						change: basicData.value.order_growth_rate
					},
					totalOrder: {
						value: formatNumber(basicData.value.order_total),
						change: null
					},
					servicePeople: {
						value: formatNumber(basicData.value.technician_total),
						change: basicData.value.technician_growth_rate
					},
					storeCount: {
						value: formatNumber(basicData.value.store_total),
						change: basicData.value.store_growth_rate
					},
					memberCount:{
						value: formatNumber(basicData.value.member_total),
						change: basicData.value.member_growth_rate
					},
					site_create_time: {
						value:basicData.value.site_create_time , // 这里需要根据实际接口返回修改
						change: null
					},
				}
			}

			if (workOrderResult.data && workOrderResult.code === 1) {
				workOrderData.value = workOrderResult.data
			}

			if (workTodoResult.data && workTodoResult.code === 1) {
				workTodoData.value = workTodoResult.data
			}

			if (transactionResult.data && transactionResult.code === 1) {
				transactionData.value = transactionResult.data
			}

			if (categoryRateResult.data && categoryRateResult.code === 1) {
				categoryRateData.value = categoryRateResult.data
			}

			if (popularServiceResult.data && popularServiceResult.code === 1) {
				popularServiceRankData.value = popularServiceResult.data
			}

			if (technicianRankResult.data && technicianRankResult.code === 1) {
				technicianRankList.value = technicianRankResult.data
			}
			console.log(technicianRankList.value)
		} catch (error) {
			console.error('获取数据失败:', error)
		} finally {
			loading.value = false
			// 等待DOM渲染完成后初始化图表
			nextTick(() => {
				initTransactionChart()
				initServiceTypeChart()
				initPopularServiceChart()
			})
		}
	}

	/**
	 * 处理时间范围变更
	 */
	const handleTimeRangeChange = async (timeRange: 'week' | 'month' | 'year') => {
		selectedTimeRange.value = timeRange
		// 重新获取交易趋势数据
		try {
			const transactionResult = await tradingTrendData({ date_type: timeRange })
			if (transactionResult.data && transactionResult.code === 1) {
				transactionData.value = transactionResult.data
				// 等待DOM渲染完成后重新初始化图表
				nextTick(() => {
					initTransactionChart()
				})
			}
		} catch (error) {
			console.error('获取交易趋势数据失败:', error)
		}
	}

	/**
	 * 页面加载时获取数据
	 */
	onMounted(() => {
		console.log('首页加载完成')
		fetchAllData()
	})
</script>

<style scoped>
	/* 响应式调整 */
	@media (max-width: 1200px) {
		.el-row {
			margin-left: 0 !important;
			margin-right: 0 !important;
		}
	}

	::v-deep .el-card__header {
		border-bottom: none !important;
	}

	::v-deep .el-card__header {
		padding: 20px 20px 0 20px !important
	}
	::v-deep .el-card {
		border-radius: 10px!important;
	}
	::v-deep .el-rate__icon{
		margin-right:-2px !important;
	}
</style>