<template>
	<view class="min-h-screen relative" :style="themeColor()">
		<!-- 背景渐变光晕 -->
		<view class="bg-layer"
			style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #f0f4ff 0%, #e0e7ff 50%, #f0f4ff 100%);">
		</view>
		<view class="glow-layer-1"
			style="position: fixed; top: -200rpx; left: 25%; width: 600rpx; height: 600rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%); border-radius: 50%; z-index: 0; filter: blur(80rpx);">
		</view>
		<view class="glow-layer-2"
			style="position: fixed; bottom: -200rpx; right: 25%; width: 600rpx; height: 600rpx; background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%); border-radius: 50%; z-index: 0; filter: blur(80rpx);">
		</view>

		<view class="relative" style="position: relative; z-index: 10;">
			<!-- 顶部标题 -->
			<view class="px-5 pt-6 pb-4 flex items-center">
				<view class="text-[32rpx] font-bold" style="color: #1e293b;">模型列表</view>
				<view class="text-xs ml-3" style="color: #94a3b8;">选择模型一键创作</view>
			</view>

			<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getModelListFn">
				<!-- 列表容器 -->
				<view class="px-4 pb-4">
					<view v-for="(item, index) in listData" :key="index"
						@click="redirect({ url: `/addon/ai_image/pages/create?model_id=${item.id}` })"
						class="relative mb-3 rounded-xl border transition-all active:scale-98"
						style="background: rgba(255, 255, 255, 0.9); border-color: rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">

						<!-- 卡片内容 -->
						<view class="flex gap-3 p-4">
							<!-- 图片 -->
							<view class="flex-shrink-0">
								<view class="relative">
									<up-image :src="img(item.logo)" width="100rpx" height="100rpx"
										class="rounded-lg overflow-hidden"
										style="border: 1px solid rgba(59, 130, 246, 0.15);"></up-image>
									<!-- 角标 -->
									<view
										class="absolute -top-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center"
										style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); box-shadow: 0 2rpx 6rpx rgba(59, 130, 246, 0.4);">
										<text class="text-white" style="font-size: 10rpx;">AI</text>
									</view>
								</view>
							</view>

							<!-- 内容 -->
							<view class="flex-1 min-w-0 flex flex-col justify-between">
								<!-- 标题 -->
								<view class="text-base font-semibold mb-1.5" style="color: #1e293b; line-height: 1.3;">
									{{ item.name }}
								</view>

								<!-- 描述 -->
								<view class="text-xs line-clamp-2 mb-2" style="line-height: 1.5; color: #64748b;">
									{{ item.desc }}
								</view>

								<!-- 底部信息栏 -->
								<view class="flex items-center justify-between">
									<!-- 标签 -->
									<view class="flex items-center gap-1.5">
										<view class="px-2 py-0.5 rounded text-xs"
											style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
											{{ item.point == 0 ? '免费' : item.point + '积分' }}
										</view>
									</view>

									<!-- 使用按钮 -->
									<view class="flex items-center gap-1 px-3 py-1 rounded-full"
										style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.25);">
										<text class="text-xs text-white" style="font-weight: 500;">使用</text>
										<u-icon name="arrow-right" size="10" color="#ffffff" />
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>

				<mescroll-empty v-if="!listData" :option="{ tip: '没有数据' }"></mescroll-empty>
			</mescroll-body>
		</view>
	</view>

	<tabbar addon="ai_image" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import {
	getModelList
} from '@/addon/ai_image/api/aiimage'
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
const getModelListFn = (mescroll: any) => {
	let data = ref<any>({});
	loading.value = false;
	data.value.page = mescroll.num;
	data.value.limit = mescroll.size;
	getModelList(data.value).then((res: any) => {
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
/* 优化文字渲染 */
text {
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}

/* 点击缩放效果 */
.active\:scale-98:active {
	transform: scale(0.98);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 文本截断 */
.line-clamp-2 {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* 深度选择器 - 图片圆角 */
:deep(.up-image) {
	border-radius: 8rpx;
}

/* 卡片悬停效果优化 */
.relative.mb-3:active {
	border-color: rgba(59, 130, 246, 0.3) !important;
	box-shadow: 0 4rpx 20rpx rgba(59, 130, 246, 0.12) !important;
	transform: scale(0.98);
}

/* AI 角标动画 */
.absolute.w-5.h-5 {
	animation: badge-pulse 3s ease-in-out infinite;
}

@keyframes badge-pulse {

	0%,
	100% {
		transform: scale(1);
		opacity: 1;
	}

	50% {
		transform: scale(1.05);
		opacity: 0.9;
	}
}
</style>