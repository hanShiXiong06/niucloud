<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden"  :style="themeColor()">
        <view class="fixed left-0 top-0 right-0 z-10">
            <view class="px-[20rpx] py-[14rpx] bg-[#fff] relative z-10084">
				<view class="h-[60rpx] bg-[#f6f6f6] rounded-[30rpx] flex items-center">
					<view class="search-input !px-[20rpx]">
						<input class="input" maxlength="50" type="text" v-model="code" placeholder="请输入核销码" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search" @confirm="searchFn()">
						<text v-if="code" class="nc-iconfont nc-icon-cuohaoV6xx1 clear mt-[4rpx] mr-[10rpx]" @click="code=''"></text>
						<text class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !mr-0 w-[40rpx] text-center" @click="searchFn()"></text>
					</view>
				</view>
			</view>
        </view>
        <mescroll-body ref="mescrollRef" top="88rpx"  @init="mescrollInit" :down="{ use: false }" @up="getListFn" >
            <view class="sidebar-margin pt-[var(--top-m)]" v-if="list.length">
                <view class="mb-[var(--top-m)] card-template" v-for="(item,index) in list" :key="index" @click="toLink(item)">
                    <view class="mb-[20rpx] multi-hidden">
                        <text class="inline-block mr-[10rpx] text-primary text-[22rpx] bg-[var(--primary-color-light)] px-[8rpx] py-[6rpx] rounded-[6rpx]">{{ item.type_name }}</text>
                        <text class="text-[30rpx] font-500">{{ item.body }}</text>
                    </view>
                    <view class="flex items-center text-[#666] mb-[20rpx]">
                        <view class="text-[26rpx]">核销码：</view>
                        <view class="text-[26rpx]">{{ item.code }}</view>
                    </view>
                    <view class="flex items-center text-[#666] mb-[20rpx]">
                        <view class="text-[26rpx]">核销员：</view>
                        <view class="text-[26rpx]">{{ item.verifier_name }}</view>
                    </view>
                    <view class="flex items-center text-[#666]">
                        <view class="text-[26rpx]">核销时间：</view>
                        <view class="text-[26rpx]">{{ item.create_time }}</view>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!list.length && !loading"></mescroll-empty>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { redirect } from '@/utils/common';
import { getVerifyRecord } from '@/app/api/verify'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';;
import { onPageScroll, onReachBottom, onLoad, onShow } from '@dcloudio/uni-app';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);


const list = ref<Array<any>>([])
const loading = ref<boolean>(true)
const code = ref<string>('')
const getListFn = (mescroll: any) => {
    let data: object = {
		page: mescroll.num,
		limit: mescroll.size,
        code: code.value
	};	

    getVerifyRecord(data).then((res: any) => {
        let newArr = res.data.data;
        mescroll.endSuccess(newArr.length);
        //设置列表数据
        if (mescroll.num == 1) {
            list.value = []; //如果是第一页需手动制空列表
        }
        list.value = list.value.concat(newArr);
        loading.value = false;
    }).catch(() => {
        loading.value = false;
        mescroll.endErr(); // 请求失败, 结束加载
    })
}

const searchFn = () => {
    getMescroll().resetUpScroll();
}

const  toLink = (data: any) => {
    redirect({url: '/app/pages/verify/detail', param: { code: data.code }})
}
</script>

<style scoped>

</style>