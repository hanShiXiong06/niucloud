<template>
    <view class="bg-[#000] h-[100vh] text-[#fff]" :style="themeColor()">
        <!-- #ifdef H5 -->
        <view class="fixed top-0 left-0 z-4000 pl-[20rpx] h-[80rpx] flex-center" @click="back()">
            <text class="nc-iconfont nc-icon-zuoV6xx text-[#fff] text-[40rpx]"></text>
        </view>
        <!-- #endif -->
        <!-- #ifdef MP-WEIXIN || APP-PLUS -->
        <view class="fixed top-0 left-0 right-0 z-4000">
            <top-tabbar :data="topTabbarData" />
        </view>
        <!-- #endif -->
        <template v-if="videoList.length">
            <swiper class="video-swiper h-screen" vertical="true" :circular="false" :current="current" @change="swiperChange">
                <swiper-item class="swiper-video-item h-full" v-for="(item, index) in videoList" :key="index">
                    <view class="h-full relative">
                        <!-- :poster="img(item.content_cover_thumb_mid)" -->
                        <video  :id="'video' + index" :src="img(item.content_video)" :autoplay="index === 0" :loop="true" :controls="false" :show-fullscreen-btn="false" :show-center-play-btn="false" :show-play-btn="false" :enable-progress-gesture="false"  class="w-full h-full" @play="handlePlay(index)" @click="togglePlay(index)" @pause="handlePause(index)"></video>
                        <!-- <view v-if="isPlay && index === 0 && isWeixinBrowser()" class="absolute top-0 left-0 right-0 bottom-0 flex-center" @click="togglePlay(index, true)">
                            <image class="w-[150rpx] h-[150rpx] rounded-full" :src="img('/addon/sow_community/index/video_play.png')" mode="aspectFill"></image>
                        </view> -->
                    </view>
					<!-- 右侧个人 -->
					<!-- <view class="fixed right-[20rpx] bottom-[64rpx] w-[88rpx] z-800 text-[#fff] flex flex-col items-center">
					    <view class="rounded-full border-solid border-[2rpx] border-[#fff] relative flex-center">
					        <u-avatar  v-if="fineData.member && fineData.member.headimg" :src="img(fineData.member.headimg)" size="45" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  @click="toUseInfo()"/>
					        <image  v-else :src="img('static/resource/images/default_headimg.png')" mode="aspectFill" class="w-[90rpx] h-[90rpx] rounded-full align-middle bg-[#fff]" @click="toUseInfo()"></image>
					        <view class="absolute bottom-[-17rpx] follow-style w-[34rpx] h-[34rpx] rounded-full bg-primary flex-center" v-if="!fineData.is_follow && !fineData.is_self" @click="handleFollow">
					            <text class="nc-iconfont nc-icon-jiahaoV6xx text-[#fff] text-[26rpx] mt-[2rpx]"></text>
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
					    <view class="mt-[30rpx] flex-col flex items-center" @click="openShareFn(fineData)">
					        <text class="nc-iconfont nc-icon-fenxiangV6mm1 text-[60rpx]"></text>
					        <text class="mt-[10rpx] text-[22rpx] text-center">{{ t('share') }}</text>
					    </view>
					    <view class="relative mt-[30rpx]" v-if="fineData.is_self">
					        <view class="nc-iconfont nc-icon-a-gengduoV6xx-28 text-[66rpx]" @click="isEdit = !isEdit"></view>
					        <view class="edit-box absolute w-[180rpx] z-5000" v-if="isEdit">
					            <view class="w-[180rpx] mxa-h-[160rpx] rounded-[16rpx] py-[30rpx] px-[20rpx] bg-[#fff] text-[#333] rounded-[12rpx] relative flex flex-col justify-between box-border" style="box-shadow: rgba(0, 0, 0, 0.1) 0 1px 7px 0;">
					                <view class="flex items-center" v-if="fineData.status == 1 || fineData.status == -1 || fineData.status == -2" @click="redirect({url: '/addon/sow_community/pages/create', param: {content_id: fineData.content_id}})">
					                    <text class="nc-iconfont nc-icon-xiugaiV6xx text-[30rpx]"></text>
					                    <text class="ml-[18rpx] text-[24rpx]">{{ t('edit') }}</text>
					                </view>
					                <view class="border-0 border-solid border-b-[1rpx] border-[#ddd] my-[22rpx]" v-if="fineData.status == 1 || fineData.status == -1 || fineData.status == -2"></view>
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
					</view> -->
					<!-- 底部信息 -->
					<!-- <view class="fixed left-[20rpx] tabbar-bottom text-[#fff]">
					    <view class="w-[584rpx] mb-[32rpx]">
					        <view class="w-[560rpx] leading-[42rpx] text-[30rpx] font-bold mb-[10rpx]" v-if="fineData.content_title">{{ fineData.content_title }}</view>
					        <view class="w-[560rpx] mb-[10rpx] max-h-[400rpx] overflow-y-auto">
					            <view class="text-[rgba(255,255,255,.9)] text-justify relative left-0 box-border break-all text-[26rpx] whitespace-pre-line leading-[1.5]">
					                <text>{{ fineData.show ? fineData.content_split : fineData.content}}</text>
					                <template v-if="fineData.flag">
					                    <text class="absolute right-0 bottom-0"  v-if="fineData.show" @click="fineData.show = false">{{ t('expand') }}</text>
					                    <text class="absolute right-0 bottom-0" v-else @click="fineData.show = true">{{ t('packUp') }}</text>
					                </template>
					            </view>
					        </view>
					        <view class="w-[560rpx] leading-[36rpx] mb-[20rpx] font-500 text-[26rpx] max-h-[400rpx] overflow-y-auto" v-if="fineData.topic_list">
					            <text class="mr-[10rpx]" v-for="(item, index) in fineData.topic_list" @click="redirect({url: '/addon/sow_community/pages/topic_list', param: {topic_id: item.topic_id,topic_name:encodeURIComponent(item.topic_name)}})">#{{ item.topic_name }}</text>
					        </view>
					        <scroll-view :scroll-x="true" class="w-[584rpx] align-middle whitespace-nowrap rounded-[8rpx] overflow-hidden" v-if="Object.values(fineData).length && fineData.treasure_list && fineData.treasure_list.length">
					            <view class="inline-block mr-[20rpx]" v-for="(item, index) in fineData.treasure_list" :key="index" @click="redirect({url: item.treasure_url})">
					                <view class="w-[400rpx]  bg-[#fff] rounded-[12rpx] p-[15rpx] flex justify-between box-border">
					                    <image v-if="item.treasure_image" class="w-[102rpx] h-[102rpx] rounded-[8rpx]" :src="img(item.treasure_image)" mode="aspectFill" @error="item.treasure_image='static/resource/images/diy/shop_default.jpg'"></image>
					                    <image v-else class="w-[102rpx] h-[102rpx] rounded-[8rpx]" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
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
					</view> -->
                </swiper-item>
            </swiper>
        </template>
        <view v-if="!videoList.length && loading" class="h-screen flex flex-col justify-center items-center">
            <view class="text-[#fff] text-[32rpx] font-500 mb-[16rpx]">{{ t('noContent') }}</view>
            <view class="text-[#fff] text-[26rpx] mb-[60rpx]">{{ t('createFirstSowCommunity') }}</view>
            <view class="bg-primary w-[260rpx] h-[72rpx] rounded-full flex-center text-[#fff] text-[26rpx] font-500" @click="toLink()">{{ t('toCreate') }}</view>
        </view>
        <evaluate-popup  ref="evaluateRef" @confirm="evaluateConfirm"/>
        <recommend-treasure-popup ref="recommendTreasureRef" />
        <!-- 分享 -->
        <share-popup ref="shareRef" :copyUrl="copyUrl" :copyUrlParam="copyUrlParam" />
        <tips-popup  ref="contentRef" :title="t('deleteContentTips')" @confirm="contentDelete"/>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { t } from '@/locale'
