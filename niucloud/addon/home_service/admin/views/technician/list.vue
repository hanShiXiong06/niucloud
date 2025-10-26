<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" class="w-[100px]" @click="addevent">
					{{ t("addTechnician") }}
				</el-button>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="technicianTable.searchParam"
					@search="getTechnicianFn" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="technicianTable.data" size="large" v-loading="technicianTable.loading">
					<template #empty>
						<span>{{ !technicianTable.loading ? t("emptyData") : "" }}</span>
					</template>
					<el-table-column prop="id" :label="t('ID')" min-width="80" />
					<el-table-column :label="t('technicianInfo')" min-width="280" align="left">
						<template #default="{ row }">
							<div class="flex items-center cursor-pointer ">
								<el-image style="width: 70px; height: 70px" class="mr-[10px] rounded-[50%] w-[50%]"
									:src="img(row.headimg_mid)" fit="contain"
									:preview-src-list="[img(row.headimg_mid)]">
									<template #error>
										<div class="flex justify-center items-center w-full h-[70px]"><img
												class="max-w-[70px]" src="@/app/assets/images/member_head.png" alt=""
												object-fit="contain"></div>
									</template>
								</el-image>
								<div class="flex flex-col w-[50%]"  @click="toLink(row.member_id)">
									<span
										class="overflow-hidden text-ellipsis line-clamp-1 text-[14px] font-bold">{{ row.real_name || '' }}</span>
									<span class="text-[13px] text-[#999]">{{ row.mobile || '' }}</span>
									<div>
										<el-tag round type="success" v-if="row.level">{{row.level.level_name}}</el-tag>
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('shop')" min-width="180" align="left">
						<template #default="{ row }">
							<el-tag :type="row.source == 'application' ? 'info' : 'primary'"
								effect="dark">{{row.source == 'application' ?  '外部入驻' : '内部员工'}}</el-tag>
							<div v-if="row.store" class="mt-[5px] text-[13px] text-[#999]">
								{{row.store.store_name}}
							</div>
							<div class="text-[13px] text-[#999] flex items-center">
								{{timeStampTurnTime(timeTurnTimeStamp(row.create_time),'Y-M-d')}}
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('severceCategory')" min-width="150"
						align="left">
						<template #default="{ row,$index }">
							<div v-if="row.category_name && row.category_name.length" @click="showMoreCategory($index)">
								<div class="flex flex-wrap">
									<div class="mr-[20px]" v-for="(item,index) in row.category_name" :key="index">
										<p class="truncate mb-[5px]  px-[10px] text-[13px] text-[#273de3] rounded-[2px] py-[2px] bg-[#e9ecfc]"
											v-if="index <= 1">{{ item.category_name }} </p>
									</div>
								</div>
								<div class="flex flex-wrap" v-if="row.showCategory">
									<div class="" v-for="(item,index) in row.category_name" :key="index">
										<p class="truncate mb-[5px] px-[10px] text-[13px] text-[#273de3]  rounded-[2px] py-[2px] bg-[#e9ecfc]"
											v-if="index > 1">{{ item.category_name }} </p>
									</div>
								</div>
								<span v-if="row.category_name.length > 2"
									class="text-[#999] text-[11px] bg-[#f5f5f5] cursor-pointer py-[4px] px-[5px]">
									{{ row.showCategory ? '收起' : `+${row.category_name.length - 2 }` }}
								</span>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('servicesData')" min-width="240"
						align="left">
						<template #default="{ row,$index }">
							<div class="flex items-center mb-[10px]">
								<div class="text-[#273de3] bg-[#e9ecfc] text-[12px] mr-[10px] px-[6px] rounded-[4px] py-[1px] leading-[20px]">
									本月
								</div>
								<div class="text-[13px]">接单 {{row.order_num}}</div>
								<div class="flex">
									<div class="mx-[10px]">
										<el-icon color="green">
											<SuccessFilled />
										</el-icon>
									</div>
									<div class="text-[13px]">
										好评 {{row.positive_rating}}
									</div>
								</div>
							</div>
							<div class="flex items-center">
								<div
									class="text-[#999] bg-[#f2f2f5] text-[12px] mr-[10px] px-[6px] rounded-[4px] py-[1px] leading-[20px]">
									累计</div>
								<div class="text-[12px] text-[999]">{{row.service_process_total_count}}单</div>
								<div class="flex items-center">
									<div class="w-[1px] h-[12px] bg-[#c1c1c1] mx-[10px]">
									</div>
									<div class="text-[12px] text-[999]">
										{{row.evaluate_avg_scores}}分
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :label="t('commionInfo')" min-width="180">
						<template #default="{ row }">
							<div class="text-[#273de3] ">
								<!-- <el-icon><WalletFilled size="26" /></el-icon> -->
								<span class="font-bold text-[18px]">
									<span class="text-[13px]">￥</span>{{row.commission}}
								</span>
							</div>
							<div class="text-[12px] text-[#999]">
								<span class="">累计提现</span>
								<span class="">￥{{row.commission_get}}</span>
							</div>
						</template>
					</el-table-column>
					<!-- <el-table-column prop="full_address" :label="t('address')" min-width="220" /> -->
					<!-- <el-table-column prop="status" :label="t('status')" min-width="150">
						<template #default="{ row,$index }">
							<div class="flex items-center">
								<el-switch v-model="row.status" inline-prompt @change="changeTechnicianStatus($event,$index)" />
								<span class="text-[13px] pl-[10px]" :class="row.status == 1 ? 'text-[#273de3]' : ''">
									{{row.status == 1 ? '工作中' : '已下线'}}
								</span>
							</div>
						</template>
					</el-table-column> -->
					<el-table-column :label="t('orderStatus')" min-width="100">
						<template #default="{ row,$index }">
							<div v-if="row.category_name && row.category_name.length"
								@click="showMoreOrderStatus($index)">
								<div class="flex flex-wrap">
									<div v-if="Number(row.wait_check_count)">
										<p class="truncate mb-[5px] px-[10px] text-[12px] text-[#fff] rounded-[5px] py-[2px] bg-[#ff0001]"
											>
											待审核 <span class="ml-[4px]">{{row.wait_check_count}}</span> </p>
									</div>
									<div v-if="Number(row.wait_service_count)">
										<p class="truncate mb-[5px] px-[10px] text-[12px] text-[#fff] rounded-[5px] py-[2px] bg-[#273de3]"
											>
											待服务<span class="ml-[8px]">{{row.wait_service_count}}</span> </p>
									</div>
								</div>
								<!-- <div class="flex flex-wrap" v-if="row.showOrderStatus">
									<div class="" v-for="(item,index) in row.category_name"
										:key="index">
										<p class="truncate px-[10px] text-[12px] text-[#fff] mb-[5px] !rounded-[5px] py-[2px]"  :class="index == 2 ? 'bg-[#ff0001]' : 'bg-[#273de3]'"
											v-if="index > 1">{{ item.category_name }} <span class="ml-[4px]">2</span>  </p>
									</div>
								</div>
								<span v-if="row.category_name.length > 2"
									class="text-[#999] text-[11px] bg-[#f5f5f5] cursor-pointer py-[4px] px-[5px]">
									{{ row.showOrderStatus ? '收起' : `+${row.category_name.length - 2 }` }}
								</span> -->
							</div>
						</template>
					</el-table-column>

					<el-table-column prop="status" :label="t('status')" min-width="120">
						<template #default="{ row }">
							<el-tag
								:type="row.status == 1 ? 'success': row.status == -1 ? 'danger' :'info'">{{ row.status_name}}</el-tag>
						</template>
					</el-table-column>
					<el-table-column :label="t('operation')" fixed="right" min-width="120" align="right">
						<template #default="{ row }">
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
							<el-button type="primary" link @click="detailEvent(row)">详情</el-button>
						</template>
					</el-table-column>
				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="technicianTable.page" v-model:page-size="technicianTable.limit"
						layout="total, sizes, prev, pager, next, jumper" :total="technicianTable.total"
						@size-change="getTechnicianFn" @current-change="getTechnicianFn" />
				</div>
			</div>
		</el-card>
	</div>
