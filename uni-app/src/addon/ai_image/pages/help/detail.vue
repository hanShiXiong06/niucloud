<template>
	<view class="min-h-screen relative" :style="themeColor()" v-if="detailData">
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
			<view class="help-detail-container">
				<!-- 头部信息卡片 -->
				<view class="help-header-card">
					<view class="help-header-content">
						<view class="help-header-image-wrapper">
							<up-image :src="img(detailData.image)" width="140rpx" height="140rpx"
								class="help-header-image"></up-image>
						</view>
						<view class="help-header-info">
							<view class="help-header-title">{{ detailData.title }}</view>
							<view class="help-header-desc">{{ detailData.desc }}</view>
							<view class="help-header-meta">
								<view class="help-meta-item">
									<u-icon name="clock" size="14" color="#94a3b8" />
									<text>{{ detailData.create_time }}</text>
								</view>
								<view v-if="detailData.view_num" class="help-meta-item">
									<u-icon name="eye" size="14" color="#94a3b8" />
									<text>{{ detailData.view_num }}次浏览</text>
								</view>
							</view>
						</view>
					</view>
				</view>

				<!-- 内容区域 -->
				<view class="help-content-card">
					<view class="help-content-header">
						<u-icon name="file-text" size="24" color="#3b82f6" />
						<view class="help-content-title">详细内容</view>
					</view>
					<view class="help-content-body">
						<up-parse :content="detailData.content" :tagStyle="{
							img: 'vertical-align: top; max-width: 100%; border-radius: 12rpx;',
							p: 'overflow: hidden; word-break: break-word; line-height: 1.8; color: #475569; margin-bottom: 16rpx;',
							h1: 'color: #1e293b; font-size: 36rpx; font-weight: 600; margin-bottom: 16rpx; margin-top: 24rpx;',
							h2: 'color: #1e293b; font-size: 32rpx; font-weight: 600; margin-bottom: 12rpx; margin-top: 20rpx;',
							h3: 'color: #1e293b; font-size: 30rpx; font-weight: 600; margin-bottom: 10rpx; margin-top: 16rpx;',
							ul: 'color: #475569; padding-left: 32rpx; margin-bottom: 16rpx;',
							li: 'color: #475569; margin-bottom: 8rpx; line-height: 1.6;',
							a: 'color: #3b82f6; text-decoration: underline;'
						}"></up-parse>
					</view>
				</view>
			</view>
		</view>
	</view>
	<tabbar name="ai_image" />
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { img, redirect } from '@/utils/common';
import { getHelpInfo } from '@/addon/ai_image/api/help';
import { onLoad } from '@dcloudio/uni-app';

// 主题颜色函数（与 create.vue 保持一致）
const themeColor = () => {
	return {}
}

interface HelpDetail {
	title: string;
	desc: string;
	image: string;
	content: string;
	create_time: string;
	view_num: number;
}

const detailData = ref<HelpDetail>();

onLoad((option: { id?: string }) => {
	if (option?.id) {
		const id = option.id;
		getHelpInfo(id).then((res: { data: HelpDetail }) => {
			detailData.value = res.data;
		});
	} else {
		uni.showToast({
			title: '参数错误',
			icon: 'none'
		});
	}
});
</script>

<style lang="scss" scoped>
/* 帮助详情容器 */
.help-detail-container {
	max-width: 1200rpx;
	margin: 0 auto;
	padding: 0;
}

/* 头部信息卡片 - 与 list.vue 的 help-item-card 保持一致 */
.help-header-card {
	background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%);
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 16rpx;
	border: 1px solid rgba(59, 130, 246, 0.15);
	box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06);
	backdrop-filter: blur(20px);
}

.help-header-content {
	display: flex;
	gap: 16rpx;
	align-items: flex-start;
}

.help-header-image-wrapper {
	flex-shrink: 0;
}

.help-header-image {
	border-radius: 12rpx;
	overflow: hidden;
	background: rgba(248, 250, 252, 0.8);
	border: 1px solid rgba(59, 130, 246, 0.1);
	box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.08);
}

