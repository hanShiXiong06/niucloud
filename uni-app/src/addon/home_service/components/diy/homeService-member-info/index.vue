<template>
	<view :style="warpCss">
		<view class="pt-[34rpx] member-info" :style="!props.global.topStatusBar.isShow ? navbarInnerStyle : ''">
			 <!-- #ifdef MP-WEIXIN -->
			 <view :style="navbarInnerStyle"></view>
			 <!-- #endif -->
 			<view v-if="info" class="flex ml-[32rpx] mr-[52rpx]  items-center relative" :style="styleey" >
				<!-- 唤起获取微信 -->
 					<u-avatar :src="img(info.headimg)" size="55" :default-url="img('static/resource/images/default_headimg.png')" leftIcon="none" @click="clickAvatar"  ></u-avatar>

					<view class="ml-[22rpx]">
						<view class="text-[#222222] truncate  font-bold text-lg max-w-[290rpx]" :style="{ color : diyComponent.textColor }">{{ info.nickname }}</view>
						<view class="text-[#696B70] text-[24rpx] mt-[10rpx]" :style="{ color : diyComponent.textColor }">UID：{{ info.member_no }}</view>
					</view>
					<view class="set-icon flex items-center ml-[auto]"  >
						<view @click="redirect({ url: '/app/pages/setting/index' })">
							<text class="nc-iconfont nc-icon-shezhiV6xx-1 text-[40rpx] ml-[10rpx]" :style="{ color : diyComponent.textColor }"></text>
						</view>
					</view>
			</view>
			
			<view v-else class="flex ml-[32rpx] mr-[52rpx]  items-center relative" @click="toLogin"  :style="styleey">
 					<u-avatar :src="img('static/resource/images/default_headimg.png')" size="55"   />

					<view class="ml-[22rpx]">
						<view class="text-[#222222] font-bold text-lg" :style="{ color : diyComponent.textColor }">
							{{ t('login') }}/{{ t('register') }}
						</view>
					</view>
					<view class="set-icon flex items-center ml-[auto]"  >
						<view @click="redirect({ url: '/app/pages/setting/index' })">
							<text class="nc-iconfont nc-icon-shezhiV6xx-1 text-[40rpx] ml-[10rpx]" :style="{ color : diyComponent.textColor }"></text>
						</view>
					</view>
			 
				
			</view>

			<view class="flex my-[30rpx] mb-0 py-[30rpx] items-center">
				<view class="flex-1 text-center">
					<view class="font-bold">
						<view @click="redirect({ url:  '/app/pages/member/balance'  })" class="text-[35rpx]" :style="{ color : diyComponent.textColor }">{{money}}<text class="text-[22rpx] pl-[10rpx] ">元</text></view>
					</view>
					<view class="text-sm mt-[10rpx]">
						<view @click="redirect({ url:  '/app/pages/member/balance' })" :style="{ color : diyComponent.accountTextColor }">{{ t('balance') }}</view>
					</view>
				</view>
				<view class="flex-1 text-center">
					<view class="font-bold">
						<view @click="redirect({ url: info ? '/addon/home_service/user/pages/coupon/member_coupon' : '' })" class="text-[35rpx]" :style="{ color : diyComponent.textColor }">{{ coupinCardInfo.coupon_count  || '0'}}<text class="text-[22rpx] pl-[10rpx]">{{t('cardUnit')}}</text></view>
					</view>
					<view class="text-sm mt-[10rpx]">
						<view @click="redirect({ url: info ? '/addon/home_service/user/pages/coupon/member_coupon' : '' })" :style="{ color : diyComponent.textColor }">优惠券</view>
					</view>
				</view>
				<view class="flex-1 text-center">
					<view class="font-bold">
						<view @click="redirect({ url: info ? '/addon/home_service/user/pages/card/my_card' : '' })" class="text-[35rpx]"  :style="{ color : diyComponent.textColor }">{{ parseInt(coupinCardInfo?.card_count) || 0 }}<text class="text-[22rpx] pl-[10rpx] ">张</text></view>
					</view>
					<view class="text-sm mt-[10rpx]">
						<view @click="redirect({ url: info ? '/addon/home_service/user/pages/card/my_card' : '' })" :style="{ color : diyComponent.textColor }">次卡</view>
					</view>
				</view>
			</view>
		</view>

		<!-- #ifdef MP-WEIXIN -->
		<information-filling ref="infoFill"></information-filling>
		<!-- #endif -->

	</view>
