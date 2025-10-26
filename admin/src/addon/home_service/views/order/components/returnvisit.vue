<template>
	<el-dialog :title="t('handleIssueFeedback')" v-model="props.visible" width="600px" :before-close="handleClose">
		<el-form ref="formRef" :model="formData" :rules="formRules" label-width="120px">
			<!-- 问题状态 -->
			<el-form-item :label="t('pleaseSelectIssueStatus')" prop="fee_situation">
				<el-radio-group v-model="formData.fee_situation">
					<el-radio :label="item.label" v-for="(item,index) in feesituation " :value="item.value" :key="index"></el-radio>
				</el-radio-group>
			</el-form-item>
			<!-- 收费情况 -->
			<el-form-item :label="t('pleaseSelectPaymentStatus')" prop="result_feedback">
				<el-radio-group v-model="formData.result_feedback">
					<el-radio :label="item.label" v-for="(item,index) in followresult" :value="item.value" :key="index"></el-radio>
				</el-radio-group>
			</el-form-item>

			<!-- 满意程度 -->
			<el-form-item :label="t('pleaseSelectSatisfaction')" prop="satisfaction_score">
				<el-rate v-model="formData.satisfaction_score" :max="5" :allow-half="false" :disabled="false" />
			</el-form-item>

			<!-- 回访概述 -->
			<el-form-item :label="t('pleaseEnterVisitSummary')" prop="follow_summary">
				<el-input type="textarea" v-model="formData.follow_summary" :rows="4" class="!w-[216px]" :placeholder="t('pleaseEnterVisitSummary')" />
			</el-form-item>

			<!-- 客户建议 -->
			<el-form-item :label="t('pleaseEnterCustomerSuggestion')" prop="suggestion_content">
				<el-input type="textarea" v-model="formData.suggestion_content" :rows="4" class="!w-[216px]" :placeholder="t('pleaseEnterCustomerSuggestion')" />
			</el-form-item>
		</el-form>

		<template #footer>
			<el-button @click="handleCancel">{{ t('cancel') }}</el-button>
			<el-button type="primary" @click="handleConfirm">{{ t('confirm') }}</el-button>
		</template>
	</el-dialog>
</template>

<script setup lang="ts">
	import { ref, reactive, onMounted,watch  } from 'vue';
	import { ElMessage } from 'element-plus';
	import { getOrderfollowresult, getOrderfeesituation } from '@/addon/home_service/api/order'
	import { useI18n } from 'vue-i18n';
	import { t } from '@/lang'
	// 定义表单数据类型
	interface FormData {
		fee_situation : string | null; // 1:已解决, 0:未解决
		result_feedback : string | null; // 1:收费一致, 0:有出入
		satisfaction_score : number; // 1-5分
		follow_summary : string;
		suggestion_content : string;
	}
	const followresult = ref([])
	const getOrderfollowresultFn = () =>{
		getOrderfollowresult().then((res)=>{
			Object.keys(res.data).forEach((item,index)=>{
				let obj = {
					label:res.data[item],
					value:item
				}
				followresult.value.push(obj)
			})
		})
	}
	getOrderfollowresultFn()
	const feesituation = ref([])
	const getOrderfeesituationFn = () =>{
		getOrderfeesituation().then((res)=>{
			Object.keys(res.data).forEach((item,index)=>{
				let obj = {
					label:res.data[item],
					value:item
				}
				feesituation.value.push(obj)
			})
		})
	}
	getOrderfeesituationFn()
	// 组件props
	const props = defineProps({
		visible: {
			type: Boolean,
			default: false
		},
		// 用于编辑时传入初始数据
		initialData: {
			type: Object as () => Partial<FormData>,
			default: () => ({})
		}
	});

	// 组件emit
	const emit = defineEmits(['update:visible', 'confirm', 'cancel']);

	// 表单引用
	const formRef = ref<any>(null);

	// 表单数据
	const formData = reactive<FormData>({
		fee_situation: null,
		result_feedback: null,
		satisfaction_score: 0,
		follow_summary: '',
		suggestion_content: ''
	});

	// 表单验证规则
	const formRules = {
		fee_situation: [
			{ required: true, message: t('pleaseSelectIssueStatus'), trigger: 'change' }
		],
		result_feedback: [
			{ required: true, message: t('pleaseSelectPaymentStatus'), trigger: 'change' }
		],
		satisfaction_score: [
			{ required: true, message: t('pleaseSelectSatisfaction'), trigger: 'change' }
		],
		follow_summary: [
			{ required: true, message: t('pleaseEnterVisitSummary'), trigger: 'blur' },
			{ min: 1, message: t('visitSummaryAtLeast10Chars'), trigger: 'blur' }
		],
		suggestion_content: [
			{ required: true, message: t('pleaseEnterCustomerSuggestion'), trigger: 'blur' }
		],
	};

	// 初始化表单数据
	onMounted(() => {
		if (props.initialData) {
			Object.assign(formData, props.initialData);
		}
	});

	// 关闭对话框
	const handleClose = () => {
		emit('update:visible', false);
		resetForm();
	};

	// 取消按钮
	const handleCancel = () => {
		emit('cancel');
		handleClose();
	};

	// 确认按钮
	const handleConfirm = () => {
		formRef.value.validate((valid : boolean) => {
			if (valid) {
				emit('confirm', { ...formData });
				handleClose();
			} else {
				return false;
			}
		});
	};

	// 重置表单
	const resetForm = () => {
		formRef.value?.resetFields();
	};

	// 监听visible变化，当显示时可能需要重新设置初始数据
	watch(
		() => props.visible,
		(newVal) => {
			if (newVal && props.initialData) {
				Object.assign(formData, props.initialData);
			}
		}
	);
</script>

<style scoped>
	.el-form-item {
		margin-bottom: 16px;
	}
</style>