:deep(.up-image) {
	border-radius: 12rpx;
}

.help-header-info {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
}

/* 标题 - 与 list.vue 的 help-item-title 保持一致 */
.help-header-title {
	font-size: 36rpx;
	font-weight: 600;
	color: #1e293b;
	line-height: 1.4;
}

/* 描述 - 与 list.vue 的 help-item-desc 保持一致 */
.help-header-desc {
	font-size: 28rpx;
	color: #64748b;
	line-height: 1.6;
}

.help-header-meta {
	display: flex;
	align-items: center;
	gap: 24rpx;
	flex-wrap: wrap;
}

.help-meta-item {
	display: flex;
	align-items: center;
	gap: 6rpx;
	font-size: 24rpx;
	color: #94a3b8;
}

/* 内容卡片 - 与 list.vue 的卡片风格保持一致 */
.help-content-card {
	background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%);
	border-radius: 16rpx;
	padding: 24rpx;
	border: 1px solid rgba(59, 130, 246, 0.15);
	box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06);
	backdrop-filter: blur(20px);
}

.help-content-header {
	display: flex;
	align-items: center;
	gap: 12rpx;
	margin-bottom: 20rpx;
	padding-bottom: 16rpx;
	border-bottom: 1px solid rgba(59, 130, 246, 0.1);
}

.help-content-title {
	font-size: 32rpx;
	font-weight: 600;
	color: #1e293b;
}

.help-content-body {
	color: #475569;
	line-height: 1.8;
	font-size: 28rpx;

	/* 优化 up-parse 内部样式 */
	:deep(p) {
		color: #475569;
		margin-bottom: 16rpx;
		line-height: 1.8;
	}

	:deep(h1),
	:deep(h2),
	:deep(h3),
	:deep(h4),
	:deep(h5),
	:deep(h6) {
		color: #1e293b;
		font-weight: 600;
		margin-top: 24rpx;
		margin-bottom: 16rpx;
	}

	:deep(h1) {
		font-size: 36rpx;
	}

	:deep(h2) {
		font-size: 32rpx;
	}

	:deep(h3) {
		font-size: 30rpx;
	}

	:deep(img) {
		max-width: 100%;
		border-radius: 12rpx;
		margin: 16rpx 0;
		border: 1px solid rgba(59, 130, 246, 0.1);
	}

	:deep(ul),
	:deep(ol) {
		padding-left: 32rpx;
		margin-bottom: 16rpx;
		color: #475569;
	}

	:deep(li) {
		margin-bottom: 8rpx;
		line-height: 1.6;
		color: #475569;
	}

	:deep(a) {
		color: #3b82f6;
		text-decoration: underline;
	}

	:deep(blockquote) {
		border-left: 4rpx solid rgba(59, 130, 246, 0.3);
		padding-left: 16rpx;
		margin: 16rpx 0;
		color: #64748b;
		font-style: italic;
		background: rgba(59, 130, 246, 0.05);
		border-radius: 8rpx;
	}

	:deep(code) {
		background: rgba(59, 130, 246, 0.1);
		padding: 4rpx 8rpx;
		border-radius: 6rpx;
		font-size: 24rpx;
		color: #3b82f6;
	}

	:deep(pre) {
		background: rgba(248, 250, 252, 0.8);
		padding: 16rpx;
		border-radius: 12rpx;
		overflow-x: auto;
		margin: 16rpx 0;
		border: 1px solid rgba(59, 130, 246, 0.15);
	}

	:deep(pre code) {
		background: transparent;
		padding: 0;
		color: #475569;
	}

	:deep(table) {
		width: 100%;
		border-collapse: collapse;
		margin: 16rpx 0;
	}

	:deep(th),
	:deep(td) {
		border: 1px solid rgba(59, 130, 246, 0.15);
		padding: 12rpx;
		color: #475569;
	}

	:deep(th) {
		background: rgba(59, 130, 246, 0.1);
		color: #1e293b;
		font-weight: 600;
	}
}
</style>