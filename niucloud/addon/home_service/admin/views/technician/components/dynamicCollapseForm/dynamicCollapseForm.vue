<template>
	<el-card class="box-card !border-none dynamicCollapseForm" shadow="never">
		<!-- 核心折叠容器 -->
		<el-collapse v-model="activeNames" :border="false"
			class="w-full p-[15px] !border-none !bg-[#f5f7fa] border-style" @change="handleCollapseChange">
			<el-collapse-item name="searchForm" class="form-collapse-item !pb-[0px] !border-none !bg-[#f5f7fa]"
				:expand-icon="null">
				<!-- 标题区 -->
				<template #title>
					<div class="flex items-center flex-nowrap w-full !bg-[#f5f7fa]" ref="titleContainer" @click.stop>
						<!-- 动态字段 -->
						<template v-for="field in visibleFields" :key="field.prop">
							<el-form-item :label="field.label" :prop="field.prop" class="mr-3 !mb-[0px] !bg-[#f5f7fa]">
								<component
								    :is="field.component"
								    v-model.trim="searchParam[field.prop]"
								    v-bind="field.props"
								    :placeholder="field.placeholder"
								  >
								    <!-- 关键：当组件是 ElSelect 时，循环生成 el-option -->
								    <template v-if="field.component.name === 'ElSelect' && field.props.options">
								      <el-option 
								        v-for="option in field.props.options" 
								        :key="option.value"
								        :label="option.label"
								        :value="option.value"
								      />
								    </template>
								  </component>
							</el-form-item>
						</template>
						
						
						<!-- 操作按钮组 -->
						<el-form-item class="ml-auto !mb-[0px] flex nowrap">
							<el-button type="primary" @click="handleSearch">{{ t('search') }}</el-button>
							<el-button @click="handleReset" class="ml-2">{{ t('reset') }}</el-button>
							<!-- 更多按钮（有折叠字段时显示） -->
							<el-button type="primary" class="ml-2" @click.stop="toggleCollapse"
								v-if="hasCollapsedFields">
								{{ isExpanded ? t('less') : t('more') }}
								<el-icon :size="12" class="ml-1">
									<ArrowUp v-if="isExpanded" />
									<ArrowDown v-else />
								</el-icon>
							</el-button>
						</el-form-item>
					</div>
				</template>

				<!-- 折叠内容区 -->
				<div class="">
					<el-form :inline="true" class="!bg-[#f5f7fa]">
						<template v-for="field in collapsedFields" :key="field.prop">
							<el-form-item :label="field.label" :prop="field.prop"
								class="mr-3 mt-[15px] !mb-[0px] !bg-[#f5f7fa]">
								<component
								    :is="field.component"
								    v-model.trim="searchParam[field.prop]"
								    v-bind="field.props"
								    :placeholder="field.placeholder"
								  >
								    <!-- 关键：当组件是 ElSelect 时，循环生成 el-option -->
								    <template v-if="field.component.name === 'ElSelect' && field.props.options">
								      <el-option 
								        v-for="option in field.props.options" 
								        :key="option.value"
								        :label="option.label"
								        :value="option.value"
								      />
								    </template>
								  </component>
							</el-form-item>
						</template>
					</el-form>
				</div>
			</el-collapse-item>
		</el-collapse>
	</el-card>
</template>

