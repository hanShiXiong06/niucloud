<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view  :class="{'dark': isFine, 'light': !isFine}">
            <view class="header_box fixed left-0 top-0 right-0 z-10">
                <view class="h-[88rpx] px-[20rpx] flex items-center justify-between" :style="navbarInnerStyle">
                    <text class="nc-iconfont nc-icon-liebiaoV6xx1 icon text-[36rpx] " @click="handleShow"></text>
                    <view class="flex itesm-center text-[32rpx]" :style="headerStyle">
                        <text class="tab-item" :class="{'active-tab': isFollow == 1 }" @click="handleTab(1)">{{ t('follow') }}</text>
                        <text class="tab-item ml-[40rpx]" :class="{'active-tab': isFollow == 0 && !isFine }" @click="handleTab(0)">{{ t('find') }}</text>
                        <text class="tab-item ml-[40rpx]" :class="{'active-tab': isFollow == 0 && isFine }" @click="handleTab(0,'fine')">{{ t('selection') }}</text>
                    </view>
                    <text class="nc-iconfont nc-icon-a-sousuoV6xx-34 icon text-[36rpx]" @click="redirect({url: '/addon/sow_community/pages/search'})"></text>
                </view>
            </view>
            <template v-if="!isFine">
                <mescroll-body ref="mescrollRef" :top="mescrollTop" bottom="60px"  @init="mescrollInit" :down="{ use: false }" @up="getCommunityListFn">
                    <!-- 发现列表 -->
                    <template v-if="isFollow == 0 && !isFine">
                        <view class="category-bg w-full  text-[#333] fixed z-1000 flex-between-center" :style="`top: ${categoryTop}`">
                            <view class="flex-1 box-border px-[30rpx]">
                                <scroll-view :scroll-x="true" scroll-with-animation :scroll-into-view="'id'+ curCategory" class="w-[690rpx] align-middle whitespace-nowrap">
                                    <view class="inline-block mr-[52rpx]" v-for="(item, index) in categoryList" :key="index" :id="'id' + item.category_id">
                                        <view class="h-[100rpx] leading-[100rpx] text-[28rpx] max-w-[204rpx] truncate" :class="{'cate-active': curCategory == item.category_id}" @click="handleCategory(item.category_id)">{{ item.category_name }}</view>
                                    </view>
                                </scroll-view>
                            </view>
                        </view>
                        <view  class="biserial-page sidebar-margin" v-if="communityList.length">
                            <view>
                                <template v-for="(item, index) in leftList" :key="index">
                                    <view  class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]" @click="toDetail(item)">
                                        <view class="overflow-y-hidden relative box-border">
                                            <image v-if="item.content_cover"  class="w-[100%]  rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" />
                                            <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('addon/sow_community/default_img.jpg')" :mode="'aspectFill'" />
                                            <view v-if="item.content_type == 1" class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color"> {{ item.image_num }}{{ t('picture') }}</view>
                                            <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" />
                                        </view>
                                        <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                            <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]">{{ item.content_title }}</view>
                                            <view class="flex items-center justify-between text-[22rpx] text-[#999]">
                                                <view class="flex items-center" v-if="item.member" @click.stop="redirect({url: '/addon/sow_community/pages/member',param: { member_id: item.member_id}})">
                                                    <u-avatar :src="img(item.member.headimg)" size="17" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                                    <text class="max-w-[180rpx] ml-[8rpx] leading-[30rpx] using-hidden">{{ item.member.nickname }}</text>
                                                </view>
                                                <view class="flex items-center" @click.stop="handleLike(item)">
                                                    <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary" v-if="item.is_like"></text>
                                                    <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] " v-else></text>
                                                    <text class="text-center ml-[6rpx] min-w-[14rpx]">{{ item.like_num }}</text>
                                                </view>
                                            </view>
                                        </view>
                                    </view>
                                </template>
                            </view>
                            <view>
                                <template v-for="(item, index) in rightList" :key="index">
                                    <view  class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]" @click="toDetail(item)">
                                        <view class="overflow-y-hidden relative box-border">
                                            <image v-if="item.content_cover"  class="w-[100%] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" />
                                            <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('addon/sow_community/default_img.jpg')" :mode="'aspectFill'" />
                                            <view v-if="item.content_type == 1" class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color"> {{ item.image_num }}{{ t('picture') }}</view>
                                            <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" />
                                        </view>
                                        <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                            <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]">{{ item.content_title }}</view>
                                            <view class="flex items-center justify-between text-[22rpx] text-[#999]">
                                                <view class="flex items-center" v-if="item.member" @click.stop="redirect({url: '/addon/sow_community/pages/member',param: { member_id: item.member_id}})">
                                                    <u-avatar :src="img(item.member.headimg)" size="17" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                                    <text class="max-w-[180rpx] ml-[8rpx] leading-[30rpx] using-hidden">{{ item.member.nickname }}</text>
                                                </view>
                                                <view class="flex items-center" @click.stop="handleLike(item)">
                                                    <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary" v-if="item.is_like"></text>
                                                    <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx]" v-else></text>
                                                    <text class="text-center ml-[6rpx] min-w-[14rpx]">{{ item.like_num }}</text>
                                                </view>
                                            </view>
                                        </view>
                                    </view>
                                </template>
                            </view>
                        </view>
                        <mescroll-empty v-if="!communityList.length && loading" :option="{tip : t('noContent')}"></mescroll-empty>
                    </template>
                    <!-- 关注列表 -->
                    <template v-if="isFollow == 1">
                        <view v-if="followList.length" class="py-[30rpx] pl-[20rpx] bg-[#fff] relative">
                            <view class="box-border">
                                <scroll-view :scroll-x="true" class="align-middle whitespace-nowrap w-full">
                                    <view class="inline-block mr-[40rpx]" v-for="(item,index) in followList" :key="index">
                                        <view class="w-[110rpx] flex flex-col flex-center relative" @click="redirect({url: '/addon/sow_community/pages/member',param: { member_id: item.follow_member_id}})">
                                            <u-avatar :src="img(item.headimg)" size="50" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                            <view class="mt-[16rpx] text-[22rpx] w-[110rpx] leading-[32rpx] text-center using-hidden">{{ item.nickname }}</view>
                                        </view>
                                    </view>
                                </scroll-view>
                            </view>
                            <view class="absolute right-0 top-0 bottom-0 w-[74rpx] h-[192rpx] bg-[#fff] box-shadow flex flex-col justify-center px-[22rpx] box-border" @click="redirect({url: '/addon/sow_community/pages/follow'})" v-if="followList.length > 5">
                                <text class="text-[28rpx] text-[#333] leading-[44rpx] mb-[16rpx]">{{ t('more') }}</text>
                                <text class="nc-iconfont nc-icon-zhankai-yousuojinV6xx text-[28rpx]"></text>
                            </view>
                        </view>
                        <view  class="sidebar-margin top-mar" v-if="communityList.length">
                            <view class="card-template mb-[var(--top-m)]" v-for="(item, index) in communityList" :key="index" @click="toDetail(item)">
                                <view class="flex-between-center mb-[20rpx]">
                                    <view class="flex items-center" v-if="item.member" @click.stop="redirect({url: '/addon/sow_community/pages/member',param: { member_id: item.member_id}})">
                                        <u-avatar :src="img(item.member.headimg)" size="30" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                        <text class="text-[30rpx] font-500 ml-[16rpx] leading-[42rpx] using-hidden">{{ item.member.nickname }}</text>
                                    </view>
                                    <text class="text-[24rpx] text-[#999] flex-shrink-0 ml-[20rpx]">{{ item.create_time }}</text>
                                </view>
                                <view>
                                    <swiper class="h-[662rpx] guanzhu-swiper mb-[20rpx]" interval="3000" autoplay="true"  circular="true" :indicator-dots="item.content_image.split(',').length > 1 ? true : false" indicator-color="rgba(255,255,255,.4)" indicator-active-color="rgb(255,255,255)" v-if="item.content_image">
                                        <swiper-item class="guanzhu-item" v-for="(subItem, subIndex) in item.content_image.split(',')" :key="subIndex">
                                            <image class="w-[100%] h-[662rpx] rounded-[var(--rounded-big)] align-middle" :src="img(subItem)" :mode="'aspectFill'" />
                                        </swiper-item>
                                    </swiper>
                                    <view class="h-[662rpx] w-full box-border relative mb-[20rpx]" v-if="item.content_type == 2">
                                        <image class="w-[100%] h-[662rpx] rounded-[var(--rounded-big)]"  :src="img(item.content_cover)" :mode="'aspectFill'" />
                                        <image class="w-[74rpx] h-[74rpx] absolute right-0 top-0 left-0 bottom-0 m-auto" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" />
                                    </view>
                                    <scroll-view :scroll-x="true" class="align-middle whitespace-nowrap w-full mb-[24rpx]" v-if="item.treasure_list">
                                        <view class="inline-block w-[662rpx]" :class="{'mr-[20rpx]': (subIndex + 1) != item.treasure_list.length }" v-for="(subItem, subIndex) in item.treasure_list" :key="subIndex">
                                            <view class="p-[15rpx] bg-[#fff] box-border border-solid border-[2rpx] border-[#eee] rounded-[var(--rounded-small)] flex justify-between" @click.stop="redirect({url: subItem.treasure_url})">
                                                <image v-if="subItem.treasure_image" class="w-[100rpx] h-[100rpx] rounded-[12rpx]" :src="img(subItem.treasure_image)" :mode="'aspectFill'" @error="subItem.treasure_image='static/resource/images/diy/shop_default.jpg'"></image>
                                                <image v-else class="w-[100rpx] h-[100rpx] rounded-[12rpx] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"></image>
                                                <view class="flex-1  ml-[16rpx] flex flex-col justify-between">
                                                    <view class="text-[#333] text-[24rpx] leading-[36rpx] using-hidden">{{ subItem.treasure_name }}</view>
                                                    <view class="flex-between-center">
                                                        <view class="price-font flex items-baseline">
                                                            <text class="text-[22rpx] font-500">￥</text>
                                                            <text class="text-[36rpx] font-500">{{ parseFloat(subItem.treasure_price).toFixed(2).split('.')[0] }}</text>
                                                            <text class="text-[22rpx] font-500">.{{ parseFloat(subItem.treasure_price).toFixed(2).split('.')[1]  }}</text>
                                                        </view>
                                                        <view class="w-[100rpx] h-[40rpx] border-solid border-[2rpx] border-primary box-border text-center leading-[38rpx] rounded-full text-[22rpx] text-primary">{{ t('buy') }}</view>
                                                    </view>
                                                </view>
                                            </view>
                                        </view>
                                    </scroll-view>
                                    <view class=" text-[28rpx] font-500 mb-[16rpx] ">{{ item.content_title }}</view>
                                    <view class="text-[24rpx] leading-[34rpx] mb-[24rpx] relative">
                                        <text v-if="item.show">{{ item.content_split }}</text>
                                        <template v-else v-for="(subItem) in item.content.split('\n')">
                                            <view v-html="subItem"></view>
                                        </template>
                                        <template v-if="item.flag">
                                            <text class="text-primary ml-[10rpx]" v-if="item.show" @click.stop="item.show = false">{{ t('expand') }}</text>
                                            <text class="text-primary ml-[10rpx]" v-else @click.stop="item.show = true">{{ t('packUp') }}</text>
                                        </template>
                                    </view>
                                    <view class="flex flex-wrap items-center" v-if="item.topic_list && item.topic_list.length">
                                        <text class="text-[24rpx] font-500 text-primary mr-[10rpx] mb-[10rpx]" v-for="(subItem, subIndex) in item.topic_list" :key="subIndex" @click.stop="redirect({url: '/addon/sow_community/pages/topic_list', param: {topic_id: subItem.topic_id,topic_name:encodeURIComponent(subItem.topic_name)}})">#{{ subItem.topic_name }}</text>
                                    </view>
                                    <view class="mt-[20rpx] flex-between-center">
                                        <view class="flex items-center">
                                            <view class="flex items-center" @click.stop="handleLike(item)">
                                                <text class="nc-iconfont nc-icon-xihuanV6mm text-primary text-[32rpx]" v-if="item.is_like"></text>
                                                <text class="nc-iconfont nc-icon-guanzhuV6xx text-[32rpx]" v-else></text>
                                                <text class="ml-[10rpx] text-[24rpx] min-w-[14rpx]">{{ item.like_num }}</text>
                                            </view>
                                            <view class="flex items-center ml-[24rpx]">
                                                <text class="nc-iconfont nc-icon-xiaoxiV6xx1 text-[32rpx]"></text>
                                                <text class="ml-[10rpx] text-[24rpx]">{{item.community_comments_status ? item.comment_num : 0 }}</text>
                                            </view>
                                        </view>
                                        <view class="flex items-center" @click.stop="openShareFn(item)">
                                            <text class="nc-iconfont nc-icon-fenxiangV6xx text-[32rpx]"></text>
                                            <!-- <text class="ml-[10rpx] text-[24rpx]">{{ item.share_num }}</text> -->
                                        </view>

                                    </view>
                                </view>
                            </view>
                        </view>
                        <mescroll-empty v-if="!communityList.length && loading" :option="{tip : '您还没有关注的用户', icon: isFollow ? img('/addon/sow_community/default_follow.jpg') : img('static/resource/images/system/empty.png') }"></mescroll-empty>
                        <ns-follow-recommend></ns-follow-recommend>
                    </template>
                </mescroll-body>
            </template>
             <!-- 精选 -->
             <template v-if="isFollow == 0 && isFine">
                <view class="text-[#fff]" v-if="communityList.length">
                    <swiper class="video-swiper h-screen" vertical="true" :circular="false" :current="current" @change="swiperChange">
                        <swiper-item class="swiper-video-item h-screen" v-for="(item, index) in communityList" :key="index">
                            <view v-if="item.content_type == 1 && current == index">
                                <swiper class="w-screen h-screen" interval="3000" :autoplay="item.autoplay" :current="curImg" :circular="true" @change="contentImgChange" v-if="item.content_image" >
                                    <swiper-item v-for="(subItem, subIndex) in item.content_image.split(',')" :key="subIndex" >
                                        <view class="w-screen h-screen flex items-center">
                                            <image :src="img(subItem)" mode="widthFix" class="w-screen"></image>
                                        </view>
                                    </swiper-item>
                                </swiper>
                            </view>
                            <view v-if="item.content_type == 2 && current == index" class="h-full relative">
                                <!-- :poster="img(item.content_cover_thumb_mid)" -->
                                <video  :id="'video' + index" :src="img(item.content_video)" @play="handlePlay(index)" @click="togglePlay(index)" @pause="handlePause(index)" :autoplay="index === 0"  loop :controls="false" :show-fullscreen-btn="false" :show-center-play-btn="false" :show-play-btn="false" :enable-progress-gesture="false"  class="w-screen h-screen"  ></video>
                                <view v-if="isPlay && index === 0 && item.content_type == 2 && isWeixinBrowser()" class="absolute top-0 left-0 right-0 bottom-0 flex-center" @click="togglePlay(index, true)">
                                    <image class="w-[120rpx] h-[120rpx] rounded-full" :src="img('/addon/sow_community/index/video_play.png')" mode="aspectFill"></image>
                                </view>
                            </view>
							<!-- 右侧个人 -->
							<view class="fixed right-[20rpx] bottom w-[88rpx] z-800 text-[#fff] flex flex-col items-center"  v-if="fineData">
							    <view class="rounded-full border-solid border-[2rpx] border-[#fff] relative flex-center">
							        <u-avatar  v-if="fineData.member && fineData.member.headimg" :src="img(fineData.member.headimg)" size="45" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  @click="toUseInfo()" />
							        <image  v-else :src="img('static/resource/images/default_headimg.png')" mode="aspectFill" class="w-[90rpx] h-[90rpx] rounded-full align-middle bg-[#fff]" @click="toUseInfo()"></image>
							        <view class="absolute bottom-[-17rpx] follow-style w-[34rpx] h-[34rpx] rounded-full bg-primary flex-center" v-if="!fineData.is_follow && !fineData.is_self" @click="handleFollow">
							            <text class="nc-iconfont nc-icon-jiahaoV6xx text-[#fff] text-[28rpx] mt-[2rpx]"></text>
							        </view>
							    </view>
							    <view class="mt-[58rpx] flex-col flex items-center" @click="handleLike(fineData)">
							        <text class="nc-iconfont nc-icon-xihuanV6mm text-[60rpx] text-primary" v-if="fineData.is_like"></text>
							        <text class="nc-iconfont nc-icon-xihuanV6mm text-[60rpx]" v-else></text>
							        <text class="mt-[10rpx] text-[24rpx] text-center">{{ fineData.like_num }}</text>
							    </view>
							    <view class="mt-[30rpx] flex-col flex items-center" @click="handleEvaluate">
							        <text class="nc-iconfont nc-icon-xiaoxiV6mm1 text-[60rpx]"></text>
							        <text class="mt-[10rpx] text-[24rpx] text-center">{{ fineData.community_comments_status ? fineData.comment_num : 0 }}</text>
							    </view>
							    <view class="mt-[30rpx] flex-col flex items-center" @click.stop="openShareFn(fineData)">
							        <text class="nc-iconfont nc-icon-fenxiangV6mm1 text-[60rpx]"></text>
							        <text class="mt-[10rpx] text-[22rpx] text-center">{{ t('share') }}</text>
							    </view>
							    <view class="relative mt-[30rpx]" v-if="fineData.is_self">
							        <view class="nc-iconfont nc-icon-a-gengduoV6xx-28 text-[66rpx]" @click="isEdit = !isEdit"></view>
							        <view class="edit-box absolute w-[180rpx] z-5000" v-if="isEdit">
							            <view class="w-[180rpx] mxa-h-[160rpx] rounded-[16rpx] py-[30rpx] px-[20rpx] bg-[#fff] text-[#333] rounded-[12rpx] relative flex flex-col justify-between box-border" style="box-shadow: rgba(0, 0, 0, 0.1) 0 1px 7px 0;">
							                <view class="flex items-center" v-if="fineData.status == 1 || fineData.status == -1" @click="redirect({url: '/addon/sow_community/pages/create', param: {content_id: fineData.content_id}})">
							                    <text class="nc-iconfont nc-icon-xiugaiV6xx text-[30rpx]"></text>
							                    <text class="ml-[18rpx] text-[24rpx]">{{ t('edit') }}</text>
							                </view>
							                <view class="border-0 border-solid border-b-[1rpx] border-[#ddd] my-[22rpx]" v-if="fineData.status == 1 || fineData.status == -1"></view>
							                <view class="flex items-center" @click="deleteFn()">
							                    <text class="nc-iconfont nc-icon-a-shanchu-fanggai2V6xx-36 text-[30rpx]"></text>
							                    <text class="ml-[18rpx] text-[24rpx]">{{ t('delete') }}</text>
							                </view>
							            </view>
							        </view>
							        <view class="fixed top-0 left-0 right-0 bottom-0 bg-transparent z-4000" v-if="isEdit" @click="isEdit = false"></view>
							    </view>
							    <view class="mt-[40rpx] flex-col flex items-center" @click="toBuy()" v-if="fineData.treasure_list && fineData.treasure_list.length">
							        <image class="w-[48rpx] h-[50rpx]" :src="img('/addon/sow_community/index/buy.png')" :mode="'aspectFill'"></image>
							    </view>
							</view>
							<!-- 底部信息 -->
							<view class="fixed left-[20rpx] tabbar-bottom text-[#fff]" v-if="fineData">
							    <view class="w-[584rpx] mb-[32rpx]">
							        <view class="w-[560rpx] leading-[42rpx] text-[30rpx] font-bold mb-[10rpx]" v-if="fineData.content_title">{{ fineData.content_title }}</view>
							        <scroll-view class="w-[560rpx] text-[26rpx] mb-[10rpx] max-h-[400rpx]" scroll-y>
							            <view class="text-[#fff] text-justify relative left-0 box-border break-all text-[26rpx] whitespace-pre-line leading-[1.5]">
							                <text class="text-[rgba(255,255,255,.9)]" v-if="fineData.show">{{ fineData.content_split}}</text>
										<rich-text class="text-[rgba(255,255,255,.9)]" v-else :nodes="fineData.content"></rich-text>
							                <!-- <text class="text-[rgba(255,255,255,.9)]" v-else>{{ fineData.show ? fineData.content_split : fineData.content}}</text> -->
 							               <template v-if="fineData.flag">
							                    <text class="absolute right-0 bottom-0"  v-if="fineData.show" @click="fineData.show = false">{{ t('expand') }}</text>
							                    <text class="absolute right-0 bottom-0" v-else @click="fineData.show = true">{{ t('packUp') }}</text>
							               </template>
							            </view>
							        </scroll-view>
							        <scroll-view class="w-[560rpx] leading-[36rpx] mb-[20rpx] font-500 text-[26rpx] max-h-[400rpx]" scroll-y  v-if="fineData.topic_list">
							            <text class="mr-[10rpx]" v-for="(item, index) in fineData.topic_list" @click="redirect({url: '/addon/sow_community/pages/topic_list', param: {topic_id: item.topic_id,topic_name:encodeURIComponent(item.topic_name)}})">#{{ item.topic_name }}</text>
							        </scroll-view>
							        <scroll-view :scroll-x="true" class="w-[584rpx] align-middle whitespace-nowrap rounded-[8rpx] overflow-hidden" v-if="Object.values(fineData).length && fineData.treasure_list && fineData.treasure_list.length">
							            <view class="inline-block mr-[20rpx]" v-for="(item, index) in fineData.treasure_list" :key="index" @click="redirect({url: item.treasure_url})">
							                <view class="w-[400rpx]  bg-[#fff] rounded-[12rpx] p-[15rpx] flex justify-between box-border">
							                    <image v-if="item.treasure_image" class="w-[102rpx] h-[102rpx] rounded-[8rpx]" :src="img(item.treasure_image)" :mode="'aspectFill'" @error="item.treasure_image='static/resource/images/diy/shop_default.jpg'"></image>
							                    <image v-else class="w-[102rpx] h-[102rpx] rounded-[8rpx]" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"></image>
							                    <view class="flex-1 ml-[16rpx] flex flex-col justify-between">
							                        <view class="w-[242rpx] using-hidden text-[24rpx] leading-[34rpx] mb-[7rpx] text-[#333]">{{ item.treasure_name }}</view>
							                        <view class="flex-between-center">
							                            <view class="text-[var(--price-text-color)] price-font flex items-baseline">
							                                <text class="text-[22rpx] font-500">￥</text>
							                                <text class="text-[32rpx] font-500">{{ parseFloat(item.treasure_price).toFixed(2).split('.')[0]  }}</text>
							                                <text class="text-[24rpx] font-500">.{{ parseFloat(item.treasure_price).toFixed(2).split('.')[1] }}</text>
							                            </view>
							                            <view class="w-[80rpx] h-[36rpx] rounded-[20rpx] bg-[var(--primary-color)] flex-center text-[#fff] text-[22rpx]">购买</view>
							                        </view>
							                    </view>
							                </view>
							            </view>
							        </scroll-view>
							    </view>
							    <view class="w-screen flex-center pb-[32rpx] mt-[24rpx] relative z-80 left-[-20rpx]" v-if="fineData.content_type == 1 && fineData.content_image && fineData.content_image.split(',').length > 1">
							        <view class="h-[6rpx] rounded-[2rpx]" :class="{'ml-[12rpx]': index}" :style="handleCss(index)" v-for="(item, index) in fineData.content_image.split(',')"></view>
							    </view>
							</view>
                        </swiper-item>
                    </swiper>

                </view>
                <view v-if="!communityList.length && loading" class="h-screen flex flex-col justify-center items-center">
                    <view class="text-[#fff] text-[32rpx] font-500 mb-[16rpx]">{{ t('noContent') }}</view>
                    <view class="text-[#fff] text-[26rpx] mb-[60rpx]">{{ t('createFirstSowCommunity') }}</view>
                    <view class="bg-primary w-[260rpx] h-[72rpx] rounded-full flex-center text-[#fff] text-[26rpx] font-500" @click="toLink()">{{ t('toCreate') }}</view>
                </view>
            </template>
        </view>
        <view class="fixed left-0 right-0 top-0 bottom-[110rpx] z-50000 bg-transparent" v-if="isShow" @click="isShow = false">
            <view class="fixed left-[12rpx] w-[214rpx]  top-[80rpx]"  :style="fixedStyle" >
                <view class="bubble_box" :style="{ borderColor: isFine ? 'transparent transparent rgba(51, 51, 51, 0.7) transparent' : 'transparent transparent #fff transparent'}"></view>
                <view class="w-[214rpx] h-[198rpx] rounded-[16rpx] py-[30rpx] px-[16rpx] rounded-[16rpx] relative flex flex-col justify-between box-border" style="box-shadow: rgba(0, 0, 0, 0.1) 0 1px 7px 0;" :class="{'bg-[#fff] text-[#333]': !isFine, 'bg-fine text-[#fff]': isFine }">
                    <view class="flex items-center pl-[16rpx] pb-[36rpx]" @click="toLink()">
                        <text class="nc-iconfont nc-icon-xiugaiV6xx text-[32rpx]"></text>
                        <text class="ml-[18rpx] text-[26rpx]">{{ t('createContent') }}</text>
                    </view>
                    <view class="border-0 border-solid border-b-[1rpx]" :style="{'border-color': isFine ? 'rgba(238, 238, 238, 0.1)': '#eee'}"></view>
                    <view class="flex items-center pl-[16rpx] pt-[36rpx]" @click="toMember()">
                        <text class="nc-iconfont nc-icon-woV6xx1 text-[32rpx]"></text>
                        <text class="ml-[18rpx] text-[26rpx]">{{ t('myInfo') }}</text>
                    </view>
                </view>
            </view>
        </view>
        <view class="fixed right-[20rpx]  z-100"  :style="{ bottom: `calc(150rpx + env(safe-area-inset-bottom))` }" @click="toLink()" v-if="!isFine">
            <image class="w-[80rpx] h-[80rpx] rounded-full create-shadow" :src="img('/addon/sow_community/index/create.png')" :mode="'aspectFill'"></image>
        </view>
        <tabbar addon="sow_community" />
        <evaluate-popup  ref="evaluateRef" @confirm="evaluateConfirm"/>
        <recommend-treasure-popup ref="recommendTreasureRef" />
        <!-- 分享 -->
        <share-popup ref="shareRef" :copyUrl="copyUrl"  :copyUrlParam="copyUrlParam" />
        <!-- 删除 -->
        <tips-popup  ref="contentRef" :title="t('deleteContentTips')" @confirm="contentDelete"/>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, computed, nextTick } from 'vue'
