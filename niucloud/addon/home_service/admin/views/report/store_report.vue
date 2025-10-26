<template>
	<div class="main-container min-h-screen">
		<!-- 顶部统计时间和核心指标选择 -->
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

			<el-row :gutter="20" class="items-center mt-[20px]">
				<el-col>
					<div class="mb-[10px] font-bold">核心指标：</div>
					<el-radio-group v-model="statType" size="large" @change="handleMetricChange">
						<el-radio-button label="service_order_count">服务数量</el-radio-button>
						<el-radio-button label="total_order_money">业绩</el-radio-button>
					</el-radio-group>
				</el-col>
			</el-row>
		</div>

		<!-- 排行榜区域 -->
		<div class="technician-ranking-section">
			<div class="flex justify-between items-center mb-[20px]">
				<h2 class="text-xl font-bold">师傅排行榜</h2>
			</div>

			<!-- 前三名排行榜 -->
		<div class="flex justify-center items-end">
			<!-- 第二名 -->
			<div v-if="topThreeTechnicians[1]" class="bg-[#ffba2e] w-[200px] h-[150px] rounded-[10px] p-[15px] flex flex-col justify-center items-center relative">
				<div class="absolute top-[-20px] right-[10px] text-[12px] font-bold bg-[#ff9831] text-[#fff] px-[15px] py-[5px] rounded-[20px]">TOP2</div>
				<div>
					<el-image style="width:60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
						:src="img(topThreeTechnicians[1].headimg || '')" fit="contain">
						<template #error>
							<div class="flex justify-center items-center w-full h-[60px]"><img
									class="max-w-[60px]" src="@/app/assets/images/member_head.png" alt=""
									object-fit="contain"></div>
						</template>
					</el-image>
				</div>
				<div class="font-bold  multi-hidden text-center leading-[1] mb-[5px]">
					{{ topThreeTechnicians[1].store_name || '-' }}
				</div>
				<div class="text-[13px] text-[#636363]">
					{{ statType === 'service_order_count' ? '服务数量' : '业绩' }}：{{ statType === 'service_order_count' ? (topThreeTechnicians[1].service_order_count || 0) : (topThreeTechnicians[1].total_order_money || 0) }}
				</div>
			</div>
			<!-- 第一名 -->
			<div v-if="topThreeTechnicians[0]" class="bg-[#ff7563] w-[200px] h-[170px] rounded-[10px] mx-[15px] p-[15px] flex flex-col justify-center items-center relative">
				<div class="absolute top-[-20px] right-[10px] text-[12px] font-bold bg-[#ff2121] text-[#fff] px-[15px] py-[5px] rounded-[20px]">TOP1</div>
				<div>
					<el-image style="width:60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
						:src="img(topThreeTechnicians[0].headimg || '')" fit="contain">
						<template #error>
							<div class="flex justify-center items-center w-full h-[60px]"><img
									class="max-w-[60px]" src="@/app/assets/images/member_head.png" alt=""
									object-fit="contain"></div>
						</template>
					</el-image>
				</div>
				<div class="font-bold text-[#fff]  multi-hidden text-center leading-[1] mb-[5px]">
					{{ topThreeTechnicians[0].store_name || '-' }}
				</div>
				<div class="text-[13px] text-[#636363]">
					{{ statType === 'service_order_count' ? '服务数量' : '业绩' }}：{{ statType === 'service_order_count' ? (topThreeTechnicians[0].service_order_count || 0) : (topThreeTechnicians[0].total_order_money || 0) }}
				</div>
			</div>
			<!-- 第三名 -->
			<div v-if="topThreeTechnicians[2]" class="bg-[#95ea46] w-[200px] h-[150px] rounded-[10px] p-[15px] flex flex-col justify-center items-center relative">
				<div class="absolute top-[-20px] right-[10px] text-[12px] font-bold bg-[#36eb16] text-[#fff] px-[15px] py-[5px] rounded-[20px]">TOP3</div>
				<div>
					<el-image style="width:60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
						:src="img(topThreeTechnicians[2].headimg || '')" fit="contain">
						<template #error>
							<div class="flex justify-center items-center w-full h-[60px]"><img
									class="max-w-[60px]" src="@/app/assets/images/member_head.png" alt=""
									object-fit="contain"></div>
						</template>
					</el-image>
				</div>
				<div class="font-bold multi-hidden text-center leading-[1] mb-[5px]">
					{{ topThreeTechnicians[2].store_name || '-' }}
				</div>
				<div class="text-[13px] text-[#636363]">
					{{ statType === 'service_order_count' ? '服务数量' : '业绩' }}：{{ statType === 'service_order_count' ? (topThreeTechnicians[2].service_order_count || 0) : (topThreeTechnicians[2].total_order_money || 0) }}
				</div>
			</div>
		</div>

			<!-- 师傅列表表格 -->
			<div class="bg-white rounded-lg shadow-sm p-[20px] mt-[40px]">
				<el-table :data="technicianList" size="large" v-loading="loading">
					<template #empty>
						<span>{{ !loading ? t("emptyData") : "" }}</span>
					</template>
					<el-table-column prop="rank" :label="'排名'" width="80" align="center">
						<template #default="{ row, $index }">
							<div class="rank-number-list w-[24px] h-[24px] text-white rounded-full flex items-center justify-center text-sm"
								:class="{
									'bg-red-500': $index === 0,
									'bg-[#FF6816]': $index === 1,
									'bg-green-500': $index === 2,
									'bg-[#DDDDDD]': $index >= 3
								}">
								{{ $index + 1 }}
							</div>
						</template>
					</el-table-column>
					<el-table-column :label="'机构信息'" min-width="200">
						<template #default="{ row }">
							<div class="flex items-center">
								<el-image style="width:60px; height: 60px" class="mr-[10px] rounded-[50%]"
									:src="img(row.headimg)" fit="contain">
									<template #error>
										<div class="flex justify-center items-center w-full h-[60px]"><img
												class="max-w-[60px]" src="@/app/assets/images/member_head.png" alt=""
												object-fit="contain"></div>
									</template>
								</el-image>
								<div>
									<div class="font-medium">{{ row.store_name }}</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="service_order_count" :label="'服务单数'" width="120" align="center" />
					<el-table-column prop="orderAmount" :label="'订单金额'" width="120" align="center">
						<template #default="{ row }">
							<span class="text-[#ff0000]">￥{{ row.total_order_money }}</span>
						</template>
					</el-table-column>
					<el-table-column prop="commission" :label="'佣金'" width="120" align="center">
						<template #default="{ row }">
							<span class="text-[#ff0000]">￥{{ row.total_commission }}</span>
						</template>
					</el-table-column>
					<el-table-column prop="timeout_count" :label="'超时次数'" width="120" align="center" />
					<el-table-column prop="satisfaction" :label="'客户满意度'" width="150" align="center">
						<template #default="{ row }">
							<el-rate v-model="row.evaluate_avg_scores" disabled size="small"/>
						</template>
					</el-table-column>
					<el-table-column label="操作" width="100" fixed="right">
						<template #default="scope">
							<el-button type="primary" link @click="detailEvent(scope.row)">{{ t('info') }}</el-button>
						</template>
					</el-table-column>
				</el-table>
				
				<!-- <div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="tableData.page"
						v-model:page-size="tableData.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="tableData.total"
						@size-change="loadTechnicianReport" @current-change="loadTechnicianReport" />
				</div> -->
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref, onMounted, reactive } from 'vue'
	import { t } from '@/lang'
	import { useRouter } from 'vue-router'
	import { img, getTablePageStorage, setTablePageStorage } from '@/utils/common'
	import { getStoreReport } from '@/addon/home_service/api/report'

	// 响应式数据
	const dateType = ref('year')
	const customDateRange = ref([])
	const statType = ref('service_order_count')
	const loading = ref(false)
	const router = useRouter()

	// 表格数据
	const tableData = reactive({
		page: 1,
		limit: 10,
		total: 0
	})

	// 师傅列表数据
	const technicianList = ref([])
	// 前三名师傅数据
	const topThreeTechnicians = ref([{}, {}, {}])

	// 方法
	const handleTimeRangeChange = () => {
		// 根据时间范围重新加载数据
		if (dateType.value !== 'custom') {
			loadTechnicianReport()
		}
	}

	const handleMetricChange = () => {
		// 根据核心指标重新加载数据
		loadTechnicianReport()
	}

	const handleCustomDateConfirm = () => {
		// 点击确定按钮后加载数据
		if (customDateRange.value && customDateRange.value.length === 2) {
			loadTechnicianReport()
		} else {
			// 提示用户选择日期范围
			uni.showToast({
				title: '请选择日期范围',
				icon: 'none'
			})
		}
	}

	/**
	 * 获取师傅报表数据
	 */
	const loadTechnicianReport = (page: number = 1) => {
		loading.value = true
		tableData.page = page
		
		// 准备请求参数
		const params = {
			page: tableData.page,
			limit: tableData.limit,
			date_type: dateType.value,
			stat_type: statType.value
		}
		
		// 只有自定义模式才传时间范围，将日期字符串转换为时间戳数组
		if (dateType.value === 'custom' && customDateRange.value && customDateRange.value.length === 2) {
			params.date = [
				(customDateRange.value[0]),
				(customDateRange.value[1])
			]
		}
		
		// 调用接口获取数据
		getStoreReport(params).then(res => {
			loading.value = false
			technicianList.value = res.data || []
			tableData.total = res.data.total || 0
			
			// 设置前三名师傅数据
			topThreeTechnicians.value = [{}, {}, {}]
			if (technicianList.value && technicianList.value.length > 0) {
				for (let i = 0; i < Math.min(3, technicianList.value.length); i++) {
					topThreeTechnicians.value[i] = technicianList.value[i]
				}
			}
			
			setTablePageStorage(tableData.page, tableData.limit, params)
		}).catch(() => {
			loading.value = false
		})
	}

	/**
	 * 打开详情页
	 */
	const detailEvent = (data: any) => {
		router.push('/home_service/store/detail?id=' + data.store_id)
	};

	// 生命周期
	onMounted(() => {
		// 加载数据
		loadTechnicianReport(getTablePageStorage({}).page)
	})
</script>

<style scoped>
	.main-container {
		padding: 20px;
		background-color: #f5f7fa;
	}

	/* 排行榜样式 */
	.top-three-ranking {
		position: relative;
		height: 300px;
		margin-bottom: 20px;
	}

	.rank-item {
		text-align: center;
	}

	.rank-first {
		width: 40%;
	}

	.rank-second,
	.rank-third {
		width: 30%;
	}

	.rank-number {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		color: white;
		font-size: 20px;
		font-weight: bold;
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto 10px;
	}

	.technician-avatar {
		width: 100px;
		height: 100px;
		border-radius: 50%;
		border: 4px solid #fff;
		box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
	}

	.rank-first .technician-avatar {
		width: 120px;
		height: 120px;
	}

	.technician-name {
		margin-top: 10px;
		font-size: 16px;
		font-weight: bold;
	}

	.technician-score {
		margin-top: 5px;
		font-size: 14px;
		color: #666;
	}

	/* 表格样式 */
	:deep(.el-table__row:hover) {
		background-color: #f5f7fa;
	}
</style>