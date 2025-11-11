<template>
	<view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden" :style="themeColor()" >
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
	
		<!-- 头部背景区域 -->
		<view class="header-background relative" >
			<image :src="img('addon/home_service/user/coupon/bg1.png')" class="header-image" mode="aspectFill"></image>
			<!-- 我的券包入口 - 替换为图片 -->
			<view class="my-coupons-btn absolute top-[50rpx] right-[0rpx]" @click="navigateToMyCoupons">
				<image :src="img('addon/home_service/user/coupon/m_coupon.png')" class="w-[180rpx] h-[70rpx]"
					mode="aspectFit"></image>
			</view>
		</view>

		<!-- 合并成一个div，结构上也成为一体 -->
		<mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: false, callback: downCallback }"  
			height="auto" @up="getShopCouponListFn" :top="0" class=" rounded-lg !bg-[#f6f6f6]" style="margin-top:-150rpx">
			<!-- 优惠券类型筛选栏 -->
			<view class="z-index-999 bg-[#ffffff] w-[100vw]" :class="is_fixed ? 'fixed' : ''" :style="{top:systemStore.topTabbarInfo.fullHeight || 0}" v-if="typeList.length">
				<scroll-view scroll-x="true" class="w-full whitespace-nowrap ">
					<view class="flex items-center justify-between w-[100%] py-[24rpx] px-[var(--sidebar-m)] box-border">
						<view
							class="flex-shrink-0 text-[28rpx] leading-[68rpx] text-center px-[36rpx] transition-all duration-300 relative"
							v-for="(item,index) in typeList" :key="index" @click="typeClick(index,item.value)">
							{{ item.label }}
							<view v-if="item.value == curType"
								class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-[80%] h-[4rpx] bg-[#FF000B] rounded-full">
						</view>
						</view>
					</view>
				</scroll-view>
			</view>
			<view class="w-[100vw] h-[58px]" v-if="is_fixed">
				
			</view>
			<!-- 内容保持不变 -->
			<view class=" pb-[var(--top-m)] mx-[25rpx] " >
				<!-- 优惠券列表容器，添加圆角 -->
				<view class="rounded-[var(--rounded-big)] overflow-hidden">
					<template v-for="(item, index) in list">
						<view v-if="item.btnType === 'collected'"
							class="flex items-center relative mt-[25rpx] w-[100%] py-[30rpx] px-[20rpx] coupon-item"
							:style="{ backgroundImage: 'url(' + img('addon/home_service/coupon/coupn_loot.png') + ')'}"
							@click="toDetail(item.id)">
							<view
								class="box-border flex-1  flex items-center">
								<view class="w-[164rpx] box-border flex justify-center ">
									<view class="flex items-baseline !text-[#ff0000]">
										<text
											class="text-[28rpx] leading-[34rpx] text-center font-400 price-font mr-[4rpx] !text-[#ff0000]">￥</text>
										<text
											class="text-[54rpx] font-500 text-left leading-[70rpx] max-w-[136rpx] price-font !text-[#ff0000]">{{ item.coupon_price }}</text>
									</view>
								</view>
								<view class="flex-1 box-border ml-[10rpx]">
									<view class="text-[26rpx] leading-[42rpx] text-left font-500">
										<text v-if="item.min_condition_money === '0.00'">无门槛</text>
										<text v-else>满{{ item.coupon_min_price }}元可用</text>
									</view>
									<view class="mt-[10rpx] text-left flex items-center">
										<text
											class="w-[80rpx] text-center bg-[#FFEFF0] whitespace-nowrap !text-[#ff0000] text-[18rpx] h-[30rpx] leading-[30rpx] rounded-[16rpx] mr-[10rpx] flex-shrink-0">{{ item.type_name }}</text>
										<text
											class="text-[24rpx] truncate max-w-[190rpx] leading-[30rpx] !text-[var(--text-color-light6)]">{{ item.title }}</text>
									</view>
									<view
										class="w-[100%] mt-[10rpx] text-[20rpx] leading-[30rpx] text-[var(--text-color-light6)]">
										<text v-if="item.valid_type == 1">领取之日起{{ item.length || '' }}天内有效</text>
										<text v-else>
											<text v-if="item.valid_end_time!= 0">
												有效期至{{ item.valid_end_time ? item.valid_end_time.slice(0, 10) : '' }}
											</text>
											<text v-else>
												永久
											</text>
										</text>
									</view>
								</view>
							</view>
							<view class="">
								<button class="flex-center"
									:style="{width:'150rpx',height:'60rpx',color:'#fff', fontSize:'24rpx', padding:'0',backgroundColor:'#FF000D', border:'none' ,opacity :'1',borderRadius:'30rpx'}"
									disabled>已领完</button>
							</view>
							<view
								class="absolute top-0 right-[190rpx]  h-[10rpx] w-[20rpx] rounded-br-[20rpx] rounded-bl-[20rpx] bg-[var(--page-bg-color)] ">
							</view>
							<view
								class="absolute bottom-0 right-[190rpx] h-[10rpx] w-[20rpx] rounded-tr-[20rpx] rounded-tl-[20rpx] bg-[var(--page-bg-color)]">
							</view>
						</view>
						<view v-else
							class="flex items-center relative w-[100%] py-[30rpx] px-[20rpx] mb-[1px] coupon-item"
							@click="toDetail(item.id)">
							<view
								class="relative box-border flex-1 flex items-center pl-[10rpx]">
								<view class="w-[164rpx] box-border flex justify-center">
									<view class="flex items-baseline !text-[#ff0000]">
										<text
											class="text-[28rpx] leading-[34rpx] text-center font-400 price-font  mr-[4rpx]">￥</text>
										<text
											class="text-[54rpx] font-500 text-left leading-[70rpx] max-w-[136rpx] price-font">{{ item.coupon_price }}</text>
									</view>
								</view>
								<view class="flex-1 box-border ml-[10rpx]">
									<view class="text-[26rpx] leading-[42rpx] text-left font-500">
										<text v-if="item.min_condition_money === '0.00'">无门槛</text>
										<text v-else>满{{ item.coupon_min_price }}元可用</text>
									</view>
									<view class="mt-[10rpx] text-left flex items-center">
										<text
											class="w-[80rpx] bg-[#FFEFF0] whitespace-nowrap !text-[#ff0000] text-[18rpx] h-[30rpx] leading-[30rpx] text-center rounded-[16rpx] mr-[10rpx] flex-shrink-0">{{ item.type_name }}</text>
										<text
											class="text-[24rpx] truncate max-w-[190rpx] leading-[30rpx] text-[var(--text-color-light9)]">{{ item.title }}</text>
									</view>
									<view
										class="w-[100%] mt-[6rpx] text-[20rpx] leading-[30rpx] text-[var(--text-color-light9)]">
										<text
											v-if="item.valid_type == 1">领取之日起<text>{{ item.length || '' }}</text>天内有效</text>
										<text v-else>
											<text v-if="item.valid_end_time!= 0">
												有效期至{{ item.valid_end_time ? item.valid_end_time.slice(0, 10) : '' }}
											</text>
											<text v-else>
												永久
											</text>
										</text>
									</view>
								</view>
							</view>
							<view v-if="item.btnType === 'collecting'" @click.stop="collecting(item.id, index)"
								class="">
								<button class="flex-center"
									:style="{width:'150rpx',height:'60rpx',color:'#fff', fontSize:'24rpx', padding:'0', backgroundColor:'#FF000D',border:'none',borderRadius:'30rpx'}">立即领取</button>
							</view>
							<view v-if="item.btnType === 'using'" @click.stop="toLink(item.id)"
								class="">
								<button class="flex-center"
									:style="{width:'150rpx',height:'60rpx',color:'#FF000D', fontSize:'24rpx', padding:'0',backgroundColor:'transparent',border:'2rpx solid #FF000D',borderRadius:'30rpx'}">去使用</button>
							</view>
							<view
								class="absolute top-0 right-[190rpx]  h-[10rpx] w-[20rpx] rounded-br-[20rpx] rounded-bl-[20rpx] bg-[var(--page-bg-color)] ">
							</view>
							<view
								class="absolute bottom-0 right-[190rpx] h-[10rpx] w-[20rpx] rounded-tr-[20rpx] rounded-tl-[20rpx] bg-[var(--page-bg-color)]">
							</view>
						</view>
					</template>
				</view>
			</view>

			<mescroll-empty v-if="!list.length && !loading" :option="{tip : '暂无优惠券'}" 
				@emptyclick="redirect({ url: '/addon/home_service/user/pages/goods/list' })"></mescroll-empty>
		</mescroll-body>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref, computed, nextTick, getCurrentInstance, watch } from 'vue'
	import { img, redirect, pxToRpx, getToken } from '@/utils/common'
	import { getCouponList, receiveCoupon, getCouponType } from '@/addon/home_service/user/api/coupon'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onLoad, onPageScroll, onReachBottom, onShow } from '@dcloudio/uni-app'
	import useMemberStore from '@/stores/member'
	import { useLogin } from '@/hooks/useLogin'
	import { t } from '@/locale'
	import useSystemStore from "@/stores/system";
	import { topTabar } from '@/utils/topTabbar'
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '优惠券', topStatusBar: { rollTextColor: '#ffffff' ,titleTextColor:'#ffffff',rollBgColor:'#ff1a1a'} })
	const systemStore = useSystemStore()
	// 修复useMescroll使用方式，确保downCallback被正确绑定
	const { mescrollInit, downCallback, upCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)

	// 定义实例和列表数据
	const instance = getCurrentInstance();
	const list : any = ref<Array<Object>>([]);
	const loading = ref<boolean>(false);
	const memberStore = useMemberStore()
	const userInfo = computed(() => memberStore.info)
	const is_fixed = ref(false)
	onPageScroll((e:any)=>{
		if(e.scrollTop >= 175){
			is_fixed.value = true
		}else{
			is_fixed.value = false
		}
	})
	// 优惠劵
	const price = ref('');
	const create_time = ref('');
	const searchType = ref('all');
	// 类型
	const subActive = ref<number>(0)
	const curType = ref('')
	const typeList = ref<Array<Object>>([])
	// 头部图片的高度
	const headStyle = computed(() => {
		// #ifdef MP
		let style = (pxToRpx(Number(systemStore.menuButtonInfo.height)) + pxToRpx(systemStore.menuButtonInfo.top) + pxToRpx(8) + 364) + 'rpx'
		// #endif
		// #ifdef APP-PLUS
		let style = (pxToRpx(Number(systemStore.systemInfo.statusBarHeight)) + pxToRpx(8) + 364) + 'rpx'
		// #endif
		return style
	})
	const navigateToMyCoupons = () =>{
		redirect({url:'/addon/home_service/user/pages/coupon/member_coupon'})
	}
	// 监听用户登录状态变化
	watch(() => userInfo.value, (newValue, oldValue) => {
		if (newValue) {
			if (getMescroll()) getMescroll().resetUpScroll();
		}
	}, { immediate: true, deep: true })

	// 合并重复的onLoad钩子
	onLoad(() => {
		getMyCouponTypeFn()
		// 确保页面加载时主动获取数据
		nextTick(() => {
			if (getMescroll()) {
				getMescroll().triggerUpScroll()
			}
		})
	})

	// 获取优惠券列表数据
	const getShopCouponListFn = (mescroll : any) => {
		loading.value = true;
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			order: searchType.value === 'all' ? '' : searchType.value,
			sort: searchType.value == 'price' ? price.value : create_time.value,
			type: curType.value || ''
		};

		getCouponList(data).then((res : any) => {
			let newArr = (res.data.data as Array<Object>).map((el : any) => {
				if (el.receive_type == 2) { //receive_type 后台发放
					el.btnType = 'collected'//已领完
				} else {
					if (!getToken()) {
						if (el.sum_count != -1 && el.receive_count === el.sum_count) {
							el.btnType = 'collected'//已领完
						} else {
							el.btnType = 'collecting'//领用
						}
					} else {
						if (el.sum_count != -1 && el.receive_count === el.sum_count) {
							el.btnType = 'collected'//已领完
						} else if (el.is_receive && el.limit_count === el.member_receive_count) {
							el.btnType = 'using'//去使用
						} else {
							el.btnType = 'collecting'//领用
						}
					}
				}
				return el
			})
			//设置列表数据
			if (mescroll.num == 1) {
				list.value = []; //如果是第一页需手动制空列表
			}
			list.value = list.value.concat(newArr);
			mescroll.endSuccess(newArr.length);
			loading.value = false;
		}).catch((error) => {
			console.error('获取优惠券列表失败:', error);
			loading.value = false;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}

	// 领用优惠券
	const collecting = (coupon_id : any, index : number) => {
		if (!userInfo.value) {
			useLogin().setLoginBack({ url: '/addon/home_service/user/pages/coupon/coupon' })
			return false
		}
		receiveCoupon({ coupon_id, number: 1, type: 'receive' }).then((res : any) => {
			if (res.code > 0) {
				list.value[index].member_receive_count += 1
				list.value[index].receive_count += 1
				if (list.value[index].member_receive_count == list.value[index].limit_count
					|| (list.value[index].sum_count != -1 && list.value[index].receive_count === list.value[index].sum_count)
				) {
					list.value[index].btnType = 'using'
				}
			}

		})
	}

	// 跳转到详情页
	const toDetail = (coupon_id : any) => {
		redirect({ url: '/addon/home_service/user/pages/coupon/detail', param: { coupon_id } })
	}
	const toLink = (coupon_id : any) => {
		redirect({ url: '/addon/home_service/user/pages/goods/list', param: { coupon_id } })
	}

	// 获取优惠券类型
	const getMyCouponTypeFn = () => {
		getCouponType().then((res : any) => {
			if (res.code > 0 && res.data && res.data.length) {
				const obj = { label: '全部', value: '' };
				typeList.value = [obj, ...res.data];
			} else {
				// 使用模拟数据作为备用方案
				const mockTypes = [
					{ label: '通用券', value: 1 },
					{ label: '品类券', value: 2 },
					{ label: '商品券', value: 3 }
				];
				const obj = { label: '全部', value: '' };
				typeList.value = [obj, ...mockTypes];
			}
		}).catch((error) => {
			console.error('获取优惠券类型失败:', error);
			// 网络请求失败时使用模拟数据
			const mockTypes = [
				{ label: '通用券', value: 1 },
				{ label: '品类券', value: 2 },
				{ label: '商品券', value: 3 }
			];
			const obj = { label: '全部', value: '' };
			typeList.value = [obj, ...mockTypes];
		});
	}

	// 类型点击处理
	const typeClick = (index : number, data : any) => {
		subActive.value = index
		curType.value = data
		list.value = []
		if (getMescroll()) {
			getMescroll().resetUpScroll()
		}
	}

	// 筛选处理
	const searchTypeFn = (type : any) => {
		searchType.value = type;
		if (type == 'all') {
			create_time.value = '';
			price.value = '';
		}
		if (type == 'price') {
			create_time.value = '';
			if (price.value) {
				price.value = price.value == 'asc' ? 'desc' : 'asc';
			} else {
				price.value = 'asc';
			}
		}
		if (type == 'create_time') {
			price.value = '';
			if (create_time.value) {
				create_time.value = create_time.value == 'asc' ? 'desc' : 'asc';
			} else {
				create_time.value = 'asc';
			}
		}
		if (type == 'type') {
			create_time.value = 'asc';
			price.value = 'asc';
			// 注意：这里引用了未定义的typePopup变量
		} else {
			// 注意：这里引用了未定义的typePopup变量
			list.value = [];

			if (getMescroll()) {
				getMescroll().resetUpScroll();
			}
		}
	}
</script>

<style lang="scss" scoped>
	button {
		box-sizing: border-box;

		&::after {
			display: none;
		}
	}

	.background-size {
		background-repeat: no-repeat;
		background-position: right top;
		background-size: 27%;
	}


	/* 头部样式 */
	.header-background {
		height: 500rpx;
		position: relative;
		overflow: hidden;
	}

	.header-image {
		width: 100%;
		height: 100%;
	}

	.header-title {
		z-index: 10;
	}

	.my-coupons-btn {
		z-index: 10;
		// backdrop-filter: blur(10px);
	}

	/* 类型筛选栏样式 - 现在在mescroll-body内部 */
	.type-filter-bar {
		border-bottom: 1px solid #F0F0F0;
		/* 添加底部边框区分筛选栏和内容 */
		background-color: #fff;
		/* 确保背景为白色 */
	}

	/* mescroll-body样式 - 确保与类型筛选栏成为一体 */
	.mescroll-body {
		background-color: #fff;
		/* 确保背景为白色 */
		padding-bottom: env(safe-area-inset-bottom, 0) !important;
	}

	:deep(.uni-scroll-view-content) {
		display: flex;
		flex-direction: row;
	}

	/* 优惠券列表样式 */
	.coupon-item {
		transition: transform 0.2s ease;
		border-radius: var(--rounded-big);
		margin-top: 20rpx;
		background-color: #fff;
		box-sizing: border-box;
		/* 添加这一行，确保内边距和边框包含在宽度内 */
	}

	// 修改优惠券项最后一项的虚线边框颜色
	.coupon-item:last-child {
		border-bottom-left-radius: var(--rounded-big);
		border-bottom-right-radius: var(--rounded-big);
		/* 保持最后一个的虚线边框颜色一致 */
	}

	/* 适配底部安全区域 */
	.mescroll-body {
		padding-bottom: env(safe-area-inset-bottom, 0) !important;
	}
	:deep(.mescroll-body) {
		margin-top: -150rpx !important;
		background: #f6f6f6 !important;
	}
	:deep(.u-icon__icon){
		color:#ffffff !important;
	}
</style>