import { t } from '@/locale'
import { redirect, img, deepClone,getToken, pxToRpx, isWeixinBrowser } from '@/utils/common';
import { getFollowList, getCategoryList, getContentList, setContentLike, follow } from '@/addon/sow_community/api/follow';
import { browse, share, deleteContent } from '@/addon/sow_community/api/content';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import EvaluatePopup from '@/addon/sow_community/components/evaluate-popup/evaluate-popup.vue'
import nsFollowRecommend from '@/addon/sow_community/components/ns-follow-recommend/ns-follow-recommend.vue'
import RecommendTreasurePopup from '@/addon/sow_community/components/recommend-treasure-popup/recommend-treasure-popup.vue'
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'
import sharePopup from '@/components/share-popup/share-popup.vue'
import { useLogin } from '@/hooks/useLogin'
import useMemberStore from '@/stores/member'
import { useShare }from '@/hooks/useShare'
import useSystemStore from "@/stores/system";
 
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const loading = ref<boolean>(false);
const categoryPopup = ref(false);
const isFollow = ref(0); // 是否关注
const isFine = ref('')
const fineData = ref<any>({}) //精选当前信息
const isShow = ref(false); //左上角发布作品展示
const communityList = ref<any>([]) //内容列表
const leftList = ref<any>([]) //内容左
const rightList = ref<any>([])//内容右
const curLimitList = ref<any>([])
const curPage = ref(1)  //精选当前页
const totalPage = ref(1) //精选总页数
const limit = ref(10) //精选分页数
const current =ref(0)  //精选当前下标
const isPlay = ref(true) //第一个视频手动播放
const systemStore = useSystemStore()

// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

// 分享
const{setShare} = useShare()

onLoad((option: any) => {
    isFollow.value = option.is_follow || 0
})

onShow(() => {
    refresh()

})

const restFn = () =>{
    // 关注同步
    let val = uni.getStorageSync('featuredVideosItem');
    if(val && Object.keys(communityList.value).length){
        Object.keys(communityList.value[current.value]).forEach((item,index)=>{
            if(Object.keys(val).indexOf(item) != -1){
                communityList.value[current.value][item] = val[item];
                fineData.value[item] = val[item];
            }
        })
        uni.removeStorageSync('featuredVideosItem');
    }
    nextTick(()=>{
        // 删除内容同步
        let content = uni.getStorageSync('delContentItem');
        if(content && communityList.value.length){
            const filterList = (list: any) => list.filter((item: any )=> item.content_id !== content);
            communityList.value = filterList(communityList.value);
            leftList.value = filterList(leftList.value);
            rightList.value = filterList(rightList.value);
            uni.removeStorageSync('delContentItem');
        }
    })


}
const refresh = () => {
    getFollowListFn()
    getCategoryListFn()
    restFn();
    getCommunityListFn({num: 1, size: 10, endSuccess: () => {}})
}

const handleShow = () =>{
    isShow.value = !isShow.value
}

