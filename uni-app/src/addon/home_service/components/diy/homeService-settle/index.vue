<template>
	<view :style="warpCss" class="rounded-[20rpx]">
		<view class="flex">
			<view class="w-[100%] mr-[25rpx] bg-[#fff] p-[25rpx] rounded-[20rpx]" @click="goSettle(0)">
				<view class="flex justify-between items-center">
					<view class="text-[30rpx]">
						{{diyComponent.leftText}}
					</view>
					<view>
						<image :src="img('addon/home_service/user/right.png')" mode="widthFix"  class="w-[25rpx] h-[25rpx] block" />
					</view>
				</view>
				<view class="text-[26rpx] text-[#999] mt-[20rpx]">
					{{diyComponent.leftDesc}}
				</view>
				<image :src="img('addon/home_service/diy/member/settle_1.png')" mode="widthFix"  class="w-[120rpx] mt-[20rpx] " />
			</view>
			<view class="w-[100%] bg-[#fff] p-[25rpx] rounded-[20rpx]"  @click="goSettle(1)">
				<view class="flex justify-between items-center">
					<view class="text-[30rpx]">
						{{diyComponent.rightText}}
					</view>
					<view>
						<image :src="img('addon/home_service/user/right.png')" mode="widthFix"  class="w-[25rpx] h-[25rpx] block" />
					</view>
				</view>
				<view class="text-[26rpx] text-[#999] mt-[20rpx]">
					{{diyComponent.rightDesc}}
				</view>
				<image :src="img('addon/home_service/diy/member/settle_2.png')" mode="widthFix"  class="w-[120rpx] mt-[20rpx] " />
			</view>
		</view>
	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useDiyStore from '@/app/stores/diy';
	import { img,redirect } from '@/utils/common';
	import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
	import {getTechnicianInfo,getStoreInfo,getTechnicianApply,getStoreApply} from '@/addon/home_service/api/diy'
	const technicianInfo = ref({})
	const sbumitStatus = ref(0)
	const getTechnicianInfoFn = () =>{
		getTechnicianApply().then((res) => {
			sbumitStatus.value = res.data.audit_status
			if (sbumitStatus.value == 0 || sbumitStatus.value == -1) {
				if(sbumitStatus.value == 0){
					uni.showToast({title:'入驻申请审核中，请耐心等待',icon:'none'})
				}else{
					uni.showToast({title:'入驻申请被拒绝，请重新提交申请',icon:'none'})
				}
				// redirect({ url: '/addon/home_service/user/pages/settle/submit_success',mode:'reLaunch' ,param: { status: sbumitStatus.value, formType: 'technician' } })
			}else if(sbumitStatus.value == 1){
				redirect({url:'/addon/home_service/technician/pages/member/index' })
			}else{
				uni.showToast({title:'您还没有入驻，请先申请入驻成为师傅',icon:'none'})
				// setTimeout(()=>{
				// 	redirect({url:'/addon/home_service/user/pages/settle/technician'})
				// },1000)
			}
		})
	}
	
	const storeInfo = ref({})
	const getStoreInfoFn = () =>{
		getStoreApply().then((res) => {
			if( res.data &&  res.data.audit_status == 1){
				redirect({url:'/addon/home_service/store/pages/member/index'});
				return
			}
			if ( res.data  && (res.data.audit_status ==  0 ||  res.data.audit_status ==  -1)) {
				if(sbumitStatus.value == 0){
					uni.showToast({title:'入驻申请审核中，请耐心等待',icon:'none'})
				}else{
					uni.showToast({title:'入驻申请被拒绝，请重新提交申请',icon:'none'})
				}
				
				// redirect({
				// 	url: '/addon/home_service/user/pages/settle/submit_success',
				// 	param: { status: res.data.audit_status, formType: 'store' },
				// 	mode:'reLaunch' 
				// })
			}else{
				uni.showToast({title:'您还没有门店，请先申请入驻门店',icon:'none'})
				// setTimeout(()=>{
				// 	redirect({url:'/addon/home_service/user/pages/settle/store',mode:'reLaunch' })
				// },1000)
			}
		
		})
	}
	
	const props = defineProps(['component', 'index']);
	const diyStore = useDiyStore();
	const diyComponent = computed(() => {
		if (diyStore.mode == 'decorate') {
			return diyStore.value[props.index];
		} else {
			return props.component;
		}
	})

	onMounted(() => {
		refresh();
	});
	const goSettle = (type:any) =>{
		if(type == 0){
			getTechnicianInfoFn()
			useSubscribeMessage().request('home_service_store_dispatch')
		}else{
			getStoreInfoFn()
		}
	}
	// 商品价格
	let goodsPrice = (data:any) =>{
		let price = "0.00";
		if(data.member_discount && getToken() && data.goodsSku.member_price != data.goodsSku.price){
			price = data.goodsSku.member_price || '0.00' // 会员价
		}else{
			price = data.goodsSku.price || '0.00'
		}
		return parseFloat(price).toFixed(2);
	}
	
	watch(() => diyComponent.value, (newValue, oldValue) => {
      refresh();
  },{deep: true})

	const refresh = () => {
      // 装修模式
      if (diyStore.mode == 'decorate') {
      }
  }

	const orderInfo = ref({})
	const warpCss = computed(() => {
        let style = '';
        style += 'position:relative;';
	    if(diyComponent.value.componentStartBgColor) {
	        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
	        else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
	    }

		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		return style;
	})

	const toList = (status:any) => {
		redirect({ url: '/addon/home_service/user/pages/order/list', param: { order_status: status } })
	}
</script>

<style>
</style>
