<template>
	<div class="min-h-screen relative text-white">
		<!-- 背景层 -->
		<div class="bg-layer"
			style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #1e293b 0%, #0f172a 50%, #1e293b 100%);">
		</div>
		<!-- 顶部光晕 -->
		<div class="glow-layer-1"
			style="position: fixed; top: -150px; left: 20%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100px);">
		</div>
		<!-- 底部光晕 -->
		<div class="glow-layer-2"
			style="position: fixed; bottom: -150px; right: 20%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100px);">
		</div>

		<div class="relative px-6 py-6" style="position: relative; z-index: 10;" v-if="config">
			<!-- 页面标题和描述 -->
			<div class="mb-8 text-center">
				<h1
					class="text-4xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">
					{{ model.name }}</h1>
				<p class="text-gray-400 text-lg max-w-3xl mx-auto">{{ model.desc }}</p>
			</div>

			<!-- 主要内容区域 - 左右分栏布局 -->
			<div class="grid grid-cols-1 xl:grid-cols-3 gap-8 max-w-7xl mx-auto">
				<!-- 左侧主要操作区域 -->
				<div class="xl:col-span-2 space-y-6">

					<!-- 参考图上传区域 -->
					<div v-if="model.is_upload_image == 1"
						class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-6 border border-gray-700/50 shadow-xl">
						<div class="flex items-center mb-6">
							<div
								class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
								<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
										clip-rule="evenodd" />
								</svg>
							</div>
							<h2 class="text-xl font-semibold text-white">参考图</h2>
						</div>
						<UploadImg v-model="formData.image_urls" :max="model.limit_image ? model.limit_image : 3" />
					</div>

					<!-- 提示词区域 -->
					<div v-if="model.is_prompt == 1"
						class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-6 border border-gray-700/50 shadow-xl">
						<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
							<div class="flex items-center">
								<div
									class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
									<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
										<path fill-rule="evenodd"
											d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
											clip-rule="evenodd" />
									</svg>
								</div>
								<h2 class="text-xl font-semibold text-white">提示词</h2>
							</div>
							<button @click="sendTextFn" :disabled="aiLoading" :loading="aiLoading"
								loading-text="AI润色中..."
								class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap shadow-lg hover:shadow-xl transform hover:scale-105">
								✨ AI润色(消耗{{ config?.chat_point }}{{ config?.alias_name }})
							</button>
						</div>

						<div class="mb-6">
							<textarea v-model="formData.prompt"
								placeholder="示例：黄昏的城市街道，霓虹灯闪烁在湿润的地面上，一位穿风衣的青年缓慢行，尽量使用英文提示词。"
								class="w-full h-36 bg-gray-900/50 border border-gray-600/50 rounded-xl p-4 text-white placeholder-gray-400 resize-none focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-200"></textarea>
						</div>
					</div>

					<!-- 视频设置区域 -->
					<div
						class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-6 border border-gray-700/50 shadow-xl">

						<!-- 分辨率选择 -->
						<div class="mb-6">
							<div class="flex items-center justify-between mb-4">
								<div class="flex items-center">
									<div
										class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
										<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
											<path fill-rule="evenodd"
												d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
												clip-rule="evenodd" />
										</svg>
									</div>
									<h2 class="text-xl font-semibold text-white">分辨率</h2>
								</div>
								<span class="text-sm text-blue-400">可选</span>
							</div>
							<div class="text-sm text-gray-400 mb-3">分辨率越高生成时间越长</div>
							<div class="grid grid-cols-3 gap-3">
								<button v-for="item in resolutions" :key="item.value"
									@click="formData.image_size = item.value" :class="[
										'py-3 px-4 rounded-xl font-medium transition-all duration-200',
										formData.image_size === item.value
											? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg border-2 border-blue-400'
											: 'bg-gray-800/50 text-gray-300 border border-gray-600/50 hover:border-purple-500/50'
									]">
									{{ item.label }}
								</button>
							</div>
							<div v-if="formData.image_size == '2K' || formData.image_size == '4K'"
								class="mt-3 flex items-center text-xs text-yellow-500/80">
								<svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
										clip-rule="evenodd" />
								</svg>
								<span>当前生成预计耗时 3-5 分钟</span>
							</div>
						</div>

						<!-- 生成尺寸选择 -->
						<div class="mb-6">
							<div class="flex items-center justify-between mb-4">
								<div class="flex items-center">
									<div
										class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
										<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
											<path fill-rule="evenodd"
												d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"
												clip-rule="evenodd" />
										</svg>
									</div>
									<h2 class="text-xl font-semibold text-white">生成尺寸</h2>
								</div>
								<span class="text-sm text-blue-400">可选</span>
							</div>
							<div class="text-sm text-gray-400 mb-3">选择图片尺寸</div>
							<div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
								<button v-for="item in aspect" :key="item.value" @click="selectAspectRatio(item.value)"
									:class="[
										'py-4 px-4 rounded-xl font-medium transition-all duration-200 flex flex-col items-center justify-center',
										formData.aspect_ratio === item.value && !showCustomRatio
											? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg border-2 border-blue-400'
											: 'bg-gray-800/50 text-gray-300 border border-gray-600/50 hover:border-purple-500/50'
									]">
									<div class="text-lg font-semibold mb-1">{{ item.label }}</div>
									<div class="text-xs opacity-80">{{ item.desc }}</div>
								</button>

								<!-- 自定义尺寸按钮 -->
								<button @click="toggleCustomRatio" :class="[
									'py-4 px-4 rounded-xl font-medium transition-all duration-200 flex flex-col items-center justify-center',
									showCustomRatio
										? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-lg border-2 border-blue-400'
										: 'bg-gray-800/50 text-gray-300 border border-gray-600/50 hover:border-purple-500/50'
								]">
									<div class="text-lg font-semibold mb-1">自定义</div>
									<div class="text-xs opacity-80">{{ showCustomRatio ? (customWidth + ':' +
										customHeight) : '点击输入' }}</div>
								</button>
							</div>

							<!-- 自定义尺寸输入区域 -->
							<div v-if="showCustomRatio"
								class="mt-3 bg-gradient-to-r from-purple-600/20 to-blue-600/20 border-2 border-purple-500 rounded-xl p-4 transition-all duration-200">
								<div class="flex items-center justify-center gap-3">
									<div class="flex flex-col items-center">
										<label class="text-xs text-gray-400 mb-1">宽度</label>
										<input v-model.number="customWidth" type="number" min="1" max="99"
											placeholder="8"
											class="w-20 h-11 bg-gray-900/50 border-2 border-purple-500/50 rounded-lg text-center text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
											@input="updateCustomRatio" />
									</div>
									<span class="text-2xl font-bold text-purple-400 mt-5">:</span>
									<div class="flex flex-col items-center">
										<label class="text-xs text-gray-400 mb-1">高度</label>
										<input v-model.number="customHeight" type="number" min="1" max="99"
											placeholder="3"
											class="w-20 h-11 bg-gray-900/50 border-2 border-purple-500/50 rounded-lg text-center text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
											@input="updateCustomRatio" />
									</div>
								</div>
							</div>
						</div>

						<!-- 生成按钮 -->
						<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
							<button @click="handleSubmit"
								class="flex-1 bg-gradient-to-r from-purple-600 via-blue-600 to-indigo-600 hover:from-purple-700 hover:via-blue-700 hover:to-indigo-700 py-4 px-6 rounded-xl font-semibold text-lg transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:scale-[1.02] relative overflow-hidden group">
								<div
									class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/10 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700">
								</div>

								<span class="relative z-10">🚀 立即创作 {{ model.point }}{{ config.alias_name }}</span>
							</button>

						</div>
					</div>
				</div>

				<!-- 右侧模特选择区域 -->
				<div class="xl:col-span-1 space-y-6">


					<!-- 使用说明 -->
					<div
						class="bg-gradient-to-br from-gray-800/80 to-gray-900/80 backdrop-blur-sm rounded-xl p-6 border border-gray-700/50 shadow-xl">
						<div class="flex items-center mb-4">
							<div
								class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg mr-3 flex items-center justify-center shadow-lg">
								<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
										clip-rule="evenodd" />
								</svg>
							</div>
							<h3 class="text-lg font-semibold text-white">使用说明</h3>
						</div>

						<div class="space-y-3 text-sm text-gray-300">
							<div class="flex items-start">
								<span class="text-purple-400 mr-2">•</span>
								<span>上传参考图片作为参考生成效果更加</span>
							</div>
							<div class="flex items-start">
								<span class="text-purple-400 mr-2">•</span>
								<span>尽量使用英文提示词</span>
							</div>
							<div class="flex items-start">
								<span class="text-purple-400 mr-2">•</span>
								<span>生成大约10-30s</span>
							</div>
							<div class="flex items-start">
								<span class="text-purple-400 mr-2">•</span>
								<span>描述越详细生成效果越好</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 生成中弹窗 -->
		<div v-if="create_loading"
			class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
			@click.self="closePopup">
			<div class="w-full max-w-[600px] mx-auto rounded-2xl overflow-hidden animate-fade-in"
				style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px);">

				<!-- 弹窗头部 -->
				<div class="px-6 pt-6 pb-4 border-b" style="border-color: rgba(59, 130, 246, 0.1);">
					<div class="flex items-center justify-center mb-2">
						<div class="flex items-center gap-2">
							<div class="text-2xl font-bold" style="color: #1e293b;">
								{{ create_result && create_result.status != 0 ? '任务完成' : 'AI 生成中' }}
							</div>
						</div>
					</div>
				</div>

				<!-- 弹窗内容 -->
				<div class="pt-3 pb-3 px-6">
					<!-- 生成中状态 -->
					<div v-if="!create_result || create_result.status == 0" class="text-center">
						<div class="mb-6">
							<!-- 行星运行系统 -->
							<div class="planet-system">
								<!-- 中心太阳 -->
								<div class="planet-sun">
									<div class="sun-inner">
										<svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
											<path fill-rule="evenodd"
												d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
												clip-rule="evenodd" />
										</svg>
									</div>
								</div>

								<!-- 第一轨道 -->
								<div class="orbit orbit-1">
									<div class="planet planet-1"></div>
								</div>

								<!-- 第二轨道 -->
								<div class="orbit orbit-2">
									<div class="planet planet-2"></div>
								</div>

								<!-- 第三轨道 -->
								<div class="orbit orbit-3">
									<div class="planet planet-3"></div>
								</div>
							</div>

							<div class="text-sm mt-6" style="color: #64748b; line-height: 1.6;">
								预计需要 30-60 秒，请耐心等待<br />您也可以稍后到作品列表查看
							</div>
						</div>
					</div>

					<!-- 生成完成状态 -->
					<div v-else class="text-center">
						<!-- 生成成功 -->
						<div v-if="create_result.status == 1">
							<div class="mb-4 rounded-2xl overflow-hidden cursor-pointer"
								style="border: 2px solid rgba(59, 130, 246, 0.2); box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);"
								@click="previewImage(create_result.images)">
								<img :src="create_result.images" class="w-full max-h-[500px] object-contain"
									style="background: #f8fafc;" />
							</div>
							<div class="flex items-center justify-center gap-2 mb-2">
								<svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
										clip-rule="evenodd" />
								</svg>
								<div class="text-lg font-bold text-green-500">生成成功</div>
							</div>
							<div class="text-xs" style="color: #94a3b8;">点击图片可以预览大图</div>
						</div>

						<!-- 生成失败 -->
						<div v-else-if="create_result.status == 2">
							<div class="mb-6 flex items-center justify-center"
								style="width: 260px; height: 260px; margin: 0 auto; background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(220, 38, 38, 0.05)); border: 2px dashed rgba(239, 68, 68, 0.3); border-radius: 32px;">
								<svg class="w-18 h-18 text-red-500" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
										clip-rule="evenodd" />
								</svg>
							</div>
							<div class="flex items-center justify-center gap-2 mb-4">
								<svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd"
										d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
										clip-rule="evenodd" />
								</svg>
								<div class="text-lg font-bold text-red-500">生成失败</div>
							</div>
							<div class="px-5 py-4 rounded-xl text-left"
								style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.1);">
								<div class="text-xs font-semibold mb-1 text-red-500">失败原因：</div>
								<div class="text-sm" style="color: #64748b; line-height: 1.6;">
									{{ create_result.msg || '生成过程中出现错误，请重试' }}
								</div>
							</div>
						</div>
					</div>

					<!-- 底部按钮 -->
					<div class="flex gap-3 mt-6">
						<button @click="closePopup"
							class="flex-1 py-3.5 rounded-xl font-semibold text-sm transition-all active:scale-95"
							style="background: transparent; border: 2px solid rgba(59, 130, 246, 0.3); color: #3b82f6; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);">
							关闭
						</button>
						<button v-if="create_result && create_result.status == 1" @click="downloadImage"
							class="flex-1 py-3.5 rounded-xl font-semibold text-sm text-white transition-all active:scale-95 flex items-center justify-center gap-2"
							style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
							<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
							</svg>
							下载图片
						</button>
						<button @click="goToList"
							class="flex-1 py-3.5 rounded-xl font-semibold text-sm text-white transition-all active:scale-95"
							style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
							我的作品
						</button>
					</div>
				</div>
			</div>
		</div>

	</div>