import { img, deepClone, getToken, redirect, isWeixinBrowser, handleOnloadParams } from '@/utils/common';
import { getContentList, setContentLike, follow } from '@/addon/sow_community/api/follow';
import { browse, deleteContent, share } from '@/addon/sow_community/api/content';
import { onLoad,onShow } from '@dcloudio/uni-app';
import EvaluatePopup from '@/addon/sow_community/components/evaluate-popup/evaluate-popup.vue'
import RecommendTreasurePopup from '@/addon/sow_community/components/recommend-treasure-popup/recommend-treasure-popup.vue'
import tipsPopup from '@/addon/sow_community/components/tips-popup/tips-popup.vue'
import { useLogin } from '@/hooks/useLogin'
import sharePopup from '@/components/share-popup/share-popup.vue'
import { useShare }from '@/hooks/useShare'
import useMemberStore from '@/stores/member'
import { topTabar } from '@/utils/topTabbar';

/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({ title: '' })
/********* 自定义头部 - end ***********/

// 会员信息
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)

const loading = ref<boolean>(false);
const videoList = ref<any>([])
const curPage = ref(1)
const totalPage = ref(1)
const limit = ref(10)
const fineData = ref<any>({}) //精选当前信息
const contentId = ref(0)
const current =ref(0)
const isEdit = ref(false)
const isPlay = ref(true) //第一个视频手动播放
onLoad((options: any) =>{
    // #ifdef MP-WEIXIN
    // 处理小程序场景值参数
    options = handleOnloadParams(options);
    // #endif
    contentId.value = options.content_id || 0;
})

