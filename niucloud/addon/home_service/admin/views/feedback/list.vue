<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">

			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
			</div>

			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="RefundFormFields" :search-param="RefundTableData.searchParam"
					@search="loadRefundList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="RefundTableData.data" size="large" v-loading="RefundTableData.loading"
					@selection-change="handleSelectionChange">
					<template #empty>
						<span>{{ !RefundTableData.loading ? t("emptyData") : "" }}</span>
					</template>
					<el-table-column prop="feedback_id" :label="t('ID')" min-width="80" />
					
					<el-table-column :label="t('title')" min-width="200">
						<template #default="{ row }">
							<span>{{ row.title}}</span>
						</template>
					</el-table-column>
					<el-table-column :label="t('content')" min-width="300">
						<template #default="{ row }">
							<span>{{ row.content}}</span>
						</template>
					</el-table-column>
					<el-table-column :label="t('name')" min-width="200">
						<template #default="{ row }">
							<span>{{ row.related_name}}</span>
						</template>
					</el-table-column>
					
					<el-table-column :label="t('categoryName')" min-width="100" align="center">
						<template #default="{ row }">
							<el-tag
								:type="{'store': 'warning', 'technician': 'success', 'member': 'danger'}[row.source] || 'info'"
								>
								{{ row.source_name }}
							</el-tag>
						</template>
					</el-table-column>
					
					<el-table-column :label="t('images')" min-width="500" align="center">
						<template #default="{ row }">
							<div class="flex">
								<el-image v-for="(item,index) in row.images"
								      style="width: 100px; height: 100px "
									  class="mr-[10px] rounded-lg"
								      :src="img(item)"
								      :zoom-rate="1.2"
								      :max-scale="7"
								      :min-scale="0.2"
								      :preview-src-list="[img(item)]"
								      show-progress
								      :initial-index="4"
								      fit="cover"
								    />
							</div>
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
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed } from 'vue'
	import { t } from '@/lang'
	import { getfeedbackList, getfeedbackCategoryAll } from '@/addon/home_service/api/feedback'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { ElMessageBox, FormInstance } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const pageName = route.meta.title
	const activeName = ref('')
	const categoryList  = ref([])
	const RefundTableData = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			join_status: activeName.value,
			order_no: '',
			join_create_time: '',
		}
	})

	const searchFormRef = ref<FormInstance>()

	const gethelpTypeFn = () => {
		getfeedbackCategoryAll().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = {
					label: res.data[item] ,
					value: item
				}
				categoryList .value.push(obj)
			})
			let obj = {
				label: '全部',
				value: ''
			}
			categoryList.value.unshift(obj)
		})
	}
	gethelpTypeFn()
	const handleFormReset = () => {
		RefundTableData.page = 1; // 重置页码（与原逻辑一致）
		loadRefundList(); // 重置后重新搜索（与原逻辑一致）
	};
	const RefundFormFields = computed(() => [
		{
			prop: 'source',
			label: t('categoryName'),
			component: ElSelect,
			placeholder: t('categoryIdPlaceholder'),
			props: {
				options: categoryList.value
			}
		}
	]);
	/**
	 * 获取文章列表
	 */
	const loadRefundList = (page : number = 1) => {
		RefundTableData.loading = true
		RefundTableData.page = page

		getfeedbackList({
			page: RefundTableData.page,
			limit: RefundTableData.limit,
			source: activeName.value,
			...RefundTableData.searchParam
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
		loadRefundList()
	}
	const router = useRouter()

	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadRefundList()
	}
</script>

<style lang="scss" scoped></style>