<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 z-99 gradient-box">
            <view class="pt-[20rpx] flex-between-center px-[20rpx]">
				<view class="flex-1 search-input bg-[#f3f3f3] mr-[20rpx]">
					<text @click.stop="searchTypeFn()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
					<input class="input" maxlength="50" type="text" v-model="keywords" :placeholder="t('keywordsTips')" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchTypeFn()">
					<text v-if="keywords" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="keywords=''"></text>
				</view>
				<view class="text-[26rpx]"  @click="searchTypeFn()">{{ t('search') }}</view>
			</view>
            <view class="flex items-center px-[20rpx]">
                <scroll-view :scroll-x="true" scroll-with-animation :scroll-into-view="'id'+ curCategory" class="pl-[30rpx] align-middle whitespace-nowrap w-[676rpx]">
                    <view class="inline-block mr-[52rpx]" v-for="(item, index) in categoryList" :key="index" :id="'id' + item.category_id">
                        <view class="h-[100rpx] leading-[100rpx] text-[28rpx] max-w-[204rpx] truncate" :class="{'cate-active': curCategory == item.category_id}" @click="handleCategory(item.category_id)">{{ item.category_name }}</view>
                    </view>
                </scroll-view>
            </view>
        </view>
        <mescroll-body ref="mescrollRef" top="194rpx"  @init="mescrollInit" :down="{ use: false }" @up="getCommunityListFn">
            <view class="biserial-page sidebar-margin" v-if="communityList.length">
                <view>
                    <template v-for="(item, index) in leftList">
                        <view  class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]"  @click="toDetail(item)">
                            <view class="overflow-y-hidden relative box-border">
                                <image v-if="item.content_cover" class="w-[100%]  rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" @error="item.content_cover='static/resource/images/diy/shop_default.jpg'" />
                                <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'" />
                                <view v-if="item.content_type == 1"  class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color">{{ item.image_num }}{{ t('picture') }}</view>
                                <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" />
                            </view>
                            <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]">{{ item.content_title }}</view>
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
                        <view  class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]"  @click="toDetail(item)">
                            <view class="overflow-y-hidden relative box-border">
                                <image v-if="item.content_cover" class="w-[100%] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img(item.content_cover)" mode="widthFix" @error="item.content_cover='static/resource/images/diy/shop_default.jpg'" />
                                <image  v-else class="w-[100%] h-[460rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'" />
                                <view v-if="item.content_type == 1"  class="w-[60rpx] h-[36rpx] text-[#fff] rounded-[8rpx] flex-center absolute right-[16rpx] bottom-[16rpx] text-[22rpx] bg-color">{{ item.image_num }}{{ t('picture') }}</view>
                                <image v-if="item.content_type == 2" class="w-[40rpx] h-[40rpx] absolute top-[20rpx] right-[20rpx] rounded-full" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'" />
                            </view>
                            <view class="p-[24rpx] flex-1 flex flex-col justify-between">
                                <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[22rpx]">{{ item.content_title }}</view>
                                <view class="flex items-center justify-between text-[22rpx] text-[#999]">
                                    <view class="flex items-center"  v-if="item.member">
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
            <mescroll-empty v-if="!communityList.length && loading" :option="{tip : t('noContent')}"></mescroll-empty>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { t } from '@/locale'
import { redirect, img, getToken, deepClone } from '@/utils/common';
import { getContentList, getCategoryList, setContentLike } from '@/addon/sow_community/api/follow';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { useLogin } from '@/hooks/useLogin'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const keywords = ref('')
const loading = ref<boolean>(false)
const communityList = ref<any>([])
const leftList = ref<any>([])
const rightList = ref<any>([])
const curLimitList = ref<any>([])
const searchTypeFn = () => {
    communityList.value = [];
    getMescroll().resetUpScroll();
}

const  curCategory = ref(0)
const categoryList = ref<any>([])
const getCategoryListFn = () =>{
    getCategoryList().then((res: any) =>{
        const initData = { category_name: '全部', category_id: '' }
		categoryList.value.push(initData)
		categoryList.value = categoryList.value.concat(res.data)
    })
}
getCategoryListFn()

const handleCategory = (data: any) =>{
    curCategory.value = data
    communityList.value = []
    getMescroll().resetUpScroll();
}

const getCommunityListFn = (mescroll: any) => {
	loading.value = false;
	let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
		content_title: keywords.value,
        category_id: curCategory.value,
	};
	getContentList(data).then((res: any) => {
		let newArr = (res.data.data as Array<Object>);
            curLimitList.value = deepClone(newArr)
		//设置列表数据
		if (Number(mescroll.num) === 1) {
			communityList.value = []; //如果是第一页需手动制空列表
            leftList.value = [];
            rightList.value = [];
		}
		communityList.value = communityList.value.concat(newArr);
        loadData()
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}
// 该方法时通过数据+底部预估高度进行计算，结果不是准确的
const loadData = () =>{
    if((leftList.value.length + rightList.value.length) == 0){
        leftList.value = communityList.value.filter((item: any, index: number) => index % 2 == 0)
        rightList.value = communityList.value.filter((item: any, index: number) => index % 2 == 1)
    }else{
        leftList.value = leftList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 0))
        rightList.value = rightList.value.concat(curLimitList.value.filter((item: any, index: number) => index % 2 == 1))
    }
    // leftList.value = communityList.value.filter((item: any, index: number) => index % 2 == 0)
    // rightList.value = communityList.value.filter((item: any, index: number) => index % 2 == 1)

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
            item.height = parseFloat(item.content_cover_height) * (172.5 / parseFloat(item.content_cover_width))
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
.gradient-box{
    background: linear-gradient(180deg,#fff,#f5f5f5);
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
.bg-color{
    background: hsla(0,0%,40%,.5)
}
.biserial-page{
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-gap: 10px;
}
</style>
