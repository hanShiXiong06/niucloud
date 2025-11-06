<template>
	<view class="errand-order-form">
		<!-- 提示信息 -->
		<view class="tips-section">
			<view class="tips-icon">
				<text class="nc-iconfont nc-icon-tixing text-[32rpx]"></text>
			</view>
			<view class="tips-content">
				<view class="tips-title">跑腿代取件说明</view>
				<view class="tips-text">请填写每个包裹的取件码和选择对应服务类型</view>
			</view>
		</view>

		<!-- 订单项列表 -->
		<view class="orders-section">
			<!-- 空状态 -->
			<view class="empty-state" v-if="orderItems.length === 0">
				<text class="nc-iconfont nc-icon-kongbaiye text-[120rpx] text-[#ddd]"></text>
				<view class="empty-text">暂无包裹，点击下方按钮添加</view>
			</view>

			<!-- 包裹卡片 -->
			<view class="card order-item" v-for="(item, index) in orderItems" :key="index">
				<!-- 卡片头部 -->
				<view class="item-header">
					<view class="item-badge">
						<text class="badge-number">{{ index + 1 }}</text>
					</view>
					<text class="item-title">包裹 {{ index + 1 }}</text>
					<view class="item-actions">
						<view class="action-btn copy-btn" @click="copyItem(index)" v-if="orderItems.length > 0">
							<text class="nc-iconfont nc-icon-fuzhiV6mm text-[24rpx]"></text>
						</view>
						<view class="action-btn delete-btn" @click="confirmRemoveItem(index)" v-if="orderItems.length > 1">
							<text class="nc-iconfont nc-icon-shanchu-yuangaizhiV6xx text-[24rpx]"></text>
						</view>
					</view>
				</view>

				<!-- 表单内容 -->
				<view class="form-content">
					<!-- 服务类型选择 -->
					<view class="form-item">
						<view class="label">
							<text class="label-text">服务类型</text>
							<text class="required">*</text>
						</view>
						<picker :range="skuList" range-key="sku_name" @change="handleSkuChange($event, index)">
							<view class="picker" :class="{ 'picker-selected': item.sku_id, 'picker-error': item.showError && !item.sku_id }">
								<text class="picker-text" :class="{ 'picker-placeholder': !item.sku_name }">
									{{ item.sku_name || '请选择服务类型' }}
								</text>
								<text class="nc-iconfont nc-icon-youV6xx picker-icon"></text>
							</view>
						</picker>
						<view class="error-tip" v-if="item.showError && !item.sku_id">请选择服务类型</view>
					</view>

					<!-- 价格显示 -->
					<view class="form-item" v-if="item.sku_id">
						<view class="price-display">
							<view class="price-left">
								<text class="price-label">服务费用</text>
								<text class="price-tag">实时计费</text>
							</view>
							<view class="price-right">
								<text class="price-symbol">¥</text>
								<text class="price-value">{{ item.price }}</text>
							</view>
						</view>
					</view>

					<!-- 取件码 -->
					<view class="form-item" v-if="item.sku_id">
						<view class="label">
							<text class="label-text">取件码</text>
							<text class="required">*</text>
						</view>
						<view class="input-wrapper" :class="{ 'input-error': item.showError && !item.pickupCode.trim() }">
							<input 
								v-model="item.pickupCode" 
								class="input" 
								placeholder="请输入取件码或快递单号"
								@blur="validateItem(index)"
							/>
							<text class="nc-iconfont nc-icon-qingchu input-clear" v-if="item.pickupCode" @click="item.pickupCode = ''"></text>
						</view>
						<view class="error-tip" v-if="item.showError && !item.pickupCode.trim()">请输入取件码</view>
						<view class="input-hint" v-else>
							<text class="nc-iconfont nc-icon-tixing"></text>
							<text>请输入快递柜取件码或快递单号</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 添加包裹按钮 -->
			<view class="add-item-btn" @click="addItem">
				<text class="nc-iconfont nc-icon-jiahaoV6xx add-icon"></text>
				<text class="add-text">再添加一个包裹</text>
			</view>
		</view>

		<!-- 底部汇总栏 -->
		<view class="bottom-bar">
			<view class="summary-section">
				<view class="summary-item">
					<text class="summary-label">包裹数量</text>
					<text class="summary-value">{{ orderItems.length }} 个</text>
				</view>
				<view class="summary-divider"></view>
				<view class="summary-item">
					<text class="summary-label">合计</text>
					<view class="total-price-wrapper">
						<text class="price-symbol">¥</text>
						<text class="total-price">{{ totalPrice }}</text>
					</view>
				</view>
			</view>
			<button class="submit-btn" :class="{ disabled: !canSubmit }" :disabled="!canSubmit" @click="submitOrder">
				<text class="submit-text">{{ submitBtnText }}</text>
			</button>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'
	import { img, redirect, getToken } from '@/utils/common'
	import { useLogin } from '@/hooks/useLogin'
	import useMemberStore from '@/stores/member'
	import { cloneDeep } from 'lodash-es'



	const goodsSkuPop = ref(false);
	const callback:any = ref(null);
	const currSpec = ref({
		sku_id: '',
		name: ''
	})
	const buyNum = ref(1)
	// 会员信息
	const memberStore = useMemberStore()
	const userInfo = computed(() => memberStore.info)

	interface SkuItem {
		sku_id : number
		sku_name : string
		sku_image ?: string
		price : string
		member_price ?: string
		sku_unit ?: string
		min_buy ?: number
	}

	interface OrderItem {
		sku_id : number | null
		sku_name : string
		pickupCode : string
		price : string
		showError ?: boolean
	}

	const props = defineProps<{
		skuList : SkuItem[]
	}>()

	const emit = defineEmits<{
		submit : [data : any]
	}>()

	const contactName = ref('')
	const contactMobile = ref('')
	const orderItems = ref<OrderItem[]>([
		{
			sku_id: null,
			sku_name: '',
			pickupCode: '',
			price: '0.00',
			showError: false
		}
	])

	// 计算总价
	const totalPrice = computed(() => {
		const total = orderItems.value.reduce((sum, item) => {
			return sum + parseFloat(item.price || '0')
		}, 0)
		return total.toFixed(2)
	})

	// 提交按钮文字
	const submitBtnText = computed(() => {
		if (orderItems.value.length === 0) {
			return '请先添加包裹'
		}
		const validCount = orderItems.value.filter(item => item.sku_id && item.pickupCode.trim()).length
		if (validCount < orderItems.value.length) {
			return `继续完善信息 (${validCount}/${orderItems.value.length})`
		}
		return '立即下单'
	})

	// 处理 SKU 选择
	const handleSkuChange = (event : any, index : number) => {
		const selectedIndex = event.detail.value
		const sku = props.skuList[selectedIndex]
		
		orderItems.value[index].sku_id = sku.sku_id
		orderItems.value[index].sku_name = sku.sku_name
		orderItems.value[index].price = sku.price
		orderItems.value[index].showError = false
	}

	// 验证单个订单项
	const validateItem = (index : number) => {
		const item = orderItems.value[index]
		if (!item.sku_id || !item.pickupCode.trim()) {
			item.showError = true
		}
	}

	// 添加订单项
	const addItem = () => {
		orderItems.value.push({
			sku_id: null,
			sku_name: '',
			pickupCode: '',
			price: '0.00',
			showError: false
		})
		
		// 滚动到底部
		uni.showToast({
			title: '已添加新包裹',
			icon: 'success',
			duration: 1500
		})
	}

	// 复制订单项
	const copyItem = (index : number) => {
		const item = orderItems.value[index]
		orderItems.value.push({
			sku_id: item.sku_id,
			sku_name: item.sku_name,
			pickupCode: '', // 取件码不复制
			price: item.price,
			showError: false
		})
		
		uni.showToast({
			title: '已复制包裹信息',
			icon: 'success',
			duration: 1500
		})
	}

	// 确认删除订单项
	const confirmRemoveItem = (index : number) => {
		uni.showModal({
			title: '确认删除',
			content: `确定要删除包裹 ${index + 1} 吗？`,
			confirmColor: '#FF4444',
			success: (res) => {
				if (res.confirm) {
					removeItem(index)
				}
			}
		})
	}

	// 删除订单项
	const removeItem = (index : number) => {
		orderItems.value.splice(index, 1)
		uni.showToast({
			title: '已删除',
			icon: 'success',
			duration: 1500
		})
	}

	// 是否可以提交
	const canSubmit = computed(() => {
		// 验证订单项
		if (orderItems.value.length === 0) return false
		
		return orderItems.value.every(item => {
			return item.sku_id && item.pickupCode.trim()
		})
	})

	// 提交订单
	const submitOrder = () => {
		if (!canSubmit.value) {
			// 显示第一个未完成的项
			const invalidIndex = orderItems.value.findIndex(item => !item.sku_id || !item.pickupCode.trim())
			if (invalidIndex !== -1) {
				orderItems.value[invalidIndex].showError = true
				uni.showToast({
					title: `请完善包裹 ${invalidIndex + 1} 的信息`,
					icon: 'none',
					duration: 2000
				})
			}
			return
		}

		// 检测是否登录
		if (!userInfo.value) {
			useLogin().setLoginBack({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: orderItems.value[0].sku_id } })
			return false
		}

		const orderData = {
			type: 'errand',
			items: orderItems.value.map(item => ({
				sku_id: item.sku_id,
				sku_name: item.sku_name,
				pickup_code: item.pickupCode,
				price: item.price
			})),
			total_price: totalPrice.value,
			sku_id: orderItems.value[0].sku_id,
			num: orderItems.value.length
		}

		uni.showLoading({ title: '提交中...' })

		uni.setStorage({
			key: 'o2oCreateData',
			data: {
				sku: orderData
			},
			success: () => {
				uni.hideLoading()
				redirect({ url: '/addon/home_service/user/pages/order/payment', param: { id: orderData.sku_id } })
			},
			fail: () => {
				uni.hideLoading()
				uni.showToast({
					title: '提交失败，请重试',
					icon: 'none'
				})
			}
		})
	}
