<template>
	<view class="bg-white" :style="themeColor()">
		<view v-if="!loading && JSON.stringify(articleDetail) != '[]'">
			<view class="px-[24rpx] py-[30rpx]">
				<view class="text-[36rpx] font-500 leading-[50rpx]">{{articleDetail.title}}</view>
				<view class="flex align-center text-[26rpx] text-[#666] mt-[20rpx]">{{articleDetail.create_time}}</view>
			</view>
			<view class="mx-[24rpx] mb-[30rpx] bg-[#f9f9f9] p-[20rpx] text-[26rpx] rounded-[16rpx] leading-[1.3]" v-if="articleDetail.summary">
				<text class="text-[#666]">{{t('abstract')}}：</text>
				{{ articleDetail.summary }}
			</view>
			<view class="px-[24rpx] pd-[10px]">
				<u-parse :content="articleDetail.content" :tagStyle="style"></u-parse>
			</view>
		</view>
		<loading-page  :loading="loading" />
	</view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { t } from '@/locale'
import { getArticleInfo } from '@/app/api/article';


let articleDetail = ref<any>([]);
let loading = ref<boolean>(true);
let style = {
	h2: 'margin-bottom: 15px;',
	p: 'margin-bottom: 10px;line-height: 1.5;',
	img: 'margin: 10px 0;vertical-align: top;',
};
onLoad((option: any) => {
	loading.value = true;
	getArticleInfo(option.id).then((res:any) => {
		articleDetail.value = res.data;
		loading.value = false;
		if(JSON.stringify(articleDetail.value) != '[]'){
			uni.setNavigationBarTitle({
				title: articleDetail.value.title
			})
		}
		

	});
})
</script>
<style lang="scss" scoped></style>
