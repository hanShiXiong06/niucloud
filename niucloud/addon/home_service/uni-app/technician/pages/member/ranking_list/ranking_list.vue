<template>
	<view class="ranking-list-container">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<!-- 头部背景区域 -->
		<view class="header-background">
			<image :src="img('addon/home_service/technician/member/ranking_list/bj.png')" class="header-image"
				mode="aspectFill"></image>
		</view>

		<!-- 渐变过渡区域 - 包含tab标签 -->
		<view class="gradient-container">
			<!-- 顶部切换标签 -->
			<view class="tab-container"
				:style="{backgroundImage: `url(${img(`addon/home_service/technician/member/ranking_list/${activeTab === 'region' ? 'tab1' : 'tab2'}.png`)})`}">
				<view class="tab-item" :class="{active: activeTab === 'region'}" @click="activeTab = 'region'">
					{{ t('regionTechnicianRank') }}</view>
				<view class="tab-item" :class="{active: activeTab === 'store'}" @click="activeTab = 'store'">
					{{ t('storeTechnicianRank') }}</view>
			</view>
			<!-- 排行榜主体内容 -->
			<!-- 内容容器 - 将分类标签和排行榜列表包裹在一个div内 -->
			<view class="content-wrapper">
				<!-- 分类标签 -->
				<scroll-view scroll-x class="category-scroll" show-scrollbar="false">
					<view class="category-container">
						<view class="category-item" :class="{active: activeCategory === item.id}"
							v-for="item in categories" :key="item.id" @click="activeCategory = item.id">
							{{ item.name }}
						</view>
					</view>
				</scroll-view>

				<!-- 排行榜列表 -->
				<view class="ranking-list">
					<view class="ranking-item" v-for="(item, index) in rankingList"
						:key="item.technician_id || item.id">
						<!-- 排名 -->
						<view class="rank-number">
							<template v-if="index === 0">
								<image :src="img('addon/home_service/technician/member/ranking_list/ph1.png')"
									class="rank-icon" mode="aspectFit"></image>
							</template>
							<template v-else-if="index === 1">
								<image :src="img('addon/home_service/technician/member/ranking_list/ph2.png')"
									class="rank-icon" mode="aspectFit"></image>
							</template>
							<template v-else-if="index === 2">
								<image :src="img('addon/home_service/technician/member/ranking_list/ph3.png')"
									class="rank-icon" mode="aspectFit"></image>
							</template>
							<template v-else>
								{{ index + 1 }}
							</template>
						</view>

						<!-- 技师信息 -->
						<view class="technician-info">
							<image
								:src="item.headimg ? img(item.headimg) : img('static/resource/images/diy/shop_default.jpg')"
								class="avatar" mode="aspectFill"></image>
							<view class="name">{{ item.name }}</view>
						</view>

						<!-- 佣金金额 -->
						<view class="commission">¥{{ item.commission }}</view>
					</view>

					<!-- 加载状态 -->
					<view v-if="loading" class="loading">
						<text>{{ t('loading') }}</text>
					</view>

					<!-- 空数据状态 -->
					<view v-else-if="rankingList.length === 0" class="empty-data">
						<image :src="img('static/resource/images/empty.png')" class="empty-icon w-[300rpx]" mode="widthFix"></image>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
	import { ref, watch } from 'vue'
	import { onShow } from '@dcloudio/uni-app'
	import { img } from '@/utils/common'
	import { getTechnicianRank, getTechnicianInfo } from '@/addon/home_service/technician/api/technician'
	import { t } from '@/locale'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '排行榜', topStatusBar: { textColor: '#333' } })
	// 切换标签状态
	const activeTab = ref<'region' | 'store'>('region')

	// 分类状态 - 默认选择第一个分类
	const activeCategory = ref(92) // 默认选择第一个业务分类ID
	// 初始化带默认值的categories数组，不包含'全部'
	const categories = ref()

	// 排行榜数据
	const rankingList = ref<any[]>([])
	const loading = ref(false)

	// 技师信息数据
	const technicianInfo = ref<any>(null)

	// 获取排行榜数据
	const getRankingList = () => {
		loading.value = true

		// 构造请求参数 - 不再有category_id === 0的情况
		const params = {
			type: activeTab.value,
			category_id: activeCategory.value
		}

		// 使用专门的排行榜API
		getTechnicianRank(params).then((res : any) => {
			// 处理返回数据
			console.log('排行榜API返回数据:', res)
			if (res && res.data && Array.isArray(res.data)) {
				// 适配API返回的数据结构
				rankingList.value = res.data.map((item : any) => ({
					id: item.technician_id, // 确保有id字段用于v-for的key
					name: item.name,
					commission: item.total_amount, // API返回的是total_amount
					// 处理头像路径中的空格问题
					headimg: item.headimg_mid ? item.headimg_mid.trim() : ''
				}))
				console.log('处理后的排行榜数据:', rankingList.value)
			} else if (res && res.data && res.data.data && Array.isArray(res.data.data)) {
				// 兼容另一种可能的数据结构
				rankingList.value = res.data.data.map((item : any) => ({
					id: item.technician_id,
					name: item.name,
					commission: item.total_amount,
					headimg: item.headimg_mid ? item.headimg_mid.trim() : ''
				}))
			} else {
				// 如果API返回数据为空，显示空状态
				rankingList.value = []
				console.warn('暂无排行榜数据')
			}
		}).catch((error : any) => {
			console.error('获取排行榜数据失败:', error)
			// 出错时显示空状态
			rankingList.value = []
		}).finally(() => {
			loading.value = false
		})
	}

	// 获取技师信息（包含分类信息） - 删除硬编码的57参数
	const fetchTechnicianInfo = () => {
		// 不传递特定ID，使用空参数调用API
		getTechnicianInfo({}).then((res : any) => {
			console.log('技师信息API返回数据:', res)

			// 根据API返回格式调整数据处理逻辑
			if (res && res.data) {
				// API返回的直接是技师信息对象
				technicianInfo.value = res.data

				// 从接口获取分类信息 - 不再包含'全部'
				if (res.data.category_name && Array.isArray(res.data.category_name) && res.data.category_name.length > 0) {
					// 深拷贝以确保响应式更新
					categories.value = JSON.parse(JSON.stringify(
						res.data.category_name.map((cat : any) => ({
							id: cat.category_id || cat.id, // 兼容不同字段名
							name: cat.category_name || cat.name // 兼容不同字段名
						}))
					))
					console.log('从API获取的分类数据:', categories.value)

					// 确保activeCategory在有效范围内
					if (categories.value.length > 0) {
						activeCategory.value = categories.value[0]?.id || 92
					}
				}
			}
		}).catch((error : any) => {
			console.error('获取技师信息失败:', error)
			// 出错时保持默认分类
			console.log('使用默认分类数据')
		})
	}

	// 在页面显示时获取数据
	onShow(() => {
		// 先显示默认分类，同时获取最新分类数据
		fetchTechnicianInfo()
		getRankingList()
	})

	// 监听标签和分类变化
	watch([activeTab, activeCategory], () => {
		getRankingList()
	})
