<template>
	<mescroll-body ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getAddressListFn">
		<view v-if="listData.length > 0" class="tk-card" v-for="(item, index) in listData" key=index>
			<view class="fb">
				<view class="fl" @click="selectaddress(index)">
					<view class="mr-[40rpx] w-[180rpx] text-sm">{{ item.name }}</view>
					<view class="text-base">{{ item.mobile }}</view>
				</view>
				<up-icon class="mr-[22rpx]" name="edit-pen" color="#8a899c" size="18"
					@click="goto('/tk_jhkd/pages/addressedit?id=' + item.id)"></up-icon>
			</view>

			<view class="tk-b-bottom mt-[-4px]"></view>
			<view class="fb items-center mt-[8px]">
				<view @click="selectaddress(index)">
					<view class="text-xs font-weight">{{ item.address }}</view>
					<view class="text-xs text-[#434343] mt-[4px]">{{ item.full_address }}</view>
				</view>
				<up-icon class="mr-[22rpx]" name="trash" color="#bebebe" size="16" @click="dodelete(item.id)"></up-icon>
			</view>
		</view>
		<mescroll-empty v-if="!listData.length && loading" :option="{ tip: '没有地址数据' }"></mescroll-empty>
		<view class="tk-butto" @click="goto('/addon/tk_jhkd/pages/addressedit')">新增地址</view>
	</mescroll-body>

</template>
<script lang="ts" setup>
	import {
		ref,
		reactive
	} from 'vue'
	import {
		onLoad
	} from '@dcloudio/uni-app'
	import {
		getJhkdAddressList, deleteJhkdAddress
	} from '@/addon/tk_jhkd/api/tkjhkd'
	import { t } from '@/locale'
	import { goto } from '@/addon/tk_jhkd/utils/ts/goto';
	import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
	import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
	import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
	import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
	const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
	const listData = ref([])
	const type = ref()
	let loading = ref<boolean>(false);
	const dodelete = async (id : number) => {
		await deleteJhkdAddress(id)
		getMescroll().resetUpScroll()
	}
	const selectaddress = (id) => {
		let addresstype = uni.getStorageSync('addresstype')
		uni.setStorageSync(addresstype, listData.value[id])
		goto('/tk_jhkd/pages/ordersubmit')
	}
	const getAddressListFn = (mescroll) => {
		let data = ref({});
		loading.value = false;
		data.value.page = mescroll.num;
		data.value.page_size = mescroll.size;
		getJhkdAddressList(data.value).then((res) => {
			let newArr = res.data.data;
			mescroll.endSuccess(newArr.length);
			//设置列表数据
			if (mescroll.num == 1) {
				listData.value = []; //如果是第一页需手动制空列表
			}
			listData.value = listData.value.concat(newArr);
			loading.value = true;
		}).catch((e) => {
			console.log('erro', e)
			loading.value = true;
			mescroll.endErr(); // 请求失败, 结束加载
		})
	}
	onLoad((options : any) => {
		if (options.type) {
			type.value = options.type
			uni.setStorageSync('addresstype', type.value)
		}
	})
</script>

<style lang="scss" scoped>
	@import '@/addon/tk_jhkd/utils/styles/common.scss';

	.setmoren {
		background: linear-gradient(-90deg, #67c6d0 0%, #19d444 100%);
		color: #ffffff;
		padding: 4rpx 12rpx;
		border-radius: 8rpx;
	}

	.dwmoren {
		background: linear-gradient(-90deg, #35464b 0%, #0a591b 100%);
		color: #ffffff;
		padding: 4rpx 12rpx;
		border-radius: 8rpx;
	}
</style>