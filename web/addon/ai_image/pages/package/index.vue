<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
		<div class="max-w-7xl mx-auto">
			<!-- Header -->
			<div class="text-center mb-12">
				<h1
					class="text-4xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
					套餐列表
				</h1>
				<p class="text-gray-400 text-lg max-w-2xl mx-auto">选择适合您的套餐，开启AI创作之旅</p>
			</div>

			<!-- Package Grid -->
			<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" v-if="config">
				<!-- Package Card -->
				<div v-for="item in packageList" :key="item.id"
					class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl border border-gray-700/50 shadow-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl hover:border-purple-500/30">
					<!-- Package Image -->
					<div class="h-48 overflow-hidden relative group">
						<!-- Package Image -->
						<img :src="img(item.image)" :alt="item.name"
							class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">

						<!-- Gradient Overlay -->
						<div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/30 to-transparent">
						</div>


						<!-- Package Name -->
						<div class="absolute bottom-0 left-0 right-0 p-6">
							<h3 class="text-2xl font-bold text-white drop-shadow-md">{{ item.name }}</h3>
						</div>
					</div>

					<!-- Package Content -->
					<div class="p-6">
						<!-- Package Info -->
						<div class="mb-6">
							<!-- Points/Type Badge -->
							<div class="flex items-center justify-between mb-4">
								<div class="flex items-center">

									<div class="flex items-center">
										<div class="text-2xl font-bold text-white">{{ item.point }}</div>
										<div class="ml-2 text-gray-300">{{ config.alias_name }}</div>
									</div>
									<div class="ml-2 text-red-800 text-[48rpx] font-bold">￥{{ item.price }}</div>
								</div>

								<!-- Type Badge -->
								<div v-if="item.type === 'point'"
									class="px-3 py-1 bg-purple-600/20 text-purple-300 text-sm font-medium rounded-full border border-purple-500/30">
									充值套餐
								</div>
								<div v-else-if="item.type === 'card'"
									class="px-3 py-1 bg-blue-600/20 text-blue-300 text-sm font-medium rounded-full border border-blue-500/30">
									卡密套餐
								</div>
							</div>

							<!-- Card Details -->
							<div v-if="item.type === 'card'"
								class="mt-4 p-4 bg-gray-700/30 rounded-lg border border-gray-600/30">
								<div class="grid grid-cols-2 gap-4 text-sm">
									<div class="space-y-1">
										<div class="text-gray-400">卡密数量</div>
										<div class="text-white font-medium">x{{ item.num || 1 }}</div>
									</div>
									<div class="space-y-1">
										<div class="text-gray-400">有效期</div>
										<div class="text-white font-medium">{{ item.day || 30 }}天</div>
									</div>
								</div>
							</div>

							<!-- Point Details -->
							<div v-else class="mt-4 p-4 bg-gray-700/30 rounded-lg border border-gray-600/30">
								<div class="text-sm">
									<div class="text-gray-400 mb-1">包含点数</div>
									<div class="text-white font-medium">{{ item.point }} {{ config.alias_name }}</div>
								</div>
							</div>
						</div>
						<!-- Features (if available) -->
						<div v-if="item.features" class="space-y-3 mb-6">
							<div v-for="(feature, index) in item.features" :key="index" class="flex items-start">
								<div
									class="w-6 h-6 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5 mr-2">
									<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor"
										viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
											d="M5 13l4 4L19 7"></path>
									</svg>
								</div>
								<span class="text-gray-300">{{ feature }}</span>
							</div>
						</div>

						<!-- Action Button -->
						<div class="mt-6">
							<button @click="buyPackage(item)"
								class="w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
								<span>立即购买</span>
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 20 20"
									fill="currentColor">
									<path fill-rule="evenodd"
										d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
										clip-rule="evenodd" />
								</svg>
							</button>
							<p v-if="item.original_price && item.original_price > item.price"
								class="mt-2 text-center text-sm text-gray-400 line-through">
								原价: ¥{{ item.original_price }}
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<el-dialog v-model="payDialogVisible" title="" width="650px" :close-on-click-modal="false"
		@close="handleDialogClose" class="payment-dialog">
		<div class="flex bg-gradient-to-br from-white to-gray-50">
			<!-- Left Side - Payment Info -->
			<div class="flex-1 px-8 py-8 flex flex-col justify-center">
				<!-- Header with Icon -->
				<div class="mb-6">
					<div
						class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl mb-3 shadow-lg shadow-purple-500/30">
						<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<h3 class="text-xl font-bold text-gray-800 mb-2">扫码支付</h3>
					<div class="flex items-baseline gap-1">
						<span class="text-sm text-gray-500">¥</span>
						<span
							class="text-4xl font-bold bg-gradient-to-r from-red-500 to-pink-500 bg-clip-text text-transparent">{{
								currentPackagePrice }}</span>
					</div>
				</div>

				<!-- Countdown Timer -->
				<div v-if="countdown > 0"
					class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-gradient-to-r from-purple-500 via-indigo-500 to-purple-600 rounded-full mb-6 shadow-lg shadow-purple-500/30 animate-pulse-subtle w-fit">
					<svg class="w-4 h-4 text-white animate-spin-slow" viewBox="0 0 24 24" fill="none"
						xmlns="http://www.w3.org/2000/svg">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
						<path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
					</svg>
					<span class="text-sm font-bold text-white font-mono tracking-wider">{{ formatCountdown }}</span>
				</div>
				<div v-else
					class="inline-flex items-center gap-2 px-5 py-2 bg-red-50 border-2 border-red-200 rounded-full mb-6 w-fit">
					<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<span class="text-red-500 text-sm font-semibold">二维码已过期</span>
				</div>

				<!-- Payment Method Icons -->
				<div class="mb-4">
					<div class="text-xs font-medium text-gray-400 mb-3 tracking-wide">支持支付方式</div>
					<div class="flex gap-2 items-center flex-wrap">
						<div v-for="method in paymentMethods" :key="method.type"
							class="group flex items-center gap-2 px-4 py-2 bg-white rounded-lg border-2 border-gray-100 hover:border-gray-200 transition-all duration-200 shadow-sm hover:shadow-md">
							<div class="flex items-center justify-center w-5 h-5 transition-transform duration-200 group-hover:scale-110"
								:style="{ color: method.color }">
								<component :is="method.icon" />
							</div>
							<span class="text-xs font-medium text-gray-600">{{ method.name }}</span>
						</div>
					</div>
				</div>

				<!-- Refresh Button for Expired QR Code -->
				<div v-if="countdown <= 0" class="mt-6">
					<el-button type="primary" @click="refreshQRCode" :loading="payLoading"
						class="w-full bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 border-0 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300">
						<span class="font-semibold">刷新二维码</span>
					</el-button>
				</div>
			</div>

			<!-- Right Side - QR Code -->
			<div
				class="w-72 bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center p-8 border-l border-gray-200">
				<div v-if="countdown > 0" class="text-center">
					<div
						class="relative inline-flex justify-center items-center p-4 bg-white rounded-2xl shadow-xl border-2 border-gray-100 mb-4">
						<!-- Decorative corners -->
						<div class="absolute top-1.5 left-1.5 w-3 h-3 border-t-2 border-l-2 border-purple-400"></div>
						<div class="absolute top-1.5 right-1.5 w-3 h-3 border-t-2 border-r-2 border-purple-400"></div>
						<div class="absolute bottom-1.5 left-1.5 w-3 h-3 border-b-2 border-l-2 border-purple-400"></div>
						<div class="absolute bottom-1.5 right-1.5 w-3 h-3 border-b-2 border-r-2 border-purple-400">
						</div>

						<el-image :src="qr_code" fit="contain" class="w-52 h-52" />
					</div>
					<!-- Tips below QR code -->
					<div class="space-y-2 px-2">
						<p class="text-sm font-medium text-gray-700 flex items-center justify-center gap-2">
							<svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
							</svg>
							请使用手机扫描二维码
						</p>
						<p class="text-xs text-gray-500">支付完成后将自动跳转</p>
					</div>
				</div>
				<div v-else class="text-center">
					<div class="w-52 h-52 bg-gray-100 rounded-2xl flex items-center justify-center mb-3">
						<svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<p class="text-xs text-gray-400">二维码已过期</p>
				</div>
			</div>
		</div>
	</el-dialog>
