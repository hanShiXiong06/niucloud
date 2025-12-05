<template>
	<div class="min-h-screen bg-gradient-to-br from-gray-900 via-slate-900 to-gray-900">
		<div class="max-w-7xl mx-auto p-6">
			<!-- 页面标题 -->
			<div class="mb-8 text-center">
				<h1 class="text-4xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
					图像创作历史
				</h1>
				<p class="text-gray-400 text-lg">查看和管理您的所有创作记录</p>
			</div>

			<!-- 状态导航栏 -->
			<div class="mb-6 flex flex-wrap gap-3 justify-center">
				<button
					v-for="item in stateList"
					:key="item.value"
					@click="changeState(item.value)"
					:class="[
						'px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105',
						state === item.value
							? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg shadow-blue-500/50'
							: 'bg-gray-800/50 text-gray-400 hover:bg-gray-700/50 border border-gray-700'
					]">
					{{ item.name }}
					<span v-if="state === item.value" class="ml-2 inline-block w-2 h-2 bg-white rounded-full animate-pulse"></span>
				</button>
			</div>

			<!-- 加载状态 -->
			<div v-if="loading" class="flex justify-center items-center py-20">
				<div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500"></div>
			</div>

			<!-- 空状态 -->
			<div v-else-if="list.length === 0" class="text-center py-20">
				<div class="text-6xl mb-4">🎨</div>
				<p class="text-gray-400 text-lg">暂无创作记录</p>
				<p class="text-gray-500 text-sm mt-2">开始创作您的第一张AI图像吧！</p>
			</div>

			<!-- 图片列表 -->
			<div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
				<div
					v-for="(item, index) in list"
					:key="item.id"
					class="rounded-xl overflow-hidden bg-gradient-to-br from-gray-800/90 to-gray-900/90 backdrop-blur-sm border border-gray-700/50 shadow-xl hover:shadow-2xl hover:scale-105 transition-all duration-300">
					
					<!-- 图片显示区域 -->
					<div class="p-3">
						<div
							v-if="getDisplayImage(item)"
							class="rounded-lg overflow-hidden cursor-pointer relative group"
							style="width: 100%; aspect-ratio: 1; border: 1px solid rgba(59, 130, 246, 0.2); background: rgba(30, 41, 59, 0.5);">
							<el-image
								:src="getImageUrl(getDisplayImage(item)!)"
								fit="cover"
								class="w-full h-full"
								:preview-src-list="getImageArray(item).map((img: string) => getImageUrl(img))"
								:initial-index="0"
								:preview-teleported="true">
								<template #error>
									<div class="flex flex-col items-center justify-center h-full text-gray-400">
										<el-icon class="text-4xl mb-2"><Picture /></el-icon>
										<span class="text-xs">加载失败</span>
									</div>
								</template>
							</el-image>
							<!-- 悬浮遮罩 -->
							<div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center pointer-events-none">
								<el-icon class="text-white text-4xl"><ZoomIn /></el-icon>
							</div>
						</div>
						<!-- 如果没有图片 -->
						<div
							v-else
							class="rounded-lg flex flex-col items-center justify-center"
							style="width: 100%; aspect-ratio: 1; border: 1px solid rgba(59, 130, 246, 0.2); background: rgba(30, 41, 59, 0.5);">
							<el-icon class="text-gray-500 text-4xl mb-2"><Picture /></el-icon>
							<span class="text-xs text-gray-500">暂无图片</span>
						</div>
					</div>

					<!-- 信息区域 -->
					<div class="px-3 pb-3">
						<div class="flex items-center justify-between mb-2">
							<div class="flex items-center gap-2 flex-1 min-w-0">
								<span
									:class="[
										'px-2 py-1 rounded text-xs font-medium flex-shrink-0',
										item.status === 0
											? 'bg-blue-500/20 text-blue-400'
											: item.status === 1
											? 'bg-green-500/20 text-green-400'
											: 'bg-red-500/20 text-red-400'
									]">
									{{ item.status === 0 ? '生成中' : item.status === 1 ? '已完成' : '失败' }}
								</span>
								<span class="text-xs text-gray-500 truncate">
									{{ item.create_time }}
								</span>
							</div>
						</div>
						<div class="text-sm font-medium   mb-3">
							💎 {{ item.point }}{{ config?.alias_name  }}
						</div>

						<!-- 按钮组 -->
						<div class="flex gap-2">
							<!-- 查看按钮 -->
							<button
								v-if="getDisplayImage(item)"
								@click="openImagePopup(item)"
								class="flex-1 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 border border-blue-500/30 text-blue-400 text-sm font-medium transition-all duration-300 flex items-center justify-center gap-1">
								<el-icon><View /></el-icon>
								<span>查看</span>
							</button>

							<!-- 下载按钮 -->
							<button
								v-if="item.status === 1 && item.images"
								@click="downloadImages(item.images)"
								class="flex-1 px-3 py-2 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white text-sm font-medium transition-all duration-300 flex items-center justify-center gap-1 shadow-lg">
								<el-icon><Download /></el-icon>
								<span>下载</span>
							</button>

							<!-- 刷新按钮 -->
							<button
								v-if="item.status === 0"
								@click="refreshImageStatus(item, index)"
								:disabled="refreshingIndex === index"
								:class="[
									'flex-1 px-3 py-2 rounded-lg text-white text-sm font-medium transition-all duration-300 flex items-center justify-center gap-1',
									refreshingIndex === index
										? 'bg-gray-600 cursor-not-allowed'
										: 'bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 shadow-lg'
								]">
								<el-icon :class="{ 'animate-spin': refreshingIndex === index }"><Refresh /></el-icon>
								<span>{{ refreshingIndex === index ? '刷新中' : '刷新' }}</span>
							</button>

							<!-- 删除按钮 -->
							<button
								@click="deleteImageItem(item.id, index)"
								class="px-3 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 text-red-400 text-sm font-medium transition-all duration-300 flex items-center justify-center">
								<el-icon><Delete /></el-icon>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- 加载更多提示 -->
			<div v-if="!loading && list.length > 0" class="mt-8 flex justify-center">
				<div v-if="hasMore" class="text-gray-400 text-sm py-4">
					<div class="flex items-center gap-2">
						<div class="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-blue-500"></div>
						<span>加载中...</span>
					</div>
				</div>
				<div v-else class="text-gray-500 text-sm py-4">
					已加载全部 {{ total }} 条记录
				</div>
			</div>
		</div>

		<!-- 图片预览弹窗 -->
		<el-dialog
			v-model="previewVisible"
			width="50%"
			top="5vh"
			:append-to-body="true"
			:close-on-click-modal="true"
			class="preview-dialog">
			<div v-if="currentItem" class="preview-container">
				<!-- 头部信息栏 -->
				<div class="preview-header mb-6">
					<div class="flex items-center justify-between flex-wrap gap-4">
						<div class="flex items-center gap-3">
							<div class="w-1 h-8 bg-gradient-to-b from-blue-500 to-purple-600 rounded-full"></div>
							<div>
								<h3 class="text-xl font-bold mb-1">创作详情</h3>
								<p class="text-sm text-gray-400">{{ currentItem.create_time }}</p>
							</div>
						</div>
						<div class="flex items-center gap-3">
							<span
								:class="[
									'px-4 py-2 rounded-lg text-sm font-medium shadow-lg',
									currentItem.status === 0
										? 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
										: currentItem.status === 1
										? 'bg-green-500/20 text-green-400 border border-green-500/30'
										: 'bg-red-500/20 text-red-400 border border-red-500/30'
								]">
								<span class="inline-block w-2 h-2 rounded-full mr-2 animate-pulse"
									:class="[
										currentItem.status === 0 ? 'bg-blue-400' : currentItem.status === 1 ? 'bg-green-400' : 'bg-red-400'
									]"></span>
								{{ currentItem.status === 0 ? '生成中' : currentItem.status === 1 ? '已完成' : '失败' }}
							</span>
							<div class="px-4 py-2 rounded-lg bg-gradient-to-r from-purple-500/20 to-blue-500/20 border border-purple-500/30">
								<span class="text-sm">💎 {{ currentItem.point }}{{ config?.alias_name  }}</span>
							</div>
						</div>
					</div>
				</div>

				<!-- 提示词信息 -->
				<div v-if="currentItem.prompt" class="prompt-section mb-4">
					<div class="rounded-lg p-4 border-l-4 border-l-orange-500">
						<div class="flex items-center justify-between mb-3">
							<div class="flex items-center gap-2">
								<span class="text-sm font-medium">✨ 提示词</span>
							</div>
							<button
								@click="copyPrompt(currentItem.prompt)"
								class="px-3 py-1.5 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded text-xs font-medium transition-all duration-200 flex items-center gap-1.5">
								<el-icon :size="13"><DocumentCopy /></el-icon>
								<span>复制</span>
							</button>
						</div>
						<p class="text-sm leading-relaxed">{{ currentItem.prompt }}</p>
					</div>
				</div>

				<!-- 图片展示区域 -->
				<div class="preview-images mb-6">
					<!-- 当有参考图像时，使用左右布局 -->
					<div v-if="getReferenceImages(currentItem).length > 0 && getImageArray(currentItem).length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
						<!-- 左侧：参考图像 -->
						<div>
							<div class="flex items-center gap-2 mb-3">
								<div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
									<span class="text-white text-sm">🖼️</span>
								</div>
								<h4 class="text-base font-semibold ">参考图像</h4>
								<span class="text-xs text-gray-400 ml-2">{{ getReferenceImages(currentItem).length }} 张</span>
							</div>
							<div class="grid grid-cols-2 gap-3" style="max-height: 500px; overflow-y: auto;">
								<div
									v-for="(refImg, idx) in getReferenceImages(currentItem)"
									:key="idx"
									class="image-card group relative overflow-hidden rounded-xl hover:ring-2 hover:ring-purple-500/60 transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/20">
									<!-- 图片序号标签 -->
									<div class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 bg-black/70 backdrop-blur-sm rounded text-xs font-medium text-white">
										{{ idx + 1 }}
									</div>
									
									<el-image
										:src="getImageUrl(refImg)"
										fit="cover"
										class="w-full aspect-square rounded-xl cursor-pointer"
										:preview-src-list="getReferenceImages(currentItem).map((img: string) => getImageUrl(img))"
										:initial-index="idx">
										<template #placeholder>
											<div class="flex items-center justify-center h-full bg-gray-800/50">
												<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
											</div>
										</template>
										<template #error>
											<div class="flex flex-col items-center justify-center h-full bg-gray-800/50 text-gray-400">
												<el-icon class="text-2xl mb-1"><Picture /></el-icon>
												<span class="text-xs">加载失败</span>
											</div>
										</template>
									</el-image>
									
									<!-- 悬浮操作层 -->
									<div class="absolute inset-0 rounded-xl bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
										<button
											@click="downloadImages([refImg])"
											class="px-3 py-1.5 bg-purple-500 hover:bg-purple-600 text-white rounded text-xs font-medium transition-all duration-300 flex items-center gap-1 shadow-lg">
											<el-icon :size="14"><Download /></el-icon>
											<span>下载</span>
										</button>
									</div>
								</div>
							</div>
						</div>

						<!-- 右侧：生成的图像 -->
						<div>
							<div class="flex items-center gap-2 mb-3">
								<div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center">
									<span class="text-white text-sm">🎨</span>
								</div>
								<h4 class="text-base font-semibold">生成的图像</h4>
							</div>
							<div class="image-card group relative overflow-hidden rounded-xl hover:ring-2 hover:ring-blue-500/60 transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/20">
								<el-image
									ref="generatedImageRef1"
									:src="getImageUrl(getImageArray(currentItem)[0])"
									fit="contain"
									class="w-full rounded-xl cursor-pointer bg-gray-900/30"
									style="max-height: 500px;"
									:preview-src-list="getImageArray(currentItem).map((img: string) => getImageUrl(img))"
									:initial-index="0"
									:preview-teleported="true">
									<template #placeholder>
										<div class="flex items-center justify-center h-full bg-gray-800/50" style="min-height: 300px;">
											<div class="text-center">
												<div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
												<p class="text-gray-400 text-sm">加载中...</p>
											</div>
										</div>
									</template>
									<template #error>
										<div class="flex flex-col items-center justify-center h-full bg-gray-800/50 text-gray-400" style="min-height: 300px;">
											<el-icon class="text-5xl mb-3"><Picture /></el-icon>
											<span class="text-base">加载失败</span>
										</div>
									</template>
								</el-image>
								
								<!-- 悬浮操作层 -->
								<div class="absolute inset-0 rounded-xl bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3">
									<button
										@click.stop="previewGeneratedImage(1)"
										class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg text-base font-medium transition-all duration-300 flex items-center gap-2 shadow-lg transform hover:scale-105">
										<el-icon :size="18"><ZoomIn /></el-icon>
										<span>放大预览</span>
									</button>
									<button
										@click.stop="downloadImages([getImageArray(currentItem)[0]])"
										class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white rounded-lg text-base font-medium transition-all duration-300 flex items-center gap-2 shadow-lg transform hover:scale-105">
										<el-icon :size="18"><Download /></el-icon>
										<span>下载图片</span>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- 当没有参考图像时，只显示生成的图像（原布局） -->
					<div v-else-if="getImageArray(currentItem).length > 0" class="mb-6">
						<div class="flex items-center gap-2 mb-3">
							<div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center">
								<span class="text-white text-sm">🎨</span>
							</div>
							<h4 class="text-base font-semibold">生成的图像</h4>
						</div>
						<div class="image-card group relative overflow-hidden rounded-xl hover:ring-2 hover:ring-blue-500/60 transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/20">
							<!-- 图片序号标签 -->
							<div class="absolute top-4 left-4 z-10 px-3 py-1.5 bg-black/70 backdrop-blur-sm rounded-lg text-sm font-medium text-white shadow-lg">
								1 / {{ getImageArray(currentItem).length }}
							</div>
							
							<el-image
								ref="generatedImageRef2"
								:src="getImageUrl(getImageArray(currentItem)[0])"
								fit="contain"
								class="w-full rounded-xl cursor-pointer bg-gray-900/30"
								style="max-height: 600px;"
								:preview-src-list="getImageArray(currentItem).map((img: string) => getImageUrl(img))"
								:initial-index="0"
								:preview-teleported="true">
								<template #placeholder>
									<div class="flex items-center justify-center h-full bg-gray-800/50" style="min-height: 400px;">
										<div class="text-center">
											<div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
											<p class="text-gray-400 text-sm">加载中...</p>
										</div>
									</div>
								</template>
								<template #error>
									<div class="flex flex-col items-center justify-center h-full bg-gray-800/50 text-gray-400" style="min-height: 400px;">
										<el-icon class="text-5xl mb-3"><Picture /></el-icon>
										<span class="text-base">加载失败</span>
									</div>
								</template>
							</el-image>
							
							<!-- 悬浮操作层 -->
							<div class="absolute inset-0 rounded-xl bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3">
								<button
									@click.stop="previewGeneratedImage(2)"
									class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg text-base font-medium transition-all duration-300 flex items-center gap-2 shadow-lg transform hover:scale-105">
									<el-icon :size="18"><ZoomIn /></el-icon>
									<span>放大预览</span>
								</button>
								<button
									@click.stop="downloadImages([getImageArray(currentItem)[0]])"
									class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white rounded-lg text-base font-medium transition-all duration-300 flex items-center gap-2 shadow-lg transform hover:scale-105">
									<el-icon :size="18"><Download /></el-icon>
									<span>下载图片</span>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- 底部操作栏 -->
				<div class="preview-footer mt-6 pt-4 border-t border-gray-700/50">
					<div class="flex items-center justify-between flex-wrap gap-3">
						<div class="text-sm text-gray-400">
							<span>共 {{ getImageArray(currentItem).length }} 张图片</span>
						</div>
						<div class="flex gap-3">
							<button
								v-if="currentItem.status === 1"
								@click="downloadImages(currentItem.images)"
								class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white rounded-lg text-sm font-medium transition-all duration-300 flex items-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-105">
								<el-icon><Download /></el-icon>
								<span>下载</span>
							</button>
							<button
								@click="previewVisible = false"
								class="px-6 py-2.5 bg-gray-700/50 hover:bg-gray-600/50 text-gray-300 rounded-lg text-sm font-medium transition-all duration-300 border border-gray-600/50">
								关闭
							</button>
						</div>
					</div>
				</div>
			</div>
		</el-dialog>
	</div>
