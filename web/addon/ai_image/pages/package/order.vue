<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
		<div class="max-w-6xl mx-auto">
			<!-- Header -->
			<div class="mb-12">
				<div class="flex items-center justify-between mb-6">
					<div>
						<h1
							class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
							订单记录
						</h1>
						<p class="text-gray-400 text-sm">查看您的购买记录</p>
					</div>
					<div class="flex items-center space-x-2 text-sm text-gray-400">
						<span>共</span>
						<span class="text-purple-400 font-medium">{{ total }}</span>
						<span>条记录</span>
					</div>
				</div>
			</div>

			<!-- Order List -->
			<div class="space-y-4 mb-8" v-if="config">
				<div v-for="(item, index) in list" :key="index"
					class="group bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl border border-gray-700/50 shadow-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl hover:border-purple-500/30">

					<div class="p-6">
						<div class="flex items-center space-x-6">
							<!-- 订单图片 -->
							<div class="flex-shrink-0">
								<div
									class="w-20 h-20 rounded-lg overflow-hidden bg-gray-700/30 border border-gray-600/30 transition-transform duration-300 group-hover:scale-105">
									<img :src="img(item.image)" :alt="item.name" class="w-full h-full object-cover">
								</div>
							</div>

							<!-- 订单信息 -->
							<div class="flex-1 min-w-0">
								<div class="flex items-start justify-between mb-3">
									<div class="flex-1 min-w-0">
										<h3 class="text-lg font-bold text-white mb-1 truncate">
											订单号: {{ item.order_no || item.order_id }}
										</h3>
										<div class="text-gray-400 text-sm">
											{{ item.name }}
										</div>
									</div>

									<!-- 状态标签 -->
									<div class="flex ml-4">
										<div v-if="item.status == 10"
											class="px-4 py-1.5 bg-green-600/20 border border-green-500/30 rounded-full text-green-300 text-sm font-medium">
											已完成
										</div>
										<div v-if="item.status == 1"
											class="px-4 py-1.5 bg-yellow-600/20 border border-yellow-500/30 rounded-full text-yellow-300 text-sm font-medium">
											待支付
										</div>
										<div v-if="item.status == 1" @click="queryOrder(item.id)"
											class="px-4 py-1.5 ml-2 bg-yellow-600/20 border border-yellow-500/30 rounded-full text-yellow-300 text-sm font-medium">
											查询支付
										</div>
										<div v-if="item.status == 0"
											class="px-4 py-1.5 bg-gray-600/20 border border-gray-500/30 rounded-full text-gray-300 text-sm font-medium">
											已取消
										</div>
										<div v-if="item.status == -1"
											class="px-4 py-1.5 bg-red-600/20 border border-red-500/30 rounded-full text-red-300 text-sm font-medium">
											失败
										</div>
									</div>
								</div>

								<!-- 订单详情 -->
								<div
									class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t border-gray-700/50">
									<div class="flex items-center space-x-2">
										<svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor"
											viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
											</path>
										</svg>
										<div>
											<div class="text-xs text-gray-500 mb-0.5">金额</div>
											<div class="text-sm text-white font-medium">¥{{ item.order_money }}</div>
										</div>
									</div>

									<div class="flex items-center space-x-2">
										<svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor"
											viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z">
											</path>
										</svg>
										<div>
											<div class="text-xs text-gray-500 mb-0.5">{{ config.alias_name }}</div>
											<div class="text-sm text-white font-medium">{{ item.point || '-' }}</div>
										</div>
									</div>

									<div class="flex items-center space-x-2">
										<svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor"
											viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
											</path>
										</svg>
										<div>
											<div class="text-xs text-gray-500 mb-0.5">类型</div>
											<div class="text-sm text-white font-medium">{{ item.type === 'card' ? '卡密' :
												'充值' }}</div>
										</div>
									</div>

									<div class="flex items-center space-x-2">
										<svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor"
											viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
										</svg>
										<div>
											<div class="text-xs text-gray-500 mb-0.5">时间</div>
											<div class="text-sm text-white font-medium">{{ formatTime(item.create_time)
											}}</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Empty State -->
				<div v-if="!loading && list.length === 0" class="text-center py-20">
					<div class="inline-block p-6 rounded-xl bg-gray-800/50 border border-gray-700/50 mb-4">
						<svg class="w-16 h-16 text-gray-600 mx-auto" fill="none" stroke="currentColor"
							viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
							</path>
						</svg>
					</div>
					<p class="text-gray-400 font-medium text-lg mb-2">暂无订单记录</p>
					<p class="text-gray-500 text-sm">您还没有任何购买记录</p>
				</div>

				<!-- Loading State -->
				<div v-if="loading" class="text-center py-20">
					<div class="inline-flex items-center space-x-2">
						<div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0ms;">
						</div>
						<div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 150ms;">
						</div>
						<div class="w-2 h-2 bg-purple-300 rounded-full animate-bounce" style="animation-delay: 300ms;">
						</div>
					</div>
					<p class="mt-4 text-gray-400 text-sm">加载中...</p>
				</div>
			</div>

			<!-- Pagination -->
			<div v-if="total > limit && !loading" class="flex items-center justify-center space-x-2 mt-12">
				<!-- 上一页 -->
				<button @click="prevPage" :disabled="page === 1"
					class="group px-4 py-2 rounded-lg bg-gray-800/80 border border-gray-700/50 text-gray-400 hover:bg-gray-700/80 hover:border-purple-500/30 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-gray-800/80 disabled:hover:border-gray-700/50 disabled:hover:text-gray-400 transition-all duration-300">
					<svg xmlns="http://www.w3.org/2000/svg"
						class="h-5 w-5 transition-transform duration-300 group-hover:-translate-x-1" viewBox="0 0 20 20"
						fill="currentColor">
						<path fill-rule="evenodd"
							d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
							clip-rule="evenodd" />
					</svg>
				</button>

				<!-- 页码 -->
				<div class="flex items-center space-x-2">
					<button v-for="p in displayPages" :key="p" @click="typeof p === 'number' ? changePage(p) : null"
						:class="[
							'min-w-[40px] h-10 rounded-lg font-medium text-sm transition-all duration-300',
							p === page
								? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg shadow-purple-500/20 border border-purple-500/30'
								: typeof p === 'number'
									? 'bg-gray-800/80 border border-gray-700/50 text-gray-400 hover:bg-gray-700/80 hover:border-purple-500/30 hover:text-white'
									: 'bg-transparent text-gray-600 cursor-default border-0'
						]">
						{{ p }}
					</button>
				</div>

				<!-- 下一页 -->
				<button @click="nextPage" :disabled="page === totalPages"
					class="group px-4 py-2 rounded-lg bg-gray-800/80 border border-gray-700/50 text-gray-400 hover:bg-gray-700/80 hover:border-purple-500/30 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-gray-800/80 disabled:hover:border-gray-700/50 disabled:hover:text-gray-400 transition-all duration-300">
					<svg xmlns="http://www.w3.org/2000/svg"
						class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 20 20"
						fill="currentColor">
						<path fill-rule="evenodd"
							d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
							clip-rule="evenodd" />
					</svg>
				</button>
			</div>

			<!-- 页面信息 -->
			<div v-if="total > 0 && !loading" class="text-center mt-6 text-sm text-gray-500">
				第 <span class="text-purple-400 font-medium">{{ page }}</span> / <span
					class="text-purple-400 font-medium">{{ totalPages }}</span> 页
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { ref, computed } from 'vue'
import { getOrder, querySxfOrder } from '@/addon/ai_image/api/aiimage'
import { img } from '@/utils/common'
import { getConfig } from '@/addon/ai_image/api/aiimage'
const queryOrder = (id) => {
	querySxfOrder(id).then((res: any) => {
		if (res.data.tranSts === 'SUCCESS') {
			ElMessage.success('支付成功')
		}
		if (res.data.tranSts === 'FAIL') {
			ElMessage.error('支付失败')

		}
		if (res.data.tranSts === 'CANCELED' || res.data.tranSts === 'CLOSED') {
			ElMessage.error('已关闭')
		}
		if (res.data.tranSts === 'PAYING') {
			//支付中
			ElMessage.success('支付中')
		}
	}).catch((err: any) => {
		// Silent fail for polling
	})
}
const config = ref()
const getConfigFn = () => {
	getConfig().then(res => {
		config.value = res.data
	})
}
getConfigFn()
const page = ref(1)
const limit = ref(10)
const list = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)

