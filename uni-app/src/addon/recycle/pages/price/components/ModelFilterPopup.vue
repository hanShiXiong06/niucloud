<template>
	<view v-if="visible" class="model-filter-mask" @click="close" @touchmove.stop.prevent catchtouchmove="noop">
		<view class="model-filter-panel" @click.stop @touchmove.stop>
			<view class="filter-head">
				<view>
					<text class="filter-title">筛选型号</text>
					<text class="filter-desc">已选 {{ draftSelected.length }} 个，确认后只展示勾选型号</text>
				</view>
				<view class="filter-close" @click="close">
					<u-icon name="close" size="30rpx" color="#6b7280"></u-icon>
				</view>
			</view>

			<view class="filter-search">
				<text class="iconfont iconsousuo"></text>
				<input
					v-model="searchKeyword"
					class="filter-search-input"
					placeholder="搜索型号"
					placeholder-class="filter-search-placeholder"
				/>
				<view v-if="searchKeyword" class="search-clear" @click="searchKeyword = ''">
					<u-icon name="close-circle" size="28rpx" color="#9ca3af"></u-icon>
				</view>
			</view>

			<view class="filter-toolbar">
				<view class="toolbar-btn" @click="selectVisible">全选当前</view>
				<view class="toolbar-btn hot" :class="{ active: draftOnlyHot }" @click="toggleOnlyHot">只看热门</view>
				<view class="toolbar-btn" @click="clearDraft">清空</view>
				<text class="toolbar-meta">共 {{ filteredOptions.length }} 个型号</text>
			</view>

			<scroll-view scroll-y class="model-list">
				<view v-if="filteredOptions.length === 0" class="model-empty">没有匹配的型号</view>
				<view
					v-for="item in filteredOptions"
					:key="item.name"
					class="model-option"
					:class="{ active: isSelected(item.name) }"
					@click="toggleModel(item.name)"
				>
					<view class="option-check">
						<u-icon v-if="isSelected(item.name)" name="checkmark" size="26rpx" color="#ffffff"></u-icon>
					</view>
					<view class="option-main">
						<text class="option-name">{{ item.name }}</text>
						<text class="option-meta">{{ item.capacityCount }} 个容量 · {{ item.rowCount }} 条价格</text>
					</view>
					<text v-if="item.isHot" class="option-hot">热门</text>
				</view>
			</scroll-view>

			<view class="filter-footer">
				<view class="footer-btn ghost" @click="clearAndApply">查看全部</view>
				<view class="footer-btn primary" @click="confirm">确认筛选</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

interface ModelFilterOption {
	name: string
	rowCount: number
	capacityCount: number
	isHot?: boolean
}

const props = withDefaults(defineProps<{
	visible: boolean
	options: ModelFilterOption[]
	selected: string[]
	onlyHot: boolean
}>(), {
	visible: false,
	options: () => [],
	selected: () => [],
	onlyHot: false
})

const emit = defineEmits<{
	'update:visible': [value: boolean]
	'update:onlyHot': [value: boolean]
	close: []
	apply: [value: string[]]
}>()

const searchKeyword = ref('')
const draftSelected = ref<string[]>([...props.selected])
const draftOnlyHot = ref(props.onlyHot)

watch(() => props.visible, value => {
	if (value) {
		draftSelected.value = [...props.selected]
		draftOnlyHot.value = props.onlyHot
		searchKeyword.value = ''
	}
}, { immediate: true })

watch(() => props.selected, value => {
	draftSelected.value = [...value]
}, { deep: true })

const filteredOptions = computed(() => {
	const keyword = searchKeyword.value.trim().toLowerCase()
	let list = draftOnlyHot.value ? props.options.filter(item => item.isHot) : props.options
	if (!keyword) return list

	return list.filter(item => item.name.toLowerCase().includes(keyword))
})

function isSelected(name: string): boolean {
	return draftSelected.value.includes(name)
}

function toggleModel(name: string) {
	if (isSelected(name)) {
		draftSelected.value = draftSelected.value.filter(item => item !== name)
		return
	}
	draftSelected.value = [...draftSelected.value, name]
}

function selectVisible() {
	const merged = new Set(draftSelected.value)
	for (const item of filteredOptions.value) {
		merged.add(item.name)
	}
	draftSelected.value = Array.from(merged)
}

function clearDraft() {
	draftSelected.value = []
}

function toggleOnlyHot() {
	draftOnlyHot.value = !draftOnlyHot.value
	emit('update:onlyHot', draftOnlyHot.value)
}

