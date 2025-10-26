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
				<DynamicCollapseForm :fields="o2oGoodsFormFields" :search-param="o2oGoodsTable.searchParam"
					@search="loadO2oGoodsList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="o2oGoodsTable.data" size="large" v-loading="o2oGoodsTable.loading"
					ref="goodsListTableRef" @sort-change="sortChange">
					<template #empty>
						<span>{{ !o2oGoodsTable.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column prop="card_cover" :label="t('image')" min-width="80">
						<template #default="{ row }">
							<div class="min-w-[60px] h-[60px] flex items-center">
								<el-image v-if="row.card_image" class="w-[60px] h-[60px] rounded-[5px]"
									:src="img(row.card_image)" fit="contain">
									<template #error>
										<div class="image-slot">
											<img class="w-[60px] h-[60px]"
												src="@/addon/home_service/assets/goods_default.png" />
										</div>
									</template>
								</el-image>
								<img v-else class="w-[60px] h-[60px] rounded-[5px]"
									src="@/addon/home_service/assets/goods_default.png" fit="contain" />
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="card_name" :label="t('cardName')" min-width="140">
						<template #default="{ row }">
							<div class="whitespace-nowrap overflow-hidden text-ellipsis w-[180px]">{{ row.card_name }}
							</div>
						</template>
					</el-table-column>
					<el-table-column :label="t('goodsInfo')" min-width="200" align="left">
						<template #default="{ row }">
							<div class="flex items-center mb-2" v-for="(item,index) in row.skuList" :key="index">
								<div class="w-[45px] max-h-[45px]">
									<div class="min-w-[45px] h-[45px] flex items-center justify-center">
										<el-image v-if="item.sku_image" class="w-[45px] h-[45px] rounded-[5px]"
											:src="img(item.sku_image)" fit="contain">
											<template #error>
												<div class="image-slot">
													<img class="w-[45px] h-[45px]"
														src="@/addon/home_service/assets/goods_default.png" />
												</div>
											</template>
										</el-image>
										<img v-else class="w-[45px] h-[45px] rounded-[5px]"
											src="@/addon/home_service/assets/goods_default.png" fit="contain" />
									</div>
								</div>
								<div class="ml-2">
									<div>
										<div href="javascript:;"
											class="whitespace-nowrap overflow-hidden text-ellipsis w-[180px]"
											:title="item.sku_name">{{ item.sku_name }}</div>
									</div>
									<div class="text-[10px]">
										<el-tag class="text-[10px]"> x
											{{ item.max_use_times }}{{item.sku_unit}}</el-tag>
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="valid_type" :label="t('time')" min-width="120">
						<template #default="{ row }">
							<el-tag type="success" v-if="row.valid_type == 'monthly_card'">月卡</el-tag>
							<el-tag type="danger" v-if="row.valid_type == 'permanent_card'">永久</el-tag>
							<el-tag type="warning" v-if="row.valid_type == 'season_card'">季卡</el-tag>
							<el-tag type="success" v-if="row.valid_type == 'year_card'">年卡</el-tag>
						</template>
					</el-table-column>
					<el-table-column prop="price" :label="t('cardOncePrcies')" min-width="120" sortable="custom">
						<template #default="{ row }">
							<span>￥{{ row.price }}</span>
						</template>
					</el-table-column>
					<el-table-column prop="sale_num" :label="t('saleNum')" min-width="120" sortable="custom" />
					<el-table-column prop="virtually_sale" :label="t('sale')" min-width="120" sortable="custom" />
					<el-table-column prop="status" :label="t('status')" min-width="120">
						<template #default="{ row }">
							<span v-if="row.status == 1">{{ t('tooUp') }}</span>
							<span v-if="row.status == 0">{{ t('tooDown') }}</span>
						</template>
					</el-table-column>
					<el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
						<template #default="{ row }">
							<el-input v-model="row.sort" class="!w-[70px]" maxlength="10"
								@input="sortInputListener($event,row)" />
						</template>
					</el-table-column>
					<el-table-column :label="t('operation')" fixed="right" align="right" min-width="150">
						<template #default="{ row }">
							<el-button type="primary" link @click="spreadEvent(row)">{{ t('spreadGoods') }}</el-button>
							<el-button type="primary" link @click="statusEvent(row, 1)"
								v-if="row.status == 0">{{ t('up')}}</el-button>
							<el-button type="primary" link @click="statusEvent(row, 0)"
								v-if="row.status == 1">{{ t('down')}}</el-button>
							<el-button type="primary" link @click="memberPriceEvent(row)"
								v-if="row.member_discount">{{ t('memberPrice') }}</el-button>
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
							<el-button type="primary" link
								@click="deleteEvent(row.card_id)">{{ t('delete') }}</el-button>
						</template>
					</el-table-column>

				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="o2oGoodsTable.page" v-model:page-size="o2oGoodsTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="o2oGoodsTable.total"
						@size-change="loadO2oGoodsList()" @current-change="loadO2oGoodsList" />
				</div>
			</div>
		</el-card>
		<!-- 服务推广弹出框 -->
		<spread-popup ref="spreadPopupRef" />

		<!-- 会员价弹出框 -->
		<goods-member-price-popup ref="memberPricePopupRef" @load="loadO2oGoodsList" />
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref,computed  } from 'vue'
	import { t } from '@/lang'
	import { getCategoryTree } from '@/addon/home_service/api/category'
	import { getGoodsList, deleteGoods, editGoodsStatus, editGoodsSort, copyGoods, getvalidType } from '@/addon/home_service/api/card'
	import { debounce, img, filterDigit, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { ElMessageBox, FormInstance, ElMessage } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import { cloneDeep } from 'lodash-es'
	import { getMemberLevelAll } from '@/app/api/member'
	import goodsMemberPricePopup from '@/addon/home_service/views/card/components/goods-member-price-popup.vue'
	import spreadPopup from '@/components/spread-popup/index.vue'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';

	const route = useRoute()
	const pageName = route.meta.title
	const repeat = ref(false)
	const validTypeList = ref([])

	const getvalidTypeFn = () => {
		getvalidType().then((res) => {
			Object.keys(res.data).forEach((item) => {
				validTypeList.value.push({ value: item, label: res.data[item] })
			})
		}).catch(err => {
			ElMessage.error(t('fetchDataFailed'))
			console.error(err)
		})
	}
	getvalidTypeFn()
	const o2oGoodsTable = reactive<any>({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			card_name: '',
			status: '',
			create_time: '',
			start_sale_num: '',
			end_sale_num: '',
			sort: '',
			valid_type: ""
		}
	})

	const searchFormRef = ref<FormInstance>()

	const categoryList = reactive([])
	// 服务分类

	const checkCategory = async (row : any = null) => {
		// 查询服务分类树结构
		getCategoryTree().then((res) => {
			const data = res.data
			if (data) {
				const goodsCategoryTree : any = []
				data.forEach((item : any) => {
					const children : any = []
					if (item.children) {
						item.children.forEach((childItem : any) => {
							children.push({
								value: childItem.category_id,
								label: childItem.category_name
							})
						})
					}
					goodsCategoryTree.push({
						value: item.category_id,
						label: item.category_name,
						children
					})
				})
				categoryList.splice(0, categoryList.length, ...goodsCategoryTree)
			}
		})
	}
	checkCategory()

	const handleFormReset = () => {
		o2oGoodsTable.page = 1; // 重置页码（与原逻辑一致）
		loadO2oGoodsList(); // 重置后重新搜索（与原逻辑一致）
	};
	// 表单字段配置（适配DynamicCollapseForm组件）
	 const o2oGoodsFormFields = computed(() => [
		// 1. 服务名称（普通输入框）
		{
			prop: 'card_name',
			label: t('goodsName'),
			component: ElInput,
			placeholder: t('goodsNamePlaceholder'),
			props: {
				trim: true,
			}
		},

		// 2. 创建时间（日期范围选择器）
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

		// 3. 销量（区间输入框，双Input+数字过滤）
		{
			prop: 'sale_num_range', // 仅作为key，不直接绑定值
			label: t('saleNum'),
			fieldType: 'ranged', // 标记为区间字段
			separator: '-', // 分隔符
			subProps: {
				left: {
					prop: 'start_sale_num', // 绑定到searchParam的起始销量
					placeholder: t('startSaleNumPlaceholder'),
					maxlength: 10,
					onKeyup: filterDigit // 数字过滤事件
				},
				right: {
					prop: 'end_sale_num', // 绑定到searchParam的结束销量
					placeholder: t('endSaleNumPlaceholder'),
					maxlength: 10,
					onKeyup: filterDigit // 数字过滤事件
				}
			}
		},

		// 4. 时效期（下拉选择器）
		{
			prop: 'valid_type',
			label: t('time'),
			component: ElSelect,
			placeholder: t('请选择时效期'),
			props: {
				options: validTypeList.value, // 下拉选项数据源
				// 选项的label和value映射（对应validTypeList的title和key）
				labelKey: 'title',
				valueKey: 'key'
			}
		}
	]);

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
	const loadO2oGoodsList = (page : number = 1) => {
		if (o2oGoodsTable.searchParam.start_sale_num && !regExp.digit.test(o2oGoodsTable.searchParam.start_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('startSaleNumTips')}`
			})
			return
		}
		if (o2oGoodsTable.searchParam.end_sale_num && !regExp.digit.test(o2oGoodsTable.searchParam.end_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('endSaleNumTips')}`
			})
			return
		}
		if (Number(o2oGoodsTable.searchParam.start_sale_num) > Number(o2oGoodsTable.searchParam.end_sale_num)) {
			ElMessage({
				type: 'warning',
				message: `${t('o2oSaleNumTips')}`
			})
			return
		}
		if (o2oGoodsTable.searchParam.start_price && !regExp.digit.test(o2oGoodsTable.searchParam.start_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('startPriceTips')}`
			})
			return
		}
		if (o2oGoodsTable.searchParam.end_price && !regExp.digit.test(o2oGoodsTable.searchParam.end_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('endPriceTips')}`
			})
			return
		}
		if (Number(o2oGoodsTable.searchParam.start_price) > Number(o2oGoodsTable.searchParam.end_price)) {
			ElMessage({
				type: 'warning',
				message: `${t('o2oPriceTips')}`
			})
			return
		}
		o2oGoodsTable.loading = true
		o2oGoodsTable.page = page
		const searchData = cloneDeep(o2oGoodsTable.searchParam)
		getGoodsList({
			page: o2oGoodsTable.page,
			limit: o2oGoodsTable.limit,
			...searchData
		}).then(res => {
			o2oGoodsTable.loading = false
			o2oGoodsTable.data = res.data.data
			o2oGoodsTable.total = res.data.total
			setTablePageStorage(o2oGoodsTable.page, o2oGoodsTable.limit, searchData)
		}).catch(() => {
			o2oGoodsTable.loading = false
		})
	}
	loadO2oGoodsList(getTablePageStorage(o2oGoodsTable.searchParam).page)

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
			o2oGoodsTable.searchParam.order = event.prop
			o2oGoodsTable.searchParam.sort = sort
		}
		loadO2oGoodsList()
	}

	/**
	 * 添加服务表
	 */
	const addEvent = () => {
		router.push('/home_service/card/edit')
	}

	/**
	 * 编辑服务表
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/card/edit?id=' + data.card_id)
	}

	// 推广
	const spreadPopupRef = ref(null)

	const spreadEvent = (data : any) => {
		const pagePath = '/addon/home_service/user/pages/card/detail'
		// 多参数数组
		const paramsArr = [
			{ name: 'card_id', value: data.card_id },
		];
		const title = '次卡推广'
		const folder = 'card'

		spreadPopupRef.value?.show(pagePath, paramsArr, title, folder);
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
			deleteGoods(id).then(() => {
				loadO2oGoodsList()
			}).catch(() => {
			})
		})
	}
	// 更改上下架状态
	const statusEvent = (data : any, num : number) => {
		editGoodsStatus({ card_id: data.card_id, status: num }).then(() => {
			loadO2oGoodsList()
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
			card_id: row.card_id,
			sort
		}).then((res) => {
		})
	})

	// 当前选中tab页面
	const tabHandleClick = (event : any) => {
		o2oGoodsTable.searchParam.status = event
		loadO2oGoodsList()
	}
	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		o2oGoodsTable.searchParam.end_sale_num = ''
		o2oGoodsTable.searchParam.end_price = ''
		loadO2oGoodsList()
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