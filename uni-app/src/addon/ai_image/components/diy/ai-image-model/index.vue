<template>
	<view :style="warpCss" class="ai-image-model-container">
		<view :style="maskLayer"></view>

		<!-- style1: 单列布局 -->
		<template v-if="diyComponent.style === 'style1'">
			<view class="single-column-container">
				<view
					v-for="(item, index) in listData"
					:key="index"
					@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
					class="model-item-card"
					:style="{ background: diyComponent.bgcolor }">
					<view class="model-item-image-wrapper" :style="getItemImageStyle(item)">
					
						<up-image
							:src="img(item.logo)"
							width="100%"
							mode="widthFix"
							class="model-item-image">
						</up-image>
					</view>
					<view class="model-item-content">
						<view class="model-item-title" :style="{ color: diyComponent.titlecolor }">
							{{ item.name }}
						</view>
						<view class="model-item-desc" :style="{ color: diyComponent.desccolor }">
							{{ item.desc }}
						</view>
						<view class="model-item-actions">
							<view
								class="same-style-btn"
								@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
							>
								做同款
							</view>
						</view>
					</view>
				</view>
			</view>
		</template>

		<!-- style2: 双列瀑布流 -->
		<template v-else-if="diyComponent.style === 'style2'">
			<view class="waterfall-container" v-if="listData.length">
				<!-- 左列 -->
				<view class="waterfall-column">
					<view
						v-for="(item, index) in leftList"
						:key="'left-' + index"
						@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
						class="model-item-card waterfall-card"
						:style="{ background: diyComponent.bgcolor }">
						<view class="model-item-image-wrapper" :style="getItemImageStyle(item)">
							<up-image
								:src="img(item.logo)"
								width="100%"
								height="100%"
								mode="widthFix"
								class="model-item-image">
							</up-image>
						</view>
						<view class="model-item-content">
							<view class="model-item-title" :style="{ color: diyComponent.titlecolor }">
								{{ item.name }}
							</view>
							<view class="model-item-desc" :style="{ color: diyComponent.desccolor }">
								{{ item.desc }}
							</view>
							<view class="model-item-actions">
								<view
									class="same-style-btn"
									@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
								>
									做同款
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- 右列 -->
				<view class="waterfall-column">
					<view
						v-for="(item, index) in rightList"
						:key="'right-' + index"
						@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
						class="model-item-card waterfall-card"
						:style="{ background: diyComponent.bgcolor }">
						<view class="model-item-image-wrapper" :style="getItemImageStyle(item)">
							<up-image
								:src="img(item.logo)"
								width="100%"
								height="100%"
								mode="widthFix"
								class="model-item-image">
							</up-image>
						</view>
						<view class="model-item-content">
							<view class="model-item-title" :style="{ color: diyComponent.titlecolor }">
								{{ item.name }}
							</view>
							<view class="model-item-desc" :style="{ color: diyComponent.desccolor }">
								{{ item.desc }}
							</view>
							<view class="model-item-actions">
								<view
									class="same-style-btn"
									@click.stop="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
								>
									做同款
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</template>
	</view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
// @ts-ignore
import useDiyStore from '@/app/stores/diy';
// @ts-ignore
import { img, redirect } from '@/utils/common';
// @ts-ignore
import { getModelList } from '@/addon/ai_image/api/aiimage';

// 分页数据
const page = ref(1);
const pageSize = ref(10);
const listData = ref<any[]>([]);
const leftList = ref<any[]>([]);
const rightList = ref<any[]>([]);
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

		// 如果是瀑布流布局，需要获取图片尺寸后再计算左右列
		if (diyComponent.value.style === 'style2') {
			loadImagesSize(newArr).then(() => {
				loadWaterfallData();
			});
		}
	}).catch((e) => {
		loading.value = false;
		isRefreshing.value = false;
	});
};

// 批量获取图片尺寸
const loadImagesSize = (items: any[]) => {
	return Promise.all(
		items.map((item: any) => {
			return new Promise((resolve) => {
				// 如果已经有尺寸信息，直接跳过
				if (item.logo_width && item.logo_height) {
					resolve(item);
					return;
				}

				uni.getImageInfo({
					src: img(item.logo),
					success: (imageInfo: any) => {
						item.logo_width = imageInfo.width;
						item.logo_height = imageInfo.height;
						resolve(item);
					},
					fail: (err: any) => {
						// 获取失败时使用默认值
						item.logo_width = 512;
						item.logo_height = 512;
						resolve(item);
					}
				});
			});
		})
	);
};

