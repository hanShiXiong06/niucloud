<template>
	
</template>

<script setup lang="ts">
// 优惠券组件
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { img, redirect } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import { useLogin } from '@/hooks/useLogin';
import useMemberStore from '@/stores/member'
import { getCouponComponents, getCoupon } from '@/addon/tk_jhkd/api/coupon';

const props = defineProps(['component', 'index']);
const diyStore = useDiyStore();
const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info)
const skeleton = reactive({
	type: 'banner',
	loading: false,
	config: {
		gridRows: 1,
		gridRowsGap: '0rpx',
		headHeight: '170rpx'
	}
})

const diyComponent = computed(() => {
	if (diyStore.mode == 'decorate') {
		return diyStore.value[props.index];
	} else {
		return props.component;
	}
})

const warpCss = computed(() => {
	var style = '';
	if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.topRounded) style += 'border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	if (diyComponent.value.bottomRounded) style += 'border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;';
	return style;
})

const couponStyle4Css = computed(() => {
	var style = '';
	if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`;
	else style += 'background-color:' + (diyComponent.value.componentStartBgColor || diyComponent.value.componentEndBgColor) + ';';
	return style;
})

const toLink = (url: any) => {
	if (diyStore.mode == 'decorate') return;
	redirect({ url })
}

const couponList: any = ref([])
const getCouponListFn = () => {
	let data: object = {
		num: diyComponent.value.source == 'all' ? diyComponent.value.num : '',
		coupon_ids: diyComponent.value.source == 'custom' ? diyComponent.value.couponIds : '',
	};
	getCouponComponents(data).then((res: any) => {
		couponList.value = res.data
		skeleton.loading = false;

		// 数据为空时隐藏整个组件
		// if(couponList.value.length == 0) {
		//     diyComponent.value.pageStyle = '';
		// }
	})
}

const couponItemLink = (data: any) => {
	// redirect({ url: '/addon/shop/pages/coupon/detail', param: { coupon_id: data.id } })
	collecting(data.id)
}

onMounted(() => {
	refresh();
});

const refresh = () => {

	// 装修模式下设置默认图
	if (diyStore.mode == 'decorate') {
		let obj = {
			title: '满减券',
			type_name: '通用券',
			price: 100,
			min_condition_money: 0,
		};
		for (let i = 0; i < 4; i++) {
			couponList.value.push(obj);
		}
	} else {
		getCouponListFn();
	}
}

const collecting = (coupon_id: any) => {
	if (diyStore.mode == 'decorate') return;
	if (!userInfo.value) {
		useLogin().setLoginBack({ url: '/addon/tk_jhkd/pages/coupon/list' })
		return false
	}
	getCoupon({ coupon_id, number: 1 }).then(res => {
		// detail.value.btnType = 'using'
	})
}
</script>

<style lang="scss" scoped>
.coupon-wrap {
	&.style-1 {
		.coupon-list {
			position: relative;
			height: 270rpx;
			width: 100%;
			white-space: nowrap;
			padding: 30rpx 40rpx 0;
			box-sizing: border-box;

			&::before {
				content: "";
				position: absolute;
				top: 0;
				left: 20rpx;
				right: 20rpx;
				bottom: 0;
				background: linear-gradient(#FEF9EC, #FCD9A5);
				border-radius: 24rpx;
			}
		}

		.coupon-buy-btn {
			border-radius: 50rpx;
		}
	}

	&.style-2 {
		.coupon-list {
			position: relative;
			height: 170rpx;
			width: 100%;
			white-space: nowrap;
			padding: 20rpx 0 20rpx 20rpx;
			box-sizing: border-box;
			overflow: hidden;
			background: linear-gradient(#EE3928, #EF3F30);
		}

		.coupon-buy-btn {
			border-radius: 50rpx;
		}
	}

	&.style-3 {
		display: flex;
		align-items: center;
		padding: 20rpx;

		.desc {
			padding-top: 12rpx;
			height: 164rpx;
			margin-right: 10rpx;
			box-sizing: border-box;
		}

		.coupon-list {
			flex: 1;
			position: relative;
			white-space: nowrap;
			box-sizing: border-box;
			overflow: hidden;

			.coupon-item-content {
				position: relative;
				background: linear-gradient(160deg, #FD5F2F 0%, #F6370F 100%);
				width: 146rpx;
				height: 108rpx;
				border-radius: 12rpx;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				padding: 12rpx 0 14rpx;
				box-sizing: border-box;

				&::after {
					content: "";
					position: absolute;
					border: 12rpx solid #fff;
					border-right-color: transparent;
					border-top-color: transparent;
					border-radius: 50%;
					top: 50%;
					transform: rotate(45deg) translateY(-50%);
					right: -5rpx;
				}

				&::before {
					content: "";
					position: absolute;
					border: 12rpx solid #fff;
					border-left-color: transparent;
					border-bottom-color: transparent;
					border-radius: 50%;
					top: 50%;
					transform: rotate(45deg) translateY(-50%);
					left: -22rpx;
				}
			}
		}

		.single-coupon {
			.coupon-item-content {
				position: relative;
				background: linear-gradient(160deg, #FD5F2F 0%, #F6370F 100%);
				height: 144rpx;
				border-radius: 12rpx;
				padding: 12rpx 0 14rpx;
				box-sizing: border-box;

				&::after {
					content: "";
					position: absolute;
					border: 14rpx solid #fff;
					border-right-color: transparent;
					border-top-color: transparent;
					border-radius: 50%;
					left: 139rpx;
					transform: rotate(-45deg);
					top: -18rpx;
				}

				&::before {
					content: "";
					position: absolute;
					border: 14rpx solid #fff;
					border-left-color: transparent;
					border-bottom-color: transparent;
					border-radius: 50%;
					left: 139rpx;
					transform: rotate(-45deg);
					bottom: -16rpx;
				}

				.coupon-left {
					position: relative;

					&::after {
						content: '';
						position: absolute;
						top: 50%;
						right: 0;
						transform: translateY(-50%);
						border-right: 2rpx dashed #fff;
						width: 2rpx;
						height: 88rpx;
					}
				}
			}
		}

		.coupon-buy-btn {
			border-radius: 50rpx;
		}
	}

	&.style-4 {
		padding: 20rpx 24rpx;

		.coupon-list {
			flex: 1;
			position: relative;
			white-space: nowrap;
			box-sizing: border-box;
			overflow: hidden;

			.coupon-item {
				width: calc(50% - 6rpx);
			}
		}
	}
}
</style>
