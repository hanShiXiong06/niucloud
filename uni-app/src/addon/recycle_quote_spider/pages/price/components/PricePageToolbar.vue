<template>
	<view class="tool-card">
		<view class="tool-head">
			<view class="data-meta">
				<view v-if="showHotBadge" class="spider-hot-badge" :style="hotBadgeBoxStyle">
					<image v-if="hotBadgeImage" class="spider-hot-badge__image" :src="img(hotBadgeImage)" mode="aspectFit"></image>
					<text v-else class="spider-hot-badge__text" :style="hotBadgeTextStyle">热门</text>
				</view>
				<text>共 {{ modelCount }} 个型号</text>
				<text class="dot">·</text>
				<text>{{ rowCount }} 条价格</text>
			</view>
			<view class="tool-actions">
				<view class="tool-action" :class="{ active: selectedModels.length > 0 }" @click="emit('filter')">
					型号筛选{{ selectedModels.length ? `(${selectedModels.length})` : '' }}
				</view>
				<view class="tool-action" @click="emit('type')">切换报价单</view>
				<view class="tool-action primary" @click="emit('refresh')">刷新</view>
			</view>
		</view>
		<view class="search-box">
			<text class="iconfont iconsousuo"></text>
			<input :value="keyword" class="search-input" placeholder="搜索型号 / 容量" placeholder-class="search-placeholder" @input="updateKeyword" />
		</view>
		<scroll-view v-if="selectedModels.length > 0" scroll-x class="selected-model-scroll">
			<view class="selected-model-list">
				<view v-for="model in selectedModels" :key="model" class="selected-model-tag" @click="emit('remove-model', model)">
					<text>{{ model }}</text>
					<u-icon name="close" size="20rpx" color="var(--brand)"></u-icon>
				</view>
				<view class="selected-model-clear" @click="emit('clear-models')">清空</view>
			</view>
		</scroll-view>
		<scroll-view
			v-if="seriesTabs.length > 1"
			scroll-x
			class="series-tab-scroll"
			:scroll-into-view="activeSeriesTabViewId"
			:scroll-with-animation="true"
		>
			<view class="series-tab-list">
				<view
					v-for="(item, index) in seriesTabs"
					:key="item.key"
					:id="`series-tab-${index}`"
					class="series-tab"
					:class="{ active: activeSeriesKey === item.key }"
					@click="emit('select-series', item.key)"
				>
					<text>{{ item.name }}</text>
					<text class="series-tab-count">{{ item.modelCount }}</text>
				</view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup lang="ts">
import { img } from '@/utils/common'

export interface PriceSeriesTab {
	key: string
	name: string
	modelCount: number
	rowCount: number
}

defineProps<{
	modelCount: number
	rowCount: number
	keyword: string
	selectedModels: string[]
	seriesTabs: PriceSeriesTab[]
	activeSeriesKey: string
	activeSeriesTabViewId: string
	showHotBadge: boolean
	hotBadgeImage: string
	hotBadgeBoxStyle: string
	hotBadgeTextStyle: string
}>()

const emit = defineEmits<{
	(event: 'update:keyword', value: string): void
	(event: 'filter' | 'type' | 'refresh' | 'clear-models'): void
	(event: 'remove-model' | 'select-series', value: string): void
}>()

function updateKeyword(event: any) {
	emit('update:keyword', String(event?.detail?.value ?? ''))
}
</script>

<style lang="scss" scoped>
.tool-card {
	position: sticky;
	top: var(--price-toolbar-sticky-top);
	z-index: 20;
	margin-bottom: 10rpx;
	padding: 12rpx;
	border-radius: 10rpx;
	background: var(--toolbar-bg);
	border: 1rpx solid var(--line);
	box-shadow: 0 10rpx 24rpx rgba(15, 23, 42, 0.08);
}

.tool-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12rpx;
	margin-bottom: 12rpx;
}

.tool-actions {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 8rpx;
}