onShow(() =>{
    getVideoListFn()
    restFn();
})

const restFn = () =>{
    let val = uni.getStorageSync('featuredVideosItem');
    if(val && Object.keys(videoList.value).length){
        Object.keys(videoList.value[current.value]).forEach((item,index)=>{
            if(Object.keys(val).indexOf(item) != -1){
                videoList.value[current.value][item] = val[item];
                fineData.value[item] = val[item];
            }
        })
        uni.removeStorageSync('featuredVideosItem');
    }
}
// 分享
const{setShare} = useShare()
const  getVideoListFn = (page:number = 1) =>{
    loading.value = false;
    let data: object = {
		page: page,
		limit: limit.value,
        content_type: 2,
        start_id: contentId.value
	};
    getContentList(data).then((res: any) => {
		// let newArr = (res.data.data as Array<Object>);
		const newArr = res.data.data.map((item: any) => {
            item.isPaused = false
            item.show = false  //是否显示收起
            item.flag = item.content.length > 37 //是否显示展开收取
            if(item.content.length > 37){
                item.content_split = item.content.slice(0, 37) + '...'
                item.show = true
            }
            return item
        });

        curPage.value = res.data.current_page
        totalPage.value = res.data.last_page

		//设置列表数据
		if (Number(page) === 1) {
			videoList.value = []; //如果是第一页需手动制空列表
		}
		videoList.value = videoList.value.concat(newArr);
        fineData.value = deepClone(videoList.value[current.value])
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

const togglePlay = (index: number, status: boolean = false) => {
    const video = uni.createVideoContext(`video${index}`);
    if (!video) return;

    const currentVideo = videoList.value[index];

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

// 精选滑动
const swiperChange = (e: any) => {
    fineData.value = deepClone(videoList.value[e.detail.current]);
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
    const newIndex = e.detail.current;

    // 获取滑动方向
    const isForward = newIndex > current.value; // 是否向前滑动

    // 暂停上一个或下一个视频
    const prevOrNextIndex = isForward ? current.value : newIndex + 1;
    const prevOrNextVideoContext = uni.createVideoContext(`video${prevOrNextIndex}`);
    prevOrNextVideoContext.pause();
	const prevVideo = videoList.value[current.value];
	if (prevVideo) {
	    prevVideo.isPaused = true; // 确保上一个视频的状态为暂停
	}

    // 更新当前索引
    current.value = newIndex;

    // 播放当前视频
    const currentVideoContext = uni.createVideoContext(`video${newIndex}`);
    currentVideoContext.play();
	const currentVideo = videoList.value[newIndex];
	currentVideo.isPaused = false; // 更新当前视频的播放状态为播放中

    // 更新浏览记录
    myBrowseFn(fineData.value.content_id);

    // 加载新视频数据
    if (curPage.value < totalPage.value && !(e.detail.current % (limit.value - 1))) {
        curPage.value++;
        getVideoListFn(curPage.value);
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
        videoList.value[current.value].is_like = data.is_like
        videoList.value[current.value].like_num = data.like_num
    })
}

// 购物袋弹框
const recommendTreasureRef = ref()
const toBuy = () =>{
    recommendTreasureRef.value.open(fineData.value.treasure_list)
}

// 精选点击评论
const evaluateRef = ref()
const handleEvaluate = () =>{
    // 检测是否登录
	if (!getToken()) {
		useLogin().setLoginBack({
            url: '/addon/sow_community/pages/video/detail',
            param: {
                content_id: contentId.value,
            }
        })
		return false
	}
    evaluateRef.value.open(fineData.value)
}

const evaluateConfirm = (data: any) =>{
    if(fineData.value.content_id == data.content_id){
        fineData.value.comment_num = data.count
    }
    videoList.value.forEach((item: any) => {
        if (item.content_id == data.content_id) {
            item.comment_num = data.count
        }
    })
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
        videoList.value[current.value].is_follow = fineData.value.is_follow
    })
}

// 删除内容
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

// 返回
const back = () =>{
    if(getCurrentPages().length > 1){
		uni.navigateBack({
			delta: 1
		});
	}else{
        redirect({
			url: '/addon/sow_community/pages/index',
            mode: 'reLaunch'
		});
	}
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
.follow-style{
    left: 50%;
    transform: translateX(-50%);
}
.tabbar-bottom{
    bottom: constant(safe-area-inset-bottom);
    bottom: env(safe-area-inset-bottom);
}
</style>
