<template>
	<div class="main-container pt-[20px] bg-[#fff]">
		<div class="flex ml-[18px] justify-between items-center">
			<span class="text-page-title">{{ pageName }}</span>
		</div>
		<!-- 唯一外层表单：统一管理所有字段，避免ref冲突 -->
		<el-form :model="formData" label-width="95px" ref="formRef" :rules="rules" class="page-form"
			v-loading="loading">
			<!-- 1. 关闭订单设置（1-7的正整数） -->
			<!-- <el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('closeOrderInfo') }}</h3>
				<el-form-item prop="refund_length">
					<div>
						<p class="!text-sm">
							<span>{{ t('closeOrderInfoLeft') }}</span>
							<el-input v-model="formData.refund_length" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('closeOrderInfoRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('closeOrderInfoBottom') }}
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="no_allow_refund">
					<el-checkbox v-model="formData.no_allow_refund" :label="t('closeOrderInfo')" true-label="1"
						false-label="2" />
				</el-form-item>
			</el-card> -->

			<!-- 2. 平台保价比例（百分比） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('aboutToTimeoutTime') }}</h3>
				<el-form-item prop="refund_expect_revenue_rate">
					<div>
						<p class="!text-sm">
							<span>{{ t('aboutToTimeoutTimeLeft') }}</span>
							<el-input v-model.trim="formData.refund_expect_revenue_rate" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable>
								<template #append>
									<span>%</span>
								</template>
							</el-input>
							<span>{{ t('aboutToTimeoutTimeRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('refundRateHint') }} <!-- 提示：请输入0-100之间的百分比数值 -->
						</p>
					</div>
				</el-form-item>
			</el-card>

			<!-- 3. 使用时长设置 -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('useTime') }}</h3>
				<el-form-item prop="is_auto_refund">
					<el-checkbox v-model="formData.is_auto_refund" :label="t('useTimeOut')" true-label="1"
						false-label="2" />
				</el-form-item>
			</el-card>

			<!-- 4. 订单审核设置（1-10的正整数） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('checkOrder') }}</h3>
				<el-form-item prop="refund_auto_length">
					<div>
						<p class="!text-sm">
							<span>{{ t('checkOrderLeft') }}</span>
							<el-input v-model="formData.refund_auto_length" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('checkOrderRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('checkOrderBottom') }} <!-- 提示：请输入1-10之间的正整数 -->
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="is_check">
					<el-checkbox v-model="formData.is_check" :label="t('checkOrder')" true-label="1" false-label="0" />
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
	import { getRefundConfig, setRefundConfig } from '@/addon/home_service/api/refund'
	import { useRoute } from 'vue-router'
	import { filterNumber } from '@/utils/common'
	import { ElMessage, FormInstance } from 'element-plus' // 引入Element Plus组件

	// 路由与页面标题
	const route = useRoute()
	const pageName = route.meta.title as string

	// 表单实例引用（类型声明，避免TS报错）
	const formRef = ref<FormInstance | null>(null)
	// 加载状态（提交/获取数据时显示）
	const loading = ref(false)

	// 表单数据（默认值初始化，符合常规业务场景）
	const formData = ref({
		order_auto_refund: '',
		// 1. 关闭订单设置（1-7的正整数）
		refund_length: '7',       // 第1个input默认值，符合1-7范围
		no_allow_refund: '1',     // 默认开启关闭订单功能

		// 2. 使用时长设置
		timeout_time: '10',       // 第2个input默认值
		is_auto_refund: '1',      // 默认开启使用时长限制

		// 3. 平台保价比例（百分比）
		refund_expect_revenue_rate: '30', // 默认值30%，符合百分比范围

		// 4. 订单审核设置（1-10的正整数）
		refund_auto_length: '1',  // 第4个input默认值，符合1-10范围
		is_check: '1',            // 默认关闭审核，开启后触发校验
	})

	// -------------------------- 核心校验函数 --------------------------

	/**
	 * 第1个input校验：refund_length（1-7的正整数）
	 */
	const validRefundLength = (rule : any, value : any, callback : Function) => {
		// 确保值是字符串类型，避免value.trim is not a function错误
		const strValue = typeof value === 'string' ? value.trim() : String(value || '')

		if (!strValue) {
			return callback(new Error(t('refundLengthRequired'))) // 请输入关闭订单时长
		}

		const num = Number(strValue)
		if (isNaN(num)) {
			return callback(new Error(t('pleaseEnterValidNumber'))) // 请输入有效的数字
		}

		// 检查是否为整数
		if (!Number.isInteger(num)) {
			return callback(new Error(t('pleaseEnterInteger'))) // 请输入整数
		}

		// 检查是否在1-7范围内
		if (num < 1 || num > 7) {
			return callback(new Error(t('refundLengthRange'))) // 请输入1-7之间的正整数
		}

		callback()
	}

	/**
	 * 第2个input校验：refund_expect_revenue_rate（百分比，0-100）
	 */
	const validRefundRate = (rule : any, value : any, callback : Function) => {
		// 确保值是字符串类型，避免value.trim is not a function错误
		const strValue = typeof value === 'string' ? value.trim() : String(value || '')

		if (!strValue) {
			return callback(new Error(t('refundRateRequired'))) // 请输入平台保价比例
		}

		const num = Number(strValue)
		if (isNaN(num)) {
			return callback(new Error(t('pleaseEnterValidNumber'))) // 请输入有效的数字
		}

		// 检查是否在0-100范围内
		if (num < 0 || num > 100) {
			return callback(new Error(t('refundRateRange'))) // 请输入0-100之间的数值
		}

		callback()
	}

	/**
	 * 第4个input校验：refund_auto_length（1-10的正整数）
	 */
	const validRefundAutoLength = (rule : any, value : any, callback : Function) => {
		if (formData.value.is_check !== '2') { // 开启审核时校验
			// 确保值是字符串类型，避免value.trim is not a function错误
			const strValue = typeof value === 'string' ? value.trim() : String(value || '')

			if (!strValue) {
				return callback(new Error(t('refundAutoLengthRequired'))) // 请输入订单审核时长
			}

			const num = Number(strValue)
			if (isNaN(num)) {
				return callback(new Error(t('pleaseEnterValidNumber'))) // 请输入有效的数字
			}

			// 检查是否为整数
			if (!Number.isInteger(num)) {
				return callback(new Error(t('pleaseEnterInteger'))) // 请输入整数
			}

			// 检查是否在1-10范围内
			if (num < 1 || num > 10) {
				return callback(new Error(t('refundAutoLengthRange'))) // 请输入1-10之间的正整数
			}
		}
		callback()
	}

	/**
	 * 预约日期校验：至少选择一天
	 */
	const validWeek = (rule : any, value : any, callback : Function) => {
		if (value.length === 0) {
			return callback(new Error(t('pleaseSelectReserveDay'))) // 请选择至少一天可预约日期
		}
		callback()
	}

	/**
	 * 预约时间范围校验：开始时间早于结束时间
	 */
	const validTimeRange = (rule : any, value : any, callback : Function) => {
		const { start, end } = formData.value
		if (!start) {
			return callback(new Error(t('pleaseSelectStartTime'))) // 请选择预约开始时间
		}
		if (!end) {
			return callback(new Error(t('pleaseSelectEndTime'))) // 请选择预约结束时间
		}
		callback()
	}

	/**
	 * 提前预约时长校验：正整数（避免0或负数）
	 */
	const validAdvance = (rule : any, value : any, callback : Function) => {
		// 确保值是字符串类型，避免value.trim is not a function错误
		const strValue = typeof value === 'string' ? value.trim() : String(value || '')

		if (!strValue) {
			return callback(new Error(t('pleaseEnterAdvanceTime'))) // 请输入提前预约时长
		}

		const num = Number(strValue)
		if (isNaN(num) || num <= 0) {
			return callback(new Error(t('pleaseEnterPositiveNumber'))) // 请输入有效的正整数
		}
		callback()
	}

	// -------------------------- 表单规则配置 --------------------------
	const rules = ref({
		// 第1个input：关闭订单时长（1-7的正整数）
		refund_length: [
			{ validator: validRefundLength, trigger: ['input', 'blur', 'change'] }
		],
		// 第2个input：平台保价比例（百分比）
		refund_expect_revenue_rate: [
			{ validator: validRefundRate, trigger: ['input', 'blur', 'change'] }
		],
		// 第4个input：审核时长（1-10的正整数）
		refund_auto_length: [
			{ validator: validRefundAutoLength, trigger: ['input', 'blur', 'change'] }
		],
	})

	// -------------------------- 数据请求逻辑 --------------------------
	/**
	 * 获取订单配置（初始化表单）
	 */
	const getConfigFn = () => {
		loading.value = true
		getRefundConfig().then(res => {
			// 合并接口返回数据到表单（兼容接口返回格式：数组/对象）
			if (Array.isArray(res.data)) {
				res.data.forEach(item => {
					formData.value = Object.assign(formData.value, item)
				})
			} else if (typeof res.data === 'object' && res.data !== null) {
				formData.value = Object.assign(formData.value, res.data)
			}

			// 确保数值类型正确，避免后续处理出错
			setTimeout(() => {
				if (formData.value.refund_auto) {
					formData.value.refund_auto_length = String(formData.value.refund_auto.refund_auto_length || '1')
					formData.value.is_check = formData.value.refund_auto.is_check || '1'
				}
				if (formData.value.order_auto_refund) {
					formData.value.is_auto_refund = formData.value.order_auto_refund.is_auto_refund || '1'
				}
				if (formData.value.dispatch_timeout) {
					formData.value.timeout_time = String(formData.value.dispatch_timeout.timeout_time || '10')
				}
				if (formData.value.order_refund) {
					formData.value.refund_length = String(formData.value.order_refund.refund_length || '7')
					formData.value.no_allow_refund = formData.value.order_refund.no_allow_refund || '1'
					formData.value.refund_expect_revenue_rate = String(formData.value.order_refund.refund_expect_revenue_rate || '30')
				}
				if (formData.value.order_finish) {
					formData.value.finish_length = String(formData.value.order_finish.finish_length || '1')
					formData.value.is_finish = formData.value.order_finish.is_finish || '1'
				}
				if (formData.value.reserve) {
					formData.value.interval = formData.value.reserve.interval || 30
					formData.value.advance = String(formData.value.reserve.advance || '24')
				}
			})

			loading.value = false
		}).catch(error => {
			ElMessage.error(t('getConfigFailed') + error.message) // 获取配置失败
			loading.value = false
		})
	}

	// 时间转换函数
	const timeTransition = (time : any) => {
		const arr = time.split(':')
		const num = arr[0] * 60 * 60 + arr[1] * 60
		return num
	}

	const timestampTransition = (timeStamp : any) => {
		let hour = Math.floor(timeStamp / (60 * 60))
		let minute = Math.floor(timeStamp / 60) - (hour * 60)
		hour = hour < 10 ? ('0' + hour) : hour
		minute = minute < 10 ? ('0' + minute) : minute
		return hour + ':' + minute
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
			// 处理表单数据格式
			const submitData = {
				...formData.value,
				refund_length: Number(formData.value.refund_length),
				refund_expect_revenue_rate: Number(formData.value.refund_expect_revenue_rate),
				refund_auto_length: Number(formData.value.refund_auto_length),
			}

			// 提交配置到接口
			await setRefundConfig(submitData)

			// 重新获取配置，刷新表单
			getConfigFn()
		} catch (error) {
		} finally {
			loading.value = false
		}
	}
</script>

<style lang="scss" scoped>
	/* 基础样式补充，确保布局正常 */
	.main-container {
		padding-bottom: 80px;
		/* 给底部按钮留空间 */
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

	.demo-time-range {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.input-width {
		width: 200px;
	}
</style>