</template>

<script lang="ts" setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UploadImg from '@/addon/ai_image/pages/components/upload-img.vue'
import { getConfig, sendText, createImage, getModelInfo, getImageInfo } from '@/addon/ai_image/api/aiimage'

const route = useRoute()
const router = useRouter()

const config = ref()
const getConfigFn = () => {
	getConfig().then(res => {
		config.value = res.data
	})
}

onMounted(() => {
	getConfigFn()
})

const aiLoading = ref(false)
const aspect = ref([
	{ label: '1:1', value: '1:1', desc: '头像' },
	{ label: '3:2', value: '3:2', desc: '文章配图' },
	{ label: '4:3', value: '4:3', desc: '公众号配图' },
	{ label: '9:16', value: '9:16', desc: '海报图' },
	{ label: '16:9', value: '16:9', desc: '电脑壁纸' },
])
const resolutions = ref([
	{ label: '1K', value: '1K' },
	{ label: '2K', value: '2K' },
	{ label: '4K', value: '4K' },
])

const showCustomRatio = ref(false)
const customWidth = ref(8)
const customHeight = ref(3)

const toggleCustomRatio = () => {
	showCustomRatio.value = !showCustomRatio.value
	if (showCustomRatio.value) {
		// 启用自定义模式，使用当前自定义值
		if (customWidth.value && customHeight.value) {
			formData.value.aspect_ratio = `${customWidth.value}:${customHeight.value}`
		}
	} else {
		// 取消自定义模式，恢复到第一个预设值
		formData.value.aspect_ratio = '9:16'
	}
}

