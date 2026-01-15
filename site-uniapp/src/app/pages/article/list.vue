<template>
	<view class="bg-white min-h-[100vh]" :style="themeColor()">
		<mescroll-body ref="mescrollRef" @init="mescrollInit" top="0" @down="downCallback" @up="getArticleListFn">
			<view class="px-[24rpx]" v-if="articleList.length">
				<view v-for="(item,index) in articleList" :key="index" class=" flex align-center py-[30rpx] border-0 border-b-[1rpx] border-solid border-[#eee]" :class="{'mb-[20rpx]': articleList.length-1 !== index}" @click="toLink(item.id)">
					<up-image width="210rpx" height="170rpx" radius="8rpx" :src="img(item.image)" model="aspectFill">
						<template #error>
							<u-icon name="photo" color="#999" size="50"></u-icon>
						</template>
					</up-image>
					<view class="flex-1 flex flex-col justify-between ml-[20rpx]">
						<view class="leading-[38rpx] multi-hidden text-[28rpx]">
							<text class="relative top-[-2rpx] px-[6rpx] h-[38rpx] text-center leading-[38rpx] bg-[#4580F3] text-[#fff] text-[20rpx] rounded-[4rpx] inline-block mr-[10rpx]">{{ item.category_name }}</text>{{item.title}}
						</view>
						<view class="text-[26rpx] text-[#999] flex justify-between items-center">
							<text>{{item.create_time}}</text>
							<text>阅读:{{ item.visit }}</text>
						</view>
					</view>
				</view>
			</view>
			<mescroll-empty v-if="!articleList.length && loading"></mescroll-empty>
		</mescroll-body>
	</view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { redirect, img } from '@/utils/common';
import { getArticlePage } from '@/app/api/article'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);


let articleList = ref<Array<any>>([]);
let mescrollRef = ref(null);
let loading = ref<boolean>(false);

interface mescrollStructure {
	num : number,
	size : number,
	endSuccess : Function,
	[propName : string] : any
}
const getArticleListFn = (mescroll : mescrollStructure) => {
	loading.value = false;
	let data : object = {
		page: mescroll.num,
		limit: mescroll.size
	};

	getArticlePage(data).then((res : any) => {
		let newArr = (res.data.data as Array<Object>);
		//设置列表数据
		if (mescroll.num == 1) {
			articleList.value = []; //如果是第一页需手动制空列表
		}
		articleList.value = articleList.value.concat(newArr);
		mescroll.endSuccess(newArr.length);
		loading.value = true;
	}).catch(() => {
		loading.value = true;
		mescroll.endErr(); // 请求失败, 结束加载
	})
}


const toLink = (id : string) => {
	redirect({ url: '/app/pages/article/detail', param: { id } })
}


</script>

<style lang="scss" scoped>
.nav-item.active {
	color: $u-primary;
}

.scroll-view-wrap {
	word-break: keep-all;
}
</style>