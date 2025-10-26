<template>
	<div class="main-container pt-[20px] bg-[#fff]">
		<div class="flex ml-[18px] justify-between items-center">
			<span class="text-page-title">{{ pageName }}</span>
		</div>
		<el-form :model="formData" label-width="95px" ref="formRef" :rules="rules" class="page-form"
			v-loading="loading">
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('openEvaluate') }}</h3>
				<el-form-item prop="is_evaluate">
					<el-checkbox v-model="formData.is_evaluate" :label="t('openEvaluateSubtit')" true-label="1"
						false-label="2" />
				</el-form-item>
			</el-card>

			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('evaluateExamine') }}</h3>
				<el-form-item prop="evaluate_is_to_examine">
					<el-checkbox v-model="formData.evaluate_is_to_examine" :label="t('evaluateExamineSubItt')"
						true-label="1" false-label="2" />
				</el-form-item>
			</el-card>


			<!-- <el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('evaluateShow') }}</h3>
				<el-form-item prop="evaluate_is_show">
					<el-checkbox v-model="formData.evaluate_is_show" :label="t('evaluateShowSubtit')" true-label="1"
						false-label="2" />
				</el-form-item>
			</el-card> -->

			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('evaluateSuccess') }}</h3>
				<el-form-item prop="auto_adopt_examine_time">
					<div>
						<p class="!text-sm">
							<span>{{ t('checkOrderLeft') }}</span>
							<el-input v-model="formData.auto_adopt_examine_time" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('checkOrderRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('checkOrderBottom') }}
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="auto_adopt_examine">
					<el-checkbox v-model="formData.auto_adopt_examine" :label="t('evaluateSuccess')" true-label="1"
						false-label="2" />
				</el-form-item>
			</el-card>

		</el-form>

		<!-- 底部保存按钮（唯一按钮，避免重复提交） -->
		<div class="fixed-footer-wrap" v-if="!loading">
			<div class="fixed-footer">
				<el-button type="primary" @click="onSave">{{ t('save') }}</el-button>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
	import { ref } from 'vue'
	import { t } from '@/lang'
	import { getEvaluateConfig, setEvaluateConfig } from '@/addon/home_service/api/evaluate'
	import { useRoute } from 'vue-router'
	import { filterNumber } from '@/utils/common'
	import { ElMessage, FormInstance } from 'element-plus'

	// 路由与页面标题
	const route = useRoute()
	const pageName = route.meta.title as string

	// 表单实例引用
	const formRef = ref<FormInstance | null>(null)
	// 加载状态
	const loading = ref(false)

	// 表单数据（字符串类型，1表示true，2表示false）
	const formData = ref({
		is_evaluate: '1',
		evaluate_is_to_examine: '1',
		evaluate_is_show: '1',
		auto_adopt_examine: '2',
		auto_adopt_examine_time: '0'
	})

	// 校验规则：自动审核天数校验
	const validAutoAdoptTime = (rule : any, value : any, callback : Function) => {
		// 只有开启自动审核时才需要校验
		if (formData.value.auto_adopt_examine === '1') {
			// 确保值是字符串类型
			const strValue = typeof value === 'string' ? value.trim() : String(value || '')

			if (!strValue) {
				return callback(new Error(t('checkOrderBottom')))
			}

			const num = Number(strValue)
			if (isNaN(num)) {
				return callback(new Error(t('checkOrderBottom')))
			}

			// 检查是否为整数
			if (!Number.isInteger(num)) {
				return callback(new Error(t('checkOrderBottom')))
			}

			// 检查是否在1-10范围内
			if (num < 1 || num > 10) {
				return callback(new Error(t('checkOrderBottom')))
			}
		}
		callback()
	}

	// 表单规则配置
	const rules = ref({
		auto_adopt_examine_time: [
			{ validator: validAutoAdoptTime, trigger: ['input', 'blur', 'change'] }
		],
	})

	// 获取评价配置（初始化表单）
	const getConfigFn = () => {
		loading.value = true
		getEvaluateConfig().then(res => {
			// 合并接口返回数据到表单
			if (typeof res.data === 'object' && res.data !== null) {
				// 将布尔值转换为1/2
				formData.value = {
					...formData.value,
					...res.data,
					is_evaluate: res.data.is_evaluate ? '1' : '2',
					evaluate_is_to_examine: res.data.evaluate_is_to_examine ? '1' : '2',
					evaluate_is_show: res.data.evaluate_is_show ? '1' : '2',
					auto_adopt_examine: res.data.auto_adopt_examine ? '1' : '2'
				}
			}
			loading.value = false
		}).catch(error => {
			ElMessage.error(t('getConfigFailed') + error.message)
			loading.value = false
		})
	}

	// 初始化加载配置
	getConfigFn()

	/**
	 * 保存配置（表单提交）
	 */
	const onSave = async () => {
		if (!formRef.value) return
		try {
			// 触发表单校验
			await formRef.value.validate()

			loading.value = true
			// 处理表单数据格式：将1/2转回布尔值
			const submitData = {
				...formData.value,
				is_evaluate: formData.value.is_evaluate === '1',
				evaluate_is_to_examine: formData.value.evaluate_is_to_examine === '1',
				evaluate_is_show: formData.value.evaluate_is_show === '1',
				auto_adopt_examine: formData.value.auto_adopt_examine === '1',
				auto_adopt_examine_time: Number(formData.value.auto_adopt_examine_time)
			}

			// 提交配置到接口
			await setEvaluateConfig(submitData)
			ElMessage.success(t('saveSuccess'))

			// 重新获取配置，刷新表单
			getConfigFn()
		} catch (error) {
			// 校验失败不做处理，保留错误提示
		} finally {
			loading.value = false
		}
	}
</script>

<style lang="scss" scoped>
	.main-container {
		padding-bottom: 80px;
	}

	.fixed-footer-wrap {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background: #fff;
		padding: 15px;
		box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
		z-index: 10;
	}

	.fixed-footer {
		display: flex;
	}

	.page-form {
		padding: 0 18px;
	}

	.box-card {
		margin-bottom: 15px;
	}

	.panel-title {
		font-weight: 500;
		color: #333;
		margin-bottom: 15px;
	}
</style>