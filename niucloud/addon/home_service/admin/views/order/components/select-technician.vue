<template>
	<el-dialog v-model="props.visible" :title="t('selectTechnican')" width="900px" @close="handleClose">
		<el-card class="box-card !border-none table-search-wrap" shadow="never">
			<DynamicCollapseForm :fields="evaluateFormFields" :search-param="technicianTable.searchParam"
				@search="getTechnicianFn" @reset="handleFormReset"
				ref="collapseFormRef" />
		</el-card>
		<!-- 标签表格 -->
		<el-table :data="technicianTable.data" size="large" v-loading="technicianTable.loading" border @selection-change="handleSelectionChange" ref="tagTable">
			<template #empty>
				<span>{{ !technicianTable.loading ? t("emptyData") : "" }}</span>
			</template>
			<el-table-column type="selection" width="50" align="center" />
			<el-table-column :label="t('technicianInfo')" min-width="280" align="left">
				<template #default="{ row }">
					<div class="flex items-center cursor-pointer ">
						<el-image style="width: 40px; height: 40px" class="mr-[10px] rounded-[50%] w-[50%]"
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
							<!-- <div>
								<el-tag round type="success" v-if="row.level">{{row.level.level_name}}</el-tag>
							</div> -->
						</div>
					</div>
				</template>
			</el-table-column>
			
			<el-table-column :show-overflow-tooltip="true" :label="t('shop')" min-width="180" align="left">
				<template #default="{ row }">
					<div class="flex">
						<el-tag :type="row.source == 'application' ? 'info' : 'primary'"
							effect="dark">{{row.source == 'application' ?  '外部入驻' : '内部员工'}}</el-tag>
						<div v-if="row.store" class="ml-[15px] text-[13px] text-[#999]">
							{{row.store.store_name}}
						</div>
					</div>
				</template>
			</el-table-column>
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
					</div>
				</template>
			</el-table-column>
			
		</el-table>
		<div class="mt-[16px] flex justify-end">
			<el-pagination v-model:current-page="technicianTable.page" v-model:page-size="technicianTable.limit"
				layout="total, prev, pager, next, jumper" :total="technicianTable.total"
				@size-change="getTechnicianFn" @current-change="getTechnicianFn" />
		</div>
		<!-- 底部按钮 -->
		<template #footer>
			<el-button @click="handleClose">
				{{ t('cancel') }}
			</el-button>
			<el-button type="primary" @click="handleConfirm" :disabled="!selectedTagId">
				{{ t('confirm') }}
			</el-button>
		</template>
	</el-dialog>
</template>

