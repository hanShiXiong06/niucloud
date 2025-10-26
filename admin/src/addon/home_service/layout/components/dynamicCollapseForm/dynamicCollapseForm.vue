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
						<!-- 动态字段：区分普通字段/区间字段 -->
						<template v-for="field in visibleFields" :key="field.prop">
							<el-form-item :label="field.label" class="mr-3 !mb-[0px] !bg-[#f5f7fa]"
								:prop="field.fieldType !== 'ranged' ? field.prop : undefined">
								<!-- 1. 区间字段：双Input + 分隔符 -->
								<template v-if="field.fieldType === 'ranged'">
									<div class="region-input flex items-center" :class="field.props?.class">
										<!-- 左侧Input -->
										<el-input v-model.trim="searchParam[field.subProps.left.prop]"
											:placeholder="field.subProps.left.placeholder"
											:maxlength="field.subProps.left.maxlength"
											@keyup="field.subProps.left.onKeyup" />
										<!-- 分隔符 -->
										<span class="separator mx-2">{{ field.separator || '-' }}</span>
										<!-- 右侧Input -->
										<el-input v-model.trim="searchParam[field.subProps.right.prop]"
											:placeholder="field.subProps.right.placeholder"
											:maxlength="field.subProps.right.maxlength"
											@keyup="field.subProps.right.onKeyup" />
									</div>
								</template>

								<!-- 2. 普通字段：原有逻辑（ElInput/ElSelect/ElDatePicker等） -->
								<template v-else>
									<!-- 1. 单独处理 ElCascader -->
									<el-cascader v-if="field.component.name === 'ElCascader'"
										v-model.trim="searchParam[field.prop]" :placeholder="field.placeholder"
										:options="field.props.options" :props="field.props.props"
										:clearable="field.props.clearable || true" :class="field.props.class" />

									<!-- 2. 其他组件（ElInput/ElSelect等）保持原有逻辑 -->
									<component v-else :is="field.component" v-model.trim="searchParam[field.prop]"
										v-bind="field.props" :placeholder="field.placeholder">
										<template v-if="field.component.name === 'ElSelect' && field.props.options">
											<el-option v-for="option in field.props.options" :key="option.value"
												:label="option.label" :value="option.value" />
										</template>
									</component>
								</template>
							</el-form-item>
						</template>

						<!-- 操作按钮组（原有逻辑不变） -->
						<el-form-item class="ml-auto !mb-[0px] flex nowrap">
							<el-button type="primary" @click="handleSearch">{{ t('search') }}</el-button>
							<el-button @click="handleReset" class="ml-2">{{ t('reset') }}</el-button>
							<el-button type="primary" class="ml-2" @click.stop="toggleCollapse"
								v-if="hasCollapsedFields">
								{{ isExpanded ? t('less') : t('more') }}
								<el-icon :size="12" class="ml-1">
									<ArrowUp v-if="isExpanded" />
									<ArrowDown v-else />
								</el-icon>
							</el-button>
							<el-button type="primary" v-if="props.is_export" @click="exportSelectEvent">{{ t('export') }}</el-button>
						</el-form-item>
					</div>
				</template>

				<!-- 折叠内容区（与标题区逻辑一致：区分普通/区间字段） -->
				<div class="">
					<el-form :inline="true" class="!bg-[#f5f7fa]">
						<template v-for="field in collapsedFields" :key="field.prop">
							<el-form-item :label="field.label" class="mr-3 mt-[15px] !mb-[0px] !bg-[#f5f7fa]"
								:prop="field.fieldType !== 'ranged' ? field.prop : undefined">
								<template v-if="field.fieldType === 'ranged'">
									<div class="region-input flex items-center" :class="field.props?.class">
										<el-input v-model.trim="searchParam[field.subProps.left.prop]"
											:placeholder="field.subProps.left.placeholder"
											:maxlength="field.subProps.left.maxlength"
											@keyup="field.subProps.left.onKeyup" />
										<span class="separator mx-2">{{ field.separator || '-' }}</span>
										<el-input v-model.trim="searchParam[field.subProps.right.prop]"
											:placeholder="field.subProps.right.placeholder"
											:maxlength="field.subProps.right.maxlength"
											@keyup="field.subProps.right.onKeyup" />
									</div>
								</template>
								<template v-else>
									<!-- 单独处理 ElCascader -->
									<el-cascader v-if="field.component.name === 'ElCascader'"
										v-model.trim="searchParam[field.prop]" :placeholder="field.placeholder"
										:options="field.props.options" :props="field.props.props"
										:clearable="field.props.clearable || true" :class="field.props.class" />

									<component v-else :is="field.component" v-model.trim="searchParam[field.prop]"
										v-bind="field.props" :placeholder="field.placeholder">
										<template v-if="field.component.name === 'ElSelect' && field.props.options">
											<el-option v-for="option in field.props.options" :key="option.value"
												:label="option.label" :value="option.value" />
										</template>
									</component>
								</template>
							</el-form-item>
						</template>
					</el-form>
				</div>
			</el-collapse-item>
		</el-collapse>
	</el-card>
