<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 py-12 px-4 sm:px-6 lg:px-8">
		<div class="max-w-7xl mx-auto">
			<!-- Header -->
			<div class="mb-12">
				<div class="flex items-center justify-between mb-6">
					<div>
						<h1
							class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
							卡密列表
						</h1>
						<p class="text-gray-400 text-sm">管理您的卡密信息</p>
					</div>
					<div v-if="total > 0" class="flex items-center space-x-2 text-sm text-gray-400">
						<span>共</span>
						<span class="text-purple-400 font-medium">{{ total }}</span>
						<span>张卡密</span>
					</div>
				</div>
			</div>

			<!-- Loading State -->
			<div v-if="loading" class="flex items-center justify-center py-20">
				<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
			</div>

			<!-- Empty State -->
			<div v-else-if="!cardList || cardList.length === 0" class="flex flex-col items-center justify-center py-20">
				<div class="w-24 h-24 mb-6 rounded-full bg-gray-800/50 flex items-center justify-center">
					<svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
						</path>
					</svg>
				</div>
				<h3 class="text-xl font-semibold text-gray-400 mb-2">暂无卡密</h3>
				<p class="text-gray-500 text-sm">您还没有任何卡密记录</p>
			</div>

			<!-- Card List -->
			<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
				<div v-for="item in cardList" :key="item.id"
					class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl border border-gray-700/50 shadow-xl overflow-hidden transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl hover:shadow-purple-500/20 hover:border-purple-500/50">

					<!-- Card Header -->
					<div class="p-6 border-b border-gray-700/50">
						<div class="flex items-start justify-between mb-4">
							<div class="flex-1">
								<div class="flex items-center gap-2 mb-2">
									<svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor"
										viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
											d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
										</path>
									</svg>
									<h3 class="text-sm font-medium text-gray-400">卡密码</h3>
								</div>
								<p class="text-white font-mono text-lg break-all">{{ item.num }}</p>
							</div>
						</div>

						<!-- Expire Time -->
						<div class="flex items-center gap-2 text-sm text-gray-400">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
								</path>
							</svg>
							<span>{{ item.expire_time }}</span>
						</div>
					</div>

					<!-- Card Body -->
					<div class="p-6">
						<!-- Status Badges -->
						<div class="flex items-center gap-2 mb-4">
							<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-white/10"
								:style="item.is_use == 1 ? 'background-color: rgba(16,185,129,0.2); color:#A7F3D0' : 'background-color: rgba(55,65,81,0.4); color:#E5E7EB'">
								<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
									<path v-if="item.is_use == 1" fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
										clip-rule="evenodd">
									</path>
									<path v-else fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
										clip-rule="evenodd">
									</path>
								</svg>
								<span>{{ item.is_use == 1 ? '已使用' : '未使用' }}</span>
							</div>
							<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-white/10"
								:style="item.is_export == 1 ? 'background-color: rgba(6,182,212,0.2); color:#A5F3FC' : 'background-color: rgba(55,65,81,0.4); color:#E5E7EB'">
								<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
									<path v-if="item.is_export == 1" fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
										clip-rule="evenodd">
									</path>
									<path v-else fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
										clip-rule="evenodd">
									</path>
								</svg>
								<span>{{ item.is_export == 1 ? '已分配' : '未分配' }}</span>
							</div>
						</div>

						<!-- Action Buttons -->
						<div class="flex items-center gap-2">
							<button @click="changeExportFn(item.id)"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 hover:text-cyan-300 transition-colors border border-cyan-500/20 hover:border-cyan-500/40 text-sm font-medium">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4">
									</path>
								</svg>
								<span>{{ item.is_export == 1 ? '取消分配' : '分配' }}</span>
							</button>
							<button @click="deltetCardFn(item.id)"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 transition-colors border border-red-500/20 hover:border-red-500/40 text-sm font-medium">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
									</path>
								</svg>
								<span>删除</span>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Load More / Loading -->
			<div class="mt-12 flex justify-center">
				<div v-if="loadingMore" class="flex items-center gap-2 text-gray-400">
					<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-purple-500"></div>
					<span class="text-sm">加载中...</span>
				</div>
				<button v-else-if="hasMore" @click="loadMore"
					class="px-6 py-3 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-medium transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl hover:shadow-purple-500/25">
					加载更多
				</button>
				<div v-else-if="cardList && cardList.length > 0" class="text-gray-500 text-sm">
					没有更多了
				</div>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { getCardList, changeExport, deltetCard, getConfig } from '@/addon/ai_image/api/aiimage'
import { ElMessage, ElMessageBox } from 'element-plus'

const cardList = ref<any[]>([])
const page = ref(1)
const limit = ref(9)
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(true)
const total = ref(0)

const changeExportFn = (id: number) => {
	changeExport(id).then((res: any) => {
		ElMessage.success('操作成功')
		// 重置分页并重新加载
		page.value = 1
		cardList.value = []
		getCardListFn()
	})
}

const deltetCardFn = async (id: number) => {
	try {
		await ElMessageBox.confirm(
			'删除后将无法恢复，确定要删除这张卡密吗？',
			'确认删除',
			{
				confirmButtonText: '确定删除',
				cancelButtonText: '取消',
				type: 'warning',
				confirmButtonClass: 'el-button--danger'
			}
		)

		const res: any = await deltetCard(id)
		if (res.code === 1) {
			ElMessage.success('删除成功')
			// 重置分页并重新加载
			page.value = 1
			cardList.value = []
			getCardListFn()
		} else {
			ElMessage.error(res.msg || '删除失败')
		}
	} catch (error: any) {
		// 用户取消删除
		if (error === 'cancel') {
			return
		}
		console.error('删除卡密失败:', error)
		ElMessage.error('删除失败')
	}
}

const getCardListFn = (isLoadMore = false) => {
	if (isLoadMore) {
		loadingMore.value = true
	} else {
		loading.value = true
	}

	getCardList({
		page: page.value,
		limit: limit.value
	}).then((res: any) => {
		const newData = res.data.data || []
		const totalCount = res.data.total || 0

		if (isLoadMore) {
			// 加载更多，追加数据
			cardList.value = [...cardList.value, ...newData]
		} else {
			// 首次加载
			cardList.value = newData
		}

		total.value = totalCount

		// 判断是否还有更多数据
		hasMore.value = cardList.value.length < totalCount
	}).finally(() => {
		loading.value = false
		loadingMore.value = false
	})
}

// 加载更多
const loadMore = () => {
	if (loadingMore.value || !hasMore.value) return
	page.value++
	getCardListFn(true)
}

onMounted(() => {
	getCardListFn()
})
</script>
<style lang="scss" scoped></style>
