<template>
	<view class="image-upload-grid">
		<view v-for="(item, index) in value" :key="index" class="image-item">
			<up-image class="rounded-[10rpx] overflow-hidden w-full h-full" :src="img(item || '')" mode="aspectFill"
				@click="previewImage(index)">
				<template #error>
					<up-icon name="plus" color="#999" size="50"></up-icon>
				</template>
			</up-image>
			<text
				class="nc-iconfont nc-icon-guanbiV6xx absolute top-0 right-[5rpx] text-[#fff] bg-[#888] rounded-bl-[16rpx] z-10"
				@click.stop="deleteImg(index)"></text>
			<view
				class="absolute left-0 top-1/2 -translate-y-1/2 bg-[#000]/40 text-white px-2 py-1 rounded-tr-[10rpx] rounded-br-[10rpx]"
				@click.stop="moveLeft(index)" v-show="index > 0">
				<up-icon name="arrow-left" color="#fff" size="24"></up-icon>
			</view>
			<view
				class="absolute right-0 top-1/2 -translate-y-1/2 bg-[#000]/40 text-white px-2 py-1 rounded-tl-[10rpx] rounded-bl-[10rpx]"
				@click.stop="moveRight(index)" v-show="index < value.length - 1">
				<up-icon name="arrow-right" color="#fff" size="24"></up-icon>
			</view>
		</view>

		<view v-show="value.length < maxCount" class="image-item upload-item flex justify-center items-center">
			<up-upload @afterRead="afterRead" :maxCount="maxCount" :multiple="prop.multiple">
				<view class="upload-placeholder">
					<view class="upload-content ml-9">
						<view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx]"></view>
						<view class="text-[24rpx] mt-[10rpx]">{{ value.length }}/{{ maxCount }}</view>
					</view>
				</view>
			</up-upload>
		</view>

		<!-- #ifdef MP-WEIXIN -->
		<!-- 小程序隐私协议 -->
		<wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
		<!-- #endif -->
	</view>
</template>
<script lang="ts" setup>
import { computed } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system'

const prop = defineProps({
	modelValue: {
		type: String || Array,
	},
	maxCount: {
		type: Number,
		default: 9
	},
	multiple: {
		type: Boolean,
		default: false
	}
})
const emit = defineEmits(['update:modelValue'])
const value = computed({
	get() {
		return prop.modelValue
	},
	set(value) {
		emit('update:modelValue', value)
	}
})
const maxCount = computed(() => {
	return prop.maxCount
})
const afterRead = (event: any) => {
	if (prop.multiple) {
		event.file.forEach(file => {
			upload({ file })
		})
	} else {
		upload(event)
	}
}

const upload = (event: any) => {
	if (value.value?.length >= maxCount.value) {
		uni.showToast({ title: `最多允许上传${maxCount.value}张图片`, icon: 'none' })
		return false
	}

	// 显示上传中提示
	uni.showLoading({ title: '上传中...', mask: true })

	uploadImage({
		filePath: event.file.url,
		name: 'file'
	}).then(res => {
		uni.hideLoading()
		if (value.value?.length < maxCount.value) {
			value.value.push(res.data.url)
			uni.showToast({ title: '上传成功', icon: 'success', duration: 1500 })
		}
	}).catch(() => {
		uni.hideLoading()
		uni.showToast({ title: '上传失败，请重试', icon: 'none', duration: 2000 })
	})
}

const deleteImg = (index: number) => {
	value.value.splice(index, 1)
}

const moveLeft = (index: number) => {
	const arr = value.value as string[]
	if (index <= 0) return
	const [moved] = arr.splice(index, 1)
	arr.splice(index - 1, 0, moved)
}
const moveRight = (index: number) => {
	const arr = value.value as string[]
	if (index >= arr.length - 1) return
	const [moved] = arr.splice(index, 1)
	arr.splice(index + 1, 0, moved)
}

// 预览图片
const previewImage = (index: number) => {
	if (!value.value) {
		return
	}

	// 处理value可能是字符串或数组的情况
	let imageList: string[] = []
	if (Array.isArray(value.value)) {
		imageList = value.value
	} else if (typeof value.value === 'string') {
		imageList = [value.value]
	} else {
		return
	}

	if (imageList.length === 0) {
		return
	}

	// 将所有图片URL转换为完整路径
	const urls = imageList.map((item: string) => img(item)).filter((url: string) => url)
	if (urls.length === 0) {
		return
	}

	const current = urls[index] || urls[0]

	uni.previewImage({
		urls: urls,
		current: current,
		indicator: 'number',
		loop: true
	})
}
</script>

<style lang="scss" scoped>
.image-upload-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 18rpx;
	width: 100%;
}

.image-item {
	position: relative;
	width: 100%;
	aspect-ratio: 1;
	border: 2rpx dashed #ebebec;
	border-radius: 10rpx;
	box-sizing: border-box;
	overflow: hidden;
	cursor: pointer;

	:deep(.up-image) {
		cursor: pointer;
		user-select: none;
	}
}

// 操作按钮样式优化
.image-item>text,
.image-item>view[class*="absolute"] {
	z-index: 20;
	pointer-events: auto;
}

.upload-item {
	display: flex;
	align-items: center;
	justify-content: center;
}

.upload-placeholder {
	width: 100%;
	height: 100%;

	border-radius: 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #888;
	text-align: center;
}

.upload-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

}

// 响应式适配
@media (max-width: 750rpx) {
	.image-upload-grid {
		gap: 12rpx;
	}
}

@media (max-width: 600rpx) {
	.image-upload-grid {
		gap: 8rpx;
	}
}
</style>