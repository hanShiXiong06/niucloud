<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>
			
			<!-- 搜索表单 -->
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm 
					:fields="orderFormFields" 
					:search-param="orderTable.searchParam"
					@search="loadOrderList" 
					@reset="handleFormReset"
					ref="collapseFormRef" 
				/>
			</el-card>

			<!-- 状态标签栏 -->
			<el-tabs 
				v-model="orderTable.searchParam.follow_status" 
				class="demo-tabs" 
				@tab-change="handleClick"
			>
				<!-- 其他状态标签 -->
				<el-tab-pane 
					:label="item.label" 
					:name="item.value" 
					v-for="(item, key) in orderStatus" 
					:key="key"
				></el-tab-pane>
			</el-tabs>
			
			<!-- 订单列表 -->
			<div class="mt-[10px]">
				<div class="table-body min-h-[150px]" v-loading="orderTable.loading">
					<el-table 
						v-if="!orderTable.loading" 
						:data="orderTable.data" 
						size="large" 
						border
						@selection-change="handleSelectionChange"
					>
						<template #empty>
							<span>{{ t("emptyData") }}</span>
						</template>
						<el-table-column type="selection" width="45" />
						<el-table-column prop="order_no" :label="t('orderNo')" min-width="220" />
						<el-table-column :label="t('serviceType')" min-width="120">
							<template #default="{ row }">
								<span v-if="row.goodsCategory">{{ row.goodsCategory.category_name }}</span>
							</template>
						</el-table-column>
						<el-table-column :label="t('servicesGoods')" min-width="200">
							<template #default="{ row }">
								<span>{{ row.order_name }}</span>
							</template>
						</el-table-column>
						<el-table-column :label="t('followStatus')" min-width="100" align="center">
							<template #default="{ row }">
								<el-tag :type="row.follow_id > 0 ? 'success' : 'danger'">{{ row.follow_status_name }}</el-tag>
							</template>
						</el-table-column>
						<el-table-column :label="t('orderAllMOney')" min-width="120" align="center">
							<template #default="{ row }">
								<div class="text-[#ff0000]">
									<span class="text-[12px]">￥</span>
									<span class="font-bold">{{ row.order_money }}</span>
								</div>
							</template>
						</el-table-column>
						<el-table-column :label="t('memberName')" min-width="200">
							<template #default="{ row }">
								<span class="flex-1 multi-hidden">{{ row.taker_name }}</span>
							</template>
						</el-table-column>
						<el-table-column :label="t('mobile')" min-width="120">
							<template #default="{ row }">
								<span>{{ row.taker_mobile }}</span>
							</template>
						</el-table-column>
						<el-table-column :label="t('orderAddress')" min-width="250">
							<template #default="{ row }">
								<span class="flex-1 multi-hidden">{{ row.taker_full_address || '--' }} </span>
							</template>
						</el-table-column>
						<el-table-column :label="t('technicianName')" min-width="120">
							<template #default="{ row }">
								<span v-if="row.technician">{{ row.technician.real_name }}</span>
							</template>
						</el-table-column>
						<el-table-column :label="t('store')" min-width="120">
							<template #default="{ row }">
								<span v-if="row.store">{{ row.store.store_name }}</span>
							</template>
						</el-table-column>
						<el-table-column prop="create_time" :label="t('createTime')" min-width="200" align="center" />
						<el-table-column :label="t('yuyueTIme')" min-width="200" align="center">
							<template #default="{ row }">
								{{ row.reserve_service_time || '' }}
							</template>
						</el-table-column>
						<el-table-column :label="t('serviceTime')" min-width="200" align="center">
							<template #default="{ row }">
								{{ row.service_time || '' }}
							</template>
						</el-table-column>
						<el-table-column :label="t('finishTime')" min-width="200" align="center">
							<template #default="{ row }">
								{{ row.finish_time || '' }}
							</template>
						</el-table-column>
						<el-table-column :label="t('serviceHours')" min-width="180" align="center">
							<template #default="{ row }">
								<div v-if="row.time_reminder" class="text-[13px]">
									{{ row.time_reminder.text }}
								</div>
							</template>
						</el-table-column>
						<el-table-column :label="t('operation')" fixed="right" align="center" min-width="200">
							<template #default="{ row }">
								<div class="relative z-index-999">
									<el-button type="primary" v-if="row.follow_id != 0" link @click="detailEvent(row)">{{ t('orderDetail') }}</el-button>
									<el-button 
										type="primary" 
										link 
										class="!text-[#ff0000]" 
										v-if="row.follow_id == 0"
										@click="openDialog(row)"
									>{{ t('follow') }}</el-button>
								</div>
							</template>
						</el-table-column>
					</el-table>
				</div>
				
				<!-- 分页控件 -->
				<div class="mt-[16px] flex justify-end">
					<el-pagination 
						v-model:current-page="orderTable.page" 
						v-model:page-size="orderTable.limit"
						layout="total, sizes, prev, pager, next, jumper" 
						:total="orderTable.total"
						@size-change="loadOrderList" 
						@current-change="loadOrderList" 
					/>
				</div>
			</div>
		</el-card>
		
		<!-- 回访对话框 -->
		<returnvisit 
			v-model:visible="dialogVisible" 
			:initial-data="dialogInitialData" 
			@confirm="handleDialogConfirm"
			@cancel="handleDialogCancel"
		></returnvisit>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed, onMounted } from 'vue'
	import { t } from '@/lang'
	import { getOrderfollow, getOrderfollowStatus, setorderfollow } from '@/addon/home_service/api/order'
	import { setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { FormInstance, ElMessage } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import returnvisit from '@/addon/home_service/views/order/components/returnvisit.vue';
	import { AnyObject } from '@/types/global'

	// 全部标签数据（包含总数量）
	const allStatus = reactive({
		label: t('all'),
		value: '',
		count: 0
	});

	// 订单表格数据
	const orderTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			order_no: '',
			member_search: '',
			order_from: '',
			join_create_time: '',
			technician_name: '',
			is_settlement: '',
			label_id: [],
			store_name: [],
			pay_time: '',
			follow_status: ''
		}
	})

	// 订单状态列表
	const orderStatus = ref([])
	
	// 回访对话框相关
	const dialogVisible = ref(false);
	const dialogInitialData = reactive({
		order_id: ''
	});

	// 路由相关
	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title

	// 选中的订单列表
	const selectedOrderList = ref([]);

	// 搜索表单字段配置
	const orderFormFields = computed(() => [
		{
			prop: 'order_no',
			label: t('orderInfo'),
			component: ElInput,
			placeholder: t('orderInfoPlaceholder'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
		{
			prop: 'member_search',
			label: t('memberSearchText'),
			component: ElInput,
			placeholder: t('memberSearchTextPlaceholder'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
		{
			prop: 'join_create_time',
			label: t('createTime'),
			component: ElDatePicker,
			placeholder: '',
			props: {
				type: 'datetimerange',
				valueFormat: 'YYYY-MM-DD HH:mm:ss',
				startPlaceholder: t('startDate'),
				endPlaceholder: t('endDate'),
				clearable: true
			}
		},
		{
			prop: 'technician_name',
			label: t('technicianSearchText'),
			component: ElInput,
			placeholder: t('technicianSearchTextPlaceholder'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
		{
			prop: 'store_name',
			label: t('storeName'),
			component: ElInput,
			placeholder: t('storeNamePlaceholder'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
		{
			prop: 'order_from',
			label: t('orderSource'),
			component: ElSelect,
			placeholder: t('orderSourcePlaceholder'),
			props: {
				filterable: true,
				options: []
			}
		},
		{
			prop: 'pay_time',
			label: t('payTime'),
			component: ElDatePicker,
			placeholder: '',
			props: {
				type: 'datetimerange',
				valueFormat: 'YYYY-MM-DD HH:mm:ss',
				startPlaceholder: t('startDate'),
				endPlaceholder: t('endDate'),
				clearable: true
			}
		}
	]);

	/**
	 * 处理标签中数字的样式（变为蓝色）
	 * 用Promise.resolve确保DOM更新后执行
	 */
	const styleTabCounts = () => {
		Promise.resolve().then(() => {
			const tabItems = document.querySelectorAll('.el-tabs__item');
			tabItems.forEach(tab => {
				const tabElement = tab as HTMLElement;
				tabElement.innerHTML = tabElement.innerHTML.replace(
					/(\(\d+\))/, 
					'<span class="tab-count">$1</span>'
				);
			});
		});
	};

	/**
	 * 获取订单列表
	 */
	const loadOrderList = (page: number = 1) => {
		orderTable.loading = true;
		orderTable.page = page;

		getOrderfollow({
			page: orderTable.page,
			limit: orderTable.limit,
			...orderTable.searchParam
		}).then((res) => {
			console.log('接口返回total:', res.data.total); // 调试：打印总数量
			orderTable.loading = false;
			orderTable.total = res.data.total;
			orderTable.data = res.data.data;
			allStatus.count = res.data.total; // 关键：赋值总数量给“全部”标签
			console.log('allStatus.count赋值后:', allStatus.count); // 调试：打印赋值结果
			setTablePageStorage(orderTable.page, orderTable.limit, orderTable.searchParam);
			styleTabCounts(); // 数据更新后处理样式
		}).catch(() => {
			orderTable.loading = false;
		});
	};

	/**
	 * 获取订单状态列表
	 */
	const checkOrderStatus = () => {
		getOrderfollowStatus().then((res) => {
			let int = 0
			Object.keys(res.data.status_list).forEach((item) => {
				orderStatus.value.push({
					label: res.data.status_list[item].name + `(${res.data.status_list[item].count})`,
					value: res.data.status_list[item].status,
					name: res.data.status_list[item].name
				});
				int += Number(res.data.status_list[item].count)
			});
			orderStatus.value.unshift({
				label:`全部(${int})`,
				value:'',
				name:'全部'
			})
			styleTabCounts(); // 状态列表加载后处理样式
		});
	};

	/**
	 * 表格选中变化
	 */
	const handleSelectionChange = (selectedRows: any[]) => {
		selectedOrderList.value = selectedRows;
	};

	/**
	 * 重置表单
	 */
	const handleFormReset = () => {
		orderTable.page = 1;
		loadOrderList();
	};

	/**
	 * 切换标签页
	 */
	const handleClick = () => {
		loadOrderList();
	};

	/**
	 * 打开详情页
	 */
	const detailEvent = (info: AnyObject) => {
		router.push(`/home_service/order/detail?order_id=${info.order_id}&status=follow`);
	};

	/**
	 * 打开回访对话框
	 */
	const openDialog = (data: any) => {
		dialogInitialData.order_id = data.order_id;
		dialogVisible.value = true;
	};

	/**
	 * 确认回访
	 */
	const handleDialogConfirm = (formData) => {
		setorderfollow(formData).then(() => {
			dialogVisible.value = false;
			loadOrderList();
		});
	};

	/**
	 * 取消回访
	 */
	const handleDialogCancel = () => {
		dialogVisible.value = false;
	};

	// 初始化加载
	onMounted(() => {
		checkOrderStatus();
		loadOrderList(getTablePageStorage(orderTable.searchParam).page);
	});
</script>

<style lang="scss" scoped>
	/* 固定列层级 */
	.el-table__fixed-right {
		z-index: 10 !important;
	}

	/* 标签中数字样式（蓝色，强制优先级） */
	.tab-count {
		color: #409eff !important; /* Element Plus 主题蓝，确保覆盖默认样式 */
		font-weight: 500;
	}

	/* 样式穿透，作用到标签内部 */
	:deep(.el-tabs__item) {
		.tab-count {
			color: #409eff !important;
		}
	}
</style>

