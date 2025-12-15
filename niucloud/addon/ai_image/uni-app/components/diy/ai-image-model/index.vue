<template>
	<view :style="warpCss" class="agent-diy-container">
		<view :style="maskLayer"></view>
		<!-- 双列布局 -->
		<view v-if="isTwoColumn" class="agent-list-container layout-two-column">
			<view v-for="(item, index) in listData" :key="index"
				@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
				class="agent-item-card card-two-column"
				:style="{ 
					background: diyComponent.bgcolor,
					...getCardStyle()
				}">
				<view class="agent-item-image-wrapper">
					<image-swiper 
						:logo="item.logo" 
						:width="'80rpx'"
						:height="'80rpx'"
					></image-swiper>
					<!-- Gemini模型标识 -->
					<view v-if="item.model === 'gemini-3-pro-image-preview'" class="model-badge">
						<text class="banana-icon">🍌</text>
					</view>
				</view>
				<view class="agent-item-content">
					<view class="agent-item-header">
						<view class="agent-item-title" :style="{ 
							color: diyComponent.titlecolor,
							fontSize: (diyComponent.titleSize || 30) + 'rpx'
						}">{{ item.name }}
						</view>
					</view>
					<view class="agent-item-desc" :style="{ 
						color: diyComponent.desccolor,
						fontSize: (diyComponent.descSize || 22) + 'rpx'
					}">{{ item.desc }}</view>
				</view>
			</view>
		</view>
		<!-- 瀑布流布局（单列） -->
		<view v-else class="waterfall-container" :style="{ gap: (diyComponent.cardGap || 12) + 'rpx' }">
			<view class="waterfall-column" :style="{ gap: (diyComponent.cardGap || 12) + 'rpx' }">
				<view v-for="(item, index) in leftList" :key="index"
					@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
					class="agent-item-card waterfall-card"
					:style="{ 
						background: diyComponent.bgcolor,
						...getCardStyle()
					}">
					<view class="agent-item-image-wrapper waterfall-image-wrapper">
						<image-swiper 
							:logo="item.logo" 
							:width="'100%'"
						></image-swiper>
						<!-- Gemini模型标识 -->
						<view v-if="item.model === 'gemini-3-pro-image-preview'" class="model-badge">
							<text class="banana-icon">🍌</text>
						</view>
					</view>
					<view class="agent-item-content waterfall-content">
						<view class="agent-item-header">
							<view class="agent-item-title" :style="{ 
								color: diyComponent.titlecolor,
								fontSize: (diyComponent.titleSize || 30) + 'rpx'
							}">{{ item.name }}
							</view>
						</view>
						<view class="agent-item-footer">
							<view class="agent-item-stats" :style="{ 
								color: diyComponent.desccolor || 'rgba(203, 213, 225, 0.6)',
								fontSize: (diyComponent.descSize || 22) + 'rpx'
							}">
								{{ diyComponent.statsText || 'xx万人在使用' }}
							</view>
							<view class="agent-item-button" 
								:style="getButtonStyle()"
								@click.stop="handleSameStyle(item)">
								{{ diyComponent.buttonText || '同款' }}
							</view>
						</view>
					</view>
				</view>
			</view>
			<view class="waterfall-column" :style="{ gap: (diyComponent.cardGap || 12) + 'rpx' }">
				<view v-for="(item, index) in rightList" :key="index"
					@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
					class="agent-item-card waterfall-card"
					:style="{ 
						background: diyComponent.bgcolor,
						...getCardStyle()
					}">
					<view class="agent-item-image-wrapper waterfall-image-wrapper">
						<image-swiper 
							:logo="item.logo" 
							:width="'100%'"
						></image-swiper>
						<!-- Gemini模型标识 -->
						<view v-if="item.model === 'gemini-3-pro-image-preview'" class="model-badge">
							<text class="banana-icon">🍌</text>
						</view>
					</view>
					<view class="agent-item-content waterfall-content">
						<view class="agent-item-header">
							<view class="agent-item-title" :style="{ 
								color: diyComponent.titlecolor,
								fontSize: (diyComponent.titleSize || 30) + 'rpx'
							}">{{ item.name }}
							</view>
						</view>
						<view class="agent-item-footer">
							<view class="agent-item-stats" :style="{ color: diyComponent.desccolor }">
								xx万人在使用
							</view>
							<view class="agent-item-button" 
								:style="getButtonStyle()"
								@click.stop="handleSameStyle(item)">
								{{ diyComponent.buttonText || '同款' }}
							</view>
						</view>
					</view>
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
// 导入图片轮播组件
import ImageSwiper from './image-swiper.vue';

