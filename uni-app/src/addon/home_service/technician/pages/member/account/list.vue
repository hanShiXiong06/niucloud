<template>
	<view class="bg-[#fff] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<!-- 账户余额卡片 -->
		<view
			class="balance-card bg-[var(--technician-bg-one)] mx-[24rpx] mt-[20rpx] rounded-lg p-[36rpx] text-white justify-between items-end flex ">
			<view>
				<view class="text-[28rpx] opacity-90 mb-[20rpx]">{{ t('accountBalance') }}</view>
				<view class="flex items-baseline">
					<text
						class="text-[50rpx] font-bold">{{ technicianInfo.commission ? Number(technicianInfo.commission).toFixed(2) : '0.00' }}</text>
					<text class="text-[32rpx] ml-[10rpx]">{{ t('yuan') }}</text>
				</view>
			</view>
			<view class="bg-[#fff] text-[var(--technician-bg-one)] rounded-lg px-[30rpx] text-[24rpx] leading-[1.8]" @click="handleCashOut">
				{{ t('withdraw') }}
			</view>
		</view>

		<!-- 功能导航 -->
		<view class="function-nav bg-white mx-[24rpx] mt-[20rpx] rounded-[24rpx] overflow-hidden">
			<view class="grid grid-cols-2">
				<view class="flex flex-col items-center py-[30rpx]" @click="navigateToAccountDetail">
					<view
						class="w-[60rpx] h-[60rpx] bg-blue-50 rounded-full flex items-center justify-center mb-[10rpx]">
						<image class="w-[40rpx] h-[40rpx] block"
							:src="img('addon/home_service/technician/member/account/list/account_details.png')"
							mode="aspectFill" />
					</view>
					<text class="text-[24rpx] text-[#333]">{{ t('accountDetails') }}</text>
				</view>
				<view class="flex flex-col items-center py-[30rpx]" @click="navigateToIncomeExpenseSummary">
					<view
						class="w-[60rpx] h-[60rpx] bg-blue-50 rounded-full flex items-center justify-center mb-[10rpx]">
						<image class="w-[40rpx] h-[40rpx] block"
							:src="img('addon/home_service/technician/member/account/list/transaction_details.png')"
							mode="aspectFill" />
					</view>
					<text class="text-[24rpx] text-[#333]">{{ t('receiptStatistics') }}</text>
				</view>
			
			</view>
		</view>

		<!-- 今日账单 -->
		<view class="today-bill bg-white mt-[30rpx] rounded-[24rpx] overflow-hidden">
			<view class="p-[28rpx]">
				<view class="flex justify-between items-center mb-[20rpx]">
					<text class="text-[34rpx] font-bold text-[#111]">{{ t('todayBill') }}</text>
					<view class="flex items-center">
						<image class="w-[25rpx] h-[25rpx] block"
							:src="img('addon/home_service/technician/member/account/list/help.png')"
							mode="aspectFill" />
						<text class="pl-[10rpx] text-[26rpx] mr-[10rpx]">{{ t('dataDescription') }}</text>
					</view>
				</view>
				<view class="text-[24rpx] text-[#999] mb-[30rpx]">{{ t('dataDelayTip') }}
				</view>

				<!-- 收入支出统计 -->
				<view class="income-expense-statistics rounded-lg p-[30rpx]">
					<view class="flex justify-between">
						<view class="flex-1 text-center">
							<text
								class="amount-text text-[48rpx] font-bold text-[#000]">+{{ dayBillStat.today_income_count.toFixed(2) }}</text>
							<text
								class="label-text !text-[28rpx] text-[#000] mt-[10rpx] block">{{ t('expectedIncome') }}</text>
						</view>
						<view class="flex-1 text-center">
							<text
								class="amount-text text-[48rpx] font-bold text-[#000]">-{{ dayBillStat.total_expense.toFixed(2) }}</text>
							<text
								class="label-text !text-[28rpx] text-[#000] mt-[10rpx] block">{{ t('expectedExpense') }}</text>
						</view>
					</view>
				</view>
			</view>
			<!-- 账单列表 - 使用mescroll-body实现分页 -->
			<view class="bill-list-wrapper">
				<mescroll-body ref="billMescrollRef" :down="{ use: true }" @up="fetchBillList"  @init="mescrollInit" :height="600">
					<view v-if="billList.length > 0">
						<view v-for="(item, index) in billList" :key="item.id || index">
							<view class="mx-[28rpx] py-[28rpx] " :class="index != billList.length -1 ? 'border-bottom-style' : ''">
								<view class="flex justify-between items-center">
									<text class="text-[28rpx] font-500 text-[#333] truncate mr-[25rpx]">{{ item.memo }}</text>
									<text class="text-[28rpx] font-bold"
										:class="item.account_data.startsWith('-') ? 'text-[#f00]' : 'text-[#333]'">
										{{ item.account_data.startsWith('-') ? '' : '+' }}{{ item.account_data }}
									</text>
								</view>
								<view class="flex justify-between items-center pt-[15rpx]">
									<text
										class="text-[24rpx] text-[#999]">{{ formatTime(item.create_time) }}</text>
									<text
										class="text-[24rpx] text-[var(--text-color-lighter)]">{{ item.status_name }}</text>
								</view>
							</view>
						</view>
					</view>
					<!-- 使用mescroll-empty组件替代原有的暂无数据提示 -->
					<mescroll-empty v-else-if="!loading" :option="{tip: t('noBillData'), btnText: t('refresh')}"
						@emptyclick="refreshData"></mescroll-empty>
					<!-- 底部空间 -->
					<!-- <view class="h-[60rpx]"></view> -->
				</mescroll-body>
			</view>
		</view>
	</view>
	<loading-page :loading="pageLoading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	import { getDayBillStat, getDayBillPage } from "@/addon/home_service/technician/api/account";
	import { getTechnicianInfo } from "@/addon/home_service/technician/api/technician";

	// 定义账单数据接口
	interface BillItem {
		id ?: number;
		account_data : string;
		create_time : string;
		memo : string;
		status_name : string;
		[propName : string] : any;
	}
	const pageLoading = ref(true)
	// 初始化账单列表的mescroll
	const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)
	const billMescrollRef = ref(null)

	// 账单数据列表
	const billList = ref<BillItem[]>([])

	// 日账单统计数据
	const dayBillStat = ref({
		today_income_count: 0,
		total_expense: 0
	})

	// 师傅佣金
	const technicianInfo = ref({
		commission: 0,
	})

	// 加载状态
	const loading = ref(false)

	// 导航到其他页面
	const navigateTo = (url : string) => {
		redirect({ url })
	}

	// 处理提现
	const handleCashOut = () => {
		redirect({
			url: '/addon/home_service/technician/pages/member/account/withdraw'
		})
	}

	// 格式化时间
	const formatTime = (timeString : string) : string => {
		if (!timeString) return '';
		// 假设时间格式为 YYYY-MM-DD HH:mm:ss
		const date = new Date(timeString);
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const day = String(date.getDate()).padStart(2, '0');
		const hours = String(date.getHours()).padStart(2, '0');
		const minutes = String(date.getMinutes()).padStart(2, '0');
		const seconds = String(date.getSeconds()).padStart(2, '0');
		return `${month}-${day} ${hours}:${minutes}:${seconds}`;
	}

	// 获取账单列表 - 重命名函数避免与API函数名冲突
	const fetchBillList = (mescroll : any) => {
		loading.value = true;
		const data = {
			page: mescroll.num,
			limit: mescroll.size
		};

		getDayBillPage(data).then((res : any) => {
			loading.value = false;
			if (res.code === 1 && res.data && res.data.data) {
				const newData = res.data.data as BillItem[];

				// 设置列表数据
				if (mescroll.num === 1) {
					billList.value = []; // 如果是第一页需手动制空列表
				}
				billList.value = billList.value.concat(newData);

				// 结束加载，传入本次加载的数据量
				mescroll.endSuccess(newData.length, res.data.total);
				pageLoading.value = false
			} else {
				mescroll.endErr();
			}
		}).catch(() => {
			loading.value = false;
			pageLoading.value = false
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}
	// 跳转到账户明细页面
	const navigateToAccountDetail = () => {
		redirect({
			url: '/addon/home_service/technician/pages/member/account/account_statement?type=1'
		})
	}

	// 跳转到收支统计页面
	const navigateToIncomeExpenseSummary = () => {
		redirect({
			url: '/addon/home_service/technician/pages/member/account/account_statement?type=2'
		})
	}

	// 刷新数据
	const refreshData = () => {
		getMescroll()?.resetUpScroll();
		fetchDayBillStat();
		fetchTechnicianInfo();
	}

	onMounted(() => {
		// 页面加载时获取日账单统计数据和初始账单列表
		fetchDayBillStat();
		fetchTechnicianInfo();
		// 初始化获取第一页数据
		setTimeout(() => {
			getMescroll()?.triggerUpScroll();
		}, 100);
	})

	// 获取日账单统计数据
	const fetchDayBillStat = () => {
		loading.value = true;
		return getDayBillStat().then((res : any) => {
			loading.value = false;
			if (res.code === 1 && res.data) {
				dayBillStat.value = res.data;
			}
		}).catch(() => {
			loading.value = false;
			console.error(t('fetchDayBillStatFailed'));
		})
	}

	// 获取师傅信息
	const fetchTechnicianInfo = () => {
		loading.value = true;
		return getTechnicianInfo({}).then((res : any) => {
			loading.value = false;
			pageLoading.value = false
			if (res.code === 1 && res.data) {
				// 确保commission是数字类型
				if (res.data.commission !== undefined) {
					res.data.commission = Number(res.data.commission);
				}
				technicianInfo.value = res.data;
			}
		}).catch(() => {
			pageLoading.value = false
			loading.value = false;
			console.error(t('fetchTechnicianDataFailed'));
		})
	}
</script>

<style lang="scss" scoped>
	/* 安全区域适配 */
	.mescroll-body {
		padding-bottom: env(safe-area-inset-bottom, 0) !important;
		padding-bottom: constant(safe-area-inset-bottom, 0) !important;
	}

	.income-expense-statistics {
		border: 1rpx solid #f6f6f6;
	}

	.divider-line {
		border: 1rpx solid #EEEEEE;
	}

	.border-bottom-style {
		border-bottom: 2rpx solid #eeeeee;
	}

	.bill-list-wrapper {
		overflow: hidden;
	}
</style>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>