<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="orderFormFields" :search-param="orderTable.searchParam"
					@search="loadOrderList" @exportSelectEvent="exportSelectEvent" @reset="handleFormReset"
					ref="collapseFormRef" />
			</el-card>

			<el-tabs v-model="orderTable.searchParam.order_status" class="demo-tabs" @tab-change="handleClick">
				<el-tab-pane :label="item.label" :name="item.value" v-for="(item, key) in orderStatus"
					:key="key"></el-tab-pane>
			</el-tabs>
			<div class="mt-[10px]">
				<div class="table-body min-h-[150px]" v-loading="orderTable.loading">
					<div class="flex justify-between items-center bg-[#f9f9f9] py-[10px] px-[20px]">
								<div class="text-[13px] text-[#666]">
									当前选中{{selectedOrderCount}}个服务
								</div>
								<div>
									<el-button type="default" @click="handleBatchDelete" :disabled="selectedOrderCount === 0">{{ t('deleteEvent') }}</el-button>
									<el-button type="default" @click="handleBatchCuiOrder" :disabled="selectedOrderCount === 0">{{ t('cuiOrder') }}</el-button>
									<el-button type="primary" @click="handleBatchSetTag" :disabled="selectedOrderCount === 0">{{ t('orderTagFn') }}</el-button>
								</div>
							</div>
					<div v-if="!orderTable.loading">
						<el-table :data="orderTable.data" size="large" v-loading="orderTable.loading" border
							@selection-change="handleSelectionChange" @clearSelection="clearSelection">
							<template #empty>
								<span>{{ !orderTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column type="selection" :selectable="selectable" width="45" />
							<el-table-column prop="order_no" :label="t('orderNo')" min-width="220" />
							<el-table-column :label="t('serviceType')" min-width="120">
								<template #default="{ row }">
									<span v-if="row.goodsCategory">{{ row.goodsCategory.category_name }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('标签')" min-width="120">
								<template #default="{ row }">
									<el-tag v-if="row.label && row.label.label_color" :style="{ backgroundColor: row.label.label_color, color: getTextColor(row.label.label_color) }"
										class="w-[80px] text-center">
										{{row.label.label_name}}
									</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('servicesGoods')" min-width="200">
								<template #default="{ row }">
									<span>{{ row.order_name }}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('orderStatus')" min-width="100" align="center">
								<template #default="{ row }">
									<el-tag
										:type="{'in_service': 'warning', 'finish': 'success', 'close': 'info','wait_service' : 'primary'}[row.order_status] || 'danger'"
										v-if="row.order_status_info">{{row.order_status_info.name}}</el-tag>
								</template>
							</el-table-column>
							<el-table-column :label="t('orderAllMOney')" min-width="120" align="center">
								<template #default="{ row }">
									<div class="text-[#ff0000]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.order_money}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('memberName')" min-width="200">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden">{{row.taker_name}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('mobile')" min-width="120">
								<template #default="{ row }">
									<span>{{row.taker_mobile}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('memberNotes')" min-width="220">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden text-[#ff674c]">{{row.member_message || ''}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('orderAddress')" min-width="250">
								<template #default="{ row }">
									<span class="flex-1 multi-hidden">{{row.taker_full_address || '--'}} </span>
								</template>
							</el-table-column>
							<el-table-column :label="t('technicianName')" min-width="120">
								<template #default="{ row }">
									<span v-if="row.technician">{{row.technician.real_name}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('technicianCommsion')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#e6a23c]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.technician_sum_commission}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('store')" min-width="120">
								<template #default="{ row }">
									<span v-if="row.store">{{row.store.store_name}}</span>
								</template>
							</el-table-column>
							<el-table-column :label="t('storeRate')" min-width="120">
								<template #default="{ row }">
									<div class="text-[#273de3]">
										<span class="text-[12px]">￥</span>
										<span class="font-bold">{{row.store_sum_commission}}</span>
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('isSetting')" min-width="120">
								<template #default="{ row }">
									<el-tag :type="row.is_settlement == 1 ? 'success' : 'danger'"
										effect="dark">{{row.settlement_name}}</el-tag>
								</template>
							</el-table-column>
							<el-table-column prop="create_time" :label="t('createTime')" min-width="200"
								align="center" />
							<el-table-column :label="t('yuyueTIme')" min-width="200" align="center">
								<template #default="{ row }">
									{{row.reserve_service_time || ''}}
								</template>
							</el-table-column>
							<el-table-column :label="t('servicesTime')" min-width="200" align="center">
								<template #default="{ row }">
									{{row.service_time || ''}}
								</template>
							</el-table-column>
							<el-table-column :label="t('finishTime')" min-width="200" align="center">
								<template #default="{ row }">
									{{row.finish_time || ''}}
								</template>
							</el-table-column>
							<el-table-column :label="t('seriversHours')" min-width="180" align="center">
								<template #default="{ row }">
									<div v-if="row.time_reminder" class="text-[13px]">
										{{row.time_reminder.text}}
									</div>
								</template>
							</el-table-column>
							<el-table-column :label="t('operation')" fixed="right" align="center" min-width="320">
								<template #default="{ row }">
									<div class="relative z-index-999">
										<el-button type="primary" link
											@click="detailEvent(row)">{{ t('orderDetail') }}</el-button>
										<el-button type="primary" link
											@click="orderlabel(row)">{{ t('orderLabel') }}</el-button>
										<el-button type="primary" link @click="handlePopupOrderAction(row,item.key)"
											v-for="(item,index) in row.order_status_info?.action"
											:key="index">{{ item.name }}</el-button>
										<!-- <el-button type="primary" link @click="handleOrderAction(row)">{{ t('resetPOrder') }}</el-button> -->
									</div>

								</template>
							</el-table-column>

						</el-table>
					</div>
				</div>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="orderTable.page" v-model:page-size="orderTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="orderTable.total"
						@size-change="loadOrderList()" @current-change="loadOrderList" />
				</div>
			</div>
		</el-card>
		<selectOrderlabel :visible="tagDialogVisible" :tags="tagList" @close="tagDialogVisible = false"
			@confirm="handleTagConfirm" />
		<selectTechnician :visible="technicianDialogVisible" :tags="tagList" @close="tagDialogVisible = false"
			@confirm="handleTagConfirm" />
		<component v-for="dialog in dialogs" :key="dialog.key" :is="dialog.component" :visible="dialog.visible"
			@close="() => setDialogVisible(dialog.key, false)"
			@confirm="(data) => handlePopupOrderConfirm(dialog.key, data)" 
			:other-prop="dialog.props"
			/>
			<export-sure ref="exportSureDialog" :show="flag" :type="export_type" :searchParam="orderTable.searchParam"
				@close="handleClose" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed } from 'vue'
	import { t } from '@/lang'
	import { getOrderList, getOrderStatus, setSendOrders, getOrderfrom, setOrderLabel, setOrderDispatch ,deleteOrder,cuiOrder,transferOrder} from '@/addon/home_service/api/order'
	import { ElMessageBox } from 'element-plus'
	import { getTechnicianGoods } from '@/addon/home_service/api/technician'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import OrderMethods from '@/addon/home_service/views/order/js/orderMethods';
	import { FormInstance } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import selectOrderlabel from '@/addon/home_service/views/order/components/select-orderlabel.vue';
	import selectTechnician from '@/addon/home_service/views/order/components/select-technician.vue';
	import { AnyObject } from '@/types/global'
	const tagDialogVisible = ref(false);
	const currentTags = ref([]); // 当前标签列表
	let tagSelectCallback : (id : number | string) => void; // 存储标签选择后的回调

	const technicianDialogVisible = ref(false)


	// 3. 定义弹框配置：key（唯一标识）、组件、显示状态、额外参数
	const dialogs = reactive([
		{
			key: 'action_dispatch', // 唯一标识，与工具类中的key对应
			component: selectTechnician,
			visible: false,
			props: { 
				title: '待派单处理',
				store_id:'',
				category_id:''
			} // 组件需要的额外参数
		},
		{
			key: 'action_transfer', // 唯一标识，重新派单
			component: selectTechnician,
			visible: false,
			props: { 
				title: '重新派单处理',
				initialTechnicianId: '', // 初始选中的师傅ID
				store_id:'',
				category_id:''
			} // 组件需要的额外参数
	
		},
	])
	const handlePopupOrderConfirm = (data:any,key:any) =>{
		console.log(data,key)
		if(data == 'action_dispatch' || data == 'action_transfer'){
			let params = {
				order_id:currentTags.value.order_id,
				technician_id:key
			}
			// 根据dialogKey决定调用哪个接口
			if(data == 'action_dispatch'){
				setOrderDispatch(params).then((res)=>{
					loadOrderList()
				})
			}else if(data == 'action_transfer'){
				transferOrder(params).then((res)=>{
					loadOrderList()
				})
			}
		}
	}
	const handlePopupOrderAction = (data : any, key : string) => {
		currentTags.value = data
		var order = data
		if(key == 'action_delete'){
			// 检查订单状态是否为关闭状态
			if (order.order_status_info?.status !== 'close') {
				ElMessage({
					message: t('onlyCloseOrderCanDelete'),
					type: 'warning',
				})
				return;
			}
			ElMessageBox.confirm(
				t('confirmDeleteOrder'),
				t('confirm'),
				{
					confirmButtonText: t('confirm'),
					cancelButtonText: t('cancel'),
					type: 'warning',
				}
			).then(() => {
				// 单个订单删除
				deleteOrder({ order_ids: order.order_id }).then(() => {
					ElMessage({
						message: t('deleteSuccess'),
						type: 'success',
					})
					// 刷新订单列表
					loadOrderList();
				});
			}).catch(() => {
			// 用户取消删除
		});
		}else{
			OrderMethods.orderClickFunction(
				data,
				key,
				() => { loadOrderList() },
				// 新增：弹框触发回调（接收工具类传递的弹框key）
				(dialogKey : string) => {
					// 如果是重新派单，设置初始师傅ID
				if (dialogKey === 'action_transfer' && data.technician_id) {
					const targetDialog = dialogs.find(d => d.key === dialogKey);
					if (targetDialog) {
						
						if(data.store_id>0){
							targetDialog.props.store_id = data.store_id;
						}
						targetDialog.props.category_id = data.category_id;
						targetDialog.props.order_id = data.order_id;
						targetDialog.props.initialTechnicianId = data.technician_id;
					}
				}
				if(dialogKey == 'action_dispatch'){
					const targetDialog = dialogs.find(d => d.key === dialogKey);
					if (targetDialog) {
						if(data.store_id>0){
							targetDialog.props.store_id = data.store_id;
						}
						targetDialog.props.order_id = data.order_id;
						targetDialog.props.category_id = data.category_id;
					}
				}
					setDialogVisible(dialogKey, true) // 显示对应的弹框
				}
			)
		}
		
	}
	// 新增：根据 dialogKey 设置弹框显示/隐藏（关键缺失函数）
	const setDialogVisible = (dialogKey: string, visible: boolean) => {
	  // 找到对应 key 的弹框配置
	  const targetDialog = dialogs.find(dialog => dialog.key === dialogKey);
	  if (targetDialog) {
	    targetDialog.visible = visible; // 修改弹框的 visible 状态
	  } else {
	    console.warn(`未找到 key 为 ${dialogKey} 的弹框配置`);
	  }
	}

	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title

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
			create_time: '',
			technician_name: '',
			is_settlement: '',
			label_id: [],
			store_name: [],
			pay_time: '',
			order_status: ''
		}
	})
	// 控制表格行是否可以被选中
// 返回true表示可以选中，返回false表示不可选中
const selectable = (row: any) => {
	// 允许所有订单都可以被选中，在执行具体操作时再进行状态验证
	return true;
};
	const selectedOrderCount = ref(0);
	const selectedOrderList = ref([]);
	/**
	 * 监听表格选中变化，实时更新选中服务数量
	 * @param selectedRows 当前所有选中的行数组
	 */
	const handleSelectionChange = (selectedRows : any[]) => {
		// 选中数量 = 选中行数组的长度（取消选中时数组长度减少，数量自动同步）
		selectedOrderCount.value = selectedRows.length;
		selectedOrderList.value = selectedRows
	};
	
	// 清空选择
	const clearSelection = () => {
		selectedOrderCount.value = 0;
		selectedOrderList.value = [];
	};
	const orderSource = ref([])
	const getOrderfromFn = () => {
		getOrderfrom().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = {
					label: res.data[item],
					value: item
				}
				orderSource.value.push(obj)
			})
		})
	}
	getOrderfromFn()
	// 处理订单按钮点击
	const handleOrderAction = (order : any) => {
		const result = OrderMethods.orderClickFunction(
			order,
			order.order_status,
			() => loadOrderList(), // 刷新列表的回调
		);
	};


	const handleTagConfirm = (data : any) => {
		// 判断是批量设置还是单个设置
		let orderIds = '';
		if (currentTags.value.isBatch) {
			// 批量设置：拼接选中的订单ID
			orderIds = selectedOrderList.value.map(item => item.order_id).join(',');
		} else {
			// 单个设置：使用单个订单ID
			orderIds = currentTags.value.order_id;
		}
		
		let params = {
			label_id: data,
			order_ids: orderIds
		}
		setOrderLabel(params).then((res) => {
			// 关闭标签选择对话框
			tagDialogVisible.value = false;
			// 刷新订单列表
			loadOrderList();
			// 清空选中状态
			selectedOrderCount.value = 0;
			selectedOrderList.value = [];
		})
	}
	const orderFormFields = computed(() => [
		{
			prop: 'order_no',
			label: t('orderInfo'),
			component: ElInput,
			placeholder: t('orderInfoPlaceholder'),
			props: {
				trim: true, // 自动去除首尾空格
				clearable: true,
				class: '!w-[230px]' // 保留原宽度样式
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
				class: '!w-[230px]' // 保留原宽度样式
			}
		},
		{
			prop: 'create_time',
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
		{
			prop: 'order_from',
			label: t('orderSource'),
			component: ElSelect, // 必须是导入的 ElSelect（不是字符串或错误引用）
			placeholder: t('orderSourcePlaecholder'),
			props: {
				filterable: true,
				options: orderSource.value
			}
		},
		{
			prop: 'label_id',
			label: t('orderTag'),
			component: ElInput,
			placeholder: t('orderTagPlaeholder'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]' // 保留原宽度样式
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
		},
		{
			prop: 'is_settlement',
			label: t('isSetting'),
			component: ElSelect, // 必须是导入的 ElSelect（不是字符串或错误引用）
			placeholder: t('isSettingPlaceholder'),
			props: {
				filterable: true,
				options: settlementList.value
			}
		},
	]);
	// 计算文本颜色（根据背景色明暗自动调整）
	const getTextColor = (bgColor : string) => {
		// 简单判断颜色亮度，返回黑白文本
		const hex = bgColor.replace('#', '')
		const r = parseInt(hex.substring(0, 2), 16)
		const g = parseInt(hex.substring(2, 4), 16)
		const b = parseInt(hex.substring(4, 6), 16)
		const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
		return luminance > 0.5 ? '#000000' : '#ffffff'
	}
	const searchFormRef2 = ref<FormInstance>()
	const settlementList = ref([
		{
			label: '已结算',
			value: '1'
		},
		{
			label: "待结算",
			value: '0'
		}
	])
	/**
	 * 获取订单列表
	 */
	const loadOrderList = (page : number = 1) => {
		orderTable.loading = true
		orderTable.page = page

		getOrderList({
			page: orderTable.page,
			limit: orderTable.limit,
			...orderTable.searchParam
		}).then((res) => {
			orderTable.loading = false
			orderTable.total = res.data.total
			orderTable.data = res.data.data
			setTablePageStorage(orderTable.page, orderTable.limit, orderTable.searchParam)
		}).catch(() => {
			orderTable.loading = false
		})
	}
	const handleFormReset = () => {
		orderTable.page = 1; // 重置页码（与原逻辑一致）
		loadOrderList(); // 重置后重新搜索（与原逻辑一致）
	};
	loadOrderList(getTablePageStorage(orderTable.searchParam).page)

	// 获取订单状态
	const orderStatus = ref([])
	const checkOrderStatus = () => {
		getOrderStatus().then((res) => {
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
				label: `全部(${res.data.total})`,
				value: '',
				name: '全部'
			})
			styleTabCounts(); // 状态列表加载后处理样式
			
			// 处理路由参数status，自动选择对应标签页
			const statusFromRoute = route.query.status;
			if (statusFromRoute && typeof statusFromRoute === 'string') {
				// 检查是否存在匹配的订单状态
				const matchedStatus = orderStatus.value.find(item => item.value === statusFromRoute);
				if (matchedStatus) {
					orderTable.searchParam.order_status = statusFromRoute;
					loadOrderList(); // 重新加载匹配状态的订单数据
				}
			}
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

	// 切换订单状态
	const handleClick = (event : any) => {
		orderTable.searchParam.order_status = event
		selectedOrderCount.value = 0
		loadOrderList()
	}
	// 详情
	const detailEvent = (info : AnyObject) => {
		router.push(`/home_service/order/detail?order_id=${info.order_id}`)
	}
	// 单个订单设置标签
	const hideTagDialog = () => {
		tagDialogVisible.value = false;
	};

	// 处理用户选择标签后的确认
	const handleTagSelectConfirm = (selectedTagId : number | string) => {
		if (tagSelectCallback && typeof tagSelectCallback === 'function') {
			tagSelectCallback(selectedTagId); // 调用orderMethods中定义的回调
		}
	};

	// 单个订单设置标签
	const orderlabel = (data : any) => {
		currentTags.value = data;
		currentTags.value.isBatch = false;
		tagDialogVisible.value = true;
	};
	
	// 批量删除订单
	const handleBatchDelete = () => {
		if (selectedOrderCount.value === 0) {
			return;
		}
		
		// 检查是否有不符合删除条件的订单
		const invalidOrders = selectedOrderList.value.filter(item => item.order_status_info?.status !== 'close');
		if (invalidOrders.length > 0) {
			 ElMessage({
			    message: t('onlyCloseOrderCanDelete'),
			    type: 'warning',
			  })
			return;
		}
		ElMessageBox.confirm(
			t('confirmDeleteOrder'),
			t('confirm'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning',
			}
		).then(() => {
	 
			// 拼接选中的订单ID
			const orderIds = selectedOrderList.value.map(item => item.order_id).join(',');
			let params = {
				order_ids: orderIds
			}
			console.log(params)
			deleteOrder(params).then(() => {
				ElMessage({
					message: t('deleteSuccess'),
					type: 'success',
				})
				// 刷新订单列表
				loadOrderList();
				// 清空选中状态
				selectedOrderCount.value = 0;
				selectedOrderList.value = [];
				tagDialogVisible.value = false;
			});
		}).catch(() => {
		
			// 用户取消删除
		});
	};
	
	// 批量催单
	const handleBatchCuiOrder = () => {
		if (selectedOrderCount.value === 0) {
			return;
		}
		
		// 检查是否有不符合催单条件的订单
		const invalidOrders = selectedOrderList.value.filter(item => item.order_status_info?.status !== 'wait_service');
		if (invalidOrders.length > 0) {
			ElMessage({
			   message: t('onlyWaitServiceOrderCanCui'),
			   type: 'warning',
			 })
			return;
		}
		
		// 拼接选中的订单ID
    const orderIds = selectedOrderList.value.map(item => item.order_id);
// 如果cuiOrder支持数组参数
    cuiOrder({ order_ids: orderIds }).then(() => {
      // 刷新订单列表
      loadOrderList();
      // 清空选中状态
      selectedOrderCount.value = 0;
      selectedOrderList.value = [];
    });


	};
	
	// 批量设置标签
	const handleBatchSetTag = () => {
		if (selectedOrderCount.value === 0) {
			return;
		}
		currentTags.value = { isBatch: true };
		tagDialogVisible.value = true;
	};
	// 师傅列表
	const technicianList = reactive({
		page: 1,
		limit: 10,
		total: 0,
		id: 0,
		loading: false,
		data: []
	})

	// 派单
	const sendOrderInfo = ref({
		order_id: '',
		technician_id: ''
	})
	const dialogTechnicianVisible = ref(false)
	const handleSelect = (data) => {
		technicianList.id = data.item[0].goods_id
		getTechnicianListFn()
		dialogTechnicianVisible.value = true
		sendOrderInfo.value.order_id = data.order_id
	}
	const sendOrderFn = (data) => {
		sendOrderInfo.value.technician_id = data.id
		setSendOrders(sendOrderInfo.value).then((res) => {
			dialogTechnicianVisible.value = false
			loadOrderList()
		})
	}

	/**
	 * 订单导出
	 */
	const exportSureDialog = ref(null)
	const export_type = ref('home_service_order')
	const flag = ref(false)
	const handleClose = (val) => {
		flag.value = val
	}
	const exportEvent = (data : any) => {
		flag.value = true
	}

	const selectExportDialog : Record<string, any> | null = ref(null)

	/**
	 * 订单导出类型选择
	 */
	const exportSelectEvent = () => {
		exportEvent()
	}

	// // 合并表格行
	const arraySpanMethod = ({ row, column, rowIndex, columnIndex }) => {
		if (rowIndex === 0) {
			if (columnIndex > 0) {
				return [row.rowNum, 1]
			} else {
				return [1, 1]
			}
		} else {
			if (columnIndex > 0) {
				return [0, 0]
			} else {
				return [1, 1]
			}
		}
	}
	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadOrderList()
	}
	const resetForm2 = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		getTechnicianListFn()
	}
	// 跳转退款详情
	const toRefundDetail = (data : string) => {
		router.push('/home_service/order/refund/detail?refund_no=' + data.refund_no)
	}
</script>

<style lang="scss" scoped>
	/* 强制固定列（右侧）层级高于主表 */
	.el-table__fixed-right {
		z-index: 10 !important;
	}

	/* 标签中数字样式（蓝色，强制优先级） */
	.tab-count {
		color: #409eff !important;
		/* Element Plus 主题蓝，确保覆盖默认样式 */
		font-weight: 500;
	}

	/* 样式穿透，作用到标签内部 */
	:deep(.el-tabs__item) {
		.tab-count {
			color: #409eff !important;
		}
	}
</style>