</template>

<script lang="ts" setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { getImageList, getConfig, getImageInfo, deleteImage } from '@/addon/ai_image/api/aiimage'
import { img } from '@/utils/common'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Picture, ZoomIn, View, Download, Refresh, Delete, DocumentCopy } from '@element-plus/icons-vue'

const stateList = ref([
	{
		name: '全部',
		value: '',
	},
	{
		name: '生成中',
		value: 0,
	},
	{
		name: '已完成',
		value: 1,
	},
	{
		name: '失败',
		value: 2,
	}
])
const state = ref<string | number>('')
const page = ref(1)
const limit = ref(20)
const total = ref(0)
const list = ref<any[]>([])
const loading = ref(false)
const config = ref<any>(null)
const previewVisible = ref(false)
const currentItem = ref<any>(null)
const refreshingIndex = ref<number>(-1)
const hasMore = ref(true)
const isLoadingMore = ref(false)
const generatedImageRef1 = ref<any>(null)
const generatedImageRef2 = ref<any>(null)

// 获取配置
const getConfigFn = () => {
	getConfig().then((res: any) => {
		config.value = res.data
	})
}
getConfigFn()

// 获取图片列表
const getImageListFn = (isLoadMore = false) => {
	if (isLoadMore) {
		if (isLoadingMore.value || !hasMore.value) return
		isLoadingMore.value = true
	} else {
		loading.value = true
	}

	getImageList({
		page: page.value,
		limit: limit.value,
		status: state.value
	}).then((res: any) => {
		const newData = res.data.data || []
		total.value = res.data.total

		if (isLoadMore) {
			// 追加数据
			list.value = [...list.value, ...newData]
		} else {
			// 替换数据
			list.value = newData
		}

		// 检查是否还有更多数据
		hasMore.value = list.value.length < total.value
	}).finally(() => {
		loading.value = false
		isLoadingMore.value = false
	})
}
getImageListFn()

