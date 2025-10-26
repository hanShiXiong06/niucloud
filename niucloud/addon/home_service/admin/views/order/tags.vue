<template>
	<div class="">
		<el-card class="" shadow="never">
			<!-- 标题与添加按钮 -->
			<div class="flex justify-between items-center">
				<span class="text-page-title">{{ pageName }}</span>
				<el-button type="primary" @click="handleAdd">{{ t('addTags') }}</el-button>
			</div>

			<!-- 标签列表表格 -->
			<div class="mt-[10px]">
				<el-table :data="OrderlaberTableData.data" size="large" v-loading="OrderlaberTableData.loading" border>
					<template #empty>
						<span>{{ !OrderlaberTableData.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column prop="label_id" :show-overflow-tooltip="true" :label="t('ID')" width="100" />
					<el-table-column prop="label_name" :show-overflow-tooltip="true" :label="t('tagsName')"
						width="180" />
					<el-table-column :label="t('tagsColor')" min-width="120" align="center">
						<template #default="{ row }">
							<el-tag :color="row.label_color">{{ row.label_color }}</el-tag>
						</template>
					</el-table-column>
					<el-table-column :label="t('operation')" fixed="right" align="right" width="180">
						<template #default="{ row }">
							<el-button type="primary" link @click="handleEdit(row)">{{ t('edit') }}</el-button>
							<el-button type="danger" link
								@click="handleDelete(row.label_id)">{{ t('delete') }}</el-button>
						</template>
					</el-table-column>
				</el-table>

				<!-- 分页 -->
				<div class="mt-[16px] flex justify-end">
					<el-pagination v-model:current-page="OrderlaberTableData.page"
						v-model:page-size="OrderlaberTableData.limit" layout="total, sizes, prev, pager, next, jumper"
						:total="OrderlaberTableData.total" @size-change="loadOrderlaberList"
						@current-change="loadOrderlaberList" />
				</div>
			</div>

			<!-- 添加/编辑弹框 -->
			<el-dialog v-model="dialogVisible" :title="isEdit ? t('dialogTitleEdit') : t('dialogTitleAdd')"
				width="500px" :before-close="handleDialogClose">
				<el-form ref="formRef" :model="form" :rules="rules" label-width="120px" class="mt-4">
					<!-- 标签名称 -->
					<el-form-item :label="t('tagsName')" prop="label_name">
						<el-input v-model="form.label_name" :placeholder="t('placeholderTagsName')" maxlength="50" />
					</el-form-item>

					<!-- 标签颜色 -->
					<el-form-item :label="t('tagsColor')" prop="label_color">
						<el-color-picker v-model="form.label_color" :placeholder="t('placeholderTagsColor')"
							:predefine="predefineColors" />
					</el-form-item>
				</el-form>

				<template #footer>
					<div class="flex justify-end gap-2">
						<el-button @click="dialogVisible = false">{{ t('cancel') }}</el-button>
						<el-button type="primary" @click="handleFormSubmit">{{ t('confirm') }}</el-button>
					</div>
				</template>
			</el-dialog>
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref, onMounted } from 'vue'
	import { t } from '@/lang'
	import {
		getOrderlaberList,
		deleteOrderlaber,
		addOrderlaber,
		editOrderlaber
	} from '@/addon/home_service/api/tags'
	import { setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import {
		ElMessageBox,
		ElMessage,
		FormInstance,
		FormRules
	} from 'element-plus'
	import { useRoute } from 'vue-router'

	// 路由相关
	const route = useRoute()
	const pageName = route.meta.title

	// 表格数据
	const OrderlaberTableData = reactive({
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

	// 弹框相关状态
	const dialogVisible = ref(false) // 弹框显示隐藏
	const isEdit = ref(false) // 是否编辑模式
	const formRef = ref<FormInstance | null>(null) // 表单ref

	// 表单数据
	const form = reactive({
		label_id: '', // 编辑时必填
		label_name: '', // 标签名称
		label_color: '#409EFF' // 标签颜色（默认蓝色）
	})

	// 颜色选择器预定义颜色
	const predefineColors = ref([
		'#FF4500', '#FF8C00', '#FFD700', '#9ACD32', '#00CED1',
		'#1E90FF', '#9370DB', '#FF69B4', '#F08080', '#CD5C5C'
	])

	// 表单验证规则
	const rules = reactive<FormRules>({
		label_name: [
			{ required: true, message: t('labelNameRequired'), trigger: 'blur' },
			{ max: 50, message: t('labelNameMax'), trigger: 'blur' }
		],
		label_color: [
			{ required: true, message: t('labelColorRequired'), trigger: 'change' }
		]
	})

	// 页面挂载时加载列表
	onMounted(() => {
		const pageStorage = getTablePageStorage(OrderlaberTableData.searchParam)
		loadOrderlaberList(pageStorage.page)
	})

	// 加载标签列表
	const loadOrderlaberList = (page : number = 1) => {
		OrderlaberTableData.loading = true
		OrderlaberTableData.page = page

		getOrderlaberList({
			page: OrderlaberTableData.page,
			limit: OrderlaberTableData.limit,
			type: 'member', // 原代码中activeName默认'member'，暂保留
			...OrderlaberTableData.searchParam
		}).then(res => {
			OrderlaberTableData.loading = false
			OrderlaberTableData.data = res.data.data
			OrderlaberTableData.total = res.data.total
			setTablePageStorage(OrderlaberTableData.page, OrderlaberTableData.limit, OrderlaberTableData.searchParam)
		}).catch(() => {
			OrderlaberTableData.loading = false
			ElMessage.error(t('loadFailed'))
		})
	}

	// 打开添加弹框
	const handleAdd = () => {
		isEdit.value = false
		dialogVisible.value = true
		// 重置表单
		if (formRef.value) {
			formRef.value.resetFields()
		}
		// 重置表单数据（默认颜色）
		form.label_id = ''
		form.label_color = '#409EFF'
	}

	// 打开编辑弹框
	const handleEdit = (row : any) => {
		isEdit.value = true
		dialogVisible.value = true
		// 赋值表单数据
		form.label_id = row.label_id
		form.label_name = row.label_name
		form.label_color = row.label_color || '#409EFF'
	}

	// 关闭弹框回调
	const handleDialogClose = () => {
		dialogVisible.value = false
		// 重置表单
		if (formRef.value) {
			formRef.value.resetFields()
		}
	}

	// 表单提交（添加/编辑）
	const handleFormSubmit = async () => {
		if (!formRef.value) return

		// 表单验证
		try {
			await formRef.value.validate()

			// 构造请求参数
			const params = {
				label_name: form.label_name,
				label_color: form.label_color,
				...(isEdit.value && { label_id: form.label_id }) // 编辑时添加label_id
			}

			// 调用接口
			if (isEdit.value) {
				await editOrderlaber(params)
			} else {
				await addOrderlaber(params)
			}

			// 关闭弹框并刷新列表
			dialogVisible.value = false
			loadOrderlaberList(OrderlaberTableData.page)
		} catch (error) {
			// 验证失败或接口错误不处理（Element已自带提示）
		}
	}

	// 删除标签
	const handleDelete = (labelId : string | number) => {
		ElMessageBox.confirm(
			t('deleteConfirm'),
			t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning'
			}
		).then(async () => {
			await deleteOrderlaber(labelId)
			loadOrderlaberList(OrderlaberTableData.page)
		})
	}
</script>

<style lang="scss" scoped>
	.main-container {
		padding: 16px;
	}

	.box-card {
		margin-bottom: 16px;
	}

	.el-color-picker {
		width: 100%;
	}
</style>
