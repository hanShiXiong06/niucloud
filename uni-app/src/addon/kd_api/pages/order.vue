<template>
	<view class="page-container" :style="themeColor()">
		<!-- 优化后的统计卡片区域 -->
		<view class="simple-stats">
			<view class="simple-card" @click="redirect({ url: '/app/pages/member/detailed_account?type=commission' })">
				<view class="card-label">累计佣金</view>
				<view class="card-value">¥{{ moneyFormat(memberStore.info?.commission_get) || '0.00' }}</view>
			</view>
			
			<view class="simple-card">
				<view class="card-label">提现中</view>
				<view class="card-value">¥{{ moneyFormat(memberStore.info?.commission_cash_outing) || '0.00' }}</view>
			</view>
		</view>

		<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
			<!-- 订单列表 -->
			<view class="order-list">
				<view v-for="(item, index) in listData" :key="index" class="order-card">
					<!-- 订单头部信息 -->
					<view class="order-header">
						<view class="order-title">{{ item.title }}</view>
						<view class="order-status" :class="getStatusClass(item.status_name)">
							{{ item.status_name }}
						</view>
					</view>

					<!-- 订单详情 -->
					<view class="order-details">
						<view class="detail-row">
							<view class="detail-item">
								<view class="label">实付金额</view>
								<view class="value money">￥{{ item.pay_money }}</view>
							</view>
							<view class="detail-item">
								<view class="label">佣金</view>
								<view class="value commission">￥{{ item.commission }}</view>
							</view>
						</view>

						<view class="detail-row">
							<view class="detail-item">
								<view class="label">结算状态</view>
								<view class="value settlement" :class="item.is_js == 1 ? 'settled' : 'unsettled'">
									{{ item.is_js == 1 ? '已结算' : '未结算' }}
								</view>
							</view>
							<view class="detail-item">
								<view class="label">SID</view>
								<view class="value order-id">{{ item.sid }}</view>
							</view>
						</view>
					</view>

					<!-- 订单时间 -->
					<view class="order-footer">
						<view class="create-time">{{ item.create_time }}</view>
					</view>
				</view>
			</view>
			<mescroll-empty v-if="!listData" :option="{ tip: '还没有数据哟~~~' }"></mescroll-empty>
		</mescroll-body>
		<!-- 底部固定按钮 -->
		<view class="bottom-actions">
			<view class="action-buttons">
				<view class="action-btn withdraw-btn" @click="goWithdraw">
					<view class="btn-text">去提现</view>
				</view>
				<view class="action-btn api-btn" @click="goApiCenter">

					<view class="btn-text">API中心</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, computed} from 'vue';
