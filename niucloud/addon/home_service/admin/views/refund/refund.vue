<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never" v-loading="RefundTableData.loading">

			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>

			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="RefundFormFields" :search-param="RefundTableData.searchParam"
					@search="loadRefundList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>

			<el-tabs v-model="activeName" class="demo-tabs" @tab-change="changeTab">
				<el-tab-pane :label="item.label" :name="item.value" v-for="(item,index) in typeList" :key="index">
					<div class="mt-[10px]">
						<el-table :data="RefundTableData.data" size="large" v-loading="RefundTableData.loading"
							@selection-change="handleSelectionChange">
							<template #empty>
								<span>{{ !RefundTableData.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column prop="orderMain.order_no" :label="t('refundOrderNo')" min-width="220" />

							<el-table-column :label="t('refundServiceItem')" min-width="200">
								<template #default="{ row }">
									<span>{{ row.orderMain?.order_name }}</span>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundStatus')" min-width="100" align="center">
								<template #default="{ row }">
									<el-tag
										:type="{'pending': 'warning', 'refund_completed': 'success', 'refund_refuse': 'danger'}[row.status] || 'info'">
										{{ row.status_name || t(`refundStatus${row.refund_status.charAt(0).toUpperCase() + row.refund_status.slice(1)}`) }}
									</el-tag>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundServiceFee')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.orderMain?.order_money }}</span>
									</div>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundAmount')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.money }}</span>
									</div>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundApplicant')" min-width="150">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden">{{ row.member?.nickname }}</span>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundPhone')" min-width="120">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden">{{ row.member?.mobile }}</span>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundTechnician')" min-width="150">
								<template #default="{ row }">
									<span v-if="row.technician">{{ row.technician.real_name }}</span>
									<span v-else>--</span>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundStore')" min-width="150">
								<template #default="{ row }">
									<span v-if="row.store">{{ row.store.store_name }}</span>
									<span v-else>--</span>
								</template>
							</el-table-column>

							<el-table-column prop="create_time" :label="t('refundApplyTime')" min-width="200"
								align="center" />

							<el-table-column prop="process_time" :label="t('refundProcessTime')" min-width="200"
								align="center">
								<template #default="{ row }">
									{{ row.audit_time || '--' }}
								</template>
							</el-table-column>

							<el-table-column :label="t('refundRemark')" min-width="200">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden">{{ row.remark || '--' }}</span>
								</template>
							</el-table-column>

							<el-table-column :label="t('refundTotalAmount')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{ row.orderMain?.order_money }}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column label="操作" width="200" fixed="right">
								<template #default="scope">
									<template v-if="scope.row.status == 'wait_refund'">
										<el-button type="success" size="small" @click="handlePass(scope.row)">通过</el-button>
										<el-button type="danger" size="small"
											@click="handleReject(scope.row)">拒绝</el-button>
									</template>
									<el-button type="primary" link
										@click="detailEvent(scope.row)">{{ t('info') }}</el-button>
								</template>
							</el-table-column>
						</el-table>

						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="RefundTableData.page"
								v-model:page-size="RefundTableData.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="RefundTableData.total"
								@size-change="loadRefundList()" @current-change="loadRefundList" />
						</div>
					</div>
				</el-tab-pane>
			</el-tabs>
		</el-card>

		<el-dialog title="确认退款" v-model="passDialogVisible" width="30%">
			<el-form :model="refundForm" :rules="refundRules" ref="refundFormRef">
				<el-form-item label="退款金额" prop="money">
					<el-input v-model="refundForm.money" placeholder="请输入退款金额" class="input-width !w-[214px]"></el-input>
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="passDialogVisible = false">取消</el-button>
				<el-button type="primary" @click="confirmPass">确认</el-button>
			</template>
		</el-dialog>

		<!-- 拒绝退款的弹窗 -->
		<el-dialog title="拒绝退款" v-model="rejectDialogVisible" width="30%">
			<el-form :model="rejectForm" :rules="rejectRules" ref="rejectFormRef">
				<el-form-item label="拒绝理由" prop="refuse_reason">
					<el-input type="textarea" v-model="rejectForm.refuse_reason" class="input-width !w-[214px]" placeholder="请输入拒绝理由"></el-input>
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="rejectDialogVisible = false">取消</el-button>
				<el-button type="primary" @click="confirmReject">确认</el-button>
			</template>
		</el-dialog>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed } from 'vue'
	import { t } from '@/lang'
	import { getRefundList, getRefundType, setRefundadoptStatus, setRefundSuccessStatus } from '@/addon/home_service/api/refund'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { ElMessageBox, FormInstance } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const pageName = route.meta.title
	const activeName = ref('')
	const typeList = ref([])
	const passDialogVisible = ref(false)
	const rejectDialogVisible = ref(false)
	// 当前操作的行数据
	const currentRow = ref(null)

	// 退款表单数据
	const refundForm = reactive({
		money: ''
	})
	// 拒绝表单数据
	const rejectForm = reactive({
		refuse_reason: ''
	})
	// 表单验证规则
	const refundRules = {
		money: [
			{ required: true, message: '请输入退款金额', trigger: 'blur' }
		]
	}

	const rejectRules = {
		refuse_reason: [
			{ required: true, message: '请输入拒绝理由', trigger: 'blur' }
		]
	}
	/**
	 * 打开详情页
	 */
	const detailEvent = (data: any) => {
		router.push(`/home_service/order/detail?order_id=${data.order_id}&status=refund`);
	};
	// 表单引用
	const refundFormRef = ref(null)
	const rejectFormRef = ref(null)

	// 处理通过操作
	const handlePass = (row) => {
		currentRow.value = row
		refundForm.money = row.apply_money // 默认填充申请金额
		passDialogVisible.value = true
	}

	// 确认通过
	const confirmPass = () => {
		refundFormRef.value.validate((valid) => {
			if (valid) {
				// 调用通过接口
				setRefundSuccessStatus({
					...currentRow.value,
					money: refundForm.money
				}).then(() => {
					passDialogVisible.value = false
					refundFormRef.value.resetFields()
					loadRefundList()
				})
			}
		})
	}

	// 处理拒绝操作
	const handleReject = (row) => {
		currentRow.value = row
		rejectDialogVisible.value = true
	}

	// 确认拒绝
	const confirmReject = () => {
		rejectFormRef.value.validate((valid) => {
			if (valid) {
				// 调用拒绝接口
				setRefundadoptStatus({
					...currentRow.value,
					refuse_reason: rejectForm.refuse_reason
				}).then(() => {
					rejectDialogVisible.value = false
					rejectFormRef.value.resetFields()
					loadRefundList()
				})
			}
		})
	}
	const RefundTableData = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			join_status: activeName.value,
			order_no: '',
			member_search: '',
			join_create_time: '',
			technician_name: '',
			store_name: '',
		}
	})

	const searchFormRef = ref<FormInstance>()

	// 获取订单状态
	const gethelpTypeFn = () => {
		getRefundType().then((res) => {
			let int = 0
			Object.keys(res.data.status_list).forEach((item) => {
				typeList.value.push({
					label: res.data.status_list[item].name + `(${res.data.status_list[item].count})`,
					value: res.data.status_list[item].status,
					name: res.data.status_list[item].name
				});
				int += Number(res.data.status_list[item].count)
			});
			typeList.value.unshift({
				label:`全部(${int})`,
				value:'',
				name:'全部'
			})
			styleTabCounts(); // 状态列表加载后处理样式
		});
	}
	gethelpTypeFn()
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
	const handleFormReset = () => {
		RefundTableData.page = 1; // 重置页码（与原逻辑一致）
		loadRefundList(); // 重置后重新搜索（与原逻辑一致）
	};
	const RefundFormFields = computed(() => [
		{
			prop: 'order_no',
			label: t('orderNo'),
			component: ElInput,
			placeholder: t('orderNoPlaecholder'),
			props: {
				clearable: true // 显示清空按钮
			}
		},
		{
			prop: 'member_search',
			label: t('membersearch'),
			component: ElInput,
			placeholder: t('membersearchPlaceholder'),
			props: {
				clearable: true // 显示清空按钮
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
				class: '!w-[230px]' // 保留原宽度样式
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
				class: '!w-[230px]' // 保留原宽度样式
			}
		},
	]);
	/**
	 * 获取文章列表
	 */
	const loadRefundList = (page : number = 1) => {
		RefundTableData.loading = true
		RefundTableData.page = page
		getRefundList({
			page: RefundTableData.page,
			limit: RefundTableData.limit,
			...RefundTableData.searchParam,
			join_status: activeName.value
		}).then(res => {
			RefundTableData.loading = false
			RefundTableData.data = res.data.data
			RefundTableData.total = res.data.total
			setTablePageStorage(RefundTableData.page, RefundTableData.limit, RefundTableData.searchParam)
		}).catch(() => {
			RefundTableData.loading = false
		})
	}
	loadRefundList(getTablePageStorage(RefundTableData.searchParam).page)
	const changeTab = (e : any) => {
		console.log(e)
		loadRefundList()
	}
	const router = useRouter()

	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadRefundList()
	}
</script>

<style lang="scss" scoped>
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