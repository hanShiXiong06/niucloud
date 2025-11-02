<template>
	<view :style="themeColor()">
		<view class="bg-[#f7f7f7] min-h-screen overflow-hidden body-bottom" v-if="!loading">
			<!-- 自定义头部 -->
			<view class="flex items-center left-0 right-0 z-10 bg-transparent detail-head" :class="{'!bg-[#fff]' :detailHeadBgChange, 'fixed': true}" :style="navbarInnerStyle">
				<view class="flex-center h-[60rpx] rounded-[30rpx] box-border arrow-left px-[40rpx] leading-[1]" :style="navbarInnerArrowStyle">
					<text class="nc-iconfont nc-icon-zuoV6xx text-[18px]" @click="backToPrevious()"></text>
					<text class="w-[2rpx] h-[26rpx] bg-[#999] mx-[14rpx]"></text>
					<text class="nc-iconfont nc-icon-liebiao-xiV6xx1 text-[16px]" @click="topNav = true"></text>
				</view>
     			<view class="flex items-center ml-auto">
					<view @click="changeCollect" class="p-[10rpx] cf-collect-like bg-black/50 rounded-[12rpx] box-border mr-[10rpx]">
						<image :src="img('addon/home_service/user/gscx.png')" class="w-[36rpx] h-[36rpx] block"
							mode="aspectFit" v-if="detail.goods.is_collect"></image>
						<image :src="img('addon/home_service/user/gsc.png')" class="w-[36rpx] h-[36rpx] block"
							mode="aspectFit" v-else></image>
					</view>
					<view class="!pt-[12rpx] !pb-[8rpx] p-[10rpx] cf-collect-like bg-black/50 rounded-[12rpx] box-border"
						@click="openShareFn">
						<image :src="img('addon/home_service/user/gfx.png')" class="w-[36rpx] h-[36rpx] block"
							mode="aspectFit"></image>
					</view>
				</view>
			</view>
			<view class="fixed top-0 left-0 right-0 bottom-0 z-100 bg-transparent" @click="topNav = false" v-if="topNav">
				<view class="search-box w-[202rpx] bg-[#fff] rounded-[12rpx] relative" :style="fixedInnerStyle">
					<view class="px-[20rpx] flex-center" @click="redirect(item.url)" v-for="(item,index) in menuContents" :key="index">
						<text class="text-[30rpx] mr-[10rpx]" :class="item.iconfont"></text>
						<text class="pl-[14rpx] py-[20rpx] flex-1 text-[24rpx] text-[#333] border-0 border-[#ddd] border-b-[1rpx] border-solid">{{ item.name }}</text>
					</view>
				</view>
			</view>
				


			<view class="relative">
				<u-swiper @change="e => currentNum = e.current + 1" :list="detail.goods.goods_image_thumb_mid" indicator
					indicatorMode="dot" indicatorActiveColor="var(--primary-color)" indicatorInactiveColor="#ffffff"
					:autoplay="false" height="100vw" radius="0" @click="swiperClick">
				</u-swiper>
				<view
					class="absolute right-[20rpx] z-index-9 bottom-[20rpx] text-[#ffffff] text-[20rpx] bg-black/50 p-[10rpx] rounded-[30rpx]">
					{{currentNum}} / {{detail.goods?.goods_image_thumb_mid?.length}}
				</view>