const selectAspectRatio = (ratio: string) => {
	showCustomRatio.value = false
	formData.value.aspect_ratio = ratio
}

// Update custom ratio when inputs change
const updateCustomRatio = () => {
	if (customWidth.value && customHeight.value) {
		formData.value.aspect_ratio = `${customWidth.value}:${customHeight.value}`
	}
}

// Watch custom ratio inputs
watch([customWidth, customHeight], () => {
	if (showCustomRatio.value && customWidth.value && customHeight.value) {
		formData.value.aspect_ratio = `${customWidth.value}:${customHeight.value}`
	}
})
const formData = ref({
	prompt: '',
	aspect_ratio: '',
	image_size: '1K',
	image_urls: [] as string[],
	model_id: '',
})

const sendTextFn = () => {
	if (formData.value.prompt == '') {
		return ElMessage.error('请输入提示词')
	}
	aiLoading.value = true
	sendText({
		prompt: formData.value.prompt,
		model_id: model.value.id
	}).then((res: any) => {
		formData.value.prompt = res.data
		aiLoading.value = false
	})
}

const create_id = ref('')
const create_loading = ref(false)
const model = ref()
const getModelInfoFn = () => {
	const agentId = route.query.id
	if (agentId) {
		getModelInfo(agentId).then((res: any) => {
			model.value = res.data
			// 如果 agent 有默认提示词，可以预填充
			if (res.data.prompt) {
				formData.value.prompt = res.data.prompt
			}
		}).catch(err => {
			console.error('获取 智能体 信息失败:', err)
		})
	} else {
		ElMessage.error('获取 智能体 信息失败')
		router.back()
	}
}
getModelInfoFn()
const handleSubmit = () => {
	if (model.value.is_prompt == 1) {
		if (formData.value.prompt == '') {
			ElMessage.error('请输入提示词')
			return
		}
	}
	if (model.value.is_prompt == 0 && model.value.is_upload_image == 1) {
		if (formData.value.image_urls.length == 0) {
			ElMessage.error('请上传图片后再生成')
			return
		}
	}
	formData.value.model_id = model.value.id
	create_loading.value = true
	createImage(formData.value).then((res: any) => {
		create_id.value = res.data.id
		getImageInfoFn()
	})
}
const create_result = ref<any>()
const pollCount = ref(0) // 轮询次数计数器
const pollIntervals = [10000, 15000, 20000] // 轮询间隔：10秒、15秒、20秒

