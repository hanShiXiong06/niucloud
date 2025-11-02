<template>
	<view :style="themeColor()" >
		<template v-if="!loading">
			<view v-if="detail" class="bg-[#f7f7f7] min-h-screen overflow-hidden body-bottom">
				<view class="bg-linear text-white h-[300rpx] px-3 pt-5" :style="{paddingTop:navHeight + 'px'}">
					<!-- #ifdef MP-WEIXIN || APP-PLUS -->
					<u-navbar :leftIconColor="scrollTop >= 10 ? '#111' : '#fff'" :titleStyle="{ color:scrollTop >= 10 ? '#111' : '#fff'}" title="订单详情"  :bgColor="scrollTop >= 10 ? '#fff' : 'transparent'"   @leftClick="rightClick">
					</u-navbar>
					 <!-- #endif -->
					 <!-- #ifdef MP-WEIXIN -->
					 <view class="h-[20rpx] w-[100vw]]"></view>
					 <!-- #endif -->
					<view class="text-[34rpx] flex items-baseline text-[#fff] my-[10rpx]">
						<text class="font-bold">{{ t('order') }}{{ detail.order_status_info?.name }}</text>
					</view>
					<view class="text-[26rpx] mt-[25rpx]" v-if="detail.order_status_info?.status == 'wait_pay'"><text
							class="nc-iconfont nc-icon-a-shijianV6xx-36 mr-1 text-[26rpx]"></text><text>请于{{timeStampTurnTime(detail.auto_close_time)}}前完成付款</text>
					</view>
					<view class="text-[26rpx] mt-[25rpx] flex justify-between items-center" @click="showImage = true"
						v-if="detail.check_photos && detail.check_photos?.length"><text
							class="">技师服务完成时间:{{detail.service_finish_time}}</text><text
							class="border-style px-[10rpx] text-[24rpx] py-[5rpx] rounded-[5rpx]"
							>查看完成照片</text></view>
					<u-steps inactiveColor="#b0b0b0" activeColor="#ffffff" class="my-[25rpx] w-[100vw] ml-[-20rpx]"
						v-if="detail.order_status_info?.status == 'in_service'" :current="stepsStatus"
						activeIcon="checkmark" inactiveIcon="arrow-right">
						<u-steps-item :title="item.text" v-for="(item,index) in detail.core_process"
							:key="index"></u-steps-item>
					</u-steps>
				</view>
				<view class=""
					:style="{
					  marginTop: detail.order_status_info?.status == 'in_service' && detail?.order_log && detail?.order_log.length 
					    ? '-135rpx' 
					    : (detail?.order_status_info?.status == 'wait_pay' || detail?.order_status_info?.status == 'finish' || detail?.order_status_info?.status == 'wait_check' 
					      ? (detail?.check_photos && detail?.check_photos?.length ? '-180rpx' : '-200rpx')
					      : '-250rpx')
					}">
					<view class="bg-[#fff] mx-3 px-[25rpx] py-[15rpx] mt-[20rpx] rounded-lg mb-[25rpx]"
						v-if="detail.add_item_list && detail.add_item_list?.length">
						<view class="">
							<view class="flex justify-between items-center">
								<view>
									<text class="iconfont iconjishibenV6xx text-[26rpx] mr-[5rpx]">
									</text>
									<text class="text-[28rpx] font-bold mt-[10rpx] leading-[5rpx] line-feed">
										技师附加服务提交
									</text>
								</view>
							</view>
							<view class="text-[#999999] text-[26rpx] mt-[15rpx]">
								{{detail.pay_time}}
							</view>
						</view>
					</view>
					<view class="bg-[#fff] mx-3 px-[25rpx] py-[15rpx] rounded-lg mb-[25rpx]"
						v-if="detail.order_status_info?.status == 'wait_service' && detail.take_photos">
						<view class="">
							<view class="flex justify-between items-center">
								<view>
									<text class="iconfont icona-bijiPC30 text-[26rpx] mr-[5rpx]">
									</text>
									<text class="text-[28rpx] font-bold mt-[10rpx] leading-[5rpx] line-feed">
										服务师傅已到达目的地
									</text>
								</view>
								<view v-if="detail.take_photos" @click="showCardImage = true">
									<view 
									class="border-style  text-[24rpx] py-[5rpx] rounded-[5rpx] text-[var(--primary-color)] flex items-center"
									>查看打卡照片
									<u-icon name="arrow-right" size="13" color="var(--primary-color)"></u-icon>
									</view>
								</view>
							</view>
							<view class="text-[#999999] text-[26rpx] mt-[15rpx]">
								{{detail.take_photos_time}}
							</view>
						</view>
					</view>
					<view class="bg-[#fff] mx-3 px-[25rpx] py-[15rpx] rounded-lg mb-[25rpx]"
						v-if="detail.order_status_info?.status == 'wait_service' && detail.depart_time && !detail.take_photos">
						<view class="">
							<view class="flex justify-between items-center">
								<view>
									<text class="iconfont icondache text-[26rpx] mr-[5rpx]">
									</text>
									<text class="text-[28rpx] font-bold mt-[10rpx] leading-[5rpx] line-feed">
										服务师傅已出发
									</text>
								</view>
							</view>
							<view class="text-[#999999] text-[26rpx] mt-[15rpx]">
								{{timeStampTurnTime(detail.depart_time)}}
							</view>
						</view>
					</view>
					
					<!-- <view class="bg-[#fff] mx-3 px-[25rpx] py-[15rpx] rounded-lg mb-[25rpx]" v-if="detail.order_status_info.status == 'wait_service' ">
						<view class="">
							<view>
								<text class="iconfont icondache text-[26rpx] mr-[5rpx]">
								</text>
								<text class="text-[28rpx] font-bold mt-[10rpx] leading-[5rpx] line-feed">
									服务人员已确定出发
								</text>
							</view>
							<view class="text-[#999999] text-[26rpx] mt-[15rpx]">
								{{detail.reserve_service_time}}
							</view>
						</view>
					</view> -->
					<view class="bg-[#fff] mx-3 px-[25rpx] py-[15rpx] rounded-lg mt-[20rpx]">
						<view class="flex mb-[25rpx]">
							<view class="flex flex-col">
								<view class="flex items-center">
									<u-icon name="map" size="19" class="mt-[8rpx] mr-[5rpx]"></u-icon>
									<text
										class="text-[28rpx] mt-[10rpx]  line-feed multi-hidden leading-[1.5]">{{ detail.taker_full_address }}{{detail.taker_address}}</text>
								</view>
								<view class="text-[#666666] ml-[40rpx]">
									<text class="text-[24rpx]">{{ detail.taker_name }}</text>
									<text class="text-[24rpx] mt-[15rpx pl-[10rpx]">{{ detail.taker_mobile }}</text>
								</view>
							</view>
						</view>
						<view class="flex justify-between mt-[15rpx] items-center">
							<view class="flex items-center">
								<u-icon name="clock" size="18" class=" mr-[5rpx]"></u-icon>
								<text class="text-[28rpx] leading-[1.5] line-feed">
									服务时间
								</text>
							</view>
							<view class="text-[28rpx] leading-[1.5]">
								{{detail.reserve_service_time}}
							</view>
						</view>
					</view>
				</view>
				<view class="flex items-center m-[25rpx] justify-between p-[25rpx] bg-[#fff] rounded-lg"
					v-if="detail.technician?.real_name">
					<view class="flex items-center">
						<view>
							<u--image :src="img(detail.technician?.headimg)" width="50rpx" height="50rpx"
								radius="100rpx" mode="aspectFill">
								<template #error>
									<image :src="img('static/resource/images/default_headimg.png')"
										class="w-[50rpx] h-[50rpx] rounded-full  border-2 border-white"
										mode="aspectFill">
									</image>
								</template>
							</u--image>
						</view>
						<view class="mx-[20rpx] text-[28rpx]">
							{{detail.technician?.real_name}}
						</view>
						<view class="text-[28rpx]">
							{{maskPhone(detail.technician?.mobile)}}
						</view>
					</view>
					<view @click="callPhone(detail?.taker_mobile)" v-if="detail.order_status_info?.status != 'finish'">
						<image :src="img('/addon/home_service/technician/call-phone.png')" class="w-[25rpx]"
							mode="widthFix"></image>
					</view>
				</view>
				<view class="m-[25rpx] p-[25rpx] bg-[#fff] rounded-lg">
					<view class="order-goods-item flex"  v-if="detail.item?.length"
						>
						<view class="w-[160rpx] h-[160rpx] flex-2" @click="toDetail(detail.item[0])">
							<up-image class="rounded-[10rpx] overflow-hidden" width="160rpx" height="160rpx"
								:src="img( detail.item[0]?.item_image_thumb_small ? detail.item[0]?.item_image_thumb_small : '')"
								model="aspectFill" shape="radius" radius="16rpx">
								<template #error>
									<u-icon name="photo" color="#999" size="50"></u-icon>
								</template>
							</up-image>
						</view>
						<view class="ml-[20rpx] flex flex-1 flex-col justify-between" @click="toDetail( detail.item[0])">
							<view class="flex justify-between items-center">
								<text
									class="text-[28rpx] text-item  leading-[40rpx] max-h-[80rpx] w-[360rpx] multi-hidden">{{  detail.item[0]?.item_name }}</text>
								<text class="text-right text-[24rpx]">x{{  detail.item[0]?.num }}</text>
							</view>
							<view class="text-[#999999] text-[24rpx]">{{ detail.item[0]?.sku_name}}</view>
							<view class="text-[var(--price-text-color)] text-[28rpx] font-bold flex items-end">
								<text class="text-[24rpx] price-font">￥</text>
								<text class="price-font text-[34rpx] leading-[1]">{{ Number(detail.item[0]?.price).toString().split('.')[0] }}</text>
								<text
									class="price-font text-[24rpx]">.{{ Number(detail.item[0]?.price).toFixed(2).split('.')[1] }}</text>
							</view>
						</view>
					</view>
					<view class="flex justify-between mt-[25rpx] items-center">
						<view class="text-[#666666] text-[26rpx] leading-[35rpx]">订单留言</view>
						<view class="flex-1 pl-[25rpx] text-[26rpx] leading-[35rpx] text-right">
							{{detail.member_message || '暂无留言'}}
						</view>
					</view>
				</view>
				<view class="mt-[30rpx]" v-if="detail.add_item_list && detail.add_item_list?.length">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">附加服务订单</view>
							<!-- <view class="flex justify-between items-end text-[26rpx]">
								<view class="text-[22rpx] mb-[4rpx] price-font">
									{{ t('realMoney') }}
								</view>
								<view
									class="text-[32rpx] font-bold leading-[35rpx] text-[var(--price-text-color)] price-font">
									<text class="!text-[22rpx]">￥</text>{{ detail.pay_money }}
								</view>
							</view> -->
						</view>
						<view class="bg-[#F9F9F9] px-[25rpx] py-[20rpx] mt-[25rpx] rounded-lg mb-[25rpx]"
							v-for="(item,index) in detail.add_item_list">
							<view class="flex justify-between mb-[15rpx]">
								<view class="font-bold text-[26rpx]">订单{{index + 1}}</view>
								<view class="text-[26rpx]">{{item.is_pay}}</view>
							</view>
							<view class="flex mt-[15rpx] justify-between" v-if="item.item_list && item.item_list?.length > 0">
								<view class="w-[77%] ">
									<u-scroll-list :indicator="false">
										<view v-for="(items, indexs) in item.item_list" :key="indexs"
											:indicator="false">
											<up-image class="rounded-[10rpx] overflow-hidden mr-[15rpx]" width="110rpx"
												height="110rpx" :src="img(items.item_image ? items.item_image : '')"
												model="aspectFill" shape="radius" radius="16rpx">
												<template #error>
													<u-icon name="photo" color="#999" size="50"></u-icon>
												</template>
											</up-image>
										</view>
									</u-scroll-list>
								</view>
								<view class="w-[20%] flex flex-col  justify-center items-center pb-[20px]">
									<view class="text-right w-[100%]">
										<text class="text-[22rpx]">￥</text>
										<text
											class="text-[28rpx] text-[32rpx] price-font">{{item.item_money}}</text>
									</view>
									<view class="text-[26rpx] text-right w-[100%] text-[#666666]">
										共{{item.item_count}}件
									</view>
								</view>
							</view>
							<view class="flex justify-between items-center mb-[10rpx]" v-if="item.service_fee && Number(item.service_fee)">
								<view class="text-[26rpx]">
									附加服务费
								</view>
								<view>
									<text class="text-[22rpx]">￥</text>
									<text
										class="text-[28rpx] text-[30rpx]  price-font">{{item.service_fee}}</text>
								</view>
							</view>
							<view class="flex justify-between items-center">
								<view class="text-[26rpx] text-[#999]">
									{{item.create_time}}
								</view>
								
								<view class="text-[28rpx]  flex items-end">
									<text class="text-[24rpx] leading-[1.2] pr-[4rpx]"> {{ item.is_pay=='待付款'?'应付款':'实付款' }} </text>
									<text class="text-[22rpx] leading-[1.1] price-font text-[var(--price-text-color)] leading-[1]">￥</text>
									<text class="price-font text-[34rpx] !leading-[1] text-[var(--price-text-color)] ">{{ Number(item?.total_money).toString().split('.')[0] }}</text>
									<text
										class="price-font text-[24rpx] leading-[1.1] text-[var(--price-text-color)] ">.{{ Number(item?.total_money).toFixed(2).split('.')[1] }}</text>
									<text v-if="detail.sku_unit">/{{ detail.sku_unit }}</text>
								</view>
								
								
								<!-- <view>
									<text class="text-[24rpx]"> {{ item.is_pay=='待付款'?'应付款':'实付款' }} </text>
									<text class="text-[24rpx]">￥</text>
									<text
										class="text-[28rpx] text-[32rpx] text-[var(--price-text-color)] price-font">{{item.total_money}}</text>
								</view> -->
							</view>
						</view>
					</view>
				</view>


				<view class="mt-[30rpx]">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="flex justify-between">
							<view class="text-[30rpx] font-bold">订单明细</view>
						</view>
						<view
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>服务费</view>
							<view class="price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail?.item?.[0]?.item_money || '0.00' }}</view>
						</view>
						<view v-if="detail?.item?.[0]?.discount_money && Number(detail?.item?.[0]?.discount_money)"
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>优惠金额</view>
							<view class="price-font"><text
									class="!text-[24rpx]">-￥</text>{{ detail?.item?.[0]?.discount_money || '0.00' }}</view>
						</view>
						<view
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>订单金额</view>
							<view class=" price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail.order_money }}
							</view>
						</view>
						<view
							class="flex justify-between text-[26rpx] pt-[30rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]">
							<view>实付金额</view>
							<view class=" text-[var(--price-text-color)] price-font"><text
									class="!text-[24rpx]">￥</text>{{ detail.pay_money }}
							</view>
						</view>
						
					</view>
				</view>

				<view class="mt-[30rpx]">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-lg">
						<view class="text-[30rpx] font-bold">
							其他信息
						</view>
						<view
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('onOrder') }}</view>
							<view class="flex items-center">{{ detail.order_no }} <text @click="copy(detail?.order_no)"
									class="text-[var(--primary-color)] text-[26rpx] ml-[10rpx]">复制</text></view>
						</view>
						<view
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('createTime') }}</view>
							<view>{{ detail.create_time }}</view>
						</view>
						<view v-if="detail.pay_time"
							class="flex justify-between text-[26rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[30rpx]">
							<view>{{ t('payTime') }}</view>
							<view>{{ detail.pay_time }}</view>
						</view>
					</view>
				</view>
				<view class="mt-[30rpx]" v-if="detail.guarantee_list && detail.guarantee_list?.length">
					<view class="bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-[10rpx]">
						<view class="text-[30rpx] font-bold">
							保障服务
						</view>
						<view @click="openServicesSafePopup" v-if="detail.guarantee_list?.length > 1"
							class="flex items-center h-[88rpx] px-[20rpx] mb-[20rpx]">
							<text class=" text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]">服务保障</text>
							<view class="flex-1 text-[#343434] text-sm leading-[42rpx] font-500 text-right mr-[10rpx] ">
								{{ detail.guarantee_list[0].guarantee_title }}
							</view>
							<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)]"></text>
						</view>
					</view>
				</view>
				<view class="h-[160rpx] w-full"></view>
				<view
					class="flex z-2 justify-end items-center bg-[#fff] fixed  py-[10rpx] left-0 right-0 bottom-0 min-h-[100rpx] px-1 flex-wrap pb-ios body-bottom">
					<view  :customStyle="{marginRight:'15rpx',marginLeft:'15rpx',width:'auto'}"
						size="" @click="handleOrderAction(detail, btnItem.key)"
						class="flex-1 text-[26rpx] justify-center border-1  border-solid border-[#999999] p-[30rpx] flex items-center leading-1 rounded-[5rpx] mb-[10rpx] !rounded-[10rpx] flex-1 px-[25rpx] !mx-[20rpx]"
						:style="{borderColor:btnItem.color,color:btnItem.color}" 
						v-for="(btnItem, btnIndex) in detail.order_status_info?.action"
						:key="btnIndex">{{btnItem.name}}</view>
				</view>

			</view>
			<view class="w-screen h-screen flex flex-col justify-center items-center" v-else>
				<u-empty :icon="img('static/resource/images/order_empty.png')" text="未获取到订单信息" />
			</view>
			<!-- 刷新 -->
			<view
				class="fixed bottom-[calc(160rpx+env(safe-area-inset-bottom))] right-[30rpx] rounded-full bg-[#fff] w-[80rpx] h-[80rpx] flex flex-col items-center justify-center shadow-xl"
				@click="getOrderDetailFu">
				<text class="nc-iconfont nc-icon-shuaxinV6xx text-[36rpx]"></text>
				<text class="text-[22rpx] mt-[6rpx]">{{ t('refresh') }}</text>
			</view>
		</template>
		<u-popup :show="showImage" @close="showImage = false" @open="showImage = true" mode="center" zIndex="9"
			round="15">
			<view class="text-[32rpx] font-bold text-center pt-[30rpx]">
				完成照片
			</view>
			<view class="px-[30rpx] py-[30rpx] pb-[40rpx]  grid grid-cols-4 gap-4 " v-if="detail?.check_photos && detail.check_photos?.length">
				<view v-for="(imageUrl, imgIndex) in detail.check_photos" :key="imgIndex" class=" rounded-lg">
					<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
						@click="imgListPreview(imageUrl, imgIndex)">
						<view slot="error" style="font-size: 24rpx;">{{ t('loadFailed') }}</view>
					</u--image>
				</view>
			</view>
		</u-popup>
		<u-popup :show="showCardImage" @close="showCardImage = false" @open="showCardImage = true" mode="center" zIndex="9"
			round="15">
			<view class="text-[32rpx] font-bold text-center pt-[30rpx]">
				打卡照片
			</view>
			<view class="px-[30rpx] py-[30rpx] pb-[40rpx]  grid grid-cols-4 gap-4 " v-if="detail?.take_photos">
				<view v-for="(imageUrl, imgIndex) in detail.take_photos.split(',')" :key="imgIndex" class=" rounded-lg">
					<u--image :src="img(imageUrl)" width="140rpx" height="140rpx" radius="10rpx"
						@click="imgListPreview(imageUrl, imgIndex)">
						<view slot="error" style="font-size: 24rpx;">{{ t('loadFailed') }}</view>
					</u--image>
				</view>
			</view>
		</u-popup>
		<u-popup :show="servicesSafePopupOpen" mode="bottom" :closeable="true" closeIconSize="40rpx"
			@close="servicesSafePopupOpen=false" :round="10">
			<view class=" bg-white rounded-[24rpx] p-[30rpx]">
				<view class=" text-center text-32rpx font-bold text-[#333333] mb-6">{{t('serviceSafe')}}</view>
				<view class="popup-body">
					<view class="service-item flex items-center p-[15rpx] bg-[#fcfcfc] rounded-xl mb-4"
						v-for="(item,index) in detail.goods?.guarantee_list">
						<view
							class="service-icon w-10 h-10 rounded-full flex items-center justify-center text-white text-28rpx mr-4">
							<image class="w-[44rpx] h-[44rpx]" :src="img(item.guarantee_image)"
								mode="aspectFill" />
						</view>
						<view class="service-text text-30rpx text-[#333333]">
							<view>
								{{item.guarantee_title}}
							</view>
							<view class="text-[24rpx] text-[#999] mt-[15rpx]">
								{{item.guarantee_content}}
							</view>
						</view>
					</view>
				</view>
			</view>
		</u-popup>
		
		<pay ref="payRef"></pay>
		<loading-page :loading="loading"></loading-page>
	</view>