<script lang="ts" setup>
	import { ref, computed, onMounted, nextTick, watch } from 'vue'
	import { t } from '@/lang'
	import {
		ElForm, ElFormItem, ElButton, ElCollapse, ElCollapseItem, ElIcon,ElSelect,ElOption
	} from 'element-plus'
	import { ArrowUp, ArrowDown } from '@element-plus/icons-vue'

	// Props
	const props = defineProps({
		fields: { type: Array, required: true, default: () => [] },
		searchParam: { type: Object, required: true, default: () => ({}) }
	})

	// Emits
	const emit = defineEmits(['search', 'reset'])

	// 状态管理
	const activeNames = ref<string[]>([])
	const titleContainer = ref<HTMLElement | null>(null)
	const containerWidth = ref(0)
	const formRef = ref<InstanceType<typeof ElForm>>()

	// 计算是否展开（简化判断）
	const isExpanded = computed(() => activeNames.value.includes('searchForm'))

	// 修复点击冲突：阻止折叠面板默认行为干扰
	const handleCollapseChange = (newActiveNames : string[]) => {
		// 仅在有折叠字段时才允许切换（避免空点击触发）
		if (hasCollapsedFields.value) {
			activeNames.value = newActiveNames
		}
	}
	console.log(props)
	// 手动切换折叠状态（仅通过“更多”按钮控制）
	const toggleCollapse = () => {
		activeNames.value = isExpanded.value ? [] : ['searchForm']
	}

	// 窗口大小监听（保持原逻辑）
	const handleResize = () => {
		if (titleContainer.value) {
			containerWidth.value = titleContainer.value.offsetWidth
		}
		nextTick(calculateVisibleFields)
	}

	onMounted(() => {
		handleResize()
		window.addEventListener('resize', handleResize)
		return () => window.removeEventListener('resize', handleResize)
	})

	// 字段宽度计算（保持原逻辑）
	const getFieldWidth = (field : any) => {
		if (field.component.name === 'ElDatePicker') return 300
		if (field.component.name === 'ElSelect') return 200
		return 220
	}

	// 可见字段计算
	const visibleCount = ref(0)
	const calculateVisibleFields = () => {
		const buttonWidth = hasCollapsedFields.value ? 320 : 200 // 含“更多”按钮的宽度
		let remainingWidth = containerWidth.value - buttonWidth
		let count = 0

		for (const field of props.fields) {
			const fieldWidth = getFieldWidth(field)
			if (remainingWidth >= fieldWidth) {
				remainingWidth -= fieldWidth + 20
				count++
			} else break
		}

		visibleCount.value = Math.max(1, count)
	}

	// 字段拆分
	const visibleFields = computed(() => props.fields.slice(0, visibleCount.value))
	const collapsedFields = computed(() => props.fields.slice(visibleCount.value))
	const hasCollapsedFields = computed(() => collapsedFields.value.length > 0)

	// 搜索/重置
	const handleSearch = () => emit('search')
	const handleReset = () => {
		// 遍历searchParam的所有字段，根据类型清空
		Object.keys(props.searchParam).forEach(key => {
			const value = props.searchParam[key]
			if (Array.isArray(value)) {
				// 日期范围等数组类型字段，重置为空数组
				props.searchParam[key] = []
			} else {
				// 输入框等字符串类型字段，重置为空字符串
				props.searchParam[key] = ''
			}
		})
		// 触发父组件的重置回调（如刷新列表等）
		emit('reset')
	}
</script>

<style scoped>
	/* 强制隐藏默认图标（双重保险） */
	.dynamicCollapseForm ::v-deep .el-collapse-item__arrow {
		display: none !important;
	}

	.dynamicCollapseForm ::v-deep .form-collapse-item .el-collapse-item__header {
		padding: 0;
		height: auto;
		border: none !important
	}

	.dynamicCollapseForm ::v-deep .form-collapse-item .el-collapse-item__content {
		padding-top: 0;
		background-color: #f5f7fa !important;
	}

	.dynamicCollapseForm ::v-deep .el-collapse-item__wrap {
		border-bottom: none !important;
	}

	.dynamicCollapseForm.border-style {
		border-top: 1px solid #eaeaea !important;
		border-bottom: 1px solid #eaeaea !important;
	}
	.dynamicCollapseForm ::v-deep  .el-form-item__content{
		flex-wrap:nowrap !important;
	}
	.dynamicCollapseForm ::v-deep  .el-card__body{
		padding: 0 !important;
	}
</style>