.tool-action {
	height: 52rpx;
	line-height: 52rpx;
	padding: 0 16rpx;
	border-radius: 8rpx;
	box-sizing: border-box;
	border: 1rpx solid var(--line);
	background: var(--bg-soft);
	color: var(--text-main);
	font-size: 22rpx;
	font-weight: 700;
	white-space: nowrap;
}

.tool-action.primary {
	border-color: var(--button-bg);
	background: var(--button-bg);
	color: var(--button-text);
}

.tool-action.active {
	border-color: var(--brand);
	background: var(--warning-bg);
	color: var(--brand);
}

.search-box {
	height: 70rpx;
	border-radius: 35rpx;
	background: var(--bg-soft);
	display: flex;
	align-items: center;
	padding: 0 22rpx;
}

.search-box .iconfont {
	font-size: 26rpx;
	color: var(--text-sub);
	margin-right: 10rpx;
}

.search-input {
	flex: 1;
	height: 70rpx;
	font-size: 26rpx;
	color: var(--text-main);
}

.search-placeholder {
	color: var(--text-sub);
}

.selected-model-scroll,
.series-tab-scroll {
	width: 100%;
	margin-top: 12rpx;
	white-space: nowrap;
}

.selected-model-list {
	display: inline-flex;
	align-items: center;
	gap: 10rpx;
	min-width: 100%;
}

.selected-model-tag {
	height: 52rpx;
	padding: 0 14rpx;
	border-radius: 26rpx;
	background: var(--warning-bg);
	border: 1rpx solid var(--brand);
	color: var(--brand);
	font-size: 23rpx;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 6rpx;
	max-width: 360rpx;
	box-sizing: border-box;
}

.selected-model-tag text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.selected-model-clear {
	height: 52rpx;
	line-height: 52rpx;
	padding: 0 16rpx;
	border-radius: 26rpx;
	background: var(--bg-soft);
	color: var(--text-sub);
	font-size: 23rpx;
	font-weight: 700;
	display: inline-block;
}

.data-meta {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	font-size: 23rpx;
	line-height: 32rpx;
	color: var(--text-sub);
	white-space: nowrap;
	overflow: hidden;
}

.spider-hot-badge {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 10rpx;
}

.spider-hot-badge__image {
	display: block;
	width: 100%;
	height: 100%;
}

.spider-hot-badge__text {
	display: block;
	padding: 2rpx 10rpx;
	border-radius: 999rpx;
	background: var(--notice-bg);
	color: var(--notice-text);
	font-weight: 700;
	white-space: nowrap;
	box-shadow: 0 4rpx 10rpx rgba(245, 158, 11, 0.12);
}

.dot {
	margin: 0 10rpx;
}

.series-tab-list {
	display: flex;
	align-items: center;
	gap: 10rpx;
	width: max-content;
	min-width: max-content;
	padding-right: 20rpx;
}

.series-tab {
	flex-shrink: 0;
	height: 56rpx;
	padding: 0 18rpx;
	border-radius: 28rpx;
	background: var(--series-inactive-bg);
	border: 1rpx solid var(--line);
	color: var(--series-inactive-text);
	font-size: 24rpx;
	font-weight: 800;
	display: inline-flex;
	align-items: center;
	gap: 8rpx;
	box-sizing: border-box;
	white-space: nowrap;
}

.series-tab.active {
	background: var(--series-active-bg);
	border-color: var(--series-active-bg);
	color: var(--series-active-text);
	box-shadow: 0 8rpx 18rpx rgba(15, 23, 42, 0.16);
}

.series-tab-count {
	min-width: 30rpx;
	height: 30rpx;
	line-height: 30rpx;
	padding: 0 8rpx;
	border-radius: 15rpx;
	background: rgba(148, 163, 184, 0.16);
	text-align: center;
	font-size: 20rpx;
}

.series-tab.active .series-tab-count {
	background: rgba(255, 255, 255, 0.18);
}
</style>
