<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
		<div class="max-w-6xl mx-auto">
			<!-- Header -->
			<div class="mb-12">
				<div class="flex items-center justify-between mb-6">
					<div>
						<h1
							class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
							帮助中心
						</h1>
						<p class="text-gray-400 text-sm">常见问题与使用指南</p>
					</div>
					<div class="flex items-center space-x-2 text-sm text-gray-400">
						<span>共</span>
						<span class="text-purple-400 font-medium">{{ total }}</span>
						<span>篇文章</span>
					</div>
				</div>
			</div>

			<!-- Help List -->
			<div class="space-y-4 mb-8">
				<div v-for="(item, index) in list" :key="index" @click="showDetail(item)"
					class="group bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl border border-gray-700/50 shadow-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl hover:border-purple-500/30 cursor-pointer">

					<div class="p-6">
						<div class="flex items-start space-x-6">
							<!-- 帮助图片 -->
							<div class="flex-shrink-0" v-if="item.image">
								<div
									class="w-32 h-32 rounded-lg overflow-hidden bg-gray-700/30 border border-gray-600/30 transition-transform duration-300 group-hover:scale-105">
									<img :src="img(item.image)" :alt="item.title" class="w-full h-full object-cover">
								</div>
							</div>

							<!-- 帮助信息 -->
							<div class="flex-1 min-w-0">
								<div class="mb-3">
									<h3
										class="text-xl font-bold text-white mb-2 group-hover:text-purple-400 transition-colors duration-300">
										{{ item.title }}
									</h3>
									<p class="text-gray-400 text-sm line-clamp-2">
										{{ item.desc }}
									</p>
								</div>

								<!-- 底部信息 -->
								<div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-700/50">
									<div class="flex items-center space-x-2 text-sm text-gray-500">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
										</svg>
										<span>{{ formatTime(item.create_time) }}</span>
									</div>

									<div
										class="flex items-center space-x-1 text-purple-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
										<span class="text-sm font-medium">查看详情</span>
										<svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1"
											fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M9 5l7 7-7 7"></path>
										</svg>
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
								d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
							</path>
						</svg>
					</div>
					<p class="text-gray-400 font-medium text-lg mb-2">暂无帮助文章</p>
					<p class="text-gray-500 text-sm">目前还没有任何帮助内容</p>
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

				<!-- Load More Loading -->
				<div v-if="loadingMore" class="text-center py-8">
					<div class="flex items-center justify-center gap-2 text-gray-400">
						<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-purple-500"></div>
						<span class="text-sm">加载更多中...</span>
					</div>
				</div>

				<!-- No More Data -->
				<div v-if="!loading && !loadingMore && list.length > 0 && !hasMore" class="text-center py-8">
					<p class="text-gray-500 text-sm">没有更多了</p>
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

		<!-- 详情抽屉 -->
		<el-drawer v-model="dialogVisible" :title="currentItem?.title" direction="rtl" size="600px" class="help-drawer"
			:close-on-click-modal="true" :close-on-press-escape="true">
			<div class="drawer-content">
				<!-- 图片 - 缩略图 -->
				<div v-if="currentItem?.image" class="mb-4 flex justify-center">
					<img :src="img(currentItem.image)" :alt="currentItem.title"
						class="max-w-[200px] h-auto rounded-lg shadow-md">
				</div>

				<!-- 描述 -->
				<div v-if="currentItem?.desc" class="mb-6 p-3 bg-gray-50 rounded-lg border border-gray-200">
					<p class="text-gray-600 text-sm leading-relaxed">{{ currentItem.desc }}</p>
				</div>

				<!-- 富文本内容 - 主体 -->
				<div v-if="currentItem?.content" class="prose prose-sm max-w-none">
					<div v-html="currentItem.content" class="rich-text-content"></div>
				</div>

				<!-- 时间信息 -->
				<div class="mt-6 pt-4 border-t border-gray-200 flex items-center justify-between text-sm text-gray-500">
					<div class="flex items-center space-x-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
						</svg>
						<span>发布时间: {{ formatTime(currentItem?.create_time || '') }}</span>
					</div>
				</div>
			</div>
		</el-drawer>
	</div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { getHelpList } from '@/addon/ai_image/api/help'
import { img } from '@/utils/common'

interface HelpItem {
	id: number | string
	title: string
	desc: string
	image?: string
	create_time: string
	content?: string
}

const page = ref(1)
const limit = ref(10)
const list = ref<HelpItem[]>([])
const total = ref(0)
const loading = ref(false)
const loadingMore = ref(false)
const hasMore = ref(true)
const error = ref<any>(null)

// 弹窗相关
const dialogVisible = ref(false)
const currentItem = ref<HelpItem | null>(null)

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

