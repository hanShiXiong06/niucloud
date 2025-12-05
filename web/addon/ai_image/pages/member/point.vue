<template>
	<div class="text-white min-h-screen">
		<div class="px-6 py-6">
			<!-- 页面标题 -->
			<div class="mb-8 text-center">
				<h1
					class="text-4xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
					账户明细
				</h1>
				<p class="text-gray-400 text-lg">查看您的账户明细</p>
			</div>

			<div class="max-w-5xl mx-auto">
				<!-- 加载状态 -->
				<div v-if="loading" class="flex justify-center items-center py-20">
					<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
				</div>

				<!-- 错误提示 -->
				<div v-else-if="error"
					class="bg-gradient-to-r from-red-500/20 to-pink-500/20 border border-red-500/50 rounded-xl p-4 text-red-400 backdrop-blur-sm">
					⚠️ 加载失败,请稍后重试
				</div>

				<!-- 积分列表 -->
				<div v-else>
					<!-- 空状态 -->
					<div v-if="!list || list.length === 0"
						class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl shadow-xl border border-gray-700/50 p-12 text-center">
						<div class="text-gray-500 text-5xl mb-4">📋</div>
						<p class="text-gray-400">暂无积分记录</p>
					</div>

					<!-- 列表内容 -->
					<div v-else class="space-y-4">
						<div v-for="(item, index) in list" :key="index"
							class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl shadow-xl border border-gray-700/50 transition-all duration-300 p-6 hover:border-purple-500/30 group">
							<div class="flex items-center justify-between">
								<!-- 左侧信息 -->
								<div class="flex-1">
									<div class="flex items-center gap-3 mb-4">
										<div
											class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
											<span class="text-white text-lg">💎</span>
										</div>
										<span class="text-xl font-semibold text-white">
											{{ item.from_type_name || '未知类型' }}
										</span>
									</div>
									<div class="text-sm text-gray-400 flex items-center gap-2 ml-[52px]">
										<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
											<path fill-rule="evenodd"
												d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
												clip-rule="evenodd" />
										</svg>
										{{ item.create_time || '-' }}
									</div>
								</div>

								<!-- 右侧积分 -->
								<div class="text-right ml-6">
									<div :class="[
										'text-2xl font-bold px-5 py-3 rounded-xl inline-block transition-all duration-300 group-hover:scale-105',
										getAmountBgClass(item.account_data)
									]">
										{{ formatAmountDisplay(item.account_data) }}
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- 分页组件 -->
					<div v-if="total > limit" class="mt-8 flex justify-center">
						<div
							class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl shadow-xl border border-gray-700/50 px-6 py-4 flex items-center gap-4">
							<button @click="handlePageChange(page - 1)" :disabled="page <= 1"
								class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"
								:class="page <= 1
									? 'bg-gray-700/50 text-gray-500 cursor-not-allowed'
									: 'bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95'">
								上一页
							</button>

							<div class="flex items-center gap-2">
								<template v-for="p in getPageNumbers()" :key="p">
									<button v-if="p !== '...'" @click="typeof p === 'number' && handlePageChange(p)"
										:class="[
											'px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-300',
											p === page
												? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg shadow-purple-500/25 scale-105'
												: 'bg-gray-700/50 text-gray-300 hover:bg-gray-600/50 hover:text-white hover:scale-105 active:scale-95'
										]">
										{{ p }}
									</button>
									<span v-else class="px-2 text-gray-500 text-sm">...</span>
								</template>
							</div>

							<button @click="handlePageChange(page + 1)" :disabled="page >= totalPages"
								class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"
								:class="page >= totalPages
									? 'bg-gray-700/50 text-gray-500 cursor-not-allowed'
									: 'bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95'">
								下一页
							</button>

							<div class="ml-4 text-sm text-gray-400 border-l border-gray-600/50 pl-4">
								共 <span class="font-semibold text-white">{{ total }}</span> 条
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { getPointList } from '@/app/api/member'

interface PointItem {
	from_type_name?: string
	create_time?: string
	account_data?: string | number
}

const page = ref(1)
const limit = ref(10)
const list = ref<PointItem[]>([])
const total = ref(0)
const loading = ref(false)
const error = ref<any>(null)

