<template>
	<view class="errand-order-form">
		<!-- 联系信息 -->
		<view class="contact-section card">
			<view class="section-title">收件人信息</view>
			<view class="form-item">
				<input v-model="contactName" class="input" placeholder="请输入收件人姓名" />
			</view>
			<view class="form-item">
				<input v-model="contactMobile" class="input" type="number" maxlength="11" placeholder="请输入收件人电话" />
			</view>
		</view>

		<!-- 订单项列表 -->
		<view class="orders-section">
			<view class="card order-item" v-for="(item, index) in orderItems" :key="index">
				<view class="item-header">
					<text class="item-title">包裹 {{ index + 1 }}</text>
					<view class="delete-btn" @click="removeItem(index)" v-if="orderItems.length > 1">
						<text class="nc-iconfont nc-icon-shanchu text-[28rpx]"></text>
					</view>
				</view>

				<!-- 路线选择 -->
				<view class="form-item">
					<view class="label">配送路线</view>
					<picker :range="routes" range-key="name" @change="handleRouteChange($event, index)">
						<view class="picker">
							{{ item.routeName || '请选择配送路线' }}
							<text class="nc-iconfont nc-icon-youV6xx"></text>
						</view>
					</picker>
				</view>

				<!-- 包裹大小选择 -->
				<view class="form-item" v-if="item.routeId">
					<view class="label">包裹大小</view>
					<view class="size-options">
						<view v-for="(size, key) in getCurrentRoutePrices(item.routeId)" :key="key" class="size-option"
							:class="{ active: item.sizeKey === key }" @click="selectSize(index, key, size)">
							<view class="size-name">{{ size.name }}</view>
							<view class="size-price">¥{{ size.price }}</view>
							<view class="size-desc">{{ size.desc }}</view>
						</view>
					</view>
				</view>

				<!-- 取件码 -->
				<view class="form-item" v-if="item.sizeKey">
					<view class="label">取件码</view>
					<input v-model="item.pickupCode" class="input" placeholder="请输入取件码或快递单号" @input="calculateTotal" />
				</view>

				<!-- 当前项价格 -->
				<view class="item-price" v-if="item.price > 0">
					单项价格: <text class="price-text">¥{{ item.price.toFixed(2) }}</text>
				</view>
			</view>

			<!-- 添加订单项按钮 -->
			<view class="add-item-btn" @click="addItem">
				<text class="nc-iconfont nc-icon-jiahaoV6xx mr-[10rpx]"></text>
				再添加一个包裹
			</view>
		</view>

		<!-- 总价 -->
		<view class="total-section card">
			<view class="total-label">总计:</view>
			<view class="total-price">¥{{ totalPrice.toFixed(2) }}</view>
		</view>

		<!-- 提交按钮 -->
		<view class="submit-section">
			<button class="submit-btn" :class="{ disabled: !canSubmit }" :disabled="!canSubmit" @click="submitOrder">
				立即下单
			</button>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed } from 'vue'

	interface ErrandRoute {
		id : number
		name : string
		prices : Record<string, ErrandPrice>
	}

	interface ErrandPrice {
		name : string
		price : number
		desc : string
	}

	interface OrderItem {
		routeId : number | null
		routeName : string
		sizeKey : string
		sizeName : string
		pickupCode : string
		price : number
	}

	const props = defineProps<{
		routes : ErrandRoute[]
	}>()

	const emit = defineEmits<{
		submit : [data : any]
	}>()

	const contactName = ref('')
	const contactMobile = ref('')
	const orderItems = ref<OrderItem[]>([
		{
			routeId: null,
			routeName: '',
			sizeKey: '',
			sizeName: '',
			pickupCode: '',
			price: 0
		}
	])

	const totalPrice = ref(0)

	// 获取当前路线的价格选项
	const getCurrentRoutePrices = (routeId : number | null) => {
		if (!routeId) return {}
		const route = props.routes.find(r => r.id === routeId)
		return route ? route.prices : {}
	}

	// 处理路线变化
	const handleRouteChange = (event : any, index : number) => {
		const selectedIndex = event.detail.value
		const route = props.routes[selectedIndex]
		orderItems.value[index].routeId = route.id
		orderItems.value[index].routeName = route.name
		// 清空之前选择的大小
		orderItems.value[index].sizeKey = ''
		orderItems.value[index].sizeName = ''
		orderItems.value[index].price = 0
		calculateTotal()
	}

	// 选择包裹大小
	const selectSize = (index : number, key : string, size : ErrandPrice) => {
		orderItems.value[index].sizeKey = key
		orderItems.value[index].sizeName = size.name
		orderItems.value[index].price = size.price
		calculateTotal()
	}

	// 添加订单项
	const addItem = () => {
		orderItems.value.push({
			routeId: null,
			routeName: '',
			sizeKey: '',
			sizeName: '',
			pickupCode: '',
			price: 0
		})
	}

	// 删除订单项
	const removeItem = (index : number) => {
		orderItems.value.splice(index, 1)
		calculateTotal()
	}

	// 计算总价
	const calculateTotal = () => {
		totalPrice.value = orderItems.value.reduce((sum, item) => {
			return sum + (item.price || 0)
		}, 0)
	}

	// 是否可以提交
	const canSubmit = computed(() => {
		if (!contactName.value.trim() || !contactMobile.value.trim()) return false
		if (!/^1[3-9]\d{9}$/.test(contactMobile.value)) return false
		if (orderItems.value.length === 0) return false

		return orderItems.value.every(item => {
			return item.routeId && item.sizeKey && item.pickupCode.trim()
		})
	})

	// 提交订单
	const submitOrder = () => {
		if (!canSubmit.value) return

		const orderData = {
			type: 'errand',
			contact_name: contactName.value,
			contact_mobile: contactMobile.value,
			items: orderItems.value.map(item => ({
				route_id: item.routeId,
				route_name: item.routeName,
				package_size: item.sizeName,
				pickup_code: item.pickupCode,
				price: item.price
			})),
			total_price: totalPrice.value
		}

		emit('submit', orderData)
	}