const navbarInnerStyle = ref('')
const headerStyle = ref('') //头部居中
const fixedStyle = ref('') //发布内容发位置
// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
let rightButtonWidth = systemStore.menuButtonInfo.width ? systemStore.menuButtonInfo.width * 2 + 'rpx' : '70rpx';
navbarInnerStyle.value += 'padding-right:calc(' + rightButtonWidth + ' + 30rpx);';
navbarInnerStyle.value += 'padding-top:' + systemStore.menuButtonInfo.top + 'px;';
navbarInnerStyle.value += 'padding-bottom: 8px;';
navbarInnerStyle.value+= 'height:' + systemStore.menuButtonInfo.height + 'px;';
fixedStyle.value += 'top:' + (systemStore.menuButtonInfo.top + 40) + 'px;'
headerStyle.value += 'margin-left:114rpx;'
// #endif

// #ifdef APP-PLUS
navbarInnerStyle.value += 'padding-top:' + systemStore.systemInfo.statusBarHeight + 'px;';
fixedStyle.value += 'top:' + (systemStore.systemInfo.statusBarHeight + uni.upx2px(80)) + 'px;'
// #endif

const categoryTop = computed(() => {
    let height = Object.keys(systemStore.menuButtonInfo).length && systemStore.menuButtonInfo.height ? (pxToRpx(Number(systemStore.menuButtonInfo.height)) + pxToRpx(systemStore.menuButtonInfo.top) + pxToRpx(8)) : 88;
    
    return height + 'rpx';
});
const mescrollTop = computed(() => {
    let baseTop = ''; // 先定义变量

    // #ifndef APP-PLUS
    if (isFollow.value == 1) {
        // 关注列表：用菜单按钮信息计算
        baseTop = Object.keys(systemStore.menuButtonInfo).length && systemStore.menuButtonInfo.height
            ? `${pxToRpx(Number(systemStore.menuButtonInfo.height)) + pxToRpx(systemStore.menuButtonInfo.top) + pxToRpx(8)}rpx` 
            : '88rpx';
    } else {
        // 非关注列表：基础计算 + 110rpx 额外间距
        baseTop = Object.keys(systemStore.menuButtonInfo).length && systemStore.menuButtonInfo.height 
            ? `${pxToRpx(Number(systemStore.menuButtonInfo.height)) + pxToRpx(systemStore.menuButtonInfo.top) + pxToRpx(8) + 110}rpx` 
            : '200rpx';
    }
    // #endif

    // #ifdef APP-PLUS
    if (isFollow.value == 1) {
        // 关注列表：状态栏高度 + 8px 间距
        baseTop = `${pxToRpx(Number(systemStore.systemInfo.statusBarHeight)) + pxToRpx(8)}rpx`;
    } else {
        // 非关注列表：状态栏高度 + 8px 间距 + 110rpx 额外间距
        baseTop = `${pxToRpx(Number(systemStore.systemInfo.statusBarHeight)) + pxToRpx(8) + 110}rpx`;
    }
    // #endif

    return baseTop;
});
// tab切换
const handleTab = (val: any, data: any = '') =>{
    isFollow.value = val
    if(val == 1){
        // 检测是否登录
        if (!userInfo.value) {
	        useLogin().setLoginBack({
		        url: '/addon/sow_community/pages/index',
		        param: {
			        is_follow: 1,
		        }
	        })
	        return false
        }
        getFollowListFn()

    }
    if(data){
        isFine.value = data
        curPage.value = 1
        totalPage.value = 1
        current.value = 0
        getVideoListFn()
    }else{
        isFine.value = ''
        communityList.value = []
        getMescroll().resetUpScroll();
    }

}