<!-- 
				<view @click="openShareFn"
					class="absolute right-[20rpx] z-index-9 top-[20rpx] text-[#ffffff] text-[20rpx] bg-black/50 p-[10rpx] rounded-[10rpx]">
					<image :src="img('/addon/home_service/user/share.png')" class="w-[30rpx] h-[30rpx] block"
						mode="aspectFit"></image>
				</view> -->
				<!-- <view @click="changeCollect"
					class="absolute right-[90rpx] z-index-9 top-[20rpx] text-[#ffffff] text-[20rpx] bg-black/50 p-[10rpx] rounded-[10rpx]">
					<image :src="img('/addon/home_service/user/active-collect.png')" class="w-[30rpx] h-[30rpx] block"
						mode="aspectFit" v-if="detail.goods.is_collect"></image>
					<image :src="img('/addon/home_service/user/collect.png')" class="w-[30rpx] h-[30rpx] block"
						mode="aspectFit" v-else></image>
				</view> -->
			</view>
			<view class="chunk-wrap pt-2 pb-3 rounded-lg relative mt-[-10rpx] !bg-[#f6f6f6] !mb-[2rpx]">
				<view class="flex items-center justify-between mt-2 ">
					<view class="flex items-end">
						<view class="text-[var(--price-text-color)] text-[28rpx] font-bold flex items-end">
							<text class="text-[24rpx] price-font">￥</text>
							<text class="price-font text-[46rpx] leading-5">{{ Number(goodsPrice).toString().split('.')[0] }}</text>
							<text
								class="price-font text-[24rpx]">.{{ Number(goodsPrice).toFixed(2).split('.')[1] }}</text>
							<text v-if="detail.sku_unit">/{{ detail.sku_unit }}</text>
							<text class="price-font text-[24rpx] text-[#999] line-through font-400 ml-[10rpx]"
								v-if="detail.price != goodsPrice"><text
									class="text-[24rpx] price-font">￥</text>{{ Number(detail.price).toFixed(2) }}</text>
							<!-- <image v-if="priceType == 'member_price'" class="h-[28rpx] ml-[12rpx] w-[60rpx]"
								:src="img('addon/home_service/VIP.png')" mode="heightFix" /> -->
						</view>
						<!-- <view class="text-[24rpx] flex ml-[20rpx]">
			                <text class="text-[var(--primary-color)] rounded-[6rpx] py-[6rpx] bg-[var(--primary-color-light)] px-[10rpx]">{{ detail.goods.buy_type_name }}</text>
			            </view> -->
					</view>
					<view class="text-[24rpx] text-[#999999] flex items-center">
						<image :src="img('/addon/home_service/user/goods/flash.png')" class="w-[30rpx] h-[30rpx] block"
							mode="aspectFit"></image>
						{{t('timeWarning')}}
					</view>
				</view>
				<view class="font-bold multi-hidden mt-[20rpx] text-[32rpx] my-[10rpx] leading-5">
					{{ detail.goods.goods_name }}
				</view>
				<view class="multi-hidden text-[#666666] text-[28rpx] leading-[1.8]">{{ detail.goods.goods_subtitle }}</view>
				<view class="flex mt-[10rpx]" v-if="detail.goods?.guarantee_list && detail.goods?.guarantee_list?.length">
					<view
						class="border-1 border-solid border-[#CCCCCC] text-[22rpx] mr-[15rpx] p-[10rpx] rounded-[8rpx] text-[#444444]"
						v-for="(item,index) in detail.goods?.guarantee_list" :key="index">
						{{item.guarantee_title}}
					</view>
				</view>
			</view>

		
			<view v-if="isErrandBusiness" class="px-[12rpx]">
				<errand-order-form :sku-list="detail.skuList" @submit="handleErrandSubmit" />
			</view>

			<!-- 原有服务业务 -->
			<view v-else>
				<view class="mx-[24rpx] bg-[#ffffff] p-[24rpx] rounded-lg mb-[24rpx]">
					<view class="text-[#333333] text-[30rpx] font-[600] ">{{ detail.goods.category_name }}</view>
					<view class="flex flex-wrap">
						<view
							class="bg-[#F5FFF6] rounded--[10rpx] border-[1px] border-[#F5FFF6] rounded-[4rpx] mr-[24rpx] px-[15rpx] border-solid text-[26rpx] py-[10rpx] !rounded-[8rpx] mt-[24rpx]"
							@click="toOrder(detail)"
							:class="item.sku_name == detail.sku_name ? '!border-[var(--primary-color)] text-[var(--primary-color)] rounded-[10rpx]' : ''"
							v-for="(item,index) in detail.skuList">
							{{ item.sku_name }}
						</view>
					</view>
				</view>
			<view class="px-[24rpx]">
				<view class="rounded-lg bg-[#fff]">
					<view @click="buyFn" v-if="detail.skuList && detail.skuList?.length>1"
						class="flex items-center h-[88rpx] px-[20rpx]  ">
						<text class=" text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]">{{ t('selected') }}</text>
						<view class="flex-1 text-[#343434] text-sm leading-[42rpx] font-500 text-right mr-[10rpx] ">
							{{ detail.sku_name }}
						</view>
						<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)]"></text>
					</view>
					<view @click="openServicesSafePopup" v-if="detail.goods?.guarantee_list?.length > 1"
						class="flex items-center h-[88rpx] px-[20rpx] mb-[20rpx]">
						<text class=" text-[30rpx] leading-[42rpx] font-500 mr-[20rpx]">{{ t('serviceSafe') }}</text>
						<view class="flex-1 text-[#343434] text-sm leading-[42rpx] font-500 text-right mr-[10rpx] ">
							{{ detail.goods?.guarantee_list[0].guarantee_title }}
						</view>
						<text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[var(--text-color-light6)]"></text>
					</view>
				</view>
				<view class="rounded-lg bg-[#fff] mb-[24rpx] px-[25rpx] pt-[30rpx] pb-[6rpx]" v-if="detail.goods.buy_type == 'reservation' && detail.goods?.price_list?.length"
					>
					<view class="flex justify-between items-center mb-[25rpx]">
						<view class="text-[32rpx] font-bold">价目表</view>
					</view>
					<view class="my-[15rpx]">
						<view class="scheduling-content mt-2">
						    <view v-if="detail.goods.price_list && detail.goods?.price_list?.length">
						        <uni-table border stripe :emptyText="t('noMore')">
						            <uni-tr>
						                <uni-th width="80" align="center">{{ t('projectName') }}</uni-th>
						                <uni-th width="50" align="center">{{ t('price') }}</uni-th>
						                <uni-th width="50" align="center">{{ t('unit') }}</uni-th>
						            </uni-tr>
						            <uni-tr v-for="(item, index) in detail.goods.price_list" :key="index">
						                <uni-td align="center">{{ item.name }}</uni-td>
						                <uni-td align="center">
						                    <view class="name">{{ item.price }}</view>
						                </uni-td>
						                <uni-td align="center">{{ item.unit }}</uni-td>
						            </uni-tr>
						        </uni-table>
						    </view>
						    <view v-else class="h-[380rpx] flex">
						        <view class="mx-auto">
						            <image class="w-[280rpx] h-[280rpx]" :src="img('addon/home_service/goods/empty01.png')" />
						            <view class="text-center text-[#c1c1c1] text-[24rpx]">{{ t('noPriceList') }}</view>
						        </view>
						    </view>
						</view>
					</view>
				</view>
				<view class="rounded-lg bg-[#fff] mb-[24rpx] px-[25rpx] pt-[30rpx] pb-[6rpx]" v-if="nearbyTechslist?.length">
					<view class="text-[30rpx] text-[#111] mb-[25rpx]">
						{{t('serviceTechnican')}}
					</view>
					<view class="my-[15rpx]">
						<u-scroll-list :indicator="false">
							<view v-for="(item, index) in nearbyTechslist" :key="index"
								class="border-1 border-[#f6f6f6] border-solid p-[20rpx] rounded-[15rpx] mr-[20rpx] w-[70vw]">
								<view class="flex w-[60vw]">
									<view class="mr-[15rpx]">
										<u-avatar :src="img(item.headimg)" shape="circle" v-if="item.headimg"
											:default-url="img('static/resource/images/default_headimg.png')"
											class="w-[80rpx] h-[80rpx]" />
										<u-avatar :src="img('static/resource/images/default_headimg.png')"
											shape="circle" v-else class="w-[80rpx] h-[80rpx]" />
									</view>
									<view class="flex flex-col justify-around flex-1">
										<view class="flex justify-between">
											<view class="flex items-center">
												<view class="text-[30rpx] font-bold mr-[10rpx] max-w-[150rpx] truncate">
													{{item.real_name}}
												</view>
												<view class="">
													<u-rate v-model="item.evaluate_avg_scores" size="12" readonly></u-rate>
												</view>
											</view>
											
											<view class="flex items-center" @click="collectTechncianFn(item.is_collect_technician,index)">
												<u-icon name="star-fill" color="#ffb507" v-if="item.is_collect_technician"></u-icon>
												<u-icon name="star" v-else></u-icon>
											</view>
										</view>
										<view class="flex items-center">
											<view class="text-[#999] text-[24rpx]">
												{{item.level.level_name}}
											</view>
										</view>
									</view>
								</view>
								<view class="flex flex-wrap max-h-[60rpx] overflow-hidden">
									<view
										class="bg-[#F5FFF6]  rounded--[10rpx] border-[1px] border-[#F5FFF6] rounded-[4rpx] mr-[24rpx] text-[24rpx] px-[15rpx] border-solid py-[10rpx] mt-[15rpx]"
										v-for="(item,index) in item.category_name">
										{{ item.category_name }}
									</view>
								</view>
								<view class="flex mt-[25rpx] justify-between  w-[50vw]">
									<view class="flex items-center w-[100%]">
										<view
											class="pr-[10rpx] bg-[#F5FFF6] p-[10rpx] !text-[var(--primary-color)] rounded-[5rpx] text-[26rpx] mr-[15rpx]">
											{{t('userEvaluate')}}
										</view>
										<view
											class="text-[26rpx] whitespace-nowrap overflow-hidden text-ellipsis w-[36%]  flex-1 ellipsis-text max-h-[80rpx]">
											<text v-if="item.evaluate?.content">
												{{item.evaluate?.content}}
											</text>
											<text v-else class="text-[#999]">
												暂无评价
											</text>
										</view>
									</view>
									
								</view>
							</view>
						</u-scroll-list>
					</view>
				</view>
				<view class="rounded-lg bg-[#fff] mb-[24rpx] px-[25rpx] pt-[30rpx] pb-[6rpx]"
					v-if="recommendPackages?.length">
					<view class="flex justify-between items-center mb-[25rpx]">
						<view class="text-[30rpx] text-[#111]">{{t('recommedGoods')}}</view>
						<view class="text-[24rpx] text-[#999999]" @click="viewAllRecommend">查看全部 <text
								class="iconfont iconarrow-right text-[24rpx]"></text></view>
					</view>
					<view class="my-[15rpx]">
						<u-scroll-list :indicator="false">
							<view v-for="(item, index) in recommendPackages" :key="index"
								class="mr-[30rpx] bg-[#F5FFF6] p-[24rpx] rounded-lg">
								<view class="flex w-[360rpx] items-center justify-between">
									<view class="font-bold text-[32rpx] mr-[5rpx]">{{item.card_name}}
									</view>
									<view class="text-[20rpx] bg-[#FF0F00] p-[6rpx] text-[#fff] rounded-[5rpx] ">
										省{{item.discount_price}}元
									</view>
								</view>

								<view class="w-[100%]">
									<view class="flex items-baseline my-[25rpx]">
										<text class="text-[#FF4444] text-[22rpx]">¥</text>
										<text
											class="text-[#FF4444] text-[36rpx] font-bold">{{Number(item.price).toFixed(0)}}</text>
										<text
											class="text-[#FF4444] text-[26rpx] font-bold">.{{item.price.split('.')[1]}}</text>
										<text
											class="text-[#999999] text-[24rpx] line-through ml-[10rpx]">¥{{item.original_price}}</text>
									</view>
									<view class="text-[#999999] text-[24rpx] mb-[20rpx]">{{item.valid_type_name}}</view>
									<u-button
										class="!text-[var(--primary-color)] !border-[var(--primary-color)] !text-[26rpx] !rounded-[8rpx] !h-[50rpx] !line-height-[50rpx]"
										@click="buyPackage(item)">立即抢购</u-button>
								</view>
							</view>
						</u-scroll-list>
					</view>
				</view>
				<view class="rounded-lg bg-[#fff] mb-[24rpx] px-[25rpx] pt-[30rpx] pb-[6rpx]" v-if="evaluateList?.length">
					<view class="flex justify-between items-center mb-[25rpx]">
						<view class="text-[30rpx] text-[#111]">{{t('userEvaluate')}}</view>
						<view class="text-[24rpx] text-[#999999]" @click="redirect({url:'/addon/home_service/user/pages/goods/evaluate',param:{goods_id:detail.goods_id}})">查看全部 <text
								class="iconfont iconarrow-right text-[24rpx]"></text></view>
					</view>
					<view class="my-[15rpx]">
						<u-scroll-list :indicator="false">
							<view v-for="(item, index) in evaluateList" :key="index"
								class="flex bg-[#F9F9F9] p-[20rpx] rounded-[15rpx] mr-[20rpx]">
								<view class="w-[260rpx] mr-[30rpx]">
									<view class="flex ">
										<view class="mr-[15rpx]">
											<u-avatar :src="img(item.member?.headimg)" shape="circle" v-if="item.member?.headimg"
												:default-url="img('static/resource/images/default_headimg.png')"
												class="w-[80rpx] h-[80rpx]" />
											<u-avatar :src="img('static/resource/images/default_headimg.png')"
												shape="circle" v-else class="w-[80rpx] h-[80rpx]" />
										</view>
										<view class="flex flex-col justify-around">
											<view class=" items-end">
												<view class="text-[30rpx] font-bold mr-[10rpx]">
													{{item.member?.nickname}}
												</view>
												<view class="mt-[10rpx]">
													<u-rate v-model="item.scores" size="14"
														readonly></u-rate>
												</view>
											</view>
										</view>
									</view>
									<view class="flex mt-[25rpx] justify-between  w-[100%]">
										<view class="text-[26rpx] multi-hidden leading-[36rpx] w-[100%]">
											{{item.content}}
										</view>
									</view>
								</view>
								<view v-if="item.image_mid && item.image_mid?.length">
									<up-image width="168rpx" height="168rpx" radius="5" :src="img(item.image_mid[0])" model="aspectFill" class="rounded-[10rpx]">
										<template #error>
											<image class="w-[168rpx] h-[168rpx] rounded-[10rpx]" :src="img('static/resource/images/diy/shop_default.jpg')"
												mode="aspectFill" />
										</template>
									</up-image>
								</view>
							</view>
						</u-scroll-list>
					</view>
				</view>
				<view class="my-[24rpx] py-[30rpx] px-[25rpx] bg-[#fff] rounded-lg">
					<view class="text-[30rpx] text-[#111] pb-[25rpx] border-bottom-style mb-[25rpx]">
						一键下单  按约上门
					</view>
					<view class="flex justify-between  mb-[40rpx]">
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx] "
									:src="img('addon/home_service/user/card/detail-step-1.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								01
							</view>
							<view class="text-[26rpx]">
								提交订单
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-2.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								02
							</view>
							<view class="text-[26rpx]">
								预约时间
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-3.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								03
							</view>
							<view class="text-[26rpx]">
								支付订单
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-4.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								04
							</view>
							<view class="text-[26rpx]">
								开始服务
							</view>
						</view>
					</view>
					<view class="flex  justify-between">
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-8.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								05
							</view>
							<view class="text-[26rpx]">
								完成评价
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-blue.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-7.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								06
							</view>
							<view class="text-[26rpx]">
								客户验收
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-6.png')" />
							</view>
							<view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								07
							</view>
							<view class="text-[26rpx]">
								结束服务
							</view>
						</view>
						<view class="h-[90rpx] flex items-center justify-center">
							<image class="w-[35rpx] h-[35rpx] "
								:src="img('addon/home_service/user/card/detail-step-right-icon.png')" />
						</view>
						<view class="flex flex-col justify-center items-center">
							<view class="position-style" :style="{backgroundImage:'url(' + img('addon/home_service/user/card/step-bg-green.png') + ')'}">
								<image class="w-[42rpx] h-[42rpx] p-[24rpx]"
									:src="img('addon/home_service/user/card/detail-step-5.png')" />
							</view><view class="text-[var(--primary-color)] text-[26rpx] mb-[15rpx] mt-[5rpx]">
								08
							</view>
							<view class="text-[26rpx]">
								附加费用
							</view>
						</view>
					</view>
				</view>
				<view class="chunk-wrap pt-[34rpx] pb-[24rpx] scheduling rounded-lg">
					<view class="text-[30rpx] text-[#111] mb-[25rpx]">
						{{t('detailText')}}
					</view>
					<view class="mt-[24rpx]">
						<view class="scheduling-content mt-2">
							<u-parse :content="detail.goods.goods_content" :tagStyle="{img: 'vertical-align: top;'}"
								v-if="detail.goods.goods_content"></u-parse>
							<view v-else class="h-[380rpx] flex">
								<view class="mx-auto">
									<image class="w-[280rpx] h-[280rpx]"
										:src="img('addon/home_service/goods/empty01.png')" />
									<view class="text-center text-[#c1c1c1] text-[24rpx]">{{ t('noProjectInt') }}</view>
								</view>
							</view>
						</view>
					</view>
				</view>
				<view class="h-[148rpx] w-screen"></view>
				<view class="flex justify-between bg-white px-3 py-2 fixed bottom-0 left-0 right-0 body-bottom">
					<view class="flex items-center">
						<view class="flex flex-col items-center mr-[44rpx]"
							@click="redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })">
							<image class="w-[43rpx] h-[43rpx] " :src="img('addon/home_service/goods/index.png')"
								mode="aspectFill" />
							<text class="text-[24rpx] text-[#454545] mt-1.5">{{ t('index') }}</text>
						</view>
						<!-- <view class="flex flex-col items-center mr-[44rpx]" @click="openShareFn">
							<view class="nc-iconfont nc-icon-fenxiangV6xx text-[36rpx] mt-[4rpx] mb-[6rpx] font-bold">
							</view>
							<text class="text-[24rpx] text-[#454545] mt-1">{{ t('share') }}</text>
						</view> -->

						<view class="flex flex-col items-center mr-[44rpx]"  @click="redirect({ url: '/app/pages/member/contact' })">
							<image class="w-[44rpx] h-[44rpx]" :src="img('addon/home_service/goods/service.png')"
								mode="aspectFill" />
							<text class="text-[24rpx] text-[#454545] mt-1">{{ t('service') }}</text>
						</view>
						<!-- <view class="flex flex-col items-center mr-[44rpx]" @click="collect(detail)">
                        <image class="w-[44rpx] h-[44rpx]" v-if="collect_id > 0" :src="img('static/resource/images/member/select_collect.png')" mode="aspectFill" />
                        <image class="w-[44rpx] h-[44rpx]" v-else :src="img('static/resource/images/member/collect.png')" mode="aspectFill" />
                        <text class="text-[24rpx] text-[#454545] mt-1">收藏</text>
                    </view> -->
					</view>
					<u-button :customStyle="{ marginLeft:'8rpx', borderRadius:'38rpx',flex: '1'}"
						:color="detail.goods.status ? 'var(--primary-color)' :'#999'" size="16"
						:text="detail.goods.status ? (detail.goods.buy_type == 'reservation' ? t('bookNow') : t('orderNow')) : t('delisted')"
						@click="toOrder(detail)"></u-button>
				</view>
			</view>
			<!-- 原有服务业务结束标签 -->
			</view>
			<share-poster ref="sharePosterRef" posterType="home_service_goods" :posterId="detail.goods.poster_id"
				:posterParam="posterParam" :copyUrlParam="copyUrlParam" />

		</view>
		<ns-goods-sku ref="goodsSkuRef" :goods-detail="detail" @change="specSelectFn"></ns-goods-sku>
		<loading-page :loading="loading"></loading-page>

		<!-- 服务保障弹框 -->
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
		<pay ref="payRef" @close="payClose"></pay>
	</view>
