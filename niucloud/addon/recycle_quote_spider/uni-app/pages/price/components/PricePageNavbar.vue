<template>
	<view class="custom-navbar" :class="{ compact }" :style="navbarStyle">
		<view class="navbar-content" :style="contentStyle">
			<view class="navbar-left" :style="sideStyle" @click="emit('back')">
				<u-icon name="arrow-left" color="#ffffff" size="42rpx"></u-icon>
			</view>
			<view class="navbar-center" :style="centerStyle">
				<text class="navbar-title">{{ title }}</text>
				<text v-if="date && date !== '--'" class="navbar-date">{{ date }}</text>
			</view>
			<view class="navbar-capsule-space" :style="sideStyle"></view>
		</view>
	</view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
	title: string
	date?: string
	compact?: boolean
	navbarStyle?: string
	contentStyle?: string
	sideStyle?: string
	centerStyle?: string
}>(), {
	date: '',
	compact: false,
	navbarStyle: '',
	contentStyle: '',
	sideStyle: '',
	centerStyle: ''
})

const emit = defineEmits<{
	(event: 'back'): void
}>()
</script>

<style lang="scss" scoped>
.custom-navbar {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 999;
	backdrop-filter: blur(8rpx);
	background: var(--button-bg);
	border-bottom: 1rpx solid var(--line);
	color: var(--button-text);

	.navbar-content {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-sizing: content-box;
	}

	.navbar-left {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;

		:deep(.u-icon) {
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}

	.navbar-center {
		position: absolute;
		left: 50%;
		transform: translateX(-50%);
		max-width: 66%;
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: center;
		gap: 12rpx;
		padding: 0 10rpx;
		box-sizing: border-box;
		pointer-events: none;
	}

	.navbar-title {
		flex: 0 1 auto;
		min-width: 0;
		max-width: 360rpx;
		font-size: 30rpx;
		line-height: 44rpx;
		font-weight: 700;
		letter-spacing: 0;
		text-align: center;
		color: var(--button-text);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.navbar-date {
		flex: 0 0 auto;
		font-size: 22rpx;
		line-height: 44rpx;
		color: var(--button-text);
		opacity: 0.72;
		white-space: nowrap;
	}

	.navbar-capsule-space {
		flex-shrink: 0;
	}
}

.custom-navbar.compact {
	background: var(--button-bg);
}
</style>
