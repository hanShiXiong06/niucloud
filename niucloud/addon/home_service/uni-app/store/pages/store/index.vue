<template>
	<!-- #ifdef MP-WEIXIN || APP-PLUS -->
	<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="false" />
	<!-- #endif -->
	<view class=" bg-[#f6f6f6] bg-gradient-to-b from-[#ffffff] to-[#f6f6f6]" v-if="!loading">
		<view class="py-[20rpx]  px-[30rpx]">
			<view class="flex items-center justify-between">
				<view class="mr-[24rpx]">
					<u--image :src="img(storeInfo.headimg)" width="100rpx" height="100rpx" radius="100rpx"
						mode="aspectFill">
						<template #error>
							<image :src="img('static/resource/images/default_headimg.png')"
								class="w-[100rpx] h-[100rpx] rounded-full  border-2 border-white"
								mode="aspectFill">
							</image>
						</template>
					</u--image>
				</view>
				<view class="flex-1 flex flex-col justify-around h-[96rpx]">
					<view class="flex items-center">
						<view class="text-[34rpx] font-bold">
							{{storeInfo.store_name}}
						</view>
					</view>
					<view class="flex">
						<text class="text-[26rpx] text-[#999999] truncate w-[450rpx]">{{storeInfo.full_address}}{{storeInfo.full_address}}{{storeInfo.full_address}}{{storeInfo.full_address}}{{storeInfo.full_address}}</text>
						<!-- <view class="bg-[#F4F9FF] text-[22rpx] p-[10rpx] rounded-[10rpx] px-[15rpx] mr-[15rpx]">
							保洁服务
						</view>
						<view class="bg-[#F4F9FF] text-[22rpx] p-[10rpx] rounded-[10rpx] px-[15rpx] mr-[15rpx]">
							保洁服务
						</view>
						<view class="bg-[#F4F9FF] text-[22rpx] p-[10rpx] rounded-[10rpx] px-[15rpx] mr-[15rpx]">
							保洁服务
						</view> -->
					</view>
				</view>
				<view class="flex justify-center flex-col items-center"  @click="redirect({url:'/addon/home_service/store/pages/member/store_list'})">
					<view class="mb-[15rpx]">
						<image :src="img('/addon/home_service/technician/sehzhi.png')"
							class="block w-[46rpx] h-[36rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[24rpx] mb-[10rpx]">
						{{t('changeAccount')}}  
					</view>
				</view>
			</view>
		</view>
		<view class="bg-[#fff] mx-[30rpx] rounded-[25rpx]">
			<view class="bg-[var(--store-bg-one)] flex justify-around py-[40rpx] rounded-[30rpx]">
				<view class="flex flex-col items-center justify-center">
					<view class="text-[#fff] text-[26rpx]">
						{{t('todayMoney')}}
					</view>
					<view class="text-[#fff] text-[48rpx] font-bold my-[25rpx]">
						{{storeTodayInfo.total_commission}}
					</view>
					<view class="text-[#fff] text-[26rpx]" @click="redirect({url:'/addon/home_service/store/pages/store/account/list'})">
						<text>{{t('myAccount')}}</text>
						<text class="iconfont iconarrow-right text-[24rpx]"></text>
					</view>
				</view>
				<view class="flex flex-col items-center justify-center">
					<view class="text-[#fff] text-[26rpx]">
						{{t('orderFinshNumber')}}
					</view>
					<view class="text-[#fff] text-[48rpx] font-bold my-[25rpx]">
						{{storeTodayInfo.today_completed_count}}
					</view>
					<view class="text-[#fff] text-[26rpx]"  @click="redirect({url:'/addon/home_service/store/pages/store/account/order_statistics'})">
						<text>{{t('orderNumber')}}</text>
						<text class="iconfont iconarrow-right text-[24rpx]"></text>
					</view>
				</view>
			</view>
			<view class="flex justify-around bg-[#fff] rounded-[25rpx] p-[30rpx]">
				<view class="flex items-center"  @click="redirect({url:'/addon/home_service/store/pages/member/feedback/feedback'})">

					<view class="">
						<image :src="img('/addon/home_service/technician/shensu.png')"
							class="block w-[30rpx] h-[30rpx] mr-[10rpx]" mode="aspectFit"></image>
					</view>
					<view class="text-[26rpx]">
						{{t('ViolationAppeal')}}
					</view>
				</view>
				<view class="flex items-center" @click="redirect({url:'/addon/home_service/store/pages/member/evaluate'})">
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
		<!-- <view class="m-[30rpx] flex justify-between">
			<view class="flex items-center bg-[#fff] p-[20rpx] py-[25rpx] rounded-[15rpx] flex-1 mr-[15rpx]">
				<view class="mr-[15rpx]">
					<image :src="img('/addon/home_service/technician/waitservice.png')"
						class="block w-[88rpx] h-[88rpx] rounded-[15rpx]" mode="aspectFit"></image>
				</view>
				<view class="">
					<view class="text-[28rpx] font-Medium">
						{{t('paiOrder')}}
					</view>
					<view class="text-[24rpx] text-[#999] mt-[15rpx]">
						{{t('paiOrderDesc')}}
					</view>
				</view>
			</view>
			<view class="flex items-center bg-[#fff] p-[20rpx] py-[25rpx] rounded-[15rpx] flex-1 ml-[15rpx]">
				<view class="mr-[15rpx]">
					<image :src="img('/addon/home_service/technician/waitservice.png')"
						class="block w-[88rpx] h-[88rpx] rounded-[15rpx]" mode="aspectFit"></image>
				</view>
				<view class="">
					<view class="text-[28rpx] font-Medium">
						{{t('workTime')}}
					</view>
					<view class="text-[24rpx] text-[#999] mt-[15rpx]">
						{{t('levalWork')}}
					</view>
				</view>
			</view>
		</view> -->

		<view class="mx-[30rpx] p-[30rpx] bg-[#fff] mt-[30rpx] rounded-[20rpx]">
			<view class="text-[30rpx] flex justify-between items-center">
				<view class="">
					{{t('workTools')}}
				</view>
				<!-- <view class="flex items-center justify-center">
					<image :src="img('/addon/home_service/store/date.png')" class="block w-[26rpx] h-[26rpx] mr-[5rpx]"
						mode="aspectFit"></image>
					<text class="text-[24rpx]">{{t('date')}}</text>
				</view> -->
			</view>
			<!-- 订单看板 -->
			<view class="flex flex-wrap">
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'wait_dispatch'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.wait_dispatch_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('daiPaiOrder')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'wait_service'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.wait_service_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('daiServices')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'in_progress'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.in_service_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('inServices')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'wait_check'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.wait_check_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('daiYanshou')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'finish'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.finish_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('finshOrder')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'refund'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.refund_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('refunedOrder')}}
					</view>
				</view>
				<view class="flex flex-col items-center justify-center w-[25%] mt-[50rpx]" @click="redirect({url:'/addon/home_service/store/pages/index',param:{order_status:'close'}})">
					<view class="text-[40rpx]">
						{{orderdashboard.close_count || 0}}
					</view>
					<view class="text-[26rpx] mt-[18rpx]">
						{{t('cancelOrder')}}
					</view>
				</view>
			</view>
		</view>

		<view class="mx-[30rpx] p-[30rpx] bg-[#fff] mt-[30rpx] rounded-[20rpx]">
			<view class="text-[30rpx] flex justify-between items-center">
				<view class="">
					{{t('technianStatus')}}
				</view>
				<view class="flex items-center justify-center" @click="getTechnicianDynamicFn">
					<image :src="img('/addon/home_service/store/index-shuaxin.png')"
						class="block w-[26rpx] h-[26rpx] mr-[8rpx]" mode="aspectFit"></image>
					<text class="text-[24rpx] text-[#999]">{{t('resetEcharts')}}</text>
				</view>
			</view>
			<!-- 调整为flex布局，图表占一半宽度 -->
			<view class="charts-container flex pt-[30rpx] ">
				<!-- uCharts 环形图组件（占一半宽度） -->
				<view class="w-[50%] flex h-[350rpx] flex-col justify-center items-center mr-[30rpx]">
					<qiun-data-charts class="ring-chart" type="ring" :opts="chartOpts" :chartData="chartData"
						:canvas2d="true" />
					<view class="chart-text">
						技师状态分布
					</view>
				</view>
				<!-- 右侧其他内容区域（留空，供后续添加） -->
				<view class="w-1/2 h-[350rpx] flex flex-col justify-around">
					<view class="flex items-center justify-between bg-[#F6FBFF] p-[20rpx] py-[30rpx] rounded-[10rpx]">
						<view class="flex items-center">
							<view class="w-[20rpx] h-[20rpx] bg-[#006DFF]">
							</view>
							<view class="text-[28rpx] ml-[10rpx]">
								服务中
							</view>
						</view>
						<view class="text-[34rpx] font-bold">
							{{technicianDynamicInfo.in_service_count}}
						</view>
					</view>
					<view class="flex items-center justify-between bg-[#FFF9F4]  py-[30rpx] p-[20rpx] rounded-[10rpx]">
						<view class="flex items-center">
							<view class="w-[20rpx] h-[20rpx] bg-[#F87122]">
							</view>
							<view class="text-[28rpx] ml-[10rpx]">
								空闲
							</view>
						</view>
						<view class="text-[34rpx] font-bold">
							{{technicianDynamicInfo.free_count}}
						</view>
					</view>
					<view class="flex items-center justify-between bg-[#F5FFFF]  py-[30rpx] p-[20rpx] rounded-[10rpx]">
						<view class="flex items-center">
							<view class="w-[20rpx] h-[20rpx] bg-[#36CFC9]">
							</view>
							<view class="text-[28rpx] ml-[10rpx]">
								离线
							</view>
						</view>
						<view class="text-[34rpx] font-bold">
							{{technicianDynamicInfo.offline_count}}
						</view>
					</view>
				</view>
			</view>
		</view>
	</view>
	<loading-page :loading="loading"></loading-page>
	<tabbar :value="1"></tabbar>
