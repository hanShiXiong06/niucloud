<template>
	<view :style="warpCss" class="agent-diy-container">
		<view :style="maskLayer"></view>
		<view class="agent-list-container" :class="{ 'layout-two-column': isTwoColumn }">
			<view v-for="(item, index) in listData" :key="index"
				@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
				class="agent-item-card" :class="{ 'card-two-column': isTwoColumn }"
				:style="{ background: diyComponent.bgcolor }">
				<view class="agent-item-image-wrapper">
					<up-image :src="img(item.logo)" :width="isTwoColumn ? '80rpx' : '120rpx'"
						:height="isTwoColumn ? '80rpx' : '120rpx'" class="agent-item-image"></up-image>
				</view>
				<view class="agent-item-content">
					<view class="agent-item-header">
						<view class="agent-item-title" :style="{ color: diyComponent.titlecolor }">{{ item.name }}
						</view>

					</view>
					<view class="agent-item-desc" :style="{ color: diyComponent.desccolor }">{{ item.desc }}</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue';
// @ts-ignore - 忽略模块类型声明错误
import useDiyStore from '@/app/stores/diy';
// @ts-ignore - 忽略模块类型声明错误
import { img, redirect } from '@/utils/common';
// @ts-ignore - 忽略模块类型声明错误
import { getModelList } from '@/addon/ai_image/api/aiimage';
// 分页数据
const page = ref(1);
const pageSize = ref(10);
const listData = ref<any[]>([]);
const loading = ref<boolean>(false);
const hasMore = ref<boolean>(true);
const isRefreshing = ref<boolean>(false);

// 获取列表数据
const getModelListFn = () => {
	if (loading.value) return;
	loading.value = true;
	const data = {
		page: page.value,
		page_size: pageSize.value
	};

	getModelList(data).then((res: any) => {
		const newArr = res.data.data;

		if (page.value === 1) {
			listData.value = newArr;
		} else {
			listData.value = listData.value.concat(newArr);
		}

		hasMore.value = newArr.length >= pageSize.value;
		loading.value = false;
		isRefreshing.value = false;
	}).catch((e) => {
		console.log('error', e);
		loading.value = false;
		isRefreshing.value = false;
	});
};

// 加载更多
const loadMore = () => {
	if (!hasMore.value || loading.value) return;
	page.value++;
	getModelListFn();
};

// 刷新数据
const refresh = () => {
	isRefreshing.value = true;
	page.value = 1;
	hasMore.value = true;
	getModelListFn();

	// 如果使用diyStore，同时刷新高度
	nextTick(() => {
		const query = uni.createSelectorQuery().in(instance);
		query.select('.scroll-view').boundingClientRect((data: any) => {
			if (data) {
				height.value = data.height;
			}
		}).exec();
	});
};


const props = defineProps(['component', 'index', 'pullDownRefreshCount']);
const diyStore = useDiyStore();
const diyComponent = computed(() => {
	if (diyStore.mode == 'decorate') {
		return diyStore.value[props.index];
	} else {
		return props.component;
	}
})

const warpCss = computed(() => {
	var style = '';
	style += 'position:relative;';
	if (diyComponent.value.componentStartBgColor) {
		if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
		else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
	}

	if (diyComponent.value.componentBgUrl) {
		style += `background-image:url('${img(diyComponent.value.componentBgUrl)}');`;
		style += 'background-size: cover;background-repeat: no-repeat;';
	}

	if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	return style;
})

// 背景图加遮罩层
const maskLayer = computed(() => {
	var style = '';
	if (diyComponent.value.componentBgUrl) {
		style += 'position:absolute;top:0;width:100%;';
		style += `background: rgba(0,0,0,${diyComponent.value.componentBgAlpha / 10});`;
		style += `height:${height.value}px;`;

		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	}

	return style;
});

watch(
	() => props.pullDownRefreshCount,
	(newValue, oldValue) => {
		// 处理下拉刷新业务
		if (newValue !== oldValue) {
			refresh();
		}
	}
)

onMounted(() => {
	refresh();
	// 装修模式下刷新
	if (diyStore.mode == 'decorate') {
		watch(
			() => diyComponent.value,
			(newValue, oldValue) => {
				if (newValue && newValue.componentName == 'RichText') {
					refresh();
				}
			}
		)
	}
});

const instance = getCurrentInstance();
const height = ref(0);

// 判断是否为双列布局（通过 diyComponent.layout 或 styleType 控制，默认单列）
const isTwoColumn = computed(() => {
	return diyComponent.value.style === 'style2';
});
</script>

<style lang="scss" scoped>
/* 代理DIY容器 */
.agent-diy-container {
	position: relative;
}

/* 代理列表容器 */
.agent-list-container {
	padding: 12rpx;
}

/* 双列布局容器 */
.agent-list-container.layout-two-column {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 12rpx;
	padding: 12rpx;
	box-sizing: border-box;
}

