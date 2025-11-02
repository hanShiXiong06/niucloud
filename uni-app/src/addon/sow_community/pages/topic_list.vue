<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 z-99 topic-box">
            <view class="px-[20rpx] py-[16rpx]  border-0 border-b-[1rpx] border-solid border-[#f6f6f6]">
                <view class="search-input h-[66rpx]">
                    <text @click.stop="searchTypeFn()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !text-[28rpx]"></text>
                    <input class="input" maxlength="50" type="text" v-model.trim="keywords" :placeholder="t('keywordsTips')" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn()">
                    <text v-if="keywords" class="nc-iconfont nc-icon-cuohaoV6xx1 clear !text-[32rpx]" @click="keywords=''"></text>
                </view>
            </view>
            <view class="flex-between-center p-[20rpx] mb-[20rpx]">
                <view>
                    <view class="text-[30rpx] font-500 leading-[42rpx] mb-[8rpx]" v-if="topicName"># {{ topicName }}</view>
                    <view class="text-[24rpx] text-[var(--text-color-light9)]">{{ t('welcomeIn') }}{{ topicName }}{{ t('discussion') }}</view>
                </view>
                <view class="w-[146rpx] h-[54rpx] border-[2rpx] border-solid border-[#ccc] rounded-[27rpx] flex-center box-border" @click="toLink()">
                    <text class="nc-iconfont nc-icon-xiugaiV6xx text-[22rpx] mr-[8rpx]"></text>
                    <text class="text-[22rpx]">{{ t('toCreate') }}</text>
                </view>
            </view>
            <view class="px-[20rpx] flex-between-center mb-[20rpx]">
               <view class="flex-center text-[28rpx] text-[#333]">
                    <text>{{ t('sowCommunity') }}</text>
                    <text class="mx-[4rpx] text-[#111] font-500">{{ topicTotal }}{{ t('piece') }}</text>
                    <text>{{ t('content') }}</text>
                </view>
                <view class=" flex-center">
                    <text class="text-[28rpx] text-[#666] mr-[20rpx]" :class="{'!text-primary font-500': type == 'hot' }" @click="handleTab('hot')">{{ t('hot') }}</text>
                    <text class="text-[28rpx] text-[#666]" :class="{'!text-primary font-500': type == 'new' }"  @click="handleTab('new')">{{ t('new') }}</text>
                </view>
            </view>
        </view>
        <mescroll-body ref="mescrollRef" top="282"  @init="mescrollInit" :down="{ use: false }" @up="getTopicListFn">
            <view class="biserial-page sidebar-margin" v-if="topicList.length">
                <view>
                    <template v-for="(item, index) in leftList">
                        <view  class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]"  @click="toDetail(item)">
                            <view class="overflow-y-hidden relative box-border">
                                <image v-if="item.content_cover" class="w-[100%]  rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" @error="item.content_cover='static/resource/images/diy/shop_default.jpg'" />
                                <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'" />
                                <view v-if="item.content_type == 1"  class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color">{{ item.image_num }}{{ t('picture') }}</view>
                                <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'"></image>
                            </view>
                            <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]" v-if="item.content_title">{{ item.content_title }}</view>
                                <view class="flex items-center justify-between text-[22rpx] text-[#999]">
                                    <view class="flex items-center" v-if="item.member">
                                        <u-avatar :src="img(item.member.headimg)" size="17" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                        <text class="max-w-[180rpx] ml-[8rpx] leading-[34rpx] using-hidden">{{ item.member.nickname }}</text>
                                    </view>
                                    <view class="flex items-center"  @click.stop="handleLike(item)">
                                        <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary" v-if="item.is_like"></text>
                                        <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx]" v-else></text>
                                        <text class="ml-[6rpx] min-w-[14rpx]">{{ item.like_num }}</text>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </template>
                </view>
                <view>
                    <template v-for="(item, index) in rightList">
                        <view class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]"  @click="toDetail(item)">
                            <view class="overflow-y-hidden relative box-border">
                                <image v-if="item.content_cover" class="w-[100%] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" @error="item.content_cover='static/resource/images/diy/shop_default.jpg'"></image>
                                <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"></image>
                                <view v-if="item.content_type == 1"  class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color">{{ item.image_num }}{{ t('picture') }}</view>
                                <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'"></image>
                            </view>
                            <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]" v-if="item.content_title">{{ item.content_title }}</view>
                                <view class="flex items-center justify-between text-[22rpx] text-[#999]">
                                    <view class="flex items-center" v-if="item.member">
                                        <u-avatar :src="img(item.member.headimg)" size="17" leftIcon="none" :default-url="img('static/resource/images/default_headimg.png')"  />
                                        <text class="max-w-[180rpx] ml-[8rpx] leading-[34rpx] using-hidden">{{ item.member.nickname }}</text>
                                    </view>
                                    <view class="flex items-center" @click.stop="handleLike(item)">
                                        <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary" v-if="item.is_like"></text>
                                        <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx]" v-else></text>
                                        <text class="ml-[6rpx] min-w-[14rpx]">{{ item.like_num }}</text>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </template>
                </view>
            </view>
            <mescroll-empty v-if="!topicList.length && loading" :option="{tip : t('noContent')}"></mescroll-empty>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/locale'
