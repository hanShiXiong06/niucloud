<template>
	<map style="width:100%;height:420rpx;" :latitude="lat" :longitude="lng" :markers="points" :scale="16"
		@tap="getMapLocation"></map>

	<view class="tk-card fb" v-if="form.address">
		<view class="">
			<view class="addressname w-[420rpx]">{{form.address}}</view>
			<view class="allarea w-[420rpx] mt-[14rpx]">{{form.full_address}}</view>
		</view>
		<view class="editaddress" @click="getMapLocation">修改地址</view>
	</view>
	<view class="index tk-card">
		<view v-if="!form.address" class="addaddress" @click="getMapLocation">选择收货地址></view>
		<up-form :model="form" ref="uForm">
			<up-form-item label="姓名" label-width="188rpx">
				<up-input v-model="form.name" placeholder="请输入姓名" />
			</up-form-item>
			<up-form-item label="电话" label-width="188rpx">
				<up-input v-model="form.mobile" placeholder="请输入电话" />
			</up-form-item>
			<up-form-item label="详细地址" label-width="188rpx">
				<up-textarea autoHeight type="textarea" v-model="form.full_address" placeholder="详细地址如:京台家园B5-1-204" />
			</up-form-item>
		</up-form>
		<view class="fl justify-center mt-[24rpx]">
			<view v-if="!form.id" class="tk-bbut mt-[48rpx]" style="background: #2979ff;color: #ffffff;" @click="add()">
				确定添加</view>
			<view v-if="form.id" class="tk-bbut mt-[48rpx]" style="background: #2979ff;color: #ffffff;" @click="edit()">
				确定修改</view>
		</view>


	</view>

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
		updateJhkdAddress,
		getJhkdRegeo,
		getJhkdAddressDetail, editJhkdAddress
	} from '@/addon/tk_jhkd/api/tkjhkd'
	import { goback } from '@/addon/tk_jhkd/utils/ts/goto';
	const form = reactive({})
	const show = ref()
	const lat = ref<any>(25.23856)
	const lng = ref<any>(103.04205)
	const points = ref([{
		id: 1, //点击事件会调用此id,建议不同点设置不同id
		title: '当前位置', //标记点的名称
		latitude: 25.23856, //纬度
		longitude: 103.04205, //经度
		iconPath: '/addon/tk_jhkd/utils/images/location.png', //图标样式路径,用绝对路径,不要写前面的../
		width: 20, //图标宽度
		height: 20, //图标高度
	}])
	const getMapLocation = async () => {
		uni.chooseLocation({
			success: function (res) {
				lng.value = res.longitude
				lat.value = res.latitude
				let addressName = res.name
				let value = lng.value + ',' + lat.value
				console.log(res)
				getJhkdRegeo({ value: value }).then((res) => {
					let addressinfo = res.data.addressComponent
					let province = addressinfo.province
					let city = addressinfo.city
					let district = addressinfo.district
					let township = addressinfo.township
					if (city == "") {
						city = province
					}
					form.address = province + '-' + city + '-' + district + '-' + township
					form.longitude = lng.value
					form.latitude = lat.value
					form.city_id = addressinfo.adcode
					// form.full_address = township + '(' + addressName + ')'
					form.full_address = addressName
				}).catch((e) => {

				})
			}
		});

	}
	const edit = async () => {
		await editJhkdAddress(form)
		uni.navigateTo({
			url: '/addon/tk_jhkd/pages/addresslist'
		})
		uni.$u.toast(res.msg)
	}
	const add = async () => {
		const res = await updateJhkdAddress(form)
		uni.navigateTo({
			url: '/addon/tk_jhkd/pages/addresslist'
		})
		uni.$u.toast(res.msg)

	}
	const getData = async (id : number) => {
		const data = await getJhkdAddressDetail(id)
		Object.assign(form, data.data)
		console.log('form', data.data)
	}
	onLoad((options : any) => {
		if (options.id) {
			getData(options.id)
		}
	})
</script>

<style lang="scss" scoped>
	@import '@/addon/tk_jhkd/utils/styles/common.scss';

	.allarea {
		font-size: 24rpx;
		color: #757575;
		overflow: hidden;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		text-overflow: ellipsis;
	}

	.addressname {
		font-size: 28rpx;
		font-weight: bold;
		overflow: hidden;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		text-overflow: ellipsis;
	}

	.editaddress {
		padding: 12rpx;
		border-color: #2979ff;
		color: #2979ff;
		border-style: solid;
		border-radius: 8rpx;
		border-width: 4rpx;
	}

	.addaddress {
		margin: 24rpx 128rpx;
		padding: 12rpx;
		border-color: #2979ff;
		color: #2979ff;
		border-style: solid;
		border-radius: 8rpx;
		border-width: 4rpx;
		text-align: center;
	}
</style>