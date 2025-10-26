<template>
	<div class="main-container pt-[20px] bg-[#fff]">
		<div class="flex ml-[18px] justify-between items-center">
			<span class="text-page-title">{{ pageName }}</span>
		</div>
		<!-- 唯一外层表单：统一管理所有字段，避免ref冲突 -->
		<el-form :model="formData" label-width="95px" ref="formRef" :rules="rules" class="page-form"
			v-loading="loading">
			<!-- 1. 关闭订单设置（第1个input：1-30） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('closeOrderInfo') }}</h3>
				<el-form-item prop="close_length">
					<div>
						<p class="!text-sm">
							<span>{{ t('closeOrderInfoLeft') }}</span>
							<el-input v-model.trim="formData.close_length" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('closeOrderInfoRight') }}</span>
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="is_close">
					<el-checkbox v-model="formData.is_close" :label="t('isClose')" true-label="1" false-label="2" />
				</el-form-item>
			</el-card>

			<!-- 2. 使用时长设置（第2个input：1-30） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('useTime') }}</h3>
				<el-form-item prop="timeout_time">
					<div>
						<p class="!text-sm">
							<span>{{ t('useLeft') }}</span>
							<el-input v-model.trim="formData.timeout_time" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('useRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('useBottom') }} <!-- 文案配置：请输入1-30之间的数值 -->
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="is_use">
					<el-checkbox v-model="formData.is_use" :label="t('useTimeOut')" true-label="1" false-label="2" />
				</el-form-item>
			</el-card>

			<!-- 3. 即将超时时间设置（第3个input：1-30，必校验） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('aboutToTimeoutTime') }}</h3>
				<el-form-item prop="about_to_timeout_time">
					<div>
						<p class="!text-sm">
							<span>{{ t('aboutToTimeoutTimeLeft') }}</span>
							<el-input v-model.trim="formData.about_to_timeout_time" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('aboutToTimeoutTimeRight') }}</span>
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('aboutToTimeoutTimeBottom') }} <!-- 文案配置：请输入1-30之间的数值 -->
						</p>
					</div>
				</el-form-item>
			</el-card>

			<!-- 4. 订单审核设置（第4个input：1分钟~2天=1-2880分钟，开启时必校验） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('checkOrder') }}</h3>
				<el-form-item prop="check_length">
					<div>
						<p class="!text-sm">
							<span>{{ t('checkOrderLeft') }}</span>
							<el-input v-model.trim="formData.check_length" class="!w-[120px] mx-[10px]"
								@keyup="filterNumber($event)" clearable />
							<span>{{ t('checkOrderRight') }}</span> <!-- 文案配置：分钟 -->
						</p>
						<p class="text-[12px] text-[#a9a9a9] leading-normal mt-[5px]">
							{{ t('checkOrderBottom') }} <!-- 文案配置：请输入1-2880之间的数值（1分钟~2天） -->
						</p>
					</div>
				</el-form-item>
				<el-form-item prop="is_check">
					<el-checkbox v-model="formData.is_check" :label="t('checkOrder')" true-label="1" false-label="2" />
				</el-form-item>
			</el-card>

			<!-- 5. 预约设置（删除嵌套表单，归属于外层主表单） -->
			<el-card class="box-card !border-none" shadow="never">
				<h3 class="panel-title !text-sm pl-[15px]">{{ t('yuyueSet') }}</h3>
				<div class="main-container bg-[#fff]">
					<el-card class="box-card !border-none" shadow="never">
						<!-- 预约日期选择（必选至少一天） -->
						<el-form-item :label="t('reserveTime')" prop="week">
							<el-checkbox-group v-model="formData.week">
								<el-checkbox :label="'1'">{{ t('monday') }}</el-checkbox>
								<el-checkbox :label="'2'">{{ t('tuesday') }}</el-checkbox>
								<el-checkbox :label="'3'">{{ t('wednesday') }}</el-checkbox>
								<el-checkbox :label="'4'">{{ t('thursday') }}</el-checkbox>
								<el-checkbox :label="'5'">{{ t('friday') }}</el-checkbox>
								<el-checkbox :label="'6'">{{ t('saturday') }}</el-checkbox>
								<el-checkbox :label="'7'">{{ t('sunday') }}</el-checkbox>
							</el-checkbox-group>
						</el-form-item>

						<!-- 预约时间段选择（开始时间<结束时间） -->
						<el-form-item prop="timeRange">
							<div class="demo-time-range">
								<el-time-select v-model="formData.start" class="mr-4" :placeholder="t('startTime')"
									start="00:00" step="00:15" end="23:30" />-
								<el-time-select v-model="formData.end" :placeholder="t('endTime')" start="00:15"
									step="00:15" end="23:45" />
							</div>
						</el-form-item>

						<!-- 预约时间间隔（必选） -->
						<el-form-item :label="t('reserveTimeInterval')" prop="interval">
							<el-radio-group v-model="formData.interval">
								<el-radio :label="30">30{{ t("minute") }}</el-radio>
								<el-radio :label="60">1{{ t("hour") }}</el-radio>
							</el-radio-group>
						</el-form-item>

						<!-- 提前预约时长（正整数，如1-720小时） -->
						<el-form-item :label="t('reserveEarly')" prop="advance">
							<el-input class="input-width" v-model.trim="formData.advance" @keyup="filterNumber($event)"
								maxlength="5">
								<template #append>{{ t("hour") }}</template>
							</el-input>
						</el-form-item>
					</el-card>
				</div>
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
	import { getOrderConfig, setOrderConfig } from '@/addon/home_service/api/order'
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
		// 1. 关闭订单设置
		close_length: '10',       // 第1个input默认值
		is_close: '1',            // 默认开启关闭订单功能

		// 2. 使用时长设置
		timeout_time: '10',       // 第2个input默认值
		is_use: '1',              // 默认开启使用时长限制

		// 3. 即将超时时间设置（第3个input）
		about_to_timeout_time: '5', // 默认值5，符合1-30范围

		// 4. 订单审核设置（第4个input：1-2880分钟）
		check_length: '1',       
		is_check: '1',            // 默认关闭审核，开启后触发校验

		// 5. 预约设置
		week: ['1', '2', '3', '4', '5'], // 默认工作日（周一到周五）
		start: '09:00',           // 默认预约开始时间9:00
		end: '18:00',             // 默认预约结束时间18:00
		interval: 30,             // 默认预约间隔1小时
		advance: '24'             // 默认提前24小时预约
	})

	// -------------------------- 核心校验函数 --------------------------

	/**
	 * 第2个input校验：timeout_time（开启使用时长时，1-30必输）
	 */
	const validTimeoutTime = (rule : any, value : any, callback : Function) => {
		if (formData.value.is_use !== '2') {
			if (!value.trim()) {
				return callback(new Error(t('timeoutTimePlaceholder'))) // 文案：请输入使用时长
			}
			const num = Number(value)
			if (isNaN(num)) {
				return callback(new Error(t('pleaseEnterValidNumber')))
			}
			if (num < 1 || num > 30) {
				return callback(new Error(t('useBottom'))) // 文案：请输入1-30之间的数值
			}
		}
		callback()
	}

	/**
	 * 第3个input校验：about_to_timeout_time（必输，1-30范围）
	 */
	const validAboutToTimeoutTime = (rule : any, value : any, callback : Function) => {
		// 无开关，始终校验
		if (!value.trim()) {
			return callback(new Error(t('请输入即将超时时间'))) // 文案：请输入即将超时时间
		}
		const num = Number(value)
		if (isNaN(num)) {
			return callback(new Error(t('pleaseEnterValidNumber')))
		}
		if (num < 1 || num > 90) {
			return callback(new Error(t('请输入1-90之间的数值'))) // 文案：请输入1-30之间的数值
		}
		callback()
	}

	/**
	 * 第4个input校验：check_length（开启审核时，1-2880分钟必输）
	 * 计算逻辑：2天 = 2*24*60 = 2880分钟
	 */
	const validCheckLength = (rule : any, value : any, callback : Function) => {
		if (formData.value.is_check !== '2') { // 开启审核时校验
			if (!value.trim()) {
				return callback(new Error(t('请输入订单审核时长'))) // 文案：请输入订单审核时长
			}
			const num = Number(value)
			if (isNaN(num)) {
				return callback(new Error(t('请输入正确的时间')))
			}
			if (value > 2 || value < 1) {
				return callback(new Error(t('请输入1天或2天'))) // 文案：请输入1-2880之间的数值（1分钟~2天）
			}
		}
		callback()
	}

	/**
	 * 预约日期校验：至少选择一天
	 */
	const validWeek = (rule : any, value : any, callback : Function) => {
		if (value.length === 0) {
			return callback(new Error(t('pleaseSelectReserveDay'))) // 文案：请选择至少一天可预约日期
		}
		callback()
	}

	/**
	 * 预约时间范围校验：开始时间早于结束时间
	 */
	const validTimeRange = (rule : any, value : any, callback : Function) => {
		const { start, end } = formData.value
		if (!start) {
			return callback(new Error(t('pleaseSelectStartTime'))) // 文案：请选择预约开始时间
		}
		if (!end) {
			return callback(new Error(t('pleaseSelectEndTime'))) // 文案：请选择预约结束时间
		}
		callback()
	}

	/**
	 * 提前预约时长校验：正整数（避免0或负数）
	 */
	const validAdvance = (rule : any, value : any, callback : Function) => {
		if (!value.trim()) {
			return callback(new Error(t('pleaseEnterAdvanceTime'))) // 文案：请输入提前预约时长
		}
		const num = Number(value)
		if (isNaN(num) || num <= 0) {
			return callback(new Error(t('pleaseEnterPositiveNumber'))) // 文案：请输入有效的正整数
		}
		callback()
	}

	// -------------------------- 表单规则配置 --------------------------
	const rules = ref({
		// 第1个input：关闭订单时长
		close_length: [
			{ validator: '', trigger: ['input', 'blur'] }
		],
		// 第2个input：使用时长
		timeout_time: [
			{ validator: validTimeoutTime, trigger: ['input', 'blur'] }
		],
		// 第3个input：即将超时时间
		about_to_timeout_time: [
			{ validator: validAboutToTimeoutTime, trigger: ['input', 'blur'] }
		],
		// 第4个input：审核时长
		check_length: [
			{ validator: validCheckLength, trigger: ['input', 'blur'] }
		],
		// 预约设置相关校验
		week: [
			{ validator: validWeek, trigger: ['change', 'blur'] }
		],
		timeRange: [
			{ validator: validTimeRange, trigger: ['change', 'blur'] }
		],
		interval: [
			{ required: true, message: t('pleaseSelectReserveInterval'), trigger: 'change' } // 文案：请选择预约时间间隔
		],
		advance: [
			{ validator: validAdvance, trigger: ['input', 'blur'] }
		]
	})

	// -------------------------- 数据请求逻辑 --------------------------
	/**
	 * 获取订单配置（初始化表单）
	 */
	const getConfigFn = () => {
		loading.value = true
		getOrderConfig().then(res => {
			// 合并接口返回数据到表单（兼容接口返回格式：数组/对象）
			if (Array.isArray(res.data)) {
				res.data.forEach(item => {
					formData.value = Object.assign(formData.value, item)
				})
			} else if (typeof res.data === 'object' && res.data !== null) {
				formData.value = Object.assign(formData.value, res.data)
			}
			console.log(formData.value)
			
			// 直接处理数据，不需要setTimeout，确保类型转换正确
			if (formData.value.reserve?.start) formData.value.start = timestampTransition(formData.value.reserve.start)
			if (formData.value.reserve?.end) formData.value.end = timestampTransition(formData.value.reserve.end)
			if (formData.value.check) {
				formData.value.check_length = String(formData.value.check.check_length) // 转换为字符串
				formData.value.is_check = formData.value.check.is_check
			}
			if (formData.value.dispatch_timeout) {
				formData.value.is_use = formData.value.dispatch_timeout.is_use
				formData.value.timeout_time = String(formData.value.dispatch_timeout.timeout_time) // 转换为字符串
			}
			if (formData.value.order_close) {
				formData.value.close_length = String(formData.value.order_close.close_length) // 转换为字符串
				formData.value.is_close = formData.value.order_close.is_close
			}
			if (formData.value.order_finish) {
				formData.value.finish_length = String(formData.value.order_finish.finish_length) // 转换为字符串
				formData.value.is_finish = formData.value.order_finish.is_finish
			}
			if (formData.value.order_time) {
				formData.value.about_to_timeout_time = String(formData.value.order_time.about_to_timeout_time) // 转换为字符串
			}
			if (formData.value.reserve) {
				formData.value.interval = formData.value.reserve.interval
				formData.value.advance = String(formData.value.reserve.advance) // 转换为字符串
				// 将字符串格式的week转换为数组格式，以便正确显示选中状态
				formData.value.week = formData.value?.reserve?.week && formData.value?.reserve?.week.indexOf(',') != -1 ? formData.value?.reserve?.week?.split(',') : ['1', '2', '3', '4', '5']
			}
			
			loading.value = false
		}).catch(error => {
			ElMessage.error(t('getConfigFailed') + error.message) // 文案：获取配置失败
			loading.value = false
		})
	}
	const timeTransition = (time:any) => {
	    const arr = time.split(':')
	    const num = arr[0] * 60 * 60 + arr[1] * 60
	    return num
	}
	
	const timestampTransition = (timeStamp:any) => {
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
			const isValid = await formRef.value.validate()
			if (isValid) {
				loading.value = true
				formData.value.week = formData.value.week.join(',')
				formData.value.start = timeTransition(formData.value.start)
				formData.value.end = timeTransition(formData.value.end)
				// 提交配置到接口
				await setOrderConfig(formData.value)
				// 重新获取配置，刷新表单
				getConfigFn()
			}
		} catch (error) {
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