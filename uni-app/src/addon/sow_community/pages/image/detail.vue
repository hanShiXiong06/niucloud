<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden" :style="themeColor()" v-if="Object.keys(detail).length">
        <template v-if="detail.status == 1 || detail.status == -1 || detail.status == -2">
            <view class="sidebar-margin card-template my-[var(--top-m)] flex items-center" v-if="detail.status == 1">
                <image class="w-[66rpx] h-[66rpx] align-middle" :src="img('/addon/sow_community/detail/audit.png')" :mode="'aspectFill'"></image>
                <view class="flex-1 ml-[20rpx]">
                    <view class="text-[26rpx] text-priamry font-500 leading-[36rpx] mb-[10rpx]">{{ t('auditTitle') }}</view>
                    <view class="text-[24rpx] text-[#666]">{{ t('auditdesc') }}</view>
                </view>
            </view>
            <view class="sidebar-margin card-template my-[var(--top-m)] flex items-center" v-if="detail.status == -1">
                <image class="w-[66rpx] h-[66rpx] align-middle" :src="img('/addon/sow_community/detail/audit.png')" :mode="'aspectFill'"></image>
                <view class="flex-1 ml-[20rpx]">
                    <view class="text-[26rpx] text-priamry font-500 leading-[36rpx] mb-[10rpx]">{{ t('auditrefuse') }}</view>
                    <view class="text-[24rpx] text-[#666]">{{ detail.refuse_reason }}</view>
                </view>
            </view>
            <view class="sidebar-margin card-template my-[var(--top-m)] flex items-center" v-if="detail.status == -2">
                <image class="w-[66rpx] h-[66rpx] align-middle" :src="img('/addon/sow_community/detail/audit.png')" :mode="'aspectFill'"></image>
                <view class="flex-1 ml-[20rpx]">
                    <view class="text-[26rpx] text-priamry font-500 leading-[36rpx] mb-[10rpx]">{{ t('offContent') }}</view>
                    <view class="text-[24rpx] text-[#666]">{{ t('offContentDesc') }}</view>
                </view>
            </view>
        </template>
        <view class="sidebar-margin card-template my-[var(--top-m)]">
            <view class="flex-between-center  pb-[var(--pad-top-m)]">
                <view class="flex items-center" v-if="detail.member" @click="redirect({url: '/addon/sow_community/pages/member', param: {member_id: detail.member_id}})">
                    <u-avatar :src="img(detail.member.headimg)" size="30" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"/>
                    <text class="text-[30rpx] font-500 ml-[16rpx] mr-[30rpx] leading-[42rpx] using-hidden">{{ detail.member.nickname }}</text>
                </view>
                <view class="flex items-center">
                    <template v-if="!detail.is_self">
                        <view class="w-[120rpx] h-[48rpx] rounded-full flex-center border-solid border-[2rpx] border-[#eee]  text-[22rpx] text-[#999] box-border" v-if="detail.is_follow"  @click="cancelFollow()">{{ t('followed') }}</view>
                        <view class="flex-center w-[120rpx] h-[48rpx] border-primary border-solid border-[2rpx] rounded-full box-border" v-else @click="followFn()">
                            <text class="nc-iconfont nc-icon-jiahaoV6xx text-[24rpx] text-primary mr-[2rpx] pt-[4rpx]"></text>
                            <text class="text-[22rpx] text-primary">{{ t('follow') }}</text>
                        </view>
                    </template>
                    <template v-if="detail.is_self">
                        <view class="relative">
                            <view class="w-[100rpx] h-[44rpx] border-solid border-[2rpx] border-[#ccc] rounded-full flex-center text-[22rpx] box-border" @click="isEdit = !isEdit">{{ t('manage') }}</view>
                            <view class="edit-box absolute w-[180rpx] z-50" v-if="isEdit">
                                <view class="w-[180rpx] mxa-h-[160rpx] rounded-[16rpx] py-[30rpx] px-[20rpx] bg-[#fff] text-[#333] rounded-[12rpx] relative flex flex-col justify-between box-border" style="box-shadow: rgba(0, 0, 0, 0.1) 0 1px 7px 0;">
                                    <view class="flex items-center" v-if="detail.status == 1 || detail.status == -1 || detail.status == -2" @click="redirect({url: '/addon/sow_community/pages/create', param: {content_id: detail.content_id}})">
                                        <text class="nc-iconfont nc-icon-xiugaiV6xx text-[30rpx]"></text>
                                        <text class="ml-[18rpx] text-[24rpx]">{{ t('edit') }}</text>
                                    </view>
                                    <view class="border-0 border-solid border-b-[1rpx] border-[#ddd] my-[22rpx]" v-if="detail.status == 1 || detail.status == -1 || detail.status == -2"></view>
                                    <view class="flex items-center" @click="deleteFn()">
                                        <text class="nc-iconfont nc-icon-a-shanchu-fanggai2V6xx-36 text-[30rpx]"></text>
                                        <text class="ml-[18rpx] text-[24rpx]">{{ t('delete') }}</text>
                                    </view>
                                </view>
                            </view>
							<view class="fixed top-0 left-0 right-0 bottom-0 bg-transparent z-40" v-if="isEdit" @click="isEdit = false"></view>
                        </view>
                    </template>
                </view>
            </view>
            <view>
                <view v-if="detail.content_image" class="relative">
                    <swiper class="h-[710rpx] guanzhu-swiper mb-[30rpx]" interval="3000" autoplay="true"  circular="true" :indicator-dots="detail.content_image_thumb_mid.length > 1 ? true : false" indicator-color="rgba(255,255,255,.4)" indicator-active-color="rgb(255,255,255)">
                        <swiper-item class="guanzhu-item" v-for="(item,index) in detail.content_image_thumb_mid" :key="index">
                            <image class="w-[100%] h-[710rpx] rounded-[var(--rounded-big)] align-middle" :src="img(item)" :mode="'aspectFill'" @click="handleImg(item,index)"></image>
                        </swiper-item>
                    </swiper>
                </view>
                <view class=" text-[28rpx] font-500 mb-[20rpx] leading-[40rpx]">{{ detail.content_title }}</view>
                <view class="u-content">
                    <u-parse :content="detail.content" :tagStyle="{img: 'vertical-align: top;',p:'overflow: hidden;word-break:break-word;' }"></u-parse>
                </view>
                <view class="flex items-center mb-[30rpx] flex-wrap" v-if="detail.topic_list && detail.topic_list.length">
                    <text class="text-[24rpx] font-500 text-primary mr-[10rpx] mb-[10rpx]" v-for="(item,index) in detail.topic_list" @click="redirect({url: '/addon/sow_community/pages/topic_list', param: {topic_id: item.topic_id,topic_name:encodeURIComponent(item.topic_name)}})">#{{ item.topic_name }}</text>
                </view>
                <view class="text-[22rpx] text-[#999]">{{ detail.create_time }}</view>
                <!-- <scroll-view :scroll-x="true" class="align-middle whitespace-nowrap w-full mt-[20rpx]" v-if="detail.treasure_list && detail.treasure_list.length">
                    <view class="inline-block " :class="{'mr-[20rpx]': (index + 1) != detail.treasure_list.length  }" v-for="(item,index) in detail.treasure_list">
                        <view class="p-[20rpx] w-[662rpx] box-border bg-[#fff] border-solid border-[2rpx] border-[#eee] rounded-[var(--rounded-small)] flex justify-between" @click="redirect({url: item.treasure_url})">
                            <image v-if="item.treasure_image" class="w-[104rpx] h-[104rpx] rounded-[12rpx]" :src="img(item.treasure_image)" :mode="'aspectFill'" @error="item.treasure_image='static/resource/images/diy/shop_default.jpg'"></image>
                            <image v-else class="w-[104rpx] h-[104rpx] rounded-[12rpx] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"></image>
                            <view class="flex-1  ml-[16rpx] flex flex-col justify-between">
                                <view class="text-[#333] text-[24rpx] leading-[36rpx] using-hidden">{{ item.treasure_name }}</view>
                                <view class="flex-between-center">
                                    <view class="text-[#333] price-font flex items-baseline">
                                        <text class="text-[22rpx] font-500">￥</text>
                                        <text class="text-[36rpx] font-500">{{ parseFloat(item.treasure_price).toFixed(2).split('.')[0] }}</text>
                                        <text class="text-[22rpx] font-500">.{{ parseFloat(item.treasure_price).toFixed(2).split('.')[1] }}</text>
                                    </view>
                                    <view class="w-[110rpx] h-[44rpx] border-solid border-[2rpx] border-primary box-border text-center leading-[42rpx] rounded-full text-[22rpx] text-primary">{{ t('buy') }}</view>
                                </view>
                            </view>
                        </view>
                    </view>
                </scroll-view> -->
                <scroll-view :scroll-x="true" class="align-middle whitespace-nowrap w-full mt-[20rpx]" v-if="detail.treasure_list && detail.treasure_list.length">
                    <view class="inline-block " :class="{'mr-[20rpx]': (index + 1) != detail.treasure_list.length  }" v-for="(item,index) in detail.treasure_list">
                        <view class="p-[20rpx] box-border bg-[#fff] border-solid border-[2rpx] border-[#eee] rounded-[var(--rounded-small)] flex justify-between" :class="{'w-[662rpx]': detail.treasure_list.length == 1, 'w-[590rpx]': detail.treasure_list.length > 1}" @click="redirect({url: item.treasure_url})">
                            <image v-if="item.treasure_image" class="w-[104rpx] h-[104rpx] rounded-[12rpx]" :src="img(item.treasure_image)" :mode="'aspectFill'" @error="item.treasure_image='static/resource/images/diy/shop_default.jpg'"></image>
                            <image v-else class="w-[104rpx] h-[104rpx] rounded-[12rpx] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"></image>
                            <view class="flex-1  ml-[16rpx] flex flex-col justify-between">
                                <view class="text-[#333] text-[24rpx] leading-[36rpx] using-hidden">{{ item.treasure_name }}</view>
                                <view class="flex-between-center">
                                    <view class="text-[#333] price-font flex items-baseline">
                                        <text class="text-[22rpx] font-500">￥</text>
                                        <text class="text-[36rpx] font-500">{{ parseFloat(item.treasure_price).toFixed(2).split('.')[0] }}</text>
                                        <text class="text-[22rpx] font-500">.{{ parseFloat(item.treasure_price).toFixed(2).split('.')[1] }}</text>
                                    </view>
                                    <view class="w-[110rpx] h-[44rpx] border-solid border-[2rpx] border-primary box-border text-center leading-[42rpx] rounded-full text-[22rpx] text-primary">{{ t('buy') }}</view>
                                </view>
                            </view>
                        </view>
                    </view>
                </scroll-view>
            </view>
        </view>
        <!-- 评论 -->
        <view class="comment-wrap sidebar-margin card-template mb-[var(--top-m)]" v-if="detail.community_comments_status">
            <view class="flex items-center mb-[20rpx]">
                <text class="text-[30rpx] font-500 mr-[6rpx]">{{ t('comment') }}</text>
                <text class="text-[24rpx] text-[#999]">({{ detail.comment_num }})</text>
            </view>
            <mescroll-body ref="mescrollRef" top="0"  @init="mescrollInit" :down="{ use: false }" @up="getCommentListFn">
                <view v-if="commentList.length">
                    <view class="flex justify-between" :class="{'mb-[30rpx]':(index+1) != commentList.length }" v-for="(item,index) in commentList" :key="index" @click="handleReply(item)">
                        <u-avatar v-if="item.member" :src="img(item.member.headimg)" size="44" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')" @click="redirect({url: '/addon/sow_community/pages/member',param: {member_id: item.member_id}})"/>
                        <view class="flex-1 ml-[16rpx]">
                            <view class="text-[24rpx] text-[#666] mb-[12rpx] leading-[34rpx]" v-if="item.member">{{ item.member.nickname }}</view>
                            <view class="text-[26rpx] leading-[36rpx] mb-[20rpx]">{{ item.comment_content }}</view>
                            <view class="flex-between-center">
                                <view class="flex items-center">
                                    <text class="text-[22rpx] text-[#999] mr-[20rpx]">{{ item.create_time }}</text>
                                    <text class="text-[22rpx] text-primary mr-[30rpx]">{{ t('reply') }}</text>
                                    <text class="text-[22rpx] text-[#666]" v-if="userInfo && userInfo.member_id == item.member_id" @click.stop="handleDelete(item.comment_id)">{{ t('delete') }}</text>
                                </view>
                                <view class="flex items-center" @click.stop="likeCommentFn(item)">
                                    <text class="nc-iconfont nc-icon-dianzanV6mm text-primary text-[24rpx] text-[#999] mr-[10rpx]" v-if="item.is_like"></text>
                                    <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] text-[#999] mr-[10rpx]" v-else></text>
                                    <text class="text-[22rpx] text-[#999] min-w-[15rpx] text-center">{{ item.like_num }}</text>
                                </view>
                            </view>
                            <view class="mt-[20rpx]">
                                <template v-if="item.child_list && item.child_list.length">
                                    <view>
                                        <view class="flex justify-between mb-[20rpx]" v-for="(subItm,subIndex) in item.child_list" :key="subIndex" @click.stop="handleReply(subItm)">
                                            <u-avatar :src="img(subItm.member.headimg)" size="27" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  @click="redirect({url: '/addon/sow_community/pages/member',param: {member_id: subItm.member_id}})"/>
                                            <view class="flex-1 ml-[14rpx]">
                                                <view class="text-[22rpx] text-[#666] mb-[12rpx] flex items-center leading-[34rpx]">
                                                    <text class="using-hidden">{{ subItm.member.nickname }}</text>
                                                    <view class="ml-[6rpx] flex items-center" v-if="subItm.replyMember">
                                                        <text  class="nc-iconfont nc-icon-a-xiangyouV6mm text-[20rpx] mt-[2rpx] mr-[4rpx]"></text>
                                                        <text  class="text-[#666] using-hidden ">{{ subItm.replyMember.nickname }}</text>
                                                    </view>

                                                </view>
                                                <view class="text-[26rpx] leading-[36rpx] mb-[20rpx]">{{ subItm.comment_content }}</view>
                                                <view class="flex-between-center">
                                                    <view class="flex items-center">
                                                        <text class="text-[22rpx] text-[#999] mr-[20rpx]">{{ subItm.create_time }}</text>
                                                        <text class="text-[22rpx] text-primary mr-[30rpx]">{{ t('reply') }}</text>
                                                        <text class="text-[22rpx] text-[#666]" v-if="userInfo && userInfo.member_id == subItm.member_id" @click.stop="handleDelete(subItm.comment_id)">{{ t('delete') }}</text>
                                                    </view>
                                                    <view class="flex items-center" @click.stop="likeCommentFn(subItm)">
                                                        <text class="nc-iconfont nc-icon-dianzanV6mm text-primary text-[24rpx] text-[#999] mr-[10rpx]" v-if="subItm.is_like"></text>
                                                        <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] text-[#999] mr-[10rpx]" v-else></text>
                                                        <text class="text-[22rpx] text-[#999] min-w-[15rpx] text-center">{{ subItm.like_num }}</text>
                                                    </view>
                                                </view>
                                            </view>
                                        </view>
                                    </view>
                                    <view class="flex items-center" v-if="item.secondFlag">
                                        <view class="flex items-center text-[#666] text-[24rpx]" v-if="(item.child_list.length - 1) < item.total" @click.stop="addMoreComment(item)">
                                            <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                            <text class="pl-[16rpx] pr-[4rpx]">{{ t('expandMore') }}</text>
                                            <text class="nc-iconfont nc-icon-xiaV6xx text-[24rpx]"></text>
                                        </view>
                                        <view class="flex items-center text-[#666] text-[24rpx] ml-[30rpx]" @click.stop="packUp(item)">
                                            <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                            <text class="pl-[16rpx] pr-[4rpx]">{{ t('packUp') }}</text>
                                            <text class="nc-iconfont nc-icon-shangV6xx-1 text-[24rpx]"></text>
                                        </view>
                                    </view>
                                </template>
                                <view class="flex items-center text-[#666] text-[24rpx]" v-if="(item.reply_num > 1) && !item.secondFlag"  @click.stop="addSecondComment(item)">
                                    <text class="w-[40rpx] h-[2rpx] bg-[#d8d8d8]"></text>
                                    <text class="pl-[16rpx] pr-[4rpx]">{{t('expand')}}{{item.reply_num - 1}}{{ t('replysWord') }}</text>
                                    <text class="nc-iconfont nc-icon-xiaV6xx text-[24rpx]"></text>
                                </view>
                            </view>
                        </view>
                    </view>
                </view>
                <mescroll-empty v-if="!commentList.length && commentLoading" :option="{tip : t('noComments'), icon: img('/addon/sow_community/default_comment.jpg')}"></mescroll-empty>
            </mescroll-body>
        </view>
        <view class="tab-bar-placeholder h-[100rpx]" :class="{'h-[200rpx]': flag }"></view>
        <view class="fixed left-0 right-0 bottom-0 z-10  bg-[#fff] tab-bar">
            <view class="h-[100rpx] bg-[#fff] px-[20rpx] flex items-center box-border flex-1" v-if="flag">
                <input class="flex-1 h-[66rpx] leading-[66rpx] bg-[#f6f6f6] rounded-full mr-[24rpx] px-[24rpx]" maxlength="50" type="text" v-model="keywords" :placeholder="Object.values(curComment).length ? `${t('reply')}：${curComment.member.nickname}` : t('keywordsPlaceholder')" placeholderClass="text-[var(--text-color-light9)] text-[24rpx] leading-[66rpx]" confirm-type="search" cursor-spacing="8" :focus="focusInput"  @blur="onBlur">
                <button hover-class="none" class="w-[120rpx] h-[66rpx] primary-btn-bg text-center leading-[66rpx] text-[24rpx] text-[#fff] rounded-full" @click="handleSend()">{{ t('send') }}</button>
            </view>
            <view class="h-[100rpx] flex-between-center">
                <view class="h-full flex-center flex-1 flex-shrink-0" v-if="detail.community_comments_status" @click="handleInputClick">
                    <text class="nc-iconfont nc-icon-dianxinxiV6xx1 text-[32rpx] mr-[14rpx]"></text>
                    <text class="text-[24rpx]">{{ t('comment') }}</text>
                </view>
                <view class="h-full flex-center flex-1 flex-shrink-0" v-if="detail.status == 2 || detail.status == 0" @click="openShareFn()">
                    <text class="nc-iconfont nc-icon-fenxiangV6xx text-[32rpx] mr-[14rpx]"></text>
                    <text class="text-[24rpx]">{{ t('transpond') }}</text>
                </view>
                <view class="h-full flex-center flex-1 flex-shrink-0"  @click="handleLike()">
                    <text class="nc-iconfont nc-icon-dianzanV6mm text-primary text-[32rpx]" v-if="detail.is_like"></text>
                    <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[32rpx]" v-else></text>
                    <text class="text-[24rpx] ml-[14rpx] min-w-[60rpx]">{{ t('like') }}({{ detail.like_num }})</text>
                </view>
            </view>
        </view>
        <!-- 分享 -->
        <share-popup ref="shareRef" :copyUrlParam="copyUrlParam" />
        <tips-popup ref="followRef" :title="t('unfollowTips')" @confirm="handleCancelFollow"/>
        <tips-popup ref="contentRef" :title="t('deleteContentTips')" @confirm="contentDelete"/>
        <tips-popup ref="commentRef" :title="t('deleteCommentTips')" @confirm="commentDelete"/>
    </view>
</template>
<script setup lang="ts">
import { ref, computed ,nextTick} from 'vue'
import { t } from '@/locale'
import { img, getToken, redirect, deepClone, goback,handleOnloadParams } from '@/utils/common';
import { onLoad,onShow,onUnload, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { getContentDetail, browse, getCommentList, setComment, setCommentLike, deleteComment, deleteContent, share } from '@/addon/sow_community/api/content';
import {  follow, setContentLike } from '@/addon/sow_community/api/follow';
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import sharePopup from '@/components/share-popup/share-popup.vue'
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'
import { useShare }from '@/hooks/useShare'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const loading = ref<Boolean>(true)
const optionLoading = ref<Boolean>(false)
const commentLoading = ref<Boolean>(false)
const contentId = ref(0)
const detail = ref<any>({})
const commentList = ref<any>([]) // 评论列表
const flag = ref<Boolean>(false)  //是否显示评论
const focusInput = ref(false); // 控制输入框焦点
const keywords = ref('') //评论内容
const curComment = ref<any>({}) //当前评论内容
const isEdit = ref(false) //管理操作展示

onLoad((options: any) => {
    // #ifdef MP-WEIXIN
    // 处理小程序场景值参数
    options = handleOnloadParams(options);
    // #endif
    contentId.value = options.content_id || 0;
})
onShow(() =>{
    getContentDetailFn()
})

// 分享
const{setShare} = useShare()

const getContentDetailFn = () => {
    loading.value = true;
    getContentDetail(contentId.value).then((res: any) => {
        if (!res.data || JSON.stringify(res.data) === '[]') {
            let goBackParameter = {
                url: '/addon/sow_community/pages/index',
                title: '找不到该内容',
                mode: 'reLaunch'
            };
            goback(goBackParameter)
            return false
        }

        detail.value = res.data;
        loading.value = false

        // 分享 - start
        let share = {
            title: detail.value.content_title,
            desc: detail.value.content,
            url: detail.value.content_cover
        }

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

        if (getToken() && (detail.value.status == 2 || detail.value.status == 0)) {
            // 我的足迹
            myBrowseFn(detail.value.content_id);
        }
    }).catch(() => {
        loading.value = false
    })
}

// 关注
const followFn = () =>{
    // 检测是否登录
	if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/image/detail',
            param: {
                content_id: detail.value.content_id,
            }
        })
		return false
	}
    if (optionLoading.value) return
	optionLoading.value = true
    detail.value.is_follow = 1
    follow({follow_member_id: detail.value.member_id, is_follow: 1}).then(res =>{
        optionLoading.value = false
        uni.showToast({
            title: '关注成功',
            icon: 'none'
        })
    })
}

