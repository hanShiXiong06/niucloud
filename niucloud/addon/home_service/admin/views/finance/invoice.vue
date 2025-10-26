<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ t('invoiceList') }}</span>
				<el-button type="primary" @click="exportEvent">{{ t('export') }}</el-button>
			</div>

			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="InvoiceFormFields" :search-param="InvoiceTableData.searchParam"
					@search="loadInvoiceList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>

			<el-tabs v-model="activeName" class="demo-tabs" @tab-change="changeTab">
				<el-tab-pane :label="item.name" :name="item.type" v-for="(item, index) in typeList" :key="index">
					<div class="mt-[10px]">
						<el-table :data="InvoiceTableData.data" size="large" v-loading="InvoiceTableData.loading">
							<template #empty>
								<span>{{ !InvoiceTableData.loading ? t('emptyData') : '' }}</span>
							</template>
							<el-table-column prop="invoice_number" :show-overflow-tooltip="true" :label="t('applicationNumber')"
								width="100">
								<template #default="{ row }">
										{{ row.invoice_number || '---' }}
								</template>
							</el-table-column>
							<el-table-column prop="nickname" :label="t('customer')" width="120" />
							
							<el-table-column prop="status" :show-overflow-tooltip="true" :label="t('status')"
								width="180">
								<template #default="{ row }">
									<el-tag :type="row.status == '1' ? 'success' : 'danger'" effect="dark">
										{{ row.status_name }}
									</el-tag>
								</template>
							</el-table-column>
							<el-table-column prop="status" :show-overflow-tooltip="true" :label="t('发票内容')"
								width="180">
								<template #default="{ row,$index }">
										<div v-if="row.content_name && row.content_name.split(',').length" @click="showMoreCategory($index)">
											<div class="flex flex-wrap">
												<div class="mr-[20px]" v-for="(item,index) in row.content_name.split(',')" :key="index">
													<p class="truncate mb-[5px]  px-[10px] text-[13px] text-[#273de3] rounded-[2px] py-[2px] bg-[#e9ecfc]"
														v-if="index <= 1">{{ item }} </p>
												</div>
											</div>
											<div class="flex flex-wrap" v-if="row.showCategory">
												<div class="" v-for="(item,index) in row.content_name.split(',')" :key="index">
													<p class="truncate mb-[5px] px-[10px] text-[13px] text-[#273de3]  rounded-[2px] py-[2px] bg-[#e9ecfc]"
														v-if="index > 1">{{ item }} </p>
												</div>
											</div>
											<span v-if="row.content_name.split(',').length > 2"
												class="text-[#999] text-[11px] bg-[#f5f5f5] cursor-pointer py-[4px] px-[5px]">
												{{ row.showCategory ? '收起' : `+${row.content_name.length - 2 }` }}
											</span>
										</div>
								</template>
							</el-table-column>
							<el-table-column  :label="t('invoiceAmount')" min-width="120" align="center">
								<template #default="{ row }">
									<span class="text-[12px]">￥</span>
									<span class="font-bold">{{ row.money }}</span>
								</template>
							</el-table-column>
							
							<el-table-column  :label="t('invoiceOrderMoney')" min-width="120" align="center">
								<template #default="{ row }">
									<span class="text-[12px]">￥</span>
									<span class="font-bold">{{ row.order_money }}</span>
								</template>
							</el-table-column>
							<el-table-column prop="header_type" :show-overflow-tooltip="true" :label="t('抬头类型')"
								width="100">
								<template #default="{ row }">
									<el-tag :type="row.header_type == 'individual' ? '' : 'warning'" effect="dark">
										{{ row.header_type_name }}
									</el-tag>
								</template>
							</el-table-column>
							<el-table-column prop="type" :show-overflow-tooltip="true" :label="t('type')"
								width="180">
								<template #default="{ row }">
									<el-tag :type="row.type === 'electron_regular_invoice' ? '' : 'warning'" effect="dark">
										{{ row.type_name }}
									</el-tag>
								</template>
							</el-table-column>

							<el-table-column :label="t('creditCode')" min-width="200" align="center">
								<template #default="{ row }">
									{{ row.tax_number || '---' }}
								</template>
							</el-table-column>
							<!-- <el-table-column :label="t('invoiceImage')" min-width="120" align="center">
								<template #default="{ row }">
									<el-image
									      style="width: 70px; height: 70px"
									      :src="img(row.invoice_voucher)"
									      :zoom-rate="1.2"
									      :max-scale="7"
									      :min-scale="0.2"
									      :preview-src-list="[img(row.invoice_voucher)]"
									      show-progress
									      :initial-index="4"
									      fit="cover"
									    >
									</el-image>
								</template>
							</el-table-column> -->
							<el-table-column :label="t('relatedOrder')" min-width="250" align="center">
								<template #default="{ row }">
									<div v-if="row.order_no" >
										<div v-for="(item,index) in row.order_no.split(',')" :key="index">{{item}}</div>
									</div>
								</template>
							</el-table-column>

							<el-table-column :label="t('applicationTime')" min-width="180" align="center">
								<template #default="{ row }">
									{{ row.create_time }}
								</template>
							</el-table-column>
							<el-table-column :label="t('invoiceTime')" min-width="180" align="center">
								<template #default="{ row }">
									{{ timeStampTurnTime(row.invoice_time )|| t('notInvoiced') }}
								</template>
							</el-table-column>
							<el-table-column prop="email" :show-overflow-tooltip="true" :label="t('邮箱地址')"
								width="180">
								<template #default="{ row }">
										{{row.email || '---'}}
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" align="right" width="130">
								<template #default="{ row }">
									<el-button type="primary" link v-if="row.status != 1"
										@click="invoiceEvent(row)">{{ t('playInvoice') }}</el-button>
									<el-button type="primary" link
										@click="detailEvent(row)">详情</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="InvoiceTableData.page"
								v-model:page-size="InvoiceTableData.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="InvoiceTableData.total"
								@size-change="loadInvoiceList()" @current-change="loadInvoiceList" />
						</div>
					</div>
				</el-tab-pane>
			</el-tabs>
		</el-card>
		<export-sure ref="exportSureDialog" :show="flag" type="home_service_invoice" :searchParam="InvoiceTableData.searchParam"
			@close="handleClose" />
		<!-- 开具发票弹窗 -->
		<el-dialog title="开具发票" v-model="invoiceDialogVisible" width="600px" :before-close="handleDialogClose">
			<el-form :model="invoiceForm" ref="invoiceFormRef" :rules="invoiceRules" label-width="120px">
				<template v-if="invoiceForm.header_type == 'individual'">
					<el-form-item :label="t('customerName')" prop="customerName">
						<el-input v-model="invoiceForm.nickname" 
							class="input-width !w-[214px]" readonly />
					</el-form-item>
				</template>
				<template v-else>
					<el-form-item :label="t('header_name')" prop="customerName">
						<el-input v-model="invoiceForm.header_name" 
							class="input-width !w-[214px]" readonly />
					</el-form-item>
					<el-form-item :label="t('telephone')" prop="customerName">
						<el-input v-model="invoiceForm.telephone" 
							class="input-width !w-[214px]" readonly />
					</el-form-item>
					<el-form-item :label="t('address')" prop="customerName">
						<el-input v-model="invoiceForm.address" 
							class="input-width !w-[214px]" readonly />
					</el-form-item>
					<el-form-item :label="t('bank_name')" prop="customerName">
						<el-input v-model="invoiceForm.bank_name" 
							class="input-width !w-[214px]" readonly />
					</el-form-item>
					<el-form-item :label="t('bank_card_number')" prop="customerName">
						<el-input v-model="invoiceForm.bank_card_number"
							class="input-width !w-[214px]" readonly />
					</el-form-item>
					<el-form-item :label="t('taxpayerId')" prop="taxpayerId">
						<el-input v-model="invoiceForm.tax_number" :placeholder="t('taxpayerIdPlaceholder')"
							class="input-width !w-[214px]" readonly />
					</el-form-item>
				</template>
				
				<el-form-item :label="t('invoiceType')" prop="invoiceType">
					<el-input v-model="invoiceForm.type_name" :placeholder="t('invoiceTypePlaceholder')"
						class="input-width !w-[214px]" readonly />
				</el-form-item>
				<el-form-item :label="t('orderMoney')">
					<el-input v-model.number="invoiceForm.order_money" :placeholder="t('invoiceAmountPlaceholder')"
						type="number" class="input-width !w-[214px]" readonly />
				</el-form-item>
				<el-form-item :label="t('email')" >
					<el-input v-model="invoiceForm.email" :placeholder="t('emailPlaceholder')"
						class="input-width !w-[214px]" readonly />
				</el-form-item>
				<el-form-item :label="t('invoiceAmount')" prop="money">
					<el-input v-model.number="invoiceForm.money" :placeholder="t('invoiceAmountPlaceholder')"
						type="number" class="input-width !w-[214px]" />
				</el-form-item>
				<el-form-item :label="t('applicationNumber')" prop="invoice_number">
					<el-input v-model="invoiceForm.invoice_number" :placeholder="t('applicationNumberPlaceholder')"
						class="input-width !w-[214px]" />
				</el-form-item>
				
				<el-form-item :label="t('invoiceFile')" prop="invoice_voucher">
					<upload-file v-model="invoiceForm.invoice_voucher" api="sys/document/document" accept=".pdf"
						class="input-width !w-[214px]" />
					<div class="ml-[10px] text-[12px] text-[#999] leading-[20px]">{{ t('fileTip') }}</div>
				</el-form-item>
			</el-form>
			<template #footer>
				<span class="dialog-footer">
					<el-button @click="invoiceDialogVisible = false">{{ t('cancel') }}</el-button>
					<el-button type="primary" @click="handleIssueInvoice">{{ t('issueInvoice') }}</el-button>
				</span>
			</template>
		</el-dialog>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed, nextTick } from 'vue';
	import { t } from '@/lang';
	import { img, setTablePageStorage, getTablePageStorage ,timeStampTurnTime} from '@/utils/common';
	import { ElMessageBox, FormInstance, ElMessage } from 'element-plus';
	import { useRouter, useRoute } from 'vue-router';
	import { getInvoiceList,setInvoice } from '@/addon/home_service/api/invoice'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	// 导入upload-file组件（假设路径）

	const route = useRoute();
	const pageName = route.meta.title;
	const typeList = ref([
		{
			name: t('all'),
			type: '',
		},
		{
			name: t('toInvoice'),
			type: 0,
		},
		{
			name: t('invoiced'),
			type: 1,
		},
	]);
	const activeName = ref('');
	const InvoiceTableData = reactive({
		page: 1,
		limit: 10,
		total: 100,
		loading: false,
		data: [],
		searchParam: {
			title: '',
			category_id: '',
		},
	});
	const showMoreCategory = (index : any) => {
		if (!InvoiceTableData.data[index].showCategory) {
			InvoiceTableData.data[index].showCategory = true
		} else {
			InvoiceTableData.data[index].showCategory = false
		}
	}
	const searchFormRef = ref<FormInstance>();

	const handleFormReset = () => {
		InvoiceTableData.page = 1;
		loadInvoiceList();
	};
	const InvoiceFormFields = computed(() => [
		{
			prop: 'nickname',
			label: t('nickname'),
			component: 'ElInput',
			placeholder: t('nicknamePlaceholder'),
			props: {
				class: 'input-width !w-[214px]'
			},
		},
		{
			prop: 'header_name',
			label: t('headerName'),
			component: 'ElInput',
			placeholder: t('headerNamePlaceholder'),
			props: {
				class: 'input-width !w-[214px]'
			},
		},
		{
			prop: 'create_time',
			label: t('createTime'),
			component: 'ElDatePicker',
			placeholder: '',
			props: {
				type: 'datetimerange',
				valueFormat: 'YYYY-MM-DD HH:mm:ss',
				startPlaceholder: t('startDate'),
				endPlaceholder: t('endDate'),
				class: 'input-width !w-[214px]'
			},
		},
	]);
	const detailEvent = (data:any) =>{
 		// window.open(img(data.invoice_voucher))
		router.push(`/home_service/order/detail?order_id=${data.order_ids[0]}&status=invoiceInfo`)
	}
	// 导出相关
	const exportSureDialog = ref(null);
	const flag = ref(false);
	const handleClose = (val) => {
		flag.value = val;
	};
	const exportEvent = (data : any) => {
		flag.value = true;
	};

	// 开具发票弹窗相关
	const invoiceDialogVisible = ref(false);
	const invoiceFormRef = ref<FormInstance>();
	const invoiceForm = reactive({
		nickname: '',
		tax_number: '',
		type: '',
		money: '',
		email: '',
		invoice_voucher: '', // 适配upload-file组件的v-model
		invoice_number:""
	});
	const invoiceRules = reactive({
		nickname: [{ required: true, message: t('requiredCustomerName'), trigger: 'blur' }],
		tax_number: [{ required: true, message: t('requiredTaxpayerId'), trigger: 'blur' }],
		type: [{ required: true, message: t('requiredInvoiceType'), trigger: 'change' }],
		money: [
			{ required: true, message: t('requiredInvoiceAmount'), trigger: 'blur' },
			{ type: 'number', min: 0.01, message: t('invoiceAmountMustBeGreaterThanZero'), trigger: 'blur' }
		],
		invoice_number: [{ required: true, message: t('applicationNumberPlaceholder'), trigger: 'blur' }],
		email: [{ required: true, message: t('requiredEmail'), trigger: 'blur' }],
		deliveryMethod: [{ required: true, message: t('requiredDeliveryMethod'), trigger: 'change' }],
		invoice_voucher: [
			{
				required: true,
				message: t('requiredInvoiceFile'),
				trigger: 'change',
			},
		],
	});

	const handleDialogClose = () => {
		invoiceDialogVisible.value = false;
		invoiceFormRef.value?.resetFields();
		invoiceForm.invoice_voucher = ''; // 重置上传文件
	};

	const handleIssueInvoice = () => {
		invoiceFormRef.value?.validate((valid) => {
			if (valid) {
				setInvoice(invoiceForm).then((res)=>{
					invoiceDialogVisible.value = false;
					loadInvoiceList();
				}).catch((err)=>{
					invoiceDialogVisible.value = false;
					loadInvoiceList();
				})
			}
		});
	};

	/**
	 * 获取发票列表（假数据）
	 */
	const loadInvoiceList = (page : number = 1) => {
		InvoiceTableData.loading = true;
		InvoiceTableData.page = page;
		getInvoiceList({
			page: InvoiceTableData.page,
			limit: InvoiceTableData.limit,
			...InvoiceTableData.searchParam,
			status:activeName.value
		}).then(res => {
			InvoiceTableData.loading = false
			InvoiceTableData.data = res.data.data
			InvoiceTableData.total = res.data.total
			setTablePageStorage(InvoiceTableData.page, InvoiceTableData.limit, InvoiceTableData.searchParam)
		}).catch(() => {
			InvoiceTableData.loading = false
		})
	};
	loadInvoiceList(getTablePageStorage(InvoiceTableData.searchParam).page);
	const changeTab = (e : any) => {
		console.log(e);
		loadInvoiceList();
	};
	const router = useRouter();

	/**
	 * 开具发票弹窗打开
	 */
	const invoiceEvent = (row : any) => {
		Object.assign(invoiceForm, row);
		invoiceDialogVisible.value = true;
		nextTick(() => {
			invoiceFormRef.value?.clearValidate();
		});
	};

	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return;
		formEl.resetFields();
		loadInvoiceList();
	};
</script>

<style lang="scss" scoped></style>