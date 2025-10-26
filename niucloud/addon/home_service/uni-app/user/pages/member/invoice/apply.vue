<template>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()" v-if="!loading">
		<!-- 第1个模块：开票详情 -->
		<view class="bg-white m-[24rpx] rounded-lg">
			<view class="px-[30rpx] py-[30rpx]">
				<!-- 开票详情标题 -->
				<view class="text-[30rpx] font-bold mb-[30rpx]">开票详情</view>

				<!-- 抬头类型 - 使用u-radio-group单选 -->
				<view class="flex items-center mb-[10rpx]">
					<view class="text-[28rpx] w-[140rpx]">{{ t('invoiceHeaderType') }}</view>
					<u-radio-group v-model="invoiceForm.header_type" class="flex-1 flex gap-[40rpx] ml-[20rpx]"
						@change="handleHeaderTypeChange">
						<u-radio name="enterprise" :label="t('enterprise')" class="flex items-center"
							active-color="var(--primary-color)" inactive-color="#e0e0e0">
						</u-radio>
						<u-radio name="individual" :label="t('individual')" class="flex items-center"
							active-color="var(--primary-color)" inactive-color="#e0e0e0">
						</u-radio>
					</u-radio-group>
				</view>

				<!-- 发票抬头 -->
				<view class="flex items-center">
					<view class="text-[28rpx] w-[140rpx]">{{ t('invoiceHeader') }}</view>
					<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
						<input v-model="invoiceForm.header_name" class="w-full" placeholder="填写发票抬头" />
					</view>
				</view>

				<!-- 税号（仅企业模式显示） -->
				<view class="flex items-center" v-if="invoiceForm.header_type === 'enterprise'">
					<view class="text-[28rpx] w-[140rpx]">{{ t('taxNumber') }}</view>
					<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
						<input v-model="invoiceForm.tax_number" class="w-full" placeholder="填写纳税人识别号" />
					</view>
				</view>

				<!-- 发票内容 - 使用u-checkbox-group多选 -->
				<view class="flex my-[10rpx]">
					<view class="text-[28rpx] w-[140rpx]">{{ t('invoiceContent') }}</view>
					<view class="ml-[20rpx] flex-1">
						<u-checkbox-group v-model="selectedContents" class="flex flex-col"
							@change="handleContentChange">
							<u-checkbox v-for="(content, index) in defaultContents" :key="index" :name="content.value"
								class="border rounded-[8rpx] flex items-center !mt-0 !mb-[20rpx] !mr-[35rpx]"
								:label="content.label" active-color="var(--primary-color)" inactive-color="#e0e0e0"
								:class="{ 
									'border-[var(--primary-color)] bg-blue-50': selectedContents.includes(content.value),
									'border-gray-300': !selectedContents.includes(content.value)
								}">
							</u-checkbox>
						</u-checkbox-group>
						<view class="text-[24rpx] text-red-500 mt-[8rpx]" v-if="showContentError">
							请至少选择一项发票内容
						</view>
					</view>
				</view>

				<!-- 企业详细信息（仅企业模式显示） -->
				<template v-if="invoiceForm.header_type === 'enterprise'">
					<view class="flex items-center">
						<view class="text-[28rpx] w-[140rpx]">{{ t('telephone') }}</view>
						<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
							<input v-model="invoiceForm.telephone" class="w-full" placeholder="请输入电话（选填）" />
						</view>
					</view>

					<view class="flex items-center">
						<view class="text-[28rpx] w-[140rpx]">{{ t('address') }}</view>
						<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
							<input v-model="invoiceForm.address" class="w-full" placeholder="请输入注册地址（选填）" />
						</view>
					</view>

					<view class="flex items-center">
						<view class="text-[28rpx] w-[140rpx]">{{ t('bankName') }}</view>
						<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
							<input v-model="invoiceForm.bank_name" class="w-full" placeholder="请输入银行名称（选填）" />
						</view>
					</view>

					<view class="flex items-center">
						<view class="text-[28rpx] w-[140rpx]">{{ t('bankCardNumber') }}</view>
						<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
							<input v-model="invoiceForm.bank_card_number" class="w-full" placeholder="请输入银行账号（选填）" />
						</view>
					</view>
				</template>
			</view>
		</view>

		<!-- 第2个模块：发票金额 -->
		<view class="bg-white m-[24rpx] rounded-lg">
			<view class="px-[30rpx] py-[30rpx]">
				<view class="flex justify-between items-center mb-[16rpx]">
					<view class="text-[30rpx] font-bold">{{ t('invoiceAmount') }}</view>
					<view class="flex items-baseline">
						<text class="font-[DINPro,DINPro] font-medium text-[40rpx] text-[#111111]">
							{{ invoiceForm.amount }}
						</text>
						<text class="font-[PingFangSC,PingFangSC] text-[26rpx] text-[#111111] ml-[4rpx]">
							元
						</text>
					</view>
				</view>

				<view class="text-[24rpx] text-gray-500 leading-[36rpx] space-y-[10rpx] whitespace-pre-line">
					<text style="color: #FF6933;">发票金额包含上门服务订单金额与附加费用总额，不包含交通费、高速费、停车费。</text>
				</view>
			</view>
		</view>

		<!-- 第3个模块：接收方式区域 -->
		<view class="bg-white m-[24rpx] rounded-lg">
			<view class="px-[30rpx] py-[30rpx]">
				<view class="text-[30rpx] font-bold mb-[20rpx]">接收方式</view>

				<!-- 邮箱地址 -->
				<view class="flex items-center">
					<view class="text-[28rpx] w-[140rpx]">{{ t('email') }}</view>
					<view class="flex-1 border border-[#e0e0e0] rounded-[8rpx] px-[20rpx] py-[20rpx]">
						<input v-model="invoiceForm.email" class="w-full" placeholder="用于向您发送电子发票" />
					</view>
				</view>
			</view>
		</view>

		<!-- 为固定按钮留出空间 -->
		<view class="h-[120rpx]"></view>

		<!-- 提交按钮 -->
		<view class="fixed bottom-[40rpx] left-0 right-0 px-[30rpx] py-[20rpx]">
			<button class="w-full bg-[var(--primary-color)] text-white rounded-[8rpx] py-[30rpx] !rounded-lg text-[32rpx]"
				@click="submitInvoice">
				提交电子发票
			</button>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, reactive, onMounted } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { createInvoice, getInvoiceTypes, getInvoiceContents, getInvoiceHeaderTypes } from '@/addon/home_service/user/api/invoice'
	const loading = ref<boolean>(true)
	// 表单数据
	const invoiceForm = reactive({
		header_type: 'enterprise', // 抬头类型: enterprise(企业), individual(个人)
		header_name: '', // 发票抬头
		tax_number: '', // 税号
		type: 'electron_regular_invoice', // 发票类型
		content: '', // 发票内容（存储多选结果，以逗号分隔）
		amount: '0.00', // 发票金额
		email: '', // 邮箱地址
		orderIds: [] as string[], // 订单ID列表
		// 企业信息
		telephone: '', // 电话
		address: '', // 注册地址
		bank_name: '', // 银行名称
		bank_card_number: '' // 银行账号
	})

	// 发票内容相关（多选逻辑）
	const defaultContents = ref([
		// 初始为空，等待API返回数据
	])
	const selectedContents = ref<string[]>([]) // 多选值（数组类型，存储选中的发票内容value）
	const showContentError = ref(false)

	// 抬头类型切换回调
	const handleHeaderTypeChange = (value : string) => {
		invoiceForm.header_type = value
		// 切换为个人时清空企业专属字段
		if (value === 'individual') {
			invoiceForm.tax_number = ''
			invoiceForm.telephone = ''
			invoiceForm.address = ''
			invoiceForm.bank_name = ''
			invoiceForm.bank_card_number = ''
		}
	}

	// 发票内容切换回调
	const handleContentChange = (values : string[]) => {
		selectedContents.value = values
		showContentError.value = values.length === 0 // 无选中项时显示错误
	}

	// API数据
	const invoiceTypes = ref<any[]>([])
	const invoiceContents = ref<any[]>([])
	const invoiceHeaderTypes = ref<any[]>([])

	// 获取发票相关配置
	const loadInvoiceConfig = async () => {
		loading.value = true
		try {
			// 并行请求所有配置
			const [typesRes, contentsRes, headerTypesRes] = await Promise.all([
				getInvoiceTypes(),
				getInvoiceContents(),
				getInvoiceHeaderTypes()
			])

			// 处理响应数据
			if (typesRes.data.code === 0) {
				invoiceTypes.value = typesRes.data.data || []
			}
			console.log(contentsRes)
			if(Object.keys(contentsRes.data) && Object.keys(contentsRes.data).length){
				Object.keys(contentsRes.data).forEach((item,index)=>{
					let obj = {
						label:contentsRes.data[item],
						value:item
					}
					defaultContents.value.push(obj)
				})
				if (defaultContents.value.length > 0) {
					selectedContents.value = [defaultContents.value[0].value]
				}
			}
			if (headerTypesRes.data.code === 0) {
				invoiceHeaderTypes.value = headerTypesRes.data.data || []
			}
			loading.value = false
		} catch (error) {
			console.error('加载发票配置失败:', error)
			loading.value = false
		}
	}

	// 提交发票申请
	const submitInvoice = () => {
		// 表单验证
		if (!invoiceForm.header_name.trim()) {
			uni.showToast({ title: '请输入发票抬头', icon: 'none' })
			return
		}

		if (invoiceForm.header_type === 'enterprise' && !invoiceForm.tax_number.trim()) {
			uni.showToast({ title: '请输入税号', icon: 'none' })
			return
		}

		// 发票内容验证（多选逻辑：至少选择一项）
		if (selectedContents.value.length === 0) {
			showContentError.value = true
			return
		}

		if (!invoiceForm.email.trim()) {
			uni.showToast({ title: '请输入邮箱地址', icon: 'none' })
			return
		}

		// 邮箱格式验证
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
		if (!emailRegex.test(invoiceForm.email)) {
			uni.showToast({ title: '请输入正确的邮箱地址', icon: 'none' })
			return
		}

		// 更新发票内容字段（将多选数组转换为逗号分隔的字符串）
		invoiceForm.content = selectedContents.value.join(',')

		// 创建提交参数，处理orderIds数组
		const submitParams : Record<string, any> = { ...invoiceForm }

		// 处理订单ID数组格式
		if (invoiceForm.orderIds && Array.isArray(invoiceForm.orderIds)) {
			delete submitParams.orderIds
			submitParams['order_ids'] = invoiceForm.orderIds // 按后端要求格式传递
		}

		// 提交申请
		createInvoice(submitParams).then((res : any) => {
			uni.showToast({ title: '申请成功', icon: 'none' })
			setTimeout(() => {
				uni.navigateBack()
			}, 1500)
		}).catch((error : any) => {
			const errorMsg = error?.data?.msg || error?.msg || '网络错误，请重试'
			uni.showToast({ title: errorMsg, icon: 'none' })
			console.error('发票提交失败:', error)
		})
	}

	// 页面参数接收
	onLoad((data : any) => {
		// 处理订单ID列表
		if (data.orderIds) {
			try {
				invoiceForm.orderIds = JSON.parse(data.orderIds)
			} catch (error) {
				// 兼容逗号分隔的字符串格式
				if (typeof data.orderIds === 'string' && data.orderIds.includes(',')) {
					invoiceForm.orderIds = data.orderIds.split(',').map((id : string) => id.trim())
				} else {
					invoiceForm.orderIds = [data.orderIds]
				}
			}
		}

		// 处理发票金额（兼容totalAmount和amount两种参数名）
		if (data.totalAmount) {
			invoiceForm.amount = data.totalAmount
		} else if (data.amount) {
			invoiceForm.amount = data.amount
		}
	})

	// 初始化页面数据
	onMounted(() => {
		loadInvoiceConfig()
	})
</script>

<style lang="scss" scoped>
	/* 组件样式 */
	.component-class {
		--page-bg-color: #f5f5f5;
		--primary-color: #1989fa;
	}

	/* 底部安全区域适配 */
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	/* 修复u-checkbox的默认样式冲突 */
	::v-deep .u-checkbox {
		@apply flex items-center;
	}

	::v-deep .u-checkbox__icon {
		@apply mr-[8rpx];
	}
</style>