// 我的足迹
const myBrowseFn = (content_id:any)=>{
	browse(content_id).then((res: any) => {})
}

// 取消关注
const followRef = ref()
const cancelFollow = () =>{
    // 检测是否登录
	if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/image/detail',
            param: {
                content_id: detail.value.content_id,
            }
        })
		return false
	}
    followRef.value.open()
}

const handleCancelFollow = () =>{
    detail.value.is_follow = 0
    follow({follow_member_id: detail.value.member_id, is_follow: 0}).then(res =>{
        optionLoading.value = false
    })
}

// 内容点赞
const handleLike = () => {
    // 检测是否登录
	if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/image/detail',
            param: {
                content_id: detail.value.content_id,
            }
        })
		return false
	}
    if(detail.value.status == 1){
        uni.showToast({
            title: '内容在审核中',
            icon: 'none'
        })
        return
    }
    if(detail.value.status == -1){
        uni.showToast({
            title: '内容被拒绝',
            icon: 'none'
        })
        return
    }
    detail.value.is_like = ! detail.value.is_like
    detail.value.is_like ?  detail.value.like_num++ :  detail.value.like_num--
    setContentLike({
        content_id:  detail.value.content_id,
        status:  detail.value.is_like ? 1 : 0
    }).then((res: any) => {

    })
}

