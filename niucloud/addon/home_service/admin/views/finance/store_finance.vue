<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<!-- 顶部标题 + 按钮 -->
			<div class="flex justify-between items-center mb-[16px]">
				<span class="text-page-title">{{ t('settlementList') }}</span>
			</div>

			<!-- 顶部Tabs -->
			<el-tabs v-model="activeName" class="demo-tabs" @tab-change="changeTab" type="border-card">
				<el-tab-pane :label="item.name" :name="item.type" v-for="(item, index) in typeList" :key="index">
					<!-- 筛选表单：根据不同Tab显示不同的筛选条件 -->
					<el-card class="box-card !border-none table-search-wrap" shadow="never">
						<DynamicCollapseForm :fields="getSettlementFormFields()"
							:search-param="settlementTableData.searchParam" @search="loadSettlementList"
							@reset="handleFormReset" ref="collapseFormRef" />
					</el-card>

					<!-- 师傅结算表格：根据不同Tab显示不同的表格内容 -->
					<div class="mt-[10px]">
						<el-table :data="settlementTableData.data" size="large" v-loading="settlementTableData.loading"
							row-key="id">
							<template #empty>
								<span>{{ !settlementTableData.loading ? t('emptyData') : '' }}</span>
							</template>

							<!-- 未结算Tab表格列 -->
							<template v-if="activeName === 1">
								<el-table-column prop="store_id" :label="t('ID')" min-width="80"
									:show-overflow-tooltip="true" />
								<!-- 师傅名称列 -->
								<el-table-column prop="store_name" :label="t('technicianName')" min-width="120"
									:show-overflow-tooltip="true" />
								<!-- 结算周期列 -->
								<el-table-column prop="month" :label="t('settlementCycle')" min-width="100"
									:show-overflow-tooltip="true" />
								<!-- 汇总订单数列 -->
								<el-table-column prop="total_count" :label="t('totalOrders')" min-width="100"
									align="center" />
								<!-- 结算金额列 -->
								<el-table-column prop="total_amount" :label="t('settlementAmount')" min-width="120"
									align="center">
									<template #default="{ row }">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.total_amount }}</span>
									</template>
								</el-table-column>
								<!-- 操作列 -->
								<el-table-column :label="t('operation')" fixed="right" align="right" width="180">
									<template #default="{ row }">
										<el-button type="primary" link
											@click="detailEvent(row)">详情</el-button>
										<!-- <el-button type="primary" link
											@click="exportEvent(row)">{{ t('exportFile') }}</el-button> -->
									</template>
								</el-table-column>
							</template>

							<!-- 已结算Tab表格列（修正后） -->
							<template v-if="activeName === 2">
								<el-table-column prop="store_id" :label="t('ID')" min-width="80"
									:show-overflow-tooltip="true" />
								<!-- 师傅名称列 -->
								<el-table-column prop="store_name" :label="t('technicianName')" min-width="150"
									:show-overflow-tooltip="true" />
								<!-- 结算订单数列 -->
								<el-table-column prop="settled_count" :label="t('settlementOrders')" min-width="120"
									align="center" />
								<!-- 结算佣金列 -->
								<el-table-column prop="settled_amount" :label="t('settlementCommission')"
									min-width="120" align="center">
									<template #default="{ row }">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.settled_amount }}</span>
									</template>
								</el-table-column>
								<!-- 金额合计列 -->
								<el-table-column prop="total_amount" :label="t('totalAmount')" min-width="120"
									align="center">
									<template #default="{ row }">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.total_amount }}</span>
									</template>
								</el-table-column>
							</template>
						</el-table>

						<!-- 分页 -->
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="settlementTableData.page"
								v-model:page-size="settlementTableData.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="settlementTableData.total"
								@size-change="loadSettlementList()" @current-change="loadSettlementList" />
						</div>
					</div>
				</el-tab-pane>
			</el-tabs>
		</el-card>

		<!-- 导出确认弹窗 -->
		<export-sure ref="exportSureDialog" :show="flag" :type="export_type"
			:searchParam="settlementTableData.searchParam" @close="handleClose" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed, nextTick } from 'vue';
	import { t } from '@/lang';
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common';
	import { ElMessage, FormInstance } from 'element-plus';
	import { useRouter, useRoute } from 'vue-router';
	import { getStoreFinance,getStoreListFinanceStat } from '@/addon/home_service/api/finance'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';

	// 路由相关
	const route = useRoute();
	const router = useRouter();
	const pageName = route.meta.title;

	// 顶部Tabs数据
	const typeList = ref([
		{
			name: t('toSettle'), // 未结算
			type: 1,
		},
		{
			name: t('settled'), // 已结算
			type: 2,
		},
	]);
	const activeName = ref(1);

	// 师傅结算表格数据
	const settlementTableData = reactive({
		page: 1,
		limit: 10,
		total: 100,
		loading: false,
		data: [],
		searchParam: {
			// 未结算筛选参数
			store_name: '',
			// 已结算筛选参数
			settledTechnicianName: '',
			settledTime: [],
			status: ''
		},
	});

	// 筛选表单Ref
	const collapseFormRef = ref<FormInstance>();

	// 重置筛选表单
	const handleFormReset = () => {
		settlementTableData.page = 1;
		settlementTableData.searchParam = {
			store_name: '',
			settlementCycle: '',
			settlementTime: [],
			settledTechnicianName: '',
			settledTime: [],
			status: ''
		};
		loadSettlementList();
	};

	// 根据当前激活的Tab获取对应的筛选表单配置
	const getSettlementFormFields = () => {// 未结算Tab的筛选表单
		if (activeName.value === 1) {
			return [
				{
					prop: 'store_name',
					label: t('technicianName'),
					component: 'ElInput',
					placeholder: t('technicianNamePlaceholder'),
					props: {
						class: 'input-width !w-[214px]'
					},
				},
			];
		}
		// 已结算Tab的筛选表单（新表单）
		else {
			return [
				{
					prop: 'store_name',
					label: t('technicianName'),
					component: 'ElInput',
					placeholder: t('technicianNamePlaceholder'),
					props: {
						class: 'input-width !w-[214px]'
					},
				},
				{
					prop: 'create_time',
					label: t('TransactionHistorycreateTime'),
					component: ElDatePicker,
					placeholder: '', // 日期选择器无需默认占位符（用start/endPlaceholder）
					props: {
						type: 'datetimerange', // 原类型
						valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式
						startPlaceholder: t('startDate'), // 原开始占位符
						endPlaceholder: t('endDate'), // 原结束占位符
						clearable: true // 可选：增加清空按钮（体验优化）
					}
				},
			];
		}};

	// 导出相关
	const exportSureDialog = ref(null);
	const flag = ref(false);
	const export_type = ref('home_service_store_settlement');
	const handleClose = (val) => {
		flag.value = val;
	};
	const exportEvent = (data : any) => {
		flag.value = true;
	};


	// 加载结算列表数据
	const loadSettlementList = (page : number = 1) => {
		settlementTableData.loading = true;
		settlementTableData.page = page;
		const url = activeName.value == 1 ? getStoreFinance : getStoreListFinanceStat
		url({
			page: settlementTableData.page,
			limit: settlementTableData.limit,
			...settlementTableData.searchParam
		}).then(res => {
			settlementTableData.loading = false
			settlementTableData.data = res.data.data
			settlementTableData.total = res.data.total
			setTablePageStorage(settlementTableData.page, settlementTableData.limit, settlementTableData.searchParam)
		}).catch(() => {
			settlementTableData.loading = false
		})
	};

	// 初始化加载列表
	loadSettlementList(getTablePageStorage(settlementTableData.searchParam).page);

	// Tabs切换事件
	const changeTab = (e : any) => {
		console.log('切换Tabs：', e);
		loadSettlementList();
	};

	// 详情按钮事件
	const detailEvent = (row : any) => {
		router.push(`/home_service/finance/store_detail?id=${row.store_id}&date=${row.month}`);
	};

	// 导出文件按钮事件
	const exportFileEvent = (row : any) => {
		ElMessage.info(`${t('exportingFile')}：${row.technicianName}`);
	};

	// 顶部"师傅名称"按钮事件
	const handleTechnicianNameBtn = () => {
		ElMessage.info(t('technicianNameBtnTip'));
	};

	// 顶部"结算统计"按钮事件
	const handleSettlementStatsBtn = () => {
		ElMessage.info(t('settlementStatsBtnTip'));
	};
</script>

<style lang="scss" scoped></style>