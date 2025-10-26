<template>
	<u-navbar title="师傅派单" bgColor="#ffffff" leftIconSize="15px" autoBack placeholder>
	</u-navbar>
	<u-sticky bgColor="#f6f6f6">
		<u-tabs :list="categoryList" :current="currentValue" keyName="category_name" @change="changeTabs" lineColor="#FBD700"></u-tabs>
	</u-sticky>
	<u-picker :show="statusShow" :columns="statusList" keyName="label" @confirm="statusConfrim"
		@cancel="statusShow = false"></u-picker>
	<view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden component-class" :style="themeColor()">
		<mescroll-body ref="mescrollRef" :down="{ use: false }" top="20" @init="mescrollInit"
			@up="getTechnicianListFn">
			<view class="mt-2 mx-[25rpx] " v-if="technicianList.length">
				<!-- 师傅项 -->
				<view class="  mb-[25rpx] " v-for="(technician, index) in technicianList"
					:key="index" @click.stop="goDetail(technician.id)">
					<view class="flex w-[100%]">
						<view class="flex items-center mr-[25rpx] w-[28rpx]" @click.stop="changeChoose(index)">
							<u-icon name="checkmark-circle-fill" v-if="technician.chooseStatus" color="#FBD700"></u-icon>
							<view class="w-[23rpx] h-[23rpx] border-style rounded-[50%]" v-else></view>
						</view>
						<view class="flex bg-white p-[24rpx] rounded-lg flex-1 box-border">
							<!-- 头像和状态区域 - 调整状态标签位置使其覆盖头像下半部分 -->
							<view class="relative h-[160rpx] mr-3">
								<u--image :src="img(technician.headimg)" width="160rpx" height="160rpx" radius="100rpx"
									mode="aspectFill">
									<template #error>
										<image :src="img('static/resource/images/default_headimg.png')"
											class="w-20 h-20 rounded-full  border-2 border-white" mode="aspectFill"></image>
									</template>
								</u--image>
								<text
									:class="technician.status == '1' ? 'text-xs bg-green-500 text-white' : 'text-xs bg-gray-400 text-white'"
									class="px-2.5 py-0.5 rounded-full absolute bottom-[0px] left-1/2 transform -translate-x-1/2 z-10 whitespace-nowrap">
									{{ technician.status == '1' ? t('working') : t('resting') }}
								</text>
							</view>
						
							<!-- 师傅信息 -->
							<view class="flex-1">
								<view class="flex items-center justify-between">
									<view class="flex items-center">
										<text
											class="text-[30rpx] font-bold truncate max-w-[150rpx] mr-[15rpx]">{{ technician.real_name }}
										</text><text class="text-[24rpx] text-[#666]">{{technician.mobile}}</text>
									</view>
									<!-- 五角星和评分水平排列 -->
									<view class="flex items-center">
										<u-icon name="star-fill" size="15" color="#FF3502" class="mr-0.5"></u-icon>
										<text
											class="text-[#FF3502] text-[26rpx] leading-5 font-bold">{{ technician.evaluate_avg_scores }}</text>
									</view>
								</view>
						
								<text class="text-[24rpx] text-[#999] mt-1 block truncate max-w-[400rpx]">
									地址：{{ technician.full_address }}
								</text>
						
								<view class="flex flex-wrap mt-[15rpx] ">
									<!-- 技能标签背景颜色保持为#FFFAD9 -->
									<view class="relative z-10 flex flex-wrap">
										<text v-for="(skill, idx) in technician.category_name"
											class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
											v-show="idx < 2">{{ skill.category_name }}</text>
										<text v-if="technician.category_name.length >= 3 && !technician.showMoreTag"
											@click.stop="technician.showMoreTag = true"
											class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]">...</text>
										<text v-for="(skill, idx) in technician.category_name"
											class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx]"
											v-show="idx >= 2 && technician.showMoreTag">{{ skill.category_name }}</text>
										<text v-if="technician.showMoreTag" @click.stop="technician.showMoreTag = false"
											class="text-[24rpx] bg-[#FFFAD9] mt-[15rpx] text-gray-600 px-[20rpx] py-[8rpx] pb-[10rpx] rounded-[6rpx] mr-[15rpx] flex items-center leading-1"><text
												class="iconfont iconshangV6xx-1"></text></text>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
			<view class="pl-[20rpx] pt-[0rpx]" style="width: calc(100% - 182rpx)">
				<mescroll-empty :option="{ icon: img('static/resource/images/empty.png'), tip: t('nothingMore') }"
					v-if="!technicianList.length && !loading" class="part"></mescroll-empty>
			</view>
		</mescroll-body>
	</view>
	
	<view class="w-full footer bg-[#fff]">
		<view
			class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full bg-[#fff] fixed bottom-0 left-0 right-0 box-border flex">
			<button hover-class="none"
				class="!bg-[var(--store-bg-one)] !text-[#fff] w-full mr-[25rpx] !bg-[#f6f6f6] h-[80rpx] !text-[#111] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
				@click="distpachConfirm" 
				:class="{'opacity-50': btnDisabled}">取消
			</button>
			<button hover-class="none"
				class="!bg-[var(--store-bg-one)] !text-[#fff] w-full !bg-[#00CB7A] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
				@click.stop="handleOrderAction({}, 'dispatch')"
				:class="{'opacity-50': btnDisabled}">确定
			</button>
		</view>
	</view>
	
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref } from 'vue'
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common'
	import tabbar from '@/addon/home_service/store/components/tabbar/tabbar'
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
	import { onPageScroll, onReachBottom,onLoad } from '@dcloudio/uni-app';
	import OrderMethods from '@/addon/home_service/store/pages/order/js/OrderMethods';
	import { getTechnicianOrderList,transferOrder } from '@/addon/home_service/store/api/order'
	import { getTechnicianStatus,getCategoryList } from '@/addon/home_service/store/api/technician'
	const getCategoryListFn = (id:any) => {
		getCategoryList().then((res) => {
			res.data.forEach((item,index)=>{
				if(item.category_id == id){
					currentValue.value = index
				}else{
					item.disabled = true
				}
				
			})
			categoryList.value = res.data
		})
	}
	
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	const statusList = ref([[{ label: '全部', value: '' }]])
	const statusShow = ref(false)
	const statusValue = ref('')
	const statusConfrim = (e : any) => {
		statusValue.value = e.value[0].value
		statusShow.value = false
		getMescroll().resetUpScroll()
	}
	const searchName = ref("");
	const categoryId = ref('')
	const loading = ref<boolean>(true);
	const categoryList = ref([])
	let orderId = 0
	const orderKey= ref('')
	let reserve_service_time_stamp = 0
	const currentValue = ref(0)
	onLoad((option : any) => {
		orderId = option.order_id || 0
		reserve_service_time_stamp = option.reserve_service_time_stamp || 0
		categoryId.value = option.category_id
		orderKey.value = option.key
		getCategoryListFn(option.category_id)
	})
	// 处理订单按钮点击
	const handleOrderAction = (order : any, key : string) => {
		if(orderKey.value == 'action_transfer'){
			key = 'transfer'
		}
		technicianList.value.forEach((item,index)=>{
			if(item.chooseStatus){
				order = item
				order.order_id = orderId
			}
		})
		OrderMethods.orderClickFunction(
			order,
			key,
			() => distpachConfirm(), // 刷新列表的回调
		);
	};
	const distpachConfirm = () =>{
		redirect({url:'/addon/home_service/store/pages/index'})
	}
	const searchTechnican = () => {
		getMescroll().resetUpScroll()
	}
	const getTechnicianStatusFn = () => {
		getTechnicianStatus().then((res) => {
			Object.keys(res.data).forEach((item, index) => {
				let obj = {
					label: res.data[item],
					value: item
				}
				statusList.value[0].push(obj)
			})
		})
	}
	const changeChoose = (e: number) => {
	  technicianList.value = technicianList.value.map((item, index) => ({
	    ...item,
	    chooseStatus: index === e
	  }));
	};
	getTechnicianStatusFn()
	const changeTabs = (e : any) => {
		categoryId.value = e.category_id
		// getMescroll().resetUpScroll()
	}
	const technicianList = ref<Array<Object>>([]);
	const getTechnicianListFn = (mescroll) => {
		loading.value = true;
		let data : object = {
			page: mescroll.num,
			limit: mescroll.size,
			reserve_service_time_stamp: reserve_service_time_stamp,
			category_id:categoryId.value 
		}
		getTechnicianOrderList(data).then((res) => {
			let newArr = (res.data.data as Array<Object>);
			//设置列表数据
			if (mescroll.num == 1) {
				technicianList.value = []; //如果是第一页需手动制空列表
			}
			technicianList.value = technicianList.value.concat(newArr);
			if (technicianList.value.length) {
				technicianList.value.forEach((item, index) => {
					item.showMoreTag = false
				})
			}
			mescroll.endSuccess(newArr.length);
			loading.value = false;
		}).catch(() => {
			loading.value = false;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}
	// 去派单按钮点击事件
	const goAssignOrder = (technicianId : number) => {
		// 实现去派单逻辑
		uni.showToast({
			title: `去派单给师傅${technicianId}`,
			icon: 'none'
		})
	}
	const goDetail = (id : number) => {
		redirect({
			url: '/addon/home_service/store/pages/technician/detail',
			param: {
				technician_id: id
			}
		})
	}
	// 排班管理按钮点击事件
	const goScheduleManagement = (technicianId : number) => {
		redirect({
			url: '/addon/home_service/store/pages/technician/rest',
			param: {
				technician_id: technicianId
			}
		})
	}

	// 打电话按钮点击事件
	const makeCall = (phone : string) => {
		// 实现打电话逻辑
		uni.makePhoneCall({
			phoneNumber: phone
		})
	}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
	/* 页面样式 */
	page {
		padding-bottom: 60px;
		/* 为底部导航栏留出空间 */
	}

	/* 安全区域适配 */
	.body-bottom {
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}
	.border-style{
		border: 2rpx solid #a2a2a2;
	}
	// 底部安全区域适配
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>