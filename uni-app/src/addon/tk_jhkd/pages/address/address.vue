<template>
	<view class="address bg-[var(--page-bg-color)] min-h-[100vh]" v-if="!loading" :style="themeColor()">
		<view class="sidebar-margin pt-[var(--top-m)]" v-if="addressList.length" scroll-y="true">
			<view class="mb-[var(--top-m)] rounded-[var(--rounded-big)] overflow-hidden"
				v-for="(item, index) in addressList">
				<view class="flex flex-col card-template">
					<view class="flex-1 line-feed mr-[20rpx]" @click="selectAddress(item)">
						<view class="flex items-center">
							<view class="text-[#333] text-[30rpx] leading-[34rpx]  font-550">{{ item.name
							}}</view>
							<text class="text-[#333] text-[30rpx] ml-[10rpx] font-550">{{ item.mobile
							}}</text>
						</view>
						<view class="mt-[16rpx] text-[26rpx] line-feed text-[var(--text-color-light9)] leading-[1.4]">
							{{ item.full_address }}
						</view>
					</view>
					<view class="line-box !mt-[20rpx] !bg-[#F2F2F2]"></view>
					<view class="flex justify-end pt-[20rpx]">
						<view class="flex">
							<view @click.stop="copyAddress(item.id)" class="flex items-center">
								<view>
									<up-icon :name="img('addon/tk_jhkd/icon/copy.png')" size="18"></up-icon>
								</view>
								<view class="text-[26rpx] ml-[12rpx] font-500">复制</view>
							</view>
							<view @click.stop="editAddress(item.id)" class="flex items-center ml-4">
								<view>
									<up-icon :name="img('addon/tk_jhkd/icon/edit.png')" size="16"></up-icon>
								</view>
								<view class="text-[26rpx] ml-[12rpx] font-500">编辑</view>
							</view>
							<view @click.stop="deleteAddressFn(item.id, index)" class="flex items-center ml-4">
								<view>
									<up-icon :name="img('addon/tk_jhkd/icon/delete.png')" size="18"></up-icon>
								</view>
								<view class="text-[26rpx] ml-[12rpx] font-500">删除</view>
							</view>

						</view>
					</view>
				</view>
			</view>
		</view>
		<view class="h-24"></view>
		<mescroll-empty v-if="!addressList.length" :option="{ tip: '暂无收货地址' }"></mescroll-empty>
		<view class="w-full footer z-999999999">
			<view
				class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="bg-[#0057FE] text-[#fff] h-[80rpx] leading-[80rpx] rounded-[100rpx] text-[26rpx] font-500"
					@click="addAddress">新增地址</button>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { redirect, img, mobileHide } from '@/utils/common'
import { getAddressList, deleteAddress } from '@/app/api/member'
import { t } from '@/locale'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';

const loading = ref(true)
const current = ref(0)
const tabs = ref([
	{ name: '快递地址', key: 'address' },
	{ name: '同城配送地址', key: 'address' }
])
const addressList = ref<object[]>([])
const locationAddressList = ref<object[]>([])
const type = ref('')
const source = ref('')

onLoad((data) => {
	type.value = data.type || ''
	source.value = data.source || ''
	if (data.type) current.value = data.type == 'address' ? 0 : 1
})

getAddressList({}).then(({ data }) => {
	const address = [], locationAddress = []
	data.forEach(item => {
		item.type == 'address' ? address.push(item) : locationAddress.push(item)
	})
	if (!source.value) { //地址管理使用
		addressList.value = data;
	} else { // 区分同城配送地址和快递地址
		addressList.value = current.value == 0 ? address : locationAddress;
	}
	loading.value = false
}).catch(() => {
	loading.value = false
})


const switchTab = (event) => {
	current.value = event.index
}

const addAddress = () => {
	const url = `/addon/tk_jhkd/pages/address/${tabs.value[current.value].key}_edit`
	redirect({ url, param: { type: tabs.value[current.value].key, source: source.value } })
}
const copyAddress = (id: number) => {
	const url = `/addon/tk_jhkd/pages/address/${tabs.value[current.value].key}_edit`
	redirect({ url, param: { id, type: 'copy', source: source.value } })
}


const editAddress = (id: number) => {
	const url = `/addon/tk_jhkd/pages/address/${tabs.value[current.value].key}_edit`
	redirect({ url, param: { id, type: tabs.value[current.value].key, source: source.value } })
}

const addressOptions = ref([
	{
		text: t('delete'),
		style: {
			backgroundColor: '#F56C6C'
		}
	}
])

const selectAddress = (data: object) => {
	const selectAddress = uni.getStorageSync('selectAddressCallback')
	if (selectAddress) {
		selectAddress.address_id = data.id

		uni.setStorage({
			key: 'selectAddressCallback',
			data: selectAddress,
			success() {
				redirect({ url: selectAddress.back, mode: 'redirectTo' })
			}
		})
	}
}

const deleteAddressFn = (id: number, index: number) => {
	deleteAddress(id).then(() => {
		addressList.value.splice(index, 1)
	}).catch()
}
</script>

<style lang="scss" scoped>
@import '@/addon/tk_jhkd/utils/styles/common.scss';

:deep(.up-tabs__wrapper__nav__line) {
	bottom: 0;
	background: var(--primary-color) !important;
}

.line-feed {
	word-wrap: break-word;
	word-break: break-all;
}
</style>