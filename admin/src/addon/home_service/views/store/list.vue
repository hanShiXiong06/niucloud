<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" class="w-[100px]" @click="addevent">
					{{ t("addstore") }}
				</el-button>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="storeTable.searchParam" @search="getstoreFn"
					@reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="storeTable.data" size="large" v-loading="storeTable.loading" @sort-change="sortChange">
					<template #empty>
						<span>{{ !storeTable.loading ? t("emptyData") : "" }}</span>
					</template>
					<el-table-column prop="store_id" :label="t('storeId')" min-width="80" />
					<el-table-column :show-overflow-tooltip="true" :label="t('storeInfo')" min-width="280" align="left">
						<template #default="{ row }">
							<div class="flex items-center cursor-pointer ">
								<el-image style="width: 60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
									:src="img(row.headimg_mid)" fit="contain" :preview-src-list="[img(row.headimg_mid)]">
									<template #error>
										<div class="flex justify-center items-center w-full h-[60px]"><img
												class="max-w-[60px]" src="@/app/assets/images/site_default.png" alt=""
												object-fit="contain"></div>
									</template>
								</el-image>
								<div class="flex flex-col w-[50%]">
									<span
										class="overflow-hidden text-ellipsis line-clamp-1">{{ row.store_name || '' }}</span>
									<span>{{ row.mobile || '' }}</span>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('member')" min-width="200" align="left">
						<template #default="{ row }">
							<div class="flex items-center cursor-pointer " v-if="row.contact_name"
								>
								<div class="flex flex-col w-[50%] ">
									<div class="overflow-hidden text-ellipsis line-clamp-1">
										{{row.contact_name || '' }}
									</div>
									<div class="text-[#999999]">{{ row.mobile || '' }}</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('technicanNumber')" min-width="200"
						align="left">
						<template #default="{ row }">
							<div class="overflow-hidden text-ellipsis line-clamp-1">
								{{row.technician_count || 0}}人
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('servicerado')" min-width="200"
						align="left">
						<template #default="{ row }">
							<div class="overflow-hidden text-ellipsis line-clamp-1">
								{{row.service_ratio ? row.service_ratio + '%' : '--'}}
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" prop="moneyData" :label="t('moneyData')" min-width="200" sortable="custom"
						align="left">
						<template #default="{ row }">
							<div class="flex items-center">
								<el-icon><DataLine color="#ff0000" /></el-icon>
								<div class="flex flex-col ml-[10px]">
									<div class="text-[13px]">
										本月：￥{{row.time_total_order_money || '0.00'}}
									</div>
									<div class="text-[13px] text-[#999999]">
										累计：￥{{row.achievement|| '0.00'}}
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" prop="orderData" :label="t('orderData')" min-width="200" sortable="custom"
						align="left">
						<template #default="{ row }">
							<div class="flex items-center">
								<el-icon><TrendCharts color="#273de3" /></el-icon>
								<div class="flex flex-col ml-[10px]">
									<div class="text-[13px]">
										本月：{{row.time_service_process_total_count || '0'}}单
									</div>
									<div class="text-[13px] text-[#999999]">
										累计：{{row.order_num|| '0'}}
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" prop="orderData" :label="t('ProfitData')" min-width="200" sortable="custom"
						align="left">
						<template #default="{ row }">
							<div class="flex items-center">
								<el-icon><DataAnalysis color="#f5b432" /></el-icon>
								<div class="flex flex-col ml-[10px]">
									<div class="text-[13px]">
										当前：￥{{row.commission || '0.00'}}
									</div>
									<div class="text-[13px] text-[#f5b432]">
										待结：￥{{row.total_pending_settlement_money|| '0.00'}}
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="create_time" :label="t('createTime')" min-width="200" />
					<el-table-column :label="t('operation')" fixed="right" min-width="80" align="right">
						<template #default="{ row }">
							<el-button type="primary" link @click="detailEvent(row)">详情</el-button>
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
						</template>
					</el-table-column>
				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="storeTable.page" v-model:page-size="storeTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="storeTable.total"
						@size-change="getstoreFn" @current-change="getstoreFn" />
				</div>
			</div>
		</el-card>
	</div>
