<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">

			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" @click="addEvent">{{ t('addhelp') }}</el-button>
			</div>

			<el-card class="box-card !border-none table-search-wrap" shadow="never">
				<DynamicCollapseForm :fields="helpFormFields" :search-param="helpTableData.searchParam"
					@search="loadhelpList" @reset="handleFormReset" ref="collapseFormRef" />
			</el-card>
			<el-tabs v-model="activeName" class="demo-tabs" @tab-click="handleClick"  @tab-change="changeTab">
			    <el-tab-pane :label="item.label" :name="item.value" v-for="(item,index) in typeList" :key="index">
					<div class="mt-[10px]">
						<el-table :data="helpTableData.data" size="large" v-loading="helpTableData.loading">
							<template #empty>
								<span>{{ !helpTableData.loading ? t('emptyData') : '' }}</span>
							</template>
							<el-table-column prop="help_id" :show-overflow-tooltip="true" :label="t('ID')" width="100" />
					
							<el-table-column prop="category_name" :label="t('categoryName')" width="120" />
					
							<el-table-column prop="name" :show-overflow-tooltip="true" :label="t('title')" width="180">
								<template #default="{ row }">
								</template>
							</el-table-column>
							<el-table-column :label="t('isShow')" min-width="120" align="center">
								<template #default="{ row }">
									<span v-if="row.is_show == 1">{{ t('show') }}</span>
									<span v-if="row.is_show == 0">{{t('hidden')}}</span>
								</template>
							</el-table-column>
					
							<el-table-column prop="sort" :label="t('sort')" width="100" align="center" />
					
							<!-- <el-table-column :label="t('createTime')" min-width="180" align="center">
								<template #default="{ row }">
									{{ row.create_time || '' }}
								</template>
							</el-table-column> -->
					
							<el-table-column :label="t('operation')" fixed="right" align="right" width="130">
								<template #default="{ row }">
									<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
									<el-button type="primary" link @click="deleteEvent(row.help_id)">{{ t('delete') }}</el-button>
								</template>
							</el-table-column>
						</el-table>
						<div class="mt-[16px] flex justify-end">
							<el-pagination v-model:current-page="helpTableData.page" v-model:page-size="helpTableData.limit"
								layout="total, sizes, prev, pager, next, jumper" :total="helpTableData.total"
								@size-change="loadhelpList()" @current-change="loadhelpList" />
						</div>
					</div>
				</el-tab-pane>
			  </el-tabs>
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, computed } from 'vue'
	import { t } from '@/lang'
	import { gethelpList, deletehelp, gethelpCategoryAll,gethelpType,gethelpCategoryList } from '@/addon/home_service/api/help'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import { ElMessageBox, FormInstance } from 'element-plus'
	import { useRouter, useRoute } from 'vue-router'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	
	const route = useRoute()
	const pageName = route.meta.title
	const activeName = ref('member')
	const typeList = ref([])
	
	const gethelpTypeFn = () =>{
		gethelpType().then((res)=>{
			console.log(res)
			Object.keys(res.data).forEach((item,index)=>{
				let obj = {
					label:res.data[item],
					value:item
				}
				typeList.value.push(obj)
			})
		})
	}
	gethelpTypeFn()
	
	const helpTableData = reactive({
		page: 1,
		limit: 10,
		total: 0,
		loading: true,
		data: [],
		searchParam: {
			name: '',
			category_id: ''
		}
	})
	const changeTab = (e:any) =>{
		loadhelpList()
	}
	
	const handleFormReset = () => {
		helpTableData.page = 1; // 重置页码（与原逻辑一致）
		loadhelpList(); // 重置后重新搜索（与原逻辑一致）
	};
	const categoryList = ref([])
	const gethelpCategoryListFn = () =>{
		gethelpCategoryList().then((res)=>{
			res.data.data.forEach((item,index)=>{
				let obj = {
					label:item.category_name,
					value:item.category_id
				}
				categoryList.value.push(obj)
			})
		})
	}
	gethelpCategoryListFn()
	const searchFormRef = ref<FormInstance>()

	const helpFormFields = computed(() => [
		// 1. 标题输入框
		{
			prop: 'name',
			label: t('title'),
			component: ElInput,
			placeholder: t('titlePlaceholder'),
			props: {
				clearable: true // 显示清空按钮
			}
		},
	
		// 2. 分类下拉选择器
		{
			prop: 'category_id',
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
	const loadhelpList = (page : number = 1) => {
		helpTableData.loading = true
		helpTableData.page = page

		gethelpList({
			page: helpTableData.page,
			limit: helpTableData.limit,
			type:activeName.value,
			...helpTableData.searchParam
		}).then(res => {
			helpTableData.loading = false
			helpTableData.data = res.data.data
			helpTableData.total = res.data.total
			setTablePageStorage(helpTableData.page, helpTableData.limit, helpTableData.searchParam)
		}).catch(() => {
			helpTableData.loading = false
		})
	}
	loadhelpList(getTablePageStorage(helpTableData.searchParam).page)

	const router = useRouter()

	/**
	 * 添加文章
	 */
	const addEvent = () => {
		router.push('/home_service/help/edit')
	}

	/**
	 * 编辑文章
	 * @param data
	 */
	const editEvent = (data : any) => {
		router.push(`/home_service/help/edit?id=${data.help_id}`)
	}

	/**
	 * 删除文章
	 */
	const deleteEvent = (id : number) => {
		ElMessageBox.confirm(t('helpDeleteTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(() => {
			deletehelp(id).then(() => {
				loadhelpList()
			}).catch(() => {
			})
		})
	}

	const resetForm = (formEl : FormInstance | undefined) => {
		if (!formEl) return
		formEl.resetFields()
		loadhelpList()
	}
</script>

<style lang="scss" scoped></style>