interface mescrollStructure {
	num: number,
	size: number,
	endSuccess: Function,
	[propName: string]: any
}
const getCommentListFn = (mescroll: mescrollStructure) =>{
    commentLoading.value = false
    let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        content_id: detail.value.content_id
	};
    getCommentList(data).then((res: any) =>{
        let newArr = res.data.data.map((item: any) => {
            item.secondFlag = false
            item.cur_page = 1
            item.total  = 0
            return item
        });
            //设置列表数据
		if (Number(mescroll.num) === 1) {
			commentList.value = []; //如果是第一页需手动制空列表
		}
        commentList.value = commentList.value.concat(newArr);
        mescroll.endSuccess(newArr.length);
        commentLoading.value = true;
    }).catch(() => {
        commentLoading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

// 评论
const handleSend = ()  =>{
    if(detail.value.status == 1){
        uni.showToast({
            title: '内容在审核中',
            icon: 'none'
        })
        return
    }
    if(detail.value.status == -1){
        uni.showToast({
            title: '内容被拒绝',
            icon: 'none'
        })
        return
    }
    if(keywords.value == ''){
        uni.showToast({
            title: '请输入评论内容',
            icon: 'none'
        })
        return false
    }
    if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/image/detail',
            param: {
                content_id: detail.value.content_id,
            }
        })
		return false
	}
    const obj = {
		content_id: detail.value.content_id,
		comment_content: keywords.value,
		parent_comment_id: curComment.value.parent_comment_id ? curComment.value.parent_comment_id:  curComment.value.comment_id,
        reply_member_id: curComment.value.parent_comment_id ? curComment.value.member_id : 0,
        level: curComment.value.parent_comment_id ?  curComment.value.level : 0
	};
    setComment(obj).then((res: any) =>{
        flag.value = false
        keywords.value = ''
		if(!detail.value.comment_moderation_status){
			if(Object.keys(curComment.value).length){
			    commentList.value.forEach((item: any) => {
			        if(item.comment_id == res.data.parent_comment_id){
			            item.child_list.unshift(res.data)
			        }
			    });
			}else{
			    commentList.value.unshift(res.data)
			}
		}

        if(!detail.value.comment_moderation_status){
            detail.value.comment_num++
        }

    })
}

