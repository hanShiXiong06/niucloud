<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ t('pageName') }}</span>
				<!-- <el-button type="primary" @click="handleExport">{{ t('export') }}</el-button> -->
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="detailTableData.searchParam"
					@search="loadDetailList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>

			<!-- 结算详情表格 -->
			<div class="mt-[10px]">
				<el-table :data="detailTableData.data" size="large" v-loading="detailTableData.loading" row-key="id">
					<template #empty>
						<span>{{ !detailTableData.loading ? t('emptyData') : '' }}</span>
					</template>
					<!-- 订单编号列 -->
					<el-table-column prop="order_no" :label="t('orderNo')" min-width="150"
						:show-overflow-tooltip="true" />
					<!-- 结算时间列 -->
					<el-table-column prop="payment_time" :label="t('settlementTime')" min-width="180"
						:show-overflow-tooltip="true" />
					<!-- 结算金额列 -->
					<el-table-column prop="account_sum" :label="t('settlementAmount')" min-width="120"
						align="center">
						<template #default="{ row }">
							<span class="text-[12px]">￥</span>
							<span class="font-bold">{{ row.account_data }}</span>
						</template>
					</el-table-column>
					<!-- 结算状态列 -->
					<el-table-column prop="settlementStatus" :label="t('settlementStatus')" min-width="120"
						align="center">
						<template #default="{ row }">
							<el-tag :type="row.status == '1' ? 'success' : 'warning'" effect="dark">
								{{row.status_name}}
							</el-tag>
						</template>
					</el-table-column>
					<!-- 操作列 -->
					<!-- <el-table-column :label="t('operation')" fixed="right" align="right" width="180">
						<template #default="{ row }">
							<el-button type="primary" link
								@click="handleExportDetail(row)">{{ t('exportDetail') }}</el-button>
							<el-button type="primary" link
								@click="handleViewDetail(row)">{{ t('viewDetail') }}</el-button>
						</template>
					</el-table-column> -->
				</el-table>

				<!-- 分页 -->
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="detailTableData.page" v-model:page-size="detailTableData.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="detailTableData.total"
						@size-change="loadDetailList()" @current-change="loadDetailList" />
				</div>
			</div>
		</el-card>

		<!-- 导出确认弹窗（复用项目已有组件） -->
		<export-sure ref="exportSureDialog" :show="flag" :type="export_type" :searchParam="detailTableData.searchParam"
			@close="handleClose" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, nextTick } from 'vue';
	import { t } from '@/lang';
	import { ElMessage, FormInstance } from 'element-plus';
	import { useRouter, useRoute } from 'vue-router';
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { getStoreListFinanceDetail } from '@/addon/home_service/api/finance'
	// 路由相关：从路由参数获取师傅/结算ID（示例，需根据实际路由配置调整）
	const route = useRoute();
	const router = useRouter();
	const settlementId = route.query.id; // 假设路由为 /technician_detail/:id
	const month = route.query.date; 
	// 根据当前激活的Tab获取对应的筛选表单配置
	// 核心：定义表单元素的配置
	const formFields = [
		{
			prop: 'order_no', // 对应searchParam的key
			label: t('orderNo'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('enterOrderNo'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
	];
	// 已结算Tab的筛选表单（新表单）
	// 表格数据：师傅结算详情列表
	const detailTableData = reactive({
		page: 1,
		limit: 10,
		total: 100, // 假数据总数，实际需接口返回
		loading: false,
		data: [],
		searchParam: {
			store_id:settlementId,
			month:month,
			order_no: '', // 订单编号筛选
			// 可扩展时间范围等其他筛选参数
		},
	});
	const handleFormReset = () => {
		settlementTableData.page = 1;
		loadDetailList();
	};
	// 导出相关：复用项目导出确认逻辑
	const exportSureDialog = ref(null);
	const flag = ref(false);
	const export_type = ref('settlementDetail'); // 标记导出类型为“结算详情”
	const handleClose = (val : boolean) => {
		flag.value = val;
	};
	const handleExport = (data:any) => {
		flag.value = true;
		// 若需携带筛选参数导出，可在 export-sure 组件中处理
	};

	// 生成假数据：模拟结算详情列表
	const generateFakeDetailData = () => {
		const fakeData = [];
		const baseOrderNo = '1102508280019'; // 原型图订单编号
		const timePrefix = '2025-';
		const dateOptions = ['6-30 12:08:08', '7-30 12:08:08'];
		const amountOptions = [5000, 4003];
		for (let i = 0; i < 10; i++) {
			const randomDate = dateOptions[Math.floor(Math.random() * dateOptions.length)];
			const randomAmount = amountOptions[Math.floor(Math.random() * amountOptions.length)];
			fakeData.push({
				id: `detail_${i}`,
				orderNo: baseOrderNo, // 固定订单编号（原型图样式）
				settlementTime: `${timePrefix}${randomDate}`,
				settlementAmount: randomAmount,
				settlementStatus: 'settled', // 模拟“已结算”状态
			});
		}
		return fakeData;
	};

	// 加载结算详情列表
	const loadDetailList = (page : number = 1) => {
		console.log(settlementId)
		detailTableData.loading = true;
		detailTableData.page = page;
		getStoreListFinanceDetail({
			page: detailTableData.page,
			limit: detailTableData.limit,
			store_id:settlementId,
			...detailTableData.searchParam
		}).then(res => {
			detailTableData.loading = false
			detailTableData.data = res.data.data
			detailTableData.total = res.data.total
			setTablePageStorage(detailTableData.page, detailTableData.limit, detailTableData.searchParam)
		}).catch(() => {
			detailTableData.loading = false
		})
	};

	// 初始化加载列表
	loadDetailList();

	// 订单编号查询按钮事件
	const handleSearch = () => {
		loadDetailList(1); // 重置页码并重新加载
	};

	// 操作列：导出明细按钮事件
	const handleExportDetail = (row : any) => {
		handleExport(row)
		// 后续可对接“导出单条明细”的接口逻辑
	};

	// 操作列：查看明细按钮事件
	const handleViewDetail = (row : any) => {
		ElMessage.info(`${t('viewingDetail')}：订单${row.orderNo}`);
		// 后续可跳转至更细粒度的订单明细页面（如 /order_detail/:orderNo）
	};
</script>

<style lang="scss" scoped></style>