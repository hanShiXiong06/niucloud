<template>
	<view :style="warpCss">
		<view :style="maskLayer"></view>
		<view class="tk-card" v-if="diyStore.mode == 'decorate'"
			:style="{ padding: diyComponent.padding * 2 + 'rpx', background: diyComponent.background, borderRadius: diyComponent.radiussize * 2 + 'rpx' }">
			<view class="flex justify-between items-center">
				<view class="text-white text-[32rpx] font-bold" :style="{ color: diyComponent.textcolor }">
					{{ config.alias_name }}充值
				</view>
				<view class="detail-btn text-white text-[24rpx] opacity-80" :style="{ color: diyComponent.textcolor }"
					@click.stop="handleRedirect('/addon/ai_image/pages/package')">
					{{ config.alias_name }}明细 <uni-icons type="forward" size="14"
						:color="diyComponent.textcolor"></uni-icons>
				</view>
			</view>

			<view class="flex items-center mt-4">
				<view class="text-white text-[24rpx]" :style="{ color: diyComponent.textcolor }">我的{{ config.alias_name
				}}:
				</view>
				<view class="lightning-icon mx-2">
					<up-icon name="level" size="12" :color="diyComponent.textcolor"></up-icon>
				</view>
				<view class="text-white text-[36rpx] font-bold" :style="{ color: diyComponent.textcolor }">6000.00
				</view>
			</view>

			<view class="text-white text-[22rpx] opacity-70 mt-3" :style="{ color: diyComponent.textcolor }">
				{{ diyComponent.desc || `尊享8+数字权益，每日赠送算力` }}
			</view>

			<view class="mt-4 charge-btn flex items-center justify-center"
				:style="{ background: diyComponent.buttoncolor }"
				@click.stop="handleRedirect('/addon/ai_image/pages/package')">
				<uni-icons type="plus" size="16" :color="diyComponent.textcolor || '#333'"></uni-icons>
				<text class="ml-1" :style="{ color: diyComponent.textcolor }">充值中心</text>
			</view>
		</view>

		<view class="tk-card" v-else
			:style="{ padding: diyComponent.padding * 2 + 'rpx', background: diyComponent.background, borderRadius: diyComponent.radiussize * 2 + 'rpx' }">
			<view class="flex justify-between items-center">
				<view class="text-white text-[32rpx] font-bold" :style="{ color: diyComponent.textcolor }">
					{{ config.alias_name }}充值
				</view>
				<view class="detail-btn text-white text-[24rpx] opacity-80 flex items-center"
					:style="{ color: diyComponent.textcolor }"
					@click.stop="handleRedirect('/app/pages/member/point_detail')">
					{{ config.alias_name }}明细 <up-icon name="arrow-right" size="12"
						:color="diyComponent.textcolor"></up-icon>
				</view>
			</view>

			<view class="flex items-center mt-4" v-if="getToken() && account">
				<view class="text-white text-[24rpx]" :style="{ color: diyComponent.textcolor }">我的{{ config.alias_name
				}}:
				</view>
				<view class="lightning-icon mx-2">
					<up-icon name="level" size="12" :color="diyComponent.textcolor"></up-icon>
				</view>
				<view class="text-white text-[36rpx] font-bold" :style="{ color: diyComponent.textcolor }">{{
					config.point || '0'
				}}</view>
			</view>

			<view class="flex items-center mt-4" v-if="!getToken()">
				<view class="text-white text-[24rpx] font-bold login-text" :style="{ color: diyComponent.textcolor }"
					@click.stop="goLogin()">
					登录后查看{{ config.alias_name }}
				</view>
			</view>

			<view class="text-white text-[22rpx] opacity-70 mt-2" :style="{ color: diyComponent.textcolor }">
				{{ diyComponent.desc || `尊享10+数字权益，超多赠送` }}
			</view>

			<view v-if="!iosPay" class="mt-4 charge-btn flex items-center justify-center"
				:style="{ background: diyComponent.buttoncolor }"
				@click="redirect({ url: '/addon/ai_image/pages/package' })">
				<uni-icons type="plus" size="16" :color="diyComponent.textcolor || '#333'"></uni-icons>
				<text class="ml-1" :style="{ color: diyComponent.textcolor }">充值中心</text>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue';
import useDiyStore from '@/app/stores/diy';
import { img, redirect, getToken } from '@/utils/common';
import { getConfig, getStat } from '@/addon/ai_image/api/aiimage';
const config = ref<any>({});
getConfig().then((res) => {
	config.value = res.data;
})
const isLogin = ref(false)
const account = ref()

getStat().then((res) => {
	account.value = res.data
})
const iosPay = computed(() => {
	// #ifdef MP-WEIXIN
	const systemInfo = uni.getSystemInfoSync();
	return systemInfo.platform == 'ios' && config.value?.ios_pay == 0;
	// #endif
	// #ifndef MP-WEIXIN
	return false;
	// #endif
});
import { useLogin } from "@/hooks/useLogin";
const goLogin = () => {
	const login = useLogin();
	// 第三方平台自动登录
	// #ifdef MP
	login.getAuthCode();
	// #endif
	// #ifdef H5
	useLogin().setLoginBack({ url: '/app/pages/index/index' })
	// #endif
}
const props = defineProps(['component', 'index', 'pullDownRefreshCount']);
const diyStore = useDiyStore();
const diyComponent = computed(() => {
	if (diyStore.mode == 'decorate') {
		return diyStore.value[props.index];
	} else {
		return props.component;
	}
})