// 回复
const handleReply = (val: any) => {
    flag.value = true
    curComment.value = deepClone(val)
	focusInput.value = false; // 重置焦点
	nextTick(() => {
	    focusInput.value = true; // 手动设置输入框获取焦点
	});
}

// 输入框失去焦点
const onBlur = () => {
    focusInput.value = false; // 重置输入框焦点
};

const handleInputClick = () => {
    flag.value = true; // 打开评论框
    focusInput.value = false; // 先重置输入框焦点
    nextTick(() => {
        focusInput.value = true; // 再设置输入框获取焦点，唤起输入法
    });
};

// 评论点赞
const likeCommentFn = (data: any) => {
    // 检测是否登录
	if (!userInfo.value) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/image/detail',
            param: {
                content_id: detail.value.content_id,
            }
        })
		return false
	}
    data.is_like = !data.is_like
    data.is_like ? data.like_num++ : data.like_num--
    setCommentLike({
        comment_id:  data.comment_id,
        status:  data.is_like ? 1 : 0
    }).then((res: any) => {
    })
}

// 删除评论
const commentRef = ref()
const commentId = ref<any>(0)
const handleDelete = (id: number) =>{
    commentId.value = id
    commentRef.value.open()
}
const commentDelete = () =>{
    deleteComment(commentId.value).then(res =>{
        detail.value.comment_num--
        getMescroll().resetUpScroll();
    })
}
// 展示二级评论
const addSecondComment = (data: any) =>{
    let obj: object = {
		page: data.cur_page,
		limit: 20,
        content_id: detail.value.content_id,
        parent_comment_id:data.comment_id,
        first_comment_id:data.first_comment_id
	};
    getCommentList(obj).then((res: any) =>{
        data.cur_page = res.data.current_page
        data.total = res.data.total
        data.secondFlag = true
        commentList.value.map((item: any) =>{
            if(item.comment_id == data.comment_id){
                item.child_list = item.child_list.concat(deepClone(res.data.data))
            }
            return item
        })
    })
}