// 获取分类列表
const  curCategory = ref('')
const categoryList = ref<any>([])
const getCategoryListFn = () =>{
    getCategoryList().then((res: any) =>{
        categoryList.value = []
        const initData = { category_name: '全部', category_id: '' }
		categoryList.value.push(initData)
		categoryList.value = categoryList.value.concat(res.data)
    })
}

const handleCategory = (data: any) =>{
    curCategory.value = data
    communityList.value = []
    getMescroll().resetUpScroll();
    categoryPopup.value = false
}

interface mescrollStructure {
	num: number,
	size: number,
	endSuccess: Function,
	[propName: string]: any
}

const  getCommunityListFn = (mescroll: mescrollStructure) =>{
    loading.value = false;
    let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        category_id: curCategory.value,
        is_follow: isFollow.value
	};
    getContentList(data).then((res: any) => {
		const newArr = res.data.data.map((item: any,index:number) => {
            item.isPaused = false
            item.autoplay = index == 0 ? true: false
            item.show = false  //是否显示收起
            // 去除HTML标签后再判断长度
            const textContent = item.content.replace(/<[^>]*>/g, '')
            item.flag = textContent.length > 39 //是否显示展开收取
            if(textContent.length > 39){
                item.content_split = textContent.slice(0, 39) + '...'
                item.show = true
            }
            return item
        });
        curLimitList.value = deepClone(newArr)
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			communityList.value = []; //如果是第一页需手动制空列表
            rightList.value = []
            leftList.value = []
		}
		communityList.value = communityList.value.concat(newArr);
        if(isFollow.value == 0 && !isFine.value){
            loadData()
        }
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const loadData = () =>{

    if((leftList.value.length + rightList.value.length) == 0){
        leftList.value = communityList.value.filter((item: any, index: number) => index % 2 == 0)
        rightList.value = communityList.value.filter((item: any, index: number) => index % 2 == 1)
    }else{
        leftList.value = leftList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 0))
        rightList.value = rightList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 1))
    }
    let leftHeight = 0;
    let rightHeight = 0;
    while (true) {
        leftHeight = leftList.value.map((item: any) => {
            item.height = parseFloat(item.content_cover_height) * (172.5 / parseFloat(item.content_cover_width))
            return parseFloat(item.height.toFixed(2))
        }).reduce((pre: any, next: any) => {
            return parseFloat(pre.toFixed(2)) + parseFloat(next.toFixed(2)) + 52 // 52为底部文字的预估高度
        }, 0)
        rightHeight = rightList.value.map((item: any) => {
            item.height = parseFloat(item.content_cover_height) * (172.5 /parseFloat(item.content_cover_width))
            return parseFloat(item.height.toFixed(2))
        }).reduce((pre: any, next: any) => {
            return parseFloat(pre.toFixed(2)) + parseFloat(next.toFixed(2)) + 52 // 52为底部文字的预估高度
        }, 0)

        if((leftHeight - rightHeight) > 0){
            let last = leftList.value[leftList.value.length - 1]
            let lastHeight = (parseFloat(last.content_cover_height) * (172.5 /parseFloat(last.content_cover_width))).toFixed(2)
            if((leftHeight - rightHeight) > lastHeight){
                let lastItem = leftList.value.pop()
                rightList.value.push(lastItem)
            }else {
                return;
            }

        }else if((leftHeight - rightHeight) < 0){
            let last = rightList.value[rightList.value.length - 1]
            let lastHeight = (parseFloat(last.content_cover_height) * (172.5 /parseFloat(last.content_cover_width))).toFixed(2)
            if((rightHeight - leftHeight) > lastHeight){
                let lastItem = rightList.value.pop()
                leftList.value.push(lastItem)
            }else {
                return;
            }
        }else {
            return;
        }
    }
}


