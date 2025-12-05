<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<!-- Header -->
			<div class="mb-8">
				<h1
					class="text-3xl font-bold bg-gradient-to-r from-purple-400 to-blue-500 bg-clip-text text-transparent mb-2">
					AI智能设计
				</h1>
				<p class="text-gray-400 text-sm">几个字快速生成专业图像</p>
			</div>

			<!-- Loading State -->
			<div v-if="loading && agentList.length === 0" class="flex items-center justify-center py-20">
				<div class="text-center">
					<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mx-auto mb-4"></div>
					<p class="text-gray-400 text-sm">加载中...</p>
				</div>
			</div>

			<!-- Agent List -->
			<div v-else-if="agentList.length > 0" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
				<div v-for="(item, index) in agentList" :key="index" @click="goToImage(item)"
					class="group bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 shadow-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl hover:shadow-purple-500/20 hover:border-purple-500/50 cursor-pointer">
					<!-- Image -->
					<div class="relative aspect-video bg-gray-800 overflow-hidden">
						<img v-if="item.logo" :src="img(item.logo)" :alt="item.name"
							class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" />
						<div v-else
							class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-500/20 to-blue-500/20">
							<svg class="w-16 h-16 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd"
									d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
									clip-rule="evenodd"></path>
							</svg>
						</div>
					</div>

					<!-- Info -->
					<div class="p-4">
						<!-- Name -->
						<h3 class="text-white font-semibold text-base mb-2 line-clamp-2 min-h-[3rem]">
							{{ item.name || '未命名' }}
						</h3>

						<!-- Description -->
						<p v-if="item.description" class="text-gray-400 text-sm mb-3 line-clamp-2 min-h-[2.5rem]">
							{{ item.description }}
						</p>

						<!-- Action Button -->
						<button
							class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white text-sm font-medium transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-lg shadow-purple-500/30">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M13 10V3L4 14h7v7l9-11h-7z"></path>
							</svg>
							<span>立即使用</span>
						</button>
					</div>
				</div>
			</div>

			<!-- Empty State -->
			<div v-else class="flex flex-col items-center justify-center py-20">
				<div
					class="w-20 h-20 rounded-full bg-gradient-to-br from-purple-500/20 to-blue-500/20 flex items-center justify-center mb-4">
					<svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
						</path>
					</svg>
				</div>
				<p class="text-gray-400 text-lg font-medium mb-2">暂无智能体</p>
				<p class="text-gray-500 text-sm">敬请期待更多精彩内容</p>
			</div>

			<!-- Load More Button -->
			<div v-if="hasMore && agentList.length > 0" class="mt-8 flex justify-center">
				<button @click="loadMore" :disabled="loadingMore"
					class="px-8 py-3 rounded-xl bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-600 hover:to-gray-700 text-white font-medium transition-all duration-300 transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center gap-2">
					<svg v-if="loadingMore" class="animate-spin h-5 w-5" fill="none" stroke="currentColor"
						viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
						</path>
					</svg>
					<span>{{ loadingMore ? '加载中...' : '加载更多' }}</span>
				</button>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { getModelList } from '@/addon/ai_image/api/aiimage'
import { img } from '@/utils/common'

const agentList = ref<any[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(true)
const total = ref(0)
const page = ref(1)
const limit = ref(15)

const getAgentListFn = (isLoadMore = false) => {
	if (isLoadMore) {
		loadingMore.value = true
	} else {
		loading.value = true
	}

	getModelList({
		page: page.value,
		limit: limit.value
	}).then((res: any) => {
		if (res.code === 1) {
			const newData = res.data.data || []
			const totalCount = res.data.total || 0

			if (isLoadMore) {
				// 加载更多，追加数据
				agentList.value = [...agentList.value, ...newData]
			} else {
				// 首次加载
				agentList.value = newData
			}

			total.value = totalCount

			// 判断是否还有更多数据
			hasMore.value = agentList.value.length < totalCount
		}
	}).finally(() => {
		loading.value = false
		loadingMore.value = false
	})
}

// 加载更多
const loadMore = () => {
	if (loadingMore.value || !hasMore.value) return
	page.value++
	getAgentListFn(true)
}

// 跳转到图片生成页面
const goToImage = (item: any) => {
	navigateTo({
		path: '/ai_image/image/image',
		query: {
			id: item.id
		}
	})
}

onMounted(() => {
	getAgentListFn()
})
</script>
<style lang="scss" scoped></style>
