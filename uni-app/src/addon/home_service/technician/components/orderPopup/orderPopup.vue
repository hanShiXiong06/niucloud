<template>
	<!-- 对于修改服务时间，直接使用u-datetime-picker作为独立弹窗 -->
	<template v-if="actionKey === 'edit_reserve_service_time'">
		<!-- 时间选择组件 -->
		<t-datetime 
			:show.sync="show" 
			:delayMin="0" 
			:canToday="false" 
			@confirm="handleTimeConfirm"
			@update:show="handleTimeClose"
			:minDate="new Date()"
		></t-datetime>
	</template>

	<!-- 其他类型的弹窗使用原来的u-popup -->
	<u-popup v-else :show="show" mode="bottom" @close="$emit('close')" round="10">
		<view class="w-[100vw] p-[25rpx] box-border rounded-[20rpx] bg-white">
			<!-- 出发确认弹窗 -->
			<template v-if="actionKey === 'action_depart'">
				<view class="text-center mb-[25rpx] text-[32rpx] font-bold">
					确认出发
				</view>
				<view class="text-center text-[#666] text-[28rpx] leading-[1.8]">
					请提前规划出发路线，避免因绕路超时！
				</view>
					<view class="text-[28rpx] leading-[1.8]">
						当前定位：
					</view>
					<view class="text-[26rpx] flex mt-[30rpx] justify-between items-center">
						<view class="flex items-center max-w-[80%]">
							<u-icon name="map" color="#4059ff"></u-icon>
							<text
								class="pl-[5rpx] text-[28rpx]">{{systemStore.diyAddressInfo?.full_address ||'山西省太原市小店区'}}</text>
						</view>
					</view>
			</template>

			<!-- 拍照弹窗 -->
			<template v-if="actionKey === 'action_photo_taken'">
				<view class="text-center mb-[20rpx] text-[32rpx] font-bold">
					上传图片
				</view>
					<view class="text-[28rpx]">
						当前定位：
					</view>
					<view class="text-[26rpx] flex mt-[30rpx] justify-between items-center">
						<view class="flex items-center">
							<u-icon name="map" color="#4059ff"></u-icon>
							<text class="pl-[5rpx] text-[28rpx]">{{systemStore.diyAddressInfo?.full_address}}</text>
						</view>
					</view>
				<view class="text-[28rpx] my-[25rpx]">
					拍照打卡：
				</view>
				<view class="flex flex-wrap gap-3 mb-[30rpx]">
					<view class="relative w-[120rpx] !h-[120rpx] layout-one-content " v-for="(item,index) in imgList"
						:key="index">
						<image class="w-[100%] h-[100%] rounded-[14rpx]" :src="img(item)" mode="aspectFill" />
						<view
							class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
							@click="deleteImage(index)">
							<text
								class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-white"></text>
						</view>
					</view>
					<block v-if="imgList.length < 6">
						<u-upload accept="image" @afterRead="afterRead" multiple :maxCount="6 - imgList.length"
							:maxSize="10485760" @oversize="oversizeTit">
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
				<view class="text-[28rpx] my-[25rpx]">
					完成拍照：
				</view>
				<view class="flex flex-wrap gap-3 mb-[30rpx]">
					<view class="relative w-[120rpx] !h-[120rpx] layout-one-content " v-for="(item,index) in imgList"
						:key="index">
						<image class="w-[100%] h-[100%] rounded-[14rpx]" :src="img(item)" mode="aspectFill" />
						<view
							class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]"
							@click="deleteImage(index)">
							<text
								class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-white"></text>
						</view>
					</view>
					<block v-if="imgList.length < 6">
						<u-upload accept="image" @afterRead="afterRead" multiple :maxCount="6 - imgList.length"
							:maxSize="10485760" @oversize="oversizeTit">
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
</template>

<script setup lang="ts">
	import { ref, defineProps, defineEmits } from 'vue'
	import { uploadImage } from '@/app/api/system'
	import { img, isWeixinBrowser } from '@/utils/common'
	import { useLocation } from '@/hooks/useLocation'
	import useSystemStore from '@/stores/system';
	import tDatetime from '../t-datetime/t-datetime.vue'
	const locationVal = useLocation(true);
	locationVal.onLoad();
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

	// 时间选择器相关变量
	const reserve_service_time = ref<Date>(new Date())
	const calcLocation = () => {
		locationVal.init();
		setTimeout(()=>{
			uni.showToast({title:'刷新成功',icon:"none"})
		},500)
		if (!systemStore.diyAddressInfo?.full_address) {
			locationVal.reposition()
		}
	}
	
	// 定义事件
	const emit = defineEmits(['close', 'confirm']);

	// 图片列表
	const imgList = ref<string[]>([])

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

	// 时间选择确认处理（添加校验逻辑）
	const handleTimeConfirm = (e: any) => {
		const selectedTime = new Date(e.value);
		const currentTime = new Date();
		
		// 校验：选择的时间必须晚于当前时间
		if (selectedTime <= currentTime) {
			uni.showToast({
				title: '不能选择过去或当前时间，请重新选择',
				icon: 'none',
				duration: 2000
			});
			return; // 不提交，保持弹窗打开
		}
		
		// 校验通过，提交时间
		reserve_service_time.value = selectedTime;
		emit('confirm', { reserve_service_time: selectedTime });
	}

	// 时间选择器关闭处理
	const handleTimeClose = (showValue: boolean) => {
		console.log('handleTimeClose called:', showValue);
		if (!showValue) {
			emit('close');
		}
	}

	// 其他类型弹窗的确认按钮点击
	const handleConfirm = (value ?: any) => {
		// 根据不同的actionKey返回不同的数据
		if (props.actionKey === 'action_photo_taken' || props.actionKey === 'action_save_check') {
			emit('confirm', { imgList: imgList.value });
		} else {
			emit('confirm');
		}
		imgList.value = [];
	}

	const oversizeTit = () => {
		uni.showToast({ title: '图片体积过大，请压缩后上传', icon: 'none' })
	}
</script>

<style scoped>
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>