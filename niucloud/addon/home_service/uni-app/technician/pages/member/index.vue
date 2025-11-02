<template>
  <!-- #ifdef MP-WEIXIN || APP-PLUS -->
  <top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
  <!-- #endif -->
	<view class=" bg-[#f6f6f6] bg-gradient-to-b from-[#ffffff] to-[#f6f6f6] h-[80vh]" v-if="technicianInfo.id">
		<view class="py-[20rpx]  px-[30rpx]">
			<view class="flex items-center justify-between">
				<view class="mr-[24rpx] w-[96rpx]">
					<up-image
					    width="96rpx"
					    height="96rpx"
					    :src="img(technicianInfo.headimg ? technicianInfo.headimg : '')"
					    model="aspectFill"
					    shape="circle"
					    class="border-[#fff] rounded-full">
					    <template #error>
					        <image class="w-[96rpx] h-[96rpx]  rounded-full" :src="img('addon/home_service/technician/user.png')" />
					    </template>
					</up-image>
				</view>
				<view class="flex-1 flex flex-col justify-around h-[96rpx]">
					<view class="flex items-center">
						<view class="text-[34rpx] font-bold">
							{{technicianInfo.real_name}}
						</view>
						<view class="ml-[15rpx]" v-if="technicianInfo.level">
							<view class="flex justify-end items-center bg-[#2A8FF7] rounded-[30rpx] pr-[10rpx] py-[4rpx]">
								<image :src="img('/addon/home_service/technician/level-icon.png')"
									class="block w-[46rpx] h-[36rpx] mr-[10rpx]" mode="aspectFit"></image>
								<text class="text-[24rpx] text-[#fff]">{{technicianInfo.level.level_name}}</text>
							</view>
						</view>
					</view>
					<view class=" flex items-center">
						<text class="text-[26rpx]" v-if="technicianInfo.store">{{technicianInfo.store.store_name}}</text>
						<view class="w-[2rpx] h-[22rpx] bg-[#d0d0d0] mx-[15rpx] mb-[4rpx]" v-if="technicianInfo.store?.store_name"></view>
						<text class="text-[26rpx]">ID:{{technicianInfo.id}}</text>
					</view>
				</view>
				<view class="" @click="redirect({url:'/addon/home_service/user/pages/member/index'})">
					<view class="mb-[10rpx]">
						<u-icon name="home" size="25"></u-icon>
					</view>
				</view>
			</view>
		</view>
		<view class="bg-[#fff] mx-[30rpx] rounded-[25rpx]">
			<view class="bg-[var(--technician-bg-one)] flex justify-around py-[40rpx] rounded-[30rpx]">
				<view class="flex flex-col items-center justify-center" @click="redirect({url:'/addon/home_service/technician/pages/member/account/list'})">
					<view class="text-[#fff] text-[26rpx]">
						{{t('todayMoney')}}
					</view>
					<view class="text-[#fff] text-[48rpx] font-bold my-[25rpx]">
						{{technicianTodayInfo.total_commission ? technicianTodayInfo.total_commission : '0.00'}}
					</view>
					<view class="text-[#fff] text-[26rpx]">
						<text>{{t('myAccount')}}</text>
						<text class="iconfont iconarrow-right text-[24rpx]"></text>
					</view>
				</view>
				<view class="flex flex-col items-center justify-center" @click="redirect({url:'/addon/home_service/technician/pages/member/order/statistics'})">
					<view class="text-[#fff] text-[26rpx]">
						{{t('orderFinshNumber')}}
					</view>
					<view class="text-[#fff] text-[48rpx] font-bold my-[25rpx]">
						{{technicianTodayInfo.today_completed_count || '0'}}
					</view>
					<view class="text-[#fff] text-[26rpx]">
						<text>{{t('orderNumber')}}</text>
						<text class="iconfont iconarrow-right text-[24rpx]"></text>
					</view>
				</view>
			</view>
			<view class="flex justify-around bg-[#fff] rounded-[25rpx] p-[30rpx]">
				<view class="flex items-center" @click="redirect({url:'/addon/home_service/technician/pages/member/feedback/feedback'})">
					<view class="">
						<image :src="img('/addon/home_service/technician/shensu.png')"
							class="block w-[30rpx] h-[30rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx]">
						{{t('ViolationAppeal')}}
					</view>
				</view>
				<view class="flex items-center" @click="redirect({url:'/addon/home_service/technician/pages/member/order/evaluate'})">
					<view class="">
						<image :src="img('/addon/home_service/technician/shoucang.png')"
							class="block w-[30rpx] h-[30rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx]">
						{{t('MyReview')}}
					</view>
				</view>
			</view>
		</view>
		<view class="m-[30rpx]">
				<image :src="img('/addon/home_service/technician/member/img_2.jpg')"
					class="block w-[100%] rounded-lg" mode="widthFix"></image>
		</view>
		<view class="mx-[30rpx] flex justify-between">
			<view class="flex justify-between items-center bg-[#fff] p-[20rpx] py-[25rpx] rounded-[15rpx]" @click="redirect({url:'/addon/home_service/technician/pages/member/store/detail'})">
				<view class="">
					<view class="text-[28rpx] font-Medium">
						{{t('store')}}
					</view>
					<view class="text-[24rpx] text-[#999] mt-[15rpx]">
						{{t('storeInfo')}}
					</view>
				</view>
				<view class="ml-[15rpx]">
					<image :src="img('/addon/home_service/technician/member/member_01.png')"
						class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
				</view>
			</view>
			<view class="flex justify-between items-center bg-[#fff] p-[20rpx] py-[25rpx] rounded-[15rpx]" @click="redirect({url:'/addon/home_service/technician/pages/member/rest/index'})">
				<view class="">
					<view class="text-[28rpx] font-Medium">
						{{t('workTime')}}
					</view>
					<view class="text-[24rpx] text-[#999] mt-[15rpx]">
						{{t('levalWork')}}
					</view>
				</view>
				<view class="ml-[15rpx]">
					<image :src="img('/addon/home_service/technician/member/member_02.png')"
						class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
				</view>
			</view>
			<view class="flex justify-between items-center bg-[#fff] p-[20rpx] py-[25rpx] rounded-[15rpx]" @click="redirect({url:'/addon/home_service/technician/pages/member/order/statistics'})">
				<view class="">
					<view class="text-[28rpx] font-Medium">
						{{t('ServiceAnalysis')}}
					</view>
					<view class="text-[24rpx] text-[#999] mt-[15rpx]">
						{{t('servicesData')}}
					</view>
				</view>
				<view class="ml-[15rpx]">
					<image :src="img('/addon/home_service/technician/member/member_03.png')"
						class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
				</view>
			</view>
		</view>
		
		<view class="mx-[30rpx] p-[30rpx] bg-[#fff] mt-[30rpx] rounded-[20rpx]">
			<view class="text-[30rpx]">
				{{t('workTools')}}
			</view>
			<view class="flex justify-between mt-[30rpx]">
				<view class="flex flex-col items-center justify-center"  @click="redirect({ url: '/app/pages/member/contact' })">
					<view class="">
						<image :src="img('/addon/home_service/technician/concat.png')"
							class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('kefuServices')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center" @click="redirect({url:'/addon/home_service/technician/pages/member/detail'})">
					<view class="">
						<image :src="img('/addon/home_service/technician/member_info.png')"
							class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('memberInfo')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center" @click="redirect({url:'/addon/home_service/technician/pages/member/help/help'})">
					<view class="">
						<image :src="img('/addon/home_service/technician/help.png')"
							class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('helpCenter')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center" @click="redirect({url:'/addon/home_service/technician/pages/member/ranking_list/ranking_list'})">
					<view class="">
						<image :src="img('/addon/home_service/technician/address.png')"
							class="block w-[44rpx] h-[44rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('areaData')}}
					</view>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
	<tabbar :value="3"></tabbar>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import { getTechnicianInfo,getTechnicianTotadyInfo} from '@/addon/home_service/technician/api/technician'
	import tabbar from '@/addon/home_service/technician/components/tabbar/tabbar'
	import { ref, computed, onMounted } from 'vue'
	import { onShow, onPageScroll } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { useLocation } from '@/hooks/useLocation'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '个人中心', topStatusBar: { textColor: '#333', rollBgColor: 'transparent' } })
	const current  = ref(0)
	const locationVal = useLocation(true);
	const loading = ref<boolean>(true) //页面加载动画
	const swiperList = ref([
		img('/addon/home_service/technician/member/banner.png'),
	])
	const technicianInfo = ref({})
	const technicianTodayInfo = ref({})
	const getTechnicianInfoFn = () =>{
		getTechnicianInfo().then((res)=>{
			technicianInfo.value = res.data
			if(res.data && res.data.real_name){
				getTechnicianTotadyInfoFn()
			}else{
				uni.showToast({
					title:'您还未成为技师，请先申请成为技师',
					icon:'none'
				})
				setTimeout(()=>{
					redirect({url:'/addon/home_service/user/pages/settle/technician'})
				},1500)
			}
		}).catch((err)=>{
		})
	}
	onShow(()=>{
		uni.removeStorageSync('manually_select_location_from_map')
		uni.removeStorageSync('location_address')
		locationVal.onLoad();
		locationVal.init();
		getTechnicianInfoFn()
	})
	const getTechnicianTotadyInfoFn = ()=>{
		getTechnicianTotadyInfo().then((res)=>{
			technicianTodayInfo.value = res.data
			loading.value = false
		}).catch((err)=>{
		})
	}
</script>
<style lang="scss">
@import '@/addon/home_service/technician/style/index.scss';
</style>
<style scoped lang="scss">

	page {
		background-color: #f6f6f6;
	}
	  .indicator {
	    @include flex(row);
	    justify-content: center;
	
	    &__dot {
	      height: 6px;
	      width: 6px;
	      border-radius: 100px;
	      background-color: rgba(255, 255, 255, 0.35);
	      margin: 0 5px;
	      transition: background-color 0.3s;
	
	      &--active {
	        background-color: #ffffff;
	      }
	    }
	  }
	
	  .indicator-num {
	    padding: 2px 0;
	    background-color: rgba(0, 0, 0, 0.35);
	    border-radius: 100px;
	    width: 35px;
	    @include flex;
	    justify-content: center;
	
	    &__text {
	      color: #ffffff;
	      font-size: 12px;
	    }
	  }
	  :deep(.u-tabbar__placeholder){
		  background:#f6f6f6 !important;
	  }
	  page{
		  background:#f6f6f6 !important;
	  }
</style>