const getImageInfoFn = () => {
	getImageInfo(create_id.value).then((res: any) => {
		create_result.value = res.data
		if (create_result.value.status != 0) {
			// 生成完成（成功或失败）
			create_loading.value = false
			pollCount.value = 0 // 重置计数器
			if (create_result.value.status == 1) {
				ElMessage.success('生成成功')
			} else {
				ElMessage.error('生成失败，请重试')
			}
		} else {
			// 检查是否已达到最大轮询次数
			if (pollCount.value >= 5) {
				create_loading.value = false
				pollCount.value = 0 // 重置计数器
				ElMessage({
					message: '图片生成中，请稍后前往创作历史查看结果',
					type: 'warning',
					duration: 5000
				})
				return
			}

			// 继续轮询，使用对应的间隔时间
			const interval = pollIntervals[pollCount.value]
			pollCount.value++
			setTimeout(() => {
				getImageInfoFn()
			}, interval)
		}
	}).catch(() => {
		// 轮询出错
		if (pollCount.value >= 3) {
			create_loading.value = false
			pollCount.value = 0
			ElMessage({
				message: '查询超时，请稍后前往创作历史查看结果',
				type: 'warning',
				duration: 5000
			})
			return
		}

		// 继续尝试
		const interval = pollIntervals[pollCount.value]
		pollCount.value++
		setTimeout(() => {
			getImageInfoFn()
		}, interval)
	})
}