// 精选分页
const  getVideoListFn = (page:number = 1) =>{
    loading.value = false;
    let data: object = {
		page: page,
		limit: limit.value,
        is_follow: isFollow.value
	};
    getContentList(data).then((res: any) => {
		let newArr = res.data.data.map((item: any,index:number) => {
            item.isPaused = false
            item.show = false  //是否显示收起
            item.autoplay = index == 0  ? true : false;
            // 去除HTML标签后再判断长度
            const textContent = item.content.replace(/<[^>]*>/g, '')
            item.flag = textContent.length > 39 //是否显示展开收取
            if(textContent.length > 39){
                item.content_split = textContent.slice(0, 39) + '...'
                item.show = true
            }
            return item
        });

        curPage.value = res.data.current_page
        totalPage.value = res.data.last_page

		//设置列表数据
		if (Number(page) === 1) {
			communityList.value = []; //如果是第一页需手动制空列表
		}
		communityList.value = communityList.value.concat(newArr);
        fineData.value = deepClone(communityList.value[current.value])

        if(Number(page) === 1){
            myBrowseFn(fineData.value.content_id)
        }

        // 分享 - start
        let share = {
            title: fineData.value.content_title,
            desc: fineData.value.content,
            url: fineData.value.content_cover
        }
        setShare({
            wechat: {
                ...share
            },
            weapp: {
                ...share
            }
        });
        copyUrlFn(fineData.value)

		loading.value = true;
	}).catch(() => {
		loading.value = true;
	})
}