// 瀑布流每列宽度（rpx），用于根据图片真实尺寸计算渲染高度
const WATERFALL_COLUMN_WIDTH = 172.5;

// 统一封装：根据图片原始尺寸和列宽，计算当前卡片应占高度（rpx）
const getWaterfallItemHeight = (item: any, columnWidth: number) => {
	// 如果有图片尺寸信息，使用实际比例计算；否则使用默认比例 1:1
	if (item.logo_width && item.logo_height) {
		item.height = parseFloat(item.logo_height) * (columnWidth / parseFloat(item.logo_width));
	} else {
		// 默认正方形图片
		item.height = columnWidth;
	}
	const h = parseFloat(item.height.toFixed(2));
	return h;
};

// 加载瀑布流数据 - 参考 sow_community
const loadWaterfallData = () => {
	if (listData.value.length === 0) {
		leftList.value = [];
		rightList.value = [];
		return;
	}



	// 初始化左右列表
	if ((leftList.value.length + rightList.value.length) === 0) {
		leftList.value = listData.value.filter((_item: any, index: number) => index % 2 === 0);
		rightList.value = listData.value.filter((_item: any, index: number) => index % 2 === 1);
	}

	// 计算左右列高度并平衡 - 参考 sow_community 的实现
	let leftHeight = 0;
	let rightHeight = 0;
	const columnWidth = WATERFALL_COLUMN_WIDTH; // 双列布局，每列宽度约为 172.5rpx (根据容器宽度750rpx计算)
	const textHeight = 80; // 底部文字区域的预估高度（标题+描述+padding）

	let iteration = 0; // 迭代次数
	while (true) {
		iteration++;
		// 计算左列总高度
		leftHeight = leftList.value.map((item: any, idx: number) => {
			const h = getWaterfallItemHeight(item, columnWidth);
			if (idx < 2) {

			}
			return h;
		}).reduce((pre: number, next: number) => {
			return parseFloat(pre.toFixed(2)) + parseFloat(next.toFixed(2)) + textHeight;
		}, 0);

		// 计算右列总高度
		rightHeight = rightList.value.map((item: any, idx: number) => {
			const h = getWaterfallItemHeight(item, columnWidth);
			return h;
		}).reduce((pre: number, next: number) => {
			return parseFloat(pre.toFixed(2)) + parseFloat(next.toFixed(2)) + textHeight;
		}, 0);



		// 左列更高，尝试将左列最后一项移到右列
		if ((leftHeight - rightHeight) > 0) {
			const last = leftList.value[leftList.value.length - 1];
			if (!last) {

				break;
			}

			const lastHeight = last.logo_width && last.logo_height
				? parseFloat((parseFloat(last.logo_height) * (columnWidth / parseFloat(last.logo_width))).toFixed(2))
				: columnWidth;



			if ((leftHeight - rightHeight) > lastHeight) {
				const lastItem = leftList.value.pop();
				if (lastItem) {
					rightList.value.push(lastItem);

				}
			} else {

				break;
			}
		}
		// 右列更高，尝试将右列最后一项移到左列
		else if ((leftHeight - rightHeight) < 0) {
			const last = rightList.value[rightList.value.length - 1];
			if (!last) {

				break;
			}

			const lastHeight = last.logo_width && last.logo_height
				? parseFloat((parseFloat(last.logo_height) * (columnWidth / parseFloat(last.logo_width))).toFixed(2))
				: columnWidth;



			if ((rightHeight - leftHeight) > lastHeight) {
				const lastItem = rightList.value.pop();
				if (lastItem) {
					leftList.value.push(lastItem);

				}
			} else {

			}
		}
		// 高度相等，结束
		else {

			break;
		}

		if (iteration > 50) {

			break;
		}
	}


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
	leftList.value = [];
	rightList.value = [];
	getModelListFn();
};

const props = defineProps(['component', 'index', 'pullDownRefreshCount']);
const diyStore = useDiyStore();
const diyComponent = computed(() => {
	if (diyStore.mode == 'decorate') {
		return diyStore.value[props.index];
	} else {
		return props.component;
	}
});

const warpCss = computed(() => {
	var style = '';
	style += 'position:relative;';
	if (diyComponent.value.componentStartBgColor) {
		if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor)
			style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
		else
			style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
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
});