// 计算总页数
const totalPages = computed(() => Math.ceil(total.value / limit.value))

// 计算要显示的页码
const displayPages = computed(() => {
	const current = page.value
	const totalP = totalPages.value
	const pages: (number | string)[] = []

	if (totalP <= 7) {
		// 总页数小于等于7,全部显示
		for (let i = 1; i <= totalP; i++) {
			pages.push(i)
		}
	} else {
		// 总页数大于7,智能显示
		if (current <= 3) {
			// 当前页在前3页
			for (let i = 1; i <= 5; i++) {
				pages.push(i)
			}
			pages.push('...')
			pages.push(totalP)
		} else if (current >= totalP - 2) {
			// 当前页在后3页
			pages.push(1)
			pages.push('...')
			for (let i = totalP - 4; i <= totalP; i++) {
				pages.push(i)
			}
		} else {
			// 当前页在中间
			pages.push(1)
			pages.push('...')
			for (let i = current - 1; i <= current + 1; i++) {
				pages.push(i)
			}
			pages.push('...')
			pages.push(totalP)
		}
	}

	return pages
})

// 格式化时间
const formatTime = (time: string) => {
	if (!time) return '-'
	const date = new Date(time)
	const now = new Date()
	const diff = now.getTime() - date.getTime()

	// 小于1分钟
	if (diff < 60000) {
		return '刚刚'
	}
	// 小于1小时
	if (diff < 3600000) {
		return `${Math.floor(diff / 60000)}分钟前`
	}
	// 小于1天
	if (diff < 86400000) {
		return `${Math.floor(diff / 3600000)}小时前`
	}
	// 小于7天
	if (diff < 604800000) {
		return `${Math.floor(diff / 86400000)}天前`
	}

	// 超过7天,显示具体日期
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	const hour = String(date.getHours()).padStart(2, '0')
	const minute = String(date.getMinutes()).padStart(2, '0')

	// 如果是今年,不显示年份
	if (year === now.getFullYear()) {
		return `${month}-${day} ${hour}:${minute}`
	}

	return `${year}-${month}-${day} ${hour}:${minute}`
}

// 获取订单列表
const getOrderFn = () => {
	loading.value = true
	getOrder({
		page: page.value,
		limit: limit.value
	}).then(res => {
		list.value = res.data.data || []
		total.value = res.data.total || res.data.count || 0
	}).catch(err => {
		error.value = err
		console.error('Failed to fetch orders:', err)
	}).finally(() => {
		loading.value = false
	})
}

// 切换页码
const changePage = (p: number) => {
	if (p === page.value) return
	page.value = p
	getOrderFn()
	// 滚动到顶部
	window.scrollTo({ top: 0, behavior: 'smooth' })
}

// 上一页
const prevPage = () => {
	if (page.value > 1) {
		changePage(page.value - 1)
	}
}

// 下一页
const nextPage = () => {
	if (page.value < totalPages.value) {
		changePage(page.value + 1)
	}
}

// 初始加载
getOrderFn()
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

/* 响应式 */
@media (max-width: 768px) {
	.text-4xl {
		font-size: 2rem;
	}
}
</style>
