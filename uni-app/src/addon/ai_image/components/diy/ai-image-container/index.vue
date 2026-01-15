<template>
	<view 
		class="ai-image-container" 
		:style="{ height: containerHeight + 'px', minHeight: containerHeight > 0 ? containerHeight + 'px' : 'auto' }"
		v-if="containerHeight > 0 || !src">
		<ai-image-model 
			:component="component" 
			:index="index" 
			:pullDownRefreshCount="pullDownRefreshCount" 
		/>
	</view>
	<view v-else class="ai-image-container-loading">
		<text>加载中...</text>
	</view>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue';
// @ts-ignore
import { img } from '@/utils/common';
// @ts-ignore
import AiImageModel from '@/addon/ai_image/components/diy/ai-image-model/index.vue';

const props = defineProps({
	src: {
		type: String,
		required: true,
		default: ''
	},
	component: {
		type: Object,
		default: () => ({})
	},
	index: {
		type: Number,
		default: 0
	},
	pullDownRefreshCount: {
		type: Number,
		default: 0
	},
	// 容器宽度，用于计算图片高度（单位：rpx，默认100%）
	containerWidth: {
		type: Number,
		default: 0
	}
});

const containerHeight = ref(0);

// 获取图片信息并计算高度
const calculateImageHeight = () => {
	if (!props.src) {
		console.warn('图片路径为空');
		containerHeight.value = 0;
		return;
	}

	const imageSrc = img(props.src);
	
	uni.getImageInfo({
		src: imageSrc,
		success: (res) => {
			// 获取系统信息，用于计算rpx到px的转换
			const systemInfo = uni.getSystemInfoSync();
			const screenWidth = systemInfo.windowWidth;
			const rpxToPx = screenWidth / 750; // uni-app中750rpx = 屏幕宽度
			
			// 如果指定了容器宽度，使用指定宽度；否则使用屏幕宽度
			const containerWidthPx = props.containerWidth > 0 
				? props.containerWidth * rpxToPx 
				: screenWidth;
			
			// 根据图片宽高比计算容器高度
			const imageWidth = res.width;
			const imageHeight = res.height;
			const aspectRatio = imageHeight / imageWidth;
			
			// 计算图片在容器中的实际高度
			const calculatedHeight = containerWidthPx * aspectRatio;
			
			// 使用 nextTick 确保 DOM 更新后再设置高度
			nextTick(() => {
				containerHeight.value = calculatedHeight;
			});
			
			console.log('图片信息:', {
				src: imageSrc,
				原始尺寸: `${imageWidth}x${imageHeight}`,
				容器宽度: `${containerWidthPx}px`,
				计算高度: `${calculatedHeight}px`,
				宽高比: aspectRatio.toFixed(2)
			});
		},
		fail: (err) => {
			console.error('获取图片信息失败:', err);
			// 失败时设置一个默认高度
			containerHeight.value = 300;
		}
	});
};

// 监听src变化，重新计算高度
watch(
	() => props.src,
	(newSrc) => {
		if (newSrc) {
			containerHeight.value = 0; // 重置高度，显示加载状态
			calculateImageHeight();
		} else {
			containerHeight.value = 0;
		}
	},
	{ immediate: true }
);

// 监听容器宽度变化
watch(
	() => props.containerWidth,
	() => {
		if (props.src) {
			calculateImageHeight();
		}
	}
);

onMounted(() => {
	if (props.src) {
		calculateImageHeight();
	}
});
</script>

<style lang="scss" scoped>
.ai-image-container {
	width: 100%;
	overflow: hidden;
	position: relative;
}

.ai-image-container-loading {
	width: 100%;
	height: 200px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #999;
	font-size: 28rpx;
}
</style>