</template>
<script lang="ts" setup>
	import { reactive, ref } from 'vue'
	import { t } from '@/lang'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { useRoute, useRouter } from 'vue-router'
	import { getstoreList, deletestore } from '@/addon/home_service/api/store'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { ElMessageBox } from 'element-plus'

	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title
	const nickname : string = route.query.nickname || ''
	const storeTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
		searchParam: {
			store_name: '',
			nickname: nickname ?? '',
			mobile: '',
			create_time: [],
			contact_name: '',
		}
	})
	const searchFormRef = ref()

	const addevent = () => {
		router.push('/home_service/store/add')
	}

	/**
	 * 获取门店列表
	 */
	const getstoreFn = (page : number = 1) => {
		storeTable.loading = true
		storeTable.page = page

		getstoreList({
			page: storeTable.page,
			limit: storeTable.limit,
			...storeTable.searchParam
		}).then(res => {
			storeTable.loading = false
			storeTable.data = res.data.data
			storeTable.total = res.data.total
			setTablePageStorage(storeTable.page, storeTable.limit, storeTable.searchParam)
		}).catch(() => {
			storeTable.loading = false
		})
	}
	// 监听排序
	const sortChange = (event: any) => {
		console.log(event)
	    let sort = ''
	    if (event.order == 'ascending') {
	        sort = 'asc'
	    } else if (event.order == 'descending') {
	        sort = 'desc'
	    }
	    if (sort) {
	        storeTable.searchParam.order = event.prop
	        storeTable.searchParam.sort = sort
	    }
	    getstoreFn()
	}
	getstoreFn(getTablePageStorage(storeTable.searchParam).page)
	// 适配 DynamicCollapseForm 组件的表单字段配置
	// 结构说明：与你提供的示例保持一致，component绑定组件类型，props传递组件属性
	const formFields = [
		{
			prop: 'store_name', // 对应搜索参数的key
			label: t('storeName'), // 表单标签（国际化）
			component: ElInput, // 绑定ElInput组件
			placeholder: t('storeNamePlaceholder'), // 占位符（国际化）
			props: {
				trim: true, // 保留原v-model.trim功能
				maxlength: 50 // 限制输入长度
			}
		},
		{
			prop: 'contact_name',
			label: t('contactName'),
			component: ElInput,
			placeholder: t('contactNamePlaceholder'),
			props: {
				trim: true,
			}
		},
		{
			prop: 'mobile',
			label: t('mobile'),
			component: ElInput,
			placeholder: t('mobilePlaceholder'),
			props: {
				trim: true,
				maxlength: 11, // 手机号长度限制
				// 可添加输入格式限制
				onInput: (value) => {
					// 仅允许数字输入
					return value.replace(/[^\d]/g, '');
				}
			},
			// 新增校验规则（可选，根据组件是否支持）
			rules: [
				{
					pattern: /^1[3-9]\d{9}$/,
					message: t('mobileFormatError'),
					trigger: 'blur'
				}
			]
		},
		{
			prop: 'create_time',
			label: t('createTime'),
			component: ElDatePicker, // 绑定日期选择器组件
			placeholder: '', // 日期选择器无需默认占位符
			props: {
				type: 'datetimerange', // 保留原时间范围类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式化配置
				startPlaceholder: t('startDate'), // 开始时间占位符
				endPlaceholder: t('endDate'), // 结束时间占位符
				clearable: true, // 增加清空按钮
				// 可添加日期范围限制（例如不能选择未来时间）
				disabledDate: (time) => {
					return time > new Date();
				}
			}
		}
	];

	const resetForm = (formEl : any) => {
		if (!formEl) return
		formEl.resetFields()
		getstoreFn()
	}

	const handleFormReset = () => {
		storeTable.page = 1; // 重置页码（与原逻辑一致）
		getstoreFn(); // 重置后重新搜索（与原逻辑一致）
	};

	/**
	 * 编辑
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/store/edit?id=' + data.store_id)
	}
	/**
	 * 详情
	 * @param data
	 */
	const detailEvent = (data : any) => {
		router.push('/home_service/store/detail?id=' + data.store_id)
	}

	const deleteEvent = (id : number) => {
		ElMessageBox.confirm('确认删除这条数据吗?', '删除',
			{
				confirmButtonText: '确认',
				cancelButtonText: '取消',
				type: 'warning'
			}
		).then(() => {
			deletestore(id).then(res => {
				getstoreFn()
			}).catch(() => {
			})
		})
	}
	// 跳转会员详情
	const toLink = (id : number) => {
		const url = router.resolve({
			path: '/member/detail',
			query: {
				id
			}
		})
		window.open(url.href)
	}
</script>
<style lang="scss" scoped></style>