</script>

<style lang="scss" scoped>
	.ranking-list-container {
		min-height: 100vh;
		width: 100%;
		background-color: #f0f2f5;
		position: relative;
		overflow: hidden;

		// 适配安全区域
		padding-bottom: env(safe-area-inset-bottom, 0);
	}

	.header-background {
		width: 100%;
		height: 460rpx;
		position: relative;
		z-index: 1;
	}

	.header-image {
		width: 100%;
		height: 100%;
		display: block;
		position: absolute;
		top: 0;
		left: 0;
	}

	// 渐变过渡容器 - 不使用CSS渐变，仅使用覆盖效果
	.gradient-container {
		position: relative;
		margin-top: -165rpx; // 向上移动覆盖部分背景图
		z-index: 2;
		padding: 0 30rpx;
		padding-top: 60rpx; // 为覆盖效果留出空间
	}

	// 普通的顶部切换标签样式
	// 调整tab-container和content-wrapper之间的间距
	.tab-container {
		display: flex;
		justify-content: center;

		padding: 0 30rpx;
		background-size: 100% 100%;
		background-repeat: no-repeat;
		transition: all 0.3s ease;
	}

	// 设置tab-item的固定样式
	.tab-item {
		height: 100rpx;

		font-weight: bold;
		font-size: 28rpx;
		color: #666;
		text-align: center;
		flex: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s ease;
		padding-bottom: 5px; // 为下划线留出空间
	}

	// 选中项样式 - 保留这个样式并调整下划线位置
	.tab-item.active {
		color: #333;
		position: relative;

		&::after {
			content: '';
			position: absolute;
			bottom: 19px; // 调整到文字最下面并产生重叠效果
			left: 43%;
			transform: translateX(-50%);
			width: 65px; // 控制下划线长度，只覆盖前4个文字
			height: 6px;
			background: linear-gradient(90deg, #37E1FF 0%, #2495FF 100%);
			border-radius: 6px;
		}
	}

	// 内容容器 - 包含分类标签和排行榜列表
	.content-wrapper {
		background-color: #FFFFFF;
		border-radius:0 0 24rpx 24rpx; // 修改为完整的圆角，增加视觉效果
		overflow: hidden;
		// 增加白色区域 - 添加内边距
		padding: 15rpx 0;
		min-height:40vh;
	}

	// 分类标签样式
	.category-scroll {
		background-color: #FFFFFF;
		padding: 20rpx 0;
	}

	.category-container {
		display: flex;
		padding: 0 20rpx;
	}

	.category-item {
		padding: 10rpx 30rpx;
		margin-right: 20rpx;
		font-size: 26rpx;
		color: #666;
		background-color: #f5f5f5;
		border-radius: 40rpx;
		white-space: nowrap;
		transition: all 0.3s ease;

		&.active {
			background-color: #e8f4ff;
			color: #1989fa;
			box-shadow: 0 2rpx 8rpx rgba(25, 137, 250, 0.1);
		}
	}

	// 排行榜列表样式 - 增加白色区域
	.ranking-list {
		background-color: #FFFFFF;
		// 增加额外的白色区域
		padding-bottom: 40rpx;
	}

	// 调整ranking-item样式，保持良好的视觉效果
	.ranking-item {
		display: flex;
		align-items: center;
		padding: 17rpx 30rpx;
		// 添加底部边框，增强分隔效果
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none; // 最后一项没有底部分隔线
		}
	}

	// 其他原有样式保持不变
	.rank-number {
		width: 60rpx;
		height: 60rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 28rpx;
		color: #999;
		font-weight: bold;
	}

	.rank-icon {
		width: 40rpx;
		height: 40rpx;
	}

	.technician-info {
		flex: 1;
		display: flex;
		align-items: center;
		padding: 0 20rpx;
	}

	.avatar {
		width: 80rpx;
		height: 80rpx;
		border-radius: 50%;
		margin-right: 20rpx;
	}

	.name {
		font-size: 28rpx;
		color: #333;
	}

	.commission {
		font-size: 32rpx;
		color: #ff6600;
		font-weight: bold;
	}

	.loading {
		padding: 60rpx 0;
		text-align: center;
		color: #999;
		font-size: 28rpx;
	}

	.empty-data {
		padding: 100rpx 0;
		text-align: center;
	}

	.empty-icon {
		margin-bottom: 20rpx;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>