import { redirect, img, getToken, deepClone } from '@/utils/common';
import { useLogin } from '@/hooks/useLogin'
import { getContentList, setContentLike } from '@/addon/sow_community/api/follow';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const keywords = ref('')
const type = ref('hot')
const loading = ref<boolean>(false)
const topicList = ref<any>([])
const topicTotal = ref(0)
const topicId = ref(0)
const topicName = ref('')
const leftList = ref<any>([])
const rightList = ref<any>([])
const curLimitList = ref<any>([])

onLoad((options: any) =>{
    topicId.value = options.topic_id || 0;
    keywords.value = options.topic_name ? decodeURIComponent(options.topic_name) : '';
    topicName.value = keywords.value || '';
})

const searchTypeFn = () =>{
    if(keywords.value){
        topicId.value = 0;
        topicName.value = keywords.value
    }else{
        uni.showToast({
			title: '请输入话题名称',
			icon: 'none'
		});
        return false
    }
    topicList.value = [];
    getMescroll().resetUpScroll();
}

const handleTab = (data: any) =>{
    type.value = data
    topicList.value = [];
    getMescroll().resetUpScroll();
}

const  getTopicListFn = (mescroll: any) => {
    loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        topic_id: topicId.value,
		topic_name: keywords.value,
        type: type.value
	};
    getContentList(data).then((res: any) => {
        topicTotal.value = res.data.total;
		let newArr = (res.data.data as Array<Object>);
        curLimitList.value = deepClone(newArr)
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			topicList.value = []; //如果是第一页需手动制空列表
            leftList.value = [];
            rightList.value = [];
		}
		topicList.value = topicList.value.concat(newArr);
        loadData()
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

const loadData = () =>{
    if((leftList.value.length + rightList.value.length) == 0){
        leftList.value = topicList.value.filter((item: any, index: number) => index % 2 == 0)
        rightList.value = topicList.value.filter((item: any, index: number) => index % 2 == 1)
    }else{
        leftList.value = leftList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 0))
        rightList.value = rightList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 1))
    }
    // leftList.value = topicList.value.filter((item: any, index: number) => index % 2 == 0)
    // rightList.value = topicList.value.filter((item: any, index: number) => index % 2 == 1)
    let leftHeight = 0;
    let rightHeight = 0;
    while (true) {
        leftHeight = leftList.value.map((item: any) => {
            item.height = parseFloat(item.content_cover_height) * (172.5 /parseFloat(item.content_cover_width))
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


// 发布作品
const toLink = () =>{
    if(!getToken()){
        useLogin().setLoginBack({
            url: '/addon/sow_community/pages/create',
        })
        return false
    }
    redirect({url:'/addon/sow_community/pages/create'})
}

// 去详情
const toDetail = (data: any) =>{
    if(data.content_type == 1){
        redirect({url: '/addon/sow_community/pages/image/detail', param: { content_id: data.content_id }})
    }else{
        redirect({url: '/addon/sow_community/pages/video/detail', param: { content_id: data.content_id }})

    }
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
    })
}
</script>

<style lang="scss" scoped>
.topic-box{
    background: linear-gradient(180deg,#fff,#f6f6f6);
}
.biserial-page{
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-gap: 10px;
}
.bg-color{
    background: hsla(0,0%,40%,.5)
}
</style>
