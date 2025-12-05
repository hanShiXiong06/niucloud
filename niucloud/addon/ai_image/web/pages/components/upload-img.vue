<template>
	<div class="w-full">
		<!-- 上传区域 - 仅在未达到上传限制时显示 -->
		<el-upload v-if="!isLimitReached" v-bind="upload" class="w-full upload-dragger" drag :show-file-list="false">
			<div class="flex flex-col items-center justify-center text-center text-gray-400 py-8 px-6">
				<!-- 上传中加载状态 -->
				<div v-if="isUploading" class="flex flex-col items-center">
					<el-icon class="text-6xl text-indigo-500 mb-4 animate-spin">
						<Loading />
					</el-icon>
					<div class="text-lg font-medium text-gray-200 mb-2">上传中...</div>
					<div class="text-sm text-gray-400">请稍候</div>
				</div>
				<!-- 默认上传状态 -->
				<div v-else>
					<el-icon class="text-6xl text-indigo-500 mb-4">
						<UploadFilled />
					</el-icon>
					<div class="text-lg font-medium text-gray-200 mb-2">上传参考图片</div>
					<div class="text-sm text-gray-400 mb-2">拖拽图片到此处或点击上传</div>
					<div class="text-xs text-gray-500 mb-1">支持 JPG、PNG 格式,建议不超过 5MB</div>
					<div class="text-xs text-gray-500">{{ uploadLimitText }}</div>
				</div>
			</div>
		</el-upload>

		<!-- 已上传图片预览 - 网格布局 -->
		<div v-if="value.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
			<div v-for="(imageUrl, index) in value" :key="index" class="relative group">
				<el-image :src="getImageUrl(imageUrl)" fit="cover"
					class="w-full h-30 rounded-lg cursor-pointer transition-all duration-300 hover:scale-105 hover:shadow-lg"
					@click="previewImage(imageUrl)">
					<template #error>
						<div class="flex flex-col items-center justify-center h-full text-gray-400 text-xs">
							<el-icon class="text-2xl mb-1">
								<Picture />
							</el-icon>
							<span>加载失败</span>
						</div>
					</template>
				</el-image>
				<div class="absolute -top-2 -right-2">
					<el-button type="danger" size="small" :icon="Delete" circle class="!w-6 !h-6 !p-0 shadow-md"
						@click="removeImage(index)" />
				</div>
				<!-- 图片序号 -->
				<div class="absolute top-2 left-2 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">
					{{ index + 1 }}/{{ max }}
				</div>
			</div>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { img } from '@/utils/common'
import { UploadFile, ElMessage } from 'element-plus'
import { UploadFilled, Picture, Delete, Loading } from '@element-plus/icons-vue'

const prop = defineProps({
	modelValue: {
		type: Array as () => string[],
		default: () => []
	},
	api: {
		type: String,
		default: '/file/image'
	},
	accept: {
		type: String,
		default: '.doc,.docx,.xml,.txt,.pem,.zip,.rar,.7z,.crt,.key,.xls,.xlsx'
	},
	max: {
		type: Number,
		default: 9
	}
})

const emit = defineEmits(['update:modelValue'])

// 上传加载状态
const isUploading = ref(false)

const value = computed({
	get() {
		return prop.modelValue
	},
	set(value) {
		emit('update:modelValue', value)
	}
})

// 是否达到上传限制
const isLimitReached = computed(() => {
	return value.value.length >= prop.max
})

// 上传限制提示文本
const uploadLimitText = computed(() => {
	const current = value.value.length
	const total = prop.max
	if (current === 0) {
		return `最多可上传 ${total} 张图片`
	}
	return `已上传 ${current}/${total} 张图片`
})

const upload = computed(() => {
	const headers: Record<string, any> = {}
	const runtimeConfig = useRuntimeConfig()
	headers[runtimeConfig.public.VITE_REQUEST_HEADER_SITEID_KEY] = useCookie('siteId').value || runtimeConfig.public.VITE_SITE_ID
	headers[runtimeConfig.public.VITE_REQUEST_HEADER_CHANNEL_KEY] = 'pc'
	const baseURL = runtimeConfig.public.VITE_APP_BASE_URL || `${location.origin}/api/`
	return {
		action: `${baseURL}${prop.api}`,
		showFileList: false,
		accept: 'image/*',
		headers,
		beforeUpload: (file: File) => {
			// 检查是否达到上传限制
			if (value.value.length >= prop.max) {
				ElMessage.error(`最多只能上传 ${prop.max} 张图片!`)
				return false
			}

			const isImage = file.type.startsWith('image/')
			const isLt5M = file.size / 1024 / 1024 < 5

			if (!isImage) {
				ElMessage.error('只能上传图片文件!')
				return false
			}
			// 开始上传,显示加载状态
			isUploading.value = true
			return true
		},
		onSuccess: (response: any, uploadFile: UploadFile) => {
			// 上传完成,隐藏加载状态
			isUploading.value = false
			if (response.code != undefined && response.code != 1) {
				ElMessage({ message: response.msg, type: 'error' })
				return
			}
			// 添加到数组中
			value.value = [...value.value, response.data.url]
			ElMessage({ message: `图片上传成功 (${value.value.length}/${prop.max})`, type: 'success' })
		},
		onError: () => {
			// 上传失败,隐藏加载状态
			isUploading.value = false
			ElMessage.error('上传失败，请重试')
		}
	}
})

// 获取图片完整URL
const getImageUrl = (url: string) => {
	return img(url)
}

// 预览图片
const previewImage = (url: string) => {
	if (!url) return

	const imageUrl = getImageUrl(url)
	// 简单的图片预览,在新窗口打开
	window.open(imageUrl, '_blank')
}

// 删除图片
const removeImage = (index: number) => {
	value.value = value.value.filter((_, i) => i !== index)
	ElMessage.success('图片已删除')
}
</script>

<style lang="scss" scoped>
.upload-dragger {
	:deep(.el-upload) {
		width: 100%;

		.el-upload-dragger {
			width: 100%;
			height: 200px;
			background-color: rgb(30 41 59);
			/* bg-slate-800 */
			border: 2px dashed rgb(75 85 99);
			/* border-gray-600 */
			border-radius: 0.5rem;
			/* rounded-lg */
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.3s ease;

			&:hover {
				border-color: rgb(99 102 241);
				/* border-indigo-500 */
				background-color: rgb(51 65 85);
				/* bg-slate-700 */
			}

			&.is-dragover {
				border-color: rgb(99 102 241);
				/* border-indigo-500 */
				background-color: rgb(51 65 85);
				/* bg-slate-700 */
			}
		}
	}
}

// 响应式适配
@media (max-width: 768px) {
	.upload-dragger :deep(.el-upload-dragger) {
		height: 160px;
	}
}
</style>