</template>

<script setup lang="ts">
	import { img, redirect, getToken } from '@/utils/common'
	import tabbar from '@/addon/home_service/store/components/tabbar/tabbar'
	import { getStoreInfo ,getStoreTodayInfo,getStoreOrderdashboard} from '@/addon/home_service/store/api/store'
	import qiunDataCharts from '@/addon/home_service/store/components/qiun-data-charts/components/qiun-data-charts/qiun-data-charts'
	import { getTechnicianDynamic } from '@/addon/home_service/store/api/member'
	import { ref, computed, onMounted } from 'vue'
	import { onLoad } from '@dcloudio/uni-app'
	import { t } from '@/locale'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '机构首页', topStatusBar: { textColor: '#333' ,rollBgColor:'#ffffff'}})
	const loading = ref<boolean>(true);
	const list5 = ref([
		img('/addon/home_service/technician/sehzhi.png'),
		img('/addon/home_service/technician/sehzhi.png'),
		img('/addon/home_service/technician/sehzhi.png'),
	])
	// 定义数据类型
	interface RingData {
		series : {
			data : Array<{
				name : string;
				value : number; // 数值决定该段的厚度占比
			}>;
		}[];
	}
	const technicianDynamicInfo = ref({})
	const getTechnicianDynamicFn = () =>{
		loading.value = true
		getTechnicianDynamic().then((res)=>{
			loading.value = false
			technicianDynamicInfo.value = res.data
			setTimeout(() => {
				const mockData = {
					series: [
						{
							data: [
								{ name: '在线', value: technicianDynamicInfo.value.in_service_count },
								{ name: '空闲', value: technicianDynamicInfo.value.free_count  },   
								{ name: '离线', value: technicianDynamicInfo.value.offline_count  }    
							]
						}
					]
				};
				chartData.value = JSON.parse(JSON.stringify(mockData)) as ChartData;
				chartOpts.value.subtitle.name = technicianDynamicInfo.value.total
			}, 500);
		}).catch((err)=>{
			loading.value = false
		})
	}
	const storeInfo = ref({})
	const getStoreInfoFn = () =>{
		getStoreInfo().then((res)=>{
			storeInfo.value = res.data
		})
	}
	const storeTodayInfo = ref({})
	const getStoreTodayInfoFn = () =>{
		getStoreTodayInfo().then((res)=>{
			storeTodayInfo.value = res.data
		})
	}
	
	const orderdashboard = ref({})
	const getStoreOrderdashboardFn = () =>{
		loading.value = true
		getStoreOrderdashboard().then((res)=>{
			loading.value = false
			orderdashboard.value = res.data
		}).catch((err)=>{
			loading.value = false
		})
	}
	
	
	onLoad(()=>{
		getStoreInfoFn()
		getStoreTodayInfoFn()
		getStoreOrderdashboardFn()
		getTechnicianDynamicFn()
	})
	// 定义图表配置类型
	interface RingOptions {
		color : string[]; // 三个颜色
		dataLabel : boolean; // 隐藏各段的数值标签
		legend : {
			show : boolean; // 隐藏右侧图例
		};
		title : {
			show : false; // 隐藏标题
		};
		subtitle : {
			show : false; // 隐藏副标题
		};
		extra : {
			ring : {
				ringWidth : number; // 环形总厚度
				radius : number; // 环形外半径
				innerRadius : number; // 环形内半径（控制中空大小）
				labelWidth : number;
				border : false; // 去除边框
			};
		};
		// 中心文字配置
		centerLabel : {
			show : false;
			text : string; // 中心数字
			fontSize : number;
			color : string;
			fontWeight : string;
		};
	}
	// 图表数据 - 三个数值决定各段厚度占比（总和为94）
	const chartData = ref<RingData>({
		series: [
			{
				data: []
			}
		]
	});

	// 环形图配置
	const chartOpts = ref<RingOptions>({
		color: ['#2F80ED', '#FF7D00', '#36CFC9'], // 蓝、橙、绿三色分段
		dataLabel: false, // 不显示各段的数值标签
		legend: {
			show: false // 完全隐藏右侧文字
		},
		title: {
			show: false
		},
		subtitle: {
			name:technicianDynamicInfo.value.total,
			color:"#000"
		},
		extra: {
			ring: {
				ringWidth:30, // 环形总厚度（数值越大越厚）
				radius: 0.7,   // 调整外半径，适配小程序
				innerRadius: 0.5, // 内半径（控制中空大小）
				labelWidth: 0,
				border: false
			}
		},
		centerLabel: {
		    show: true, // 必须开启，否则中间文字不显示
		    text: '33', // 动态绑定响应式变量
		    fontSize: 28, // 增大字体大小，适配小程序
		    color: '#333', // 文字颜色
		    fontWeight: 'bold', // 字体加粗
		    lineHeight: 40 // 行高（控制换行后的间距，避免文字重叠）
		  },
	});
	onMounted(() => {
		getTechnicianDynamicFn()
	});