</script>

<style lang="scss" scoped>
	.errand-order-form {
		padding: 24rpx;
		padding-bottom: 200rpx;
	}

	.card {
		background: #ffffff;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 24rpx;
	}

	.section-title {
		font-size: 32rpx;
		font-weight: 600;
		color: #333;
		margin-bottom: 24rpx;
	}

	.form-item {
		margin-bottom: 24rpx;

		&:last-child {
			margin-bottom: 0;
		}
	}

	.label {
		font-size: 28rpx;
		color: #666;
		margin-bottom: 16rpx;
	}

	.input {
		width: 100%;
		height: 80rpx;
		background: #F5F5F5;
		border-radius: 12rpx;
		padding: 0 24rpx;
		font-size: 28rpx;
		color: #333;
		box-sizing: border-box;
	}

	.picker {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 80rpx;
		background: #F5F5F5;
		border-radius: 12rpx;
		padding: 0 24rpx;
		font-size: 28rpx;
		color: #333;
	}

	.order-item {
		position: relative;
	}

	.item-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 24rpx;
	}

	.item-title {
		font-size: 30rpx;
		font-weight: 600;
		color: #333;
	}

	.delete-btn {
		width: 60rpx;
		height: 60rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #FFE5E5;
		border-radius: 30rpx;
		color: #FF4444;
	}

	.size-options {
		display: flex;
		flex-wrap: wrap;
		gap: 20rpx;
	}

	.size-option {
		flex: 1;
		min-width: calc(50% - 10rpx);
		background: #F5F5F5;
		border: 2rpx solid #F5F5F5;
		border-radius: 12rpx;
		padding: 24rpx;
		box-sizing: border-box;
		transition: all 0.3s;

		&.active {
			background: #E8F5FF;
			border-color: var(--primary-color);
		}
	}

	.size-name {
		font-size: 28rpx;
		font-weight: 600;
		color: #333;
		margin-bottom: 8rpx;
	}

	.size-price {
		font-size: 32rpx;
		font-weight: bold;
		color: var(--price-text-color);
		margin-bottom: 8rpx;
	}

	.size-desc {
		font-size: 24rpx;
		color: #999;
	}

	.item-price {
		margin-top: 24rpx;
		padding-top: 24rpx;
		border-top: 1rpx solid #F0F0F0;
		text-align: right;
		font-size: 28rpx;
		color: #666;
	}

	.price-text {
		font-size: 36rpx;
		font-weight: bold;
		color: var(--price-text-color);
	}

	.add-item-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 88rpx;
		background: #F8F8F8;
		border: 2rpx dashed #CCCCCC;
		border-radius: 16rpx;
		font-size: 28rpx;
		color: #666;
		margin-bottom: 24rpx;
	}

	.total-section {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 32rpx 24rpx;
	}

	.total-label {
		font-size: 32rpx;
		color: #333;
	}

	.total-price {
		font-size: 48rpx;
		font-weight: bold;
		color: var(--price-text-color);
	}

	.submit-section {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		padding: 24rpx;
		background: #ffffff;
		box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.submit-btn {
		width: 100%;
		height: 88rpx;
		background: var(--primary-color);
		border-radius: 44rpx;
		font-size: 32rpx;
		font-weight: 600;
		color: #ffffff;
		display: flex;
		align-items: center;
		justify-content: center;
		border: none;

		&.disabled {
			background: #CCCCCC;
			color: #999;
		}
	}
</style>