const warpCss = computed(() => {
	var style = '';
	style += 'position:relative;';
	if (diyComponent.value.componentStartBgColor) {
		if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
		else style += 'background-color:' + diyComponent.value.componentStartBgColor + ';';
	}

	if (diyComponent.value.componentBgUrl) {
		style += `background-image:url('${img(diyComponent.value.componentBgUrl)}');`;
		style += 'background-size: cover;background-repeat: no-repeat;';
	}

	if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	return style;
})

// 背景图加遮罩层
const maskLayer = computed(() => {
	var style = '';
	if (diyComponent.value.componentBgUrl) {
		style += 'position:absolute;top:0;width:100%;';
		style += `background: rgba(0,0,0,${diyComponent.value.componentBgAlpha / 10});`;
		style += `height:${height.value}px;`;

		if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
		if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	}

	return style;
});

watch(
	() => props.pullDownRefreshCount,
	(newValue, oldValue) => {
		// 处理下拉刷新业务
	}
)

onMounted(() => {
	refresh();
	// 装修模式下刷新
	if (diyStore.mode == 'decorate') {
		watch(
			() => diyComponent.value,
			(newValue, oldValue) => {
				if (newValue && newValue.componentName == 'RichText') {
					refresh();
				}
			}
		)
	}
});

const instance = getCurrentInstance();
const height = ref(0)

const refresh = () => {
	nextTick(() => {
		const query = uni.createSelectorQuery().in(instance);
		query.select('.diy-rich-text').boundingClientRect((data: any) => {
			height.value = data.height;
		}).exec();
	})
}

// 添加处理跳转的方法
const handleRedirect = (url: string) => {
	redirect({ url });
}

</script>

<style lang="scss" scoped>
@import '@/addon/ai_image/utils/styles/common.scss';

.tk-card {
	position: relative;
	overflow: hidden;
	padding: 30rpx;
	z-index: 0;

	&::after {
		content: '';
		position: absolute;
		top: -30%;
		right: -10%;
		width: 200rpx;
		height: 200rpx;
		background: rgba(255, 255, 255, 0.1);
		border-radius: 50%;
		z-index: 1;
	}

	&::before {
		content: '';
		position: absolute;
		bottom: -20%;
		left: -5%;
		width: 150rpx;
		height: 150rpx;
		background: rgba(255, 255, 255, 0.05);
		border-radius: 50%;
		z-index: 1;
	}
}

.detail-btn {
	cursor: pointer;
	padding: 4rpx 8rpx;
	z-index: 10;
	position: relative;

	&:active {
		opacity: 0.7;
	}
}

.charge-btn {
	background: linear-gradient(-90deg, #ffffff 30%, #f5f5f5 100%);
	padding: 12rpx 24rpx;
	border-radius: 30rpx;
	color: #333;
	font-weight: bold;
	box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.1);
	transition: all 0.3s ease;
	position: relative;
	z-index: 10;
	cursor: pointer;

	&:active {
		transform: scale(0.98);
	}
}

.lightning-icon {
	position: relative;
	animation: lightning 2s ease-in-out infinite;

	&::before {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 40rpx;
		height: 40rpx;
		background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0) 70%);
		border-radius: 50%;
		animation: glow 2s ease-in-out infinite;
		z-index: -1;
	}
}

.login-text {
	border-bottom: 1px dashed rgba(255, 255, 255, 0.5);
	padding-bottom: 4rpx;
}

@keyframes lightning {
	0% {
		transform: scale(1);
		opacity: 1;
	}

	50% {
		transform: scale(1.2);
		opacity: 0.8;
	}

	100% {
		transform: scale(1);
		opacity: 1;
	}
}

@keyframes glow {
	0% {
		transform: translate(-50%, -50%) scale(1);
		opacity: 0.5;
	}

	50% {
		transform: translate(-50%, -50%) scale(1.5);
		opacity: 0.2;
	}

	100% {
		transform: translate(-50%, -50%) scale(1);
		opacity: 0.5;
	}
}

:deep(.up-icon) {
	animation: float 3s ease-in-out infinite;
	filter: drop-shadow(0 2rpx 4rpx rgba(0, 0, 0, 0.2));
}

:deep(.uni-icons) {
	animation: pulse 2s ease-in-out infinite;
}

@keyframes float {
	0% {
		transform: translateY(0);
	}

	50% {
		transform: translateY(-4rpx);
	}

	100% {
		transform: translateY(0);
	}
}

@keyframes pulse {
	0% {
		transform: scale(1);
	}

	50% {
		transform: scale(1.1);
	}

	100% {
		transform: scale(1);
	}
}
</style>