<template>
	<view class="min-h-screen relative" :style="themeColor()">
		<!-- 背景渐变光晕 -->
		<!-- 背景层 -->
		<view class="bg-layer"
			style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #f8fafc 0%, #f0f4ff 30%, #e0e7ff 50%, #f0f4ff 70%, #f8fafc 100%);">
		</view>
		<!-- 顶部光晕 -->
		<view class="glow-layer-1"
			style="position: fixed; top: -150rpx; left: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
		</view>
		<!-- 底部光晕 -->
		<view class="glow-layer-2"
			style="position: fixed; bottom: -150rpx; right: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
		</view>

		<view class="relative p-4" style="position: relative; z-index: 10;">
			<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getHelpListFn">
				<view class="help-list-container">
					<view v-for="(item, index) in listData" :key="index"
						@click="redirect({ url: `/addon/ai_image/pages/help/detail?id=${item.id}` })"
						class="help-item-card">
						<view class="help-item-image-wrapper">
							<up-image :src="img(item.image)" width="120rpx" height="120rpx"
								class="help-item-image"></up-image>
						</view>
						<view class="help-item-content">
							<view class="help-item-title">{{ item.title }}</view>
							<view class="help-item-desc">{{ item.desc }}</view>
							<view class="help-item-footer">
								<view class="help-item-time">
									<u-icon name="clock" size="14" color="#94a3b8" />
									<text>{{ item.create_time }}</text>
								</view>
								<u-icon name="arrow-right" size="16" color="#3b82f6" />
							</view>
						</view>
					</view>
				</view>

				<mescroll-empty v-if="!listData" :option="{ tip: '没有数据' }"></mescroll-empty>
			</mescroll-body>
		</view>
	</view>

	<tabbar name="ai_image" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import {
	getHelpList
} from '@/addon/ai_image/api/help'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
import { img, redirect } from '@/utils/common';

// 主题颜色函数（与 create.vue 保持一致）
const themeColor = () => {
	return {}
}
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const listData = ref()
let loading = ref<boolean>(false);
const getHelpListFn = (mescroll) => {
	let data = ref({});
	loading.value = false;
	data.value.page = mescroll.num;
	data.value.page_size = mescroll.size;
	getHelpList(data.value).then((res) => {
		let newArr = res.data.data;
		mescroll.endSuccess(newArr.length);
		//设置列表数据
		if (mescroll.num == 1) {
			listData.value = []; //如果是第一页需手动制空列表
		}
		listData.value = listData.value.concat(newArr);
		loading.value = true;
	}).catch((e) => {
		console.log('erro', e)
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}
</script>

<style lang="scss" scoped>
/* 帮助列表容器 */
.help-list-container {
	padding: 0;
}

/* 帮助项卡片 - 与 create.vue 的卡片风格保持一致 */
.help-item-card {
	display: flex;
	gap: 16rpx;
	padding: 16rpx;
	margin-bottom: 16rpx;
	border-radius: 16rpx;
	overflow: hidden;
	background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%);
	border: 1px solid rgba(59, 130, 246, 0.15);
	box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06);
	backdrop-filter: blur(20px);
	transition: all 0.2s ease;
	cursor: pointer;

	&:active {
		transform: scale(0.98);
		box-shadow: 0 4rpx 16rpx rgba(59, 130, 246, 0.12);
	}
}

/* 图片容器 */
.help-item-image-wrapper {
	flex-shrink: 0;
}

.help-item-image {
	border-radius: 12rpx;
	overflow: hidden;
	background: rgba(248, 250, 252, 0.8);
	border: 1px solid rgba(59, 130, 246, 0.1);
	box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.08);
}

:deep(.up-image) {
	border-radius: 12rpx;
}

/* 内容区域 */
.help-item-content {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

/* 标题 - 与 create.vue 的文字颜色保持一致 */
.help-item-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #1e293b;
	margin-bottom: 8rpx;
	line-height: 1.4;
}

/* 描述 - 与 create.vue 的文字颜色保持一致 */
.help-item-desc {
	font-size: 26rpx;
	color: #64748b;
	line-height: 1.6;
	margin-bottom: 12rpx;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
}

.help-item-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

/* 时间 - 与 create.vue 的文字颜色保持一致 */
.help-item-time {
	display: flex;
	align-items: center;
	gap: 6rpx;
	font-size: 22rpx;
	color: #94a3b8;
}
</style>