</template>

<script setup lang="ts">
	import { ref,computed } from 'vue'
	import { onLoad, onShow,onPageScroll } from '@dcloudio/uni-app'
	import { img, redirect, timeStampTurnTime,copy,goback } from '@/utils/common'
	import OrderMethods from '@/addon/home_service/user/pages/order/js/orderMethods';
	import { getOrderDetail, cancelOrder, deleteOrder,getRefundNo } from '@/addon/home_service/user/api/order'
	import { t } from '@/locale'
	import useSystemStore from '@/stores/system';
	import { topTabar } from '@/utils/topTabbar';
	const scrollTop = ref(0)
	const servicesSafePopupOpen = ref(false)
	const openServicesSafePopup = () => {
		servicesSafePopupOpen.value = true
	}
	onPageScroll((e) => {
		scrollTop.value = e.scrollTop || 0
	})
	/********* 自定义头部 - start ***********/
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '订单详情' })
	/********* 自定义头部 - end ***********/
	
	const systemStore = useSystemStore()
	const menuButtonInfo = ref({});
	menuButtonInfo.value = systemStore.menuButtonInfo
	// 导航栏总高度（小程序：胶囊高度 + 胶囊top + 底部预留8px；H5：固定高度）
	const navHeight = computed(() => {
		// #ifdef MP-WEIXIN
		return (menuButtonInfo.value.height || 32) + (menuButtonInfo.value.top || 10);
		// #endif
		// #ifdef H5
		return 30; // H5：固定高度（可根据设计调整）
		// #endif
	});
	
	const showImage = ref(false)
	const showCardImage = ref(false)
	const showPayBtnStatus = ref(false)
	//预览图片
	const imgListPreview = (item : any, index : any) => {
		if (Array.isArray(item)) {
			if (!item.length) return false
			var urlList = item;
			uni.previewImage({
				indicator: "number",
				current: index,
				loop: true,
				urls: urlList
			})
		} else {
			if (item === '') return false
			var urlList = []
			urlList.push(img(item))  //push中的参数为 :src="item.img_url" 中的图片地址
			uni.previewImage({
				indicator: "number",
				loop: true,
				urls: urlList
			})
		}
	}

	// 处理订单按钮点击
	const handleOrderAction = (data : any, key : string) => {
		if(key == 'action_refund'){
			if(data.refund_status == "wait_refund"){
				getRefundNo(data.order_id).then((res : any) => {
					//  console.log(res.data.refund_id,'哈哈哈')
					 redirect({ url: '/addon/home_service/user/pages/order/refund/detail', param: { refund_no: res.data.refund_id } })
				})
				return
			}
		}
		if (key == 'action_item_pay' || key == 'action_pay' ) {
			if(key == 'action_item_pay' ){
				payRef.value?.open(batchType.value, data.wait_pay_batch_id, `/addon/home_service/user/pages/order/detail?order_id=${data.order_id}`);
			}else{
				payRef.value?.open(data.order_type, data.order_id, `/addon/home_service/user/pages/order/detail?order_id=${data.order_id}`);
			}
		} else {
			OrderMethods.orderClickFunction(
				data,
				key,
				() => getOrderDetailFu(), // 刷新列表的回调
			);
		}
	};
	import useConfigStore from '@/stores/config'
	const list = ref([])
	// let orderId = 0
	const detail = ref<AnyObject | null>({
		order_status_info: {
			status: '',
			name: '',
		},
		order_log: [],
		item: [],
		technician: {
			real_name: '',
			mobile: '',
			headimg: '',
		},
		taker_address: '',
		taker_full_address: '',
		taker_name: '',
		taker_mobile: '',
		reserve_service_time: '',
		depart_time: '',
		take_photos: '',
		check_photos: [],
		finish_time: '',
		take_photos_time: '',
		add_item_list:[]
	})
	const loading = ref(false)
	const orderId = ref(0)
	onLoad((option : any) => {
		orderId.value = option.order_id || 0
		console.log(option,'看看ID')
		getOrderDetailFu()
	})
	onShow(() => {
		if (orderId.value) {
			getOrderDetailFu()
		}
	})
	// 手机号脱敏
	const maskPhone = (phone : any) => {
		if (!/^1[3-9]\d{9}$/.test(phone)) {
			return phone;
		}
		return phone.replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2');
	}

	// 拨打电话
	const callPhone = (e : any) => {
		if (!e) return
		uni.makePhoneCall({
			phoneNumber: e,
		});
	}

	const batchType = ref('home_service_item')
	const stepsStatus = ref(0)
	// 订单详情
	const getOrderDetailFu = () => {
		loading.value = true
		getOrderDetail(orderId.value).then((res) => {
			detail.value = res.data
			if (detail.value.core_process && Object.keys(detail.value.core_process).length) {
				stepsStatus.value = -1
				Object.keys(detail.value.core_process).forEach((item, index) => {
					if (detail.value.core_process[item].time_value) {
						stepsStatus.value++
					}
				})
			}
			loading.value = false
			getStatus()
		}).catch(() => {
			loading.value = false
		})
	}
	// 支付
	const payRef = ref(null)
	const orderBtnFn = (type = '', data = '') => {
		if (type == 'pay') {
			payRef.value?.open(detail.value.order_type, detail.value.order_id, `/addon/home_service/pages/order/detail?order_id=${detail.value.order_id}`)
		} else if (type == 'item_pay') {
			payRef.value?.open('o2o_item', data.order_item_id, `/addon/home_service/pages/order/detail?order_id=${detail.value.order_id}`)
		} else if (type == 'cancel') {
			cancel(detail.value)
		} else if (type == 'delete') {
			deleteFn(detail.value)
		} else if (type == 'index') {
			redirect({
				url: '/addon/home_service/pages/index',
				mode: 'reLaunch'
			})
		}
	}
	const toDetail = (e : any) => {
		console.log(e)
		redirect({ url: '/addon/home_service/user/pages/goods/detail', param: { goods_id: e.goods_id } })
	}
	// 取消订单
	const cancel = (item : any) => {
		uni.showModal({
			title: '提示',
			content: '您确定要取消该订单吗？',
			confirmColor: useConfigStore().themeColor['--primary-color'],
			success: (res) => {
				if (res.confirm) {
					cancelOrder(item.order_id).then((res) => {
						getOrderDetailFu()
					})
				}
			}
		})
	}

	// 删除订单
	const deleteFn = (data : any) => {
		uni.showModal({
			title: '提示',
			content: '您确定要删除该订单吗？',
			confirmColor: useConfigStore().themeColor['--primary-color'],
			success: (res) => {
				if (res.confirm) {
					deleteOrder(data.order_id).then((res) => {
						redirect({ url: '/addon/home_service/pages/order/list' })
					})
				}
			}
		})
	}

	// 申请退款
	const refundApplyFn = (orderItemId) => {
		redirect({
			url: '/addon/home_service/pages/refund/apply',
			param: {
				order_id: detail.value.order_id,
				order_item_id: orderItemId
			}
		})
	}

	// 导航
	// const getAddress = () => {
	// 	uni.openLocation({
	// 		latitude: Number(detail.value.taker_latitude),
	// 		longitude: Number(detail.value.taker_longitude),
	// 		success: function () { }
	// 	});
	// }

	// 日期格式转成月日时分
	function dataTurnTime(timeStamp) {
		const time = new Date(timeStamp).getTime();
		if (time != undefined && time != '' && time > 0) {
			const date = new Date();
			date.setTime(time)
			let m = date.getMonth() + 1;
			m = m < 10 ? '0' + m : m
			let d = date.getDate();
			d = d < 10 ? '0' + d : d
			let h = date.getHours();
			h = h < 10 ? '0' + h : h
			let minute = date.getMinutes();
			minute = minute < 10 ? '0' + minute : minute
			return m + '-' + d + ' ' + h + ':' + minute
		} else {
			return ''
		}
	}

	// 判断当前步骤条的状态
	const current = ref(0)
	function getStatus() {
		if (detail.value.order_status_info?.status == 'dispatch') {
			return (current.value = 0)
		} else if (detail.value.order_status_info?.status == 'wait_service') {
			return (current.value = 1)
		} else if (detail.value.order_status_info?.status == 'in_service') {
			return (current.value = 2)
		} else if (detail.value.order_status_info?.status == 'finish') {
			return (current.value = 3)
		}
	}

	// 跳转项目详情
	const toLink = (data : any) => {
		if (data.item_type == 'reservation' || data.item_type == 'buy') {
			redirect({
				url: `/addon/home_service/pages/goods/detail`,
				param: {
					sku_id: data.item_id
				}
			})
		}
	}

	// 联系技师
	const callPhoto = (tel) => {
		uni.makePhoneCall({
			phoneNumber: tel
		})
	}

	// 查看服务项
	const showServiceFn = (data) => {
		redirect({
			url: '/addon/home_service/pages/master/task/show',
			param: { order_id: data.order_id, order_item_id: data.order_item_id, item_name: data.item_name, price: data.item_money, item_images: data.item_images }
		})
	}
	const rightClick =()=>{
		console.log('rightClick')
		
		redirect({
			url: '/addon/home_service/user/pages/order/list',
			mode: 'redirectTo'
 		})
		

	}
</script>

<style lang="scss" scoped>
	.bg-linear {
		background: linear-gradient(360deg, #f8f8f8 0%, $u-primary 50%);
	}

	.task-steps :deep(.u-text) {
		justify-content: center !important;
	}

	.line-feed {
		word-wrap: break-word;
		word-break: break-all;
	}

	.friend-pay {
		&::after {
			content: '';
			display: block;
			width: 20rpx;
			height: 20rpx;
			background-color: #f2f2f2;
			position: absolute;
			right: 30rpx;
			top: 0;
			transform: translateY(-50%) rotate(45deg);
			border-radius: 4rpx;
		}
	}

	.border-style {
		border: 2rpx solid #fff;
	}

	:deep(.u-steps-item__wrapper) {
		width: 40rpx !important;
		height: 40rpx !important;
		font-weight: bold;
		border-radius: 50%;
	}

	:deep(.u-steps-item__wrapper .uicon-checkmark) {
		color: var(--primary-color) !important
	}
	/* 底部安全区域适配 */
	.body-bottom {
		padding-bottom: env(safe-area-inset-bottom, 0);
		padding-bottom: constant(safe-area-inset-bottom, 0);
	}
</style>