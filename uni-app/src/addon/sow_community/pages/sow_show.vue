<template>
	<view class="bg-[var(--page-bg-color)] min-h-[100vh]" :style="themeColor()" >
		<!-- 头部 -->
		<view class="fixed left-0 top-0 right-0 z-10 bg-[var(--page-bg-color)]" v-if="Object.keys(treasure_info).length">
			<view class="card-template flex  sidebar-margin my-[var(--top-m)]" >
				<image v-if="treasure_info.treasure_image" class="w-[100rpx] h-[100rpx] rounded-[var(--rounded-small)] align-middle" :src="img(treasure_info.treasure_image)" :mode="'aspectFill'" />
				<image v-else class="w-[100rpx] h-[100rpx] rounded-[var(--rounded-small)] align-middle" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'" />
				<view class="flex flex-col flex-1 justify-between ml-3">
					<view class="using-hidden text-[28rpx] leading-[40rpx]">{{treasure_info.treasure_name}}</view>
					<view class="text-[26rpx] leading-[37rpx]"><text class="text-[28rpx] leading-[40rpx] text-[#ff3333]">{{treasure_info.count}}</text> {{ t('itemSowshow') }}</view>
				</view>
			</view>
		</view>

		<!-- 内容列表 -->
		<mescroll-body ref="mescrollRef" top="200rpx"  @init="mescrollInit" :down="{ use: false }" @up="getTreasureContentListFn">
			<view  class="biserial-page sidebar-margin" v-if="grassData.length">
			    <view>
			        <template v-for="(item, index) in grassData" :key="index">
			            <view v-if="(index % 2) == 0" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]" @click="toDetail(item)">
			                <view class="max-h-[720rpx] overflow-y-hidden relative box-border">
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
			                            <text class="w-[180rpx] ml-[8rpx] leading-[30rpx] using-hidden">{{ item.member.nickname }}</text>
			                        </view>
			                        <view class="flex items-center" @click.stop="handleLike(item)">
			                            <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary mr-[10rpx]" v-if="item.is_like"></text>
			                            <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] mr-[10rpx]" v-else></text>
			                            <text class="min-w-[15rpx] text-center">{{ item.like_num }}</text>
			                        </view>
			                    </view>
			                </view>
			            </view>
			        </template>
			    </view>
			    <view>
			        <template v-for="(item, index) in grassData">
			            <view v-if="(index % 2) == 1" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mb-[var(--top-m)]" @click="toDetail(item)">
			                <view class="max-h-[720rpx] overflow-y-hidden relative box-border">
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
			                            <text class="w-[180rpx] ml-[8rpx] leading-[30rpx] using-hidden">{{ item.member.nickname }}</text>
			                        </view>
			                        <view class="flex items-center" @click.stop="handleLike(item)">
			                            <text class="nc-iconfont nc-icon-dianzanV6mm text-[24rpx] text-primary mr-[10rpx]" v-if="item.is_like"></text>
			                            <text class="nc-iconfont nc-icon-a-dianzanV6xx-36 text-[24rpx] mr-[10rpx]" v-else></text>
			                            <text class="min-w-[15rpx] text-center">{{ item.like_num }}</text>
			                        </view>
			                    </view>
			                </view>
			            </view>
			        </template>
			    </view>
			</view>
			<mescroll-empty v-if="!grassData.length && loading" :option="{tip : t('noContent')}"></mescroll-empty>
		</mescroll-body>

	</view>
</template>

<script setup lang="ts">
import {ref} from 'vue';
import { t } from '@/locale'
import {img,redirect} from '@/utils/common';
import { getTreasureContent} from '@/addon/sow_community/api/treasure';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import { setContentLike} from '@/addon/sow_community/api/follow';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad,onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';

const {mescrollInit,downCallback,getMescroll} = useMescroll(onPageScroll, onReachBottom);
const loading = ref(false);
const treasureId = ref(0);

// 示例数据
const grassData = ref([]);
const treasure_info = ref({})

onLoad((options: any) => {
    treasureId.value = options.treasure_id || 0;
})

interface mescrollStructure {
	num: number,
	size: number,
	endSuccess: Function,
	[propName: string]: any
}

const getTreasureContentListFn = (mescroll: mescrollStructure) =>{
    // loading.value = true
    let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        treasure_id: treasureId.value
	};
    getTreasureContent(data).then((res: any) =>{
		treasure_info.value = res.data.treasure_info
        // commentTotal.value = res.data.total;
        let newArr = res.data.list.data.map((item: any) => {
            item.secondFlag = false
            item.cur_page = 1
            item.total  = 0
            return item
        });
            //设置列表数据
		if (Number(mescroll.num) === 1) {
			grassData.value = []; //如果是第一页需手动制空列表
		}
        grassData.value = grassData.value.concat(newArr);
        mescroll.endSuccess(newArr.length);
        loading.value = true;
    }).catch(() => {
        loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}

// 点赞
const handleLike = (data: any) => {
	data.is_like = !data.is_like
    data.is_like ? data.like_num++ : data.like_num--
    setContentLike({
        content_id: data.content_id,
        status: data.is_like ? 1 : 0
    }).then((res: any) => {
    })
}

// 跳转到详情页
const toDetail = (item:any) => {
	if(item.content_type == 1){
	    redirect({url: '/addon/sow_community/pages/image/detail', param: { content_id: item.content_id }})
	}else{
	    redirect({url: '/addon/sow_community/pages/video/detail', param: { content_id: item.content_id }})
	}
}
</script>

<style lang="scss" scoped>
	.bg-color {
		background: hsla(0, 0%, 40%, .5)
	}
	.biserial-page{
		display: grid;
		grid-template-columns: 1fr 1fr;
		grid-gap: 10px;
	}
</style>
