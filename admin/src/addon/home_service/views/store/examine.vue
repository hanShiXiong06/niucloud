<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>
			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="storeTable.searchParam" @search="getstoreFn"
					@reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-tabs class="" @tab-change="changeTabs" v-model="tabActive">
					<el-tab-pane class="bg-[#000]" :label="item.label" :name="item.value"
						v-for="(item,index) in statusList" :key="index">
				<el-table :data="storeTable.data" size="large" v-loading="storeTable.loading">
					<template #empty>
						<span>{{ !storeTable.loading ? t("emptyData") : "" }}</span>
					</template>
					<el-table-column prop="id" :label="t('storeNo')" min-width="100" />
					<el-table-column :show-overflow-tooltip="true" :label="t('ApplyInfo')" min-width="300" align="left">
						<template #default="{ row }">
							<div class="flex items-center cursor-pointer ">
								<el-image style="width: 60px; height: 60px" class="mr-[10px] rounded-[50%] w-[50%]"
									:src="img(row.headimg_thumb_mid)" fit="contain" :preview-src-list="[img(row.headimg_thumb_mid)]">
									<template #error>
										<div class="flex justify-center items-center w-full h-[60px]"><img
												class="max-w-[60px]" src="@/app/assets/images/site_default.png" alt=""
												object-fit="contain"></div>
									</template>
								</el-image>
								<div class="flex flex-col w-[50%]">
									<span
										class="overflow-hidden text-ellipsis line-clamp-1">{{ row.store_name || '' }}</span>
									<span class="text-[13px] text-[#999]">{{ row.full_address || '' }}</span>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('contactName')" min-width="150">
						<template #default="{ row }">
							<div class="flex items-center">
								<div class="flex flex-wrap" v-if="row.contact_name">
									<p class="w-[100%] truncate">{{ row.contact_name }}</p>
									<p class="w-[150px] truncate self-end text-[13px] text-[#999] flex items-center"><el-icon class="mr-[2px]"><PhoneFilled /></el-icon>{{ row.mobile }}</p>
								</div>
							</div>
						</template>
					</el-table-column>
					
					<el-table-column :show-overflow-tooltip="true" :label="t('imageCard')" min-width="150"
						align="left">
						<template #default="{ row }">
							<el-image style="width: 100px; height: 100px" :src="img(row.license_img_thumb_mid)" fit="contain"
								:preview-src-list="[img(row.license_img_thumb_mid)]">
								<template #error>
									<div class="flex justify-center items-center w-full h-[100px]"><img
											class="max-w-[100px]" src="@/app/assets/images/error.png" alt=""
											object-fit="contain"></div>
								</template>
							</el-image>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('submitData')" width="250"
						align="left">
						<template #default="{ row }">
							<div class="flex items-center justify-between">
								<div class="relative">
									<el-image style="width: 100px; height: 81px" :src="img(row.id_card_font_thumb_mid)"
										fit="contain" :preview-src-list="[img(row.id_card_font_thumb_mid)]">
										<template #error>
											<div class="flex justify-center items-center w-full h-[81px]"><img
													class="max-w-[81px]"
													src="@/app/assets/images/goods_default.png" alt=""
													object-fit="contain"></div>
										</template>
									</el-image>
									<div
										class="bg-black opacity-50 absolute bottom-[0px] left-[0px] w-[100px] flex z-index-99 justify-center items-center text-[12px] py-[2px] text-[#fff]">
										正
									</div>
								</div>
								<div class="relative">
									<el-image style="width: 100px; height: 81px" :src="img(row.id_card_back_thumb_mid)"
										fit="contain" :preview-src-list="[img(row.id_card_back_thumb_mid)]">
										<template #error>
											<div class="flex justify-center items-center w-full h-[81px]"><img
													class="max-w-[81px]"
													src="@/app/assets/images/icon-addon.png" alt=""
													object-fit="contain"></div>
										</template>
									</el-image>
									<div
										class="bg-black opacity-50 absolute bottom-[0px] left-[0px] w-[100px] z-index-99 flex justify-center items-center text-[12px] py-[2px] text-[#fff]">
										反
									</div>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column :show-overflow-tooltip="true" :label="t('ApplyUser')" min-width="150" align="left">
						<template #default="{ row }">
							<div v-if="row && row.sysUser">
								<div class="flex flex-wrap items-center">
									<el-icon class="mr-[4px]" color="#999999"><UserFilled  /></el-icon>
									<p class="truncate">{{ row.sysUser.username }} </p>
								</div>
							</div>
						</template>
					</el-table-column>
					<el-table-column prop="status" :label="t('auditStatus')" min-width="120">
						<template #default="{ row }">
							<el-tag
								:type="row.audit_status == 1 ? 'success': row.audit_status == -1 ? 'danger' :'info'">{{ row.audit_status_name}}</el-tag>
						</template>
					</el-table-column>
					<el-table-column prop="create_time" :label="t('createTime')" min-width="180" />
					
					<el-table-column :label="t('operation')" fixed="right" min-width="150" align="right">
						<template #default="{ row }">
							<div class="flex justify-end">
								<el-button type="primary" link v-if="row.audit_status != 0"
									@click="detailEvent(row)">详情</el-button>
								<el-button type="primary" class="!text-[#273de3]" link v-if="row.audit_status == 0"
									@click="detailEvent(row)">审核</el-button>
							</div>
						</template>
					</el-table-column>
				</el-table>
				</el-tab-pane>
				</el-tabs>
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
	import { getstoreapplication, deletestore, getApplyStatus } from '@/addon/home_service/api/store'
	import { ElMessageBox } from 'element-plus'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const router = useRouter()
	const pageName = route.meta.title
	const name : string = route.query.name || ''
	const storeTable = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: false,
		data: [],
		searchParam: {
			nickname: '',
			store_name:'',
			contact_name: '',
			mobile: '',
			create_time: [],
			audit_status: ''
		}
	})
	const searchFormRef = ref()

	const statusList = ref([])
	const getApplyStatusFn = () => {
		getApplyStatus().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = { label: res.data[item], value: item }
				statusList.value.push(obj)
			})
		})
		statusList.value.unshift({
			label: "全部",
			value: ''
		})
	}
	getApplyStatusFn()
	const tabActive = ref('')
	const changeTabs = (e : any) => {
		storeTable.searchParam.audit_status = e
		getstoreFn(1)  // 重新查询列表，重置到第1页
	}
	const handleFormReset = () => {
		storeTable.page = 1; // 重置页码（与原逻辑一致）
		getstoreFn(); // 重置后重新搜索（与原逻辑一致）
	};

	// 新表单对应的字段配置（与组件要求严格匹配）
	const formFields = [
		{
			prop: 'store_name',
			label: t('storeName'),
			component: ElInput,
			placeholder: t('storeNamePlaceholder'),
			props: {
				trim: true,
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
			prop: 'create_time',
			label: t('createTime'),
			component: ElDatePicker,
			placeholder: '', // 日期选择器无需默认占位符
			props: {
				type: 'datetimerange', // 时间范围类型
				valueFormat: 'YYYY-MM-DD HH:mm:ss', // 原格式配置
				startPlaceholder: t('startDate'),
				endPlaceholder: t('endDate'),
			}
		}
	];

	// 按钮操作配置
	const formActions = [
		{
			component: ElButton,
			props: {
				type: 'primary',
				text: t('search')
			},
			onClick: (formRef) => {
				formRef.validate((valid) => {
					if (valid) {
						getstoreFn(); // 调用原搜索方法
					}
				});
			}
		},
		{
			component: ElButton,
			props: {
				text: t('reset')
			},
			onClick: (formRef) => {
				resetForm(formRef); // 调用原重置方法
			}
		}
	];

	/**
	 * 获取门店审核列表
	 */
	const getstoreFn = (page : number = 1) => {
		storeTable.loading = true
		storeTable.page = page

		getstoreapplication({
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
	getstoreFn(getTablePageStorage(storeTable.searchParam).page)

	const resetForm = (formEl : any) => {
		if (!formEl) return
		formEl.resetFields()
		getstoreFn()
	}
	/**
	 * 编辑
	 * @param data
	 */
	const detailEvent = (data : any) => {
		router.push('/home_service/store/examine_edit?id=' + data.id)
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