</template>

<script lang="ts" setup>
	import { ref, computed, watch,onMounted } from 'vue';
	import useMemberStore from '@/stores/member'
	import { useLogin } from '@/hooks/useLogin'
	import { img, isWeixinBrowser, redirect, urlDeconstruction, moneyFormat } from '@/utils/common'
	import { getCouponCard } from '@/addon/home_service/api/diy'
	import { t } from '@/locale'
	import { wechatSync } from '@/app/api/system'
	import useDiyStore from '@/app/stores/diy'
	import useSystemStore from "@/stores/system";
	const systemStore = useSystemStore()
	onMounted(() => {
		getCouponCardFn()
	});
	
	const coupinCardInfo = ref({})
	const getCouponCardFn = () =>{
		getCouponCard().then((res)=>{
			coupinCardInfo.value = res.data
		})
	}
	const props = defineProps(['component', 'index','global']);

	const diyStore = useDiyStore();

	const diyComponent = computed(() => {
		if (diyStore.mode == 'decorate') {
			return diyStore.value[props.index];
		} else {
			return props.component;
		}
	})

	const warpCss = computed(() => {
        let style = '';
        if(diyComponent.value.componentStartBgColor) {
            if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
            else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
        }
		if (diyComponent.value.bgUrl) {
			style += 'background-image:url(' + img(diyComponent.value.bgUrl) + ');';
			style += 'background-size: 100%;';
			style += 'background-repeat: no-repeat;';
		}
		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		return style;
	})

	const memberStore = useMemberStore()

	// #ifdef H5
	const { query } = urlDeconstruction(location.href)
	if (query.code && isWeixinBrowser()) {
		wechatSync({ code: query.code }).then(res => {
			memberStore.getMemberInfo()
		})
	}
	// #endif

	const info = computed(() => {
		// 装修模式
		if (diyStore.mode == 'decorate') {
			return {
				headimg: '',
				nickname: '昵称',
				balance: 0,
				point: 0,
				money: 0,
				member_no: 'NIU0000021'
			}
		} else {
			return memberStore.info;
		}
	})

	const money = computed(() => {
		if (info.value) {
			let m = parseFloat(info.value.balance) + parseFloat(info.value.money)
			return moneyFormat(m.toString());
		} else {
			return 0;
		}
	})

	const toLogin = () => {
		useLogin().setLoginBack({ url: '/addon/home_service/user/pages/member/index' })
	}

	const infoFill = ref(false)
	const clickAvatar = () => {
		// #ifdef MP-WEIXIN
		infoFill.value.show = true
		// #endif

		// #ifdef H5
		if (isWeixinBrowser()) {
			useLogin().getAuthCode({ scopes : 'snsapi_userinfo' })
		} else {
			redirect({ url: '/app/pages/member/personal' })
		}
		// #endif
	}
	// 导航栏内部盒子的样式
const navbarInnerStyle = computed(() => {
    let style = '';
    // 导航栏宽度，如果在小程序下，导航栏宽度为胶囊的左边到屏幕左边的距离
    // #ifdef MP
    if (props.global.topStatusBar.isShow == false) {
        // style += 'height:' + systemStore.menuButtonInfo.height + 'px;';
        style += 'padding-top:' + systemStore.menuButtonInfo.top + 'px;';
    }
    // #endif
    return style;
})

const styleey = computed(() => {
	  let style = '';
	  // #ifdef MP
	  
	   if (props.global.topStatusBar.isShow == false) {
			let rightButtonWidth = systemStore.menuButtonInfo.width ? systemStore.menuButtonInfo.width * 2 + 'rpx' : '70rpx';
			style += 'margin-right: 0;';
		}
		// #endif
		return style
})
</script>

<style lang="scss" scoped>
	.member-info {
		// background-image: linear-gradient(#E3F0FF, #F5F6F8)
	}
</style>