<script lang="ts" setup>
	import { ref ,reactive, watch,computed} from 'vue'
	import { t } from '@/lang'
	import { ElTable } from 'element-plus'
	import { getSelectTechnician} from '@/addon/home_service/api/order'
	import { img, setTablePageStorage, getTablePageStorage } from '@/utils/common'
	import DynamicCollapseForm from '@/addon/home_service/layout/components/dynamicCollapseForm/dynamicCollapseForm.vue';
	
	
	const handleFormReset = () => {
		technicianTable.page = 1;
		getTechnicianFn();
	};
	
	// 表单配置
	const evaluateFormFields = computed(() => [
		{
			prop: 'real_name',
			label: t('technicianName'),
			component: ElInput,
			placeholder: t('inputTechnicianName'),
			props: {
				trim: true,
				clearable: true,
				class: '!w-[230px]'
			}
		},
	]);
	// 修复1：修正标签数据类型定义（与表格字段匹配）
	interface TagItem {
		label_id : number | string  // 匹配表格的label_id
		label_name : string         // 匹配表格的label_name
		label_color : string        // 匹配表格的label_color
	}
	const technicianTable = reactive({
		page: 1,
		limit: 5,
		total: 0,
		loading: false,
		data: [],
		searchParam:{
			real_name:''
		}
	})
	
	/**
	 * 获取师傅列表
	 */
	const getTechnicianFn = (page : number = 1) => {
		technicianTable.loading = true
		technicianTable.page = page
		getSelectTechnician({
			page: technicianTable.page,
			limit: technicianTable.limit,
			...technicianTable.searchParam,
			order_id:props.otherProp.order_id,
			store_id:props.otherProp.store_id,
			category_id:props.otherProp.category_id
			
		}).then(res => {
			technicianTable.loading = false
			technicianTable.data = res.data.data
			technicianTable.total = res.data.total
			setTablePageStorage(technicianTable.page, technicianTable.limit, technicianTable.searchParam)
		}).catch(() => {
			technicianTable.loading = false
		})
	}


	// 组件props
	const props = defineProps<{
		visible : boolean
		initialTechnicianId?: number | string // 初始选中的师傅ID
		,otherProp:any
	}>()

	// 监听visible变化，当显示且有初始师傅ID时，尝试选中该师傅
	watch(() => props.visible, (newVal) => {
		getTechnicianFn(getTablePageStorage(technicianTable.searchParam).page)
		console.log(props.otherProp)
		 
		if (newVal && props.initialTechnicianId) {
			// 延迟执行，确保师傅列表已经加载完成
			setTimeout(() => {
				selectInitialTechnician(props.otherProp.initialTechnicianId);
			}, 300);
		}
	});

	// 根据师傅ID选择初始师傅
	const selectInitialTechnician = (id: number | string) => {
		if (!tagTable.value || !technicianTable.data || technicianTable.data.length === 0) {
			return;
		}

		const targetTechnician = technicianTable.data.find((item: any) => 
			String(item.id) === String(id)
		);

		if (targetTechnician) {
			isProcessing.value = true;
			try {
				tagTable.value.clearSelection();
				tagTable.value.toggleRowSelection(targetTechnician, true);
				selectedTagId.value = targetTechnician.id;
			} finally {
				isProcessing.value = false;
			}
		}
	}


	// 组件事件
	const emit = defineEmits<{
		(e : 'close') : void
		(e : 'confirm', id : number | string) : void
	}>()

	// 表格实例
	const tagTable = ref<InstanceType<typeof ElTable>>()

	// 选中的标签ID
	const selectedTagId = ref<number | string | null>(null)

	// 修复2：添加处理中标记，防止无限循环
	const isProcessing = ref(false)

	// 修复3：重构选择事件处理逻辑
	const handleSelectionChange = (selection : TagItem[]) => {
		// 如果正在处理中，直接返回
		if (isProcessing.value) return

		try {
			isProcessing.value = true  // 标记为处理中

			if (selection.length > 0) {
				const lastSelect = selection[selection.length - 1]
				// 修复4：使用正确的label_id字段
				selectedTagId.value = lastSelect.id

				// 完整单选逻辑：先清空再选中当前行
				tagTable.value?.clearSelection()
				tagTable.value?.toggleRowSelection(lastSelect, true)
			} else {
				selectedTagId.value = null
			}
		} finally {
			// 无论是否出错，都标记为处理完成
			isProcessing.value = false
		}
	}

	// 确定选择
	const handleConfirm = () => {
		if (selectedTagId.value) {
			emit('confirm', selectedTagId.value)
			handleClose()
		}
	}

	// 关闭对话框
	const handleClose = () => emit('close')
	// 计算文本颜色（根据背景色明暗自动调整）
	const getTextColor = (bgColor : string) => {
		// 简单判断颜色亮度，返回黑白文本
		const hex = bgColor.replace('#', '')
		const r = parseInt(hex.substring(0, 2), 16)
		const g = parseInt(hex.substring(2, 4), 16)
		const b = parseInt(hex.substring(4, 6), 16)
		const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
		return luminance > 0.5 ? '#000000' : '#ffffff'
	}
</script>

<style scoped>
</style>