// 背景图加遮罩层
const maskLayer = computed(() => {
	var style = '';
	if (diyComponent.value.componentBgUrl) {
		style += 'position:absolute;top:0;left:0;width:100%;height:100%;';
		style += `background: rgba(0,0,0,${diyComponent.value.componentBgAlpha / 10});`;

		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	}

	return style;
});

watch(
	() => props.pullDownRefreshCount,
	(newValue, _oldValue) => {
		// 处理下拉刷新业务
		if (newValue !== _oldValue) {
			refresh();
		}
	}
);

watch(
	() => diyComponent.value.style,
	(newValue) => {
		// 当布局样式改变时，重新加载数据
		if (newValue === 'style2') {
			loadWaterfallData();
		}
	}
);

onMounted(() => {
	refresh();
	// 装修模式下刷新
	if (diyStore.mode == 'decorate') {
		watch(
			() => diyComponent.value,
			(newValue) => {
				if (newValue && newValue.componentName == 'AiImageModel') {
					refresh();
				}
			}
		);
	}
});

// 根据列表项的图片尺寸，计算具体渲染高度，并让图片在容器中“以图片为中心”展示
const getItemImageStyle = (item: any) => {
	// 只有样式2（瀑布流）才需要精确控制高度
	if (diyComponent.value.style !== 'style2') return {};
	
	if (item.logo_width && item.logo_height) {
		// 这里的高度单位是 rpx，WATERFALL_COLUMN_WIDTH 也是 rpx
		const h = parseFloat(item.logo_height) * (WATERFALL_COLUMN_WIDTH / parseFloat(item.logo_width));
		return {
			height: h * 1.9 + 'rpx'
		};
	}

	return {};
};
</script>

<style lang="scss" scoped>
/* AI图像模型容器 */
.ai-image-model-container {
	position: relative;
	padding: 20rpx;
	box-sizing: border-box;
}

/* 单列布局容器 */
.single-column-container {
	display: flex;
	flex-direction: column;
	gap: 20rpx;
}

/* 瀑布流容器 - 参考 sow_community */
.waterfall-container {
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-gap: 20rpx;
}

.waterfall-column {
	display: flex;
	flex-direction: column;
	gap: 20rpx;
}

/* 模型项卡片 */
.model-item-card {
	display: flex;
	flex-direction: column;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 24rpx;
	overflow: hidden;
	box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.1);
	transition: all 0.3s ease;
	cursor: pointer;
	position: relative;

	&:active {
		transform: scale(0.98);
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.15);
	}

	/* #ifdef H5 */
	&:hover {
		box-shadow: 0 6rpx 16rpx rgba(0, 0, 0, 0.12);
		transform: translateY(-4rpx);
	}
	/* #endif */
}

/* 瀑布流卡片样式 */
.waterfall-card {
	width: 100%;
	margin-bottom: 0;
}

/* 图片容器 */
.model-item-image-wrapper {
	width: 100%;
	position: relative;
	overflow: hidden;
	display: flex;
	align-items: center;
	justify-content: center;
}

.model-item-image {
	width: 100%;
	display: block;
	border-radius: 0;
}

:deep(.up-image) {
	display: block;
	width: 100%;
}

/* 内容区域 */
.model-item-content {
	padding: 24rpx;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
	flex: 1;
}

/* 标题 */
.model-item-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #333;
	line-height: 1.4;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
	word-break: break-all;
}

/* 描述 */
.model-item-desc {
	font-size: 24rpx;
	color: #666;
	line-height: 1.6;
	// 超出 ...
	overflow: hidden;
	text-overflow: ellipsis;
	flex-wrap: nowrap;

	white-space: nowrap;

}

/* 操作区域 */
.model-item-actions {
	display: flex;
	justify-content: flex-end;
}

/* “做同款”按钮 - 吸睛样式 */
.same-style-btn {
	padding: 10rpx 26rpx;
	border-radius: 999rpx;
	background: linear-gradient(135deg, #ff4b4b, #ff7a00);
	color: #fff;
	font-size: 24rpx;
	font-weight: 600;
	box-shadow: 0 6rpx 14rpx rgba(255, 122, 0, 0.35);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	letter-spacing: 2rpx;
}

.same-style-btn:active {
	opacity: 0.85;
	transform: scale(0.96);
}

/* 瀑布流布局下的样式调整 */
.waterfall-card .model-item-content {
	padding: 16rpx;
	gap: 8rpx;
}

.waterfall-card .model-item-title {
	font-size: 26rpx;
	-webkit-line-clamp: 2;
	line-clamp: 2;
}


</style>
