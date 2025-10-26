<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center mb-[16px]">
				<span class="text-page-title">{{ t('withdrawalList') }}</span>
			</div>
			<el-tabs v-model="storeTechnicanIndex" class="demo-tabs" @tab-change="changeTabs" type="border-card">
				
				<el-tab-pane :label="items.name" :name="items.type" v-for="(items, indexs) in storeTechnicanList" :key="indexs">
					<el-card class="box-card !border-none table-search-wrap" shadow="never">
						<DynamicCollapseForm :fields="InvoiceFormFields" :search-param="withdrawalTableData.searchParam"
							@search="loadWithdrawalList" @reset="handleFormReset" ref="collapseFormRef" />
					</el-card>
					<el-tabs v-model="activeName" class="demo-tabs" @tab-change="changeTab">
						<el-tab-pane :label="item.name" :name="item.type" v-for="(item, index) in typeList"
							:key="index">
							<!-- 提现列表表格 -->
							<div class="mt-[10px]">
								<el-table :data="withdrawalTableData.data" size="large"
									v-loading="withdrawalTableData.loading" border stripe row-key="id">
									<template #empty>
										<span>{{ !withdrawalTableData.loading ? t('emptyData') : '' }}</span>
									</template>

									<!-- id列 -->
									<el-table-column prop="id" :label="t('id')" min-width="60" align="center" />

									<el-table-column prop="related_name" :label="storeTechnicanIndex==1?t('applicant'):t('institutions')" min-width="150"
										:show-overflow-tooltip="true" />
									
									<el-table-column prop="account_type_name" :label="t('type')" min-width="100"
										:show-overflow-tooltip="true" />
										

									<!-- 开户行列 -->
									<el-table-column prop="transfer_type_name" :label="t('bankName')" min-width="120"
										:show-overflow-tooltip="true" />

									<!-- 银行卡号列 -->
									<el-table-column prop="cash_out_no" :label="t('bankCardNo')" min-width="180"
										:show-overflow-tooltip="true" />
										
									
									<el-table-column prop="money" :label="t('aoolyMoney')"
										min-width="100" align="center">
										<template #default="{ row }">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{ row.apply_money }}</span>
										</template>
									</el-table-column>
									
									
									<el-table-column prop="money" :label="t('serfviceMOney')"
										min-width="100" align="center">
										<template #default="{ row }">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{ row.service_money }}</span>
										</template>
									</el-table-column>
									<!-- 提现金额列 -->
									<el-table-column prop="money" :label="t('withdrawalAmount')"
										min-width="100" align="center">
										<template #default="{ row }">
											<span class="text-[12px]">￥</span>
											<span class="font-bold">{{ row.money }}</span>
										</template>
									</el-table-column>

									<!-- 状态列 -->
									<el-table-column prop="status" :label="t('status')" min-width="100" align="center">
										<template #default="{ row }">
											<el-tag :type="row.status == '1' ? 'warning' : 'success'" effect="dark">
												{{ row.status_name }}
											</el-tag>
										</template>
									</el-table-column>

									<!-- 申请时间列 -->
									<el-table-column prop="create_time" :label="t('applyTime')" min-width="180"
										:show-overflow-tooltip="true" />

									<!-- 处理人列 -->
								<!-- 	<el-table-column prop="related_name" :label="t('handler')" min-width="100"
										:show-overflow-tooltip="true" /> -->

									<!-- 处理时间列 -->
								<!-- 	<el-table-column prop="handleTime" :label="t('handleTime')" min-width="180"
										:show-overflow-tooltip="true" /> -->

									<!-- 操作列 -->
									<el-table-column :label="t('operation')" fixed="right" align="right" width="160">
										<template #default="{ row }">
											<el-button v-if="row.status == 1" type="primary" link
												@click="handleTransfer(row)">{{ t('transfer') }}</el-button>
											<el-button type="primary" link
												@click="handleView(row)">{{ t('view') }}</el-button>
										</template>
									</el-table-column>
								</el-table>

								<!-- 分页组件 -->
								<div class="mt-[16px] flex justify-end">
									<el-pagination v-model:current-page="withdrawalTableData.page"
										v-model:page-size="withdrawalTableData.limit"
										layout="total, sizes, prev, pager, next, jumper"
										:total="withdrawalTableData.total" @size-change="loadWithdrawalList()"
										@current-change="loadWithdrawalList" />
								</div>
							</div>
						</el-tab-pane>
					</el-tabs>
				</el-tab-pane>
			</el-tabs>
		</el-card>
		<BankTransferDialog ref="transferDialogRef" @transfer-success="handleTransferSuccess" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, nextTick, computed } from 'vue';
	import { t } from '@/lang'; // 假设项目有i18n语言包
	import { ElMessage } from 'element-plus';
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common';
	import { useRouter, useRoute } from 'vue-router';
	import BankTransferDialog from '@/addon/home_service/views/finance/components/BankTransferDialog.vue';
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { getCashOutStatus,getCashOutList,setCashOutTransfer } from '@/addon/home_service/api/finance'
	const transferDialogRef = ref();
	const activeName = ref('');
	// 路由相关（若需跳转详情页，可通过路由传参）
	const route = useRoute();
	const router = useRouter();
	const storeTechnicanIndex = ref(1)
	const storeTechnicanList = ref([
		{
			name: '师傅提现',
			type: 1,
		},
		{
			name: '机构提现',
			type: 2,
		},
	])
	const typeList = ref([]);
	const getCashOutStatusFn = () =>{
		getCashOutStatus().then((res)=>{
			Object.keys(res.data).forEach((item,index)=>{
				let obj ={
					name:res.data[item],
					type:item
				}
				typeList.value.push(obj)
			})
			typeList.value.unshift({name:'全部',type:''})
		})
	}
	getCashOutStatusFn()
	// 提现列表数据管理
	const withdrawalTableData = reactive({
		page: 1,
		limit: 10,
		total: 3, // 假数据总数，实际由接口返回
		loading: false,
		data: [],
		searchParam: {
			// 可扩展筛选参数（如申请人、状态等）
		},
	});
	const handleFormReset = () => {
		withdrawalTableData.page = 1;
		loadWithdrawalList();
	};
	const InvoiceFormFields = computed(() => [
		{
			prop: 'keywords',
			label:storeTechnicanIndex.value==1? t('name'):t('institutions'),
			component: 'ElInput',
			placeholder: storeTechnicanIndex.value==1?t('namePlaceholder'):t('institutionsPlaceholder'),
			props: {
				class: 'input-width !w-[214px]'
			},
		},
	]);


	// 加载提现列表（模拟接口请求）
	const loadWithdrawalList = (page : number = 1) => {
		withdrawalTableData.loading = true;
		withdrawalTableData.page = page;
		getCashOutList({
			page: withdrawalTableData.page,
			limit: withdrawalTableData.limit,
			source : storeTechnicanIndex.value != 1? 'store' : '',
			status:activeName.value,
			...withdrawalTableData.searchParam
		}).then(res => {
			withdrawalTableData.loading = false
			withdrawalTableData.data = res.data.data
			withdrawalTableData.total = res.data.total
			setTablePageStorage(withdrawalTableData.page, withdrawalTableData.limit, withdrawalTableData.searchParam)
		}).catch(() => {
			withdrawalTableData.loading = false
		})
	};
	const changeTab = (e : any) => {
		console.log(activeName.value)
		loadWithdrawalList();
	};
	const changeTabs = (e:any) =>{
		storeTechnicanIndex.value = e
		loadWithdrawalList();
	}
	// 初始化加载列表
	loadWithdrawalList();

	// 操作：转账（“待处理”状态时触发）
	const handleTransfer = (row : any) => {
		transferDialogRef.value.openDialog(row, 'transfer');
	};

	// 操作：查看（“已提现”状态时触发）
	const handleView = (row : any) => {
		transferDialogRef.value.openDialog(row, 'view');
	};

	// 处理转账成功事件，刷新表格数据
	const handleTransferSuccess = () => {
		loadWithdrawalList();
	};
</script>

<style lang="scss" scoped></style>