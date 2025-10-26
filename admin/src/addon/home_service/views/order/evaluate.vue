<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="evaluateFormFields" :search-param="evaluateTable.searchParam"
					@search="loadEvaluateList" @exportSelectEvent="exportSelectEvent" @reset="handleFormReset"
					ref="collapseFormRef" />
			</el-card>

			<el-tabs v-model="evaluateTable.searchParam.is_audit" class="demo-tabs" @tab-change="handleClick">
				<el-tab-pane :label="item.label" :name="item.value" v-for="(item, key) in orderStatus"
					:key="key"></el-tab-pane>
			</el-tabs>
			<div class="mt-[10px]">
				<div class="table-body min-h-[150px]" v-loading="evaluateTable.loading">
					<div v-if="!evaluateTable.loading">
						<el-table :data="evaluateTable.data" size="large" v-loading="evaluateTable.loading" border
							@selection-change="handleSelectionChange">
							<template #empty>
								<span>{{ !evaluateTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column prop="evaluate_id" :label="t('evaluateId')" min-width="100" />
							<el-table-column :label="t('orderNo')" min-width="220">
								<template #default="{ row }">
									<span>{{ row.order?.order_no }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('orderName')" min-width="200">
								<template #default="{ row }">
									<span>{{ row.order?.order_name }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('memberInfo')" min-width="150">
								<template #default="{ row }">
									<span>{{ row.member?.nickname }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('technicianName')" min-width="150">
								<template #default="{ row }">
									<span>{{ row.technician?.real_name }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('storeName')" min-width="150">
								<template #default="{ row }">
									<span>{{ row.store?.store_name || '--' }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('score')" min-width="200" align="center">
								<template #default="{ row }">
									<el-rate v-model="row.scores" disabled :max="5" />
								</template>
							</el-table-column>
							<el-table-column :label="t('evaluateContent')" min-width="200">
								<template #default="{ row }">
									<span class="multi-hidden">{{ row.content }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('isAnonymous')" min-width="100" align="center">
								<template #default="{ row }">
									<el-tag :type="row.is_anonymous === 1 ? 'success' : 'info'">
										{{ row.is_anonymous === 1 ? t('anonymous') : t('notAnonymous') }}
									</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('evaluateImages')" min-width="250">
								<template #default="{ row }">
									<div class="image-list">
										<el-image v-for="(item, index) in row.images" :key="index" :src="img(item)"  v-if="row.images.length > 0"
											class="evaluate-image" :preview-src-list="[img(item)]">
										</el-image>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('createTime')" prop="create_time" min-width="200"
								align="center" />
							<el-table-column :label="t('auditStatus')" min-width="120" align="center">
								<template #default="{ row }">
									<el-tag
										:type="{'1': 'warning', '2': 'success', '3': 'danger'}[row.is_audit] || 'info'">
										{{ row.audit_name || t('unAudited') }}
									</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" align="center" min-width="200">
								<template #default="{ row }">
									<div class="relative z-index-999">
										<el-button type="primary" link @click="passEvent(row)"
											v-if="row.is_audit == '1'">{{ t('pass') }}</el-button>
										<el-button type="warning" link @click="rejectEvent(row)"
											v-if="row.is_audit == '1'">{{ t('reject') }}</el-button>
										<el-button type="danger" link
											@click="deleteEvent(row.evaluate_id)">{{ t('delete') }}</el-button>
										<el-button type="primary" link
											@click="detailEvent(row)">详情</el-button>
									</div>
								</template>
							</el-table-column>
						</el-table>
					</div>
				</div>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="evaluateTable.page" v-model:page-size="evaluateTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="evaluateTable.total"
						@size-change="loadEvaluateList()" @current-change="loadEvaluateList" />
				</div>
			</div>
		</el-card>
		<el-dialog :title="t('rejectReason')" v-model="refuseDialogVisible" width="400px"
			:before-close="handleCloseRefuseDialog">
			<el-form>
				<el-form-item :label="t('reason')" required>
					<el-input type="textarea" v-model="refuseReason" :placeholder="t('pleaseEnterRejectReason')"
						rows="4" />
				</el-form-item>
			</el-form>
			<template #footer>
				<el-button @click="refuseDialogVisible = false">{{ t('cancel') }}</el-button>
				<el-button type="primary" @click="confirmRefuse">{{ t('confirm') }}</el-button>
			</template>
		</el-dialog>
		
		
		<el-dialog title="拒绝" v-model="rejectDialogVisible" width="30%">
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
		<export-sure ref="exportSureDialog" :show="flag" :type="export_type" :searchParam="evaluateTable.searchParam"
			@close="handleClose" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed } from 'vue'
	import { t } from '@/lang'
	import { getEvaluateList, getAuditStatus, setEvaluateadoptStatus, setEvaluaterefuseStatus, deleteEvaluate,getEvaluateType } from '@/addon/home_service/api/evaluate'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { FormInstance } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';

	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title

  const store_id: string = (() => {
    const rawValue = route.query.store_id;
    // 若参数不存在（undefined/null）或为空字符串，直接返回空
    if (rawValue === undefined || rawValue === null || rawValue === "") {
      return "";
    }
    // 若参数存在，转换为字符串（兼容数字型参数，确保类型统一）
    return String(rawValue);
  })();

	const rejectDialogVisible = ref(false)
	// 当前操作的行数据
	const currentRow = ref(null)
	const evaluateTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			order_no: '',
			member_search: '',
			join_create_time: '',
			technician_name: '',
			store_name: '',
			is_audit: ''
		}
	})
	// 拒绝表单数据
	const rejectForm = reactive({
		refuse_reason: ''
	})
	// 表单验证规则
	const rejectFormRef = ref(null)
	const rejectRules = {
		refuse_reason: [
			{ required: true, message: '请输入拒绝理由', trigger: 'blur' }
		]
	}
	const selectedEvaluateCount = ref(0);
	const selectedEvaluateList = ref([]);

	/**
	 * 监听表格选中变化，实时更新选中数量
	 * @param selectedRows 当前所有选中的行数组
	 */
	const handleSelectionChange = (selectedRows : any[]) => {
		selectedEvaluateCount.value = selectedRows.length;
		selectedEvaluateList.value = selectedRows
	};
	
	// 确认拒绝
	const confirmReject = () => {
		rejectFormRef.value.validate((valid) => {
			if (valid) {
				// 调用拒绝接口
				setEvaluaterefuseStatus({
					...currentRow.value,
					refuse_reason: rejectForm.refuse_reason
				}).then(() => {
					rejectDialogVisible.value = false
					rejectFormRef.value.resetFields()
					loadEvaluateList()
				})
			}
		})
	}
	// 审核状态列表
	// 获取订单状态
	const orderStatus = ref([])
	const checkOrderStatus = () => {
		getEvaluateType(store_id).then((res) => {
			let int = 0
			Object.keys(res.data.status_list).forEach((item) => {
				orderStatus.value.push({
					label: res.data.status_list[item].name + `(${res.data.status_list[item].count})`,
					value: res.data.status_list[item].is_audit,
					name: res.data.status_list[item].name,
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
	}
	checkOrderStatus()
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
	

	// 表单配置
	const evaluateFormFields = computed(() => [
		{
			prop: 'order_no',
			label: t('orderNo'),
			component: ElInput,
			placeholder: t('inputOrderNo'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
		{
			prop: 'member_search',
			label: t('memberInfo'),
			component: ElInput,
			placeholder: t('inputMemberInfo'),
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
			label: t('technicianName'),
			component: ElInput,
			placeholder: t('inputTechnicianName'),
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
			placeholder: t('inputStoreName'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		}
	]);

	if(store_id){
		evaluateTable.searchParam.store_id = store_id
	}
	/**
	 * 获取评价列表
	 */
	const loadEvaluateList = (page : number = 1) => {
		evaluateTable.loading = true
		evaluateTable.page = page

		getEvaluateList({
			page: evaluateTable.page,
			limit: evaluateTable.limit,
			...evaluateTable.searchParam
		}).then((res) => {
			evaluateTable.loading = false
			evaluateTable.total = res.data.total
			evaluateTable.data = res.data.data
			setTablePageStorage(evaluateTable.page, evaluateTable.limit, evaluateTable.searchParam)
		}).catch(() => {
			evaluateTable.loading = false
		})
	}

	const handleFormReset = () => {
		evaluateTable.page = 1;
		loadEvaluateList();
	};

	loadEvaluateList(getTablePageStorage(evaluateTable.searchParam).page)

	// 切换审核状态
	const handleClick = (event : any) => {
		console.log(event)
		evaluateTable.searchParam.is_audit = event
		selectedEvaluateCount.value = 0
		loadEvaluateList()
	}

	// 操作方法 - 单个
	const passEvent = (row) => {
		setEvaluateadoptStatus(row).then((res)=>{
			loadEvaluateList()
		})
	}

	const deleteEvent = (labelId ) => {
		ElMessageBox.confirm(
			t('deleteConfirm'),
			t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(async () => {
			await deleteEvaluate(labelId)
			loadEvaluateList()
		})
	}
	// 处理拒绝操作
	const rejectEvent = (row) => {
		// currentRow.value = row
		// rejectDialogVisible.value = true
		ElMessageBox.confirm(
			t('jvjueConfirm'),
			t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(async () => {
			console.log()
			await setEvaluaterefuseStatus(row.evaluate_id)
			loadEvaluateList()
		})
		
	}
	// 批量操作
	const batchPassEvent = () => {
		if (selectedEvaluateCount.value === 0) {
			return ElMessage.warning(t('selectAtLeastOne'))
		}
		// 批量通过逻辑
		console.log('批量通过', selectedEvaluateList.value)
		// 实际项目中调用接口后刷新列表
		// loadEvaluateList()
	}

	const batchDeleteEvent = () => {
		if (selectedEvaluateCount.value === 0) {
			return ElMessage.warning(t('selectAtLeastOne'))
		}
		// 批量删除逻辑
		console.log('批量删除', selectedEvaluateList.value)
		// 实际项目中调用接口后刷新列表
		// loadEvaluateList()
	}

	const batchRejectEvent = () => {
		if (selectedEvaluateCount.value === 0) {
			return ElMessage.warning(t('selectAtLeastOne'))
		}
		// 批量拒绝逻辑
		console.log('批量拒绝', selectedEvaluateList.value)
		// 实际项目中调用接口后刷新列表
		// loadEvaluateList()
	}

	/**
	 * 导出相关
	 */
	const exportSureDialog = ref(null)
	const export_type = ref('home_service_evaluate')
	const flag = ref(false)
	const handleClose = (val) => {
		flag.value = val
	}
	const exportEvent = (data : any) => {
		flag.value = true
	}

	const selectExportDialog : Record<string, any> | null = ref(null)

	/**
	 * 评价导出类型选择
	 */
	const exportSelectEvent = () => {
		exportEvent()
	}
	/**
	 * 打开详情页
	 */
	const detailEvent = (data: any) => {
		router.push(`/home_service/order/detail?order_id=${data.order_id}&status=evaluate`);
	};
	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadEvaluateList()
	}
</script>

<style lang="scss" scoped>
	/* 强制固定列（右侧）层级高于主表 */
	.el-table__fixed-right {
		z-index: 10 !important;
	}

	.image-list {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
	}

	.evaluate-image {
		width: 60px;
		height: 60px;
		object-fit: cover;
		border-radius: 4px;
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