// 切换状态
const changeState = (newState: string | number) => {
	state.value = newState
	page.value = 1
	list.value = []
	hasMore.value = true
	getImageListFn()
}

// 滚动加载更多
const handleScroll = () => {
	const scrollTop = window.pageYOffset || document.documentElement.scrollTop
	const windowHeight = window.innerHeight
	const documentHeight = document.documentElement.scrollHeight

	// 距离底部200px时触发加载
	if (scrollTop + windowHeight >= documentHeight - 200) {
		if (!isLoadingMore.value && hasMore.value && !loading.value) {
			page.value++
			getImageListFn(true)
		}
	}
}

// 组件挂载时添加滚动监听
onMounted(() => {
	window.addEventListener('scroll', handleScroll)
})

// 组件卸载时移除滚动监听
onUnmounted(() => {
	window.removeEventListener('scroll', handleScroll)
})

// 获取图片完整URL
const getImageUrl = (url: string) => {
	return img(url)
}

// 获取显示的图片(第一张)
const getDisplayImage = (item: any) => {
	if (item.images) {
		if (Array.isArray(item.images)) {
			return item.images[0] || null
		}
		if (typeof item.images === 'string') {
			try {
				const parsed = JSON.parse(item.images)
				if (Array.isArray(parsed)) {
					return parsed[0] || null
				}
				return item.images
			} catch {
				return item.images
			}
		}
	}
	return null
}

