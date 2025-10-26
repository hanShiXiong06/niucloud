<template>
	<el-dialog v-model="props.visible" :title="t('selectTag')" width="600px" @close="handleClose">
		<!-- 标签表格 -->
		<el-table :data="tags" border @selection-change="handleSelectionChange" ref="tagTable">
			<el-table-column type="selection" width="50" align="center" />
			<el-table-column prop="label_id" :label="t('tagId')" width="100" align="center" />
			<el-table-column prop="label_name" :label="t('tagName')" min-width="200" />
			<el-table-column :label="t('tagColor')" width="150" align="center">
				<template #default="{ row }">
					<el-tag :style="{ backgroundColor: row.label_color, color: getTextColor(row.label_color) }"
						class="w-[80px] text-center">
						{{ row.label_color }}
					</el-tag>
				</template>
			</el-table-column>
		</el-table>

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
	import { ref } from 'vue'
	import { t } from '@/lang'
	import { ElTable } from 'element-plus'
	import { getOrderlaberList } from '@/addon/home_service/api/tags'

	// 修复1：修正标签数据类型定义（与表格字段匹配）
	interface TagItem {
		label_id : number | string  // 匹配表格的label_id
		label_name : string         // 匹配表格的label_name
		label_color : string        // 匹配表格的label_color
	}

	const tags = ref<TagItem[]>([])  // 明确类型

	// 组件props
	const props = defineProps<{
		visible : boolean
	}>()

	const getOrderlaberListFn = () => {
		getOrderlaberList().then((res) => {
			console.log(res.data)
			tags.value = res.data.data || []  // 增加空数组兜底
		})
	}
	getOrderlaberListFn()

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
				selectedTagId.value = lastSelect.label_id

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
	const handleClose = () => {
		emit('close')
		// 重置选择状态
		selectedTagId.value = null
		tagTable.value?.clearSelection()
	}

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
	/* 标签样式优化 */
	:deep(.el-tag) {
		padding: 4px 0;
		border-radius: 4px;
	}
</style>