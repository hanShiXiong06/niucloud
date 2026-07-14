<template>
	<view v-if="visible" class="sheet-mask" @click="emit('close')">
		<view class="type-sheet" @click.stop>
			<view class="type-sheet-head">
				<text class="type-title">选择报价单</text>
				<text class="type-close" @click="emit('close')">关闭</text>
			</view>
			<block v-if="source === 'spider'">
				<view v-if="spiderSheets.length === 0" class="type-empty">暂无可切换报价单</view>
				<view
					v-for="item in spiderSheets"
					:key="item.id"
					class="type-item"
					:class="{ active: String(item.id) === String(spiderItemId) }"
					@click="emit('selectSpider', item)"
				>
					<view>
						<text class="type-name">{{ item.name || item.title }}</text>
						<text class="type-meta">{{ item.model_count || 0 }} 个型号</text>
					</view>
					<text class="type-check">✓</text>
				</view>
			</block>
			<block v-else>
				<view v-if="quotationTypes.length === 0" class="type-empty">暂无可切换报价单</view>
				<view
					v-for="item in quotationTypes"
					:key="item.dataset_id || item.quotation_id"
					class="type-item"
					:class="{ active: String(item.dataset_id) === String(datasetId) || String(item.quotation_id) === String(priceTypeId) }"
					@click="emit('selectQuotation', item)"
				>
					<view>
						<text class="type-name">{{ item.title || item.dataset_name || item.price_name }}</text>
						<text class="type-meta">{{ item.last_sync_at_text || '待同步' }} · {{ item.model_count || 0 }} 个型号</text>
					</view>
					<text class="type-check">✓</text>
				</view>
			</block>
		</view>
	</view>
</template>

<script setup lang="ts">
interface SpiderSheet {
	id: number | string
	name?: string
	title?: string
	model_count?: number
}

interface QuotationType {
	id: number
	dataset_id: number
	quotation_id: number
	price_name: string
	dataset_name: string
	title: string
	last_sync_at_text: string
	model_count: number
}

withDefaults(defineProps<{
	visible?: boolean
	source?: string
	spiderSheets?: SpiderSheet[]
	spiderItemId?: string
	quotationTypes?: QuotationType[]
	datasetId?: string
	priceTypeId?: string
}>(), {
	visible: false,
	source: '',
	spiderSheets: () => [],
	spiderItemId: '',
	quotationTypes: () => [],
	datasetId: '',
	priceTypeId: ''
})

const emit = defineEmits<{
	(event: 'close'): void
	(event: 'selectSpider', item: SpiderSheet): void
	(event: 'selectQuotation', item: QuotationType): void
}>()
</script>

<style lang="scss" scoped>
.sheet-mask {
	position: fixed;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	z-index: 2000;
	background: rgba(0, 0, 0, 0.42);
	display: flex;
	align-items: flex-end;
}

.type-sheet {
	width: 100%;
	max-height: 1100rpx;
	padding: 28rpx 24rpx calc(28rpx + env(safe-area-inset-bottom));
	background: var(--bg-card);
	border-radius: 28rpx 28rpx 0 0;
	box-sizing: border-box;
	overflow-y: auto;
}

.type-sheet-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 18rpx;
}

.type-title {
	font-size: 32rpx;
	line-height: 44rpx;
	font-weight: 800;
	color: var(--text-main);
}

.type-close {
	font-size: 24rpx;
	color: var(--text-sub);
	padding: 10rpx 18rpx;
	background: var(--bg-soft);
	border-radius: 22rpx;
}

.type-empty {
	padding: 50rpx 0;
	text-align: center;
	font-size: 26rpx;
	color: var(--text-sub);
}

.type-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 22rpx 8rpx;
	border-top: 1rpx solid var(--line);
}

.type-name {
	display: block;
	font-size: 29rpx;
	line-height: 40rpx;
	font-weight: 700;
	color: var(--text-main);
}

.type-meta {
	display: block;
	margin-top: 6rpx;
	font-size: 23rpx;
	line-height: 32rpx;
	color: var(--text-sub);
}

.type-check {
	display: none;
	font-size: 30rpx;
	color: var(--brand);
	font-weight: 800;
}

.type-item.active .type-check {
	display: block;
}
</style>