// 获取帮助列表
const getHelpListFn = (isLoadMore = false) => {
	if (isLoadMore) {
		loadingMore.value = true
	} else {
		loading.value = true
	}

	error.value = null
	getHelpList({
		page: page.value,
		limit: limit.value,
	}).then((res: any) => {
		const newData = res.data.data || []
		const totalCount = res.data.total || res.data.count || 0

		if (isLoadMore) {
			// 加载更多，追加数据
			list.value = [...list.value, ...newData]
		} else {
			// 首次加载
			list.value = newData
		}

		total.value = totalCount

		// 判断是否还有更多数据
		hasMore.value = list.value.length < totalCount
	}).catch((err: any) => {
		error.value = err
		console.error('Failed to fetch help list:', err)
	}).finally(() => {
		loading.value = false
		loadingMore.value = false
	})
}

// 加载更多
const loadMore = () => {
	if (loadingMore.value || !hasMore.value) return
	page.value++
	getHelpListFn(true)
}

// 滚动加载处理
const handleScroll = () => {
	// 如果正在加载或没有更多数据，则不处理
	if (loadingMore.value || !hasMore.value || loading.value) return

	// 获取滚动位置
	const scrollTop = window.pageYOffset || document.documentElement.scrollTop
	const windowHeight = window.innerHeight
	const documentHeight = document.documentElement.scrollHeight

	// 当滚动到距离底部200px时触发加载
	if (scrollTop + windowHeight >= documentHeight - 200) {
		loadMore()
	}
}

// 切换页码
const changePage = (p: number) => {
	if (p === page.value) return
	page.value = p
	getHelpListFn()
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

// 显示详情
const showDetail = (item: HelpItem) => {
	currentItem.value = item
	dialogVisible.value = true
}

onMounted(() => {
	getHelpListFn()
	// 添加滚动监听
	window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
	// 移除滚动监听
	window.removeEventListener('scroll', handleScroll)
})
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

/* 文本截断 */
.line-clamp-2 {
	display: -webkit-box;
	line-clamp: 2;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* 响应式 */
@media (max-width: 768px) {
	.text-4xl {
		font-size: 2rem;
	}
}
</style>

<style lang="scss">
/* 抽屉样式 */
.help-drawer {
	.el-drawer__header {
		background: linear-gradient(to right, #8b5cf6, #3b82f6);
		padding: 20px 24px;
		margin: 0;
		border-bottom: none;

		.el-drawer__title {
			color: white;
			font-size: 20px;
			font-weight: 600;
		}

		.el-drawer__close-btn {
			color: white;
			font-size: 24px;

			.el-icon {
				color: white;
			}

			&:hover {
				color: rgba(255, 255, 255, 0.8);

				.el-icon {
					color: rgba(255, 255, 255, 0.8);
				}
			}
		}
	}

	.el-drawer__body {
		padding: 24px;
		overflow-y: auto;
	}

	.drawer-content {
		color: #374151;
	}
}

/* 富文本内容样式 */
.rich-text-content {
	line-height: 1.8;
	color: #374151;

	:deep(h1),
	:deep(h2),
	:deep(h3),
	:deep(h4),
	:deep(h5),
	:deep(h6) {
		margin-top: 1.5em;
		margin-bottom: 0.75em;
		font-weight: 600;
		color: #111827;
	}

	:deep(h1) {
		font-size: 1.875rem;
	}

	:deep(h2) {
		font-size: 1.5rem;
	}

	:deep(h3) {
		font-size: 1.25rem;
	}

	:deep(h4) {
		font-size: 1.125rem;
	}

	:deep(p) {
		margin-bottom: 1em;
	}

	:deep(ul),
	:deep(ol) {
		margin-bottom: 1em;
		padding-left: 2em;
	}

	:deep(li) {
		margin-bottom: 0.5em;
	}

	:deep(img) {
		max-width: 100%;
		height: auto;
		border-radius: 8px;
		margin: 1em 0;
	}

	:deep(a) {
		color: #8b5cf6;
		text-decoration: underline;

		&:hover {
			color: #7c3aed;
		}
	}

	:deep(blockquote) {
		border-left: 4px solid #8b5cf6;
		padding-left: 1em;
		margin: 1em 0;
		color: #6b7280;
		font-style: italic;
	}

	:deep(code) {
		background: #f3f4f6;
		padding: 0.2em 0.4em;
		border-radius: 4px;
		font-size: 0.875em;
		font-family: 'Courier New', monospace;
	}

	:deep(pre) {
		background: #1f2937;
		color: #f9fafb;
		padding: 1em;
		border-radius: 8px;
		overflow-x: auto;
		margin: 1em 0;

		code {
			background: transparent;
			padding: 0;
			color: inherit;
		}
	}

	:deep(table) {
		width: 100%;
		border-collapse: collapse;
		margin: 1em 0;

		th,
		td {
			border: 1px solid #e5e7eb;
			padding: 0.75em;
			text-align: left;
		}

		th {
			background: #f9fafb;
			font-weight: 600;
		}
	}

	:deep(hr) {
		border: none;
		border-top: 2px solid #e5e7eb;
		margin: 2em 0;
	}
}
</style>