</template>

<script lang="ts" setup>
	import { ref, computed, onMounted, nextTick } from 'vue'
	import { t } from '@/lang'
	import {
		ElForm, ElFormItem, ElButton, ElCollapse, ElCollapseItem, ElIcon, ElSelect, ElOption, ElInput
	} from 'element-plus'
	import { ArrowUp, ArrowDown } from '@element-plus/icons-vue'

	// Props：新增区间字段相关配置
	const props = defineProps({
		fields: {
			type: Array,
			required: true,
			default: () => [],
			// 字段结构校验（可选，确保传入格式正确）
			validator: (val : any[]) => {
				return val.every(field => {
					// 普通字段：必须有component和prop
					if (field.fieldType !== 'ranged') {
						return !!field.component && !!field.prop
					}
					// 区间字段：必须有subProps（left/right）
					return !!field.subProps && !!field.subProps.left.prop && !!field.subProps.right.prop
				})
			}
		},
		searchParam: { type: Object, required: true, default: () => ({}) },
		is_export: {
		    type: Boolean, // 类型：布尔值（是否显示导出按钮）
		    required: false, // 非必传
		    default: false // 默认不显示
		  }
	})

	// Emits
	const emit = defineEmits(['search', 'reset', 'exportSelectEvent']);

	// 状态管理
	const activeNames = ref<string[]>([])
	const titleContainer = ref<HTMLElement | null>(null)
	const containerWidth = ref(0)

	// 计算是否展开
	const isExpanded = computed(() => activeNames.value.includes('searchForm'))

	// 修复点击冲突
	const handleCollapseChange = (newActiveNames : string[]) => {
		if (hasCollapsedFields.value) {
			activeNames.value = newActiveNames
		}
	}

	// 手动切换折叠状态
	const toggleCollapse = () => {
		activeNames.value = isExpanded.value ? [] : ['searchForm']
	}

	// 窗口大小监听
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

	// 字段宽度计算：新增区间字段宽度
	const getFieldWidth = (field : any) => {
		if (field.fieldType === 'ranged') return 320 // 区间字段宽度（双Input+分隔符）
		if (field.component.name === 'ElDatePicker') return 300
		if (field.component.name === 'ElSelect') return 200
		return 240 // 默认宽度（ElInput）
	}

	// 可见字段计算
	const visibleCount = ref(0)
	const calculateVisibleFields = () => {
		const buttonWidth = hasCollapsedFields.value ? 280 : 160// 按钮组宽度
		let remainingWidth = containerWidth.value - buttonWidth
		let count = 0

		for (const field of props.fields) {
			const fieldWidth = getFieldWidth(field)
			if (remainingWidth >= fieldWidth) {
				remainingWidth -= fieldWidth + 50 // 20px为字段间距
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
		Object.keys(props.searchParam).forEach(key => {
			const value = props.searchParam[key]
			props.searchParam[key] = Array.isArray(value) ? [] : ''
		})
		emit('reset')
	}
	const exportSelectEvent = () => {
		emit('exportSelectEvent')
	}
</script>

<style scoped>
	/* 原有样式不变，新增区间输入框样式 */
	.dynamicCollapseForm ::v-deep .region-input {
		display: flex;
		align-items: center;
		width: 100%;
	}

	.dynamicCollapseForm ::v-deep .region-input .el-input {
		width: 45%;
		/* 左右Input各占45%，留10%给分隔符 */
	}
	
	.dynamicCollapseForm ::v-deep .separator {
		color: #666;
		white-space: nowrap;
	}

	/* 原有样式保留 */
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

	.dynamicCollapseForm ::v-deep .el-form-item__content {
		flex-wrap: nowrap !important;
	}

	.dynamicCollapseForm ::v-deep .el-card__body {
		padding: 0 !important;
	}
</style>