// 展开更多评论
const addMoreComment = (data: any) =>{
    data.cur_page ++
    let obj: object = {
		page: data.cur_page,
		limit: 20,
        content_id: detail.value.content_id,
        parent_comment_id:data.comment_id,
        first_comment_id:data.first_comment_id
	};
    getCommentList(obj).then((res: any) =>{
        commentList.value.map((item: any) =>{
            if(item.comment_id == data.comment_id){
                item.child_list =  item.child_list.concat(deepClone(res.data.data))
            }
            return item
        })
    })
}

// 收起
const packUp = (data: any) =>{
    data.cur_page = 1
    data.total_page = 1
    data.secondFlag = false
    commentList.value.map((item: any) =>{
        if(item.comment_id == data.comment_id){
            item.child_list = item.child_list.slice(0, 1)
        }
        return item
    })
}
// 删除内容
const contentRef = ref()
const deleteFn = () =>{
    isEdit.value = false
    contentRef.value.open()
}
const contentDelete = () =>{
    deleteContent(detail.value.content_id).then((res: any) =>{
        let obj = ''
        if (uni.getStorageSync('delContentItem')) {
            obj = uni.getStorageSync('delContentItem')
        }
        obj = detail.value.content_id
        uni.setStorageSync('delContentItem', obj);
        redirect({ url: '/addon/sow_community/pages/member', param: { member_id: userInfo.value.member_id }, mode: 'redirectTo' })
    })
}