</script>

<style lang="scss" scoped>
	.errand-order-form {
		min-height: 100vh;
		background: #F7F8FA;
		padding: 24rpx;
		padding-bottom: 280rpx;
	}

	// 提示区域
	.tips-section {
		display: flex;
		align-items: flex-start;
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 24rpx;

		.tips-icon {
			width: 48rpx;
			height: 48rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(255, 255, 255, 0.3);
			border-radius: 24rpx;
			color: #fff;
			margin-right: 16rpx;
			flex-shrink: 0;
		}

		.tips-content {
			flex: 1;
		}

		.tips-title {
			font-size: 30rpx;
			font-weight: 600;
			color: #fff;
			margin-bottom: 8rpx;
		}

		.tips-text {
			font-size: 24rpx;
			color: rgba(255, 255, 255, 0.9);
			line-height: 1.5;
		}
	}

	// 空状态
	.empty-state {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 80rpx 0;

		.empty-text {
			margin-top: 24rpx;
			font-size: 28rpx;
			color: #999;
		}
	}

	// 卡片
	.card {
		background: #ffffff;
		border-radius: 20rpx;
		padding: 24rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
	}

	.order-item {
		position: relative;
		transition: all 0.3s ease;
	}

	// 卡片头部
	.item-header {
		display: flex;
		align-items: center;
		margin-bottom: 24rpx;
		padding-bottom: 20rpx;
		border-bottom: 1rpx solid #F0F0F0;

		.item-badge {
			width: 48rpx;
			height: 48rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			border-radius: 24rpx;
			margin-right: 16rpx;

			.badge-number {
				font-size: 24rpx;
				font-weight: bold;
				color: #fff;
			}
		}

		.item-title {
			flex: 1;
			font-size: 32rpx;
			font-weight: 600;
			color: #333;
		}

		.item-actions {
			display: flex;
			gap: 16rpx;
		}

		.action-btn {
			width: 56rpx;
			height: 56rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 28rpx;
			transition: all 0.3s ease;

			&.copy-btn {
				background: #E8F5E9;
				color: #4CAF50;
			}

			&.delete-btn {
				background: #FFEBEE;
				color: #F44336;
			}

			&:active {
				transform: scale(0.9);
			}
		}
	}

	// 表单内容
	.form-content {
		.form-item {
			margin-bottom: 24rpx;

			&:last-child {
				margin-bottom: 0;
			}
		}

		.label {
			display: flex;
			align-items: center;
			margin-bottom: 16rpx;

			.label-text {
				font-size: 28rpx;
				font-weight: 500;
				color: #333;
			}

			.required {
				margin-left: 4rpx;
				font-size: 28rpx;
				color: #FF4444;
			}
		}

		// 选择器
		.picker {
			display: flex;
			align-items: center;
			justify-content: space-between;
			height: 88rpx;
			background: #F8F9FA;
			border-radius: 16rpx;
			padding: 0 24rpx;
			border: 2rpx solid transparent;
			transition: all 0.3s ease;

			&.picker-selected {
				background: #fff;
				border-color: var(--primary-color);
			}

			&.picker-error {
				border-color: #FF4444;
			}

			.picker-text {
				font-size: 28rpx;
				color: #333;
				flex: 1;

				&.picker-placeholder {
					color: #999;
				}
			}

			.picker-icon {
				font-size: 24rpx;
				color: #999;
			}
		}

		// 价格显示
		.price-display {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 24rpx;
			background: linear-gradient(135deg, #FFF9E6 0%, #FFF3D6 100%);
			border-radius: 16rpx;

			.price-left {
				display: flex;
				align-items: center;
				gap: 12rpx;
			}

			.price-label {
				font-size: 28rpx;
				color: #666;
			}

			.price-tag {
				padding: 4rpx 12rpx;
				background: rgba(255, 152, 0, 0.1);
				border-radius: 8rpx;
				font-size: 20rpx;
				color: #FF9800;
			}

			.price-right {
				display: flex;
				align-items: baseline;
			}

			.price-symbol {
				font-size: 28rpx;
				font-weight: bold;
				color: #FF6B00;
				margin-right: 4rpx;
			}

			.price-value {
				font-size: 40rpx;
				font-weight: bold;
				color: #FF6B00;
			}
		}

		// 输入框
		.input-wrapper {
			position: relative;
			display: flex;
			align-items: center;
			height: 88rpx;
			background: #F8F9FA;
			border-radius: 16rpx;
			border: 2rpx solid transparent;
			transition: all 0.3s ease;

			&:focus-within {
				background: #fff;
				border-color: var(--primary-color);
			}

			&.input-error {
				border-color: #FF4444;
			}

			.input {
				flex: 1;
				height: 100%;
				padding: 0 24rpx;
				font-size: 28rpx;
				color: #333;
				background: transparent;
			}

			.input-clear {
				width: 56rpx;
				height: 56rpx;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-right: 8rpx;
				font-size: 28rpx;
				color: #999;
			}
		}

		.error-tip {
			margin-top: 12rpx;
			font-size: 24rpx;
			color: #FF4444;
			padding-left: 8rpx;
		}

		.input-hint {
			margin-top: 12rpx;
			font-size: 24rpx;
			color: #999;
			display: flex;
			align-items: center;
			gap: 8rpx;
		}
	}

	// 添加按钮
	.add-item-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 96rpx;
		background: #fff;
		border: 2rpx dashed #D0D0D0;
		border-radius: 20rpx;
		margin-bottom: 24rpx;
		transition: all 0.3s ease;

		&:active {
			transform: scale(0.98);
			border-color: var(--primary-color);
		}

		.add-icon {
			font-size: 32rpx;
			color: var(--primary-color);
			margin-right: 12rpx;
		}

		.add-text {
			font-size: 28rpx;
			color: #666;
		}
	}

	// 底部栏
	.bottom-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background: #fff;
		box-shadow: 0 -4rpx 24rpx rgba(0, 0, 0, 0.08);
		padding: 20rpx 24rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		z-index: 999;
	}

	.summary-section {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 20rpx;

		.summary-item {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
		}

		.summary-label {
			font-size: 24rpx;
			color: #999;
			margin-bottom: 8rpx;
		}

		.summary-value {
			font-size: 28rpx;
			font-weight: 600;
			color: #333;
		}

		.summary-divider {
			width: 1rpx;
			height: 40rpx;
			background: #E0E0E0;
		}

		.total-price-wrapper {
			display: flex;
			align-items: baseline;

			.price-symbol {
				font-size: 28rpx;
				font-weight: bold;
				color: #FF6B00;
				margin-right: 4rpx;
			}

			.total-price {
				font-size: 44rpx;
				font-weight: bold;
				color: #FF6B00;
			}
		}
	}

	.submit-btn {
		width: 100%;
		height: 96rpx;
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		border-radius: 48rpx;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 8rpx 24rpx rgba(102, 126, 234, 0.4);
		transition: all 0.3s ease;

		&:active {
			transform: scale(0.98);
			box-shadow: 0 4rpx 16rpx rgba(102, 126, 234, 0.3);
		}

		&.disabled {
			background: #E0E0E0;
			box-shadow: none;

			.submit-text {
				color: #999;
			}
		}

		.submit-text {
			font-size: 32rpx;
			font-weight: 600;
			color: #fff;
		}
	}
</style>