// 精选滑动
const swiperChange = (e: any) => {
    fineData.value = deepClone(communityList.value[e.detail.current])
    communityList.value.forEach((item: any, index: number) =>{
        item.autoplay = false
    })
    communityList.value[e.detail.current].autoplay = true;
    // 分享 - start
    let share = {
        title: fineData.value.content_title,
        desc: fineData.value.content,
        url: fineData.value.content_cover
    }
    setShare({
        wechat: {
            ...share
        },
        weapp: {
            ...share
        }
    });
    copyUrlFn(fineData.value)
	curImg.value = 0; // 重置滚动条指示到第一张
	const newIndex = e.detail.current;
	// 获取滑动方向
	const isForward = newIndex > current.value; // 是否向前滑动

	// 暂停上一个或下一个视频
	const prevOrNextIndex = isForward ? current.value : newIndex + 1;
    if(communityList.value[prevOrNextIndex] && communityList.value[prevOrNextIndex].content_type == 2){
        const prevOrNextVideoContext = uni.createVideoContext(`video${prevOrNextIndex}`);
	    prevOrNextVideoContext.pause();
        const prevVideo = communityList.value[current.value];
        if (prevVideo) {
            prevVideo.isPaused = true; // 确保上一个视频的状态为暂停
        }
    }
	// 更新当前索引
	current.value = newIndex;

	// 播放当前视频
	if(communityList.value[newIndex] &&  communityList.value[newIndex].content_type == 2){
        const currentVideoContext = uni.createVideoContext(`video${newIndex}`);
        currentVideoContext.play();
        const currentVideo = communityList.value[newIndex];
        currentVideo.isPaused = false; // 更新当前视频的播放状态为播放中
    }
    myBrowseFn(fineData.value.content_id)
    if(curPage.value < totalPage.value && e.detail.current !== 0 && !( e.detail.current % (limit.value - 1))){
        curPage.value++
        getVideoListFn(curPage.value)
    }
}

const togglePlay = (index: number, status: boolean = false) => {
    const video = uni.createVideoContext(`video${index}`);
    if (!video) return;

    const currentVideo = communityList.value[index];

    if(status){ //处理第一次视频进入手动播放
        currentVideo.isPaused = true;
        isPlay.value = false
    }

    if (currentVideo.isPaused) {
        video.play();
        currentVideo.isPaused = false; // 更新状态为播放中
    } else {
        video.pause();
        currentVideo.isPaused = true; // 更新状态为暂停中
    }
};

// 处理视频播放事件
const handlePlay = (index) => {
	console.log(`视频 ${index} 开始播放`);
};

// 处理视频暂停事件
const handlePause = (index) => {
	console.log(`视频 ${index} 暂停播放`);
};

// 我的足迹
const myBrowseFn = (content_id:any)=>{
	browse(content_id).then((res: any) => {})
}

// 关注的人
const followList = ref([])
const getFollowListFn = () => {
    let data: object = {
		page: 1,
		limit: 20
	}
    getFollowList(data).then((res: any) => {
        followList.value = res.data.data
    })
}

// 精选点击评论
const evaluateRef = ref()
const handleEvaluate = () =>{
    // 检测是否登录
	if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/index',
        })
		return false
	}
    evaluateRef.value.open(fineData.value)
}

const evaluateConfirm = (data: any) =>{
    if(fineData.value.content_id == data.content_id){
        fineData.value.comment_num = data.count
    }
    communityList.value.forEach((item: any, index: number) =>{
        if(item.content_id == data.content_id){
            item.comment_num = data.count
        }
    })
}

// 发布作品
const toLink = () =>{
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/index',
        })
        return false
    }
    redirect({url:'/addon/sow_community/pages/create'})
}