// 关闭弹窗并清除结果
const closePopup = () => {
	create_loading.value = false
	create_result.value = null
}

// 预览图片
const previewImage = (imageUrl: string) => {
	// 在新窗口打开图片
	window.open(imageUrl, '_blank')
}

// 跳转到作品列表
const goToList = () => {
	router.push('/ai_image/history/image')
}

// 下载图片
const downloadImage = async () => {
	if (!create_result.value || !create_result.value.images) {
		ElMessage.error('图片地址不存在')
		return
	}

	try {
		const imageUrl = create_result.value.images
		// 创建一个隐藏的 a 标签来触发下载
		const link = document.createElement('a')
		link.href = imageUrl
		link.download = `ai-image-${Date.now()}.png`
		link.target = '_blank'

		// 如果是跨域图片，需要先转换为 blob
		try {
			const response = await fetch(imageUrl)
			const blob = await response.blob()
			const blobUrl = window.URL.createObjectURL(blob)
			link.href = blobUrl
			document.body.appendChild(link)
			link.click()
			document.body.removeChild(link)
			window.URL.revokeObjectURL(blobUrl)
			ElMessage.success('下载成功')
		} catch (error) {
			// 如果跨域失败，直接打开新窗口
			window.open(imageUrl, '_blank')
			ElMessage.info('已在新窗口打开图片，请右键保存')
		}
	} catch (error) {
		ElMessage.error('下载失败')
		console.error('下载图片失败:', error)
	}
}


</script>
<style lang="scss" scoped>
/* 自定义尺寸输入框样式 */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
	-webkit-appearance: none;
	margin: 0;
}

input[type="number"] {
	-moz-appearance: textfield;
	appearance: textfield;
}

/* 响应式布局优化 */
@media (max-width: 1280px) {
	.xl\:grid-cols-3 {
		grid-template-columns: 1fr;
	}

	.xl\:col-span-2 {
		grid-column: span 1;
	}

	.xl\:col-span-1 {
		grid-column: span 1;
	}
}