/* 代理项卡片 - 参考 agent.vue 的样式 */
.agent-item-card {
	display: flex;
	gap: 24rpx;
	padding: 24rpx;
	margin-bottom: 20rpx;
	background: rgba(30, 41, 59, 0.6);
	border-radius: 24rpx;
	border: 1rpx solid rgba(34, 211, 238, 0.2);
	box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.12), 0 0 0 1rpx rgba(34, 211, 238, 0.05);
	backdrop-filter: blur(10rpx);
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;
	overflow: hidden;

	&::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 2rpx;
		background: linear-gradient(90deg, transparent, rgba(34, 211, 238, 0.3), transparent);
		opacity: 0;
		transition: opacity 0.3s ease;
	}

	&:active {
		transform: scale(0.98);
		border-color: rgba(34, 211, 238, 0.4);
		box-shadow: 0 4rpx 12rpx rgba(34, 211, 238, 0.15), 0 0 0 1rpx rgba(34, 211, 238, 0.1);
	}

	/* #ifdef H5 */
	&:hover {
		border-color: rgba(34, 211, 238, 0.3);
		box-shadow: 0 6rpx 16rpx rgba(34, 211, 238, 0.12), 0 0 0 1rpx rgba(34, 211, 238, 0.15);

		&::before {
			opacity: 1;
		}
	}

	/* #endif */
}

/* 双列布局卡片样式 */
.agent-item-card.card-two-column {
	flex-direction: column;
	width: calc((100% - 12rpx) / 2);
	min-width: 0;
	max-width: calc((100% - 12rpx) / 2);
	margin-bottom: 12rpx;
	padding: 16rpx;
	gap: 12rpx;
	align-items: center;
	text-align: center;
	box-sizing: border-box;
	flex-shrink: 0;
}

/* 图片容器 */
.agent-item-image-wrapper {
	flex-shrink: 0;
	position: relative;
}

/* 双列布局下的图片容器 */
.card-two-column .agent-item-image-wrapper {
	width: 100%;
	display: flex;
	justify-content: center;
	align-items: center;
	flex-shrink: 0;
}

.agent-item-image {
	border-radius: 16rpx;
	overflow: hidden;
	background: rgba(51, 65, 85, 0.6);
	border: 1rpx solid rgba(34, 211, 238, 0.15);
	box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.1);
	transition: all 0.3s ease;
}

.agent-item-card:active .agent-item-image {
	border-color: rgba(34, 211, 238, 0.25);
	box-shadow: 0 3rpx 8rpx rgba(34, 211, 238, 0.12);
}

/* #ifdef H5 */
.agent-item-card:hover .agent-item-image {
	border-color: rgba(34, 211, 238, 0.25);
	box-shadow: 0 3rpx 8rpx rgba(34, 211, 238, 0.12);
}

/* #endif */

:deep(.up-image) {
	border-radius: 16rpx;
}

/* 内容区域 */
.agent-item-content {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
}

/* 双列布局下的内容区域 */
.card-two-column .agent-item-content {
	width: 100%;
	min-width: 0;
	align-items: center;
	gap: 8rpx;
	flex: 1;
	display: flex;
	flex-direction: column;
	justify-content: flex-start;
}

/* 头部区域 - 标题和箭头 */
.agent-item-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16rpx;
	width: 100%;
}

/* 双列布局下的头部区域 */
.card-two-column .agent-item-header {
	flex-direction: column;
	align-items: center;
	gap: 6rpx;
	width: 100%;
	min-width: 0;
}

/* 标题 */
.agent-item-title {
	flex: 1;
	font-size: 30rpx;
	font-weight: 600;
	color: #e2e8f0;
	line-height: 1.4;
	-webkit-font-smoothing: antialiased;
	min-width: 0;
	display: -webkit-box;
	-webkit-line-clamp: 1;
	line-clamp: 1;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* 双列布局下的标题 */
.card-two-column .agent-item-title {
	flex: none;
	font-size: 26rpx;
	text-align: center;
	width: 100%;
	min-width: 0;
	word-break: break-all;
	word-wrap: break-word;
	overflow-wrap: break-word;
	max-width: 100%;
	box-sizing: border-box;
}

/* 箭头图标 */
.agent-item-arrow {
	flex-shrink: 0;
	margin-top: 4rpx;
	transition: transform 0.3s ease;
	opacity: 0.8;
}

/* 双列布局下的箭头图标 */
.card-two-column .agent-item-arrow {
	margin-top: 0;
}

.agent-item-card:active .agent-item-arrow {
	transform: translateX(6rpx);
	opacity: 1;
}

/* #ifdef H5 */
.agent-item-card:hover .agent-item-arrow {
	transform: translateX(6rpx);
	opacity: 1;
}

/* #endif */

/* 描述 */
.agent-item-desc {
	font-size: 26rpx;
	color: #cbd5e1;
	line-height: 1.6;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
	-webkit-font-smoothing: antialiased;
	flex: 1;
}

/* 双列布局下的描述 */
.card-two-column .agent-item-desc {
	font-size: 22rpx;
	text-align: center;
	width: 100%;
	min-width: 0;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	word-break: break-all;
	word-wrap: break-word;
	overflow-wrap: break-word;
	max-width: 100%;
	box-sizing: border-box;
	line-height: 1.5;
}
</style>