// 我的主页
const toMember = () =>{
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/index'
        })
        return false
    }
    redirect({url:'/addon/sow_community/pages/member',param: { member_id: userInfo.value.member_id }})
}

// 去详情
const toDetail = (data: any) =>{
    if(data.content_type == 1){
        redirect({url: '/addon/sow_community/pages/image/detail', param: { content_id: data.content_id }})
    }else{
        redirect({url: '/addon/sow_community/pages/video/detail', param: { content_id: data.content_id }})
    }
}

// 查看其他人的个人主页
const toUseInfo = () =>{
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/member',
            param: {member_id: fineData.value.member_id}
        })
        return false
    }
    redirect({url: '/addon/sow_community/pages/member',param: {member_id: fineData.value.member_id}})
}

// 点赞
const handleLike = (data: any) => {
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/index'
        })
        return false
    }
    data.is_like = !data.is_like
    data.is_like ? data.like_num++ : data.like_num--
    setContentLike({
        content_id: data.content_id,
        status: data.is_like ? 1 : 0
    }).then((res: any) => {
        if(isFine.value == 'fine'){
            communityList.value[current.value].is_like = data.is_like
            communityList.value[current.value].like_num = data.like_num
        }
    })
}

// 删除
const isEdit = ref(false)
const contentRef = ref()
const deleteFn = () =>{
    isEdit.value = false
    contentRef.value.open()

}
const contentDelete = () =>{
    deleteContent(fineData.value.content_id).then((res: any) =>{
        redirect({ url: '/addon/sow_community/pages/member', param: { member_id: userInfo.value.member_id }, mode: 'redirectTo' })
    })
}

// 购物袋弹框
const recommendTreasureRef = ref()
const toBuy = () =>{
    recommendTreasureRef.value.open(fineData.value.treasure_list)
}
// 精选图文底部导航
const curImg = ref(0)
const handleCss = computed(() =>{
    return (index: number) =>{
        let num = fineData.value.content_image.split(',').length
        let one = (750 - 40 - ( (num - 1) * 12)) / num
        let backgroundColor = index <= curImg.value ? 'rgb(255, 255, 255)' : 'rgba(255, 255, 255, 0.4)'
        return {
            width: `${one}rpx`,
            backgroundColor: backgroundColor
        }

    }
})

// 切换图文底部滚动条
const contentImgChange = (e: any) =>{
    curImg.value = e.detail.current
}

// 关注
const handleFollow = () =>{
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/index'
        })
        return false
    }

    follow({follow_member_id: fineData.value.member_id, is_follow: 1}).then(res =>{
        fineData.value.is_follow = !fineData.value.is_follow
        communityList.value[current.value].is_follow = fineData.value.is_follow
    })
}

/************* 分享弹框-start **************/
const shareRef: any = ref(null);
const copyUrl = ref('')
const copyUrlParam = ref('');

// 分享海报链接
const copyUrlFn = (data: any)=>{
    if(data.content_type == 1){
        copyUrl.value = '/addon/sow_community/pages/image/detail'
    }else{
        copyUrl.value = '/addon/sow_community/pages/video/detail'
    }
	copyUrlParam.value = '?content_id='+ data.content_id;
}

const openShareFn = (val: any)=>{
    copyUrlFn(val)
    share(val.content_id).then((res: any)=>{})
	shareRef.value.openShare()
}
/************* 分享弹框-end **************/
</script>

<style lang="scss" scoped>
.header_box {
	z-index: 10090;
}
.dark{
    background-color: #000;
    .header_box{
        background-color: initial;
        .icon{
            color: #fff;
        }
        .tab-item{
            color: hsla(0,0%,96.1%,.6);
        }
        .active-tab{
            font-weight: 500;
            font-size: 32rpx;
            color: #fff;
        }
    }
}

.light{
    .header_box{
        background-color: #fff;
        .tab-item{
            color: rgba(51,51,51,.6);
        }
        .active-tab{
            font-weight: 500;
            font-size: 32rpx;
            color: #333;
        }
    }
}
.category-bg{
    background: linear-gradient(180deg,#fff,#f5f5f5);
}

.bg-fine{
    background-color: rgba(51, 51, 51, 0.7);
}
.box-shadow{
    box-shadow: 0 0 10px 0 rgba(0,0,0,.1);
}
.cate-active{
    font-weight: 500;
    color: var(--primary-color);
    position: relative;
    &::after{
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 7px;
        width: 32rpx;
        height: 7rpx;
        border-radius: 6rpx;
        background-color: var(--primary-color);
    }
}

.label-select {
	color: var(--primary-color);
	border-color: var(--primary-color);
	background-color: var(--primary-color-light);
}

.biserial-page{
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-gap: 10px;
}
.bg-color{
    background: hsla(0,0%,40%,.5)
}
.follow-style{
    left: 50%;
    transform: translateX(-50%);
}
.bubble_box{
    content: "";
    position: absolute;
    left: 11px;
    top: -10px;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 10px 10px 10px;
}

.edit-box{
    top: 50%;
    left: -200rpx;
    transform: translateY(-50%);
    &::after{
        content: "";
        position: absolute;
        right: -10rpx;
        top: 50%;
        transform: translatey(-50%) rotate(45deg);
        width: 20rpx;
        height: 20rpx;
        background-color: #fff;
    }
}
:deep(.tab-bar-placeholder) {
	display: none !important;
}
:deep(.u-tabbar__placeholder) {
	display: none !important;
}
.tabbar-bottom{
    bottom: calc(constant(safe-area-inset-bottom) + 100rpx);
    bottom: calc(env(safe-area-inset-bottom) + 100rpx);
}
.bottom{
    bottom: calc(constant(safe-area-inset-bottom) + 172rpx);
    bottom: calc(env(safe-area-inset-bottom) + 172rpx);
}
.guanzhu-swiper :deep(.uni-swiper-dot) {
    width: 12rpx;
    height: 12rpx;
}
</style>
<style>
/* #ifdef MP-WEIXIN */
wx-swiper .wx-swiper-dot{
    width: 12rpx;
    height: 12rpx;
}
/* #endif */
.create-shadow{
    box-shadow: 0 0 14rpx rgba(0,0,0,.1);
}
</style>