const handleImg = (url: any,index: number) => {
	let tmp = [];
	if(detail.value.content_image){
        tmp = detail.value.content_image.split(',').map((item: any) => {
            return img(item)
        })
    }
	uni.previewImage({
		current: index,
		urls: tmp,
		indicator: "number",
		loop: true
	})

}

// 关闭预览图片
onUnload(()=>{
    // #ifdef  H5 || APP
    try {
        uni.closePreviewImage()
    }catch (e) {

    }
    // #endif
})

/************* 分享弹框-start **************/
const shareRef: any = ref(null);
const copyUrlParam = ref('');

// 分享海报链接
const copyUrlFn = ()=>{
	copyUrlParam.value = '?content_id='+ detail.value.content_id;
}

const openShareFn = ()=>{
    share(detail.value.content_id).then((res: any)=>{})
	shareRef.value.openShare()
}
/************* 分享弹框-end **************/

</script>
<style lang="scss" scoped>
.edit-box{
    top: 58rpx;
    left: 50%;
    transform: translateX(-50%);
    &::after{
        content: "";
        position: absolute;
        left: 50%;
        top: -10rpx;
        transform: translateX(-50%) rotate(45deg);
        width: 20rpx;
        height: 20rpx;
        background-color: #fff;

    }
}

.comment-wrap :deep(.mescroll-body.mescroll-render-touch){
    min-height: auto !important;
}
.tab-bar-placeholder {
	padding-bottom: constant(safe-area-inset-bottom);
	padding-bottom: env(safe-area-inset-bottom);
}
.tab-bar {
	padding-bottom: constant(safe-area-inset-bottom);
	padding-bottom: env(safe-area-inset-bottom);
}
.empty-page{
    width: 100%;
    height: 610rpx;
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
</style>