// 获取所有图片数组
const getImageArray = (item: any) => {
	if (!item.images) return []
	if (Array.isArray(item.images)) return item.images
	if (typeof item.images === 'string') {
		try {
			const parsed = JSON.parse(item.images)
			return Array.isArray(parsed) ? parsed : [item.images]
		} catch {
			return [item.images]
		}
	}
	return []
}

// 获取参考图像数组（支持 reference_images 和 image_urls 字段）
const getReferenceImages = (item: any) => {
	// 优先使用 image_urls 字段
	if (item.image_urls) {
		if (Array.isArray(item.image_urls)) return item.image_urls
		if (typeof item.image_urls === 'string') {
			try {
				const parsed = JSON.parse(item.image_urls)
				return Array.isArray(parsed) ? parsed : [item.image_urls]
			} catch {
				return [item.image_urls]
			}
		}
	}
	
	// 其次使用 reference_images 字段
	if (item.reference_images) {
		if (Array.isArray(item.reference_images)) return item.reference_images
		if (typeof item.reference_images === 'string') {
			try {
				const parsed = JSON.parse(item.reference_images)
				return Array.isArray(parsed) ? parsed : [item.reference_images]
			} catch {
				return [item.reference_images]
			}
		}
	}
	
	return []
}

// 打开图片预览
const openImagePopup = (item: any) => {
	currentItem.value = item
	previewVisible.value = true
}

