<template>
	<div class="main-container">
		<el-card class="box-card !border-none" shadow="never">

			<div class="flex justify-between items-center">
				<span class="text-page-title">{{pageName}}</span>
				<el-button type="primary" class="w-[100px]" @click="addEvent">
					{{ t('addguarantee') }}
				</el-button>
			</div>

			<div class="mt-[20px]">
				<el-table :data="guaranteeTable.data" row-key="id" size="large"
					v-loading="guaranteeTable.loading">
					<template #empty>
						<span>{{ !guaranteeTable.loading ? t('emptyData') : '' }}</span>
					</template>
					<el-table-column prop="guarantee_title" :label="t('guaranteeName')" min-width="120" />
					<el-table-column :label="t('image')" min-width="100" align="left">
						<template #default="{ row }">
							<el-avatar v-if="row.guarantee_image" :src="img(row.guarantee_image)" />
							<!-- <img v-else class="w-[50px] h-[50px]" src="@/app/assets/images/guarantee_default.png" /> -->
						</template>
					</el-table-column>
					<el-table-column :label="t('settledguarantee')" min-width="100" align="left">
						<template #default="{ row }">
							{{row.guarantee_content}}
						</template>
					</el-table-column>
					<el-table-column :label="t('operation')" fixed="right" align="right" min-width="100">
						<template #default="{ row }">
							<el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
							<el-button type="primary" link
								@click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
						</template>
					</el-table-column>
				</el-table>
			</div>

			<guaranteeEdit ref="editGuaranteeDialog" @complete="getGuaranteeloadList" />
		</el-card>
	</div>
</template>

<script lang="ts" setup>
	import { reactive, ref } from 'vue'
	import { t } from '@/lang'
	import { getGuaranteeList, deleteGuarantee, editGuarantee } from '@/addon/home_service/api/guarantee'
	import { img, debounce } from '@/utils/common'
	import { ElMessageBox } from 'element-plus'
	import guaranteeEdit from '@/addon/home_service/views/goods/components/guarantee-edit.vue'
	import { useRoute } from 'vue-router'

	const route = useRoute()
	const pageName = route.meta.title;

	const guaranteeTable = reactive({
		loading: true,
		data: []
	})
	// 正则表达式
	const regExp = {
		required: /[\S]+/,
		number: /^\d{0,10}$/,
		digit: /^\d{0,10}(.?\d{0,2})$/,
		special: /^\d{0,10}(.?\d{0,3})$/
	}
	/**
	 * 获取 服务保障列表
	 */
	const getGuaranteeloadList = (page : number = 1) => {
		guaranteeTable.loading = true
		getGuaranteeList().then(res => {
			guaranteeTable.loading = false
			guaranteeTable.data = res.data.data
		}).catch(() => {
			guaranteeTable.loading = false
		})
	}
	getGuaranteeloadList()

	const editGuaranteeDialog : Record<string, any> | null = ref(null)

	/**
	 * 添加 服务保障
	 */
	const addEvent = () => {
		editGuaranteeDialog.value.setFormData()
		editGuaranteeDialog.value.showDialog = true
	}

	/**
	 * 编辑 服务保障
	 * @param data
	 */
	const editEvent = (data : any) => {
		editGuaranteeDialog.value.setFormData(data)
		editGuaranteeDialog.value.showDialog = true
	}

	/**
	 * 删除 服务保障
	 */
	const deleteEvent = (id : number) => {
		ElMessageBox.confirm(t('o2oGoodsguaranteeDeleteTips'), t('warning'),
			{
				confirmButtonText: t('confirm'),
				cancelButtonText: t('cancel'),
				type: 'warning',
			}
		).then(() => {
			deleteGuarantee(id).then(() => {
				getGuaranteeloadList()
			}).catch(() => {
			})
		})
	}
</script>

<style lang="scss" scoped></style>