</template>

<script lang="ts" setup>
import { ref, computed, onUnmounted, h } from 'vue'
import { getPackageList, getConfig, addOrder, querySxfOrder, getSxfScan } from '@/addon/ai_image/api/aiimage'
import { img } from '@/utils/common'
import { SuccessFilled } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

// Payment method icons as SVG components
const WechatIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', style: 'width: 24px; height: 24px;' }, [
	h('path', { d: 'M8.5 5C4.4 5 1 7.9 1 11.5c0 2.1 1.1 4 3 5.2L3.5 19l3.1-1.5c.8.2 1.6.3 2.4.3.3 0 .5 0 .8-.1-.2-.6-.3-1.3-.3-2 0-3.8 3.5-6.9 7.8-6.9.4 0 .9 0 1.3.1C17.8 6.4 13.5 5 8.5 5zm5.3 3.4c.6 0 1 .4 1 1s-.4 1-1 1-1-.4-1-1 .4-1 1-1zM5.2 10.4c-.6 0-1-.4-1-1s.4-1 1-1 1 .4 1 1-.4 1-1 1z' }),
	h('path', { d: 'M17.3 11.5c-3.5 0-6.3 2.4-6.3 5.5s2.8 5.5 6.3 5.5c.7 0 1.4-.1 2-.3l2.5 1.2-.8-2.3c1.5-1 2.5-2.6 2.5-4.4 0-3.1-2.8-5.5-6.2-5.5zm-2.5 4.1c-.4 0-.8-.3-.8-.8s.3-.8.8-.8.8.3.8.8-.4.8-.8.8zm5 0c-.4 0-.8-.3-.8-.8s.3-.8.8-.8.8.3.8.8-.4.8-.8.8z' })
])

const AlipayIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', style: 'width: 24px; height: 24px;' }, [
	h('path', { d: 'M3 3h18c1.1 0 2 .9 2 2v14c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2zm15.7 11.3c-1.1.5-3.3 1.3-5.7 1.3-3.4 0-6.4-1.3-7.5-3.4 1.3 1.5 3.4 2.4 6 2.4 2.7 0 5.2-1 6.5-2.6.5 1 .7 1.9.7 2.3zm-6.2-5.8c-.5-.2-1-.3-1.5-.3-2.2 0-4 1.8-4 4 0 .3 0 .6.1.9-1.4-1.1-2.3-2.8-2.3-4.7 0-3.3 2.7-6 6-6s6 2.7 6 6c0 .7-.1 1.4-.3 2-.9-1.2-2.4-1.9-4-1.9z' })
])

const UnionPayIcon = () => h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', style: 'width: 24px; height: 24px;' }, [
	h('path', { d: 'M3 5h18c1.1 0 2 .9 2 2v10c0 1.1-.9 2-2 2H3c-1.1 0-2-.9-2-2V7c0-1.1.9-2 2-2zm0 2v2h18V7H3zm0 4v6h18v-6H3zm2 2h3v2H5v-2z' })
])

const qr_code = ref('')
const payLoading = ref(false)
const payDialogVisible = ref(false)
const id = ref()
const currentPackagePrice = ref(0)
const countdown = ref(300) // 5 minutes in seconds
let countdownTimer: number | null = null
let orderCheckTimer: number | null = null

// Payment methods configuration (display only for aggregate payment)
const paymentMethods = [
	{ type: 'wechat', name: '微信支付', icon: WechatIcon, color: '#07C160' },
	{ type: 'alipay', name: '支付宝', icon: AlipayIcon, color: '#1677FF' },
	{ type: 'unionpay', name: '云闪付', icon: UnionPayIcon, color: '#E4393C' }
]

const formatCountdown = computed(() => {
	const minutes = Math.floor(countdown.value / 60)
	const seconds = countdown.value % 60
	return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
})

const startCountdown = () => {
	countdown.value = 300
	if (countdownTimer) clearInterval(countdownTimer)

	countdownTimer = window.setInterval(() => {
		if (countdown.value > 0) {
			countdown.value--
		} else {
			if (countdownTimer) clearInterval(countdownTimer)
			if (orderCheckTimer) clearInterval(orderCheckTimer)
		}
	}, 1000)
}