</script>

<style>
	page {
		background-color: #f6f6f6;
	}

	/deep/ .uni-swiper-dot {
		width: 8rpx;
		height: 8rpx;
		margin-right: 5rpx;
	}

	/deep/ .uni-swiper-dot-active {
		width: 20rpx;
		height: 8rpx;
		border-radius: 10rpx;
		background-color: var(--store-bg-one);
	}

	.charts-container {
	}

	/* 环形图容器尺寸（适配一半宽度父容器） */
	.ring-chart {
		width: 100%;
		height: 280rpx !important; /* 增大高度，确保在小程序端正常显示 */
	}
	
	/* 小程序端特定样式适配 */
	/* #ifdef MP-WEIXIN */
	.charts-container {
		height: 380rpx; /* 增加整体图表容器高度 */
	}
	
	/* 调整订单看板数字大小 */
	.flex.flex-col.items-center.justify-center.w-\[25\%\] .text-\[40rpx\] {
		font-size: 44rpx;
		font-weight: bold;
	}
	
	/* 调整头部信息字体大小 */
	.bg-\[var\(--store-bg-one\)\] .text-\[48rpx\].font-bold {
		font-size: 52rpx;
	}
	/* #endif */

	/* 下方说明文字 */
	.chart-text {
		margin-top: 20rpx;
		font-size: 28rpx;
		color: #666;
	}
</style>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>