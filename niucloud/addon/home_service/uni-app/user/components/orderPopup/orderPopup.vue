<template>
	<u-popup :show="show" mode="bottom" @close="$emit('close')" round="10">
		<view class="w-[100vw] p-[25rpx] box-border rounded-[20rpx] bg-white">
			<!-- 出发确认弹窗 -->
			<template v-if="actionKey === 'action_depart'">
				<view class="text-center mb-[25rpx] text-[32rpx] font-bold">
					确认出发
				</view>

				<view class="text-[28rpx]">
					当前定位：
				</view>
				<view class="text-[26rpx] flex mt-[30rpx] justify-between items-center">
					<view class="flex items-center">
						<u-icon name="map" color="#4059ff"></u-icon>
						<text class="pl-[5rpx] text-[28rpx]">{{systemStore.diyAddressInfo.full_address}}</text>
					</view>
					<view class="flex items-center text-[#999999]" @click="calcLocation">
						<text class="pr-[5rpx] text-[26rpx]">刷新</text>
						<u-icon name="reload" color="#999999"></u-icon>
					</view>
				</view>
			</template>

			<!-- 拍照弹窗 -->
			<template v-if="actionKey === 'again_check'">
				<view class="text-center mb-[20rpx] text-[32rpx] font-bold">
					上传图片
				</view>
				<view class="text-[28rpx]">
					当前定位：
				</view>
				<view class="text-[26rpx] flex mt-[30rpx] justify-between items-center">
					<view class="flex items-center">
						<u-icon name="map" color="#4059ff"></u-icon>
						<text class="pl-[5rpx] text-[28rpx]">{{systemStore.diyAddressInfo.full_address}}</text>
					</view>
					<view class="flex items-center text-[#999999]" @click="calcLocation">
						<text class="pr-[5rpx] text-[26rpx]">刷新</text>
						<u-icon name="reload" color="#999999"></u-icon>
					</view>
				</view>
				<view class="text-[28rpx] my-[25rpx]">
					拍照验收：
				</view>
				<view class="flex flex-wrap gap-3 mb-[30rpx]">
					<view class="relative w-[120rpx] !h-[120rpx] layout-one-content " v-for="(item,index) in imgList"
						:key="index">
						<image class="w-[100%] h-[100%]" :src="img(item)" mode="aspectFill" />
						<view
							class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
							@click="deleteImage(index)">
							<text
								class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-white"></text>
						</view>
					</view>
					<block v-if="imgList.length < 6">
						<u-upload accept="image" @afterRead="afterRead" multiple :maxCount="6 - imgList.length" :maxSize="10485760" @oversize="oversizeTit">
							<view
								class="w-[120rpx] !h-[120rpx] flex items-center justify-center border-1 border-[#cccccc] border-solid rounded-[14rpx]">
								<text class="iconfont iconjiahaoV6xx1 text-[50rpx] text-[#9c9c9c]"></text>
							</view>
						</u-upload>
					</block>
				</view>
			</template>
			
			
			<template v-if="actionKey === 'action_save_check'">
				<view class="text-center mb-[20rpx] text-[32rpx] font-bold">
					上传图片
				</view>
				<view class="text-[28rpx]">
					当前定位：
				</view>
				<view class="text-[26rpx] flex mt-[30rpx] justify-between items-center">
					<view class="flex items-center">
						<u-icon name="map" color="#4059ff"></u-icon>
						<text class="pl-[5rpx] text-[28rpx]">{{systemStore.diyAddressInfo.full_address}}</text>
					</view>
					<view class="flex items-center text-[#999999]" @click="calcLocation">
						<text class="pr-[5rpx] text-[26rpx]">刷新</text>
						<u-icon name="reload" color="#999999"></u-icon>
					</view>
				</view>
				<view class="text-[28rpx] my-[25rpx]">
					完成拍照：
				</view>
				<view class="flex flex-wrap gap-3 mb-[30rpx]">
					<view class="relative w-[120rpx] !h-[120rpx] layout-one-content " v-for="(item,index) in imgList"
						:key="index">
						<image class="w-[100%] h-[100%]" :src="img(item)" mode="aspectFill" />
						<view
							class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
							@click="deleteImage(index)">
							<text
								class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-white"></text>
						</view>
					</view>
					<block v-if="imgList.length < 6">
						<u-upload accept="image" @afterRead="afterRead" multiple :maxCount="6 - imgList.length" :maxSize="10485760" @oversize="oversizeTit">
							<view
								class="w-[120rpx] !h-[120rpx] flex items-center justify-center border-1 border-[#cccccc] border-solid rounded-[14rpx]">
								<text class="iconfont iconjiahaoV6xx1 text-[50rpx] text-[#9c9c9c]"></text>
							</view>
						</u-upload>
					</block>
				</view>
			</template>
			<view class="w-full footer bg-[#fff]">
				<view
					class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border flex">
					<u-button type="info" class="flex-1 mr-[25rpx]" @click="$emit('close')" shape="circle">
						取消
					</u-button>
					<u-button type="primary" class="flex-1" @click="handleConfirm" shape="circle">
						确认
					</u-button>
				</view>
			</view>
		</view>
	</u-popup>
	<pay ref="payRef"></pay>
</template>

<script setup lang="ts">
	import { ref, defineProps, defineEmits } from 'vue'
	import { uploadImage } from '@/app/api/system'
	import { img, redirect, getToken } from '@/utils/common'
	const payRef = ref(null)
	import useSystemStore from '@/stores/system';
	const systemStore = useSystemStore()
	// 定义接收的参数
	const props = defineProps({
		show: {
			type: Boolean,
			default: false
		},
		order: {
			type: Object,
			default: () => ({})
		},
		actionKey: {
			type: String,
			default: ''
		}
	});
	const calcLocation = () => {
		// getLocation()
	}
	// 定义事件
	const emit = defineEmits(['close', 'confirm']);

	// 图片列表
	const imgList = ref<string[]>([])
	const oversizeTit = () => {
		uni.showToast({ title: '图片体积过大，请压缩后上传', icon: 'none' })
	}
	// 处理图片上传
	const afterRead = (event : any) => {
		event.file.forEach((item : any) => {
			upload(item);
		})
	}

	// 上传图片到服务器
	const upload = (data : any) => {
		if (imgList.value.length >= 6) {
			uni.showToast({ title: `最多允许上传6张图片`, icon: 'none' })
			return false
		}

		uploadImage({
			filePath: data.url,
			name: 'file'
		}).then((res : any) => {
			if (imgList.value.length < 6) {
				imgList.value.push(res.data.url)
			}
		}).catch(() => {
			uni.showToast({ title: '图片上传失败', icon: 'none' })
		})
	}

	// 删除图片
	const deleteImage = (index : number) => {
		imgList.value.splice(index, 1)
	}

	// 确认按钮点击
	const handleConfirm = () => {
		// 根据不同的actionKey返回不同的数据
		if (props.actionKey === 'again_check') {
			emit('confirm', { imgList: imgList.value });
		} else {
			emit('confirm');
		}
	}
</script>

<style scoped>
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>