</template>

<script setup lang="ts">
	import { ref, reactive, computed, getCurrentInstance } from 'vue';
	import { onLoad, onShow, onUnload, onPageScroll } from '@dcloudio/uni-app'
	import { useLogin } from '@/hooks/useLogin';
	import { img, redirect, getToken, handleOnloadParams } from '@/utils/common';
	import { getGoodsDetail, getNearbyTechs, getCardList, getEvaluateList,addbrowseGoods } from '@/addon/home_service/user/api/goods';
    import { getCollect, setCollect, cancelCollect,collectTechnician,cancelTechnicianCollect } from '@/addon/home_service/user/api/collect';
	import useMemberStore from '@/stores/member'
	import { getCardDetail ,orderCreate} from '@/addon/home_service/user/api/card';
	import { t } from '@/locale';
	import nsGoodsSku from '@/addon/home_service/user/components/ns-goods-sku/ns-goods-sku.vue'
	import ErrandOrderForm from '@/addon/home_service/user/components/errand-order-form/errand-order-form.vue'
	import uniTable from '@/addon/home_service/user/components/uni-table/components/uni-table/uni-table.vue'
	import uniTr from '@/addon/home_service/user/components/uni-table/components/uni-tr/uni-tr.vue'
	import uniTh from '@/addon/home_service/user/components/uni-table/components/uni-th/uni-th.vue'
	import uniTd from '@/addon/home_service/user/components/uni-table/components/uni-td/uni-td.vue'
	import sharePoster from '@/components/share-poster/share-poster.vue'
	import { useShare } from '@/hooks/useShare'
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	const payRef = ref(null)
	const detail = ref<Record<string, any>>({});
	const loading = ref<boolean>(true);
	const memberStore = useMemberStore()
	const goodsSkuRef = ref(null)
	// 分享
	const { setShare } = useShare()
	// 会员信息
	const userInfo = computed(() => memberStore.info)
	const servicesSafePopupOpen = ref(false)
	const openServicesSafePopup = () => {
		servicesSafePopupOpen.value = true
	}
	const goodsState = ref('goods_content')
	const currentNum = ref(1)
	// 切换状态
	const changeGoodStatus = (status : any) => {
		goodsState.value = status
	}
	
	/************ 自定义头部-start ****************/
	const topNav = ref(false);
	let platform = systemStore.systemInfo.platform;
	
	// 跑腿业务相关
	const isErrandBusiness = computed(() => {
		// 判断条件：商品的 goods_content 包含跑腿路线配置
		console.log(detail.value.errand_business);
		
		return detail.value.errand_business
	})
	// 导航栏内部盒子的样式
	const navbarInnerStyle = computed(() => {
		let style = '';
		// #ifdef MP
		let rightButtonWidth = systemStore.menuButtonInfo.width ? systemStore.menuButtonInfo.width * 2 + 'rpx' : '70rpx';
		style += 'height:' + systemStore.menuButtonInfo.height + 'px;';
		style += 'padding-right:calc(' + rightButtonWidth + ' + 30rpx);';
		style += 'padding-left:calc(' + rightButtonWidth + ' + 30rpx);';
		style += 'padding-top:' + systemStore.menuButtonInfo.top + 'px;';
		style += 'padding-bottom: 8px;';
		style += 'font-size: 32rpx;';
		if (platform == 'ios') {
			style += 'font-weight: 500;';
		} else if (platform == 'android') {
			style += 'font-size: 36rpx;';
		}
		// #endif
		
		// #ifdef H5
		style += 'height: 100rpx;';
		style += 'padding-right: 30rpx;';
		style += 'padding-left: 30rpx;';
		style += 'font-size: 32rpx;';
		if (platform == 'ios') {
			style += 'font-weight: 500;';
		} else if (platform == 'android') {
			style += 'font-size: 36rpx;';
		}
		// #endif
		
		// #ifdef APP-PLUS
		style += 'height: 80rpx;';
		style += 'padding-right: 30rpx;';
		style += 'padding-left: 30rpx;';
		style += 'padding-top:' + systemStore.systemInfo.statusBarHeight + 'px;';
		// #endif
		
		return style;
	})
	
	// 导航栏内部盒子的样式
	const navbarInnerArrowStyle = computed(() => {
		let style = '';
		// #ifdef MP
		style += 'position: absolute;';
		style += 'left:calc( 100vw - ' + systemStore.menuButtonInfo.right + 'px);';
		if (platform == 'ios') {
			style += 'font-weight: 700;';
		}
		// #endif
		return style;
	})
	
	// 导航栏头部卡片样式
	const fixedInnerStyle = computed(() => {
		let style = '';
		// #ifdef MP
		style += 'top:' + (systemStore.menuButtonInfo.height + systemStore.menuButtonInfo.top + 8) + 'px;';
		style += 'left:calc( 100vw - ' + systemStore.menuButtonInfo.right + 'px);';
		// #endif
		// #ifdef H5
		style += 'top: 100rpx;';
		style += 'left: 30rpx;';
		// #endif
		// #ifdef APP-PLUS
		style += 'top:' + (systemStore.systemInfo.statusBarHeight + uni.upx2px(100)) + 'px;';
		style += 'left: 30rpx;';
		// #endif
		return style;
	})
	
	// 头部滚动
	const instance = getCurrentInstance();
	let swiperHeight = 0
	let detailHead = 0
	
	const detailHeadBgChange = ref(false)
	onPageScroll((e) => {
		if (swiperHeight == 0 || detailHead == 0) return;
		let height = swiperHeight - detailHead - 20;
		detailHeadBgChange.value = false;
		if (e.scrollTop >= height) {
			detailHeadBgChange.value = true;
		}
	})
	
	// 菜单列表
	const menuList = {
		index: {
			name: '首页',
			iconfont: 'nc-iconfont nc-icon-shouyeV6xx11',
			url: { url: '/addon/home_service/user/pages/index', mode: 'reLaunch' }
		},
		search: {
			name: '搜索',
			iconfont: 'nc-iconfont nc-icon-sousuo-duanV6xx1',
			url: { url: '/addon/home_service/user/pages/goods/list' }
		},
		member: {
			name: '个人中心',
			iconfont: 'nc-iconfont nc-icon-a-wodeV6xx-36',
			url: { url: '/addon/home_service/user/pages/member/index' }
		},
		// collect: {
		// 	name: '我的收藏',
		// 	iconfont: 'nc-iconfont nc-icon-guanzhuV6xx',
		// 	url: { url: '/addon/home_service/user/pages/goods/collect' }
		// }
	}
	
	const menuContents = computed(() => {
		const list = []
		const menu = ['index', 'search', 'member']
		menu.forEach((item: any) => {
			if (menuList[item]) {
				list.push(menuList[item])
			}
		})
		return list
	})
	
	// 返回上一页
	const backToPrevious = () => {
		if (getCurrentPages().length > 1) {
			uni.navigateBack({
				delta: 1
			});
		} else {
			redirect({
				url: '/addon/home_service/user/pages/index',
				mode: 'reLaunch'
			});
		}
	}
	/************ 自定义头部-end ****************/
	const collectTechncianFn = (status:any,index :any) =>{
		let params = {
			technician_id:nearbyTechslist.value[index].id
		}
		console.log(status,index)
		if(status == 0){
			collectTechnician(params).then((res)=>{
				nearbyTechslist.value[index].is_collect_technician = 1
			})
		}else{
			cancelTechnicianCollect(params).then((res)=>{
				nearbyTechslist.value[index].is_collect_technician = 0
			})
		}
	}
	
	const nearbyTechslist = ref([])
	const getNearbyTechsFn = () => {
		loading.value = true;
		let params = {
			lng: systemStore.diyAddressInfo?.longitude,
			lat: systemStore.diyAddressInfo?.latitude,
			distance: 'all',
			goods_id: detail.value.goods_id,
			city_id:systemStore.diyAddressInfo?.city_id
		}
		getNearbyTechs(params).then((res) => {
			loading.value = false;
			nearbyTechslist.value = res.data
		}).catch((err)=>{
			loading.value = false;
		})
	}
	const recommendPackages = ref([])
	const getCardListFn = () => {
		loading.value = true;
		let params = {
			goods_id: detail.value.goods_id,
			city_id:systemStore.diyAddressInfo?.city_id
		}
		getCardList(params).then((res) => {
			loading.value = false;
			recommendPackages.value = res.data.data
		}).catch((err)=>{
			loading.value = false;
		})
	}
	const evaluateList = ref([])
	const getEvaluateListFn = () => {
		loading.value = true;
		let params = {
			goods_id: detail.value.goods_id,
			page:1,
			page_size:5,
			city_id:systemStore.diyAddressInfo?.city_id
		}
		getEvaluateList(params).then((res) => {
			evaluateList.value = res.data.data
			loading.value = false;
		}).catch((err)=>{
			loading.value = false;
		})
	}
	const changeCollect = () =>{
		if(detail.value.goods.is_collect){
			let params = {
				goods_ids:[detail.value.goods_id]
			}
			cancelCollect(params).then((res)=>{
				detail.value.goods.is_collect = 0
			})
		}else{
			let params = {
				goods_id: detail.value.goods_id,
				type: 'goods'
			}
			setCollect(params).then((res)=>{
				detail.value.goods.is_collect = 1
			})
		}
	}
	// 查看全部推荐套餐
	const viewAllRecommend = () => {
		// 实现查看全部推荐套餐的逻辑
		redirect({url:'/addon/home_service/user/pages/card/list'})
	}

	// 购买套餐
	const buyPackage = (packageItem) => {
		let data = {
			card_id:packageItem.card_id,
      city_id:systemStore.diyAddressInfo?.city_id
		}
		orderCreate(data).then(({ data }) => {
			payRef.value?.open(data.trade_type, data.trade_id, `/addon/home_service/user/pages/card/my_card`)
		}).catch((res) => {
		})
	}

	const goods = ref({
		sku_id: '',
		goods_id: ''
	});
	onLoad((option) => {
		if (!getToken()) {
			useLogin().setLoginBack({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: option.sku_id,goods_id: option.goods_id } })
			return false;
		}
		// #ifdef MP-WEIXIN
		// 处理小程序场景值参数
		option = handleOnloadParams(option);
		// #endif

		goods.value.sku_id = option.sku_id
		goods.value.goods_id = option.goods_id
		goods.value.city_id = systemStore.diyAddressInfo?.city_id
		loading.value = true
		if (getToken()) {
			memberStore.getMemberInfo()
		}
		getGoodsDetail(goods.value).then((res) => {
			if (!res.data || JSON.stringify(res.data) === '{}') {
				uni.showToast({ title: '找不到该商品', icon: 'none' })
				setTimeout(() => {
					redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })
				}, 600)
				return false
			}
			detail.value = res.data
			getNearbyTechsFn()
			getCardListFn()
			getEvaluateListFn()
			// 处理图片路径
			if (detail.value.goods && detail.value.goods.goods_image_thumb_mid) {
				detail.value.goods.goods_image_thumb_mid.forEach((item, index) => {
					detail.value.goods.goods_image_thumb_mid[index] = img(item);
				})
			}
			//  console.log(detail.value, 'detail.value')
			// 分享 - start
			let share = {
				title: detail.value.goods.goods_name,
				desc: detail.value.goods.sub_title,
				url: detail.value.goods.goods_cover_thumb_mid
			}
			uni.setNavigationBarTitle({
				title: detail.value.goods.goods_name
			})

			addbrowseGoodsFn()
			setShare({
				wechat: {
					...share
				},
				weapp: {
					...share
				}
			});
			// 分享 - end
			copyUrlFn();
		}).catch((err)=>{
			uni.showToast({ title: '找不到该商品', icon: 'none' })
			setTimeout(() => {
				redirect({ url: '/addon/home_service/user/pages/index', mode: 'reLaunch' })
			}, 600)
			return false
		})
	})
	const addbrowseGoodsFn = () =>{
		let params = {
			goods_id:detail.value.goods_id 
		}
		addbrowseGoods(params).then((res)=>{
		})
	}
	// 订单计算创建
	let orderData = {
		sku: {
			num: 1,
			sku_id: ''
		}
	}

	// 跳转订单预约
	const toOrder = (data) => {
		if (!getToken()) {
			useLogin().setLoginBack({ url: '/addon/home_service/user/pages/goods/detail', param: { sku_id: data.sku_id } })
			return false;
		}
		if (!data.goods.status) {
			return false
		}
		orderData.sku.sku_id = data.sku_id
		uni.setStorageSync('o2oCreateData', orderData);
		if (data.goods.buy_type == 'buy') {
			buyFn()
		} else {
			redirect({ url: '/addon/home_service/user/pages/order/payment', param: { id: data.goods_id } })
		}
	}

	const buyFn = () => {
		goodsSkuRef.value.open()
	}

	const specSelectFn = (id) => {
		detail.value.skuList.forEach((item, index) => {
			if (item.sku_id == id) {
				Object.assign(detail.value, item);
			}
		})
		console.log(detail.value)
	}
	const swiperClick = (index : any) => {
		if (typeof index == 'number') imgListPreview(detail.value.goods.goods_image_thumb_mid, index)
	}
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

	/************* 分享海报-start **************/
	const sharePosterRef = ref(null);
	const copyUrlParam = ref('');
	let posterParam = {};

	// 分享海报链接
	const copyUrlFn = () => {
		copyUrlParam.value = '?sku_id=' + detail.value.sku_id;
		if (userInfo.value && userInfo.value.member_id) copyUrlParam.value += '&mid=' + userInfo.value.member_id;
	}

	const openShareFn = () => {
		posterParam.sku_id = detail.value.sku_id;
		if (userInfo.value && userInfo.value.member_id) posterParam.member_id = userInfo.value.member_id;
		sharePosterRef.value.openShare()
	}

	/************* 分享海报-end **************/

	// 价格类型
	const priceType = ref('') //''=>原价，discount_price=>折扣价，member_price=>会员价

	// 商品价格
	const goodsPrice = computed(() => {
		let price = "0.00";
		if (Object.keys(detail.value).length && Object.keys(detail.value.goods).length && getToken() && detail.value.member_price != detail.value.price) {
			// 会员价
			price = detail.value.member_price ? detail.value.member_price : detail.value.price
			priceType.value = 'member_price'
		} else {
			price = detail.value.price
			priceType.value = ''
		}
		return price;
	})

	// 关闭预览图片
	onUnload(() => {
		// #ifdef  H5 || APP
		try {
			uni.closePreviewImage()
		} catch (e) {

		}
		// #endif
	})