function clearAndApply() {
	emit('update:onlyHot', false)
	emit('apply', [])
}

function confirm() {
	emit('apply', draftSelected.value)
}

function close() {
	emit('close')
}

function noop() {
}
</script>

<style scoped lang="scss">
.model-filter-mask {
	position: fixed;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	z-index: 3000;
	background: rgba(15, 23, 42, 0.52);
	display: flex;
	align-items: flex-end;
}

.model-filter-panel {
	width: 100%;
	max-height: 82vh;
	padding: 26rpx 24rpx calc(24rpx + env(safe-area-inset-bottom));
	box-sizing: border-box;
	border-radius: 28rpx 28rpx 0 0;
	background: #ffffff;
	display: flex;
	flex-direction: column;
}

.filter-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 20rpx;
	margin-bottom: 20rpx;
}

.filter-title {
	display: block;
	font-size: 34rpx;
	line-height: 44rpx;
	font-weight: 800;
	color: #111827;
}

.filter-desc {
	display: block;
	margin-top: 6rpx;
	font-size: 24rpx;
	line-height: 34rpx;
	color: #6b7280;
}

.filter-close {
	width: 58rpx;
	height: 58rpx;
	border-radius: 50%;
	background: #f3f4f6;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.filter-search {
	height: 76rpx;
	border-radius: 38rpx;
	background: #f3f4f6;
	display: flex;
	align-items: center;
	padding: 0 22rpx;
	box-sizing: border-box;
}

.filter-search .iconfont {
	font-size: 28rpx;
	color: #9ca3af;
	margin-right: 12rpx;
}

.filter-search-input {
	flex: 1;
	height: 76rpx;
	font-size: 28rpx;
	color: #111827;
}

.filter-search-placeholder {
	color: #9ca3af;
}

.search-clear {
	width: 44rpx;
	height: 44rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.filter-toolbar {
	display: flex;
	align-items: center;
	gap: 12rpx;
	margin: 18rpx 0;
}

.toolbar-btn {
	height: 54rpx;
	line-height: 54rpx;
	padding: 0 18rpx;
	border-radius: 27rpx;
	background: #eef2ff;
	color: #2563eb;
	font-size: 24rpx;
	font-weight: 700;
}

.toolbar-btn.hot {
	background: #fff7ed;
	color: #ea580c;
}

.toolbar-btn.hot.active {
	background: #ea580c;
	color: #ffffff;
}

.toolbar-meta {
	margin-left: auto;
	font-size: 23rpx;
	color: #6b7280;
}

.model-list {
	min-height: 260rpx;
	max-height: 52vh;
}

.model-empty {
	padding: 70rpx 0;
	text-align: center;
	font-size: 26rpx;
	color: #9ca3af;
}

.model-option {
	min-height: 96rpx;
	padding: 16rpx 18rpx;
	margin-bottom: 12rpx;
	border-radius: 18rpx;
	background: #f9fafb;
	border: 1rpx solid #e5e7eb;
	display: flex;
	align-items: center;
	gap: 16rpx;
	box-sizing: border-box;
}

.model-option.active {
	background: #eff6ff;
	border-color: #93c5fd;
}

.option-check {
	width: 38rpx;
	height: 38rpx;
	border-radius: 50%;
	border: 2rpx solid #cbd5e1;
	background: #ffffff;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	box-sizing: border-box;
}

.model-option.active .option-check {
	border-color: #2563eb;
	background: #2563eb;
}

.option-main {
	flex: 1;
	min-width: 0;
}

.option-hot {
	flex-shrink: 0;
	height: 34rpx;
	line-height: 34rpx;
	padding: 0 12rpx;
	border-radius: 17rpx;
	background: #fff1f2;
	color: #e11d48;
	font-size: 21rpx;
	font-weight: 800;
	margin-left: 12rpx;
}

.option-name {
	display: block;
	font-size: 28rpx;
	line-height: 38rpx;
	font-weight: 700;
	color: #111827;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.option-meta {
	display: block;
	margin-top: 4rpx;
	font-size: 23rpx;
	line-height: 32rpx;
	color: #6b7280;
}

.filter-footer {
	display: flex;
	gap: 16rpx;
	padding-top: 18rpx;
}

.footer-btn {
	flex: 1;
	height: 78rpx;
	line-height: 78rpx;
	text-align: center;
	border-radius: 39rpx;
	font-size: 28rpx;
	font-weight: 800;
}

.footer-btn.ghost {
	background: #f3f4f6;
	color: #374151;
}

.footer-btn.primary {
	background: #111827;
	color: #ffffff;
}
</style>