// 复制提示词
const copyPrompt = (prompt: string) => {
	if (!prompt) {
		ElMessage.warning('提示词为空')
		return
	}
	
	navigator.clipboard.writeText(prompt).then(() => {
		ElMessage.success('复制成功')
	}).catch(() => {
		// 降级方案：使用传统方法复制
		const textarea = document.createElement('textarea')
		textarea.value = prompt
		textarea.style.position = 'fixed'
		textarea.style.opacity = '0'
		document.body.appendChild(textarea)
		textarea.select()
		try {
			document.execCommand('copy')
			ElMessage.success('复制成功')
		} catch (err) {
			ElMessage.error('复制失败')
		}
		document.body.removeChild(textarea)
	})
}

// 预览生成的图片 - 通过 ref 触发 el-image 的预览功能
const previewGeneratedImage = (refIndex: number) => {
	const imageRef = refIndex === 1 ? generatedImageRef1.value : generatedImageRef2.value
	if (imageRef) {
		// 查找 el-image 组件内部的图片元素
		const imgElement = imageRef.$el?.querySelector('img')
		if (imgElement) {
			// 触发点击事件来打开预览
			imgElement.click()
		}
	}
}

// 下载图片
const downloadImages = (images: any) => {
	const imageArray = getImageArray({ images })
	if (imageArray.length === 0) {
		ElMessage.warning('没有可下载的图片')
		return
	}

	imageArray.forEach((imageUrl: string, index: number) => {
		const link = document.createElement('a')
		link.href = getImageUrl(imageUrl)
		link.download = `image_${Date.now()}_${index + 1}.png`
		link.target = '_blank'
		document.body.appendChild(link)
		link.click()
		document.body.removeChild(link)
	})

	ElMessage.success(`开始下载 ${imageArray.length} 张图片`)
}

