<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" @click="addEvent">{{ t('addhelpCategory') }}</el-button>
			</div>

			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="categoryFormFields" :search-param="categoryTableData.searchParam"
					@search="loadCategoryList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<div class="mt-[10px]">
				<el-table :data="categoryTableData.data" size="large" v-loading="categoryTableData.loading">
					<template #empty>
						<span>{{ !categoryTableData.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column prop="category_name" :label="t('name')" min-width="150" />
					<el-table-column prop="help_count" :label="t('helpNumber')" min-width="140" />
					<el-table-column prop="is_show" :label="t('isShow')" min-width="150">
						<template #default="{ row }">
							<el-tag :type="row.is_show == '1' ? 'success' : ''" effect="dark">
								{{ row.is_show == 1 ? t('show') : t('hide') }}
							</el-tag>
						</template>
					</el-table-column>

					<el-table-column prop="sort" :label="t('sort')" min-width="120" />

					<el-table-column :label="t('operation')" fixed="right" width="130" align="right">
						<template #default="{ row }">
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
							<el-button type="primary" link v-if="!row.is_builtin_data"
								@click="deleteEvent(row.category_id)">{{ t('delete') }}</el-button>
						</template>
					</el-table-column>

				</el-table>
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="categoryTableData.page"
						v-model:page-size="categoryTableData.limit" layout="total, sizes, prev, pager, next, jumper"
						:total="categoryTableData.total" @size-change="loadCategoryList()"
						@current-change="loadCategoryList" />
				</div>
			</div>

			<edit-category ref="editCategoryDialog" @complete="loadCategoryList()" />
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref,computed } from 'vue'
	import { t } from '@/lang'
	import { gethelpCategoryList, deletehelpCategory } from '@/addon/home_service/api/help'
	import { ElMessageBox, FormInstance } from 'element-plus'
	import EditCategory from '@/addon/home_service/views/help/components/edit-category.vue'
	import { debounce, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	const route = useRoute()
	const pageName = route.meta.title

	const categoryTableData = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			name: ''
		}
	})

	const searchFormRef = ref<FormInstance>()

	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadCategoryList()
	}

	const handleFormReset = () => {
		categoryTableData.page = 1; // 重置页码（与原逻辑一致）
		loadCategoryList(); // 重置后重新搜索（与原逻辑一致）
	};

	/**
	 * 获取文章分类列表
	 */
	const loadCategoryList = debounce((page : number = 1) => {
		categoryTableData.loading = true
		categoryTableData.page = page

		gethelpCategoryList({
			page: categoryTableData.page,
			limit: categoryTableData.limit,
			...categoryTableData.searchParam
		}).then(res => {
			categoryTableData.loading = false
			categoryTableData.data = res.data.data
			categoryTableData.total = res.data.total
			setTablePageStorage(categoryTableData.page, categoryTableData.limit, categoryTableData.searchParam)
		}).catch(() => {
			categoryTableData.loading = false
		})
	})
	loadCategoryList(getTablePageStorage(categoryTableData.searchParam).page)
	const editCategoryDialog : Record<string, any> | null = ref(null)

	/**
	 * 添加文章分类
	 */
	const addEvent = () => {

		editCategoryDialog.value.setFormData()
		editCategoryDialog.value.showDialog = true
	}
	
	// 表单字段配置（适配DynamicCollapseForm组件）
	 const categoryFormFields = computed(() => [
	  // 名称搜索输入框
	  {
	    prop: 'name',
	    label: t('name'),
	    component: ElInput,
	    placeholder: t('namePlaceholder'),
	    props: {
	      trim: true, // 自动去除首尾空格
	      clearable: true, // 显示清除按钮
	      prefixIcon: 'Search', // 保留搜索前缀图标
	      class: 'w-[190px]' // 保留原宽度样式
	    }
	  }
	]);

	/**
	 * 编辑文章分类
	 * @param data
	 */
	const editEvent = (data : any) => {
		editCategoryDialog.value.setFormData(data)
		editCategoryDialog.value.showDialog = true
	}

	/**
	 * 删除文章分类
	 */
	const deleteEvent = (id : number) => {
		ElMessageBox.confirm(t('helpCategoryDeleteTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(() => {
			deletehelpCategory(id).then(() => {
				loadCategoryList()
			}).catch(() => {
			})
		})
	}
</script>

<style lang="scss" scoped></style>