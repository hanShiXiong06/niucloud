<template>
	<view class="image-swiper-container" :style="{ width: width }">
		<!-- 多张图片使用 swiper -->
		<swiper 
			v-if="imageList.length > 1" 
			class="image-swiper" 
			:style="{ width: width, height: swiperHeight }"
			:indicator-dots="true" 
			indicator-color="rgba(255,255,255,.4)" 
			indicator-active-color="rgb(255,255,255)"
			:autoplay="false"
			:circular="true"
			:interval="3000"
			@change="handleSwiperChange"
		>
			<swiper-item v-for="(image, index) in imageList" :key="index" class="swiper-item">
				<image 
					:src="getImageSrc(image)" 
					:mode="'widthFix'"
					class="swiper-image"
					:data-index="index"
					@error="handleImageError"
					@load="handleImageLoad"
					:lazy-load="true"
				></image>
			</swiper-item>
		</swiper>
		
		<!-- 单张图片直接显示 -->
		<image 
			v-else-if="imageList.length === 1" 
			:src="getImageSrc(imageList[0])" 
			:mode="'widthFix'"
			class="single-image"
			@error="handleImageError"
			@load="handleImageLoad"
			:lazy-load="true"
		></image>
		
		<!-- 无图片时的占位 -->
		<view v-else class="placeholder-image" :style="{ width: width, minHeight: height }">
			<text class="placeholder-text">暂无图片</text>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
// @ts-ignore
import { img } from '@/utils/common';

const props = defineProps({
	logo: {
		type: String,
		default: ''
	},
	width: {
		type: String,
		default: '120rpx'
	},
	height: {
		type: String,
		default: '120rpx'
	}
});

// 图片加载错误处理
const imageError = ref(false);
const swiperHeight = ref<string>('200rpx'); // swiper 高度，初始值
const currentIndex = ref<number>(0); // 当前显示的图片索引
const imageHeights = ref<Map<number, number>>(new Map()); // 存储每张图片的高度（rpx）

// 处理 logo 字段，可能是单张或多张（逗号分隔）
const imageList = computed(() => {
	if (!props.logo) return [];
	
	// 如果包含逗号，说明是多张图片
	if (props.logo.includes(',')) {
		return props.logo.split(',').map(item => item.trim()).filter(item => item);
	}
	
	// 单张图片
	return [props.logo];
});

// 获取图片地址
const getImageSrc = (imagePath: string) => {
	if (!imagePath) return '';
	return img(imagePath);
};

// 图片加载错误处理
const handleImageError = (e: any) => {
	console.log('图片加载失败:', e);
	imageError.value = true;
};

// 计算容器宽度（rpx）
const getContainerWidthRpx = () => {
	// 瀑布流布局，每列宽度约为 (750 - 40 - 12) / 2 = 349rpx
	// 如果是双列布局或其他布局，可以根据实际情况调整
	return 349;
};

// 图片加载完成，存储图片高度
const handleImageLoad = (e: any) => {
	const { width, height } = e.detail;
	const index = e.currentTarget?.dataset?.index ?? 0;
	console.log(`图片 ${index} 加载完成，尺寸:`, width, height);
	
	// 计算图片的实际rpx高度
	if (width > 0 && height > 0) {
		const containerWidthRpx = getContainerWidthRpx();
		const aspectRatio = height / width;
		const calculatedHeightRpx = containerWidthRpx * aspectRatio;
		
		// 存储每张图片的高度
		imageHeights.value.set(index, calculatedHeightRpx);
		
		// 如果是当前显示的图片，立即更新swiper高度
		if (index === currentIndex.value) {
			updateSwiperHeight(calculatedHeightRpx);
		}
		// 如果是第一张图片且还没有设置高度，也设置一下
		else if (index === 0 && swiperHeight.value === '200rpx') {
			updateSwiperHeight(calculatedHeightRpx);
		}
	}
};

// 更新swiper高度
const updateSwiperHeight = (heightRpx: number) => {
	swiperHeight.value = `${heightRpx}rpx`;
};

// swiper切换事件
const handleSwiperChange = (e: any) => {
	const newIndex = e.detail.current;
	currentIndex.value = newIndex;
	
	// 获取当前图片的高度
	const currentImageHeight = imageHeights.value.get(newIndex);
	
	if (currentImageHeight) {
		// 如果已经加载过，直接使用存储的高度
		updateSwiperHeight(currentImageHeight);
	} else {
		// 如果还没加载，等待图片加载完成
		// handleImageLoad 会在加载完成后自动更新
		console.log(`图片 ${newIndex} 还未加载，等待加载完成...`);
	}
};
</script>

<style lang="scss" scoped>
.image-swiper-container {
	position: relative;
	flex-shrink: 0;
	display: flex;
	align-items: flex-start;
	justify-content: center;
	width: 100%;
}

.image-swiper {
	width: 100%;
	min-height: 200rpx; /* 最小高度，避免初始不显示 */
	transition: height 0.3s ease; /* 高度变化时的平滑过渡 */
}

.swiper-item {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	width: 100%;
	height: 100%;
	overflow: hidden;
}

.swiper-image,
.single-image {
	width: 100%;
	height: auto;
	border-radius: 16rpx 16rpx 0 0;
	overflow: hidden;
	background: rgba(51, 65, 85, 0.6);
	border: 1rpx solid rgba(34, 211, 238, 0.15);
	box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.1);
	transition: all 0.3s ease;
	flex-shrink: 0;
	display: block;
}

/* swiper 指示器样式优化 */
:deep(.uni-swiper-dot) {
	width: 12rpx;
	height: 12rpx;
}

/* 占位图片样式 */
.placeholder-image {
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 16rpx 16rpx 0 0;
	background: rgba(51, 65, 85, 0.6);
	border: 1rpx solid rgba(34, 211, 238, 0.15);
}

.placeholder-text {
	font-size: 24rpx;
	color: rgba(255, 255, 255, 0.5);
}
</style>