// 刷新图片状态
const refreshImageStatus = (item: any, index: number) => {
	if (refreshingIndex.value !== -1) return

	refreshingIndex.value = index
	getImageInfo(item.id).then((res: any) => {
		if (res.code === 1) {
			list.value[index] = res.data
			ElMessage.success('刷新成功')
		} else {
			ElMessage.error(res.msg || '刷新失败')
		}
	}).catch(() => {
		ElMessage.error('刷新失败')
	}).finally(() => {
		refreshingIndex.value = -1
	})
}

// 删除图片
const deleteImageItem = (id: number, index: number) => {
	ElMessageBox.confirm(
		'确定要删除这条创作记录吗?',
		'提示',
		{
			confirmButtonText: '确定',
			cancelButtonText: '取消',
			type: 'warning',
		}
	).then(() => {
		deleteImage(id).then((res: any) => {
			if (res.code === 1) {
				list.value.splice(index, 1)
				total.value--
				ElMessage.success('删除成功')
			} else {
				ElMessage.error(res.msg || '删除失败')
			}
		}).catch(() => {
			ElMessage.error('删除失败')
		})
	}).catch(() => {
		// 用户取消删除
	})
}
</script>
<style lang="scss" scoped>
// 预览对话框样式
:deep(.preview-dialog) {
	.el-dialog {
		background: linear-gradient(135deg, rgba(17, 24, 39, 0.98) 0%, rgba(31, 41, 55, 0.98) 100%);
		border: 1px solid rgba(75, 85, 99, 0.3);
		border-radius: 1rem;
		box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
		backdrop-filter: blur(20px);
		max-width: 1200px;
		margin: 0 auto;
	}

	// 响应式宽度调整
	@media (max-width: 1024px) {
		.el-dialog {
			width: 90% !important;
		}
	}

	@media (max-width: 768px) {
		.el-dialog {
			width: 95% !important;
			max-width: 100%;
		}
	}

	.el-dialog__header {
		display: none;
	}

	.el-dialog__body {
		padding: 2rem;
		max-height: 90vh;
		overflow-y: auto;

		&::-webkit-scrollbar {
			width: 8px;
		}

		&::-webkit-scrollbar-track {
			background: rgba(31, 41, 55, 0.5);
			border-radius: 4px;
		}

		&::-webkit-scrollbar-thumb {
			background: rgba(59, 130, 246, 0.5);
			border-radius: 4px;

			&:hover {
				background: rgba(59, 130, 246, 0.7);
			}
		}
	}

	.el-dialog__close {
		color: #9ca3af;
		font-size: 20px;

		&:hover {
			color: #60a5fa;
		}
	}
}

.preview-container {
	animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
	from {
		opacity: 0;
		transform: translateY(20px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.preview-header {
	animation: slideInLeft 0.4s ease-out;
}

@keyframes slideInLeft {
	from {
		opacity: 0;
		transform: translateX(-20px);
	}
	to {
		opacity: 1;
		transform: translateX(0);
	}
}

.image-card {
	animation: scaleIn 0.3s ease-out;
	animation-fill-mode: both;

	@for $i from 1 through 12 {
		&:nth-child(#{$i}) {
			animation-delay: #{$i * 0.05}s;
		}
	}
}

@keyframes scaleIn {
	from {
		opacity: 0;
		transform: scale(0.9);
	}
	to {
		opacity: 1;
		transform: scale(1);
	}
}

.prompt-section {
	animation: fadeIn 0.5s ease-out 0.3s both;
}

@keyframes fadeIn {
	from {
		opacity: 0;
	}
	to {
		opacity: 1;
	}
}

.preview-footer {
	animation: slideInUp 0.4s ease-out 0.2s both;
}

@keyframes slideInUp {
	from {
		opacity: 0;
		transform: translateY(10px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}
</style>
