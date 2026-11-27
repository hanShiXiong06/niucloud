<template>
	<view class="price-redirect-page">
		<view class="status-card">
			<text class="status-title">{{ title }}</text>
			<text class="status-desc">{{ desc }}</text>
			<view v-if="targetUrl" class="status-btn" @click="openTarget">继续查看</view>
			<view v-else class="status-btn" @click="goBack">返回</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

const title = ref('报价插件未安装')
const desc = ref('请先安装并启用对应的报价插件后再查看报价详情')
const targetUrl = ref('')

function buildQuery(options: Record<string, any>) {
	return Object.keys(options)
		.filter(key => options[key] !== undefined && options[key] !== null && options[key] !== '')
		.map(key => `${encodeURIComponent(key)}=${encodeURIComponent(String(options[key]))}`)
		.join('&')
}

function openTarget() {
	if (!targetUrl.value) return
	uni.redirectTo({ url: targetUrl.value })
}

function goBack() {
	uni.navigateBack()
}

onLoad((options: Record<string, any> = {}) => {
	const source = String(options.source || '')
	const query = buildQuery(options)

	if (source === 'v2') {
		targetUrl.value = `/addon/recycle_daheng_quote/pages/price/show_price${query ? `?${query}` : ''}`
		title.value = '正在打开报价单'
		desc.value = '如果无法打开，请确认已安装DH速收报价插件'
		openTarget()
		return
	}

	if (source === 'spider') {
		targetUrl.value = `/addon/recycle_quote_spider/pages/price/show_price${query ? `?${query}` : ''}`
		title.value = '正在打开报价单'
		desc.value = '如果无法打开，请确认已安装回收报价增强插件'
		openTarget()
	}
})
</script>

<style lang="scss" scoped>
.price-redirect-page {
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 40rpx;
	background: #f6f7fb;
}

.status-card {
	width: 100%;
	padding: 48rpx 36rpx;
	border-radius: 16rpx;
	background: #ffffff;
	box-shadow: 0 16rpx 48rpx rgba(15, 23, 42, 0.08);
	text-align: center;
}

.status-title {
	display: block;
	font-size: 34rpx;
	font-weight: 600;
	color: #111827;
}

.status-desc {
	display: block;
	margin-top: 18rpx;
	font-size: 26rpx;
	line-height: 1.6;
	color: #6b7280;
}

.status-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	margin-top: 36rpx;
	min-width: 220rpx;
	height: 72rpx;
	border-radius: 12rpx;
	background: #111827;
	color: #ffffff;
	font-size: 28rpx;
}
</style>
