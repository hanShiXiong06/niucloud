<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>
			<el-card class="box-card !border-none table-search-wrap !py-[0px]" shadow="never">
				<DynamicCollapseForm :fields="formFields" :search-param="technicianTable.searchParam"
					@search="getTechnicianFn" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="">
				<el-tabs class="" @tab-change="changeTabs" v-model="tabActive">
					<el-tab-pane class="bg-[#000]" :label="item.label" :name="item.value"
						v-for="(item,index) in statusList" :key="index">
						<el-table :data="technicianTable.data" size="large" v-loading="technicianTable.loading">
							<template #empty>
								<span>{{ !technicianTable.loading ? t("emptyData") : "" }}</span>
							</template>
							<el-table-column prop="id" :label="t('exammineID')" min-width="100" />
							<el-table-column :label="t('technicianInfo')" min-width="250" align="left">
								<template #default="{ row }">
									<div class="flex items-center cursor-pointer ">
										<el-image style="width: 70px; height: 70px"
											class="mr-[10px] rounded-[50%] w-[50%]" :src="img(row.headimg_thumb_mid)"
											fit="contain" :preview-src-list="[img(row.headimg_thumb_mid)]">
											<template #error>
												<div class="flex justify-center items-center w-full h-[70px]"><img
														class="max-w-[70px]" src="@/app/assets/images/member_head.png"
														alt="" object-fit="contain"></div>
											</template>
										</el-image>
										<div class="flex flex-col w-[50%]"  @click="toLink(row.member_id)">
											<span
												class="overflow-hidden text-ellipsis line-clamp-1 text-[14px] font-bold">{{ row.real_name || '' }}</span>
											<span class="text-[13px] text-[#999]">{{ row.mobile || '' }}</span>
											<div>
												<el-tag round type="success"
													v-if="row.level">{{row.level.level_name}}</el-tag>
											</div>
										</div>
									</div>
								</template>
							</el-table-column>
							<el-table-column :show-overflow-tooltip="true" :label="t('severceCategory')" min-width="120"
								align="left">
								<template #default="{ row,$index }">
									<div v-if="row.category_name && row.category_name.length"
										@click="showMoreCategory($index)">
										<div class="flex flex-wrap">
											<div class="mr-[20px]" v-for="(item,index) in row.category_name"
												:key="index">
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
							<el-table-column :show-overflow-tooltip="true" :label="t('sercivrsOrderCity')"
								min-width="150" align="left">
								<template #default="{ row }">
									<div class="flex flex-wrap">
										<p class="w-[100%] truncate">{{ row.province.name }} - {{row.city.name}} </p>
									</div>
								</template>
							</el-table-column>
							<!-- <el-table-column prop="id_number" :label="t('idNumber')" min-width="200" /> -->
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
							<el-table-column :show-overflow-tooltip="true" :label="t('ApplyUser')" min-width="150"
								align="left">
								<template #default="{ row }">
									<div v-if="row && row.sysUser">
										<div v-if="row.audit_status != 0">
											<div class="flex flex-wrap">
												<p class="w-[100%] truncate font-bold !text-[15px]">
													{{ row.sysUser.username }} </p>
											</div>
											<div class="flex flex-wrap">
												<p class="w-[100%] truncate text-[#ff0000]">{{ row.audit_remark}} </p>
											</div>
										</div>
									</div>
									<div class="text-[#999]" v-else>
										未分配
									</div>
								</template>
							</el-table-column>
							<el-table-column prop="status" :label="t('auditStatus')" min-width="120">
								<template #default="{ row }">
									<el-tag
										:type="row.audit_status == 1 ? 'success': row.audit_status == -1 ? 'danger' :'info'">{{ row.audit_status_name}}</el-tag>
								</template>
							</el-table-column>
							<!-- <el-table-column prop="create_time" :label="t('createTime')" min-width="180" /> -->
							<el-table-column :label="t('operation')" fixed="right" min-width="150" align="right">
								<template #default="{ row }">
									<div class="flex justify-end">
										<el-button type="primary" link v-if="row.audit_status != 0"
											@click="detailEvent(row)">详情</el-button>
										<el-button type="primary" class="!text-[#273de3]" link v-if="row.audit_status == 0"
											@click="passEvent(row)">{{ t('pass') }}</el-button>
									</div>
								</template>
							</el-table-column>
						</el-table>
					</el-tab-pane>
				</el-tabs>

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
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { useRoute, useRouter } from 'vue-router'
	import { getTechnicianapplication, deleteTechnician, getApplyStatus } from '@/addon/home_service/api/technician'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	import { ElMessageBox } from 'element-plus'

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
			real_name: '',
			nickname: '',
			mobile: '',
			create_time: [],
			audit_status: ''
		}
	})
	const searchFormRef = ref()
	const addevent = () => {
		router.push('/home_service/technician/edit')
	}
	const tabActive = ref('')
	const changeTabs = (e : any) => {
		technicianTable.searchParam.audit_status = e
		getTechnicianFn(1)  // 重新查询列表，重置到第1页
	}
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
	const showMoreCategory = (index : any) => {
		if (!technicianTable.data[index].showCategory) {
			technicianTable.data[index].showCategory = true
		} else {
			technicianTable.data[index].showCategory = false
		}
	}
	/**
	 * 获取师傅列表
	 */
	const getTechnicianFn = (page : number = 1) => {
		technicianTable.loading = true
		technicianTable.page = page

		getTechnicianapplication({
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
	const formFields = reactive([
		{
			prop: 'real_name',
			label: t('realNameForm'),
			component: ElInput,
			placeholder: t('realNameFormPlaceholder'),
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
				maxlength: 11                             // 优化：增加手机号长度限制（可选，原表单若有则加）
			}
		},
	]);

	const resetForm = (formEl : any) => {
		if (!formEl) return
		formEl.resetFields()
		getTechnicianFn()
	}
	/**
	 * 编辑
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push('/home_service/technician/edit?id=' + data.id)
	}
	const handleFormReset = () => {
		technicianTable.page = 1; // 重置页码（与原逻辑一致）
		getTechnicianFn(); // 重置后重新搜索（与原逻辑一致）
	};
	/**
	 * 编辑
	 * @param data
	 */
	const detailEvent = (data : any) => {
		router.push('/home_service/technician/examine_edit?id=' + data.id)
	}
	const errorEvent = (data : any) => {
		router.push('/home_service/technician/examine_edit?id=' + data.id)
	}
	const passEvent = (data : any) => {
		router.push('/home_service/technician/examine_edit?id=' + data.id)
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
<style lang="scss" scoped></style>