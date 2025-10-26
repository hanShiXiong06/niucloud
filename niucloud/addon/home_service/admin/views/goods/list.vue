<template>
	<div class="main-container">

		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" class="w-[100px]" @click="addEvent">
					{{ t('addGoods') }}
				</el-button>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="servicesGoodsTable.searchParam"
					@search="loadServicesGoodsList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<el-tabs v-model="servicesGoodsTable.searchParam.status" class="goods-tabs" @tab-change="tabHandleClick">
				<el-tab-pane :label="t('all')" name=""></el-tab-pane>
				<el-tab-pane :label="t('statusOn')" name="1"></el-tab-pane>
				<el-tab-pane :label="t('statusOff')" name="0"></el-tab-pane>
			</el-tabs>
			<div class="flex justify-between items-center bg-[#f9f9f9] py-[10px] px-[20px]">
				<div class="text-[13px] text-[#666]">
					当前选中{{selectedGoodsCount}}个服务
				</div>
				<div>
					<el-button type="default" @click="batchDownEvent(0)">{{ t('batchDownGoods') }}</el-button>
					<el-button type="default" @click="batchDownEvent(1)">{{ t('batchUpGoods') }}</el-button>
					<el-button type="primary" @click="batchDeleteEvent(row)">{{ t('batchDelete') }}</el-button>
					<!-- <el-button type="primary" @click="batchSetEvent(row)">{{ t('batchSet') }}</el-button> -->
				</div>
			</div>

			<div class="mt-[10px]">
				<el-table :data="servicesGoodsTable.data" size="large" v-loading="servicesGoodsTable.loading"
					ref="goodsListTableRef" @sort-change="sortChange" @selection-change="handleSelectionChange">
					<template #empty>
						<span>{{ !servicesGoodsTable.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column type="selection" :selectable="selectable" width="55" />
					<el-table-column :show-overflow-tooltip="true" label="ID" min-width="80">
						<template #default="{ row,$index }">
							<!-- {{$index + 1}} -->
							{{row.goods_id}}
						</template>
					</el-table-column>
					<el-table-column :label="t('goodsInfo')" :show-overflow-tooltip="true" min-width="280" align="left">
						<template #default="{ row }">
							<div class="flex items-center">
								<div class="w-[60px] max-h-[60px]">
									<div class="min-w-[60px] h-[60px] flex items-center justify-center">
										<el-image v-if="row.goods_cover" class="w-[60px] h-[60px]"
											:src="img(row.goods_cover)" fit="contain">
											<template #error>
												<div class="image-slot">
													<img class="w-[60px] h-[60px]"
														src="@/addon/home_service/assets/goods_default.png" />
												</div>
											</template>
										</el-image>
										<img v-else class="w-[60px] h-[60px]"
											src="@/addon/home_service/assets/goods_default.png" fit="contain" />
									</div>
								</div>
								<div class="ml-2">
									<div>
										<a href="javascript:;" class="flex-1 multi-hidden"
											:title="row.goods_name">{{ row.goods_name }}</a>
									</div>
									<div class="flex">
										<el-tag type="primary" v-if="row.buy_type == 'buy'"
											class="mr-[10px]">{{ row.buy_type_name }}</el-tag>
										<el-tag type="success" v-else-if="row.buy_type == 'reservation'"
											class="mr-[10px]">{{ row.buy_type_name }}</el-tag>
										<el-tag type="warning" v-else-if="row.is_card">次卡</el-tag>
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('severceCategory')" min-width="150"
						align="left">
						<template #default="{ row,$index }">
							{{row.category_name}}
						</template>
					</el-table-column>
					<el-table-column prop="price" :label="t('price')" min-width="120" sortable="custom">
						<template #default="{ row }">
							<span>￥{{ row.goodsSku.price }}</span>
						</template>
					</el-table-column>
					<el-table-column prop="sale_num" :label="t('saleNum')" min-width="120" sortable="custom" />
					<!-- <el-table-column prop="take_pictures" :label="t('takephoto')" min-width="120">
						<template #default="{ row }">
							<el-tag
								:type="row.take_pictures ? 'success' : 'info'">{{ row.take_pictures ? t('yes') : t('no') }}</el-tag>
						</template>
					</el-table-column>
					<el-table-column prop="grab_orders" :label="t('grabOrders')" min-width="120">
						<template #default="{ row }">
							<el-tag
								:type="row.grab_orders ? 'success' : 'info'">{{ row.grab_orders ? t('yes') : t('no') }}</el-tag>
						</template>
					</el-table-column>
					<el-table-column prop="set_out" :label="t('setOut')" min-width="120">
						<template #default="{ row }">
							<el-tag
								:type="row.set_out ? 'success' : 'info'">{{ row.set_out ? t('open') : t('close') }}</el-tag>
						</template>
					</el-table-column> -->
					<el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
						<template #default="{ row }">
							<el-input v-model="row.sort" class="!w-[70px]" maxlength="10"
								@input="sortInputListener($event,row)" />
						</template>
					</el-table-column>
					<el-table-column prop="status" :label="t('status')" min-width="120">
						<template #default="{ row }">
							<el-tag type="success" v-if="row.status == 1">{{t('tooUp')}}</el-tag>
							<el-tag type="info" v-else-if="row.status == 0">{{t('tooDown')}}</el-tag>
							<el-tag type="warning" v-else>{{t('tooUp')}}</el-tag>
						</template>
					</el-table-column>
					<el-table-column prop="create_time" :label="t('createTime')" min-width="200" />
					<el-table-column :label="t('operation')" fixed="right" align="right" min-width="320">
						<template #default="{ row }">
							<el-button type="primary" link @click="copyEvent(row)">{{ t('copyGoods') }}</el-button>
							<el-button type="primary" link @click="spreadEvent(row)">{{ t('spreadGoods') }}</el-button>
							<el-button type="primary" link @click="statusEvent(row, 1)"
								v-if="row.status == 0">{{ t('up')}}</el-button>
							<el-button type="primary" link @click="statusEvent(row, 0)"
								v-if="row.status == 1">{{ t('down')}}</el-button>
							<el-button type="primary" link @click="memberPriceEvent(row)"
								v-if="row.buy_type == 'buy'">{{ t('memberPrice') }}</el-button>
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
							<el-button type="primary" link
								@click="deleteEvent(row.goods_id)">{{ t('delete') }}</el-button>
						</template>
					</el-table-column>

				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="servicesGoodsTable.page"
						v-model:page-size="servicesGoodsTable.limit" layout="total, sizes, prev, pager, next, jumper"
						:total="servicesGoodsTable.total" @size-change="loadServicesGoodsList()"
						@current-change="loadServicesGoodsList" />
				</div>
			</div>
		</el-card>
		<!-- 服务推广弹出框 -->
		<spread-popup ref="spreadPopupRef" />

		<!-- 会员价弹出框 -->
		<goods-member-price-popup ref="memberPricePopupRef" @load="loadServicesGoodsList" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref,nextTick ,computed } from 'vue'
	import { t } from '@/lang'
	import { getCategoryTree } from '@/addon/home_service/api/category'
	import { getGoodsList, deleteGoods, editGoodsStatus, editGoodsSort, copyGoods } from '@/addon/home_service/api/goods'
	import { debounce, img, filterDigit, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { ElMessageBox, FormInstance, ElMessage } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import { cloneDeep } from 'lodash-es'
	import { getMemberLevelAll } from '@/app/api/member'
	import goodsMemberPricePopup from '@/addon/home_service/views/goods/components/goods-member-price-popup.vue'
	import spreadPopup from '@/components/spread-popup/index.vue'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const pageName = route.meta.title
	const repeat = ref(false)
	const selectable = ref()
	const selectedGoodsCount = ref(0);
	const selectedGoodsList = ref([]);
	const servicesGoodsTable = reactive<any>({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			goods_name: '',
			goods_category: '',
			status: '',
			create_time: '',
			start_sale_num: '',
			end_sale_num: '',
			start_price: '',
			end_price: '',
			order: '',
			sort: ''
		}
	})
	/**
	 * 监听表格选中变化，实时更新选中服务数量
	 * @param selectedRows 当前所有选中的行数组
	 */
	const handleSelectionChange = (selectedRows : any[]) => {
		// 选中数量 = 选中行数组的长度（取消选中时数组长度减少，数量自动同步）
		selectedGoodsCount.value = selectedRows.length;
		selectedGoodsList.value = selectedRows
	};
	const searchFormRef = ref<FormInstance>()

	// 服务分类
	const handleFormReset = () => {
		servicesGoodsTable.page = 1; // 重置页码（与原逻辑一致）
		loadServicesGoodsList(); // 重置后重新搜索（与原逻辑一致）
	};
	// 响应式存储转换后的分类树（供 ElCascader 使用）
	const categoryList = ref([]);

	// 获取分类树并转换格式
	const checkCategory = async (row = null) => {
		getCategoryTree().then((res) => {
			const data = res.data;
			if (data) {
				// 调用转换函数，将原始数据转为 Cascader 格式
				categoryList.value = transformToCascaderOptions(res.data);
				console.log(categoryList.value); // 可查看转换后的数据结构
			}
		});
	};

	// 初始化调用
	checkCategory();

	/**
	 * 将原始分类树转换为 ElCascader 所需格式
	 * @param {Array} nodes - 原始分类节点数组
	 * @returns {Array} 转换后的 Cascader 选项数组
	 */
	const transformToCascaderOptions = (nodes) => {
		return nodes.map(node => {
			// 映射核心字段：value + label
			const transformedNode = {
				value: node.category_id, // 原 category_id → value
				label: node.category_name, // 原 category_name → label
			};
			// 递归处理子节点（若存在）
			if (node.children && Array.isArray(node.children) && node.children.length > 0) {
				transformedNode.children = transformToCascaderOptions(node.children);
			}
			return transformedNode;
		});
	};
	// O2O服务搜索表单的字段配置（与组件适配）
	const formFields = computed(() => [
	  {
	  	prop: 'goods_name',
	  	label: t('goodsName'),
	  	component: ElInput,
	  	placeholder: t('goodsNamePlaceholder'),
	  	props: {
	  		trim: true,
	  	}
	  },
	  {
	  	prop: 'goods_category',
	  	label: t('categoryId'),
	  	component: ElCascader,
	  	placeholder: t('selectCategoryPlaceholder'),
	  	props: {
	  		options: categoryList.value, // 直接绑定转换后的分类树
			key: categoryList.value.length,
	  		props: {
	  			value: 'value',
	  			label: 'label',
	  			children: 'children',
	  			expandTrigger: 'hover',
				emitPath: false 
	  		}
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
	  	}
	  },
	  {
	  	prop: 'sale_num_range',
	  	label: t('saleNum'),
	  	fieldType: 'ranged',
	  	subProps: {
	  		left: {
	  			prop: 'start_sale_num',
	  			placeholder: t('startSaleNumPlaceholder'),
	  			maxlength: 10,
	  			onKeyup: (e : Event) => {
	  				const input = e.target as HTMLInputElement;
	  				input.value = input.value.replace(/[^\d]/g, ''); // 数字过滤逻辑
	  			}
	  		},
	  		right: {
	  			prop: 'end_sale_num',
	  			placeholder: t('endSaleNumPlaceholder'),
	  			maxlength: 10,
	  			onKeyup: (e : Event) => {
	  				const input = e.target as HTMLInputElement;
	  				input.value = input.value.replace(/[^\d]/g, '');
	  			}
	  		}
	  	}
	  },
	  {
	  	prop: 'price_range',
	  	label: t('skuPrice'),
	  	fieldType: 'ranged',
	  	subProps: {
	  		left: {
	  			prop: 'start_price',
	  			placeholder: t('startPricePlaceholder'),
	  			maxlength: 10,
	  			onKeyup: (e : Event) => {
	  				const input = e.target as HTMLInputElement;
	  				input.value = input.value.replace(/[^\d.]/g, '').replace(/\.{2,}/g, '.').replace(/^\./, '');
	  			}
	  		},
	  		right: {
	  			prop: 'end_price',
	  			placeholder: t('endPricePlaceholder'),
	  			maxlength: 10,
	  			onKeyup: (e : Event) => {
	  				const input = e.target as HTMLInputElement;
	  				input.value = input.value.replace(/[^\d.]/g, '').replace(/\.{2,}/g, '.').replace(/^\./, '');
	  			}
	  		}
	  	}
	  }
	]);
	const batchDownEvent = (num:any) => {
		if (selectedGoodsList.value.length == 0) {
			ElMessage({
				type: 'warning',
				message: `${t('choosePlaceholder')}`
			})
			return
		}
		ElMessageBox.confirm(
			num ? t('batchUpTips') :t('batchDownTips'),
			t('warning'),
			{ confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning' }
		).then(async () => {
			let arr = []
			selectedGoodsList.value.forEach((item,index)=>{
				arr.push(item.goods_id)
			})
			editGoodsStatus({ goods_ids: arr.toString(), status: num }).then(() => {
				loadServicesGoodsList()
			})
			loadServicesGoodsList();
		});
	}
	const batchDeleteEvent = () => {
		if (selectedGoodsList.value.length == 0) {
			ElMessage({
				type: 'warning',
				message: `${t('choosePlaceholder')}`
			})
			return
		}
		ElMessageBox.confirm(
			t('batchDeleteTips'),
			t('warning'),
			{ confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning' }
		).then(async () => {
			let arr = []
			selectedGoodsList.value.forEach((item,index)=>{
				arr.push(item.goods_id)
			})
			console.log(arr)
			deleteGoods({ goods_ids: arr.toString() }).then(() => {
				loadServicesGoodsList()
			})
			loadServicesGoodsList();
		});
	}
	const batchSetEvent = () => {
		if (selectedGoodsList.value.length == 0) {
			ElMessage({
				type: 'warning',
				message: `${t('choosePlaceholder')}`
			})
			return
		}
		ElMessageBox.confirm(
			t('batchSetTips'),
			t('warning'),
			{ confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning' }
		).then(async () => {
			ElMessage({
				type: 'success',
				message: '操作成功'
			})
			loadServicesGoodsList();
		});
	}

	// 正则表达式
	const regExp = {
		required: /[\S]+/,
		number: /^\d{0,10}$/,
		digit: /^\d{0,10}(.?\d{0,2})$/,
		special: /^\d{0,10}(.?\d{0,3})$/
	}
	/**
	 * 获取服务表列表
	 */
	const loadServicesGoodsList = (page : number = 1) => {
		if (servicesGoodsTable.searchParam.start_sale_num && !regExp.digit.test(servicesGoodsTable.searchParam.start_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('startSaleNumTips')}`
			})
			return
		}
		if (servicesGoodsTable.searchParam.end_sale_num && !regExp.digit.test(servicesGoodsTable.searchParam.end_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('endSaleNumTips')}`
			})
			return
		}
		if (Number(servicesGoodsTable.searchParam.start_sale_num) > Number(servicesGoodsTable.searchParam.end_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('o2oSaleNumTips')}`
			})
			return
		}
		if (servicesGoodsTable.searchParam.start_price && !regExp.digit.test(servicesGoodsTable.searchParam.start_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('startPriceTips')}`
			})
			return
		}
		if (servicesGoodsTable.searchParam.end_price && !regExp.digit.test(servicesGoodsTable.searchParam.end_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('endPriceTips')}`
			})
			return
		}
		if (Number(servicesGoodsTable.searchParam.start_price) > Number(servicesGoodsTable.searchParam.end_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('o2oPriceTips')}`
			})
			return
		}
		servicesGoodsTable.loading = true
		servicesGoodsTable.page = page
		const searchData = cloneDeep(servicesGoodsTable.searchParam)
		getGoodsList({
			page: servicesGoodsTable.page,
			limit: servicesGoodsTable.limit,
			...searchData
		}).then(res => {
			servicesGoodsTable.loading = false
			servicesGoodsTable.data = res.data.data
			servicesGoodsTable.total = res.data.total
			setTablePageStorage(servicesGoodsTable.page, servicesGoodsTable.limit, searchData)
		}).catch(() => {
			servicesGoodsTable.loading = false
		})
	}
	loadServicesGoodsList(getTablePageStorage(servicesGoodsTable.searchParam).page)

	const router = useRouter()

	// 监听排序
	const sortChange = (event : any) => {
		let sort = ''
		if (event.order == 'ascending') {
			sort = 'asc'
		} else if (event.order == 'descending') {
			sort = 'desc'
		}
		if (sort) {
			servicesGoodsTable.searchParam.order = event.prop
			servicesGoodsTable.searchParam.sort = sort
		}
		loadServicesGoodsList()
	}

	/**
	 * 添加服务表
	 */
	const addEvent = () => {
		router.push('/home_service/goods/edit')
	}

	/**
	 * 编辑服务表
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/goods/edit?id=' + data.goods_id)
	}

	// 推广
	const spreadPopupRef = ref(null)

	const spreadEvent = (data : any) => {
		const pagePath = '/addon/home_service/user/pages/goods/detail'
		// 多参数数组
		const paramsArr = [
			{ name: 'goods_id', value: data.goods_id },
		];
		const title = '服务推广'
		const folder = 'goods'

		spreadPopupRef.value?.show(pagePath, paramsArr, title, folder);
	}

	// 复制服务
	const copyEvent = (data : any) => {
		ElMessageBox.confirm(t('goodsCopyTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(() => {
			if (repeat.value) return
			repeat.value = true

			copyGoods({
				goods_id: data.goods_id
			}).then((res : any) => {
				if (res.code == 1) {
					loadServicesGoodsList()
				}
				repeat.value = false
			}).catch(() => {
				repeat.value = false
			})
		})
	}

	/**
	 * 删除服务表
	 */
	const deleteEvent = (id : number) => {
		ElMessageBox.confirm(t('o2oGoodsDeleteTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(() => {
			deleteGoods({goods_ids:id}).then(() => {
				loadServicesGoodsList()
			}).catch(() => {
			})
		})
	}
	// 更改上下架状态
	const statusEvent = (data : any, num : number) => {
		editGoodsStatus({ goods_ids: data.goods_id, status: num }).then(() => {
			loadServicesGoodsList()
		})
	}
	// 修改排序号
	const sortInputListener = debounce((sort : any, row : any) => {
		if (isNaN(sort) || !regExp.number.test(sort)) {
			ElMessage({
				type: 'warning',
				message: `${t('sortTips')}`
			})
			return
		}
		editGoodsSort({
			goods_id: row.goods_id,
			sort
		}).then((res) => {
		})
	})

	// 当前选中tab页面
	const tabHandleClick = (event : any) => {
		servicesGoodsTable.searchParam.status = event
		loadServicesGoodsList()
	}
	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		servicesGoodsTable.searchParam.end_sale_num = ''
		servicesGoodsTable.searchParam.end_price = ''
		loadServicesGoodsList()
	}

	/** ***************** 会员价-start *************************/
	// 会员等级
	const memberLevel = ref([])
	const getMemberLevelAllFn = () => {
		getMemberLevelAll().then(res => {
			memberLevel.value = res.data ? res.data : []
		})
	}
	getMemberLevelAllFn()

	const memberPricePopupRef : any = ref(null)
	const memberPriceEvent = (data : any) => {
		memberPricePopupRef.value.show(data, memberLevel.value)
	}
	/** ***************** 会员价-end *************************/
</script>

<style lang="scss" scoped></style>