</script>

<style lang="scss" scoped>
	.arrow-left {
		background: rgba(255, 255, 255, 0.6);
		border: 1rpx solid rgba(0, 0, 0, 0.1);
	}
	.cf-arrow-left{
		background: rgba(17, 17, 17, 0.50);
        border: 0.03125rem solid rgba(0, 0, 0, 0.1);
	}
	
	.chunk-wrap {
		@apply bg-white px-4 mb-3;

		.chunk-head {
			height: 84rpx;
			@apply flex justify-between items-center border-0 border-b border-solid border-[#F2F2F2] box-border;

			text {
				&:first-of-type {
					@apply font-bold;
				}

				&:last-of-type {
					@apply text-[24rpx] text-[var(--text-color-light9)];
				}

				.iconfont {
					@apply inline-block;
					margin-left: 2rpx;
				}
			}
		}
	}

	.member-price {
		background: linear-gradient(90deg, #FEF3E7 0%, #FFFFFF 100%);
	}

	.text-color {
		color: $u-primary;
	}

	.bg-color {
		background-color: $u-primary;
	}

	.word-all {
		word-break: keep-all;
	}

	.text-scale {
		transform: scale(0.8);
	}

	.class-select {
		position: relative;
		font-weight: bold;
		color: var(--primary-color);

		&::after {
			content: "";
			position: absolute;
			bottom: 0;
			height: 6rpx;
			border-radius: 3rpx;
			background-color: $u-primary;
			width: 60rpx;
			left: 50%;
			transform: translateX(-50%);
		}
	}

	.scheduling-content :deep(.uni-table) {
		min-width: 100% !important;
	}

	:deep(.scheduling-content img) {
		vertical-align: middle;
	}

	/* 服务保障弹框样式 */
	.popup-content {
		animation: popupIn 0.3s ease-out;
	}

	@keyframes popupIn {
		from {
			opacity: 0;
			transform: scale(0.9) translateY(-20rpx);
		}

		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
	}

	.service-item {
		transition: all 0.2s ease;
	}

	.service-item:active {
		background-color: #f0f0f0;
	}
	.data-stats-card {
		background: #FFFFFF;
		border-radius: 50%;
		box-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
	}
	.border-bottom-style{
		border-bottom:2rpx solid #f5f5f5
	}
	/* 底部安全区域适配 */
	.body-bottom {
		padding-bottom: calc( 20rpx + env(safe-area-inset-bottom, 0));
		padding-bottom: calc(20rpx + constant(safe-area-inset-bottom, 0));
	}
	.position-style{
		background-size: 100%;
		background-repeat: no-repeat;
	}
</style>