/* 确保在小屏幕上有合适的间距 */
@media (max-width: 640px) {
	.px-6 {
		padding-left: 1rem;
		padding-right: 1rem;
	}

	/* 优化按钮在小屏幕上的显示 */
	button {
		min-height: 44px;
		/* 确保触摸友好的最小高度 */
	}

	/* 优化文本区域在小屏幕上的显示 */
	textarea {
		font-size: 16px;
		/* 防止iOS上的缩放 */
	}

	/* 小屏幕上的网格调整 */
	.grid-cols-4 {
		grid-template-columns: repeat(3, 1fr);
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

/* 优化选择按钮的交互效果 */
.grid button {
	transition: all 0.2s ease-in-out;
	position: relative;
}

.grid button:active {
	transform: scale(0.98);
}

.grid button::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: inherit;
	background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
	opacity: 0;
	transition: opacity 0.3s ease;
}

.grid button:hover::before {
	opacity: 1;
}

/* 模特网格悬停效果 */
.aspect-square {
	transition: all 0.2s ease;
}

.aspect-square:hover {
	transform: scale(1.05);
	box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
}

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

/* 图标旋转动画 */
.w-8.h-8 {
	transition: transform 0.3s ease;
}

.bg-gradient-to-br:hover .w-8.h-8 {
	transform: rotate(5deg) scale(1.1);
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

/* 行星运行系统 */
.planet-system {
	position: relative;
	width: 280px;
	height: 280px;
	margin: 0 auto;
}

/* 中心太阳 */
.planet-sun {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: 80px;
	height: 80px;
	border-radius: 50%;
	background: linear-gradient(135deg, #3b82f6, #8b5cf6);
	box-shadow: 0 0 30px rgba(59, 130, 246, 0.6),
		0 0 60px rgba(59, 130, 246, 0.4),
		inset 0 0 20px rgba(255, 255, 255, 0.3);
	animation: sun-pulse 2s ease-in-out infinite;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
}

.sun-inner {
	display: flex;
	align-items: center;
	justify-content: center;
}

/* 太阳脉动动画 */
@keyframes sun-pulse {

	0%,
	100% {
		box-shadow: 0 0 30px rgba(59, 130, 246, 0.6),
			0 0 60px rgba(59, 130, 246, 0.4),
			inset 0 0 20px rgba(255, 255, 255, 0.3);
		transform: translate(-50%, -50%) scale(1);
	}

	50% {
		box-shadow: 0 0 40px rgba(59, 130, 246, 0.8),
			0 0 80px rgba(59, 130, 246, 0.5),
			inset 0 0 25px rgba(255, 255, 255, 0.4);
		transform: translate(-50%, -50%) scale(1.05);
	}
}

/* 轨道 */
.orbit {
	position: absolute;
	top: 50%;
	left: 50%;
	border: 1px solid rgba(59, 130, 246, 0.2);
	border-radius: 50%;
	transform: translate(-50%, -50%);
}

.orbit-1 {
	width: 140px;
	height: 140px;
	animation: rotate-orbit 8s linear infinite;
}

.orbit-2 {
	width: 200px;
	height: 200px;
	animation: rotate-orbit 12s linear infinite;
}

.orbit-3 {
	width: 260px;
	height: 260px;
	animation: rotate-orbit 16s linear infinite;
}

/* 轨道旋转动画 */
@keyframes rotate-orbit {
	from {
		transform: translate(-50%, -50%) rotate(0deg);
	}

	to {
		transform: translate(-50%, -50%) rotate(360deg);
	}
}

/* 行星 */
.planet {
	position: absolute;
	border-radius: 50%;
	box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
}

.planet-1 {
	width: 12px;
	height: 12px;
	background: linear-gradient(135deg, #3b82f6, #2563eb);
	top: 0;
	left: 50%;
	transform: translateX(-50%);
	animation: planet-glow-1 2s ease-in-out infinite;
}

.planet-2 {
	width: 16px;
	height: 16px;
	background: linear-gradient(135deg, #8b5cf6, #7c3aed);
	top: 0;
	left: 50%;
	transform: translateX(-50%);
	animation: planet-glow-2 2.5s ease-in-out infinite;
}

.planet-3 {
	width: 14px;
	height: 14px;
	background: linear-gradient(135deg, #06b6d4, #0891b2);
	top: 0;
	left: 50%;
	transform: translateX(-50%);
	animation: planet-glow-3 3s ease-in-out infinite;
}

/* 行星发光动画 */
@keyframes planet-glow-1 {

	0%,
	100% {
		box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
	}

	50% {
		box-shadow: 0 0 20px rgba(59, 130, 246, 0.8);
	}
}

@keyframes planet-glow-2 {

	0%,
	100% {
		box-shadow: 0 0 12px rgba(139, 92, 246, 0.5);
	}

	50% {
		box-shadow: 0 0 24px rgba(139, 92, 246, 0.8);
	}
}

@keyframes planet-glow-3 {

	0%,
	100% {
		box-shadow: 0 0 11px rgba(6, 182, 212, 0.5);
	}

	50% {
		box-shadow: 0 0 22px rgba(6, 182, 212, 0.8);
	}
}

/* 点击缩放效果 */
.active\:scale-98:active {
	transform: scale(0.98);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.active\:scale-95:active {
	transform: scale(0.95);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 弹窗淡入动画 */
@keyframes fade-in {
	from {
		transform: scale(0.95);
		opacity: 0;
	}

	to {
		transform: scale(1);
		opacity: 1;
	}
}

.animate-fade-in {
	animation: fade-in 0.3s ease-out;
}
</style>