// 分页数据
const page = ref(1);
const pageSize = ref(10);
const listData = ref<any[]>([]);
const loading = ref<boolean>(false);
const hasMore = ref<boolean>(true);
const isRefreshing = ref<boolean>(false);

// 瀑布流数据
const leftList = ref<any[]>([]);
const rightList = ref<any[]>([]);
const curLimitList = ref<any[]>([]);

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
		
		// 如果不是双列布局，处理瀑布流
		if (!isTwoColumn.value) {
			curLimitList.value = newArr;
			loadWaterfallData();
		}
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
	// 清空瀑布流数据
	leftList.value = [];
	rightList.value = [];
	curLimitList.value = [];
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

// 处理"同款"按钮点击
const handleSameStyle = (item: any) => {
	// 跳转到创建页面，使用当前模型
	redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` });
};

// 获取按钮样式
const getButtonStyle = () => {
	const buttonColor = diyComponent.value.buttonColor || 'rgba(34, 211, 238, 0.9)';
	const buttonSize = diyComponent.value.buttonSize || 24;
	
	return {
		fontSize: `${buttonSize}rpx`,
		background: `linear-gradient(135deg, ${buttonColor}, ${buttonColor.replace('0.9', '0.8')})`,
	};
};

// 获取卡片样式
const getCardStyle = () => {
	const cardRadius = diyComponent.value.cardRadius || 24;
	return {
		borderRadius: `${cardRadius}rpx`,
	};
};

// 瀑布流数据加载
const loadWaterfallData = () => {
	if ((leftList.value.length + rightList.value.length) === 0) {
		// 初始加载，使用当前页数据按索引分配
		leftList.value = curLimitList.value.filter((item: any, index: number) => index % 2 === 0);
		rightList.value = curLimitList.value.filter((item: any, index: number) => index % 2 === 1);
	} else {
		// 追加数据，使用当前页数据
		leftList.value = leftList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 === 0));
		rightList.value = rightList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 === 1));
	}
	
	// 高度平衡算法
	nextTick(() => {
		balanceWaterfallHeight();
	});
};

// 瀑布流高度平衡算法
const balanceWaterfallHeight = () => {
	// 由于图片高度是动态的，我们需要等待图片加载完成后再计算
	// 这里先使用一个基础估算值，实际高度会在图片加载后自动调整
	nextTick(() => {
		setTimeout(() => {
			// 延迟执行，等待图片加载完成
			rebalanceWaterfall();
		}, 500);
	});
};

// 重新平衡瀑布流高度（基于实际渲染后的高度）
const rebalanceWaterfall = () => {
	// 这里可以获取实际DOM高度进行更精确的平衡
	// 由于uni-app的限制，我们使用估算值
	let leftHeight = 0;
	let rightHeight = 0;
	
	// 计算左右两列的总高度（使用估算值）
	const calculateItemHeight = (item: any) => {
		// 基础高度：图片估算高度（根据常见比例） + padding 32rpx + 内容区域估算高度
		// 假设图片宽高比约为 4:3，宽度为 345rpx（(750-40-12)/2），高度约为 260rpx
		// 标题约 42rpx，描述约 78rpx（两行），间距 16rpx
		const baseHeight = 260 + 32 + 42 + 78 + 16; // 约 428rpx
		return baseHeight;
	};
	
	leftHeight = leftList.value.reduce((sum: number, item: any) => {
		return sum + calculateItemHeight(item);
	}, 0);
	
	rightHeight = rightList.value.reduce((sum: number, item: any) => {
		return sum + calculateItemHeight(item);
	}, 0);
	
	// 平衡高度
	while (Math.abs(leftHeight - rightHeight) > 50) { // 50rpx 的容差
		if (leftHeight > rightHeight) {
			// 左侧较高，移动最后一个元素到右侧
			if (leftList.value.length > 0) {
				const lastItem = leftList.value.pop();
				if (lastItem) {
					rightList.value.push(lastItem);
					leftHeight -= calculateItemHeight(lastItem);
					rightHeight += calculateItemHeight(lastItem);
				} else {
					break;
				}
			} else {
				break;
			}
		} else {
			// 右侧较高，移动最后一个元素到左侧
			if (rightList.value.length > 0) {
				const lastItem = rightList.value.pop();
				if (lastItem) {
					leftList.value.push(lastItem);
					rightHeight -= calculateItemHeight(lastItem);
					leftHeight += calculateItemHeight(lastItem);
				} else {
					break;
				}
			} else {
				break;
			}
		}
	}
};
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
	padding: 12rpx;
	box-sizing: border-box;
}

/* 瀑布流容器 */
.waterfall-container {
	display: grid;
	grid-template-columns: 1fr 1fr;
	box-sizing: border-box;
}

/* 瀑布流列 */
.waterfall-column {
	display: flex;
	flex-direction: column;
}

/* 瀑布流卡片 */
.waterfall-card {
	width: 100%;
	margin-bottom: 0;
}

/* 瀑布流图片容器 - 图片优先，占据主要空间 */
.waterfall-image-wrapper {
	width: 100%;
	order: 1;
	display: flex;
	align-items: flex-start;
	justify-content: center;
	overflow: hidden;
	border-radius: 16rpx 16rpx 0 0;
	position: relative;
}

/* 瀑布流内容区域 - 文字在图片下方 */
.waterfall-content {
	order: 2;
	width: 100%;
	padding-top: 0;
}

/* 模型标识徽章 */
.model-badge {
	position: absolute;
	top: 12rpx;
	right: 12rpx;
	z-index: 10;
	background: rgba(0, 0, 0, 0.6);
	backdrop-filter: blur(8rpx);
	border-radius: 50%;
	width: 56rpx;
	height: 56rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 2rpx solid rgba(255, 255, 255, 0.3);
	box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.3), 0 0 0 2rpx rgba(255, 193, 7, 0.4);
	animation: pulse 2s ease-in-out infinite;
}

.banana-icon {
	font-size: 32rpx;
	line-height: 1;
	display: block;
	filter: drop-shadow(0 1rpx 2rpx rgba(0, 0, 0, 0.3));
}

/* 双列布局下的模型标识 */
.card-two-column .model-badge {
	top: 8rpx;
	right: 8rpx;
	width: 48rpx;
	height: 48rpx;
}

.card-two-column .banana-icon {
	font-size: 28rpx;
}

/* 脉冲动画 */
@keyframes pulse {
	0%, 100% {
		box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.3), 0 0 0 2rpx rgba(255, 193, 7, 0.4);
	}
	50% {
		box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.4), 0 0 0 4rpx rgba(255, 193, 7, 0.6);
	}
}

/* 代理项卡片 - 参考 agent.vue 的样式 */
.agent-item-card {
	display: flex;
	gap: 24rpx;
	padding: 24rpx;
	margin-bottom: 20rpx;
	background: rgba(30, 41, 59, 0.6);
	border: 1rpx solid rgba(34, 211, 238, 0.2);
	box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.12), 0 0 0 1rpx rgba(34, 211, 238, 0.05);
	backdrop-filter: blur(10rpx);
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;
	overflow: hidden;
	box-sizing: border-box;

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

/* 瀑布流卡片样式 - 纵向布局，图片优先 */
.agent-item-card.waterfall-card {
	flex-direction: column;
	gap: 16rpx;
	padding: 16rpx;
	margin-bottom: 0;
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
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
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
	padding: 16rpx;
	box-sizing: border-box;
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

/* 底部区域 - 统计和按钮 */
.agent-item-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	width: 100%;
	margin-top: 8rpx;
	gap: 16rpx;
}

/* 统计文字 - 淡化显示 */
.agent-item-stats {
	font-size: 22rpx;
	color: rgba(203, 213, 225, 0.6);
	line-height: 1.4;
	flex: 1;
	opacity: 0.7;
}

/* 同款按钮 */
.agent-item-button {
	flex-shrink: 0;
	padding: 8rpx 24rpx;
	background: linear-gradient(135deg, rgba(34, 211, 238, 0.9), rgba(59, 130, 246, 0.9));
	border-radius: 20rpx;
	font-size: 24rpx;
	font-weight: 500;
	color: #ffffff;
	line-height: 1.2;
	box-shadow: 0 2rpx 8rpx rgba(34, 211, 238, 0.3);
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;
	overflow: hidden;
}

.agent-item-button::before {
	content: '';
	position: absolute;
	top: 0;
	left: -100%;
	width: 100%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
	transition: left 0.5s ease;
}

.agent-item-button:active {
	transform: scale(0.95);
	box-shadow: 0 1rpx 4rpx rgba(34, 211, 238, 0.4);
}

.agent-item-button:active::before {
	left: 100%;
}

/* #ifdef H5 */
.agent-item-button:hover {
	background: linear-gradient(135deg, rgba(34, 211, 238, 1), rgba(59, 130, 246, 1));
	box-shadow: 0 4rpx 12rpx rgba(34, 211, 238, 0.4);
	transform: translateY(-2rpx);
}

.agent-item-button:hover::before {
	left: 100%;
}
/* #endif */

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