const startOrderCheck = () => {
	if (orderCheckTimer) clearInterval(orderCheckTimer)

	orderCheckTimer = window.setInterval(() => {
		if (countdown.value > 0) {
			queryOrder()
		} else {
			if (orderCheckTimer) clearInterval(orderCheckTimer)
		}
	}, 3000) // Check every 3 seconds
}

const handleDialogClose = () => {
	if (countdownTimer) clearInterval(countdownTimer)
	if (orderCheckTimer) clearInterval(orderCheckTimer)
	countdown.value = 300
}

const refreshQRCode = () => {
	if (!id.value) return
	payLoading.value = true
	addOrder({ package_id: id.value }).then((res: any) => {
		getSxfScan(res.data.trade_id).then((res: any) => {
			qr_code.value = res.data.qr_code
			startCountdown()
			startOrderCheck()
			payLoading.value = false
		}).catch((err: any) => {
			ElMessage.error('获取支付信息失败')
			payLoading.value = false
		})
	}).catch((err: any) => {
		ElMessage.error(err.message || '创建订单失败')
		payLoading.value = false
	})
}

const buyPackage = (item: Package) => {
	if (config.value?.pc_pay == 0) {
		ElMessageBox({
			title: '温馨提示',
			message: h('div', { class: 'custom-message-box' }, [
				h('div', { class: 'flex items-center justify-center mb-4' }, [
					h('div', {
						class: 'w-16 h-16 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center shadow-lg',
						style: 'animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;'
					}, [
						h('svg', {
							class: 'w-8 h-8 text-white',
							fill: 'none',
							stroke: 'currentColor',
							viewBox: '0 0 24 24'
						}, [
							h('path', {
								'stroke-linecap': 'round',
								'stroke-linejoin': 'round',
								'stroke-width': '2',
								d: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
							})
						])
					])
				]),
				h('div', { class: 'text-center' }, [
					h('p', {
						class: 'text-base text-gray-700 leading-relaxed mb-2',
						style: 'line-height: 1.8;'
					}, config.value?.pc_notice || 'PC端暂不支持购买套餐'),
					h('p', {
						class: 'text-sm text-gray-500',
						style: 'margin-top: 8px;'
					}, '请联系客户或者进行卡密兑换')
				])
			]),
			confirmButtonText: '我知道了',
			confirmButtonClass: 'custom-confirm-button',
			customClass: 'custom-message-box-container',
			showCancelButton: false,
			center: true,
			closeOnClickModal: true,
			closeOnPressEscape: true
		})
		return
	}
	currentPackagePrice.value = item.price
	payLoading.value = true
	addOrder({ package_id: item.id }).then((res: any) => {
		id.value = res.data.trade_id
		getSxfScan(res.data.trade_id).then((res: any) => {
			qr_code.value = res.data.qr_code
			payDialogVisible.value = true
			startCountdown()
			startOrderCheck()
			payLoading.value = false
		}).catch((err: any) => {
			ElMessage.error('获取支付信息失败')
			payLoading.value = false
		})
	}).catch((err: any) => {
		ElMessage.error(err.message || '创建订单失败')
		payLoading.value = false
	})
}

const queryOrder = () => {
	querySxfOrder(id.value).then((res: any) => {
		if (res.data.tranSts === 'SUCCESS') {
			ElMessage.success('支付成功')
			payDialogVisible.value = false
			handleDialogClose()
			// Refresh package list or user balance here
			fetchPackages()
		}
		if (res.data.tranSts === 'FAIL') {
			ElMessage.error('支付失败')
			payDialogVisible.value = false
			handleDialogClose()
		}
		if (res.data.tranSts === 'CANCELED') {
			ElMessage.error('已关闭')
			payDialogVisible.value = false
			handleDialogClose()
		}
		if (res.data.tranSts === 'PAYING') {
			//支付中
		}
	}).catch((err: any) => {
		// Silent fail for polling
	})
}

onUnmounted(() => {
	if (countdownTimer) clearInterval(countdownTimer)
	if (orderCheckTimer) clearInterval(orderCheckTimer)
})

interface Package {
	id: number | string
	name: string
	price: number
	original_price?: number
	point: number
	type: 'point' | 'card'
	image: string
	duration?: number
	num?: number
	day?: number
	discount?: number
	features?: string[]
	description?: string
}

interface Config {
	alias_name: string
	[key: string]: any
}