</template>
<script lang="ts" setup>
	import { reactive, ref } from 'vue'
	import { t } from '@/lang'
	import { img, setTablePageStorage, getTablePageStorage, timeTurnTimeStamp, timeStampTurnTime } from '@/utils/common'
	import { useRoute, useRouter } from 'vue-router'
	import { getTechnicianList, deleteTechnician, getStoreList,getTechnicianSource } from '@/addon/home_service/api/technician'
	import { getLevelList } from '@/addon/home_service/api/level'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { ElMessageBox, ElSelect } from 'element-plus'

	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title
	const name : string = route.query.name || ''
	const technicianTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
		searchParam: {
			search_text: name ?? '',
			mobile: '',
			create_time: [],
			level_id: '',
			store_id: '',
			source:''
		}
	})
	const searchFormRef = ref()
	const collapseFormRef = ref(); // 组件引用（可选，用于主动调用组件方法）
	const levelList = ref([])
	const getLevelListFn = () => {
		getLevelList().then((res) => {
			if (res.data.data.length) {
				res.data.data.forEach((item, index) => {
					let obj = {
						label: item.level_name,
						value: item.level_id
					}
					levelList.value.push(obj)
				})
			}
		})
	}
	getLevelListFn()
	const technicianSource = ref([])
	const getTechnicianSourceFn = () => {
		getTechnicianSource().then((res) => {
			if (res.data) {
				Object.keys(res.data).forEach((item, index) => {
					let obj = {
						label: res.data[item],
						value: item
					}
					technicianSource.value.push(obj)
				})
			}
		})
	}
	getTechnicianSourceFn()
	
	const storeList = ref([])
	const getStoreListFn = () => {
		getStoreList().then((res) => {
			if (res.data.data.length) {
				res.data.data.forEach((item, index) => {
					let obj = {
						label: item.store_name,
						value: item.store_id
					}
					storeList.value.push(obj)
				})
			}
		})
	}
	getStoreListFn()

	// 核心：定义表单元素的配置
	const formFields = [
		{
			prop: 'search_text', // 对应searchParam的key
			label: t('name'), // 原表单label
			component: ElInput, // 表单组件类型
			placeholder: t('namePlaceholder'), // 原占位符
			props: {
				trim: true, // 原v-model.trim
			}
		},
		{
			prop: 'level_id',
			label: t('technicianLevel'),
			component: ElSelect, // 必须是导入的 ElSelect（不是字符串或错误引用）
			placeholder: t('technicianLevelPlaceholder'),
			props: {
				options: levelList.value
			}
		},
		{
			prop: 'source',
			label: t('servicesType'),
			component: ElSelect, // 必须是导入的 ElSelect（不是字符串或错误引用）
			placeholder: t('servicesTypePlaceholder'),
			props: {
				options: technicianSource.value
			}
		},
		{
			prop: 'store_id',
			label: t('registerStore'),
			component: ElSelect, // 必须是导入的 ElSelect（不是字符串或错误引用）
			placeholder: t('registerStorePlaceholder'),
			props: {
				filterable: true,
				options: storeList.value
			}
		},
		{
			prop: 'create_time',
			label: t('createTime'),
			component: ElDatePicker,
			placeholder: '', // 日期选择器无需默认占位符（用start/endPlaceholder）
			props: {
				type: 'datetimerange', // 原类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式
				startPlaceholder: t('startDate'), // 原开始占位符
				endPlaceholder: t('endDate'), // 原结束占位符
			}
		},
	];
	const changeTechnicianStatus = (e : any, index : Number) => {
		console.log(e, index)
	}
	const showMoreCategory = (index : any) => {
		if (!technicianTable.data[index].showCategory) {
			technicianTable.data[index].showCategory = true
		} else {
			technicianTable.data[index].showCategory = false
		}
	}
	const showMoreOrderStatus = (index : any) => {
		if (!technicianTable.data[index].showOrderStatus) {
			technicianTable.data[index].showOrderStatus = true
		} else {
			technicianTable.data[index].showOrderStatus = false
		}
	}

	const handleFormReset = () => {
		technicianTable.page = 1; // 重置页码（与原逻辑一致）
		getTechnicianFn(); // 重置后重新搜索（与原逻辑一致）
	};

	const addevent = () => {
		router.push('/home_service/technician/edit')
	}

	/**
	 * 获取师傅列表
	 */
	const getTechnicianFn = (page : number = 1) => {
		technicianTable.loading = true
		technicianTable.page = page
		getTechnicianList({
			page: technicianTable.page,
			limit: technicianTable.limit,
			...technicianTable.searchParam
		}).then(res => {
			technicianTable.loading = false
			technicianTable.data = res.data.data
			technicianTable.total = res.data.total
			setTablePageStorage(technicianTable.page, technicianTable.limit, technicianTable.searchParam)
		}).catch(() => {
			technicianTable.loading = false
		})
	}
	getTechnicianFn(getTablePageStorage(technicianTable.searchParam).page)
	/**
	 * 编辑
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/technician/edit?id=' + data.id)
	}
	/**
	 * 详情
	 * @param data
	 */
	const detailEvent = (data : any) => {
		router.push('/home_service/technician/detail?id=' + data.id)
	}

	const deleteEvent = (id : number) => {
		ElMessageBox.confirm('确认删除这条数据吗?', '删除',
			{
				confirmButtonText: '确认',
				cancelButtonText: '取消',
				type: 'warning'
			}
		).then(() => {
			deleteTechnician(id).then(res => {
				getTechnicianFn()
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
<style lang="scss" scoped>
	::v-deep .el-card__body{
		// padding:0px !important
	}
</style>