// 计算总页数
const totalPages = computed(() => Math.ceil(total.value / limit.value))

// 获取积分列表
const getPointFn = () => {
	loading.value = true
	error.value = null

	getPointList({
		page: page.value,
		limit: limit.value,
		amount_type: 'all'
	}).then((res: any) => {
		list.value = res.data.data[0]['month_data'] || []
		total.value = res.data.total || res.data.count || 0
	}).catch((err: any) => {
		error.value = err
		console.error('Failed to fetch points:', err)
	}).finally(() => {
		loading.value = false
	})
}

// 处理分页变化
const handlePageChange = (newPage: number) => {
	if (newPage < 1 || newPage > totalPages.value) return
	page.value = newPage
	getPointFn()
	// 滚动到顶部
	window.scrollTo({ top: 0, behavior: 'smooth' })
}

// 获取页码数组
const getPageNumbers = () => {
	const pages: (number | string)[] = []
	const maxVisible = 5 // 最多显示5个页码

	if (totalPages.value <= maxVisible) {
		// 总页数小于等于maxVisible,显示全部
		for (let i = 1; i <= totalPages.value; i++) {
			pages.push(i)
		}
	} else {
		// 总页数大于maxVisible,需要省略
		if (page.value <= 3) {
			// 当前页在前面
			for (let i = 1; i <= 4; i++) {
				pages.push(i)
			}
			pages.push('...')
			pages.push(totalPages.value)
		} else if (page.value >= totalPages.value - 2) {
			// 当前页在后面
			pages.push(1)
			pages.push('...')
			for (let i = totalPages.value - 3; i <= totalPages.value; i++) {
				pages.push(i)
			}
		} else {
			// 当前页在中间
			pages.push(1)
			pages.push('...')
			pages.push(page.value - 1)
			pages.push(page.value)
			pages.push(page.value + 1)
			pages.push('...')
			pages.push(totalPages.value)
		}
	}

	return pages
}

// 格式化金额显示(带正负号)
const formatAmountDisplay = (amount: any) => {
	const num = parseFloat(amount)
	if (isNaN(num)) return '0'
	return num > 0 ? `+${num}` : num.toString()
}

// 获取金额背景样式
const getAmountBgClass = (amount: any) => {
	const num = parseFloat(amount)
	if (isNaN(num) || num === 0) return 'bg-gray-700/50 text-gray-300'
	return num > 0
		? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg shadow-green-500/25'
		: 'bg-gradient-to-r from-red-500 to-pink-500 text-white shadow-lg shadow-red-500/25'
}

// 初始化加载
getPointFn()
</script>

<style lang="scss" scoped>
/* 渐变文字动画 */
.bg-clip-text {
	background-size: 200% 200%;
	animation: gradient 3s ease infinite;
}

@keyframes gradient {
	0% {
		background-position: 0% 50%;
	}

	50% {
		background-position: 100% 50%;
	}

	100% {
		background-position: 0% 50%;
	}
}

/* 卡片悬停效果 */
.bg-gradient-to-br {
	transition: all 0.3s ease;
}

.bg-gradient-to-br:hover {
	transform: translateY(-2px);
	box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
}

/* 图标旋转动画 */
.w-10.h-10 {
	transition: transform 0.3s ease;
}

/* 优化按钮的交互效果 */
button {
	transition: all 0.3s ease-in-out;
	position: relative;
}

button:active:not(:disabled) {
	transform: scale(0.98);
}

button::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: inherit;
	background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
	opacity: 0;
	transition: opacity 0.3s ease;
}

button:hover:not(:disabled)::before {
	opacity: 1;
}

/* 自定义滚动条 */
::-webkit-scrollbar {
	width: 6px;
}

::-webkit-scrollbar-track {
	background: transparent;
}

::-webkit-scrollbar-thumb {
	background: rgba(139, 92, 246, 0.3);
	border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
	background: rgba(139, 92, 246, 0.5);
}

/* 响应式布局优化 */
@media (max-width: 640px) {
	.px-6 {
		padding-left: 1rem;
		padding-right: 1rem;
	}

	button {
		min-height: 44px;
	}
}
</style>