const config = ref<Config | null>(null)
const packageList = ref<Package[]>([])
const loading = ref(true)
const error = ref<Error | null>(null)

const getConfigFn = async () => {
	try {
		const res: any = await getConfig()
		config.value = res.data
	} catch (err) {
		console.error('Failed to fetch config:', err)
		error.value = err as Error
	}
}

getConfigFn()

const fetchPackages = async () => {
	try {
		loading.value = true
		const res: any = await getPackageList({
			limit: 100,
			page: 1,
		})
		packageList.value = res.data.data
	} catch (err) {
		error.value = err as Error
		console.error('Failed to fetch packages:', err)
		// You can show an error message to the user here if needed
	} finally {
		loading.value = false
	}
}

// Initial fetch
fetchPackages()
</script>
<style lang="scss" scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
	width: 6px;
	height: 6px;
}

::-webkit-scrollbar-track {
	background: rgba(255, 255, 255, 0.05);
	border-radius: 10px;
}

::-webkit-scrollbar-thumb {
	background: linear-gradient(to bottom, #8b5cf6, #3b82f6);
	border-radius: 10px;
	transition: all 0.3s ease;
}

::-webkit-scrollbar-thumb:hover {
	background: linear-gradient(to bottom, #7c3aed, #2563eb);
}

/* Animation */
@keyframes fadeIn {
	from {
		opacity: 0;
		transform: translateY(20px);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Card hover effect */
.package-card {
	animation: fadeIn 0.6s ease-out forwards;
	opacity: 0;
	transition: all 0.3s ease;

	&:hover {
		transform: translateY(-4px);
		box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
	}
}

/* Loading state */
.loading-shimmer {
	background: linear-gradient(to right,
			rgba(255, 255, 255, 0) 0%,
			rgba(255, 255, 255, 0.05) 50%,
			rgba(255, 255, 255, 0) 100%);
	background-size: 200% 100%;
	animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
	0% {
		background-position: 200% 0;
	}

	100% {
		background-position: -200% 0;
	}
}

/* Responsive adjustments */
@media (max-width: 768px) {
	.package-card {
		margin: 0 0.5rem;
	}

	.package-grid {
		padding: 0 0.5rem;
	}
}

/* Button hover effect */
button {
	position: relative;
	overflow: hidden;
	z-index: 1;

	&::after {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
		transform: translateX(-100%);
		transition: 0.5s;
		z-index: -1;
	}

	&:hover::after {
		transform: translateX(100%);
	}
}

/* Custom animations for payment dialog */
@keyframes pulse-subtle {

	0%,
	100% {
		opacity: 1;
	}

	50% {
		opacity: 0.9;
	}
}

@keyframes spin-slow {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

.animate-pulse-subtle {
	animation: pulse-subtle 2s ease-in-out infinite;
}

.animate-spin-slow {
	animation: spin-slow 3s linear infinite;
}

/* Custom Message Box Styles */
:deep(.custom-message-box-container) {
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

	.el-message-box__header {
		padding: 24px 24px 16px;
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		border-bottom: none;

		.el-message-box__title {
			color: white;
			font-size: 20px;
			font-weight: 600;
			text-align: center;
		}

		.el-message-box__headerbtn {
			top: 20px;
			right: 20px;

			.el-message-box__close {
				color: white;
				font-size: 20px;

				&:hover {
					color: rgba(255, 255, 255, 0.8);
				}
			}
		}
	}

	.el-message-box__content {
		padding: 32px 24px;

		.el-message-box__message {
			margin: 0;
		}
	}

	.el-message-box__btns {
		padding: 16px 24px 24px;

		.custom-confirm-button {
			width: 100%;
			padding: 12px 24px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			transition: all 0.3s ease;
			box-shadow: 0 4px 6px -1px rgba(102, 126, 234, 0.3);

			&:hover {
				transform: translateY(-2px);
				box-shadow: 0 6px 12px -2px rgba(102, 126, 234, 0.4);
			}

			&:active {
				transform: translateY(0);
			}
		}
	}
}

.custom-message-box {
	padding: 8px 0;
}

@keyframes pulse {

	0%,
	100% {
		opacity: 1;
		transform: scale(1);
	}

	50% {
		opacity: 0.8;
		transform: scale(1.05);
	}
}
</style>