import { getConfig, getOrder } from '@/addon/kd_api/api/common'
import { onShow } from '@dcloudio/uni-app'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common';
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const moneyFormat=(money: string)=>{
    return isNaN(parseFloat(money)) ? money : parseFloat(money).toFixed(2)
}
import useMemberStore from '@/stores/member'
const memberStore = useMemberStore();
const info = computed(() => memberStore.info)
// 获取系统状态栏的高度
const userInfo = computed(() => memberStore.info)
const listData = ref()
let loading = ref<boolean>(false);
const getOrderListFn = (mescroll) => {
	let data = ref({});
	loading.value = false;
	data.value.page = mescroll.num;
	data.value.limit = mescroll.size;
	getOrder(data.value).then((res) => {
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
const config = ref()
const getConfigFn = () => {
	getConfig().then(res => {
		if (res.data.is_open == 0) {
			uni.$u.toast('请联系管理员获取权限')
			uni.navigateBack()
		}
		config.value = res.data.data
	})
}
// 格式化时间
const formatTime = (timeStr: string) => {
	if (!timeStr) return ''
	const date = new Date(timeStr)
	const now = new Date()
	const diff = now.getTime() - date.getTime()
	const days = Math.floor(diff / (1000 * 60 * 60 * 24))

	if (days === 0) {
		return '今天 ' + timeStr.split(' ')[1]
	} else if (days === 1) {
		return '昨天 ' + timeStr.split(' ')[1]
	} else if (days < 7) {
		return days + '天前'
	} else {
		return timeStr
	}
}

// 获取状态样式类
const getStatusClass = (status: string) => {
	const statusMap = {
		'已完成': 'status-success',
		'进行中': 'status-processing',
		'已取消': 'status-cancelled',
		'待处理': 'status-pending'
	}
	return statusMap[status] || 'status-default'
}

// 去提现
const goWithdraw = () => {
	uni.setStorageSync('cashOutAccountType', 'commission')
	redirect({ url: '/app/pages/member/apply_cash_out' })
}

// 去API中心
const goApiCenter = () => {
	redirect({ url: '/addon/kd_api/pages/index' })
}

onShow(() => {
	getConfigFn()
})
</script>
<style lang="scss" scoped>
.page-container {
	min-height: 100vh;
	background: #f5f7fa;
	display: flex;
	flex-direction: column;
}

// 简洁统计样式
.simple-stats {
	display: flex;
	gap: 12px;
	margin: 0px 12px;
	margin-top: 12px;
}

.simple-card {
	flex: 1;
	background: #ffffff;
	border-radius: 12px;
	padding: 20px 16px;
	text-align: center;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
	border: 1px solid #f0f0f0;
	transition: all 0.2s ease;
	
	&:active {
		transform: scale(0.98);
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
	}
}

.card-label {
	font-size: 13px;
	color: #666;
	margin-bottom: 8px;
	font-weight: 500;
}

.card-value {
	font-size: 18px;
	font-weight: 600;
	color: #333;
}

.header {
	background: white;
	padding: 20px 16px 16px 16px;
	border-bottom: 1px solid #f0f0f0;

	.title {
		font-size: 20px;
		font-weight: 600;
		color: #333;
		margin-bottom: 4px;
	}

	.subtitle {
		font-size: 14px;
		color: #666;
	}
}

.order-list {
	padding: 12px 12px 80px 12px;
	/* 底部留出按钮空间 */
}

.order-card {
	background: white;
	border-radius: 12px;
	margin-bottom: 12px;
	padding: 16px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
	border: 1px solid #f0f0f0;
}

.order-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 12px;

	.order-title {
		flex: 1;
		font-size: 16px;
		font-weight: 500;
		color: #333;
		margin-right: 12px;
		line-height: 1.4;
	}

	.order-status {
		padding: 4px 8px;
		border-radius: 4px;
		font-size: 12px;
		font-weight: 500;
		white-space: nowrap;

		&.status-success {
			background: #f6ffed;
			color: #52c41a;
			border: 1px solid #b7eb8f;
		}

		&.status-processing {
			background: #e6f7ff;
			color: #1890ff;
			border: 1px solid #91d5ff;
		}

		&.status-cancelled {
			background: #fff2f0;
			color: #ff4d4f;
			border: 1px solid #ffccc7;
		}

		&.status-pending {
			background: #fffbe6;
			color: #faad14;
			border: 1px solid #ffe58f;
		}

		&.status-default {
			background: #f5f5f5;
			color: #666;
			border: 1px solid #d9d9d9;
		}
	}
}

.order-details {
	.detail-row {
		display: flex;
		margin-bottom: 8px;

		&:last-child {
			margin-bottom: 0;
		}
	}

	.detail-item {
		flex: 1;
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 8px 12px;
		background: #f8f9fa;
		border-radius: 6px;
		margin-right: 8px;

		&:last-child {
			margin-right: 0;
		}

		.label {
			font-size: 12px;
			color: #666;
		}

		.value {
			font-size: 14px;
			font-weight: 500;

			&.money {
				color: #ff6b35;
			}

			&.commission {
				color: #52c41a;
			}

			&.settlement {
				&.settled {
					color: #52c41a;
				}

				&.unsettled {
					color: #faad14;
				}
			}

			&.order-id {
				color: #666;
				font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
				font-size: 12px;
			}
		}
	}
}

.order-footer {
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid #f0f0f0;

	.create-time {
		font-size: 12px;
		color: #999;
		text-align: right;
	}
}

// 底部固定按钮
.bottom-actions {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: #ffffff;
	padding: 12px 16px calc(12px + env(safe-area-inset-bottom)) 16px;
	border-top: 1px solid #f0f0f0;
	box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.08);
	z-index: 100;

	.action-buttons {
		display: flex;
		gap: 12px;

		.action-btn {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 12px;
			border-radius: 12px;
			cursor: pointer;
			transition: all 0.3s ease;

			.btn-icon {
				font-size: 20px;
				margin-bottom: 4px;
			}

			.btn-text {
				font-size: 14px;
				font-weight: 500;
			}

			&.withdraw-btn {
				background: linear-gradient(135deg, #ff6b6b, #ee5a52);
				color: white;
				box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);

				&:active {
					transform: translateY(1px);
					box-shadow: 0 2px 6px rgba(255, 107, 107, 0.3);
				}
			}

			&.api-btn {
				background: linear-gradient(135deg, #007aff, #0056cc);
				color: white;
				box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);

				&:active {
					transform: translateY(1px);
					box-shadow: 0 2px 6px rgba(0, 122, 255, 0.3);
				}
			}
		}
	}
}

// 空状态样式
:deep(.mescroll-empty) {
	.empty-icon {
		font-size: 48px;
		margin-bottom: 16px;
	}

	.empty-text {
		font-size: 14px;
		color: #999;
	}
}
</style>
