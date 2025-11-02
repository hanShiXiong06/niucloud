<template>
	<view class="bg-[#f5f5f5] min-h-screen" v-if="!loading" :style="themeColor()">
		<!-- #ifdef MP-WEIXIN || APP-PLUS -->
		<top-tabbar :data="topTabbarData" scrollBool="1" :isBack="true" />
		<!-- #endif -->
		<!-- 门店名称 -->
		<view class="bg-white px-4 py-2 mt-3">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('storeName') }}</text>
			<input type="text" :placeholder="t('inputStoreName')" v-model="form.store_name"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 门店相关证照 -->
		<view class="bg-white px-4 py-2 ">
			<view class="flex items-center mb-3">
				<text class="text-base text-[30rpx] font-bold block">{{ t('storeCertificates') }}</text>
				<text class="text-[#999999] ml-2 text-sm">({{ t('uploadClearPhotos') }})</text>
			</view>

			<!-- 营业执照 -->
			<view class="flex items-start justify-between mb-3 bg-[#F5F9FA] p-[20rpx] rounded-lg">
				<view class="pr-3">
					<text class="text-[28rpx] font-medium text-gray-800 block mb-2">{{ t('businessLicense') }}</text>
					<view class="text-[22rpx] text-[#999999] leading-[34rpx]">
						<view>{{ t('storeBusinessLicenseTip') }}</view>
					</view>
				</view>
				<upload-img v-model="form.license_img"
					:bgUrl="img('addon/home_service/user/settle/store_form/store_example1.png')" :max-count="1"
					:multiple="false" />
			</view>
		</view>

		<!-- 上传门店图像 -->
		<view class="bg-white px-4 py-2">
			<view class="flex items-center mb-3">
				<text class="text-base text-[30rpx] font-bold block">{{ t('uploadStoreImage') }}</text>

			</view>
			<upload-img v-model="form.headimg" :max-count="1" :multiple="false" />
		</view>

		<!-- 我的定位 -->
		<view class="bg-white px-4 py-2">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">门店位置</text>
			<view
				class="bg-gray-50 rounded-lg px-3 py-3 flex items-center justify-between border border-gray-200 hover:border-blue-200 transition-all cursor-pointer"
				@tap="chooseLocation">
				<text class="text-gray-400 text-[28rpx]" v-if="!form.address">{{ t('selectLocation') }}</text>
				<text class="text-gray-700 text-[28rpx]" v-else>{{ form.address }}</text>
				<u-icon name="arrow-right" class="text-gray-400" size="16"></u-icon>
			</view>
		</view>

		<!-- 联系人姓名 -->
		<view class="bg-white px-4 py-2 ">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('contactPersonName') }}</text>
			<input type="text" :placeholder="t('inputContactPersonName')" v-model="form.contact_name"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 手机号码 -->
		<view class="bg-white px-4 py-2">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('contactPersonMobile') }}</text>
			<input type="number" :placeholder="t('inputContactPersonMobile')" v-model="form.mobile" :maxlength="11"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 身份证号 -->
		<view class="bg-white px-4 py-2">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('contactPersonIdcard') }}</text>
			<input type="text" :placeholder="t('inputContactPersonIdcard')" v-model="form.id_number" :maxlength="18"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 法人身份证照片 -->
		<view class="bg-white px-4 py-2">
			<view class="flex items-center mb-3">
				<text class="text-base text-[30rpx] font-bold block">{{ t('idcardUpload') }}</text>
				<text class="text-[#999999] ml-2 text-sm">({{ t('idcardUploadTip') }})</text>
			</view>

			<!-- 证件头像面 -->
			<view class="flex items-start justify-between mb-3 bg-[#F5F9FA] p-[20rpx] rounded-lg">
				<view class="pr-3">
					<text class="text-[28rpx] font-medium text-gray-800 block mb-2">{{ t('idcardFront') }}</text>
					<view class="text-[22rpx] text-[#999999] leading-[34rpx]">
						<view>1.{{ t('idcardFrontTip1') }}</view>
						<view>2.{{ t('idcardFrontTip3') }}</view>
					</view>
				</view>
				<upload-img v-model="form.id_card_front" :bgUrl="img('addon/home_service/user/settle/id_card1.png')"
					:max-count="1" :multiple="false" />
			</view>

			<!-- 证件国徽面 -->
			<view class="flex items-start justify-between bg-[#F5F9FA] p-[20rpx] rounded-lg">
				<view class="pr-3">
					<text class="text-[28rpx] font-medium text-gray-800 block mb-2">{{ t('idcardBack') }}</text>
					<view class="text-[22rpx] text-[#999999] leading-[34rpx]">
						<view>1.{{ t('idcardBackTip1') }}</view>
						<view>2.{{ t('idcardBackTip3') }}</view>
					</view>
				</view>
				<upload-img v-model="form.id_card_back" :bgUrl="img('addon/home_service/user/settle/id_card2.png')"
					:max-count="1" :multiple="false" />
			</view>
		</view>

		<!-- 申请说明 -->
		<view class="bg-white px-4 py-3">
			<view class="flex items-center mb-3">
				<text class="text-base text-[30rpx] font-bold block">{{ t('applyDescription') }}</text>
			</view>
			<input type="text" :placeholder="t('inputApplyDescription')" v-model="form.apply_desc"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 提交按钮 -->
		<view class="w-full footer bg-[#fff]">
			<view
				class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border z-10 bg-[#fff]">
				<button hover-class="none"
					class="!bg-[var(--primary-color)] !text-[#fff] !rounded-lg h-[80rpx] !leading-[80rpx] rounded-[10rpx] !text-[26rpx] font-500 relative z-999"
					@click="submitForm" :disabled="btnDisabled" :loading="operateLoading"
					:class="{'opacity-50': btnDisabled}">{{ t('confirmSubmit') }}
				</button>
			</view>
		</view>
		<view class="footer"></view>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'
	// 注意：loading-page组件需要在项目中存在，否则需要移除或替换为可用的加载组件
	const loading = ref<boolean>(false);
	const btnDisabled = ref<boolean>(false);
	const operateLoading = ref<boolean>(false);
	import { t } from '@/locale'
	import { img, redirect } from '@/utils/common' // 导入redirect函数
	import manifestJson from '@/manifest.json'
	import { getAddressByLatlng } from '@/app/api/system'
	import { onLoad } from '@dcloudio/uni-app'
	import uploadImg from '@/addon/home_service/user/components/upload-img/upload-img.vue'
	import { getStoreApply, applyStore, reapplyStore } from '@/addon/home_service/user/api/settle'
	import { topTabar } from '@/utils/topTabbar';
	// 系统状态管理
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '入驻资料', topStatusBar: { textColor: '#333' } })
	// 表单数据
	const form = ref({
		store_name: '',
		license_img: '',
		headimg: '',
		address: '',
		latitude: '',
		longitude: '',
		contact_name: '',
		mobile: '',
		id_number: '',
		id_card_front: '',
		id_card_back: '',
		apply_desc: '',
		// 添加缺失的地址字段，与technician_form.vue保持一致
		province_id: 0,
		city_id: 0,
		district_id: 0,
		lat: '',
		lng: '',
		full_address: '',
		address_name: '',
		area: ''
	})

	// 返回上一页
	const navigateBack = () => {
		uni.navigateBack()
	}

	// 获取门店入驻申请资料
	const getStoreApplyFn = (fromReapply : boolean = false) => {
		loading.value = true
		getStoreApply().then((res : any) => {
			loading.value = false
			if (res.data && res.data.member_id) {

				if (res.data.id) {
					form.value.id = res.data.id;
				}
				// 确保 audit_status 字段存在
				form.value.audit_status = res.data.audit_status || 0;
				// 如果有数据，填充表
				Object.assign(form.value, res.data)
				// 增强地址字段的赋值逻辑，确保地址正确显示
				if (!form.value.address) {
					// 优先使用address_name
					if (form.value.address_name) {
						form.value.address = form.value.address_name
					}
					// 如果没有address_name，使用full_address
					else if (form.value.full_address) {
						form.value.address = form.value.full_address
					}
					// 如果都没有，尝试拼接省市区
					else if (form.value.province && form.value.city && form.value.district) {
						form.value.address = form.value.province + form.value.city + form.value.district
					}
				}
			}
		}).catch(() => {
			loading.value = false
		})
	}

	// 选择位置 - 完整复制技师端实现
	const chooseLocation = () => {
		// #ifndef H5
		// 非H5环境（小程序、App等）使用原生位置选择
		uni.chooseLocation({
			success: (res) => {
				res.latitude && (form.value.lat = res.latitude);
				res.longitude && (form.value.lng = res.longitude);
				res.address && (form.value.area = res.address);
				res.name && (form.value.address_name = res.name);
				// 确保address字段被正确赋值
				if (res.address) {
					form.value.address = res.address;
				} else if (res.name) {
					form.value.address = res.name;
				}
				if (res.latitude && res.longitude) {
					let latng = res.latitude + ',' + res.longitude;
					getAddress(latng);
				}
			},
			fail: (res) => {
				// 处理失败情况
				if (res.errMsg && res.errno) {
					if (res.errno == 104) {
						uni.showToast({ title: '用户未授权隐私权限，选择位置失败', icon: 'none' });
					} else if (res.errno == 112) {
						uni.showToast({ title: '隐私协议中未声明，打开地图选择位置失败', icon: 'none' });
					} else {
						uni.showToast({ title: res.errMsg || '选择位置失败', icon: 'none' });
					}
				}
			}
		});
		// #endif

		// #ifdef H5
		// H5环境使用腾讯地图API
		const urlencode = form.value;
		uni.setStorageSync('serviceArea', urlencode);
		let backurl = location.origin + location.pathname;
		window.location.href = 'https://apis.map.qq.com/tools/locpicker?search=1&type=0&backurl=' + encodeURIComponent(backurl) + '&key=' + manifestJson.h5.sdkConfigs.maps.qqmap.key + '&referer=myapp';
		// #endif
	};

	const getAddress = (latlng : string) => {
		getAddressByLatlng({ latlng }).then((res : any) => {
			if (res.data) {
				form.value.full_address = '';
				form.value.full_address += res.data.province != undefined ? res.data.province : '';
				form.value.full_address += res.data.city != undefined ? '' + res.data.city : '';
				form.value.full_address += res.data.district != undefined ? '' + res.data.district : '';

				form.value.address_name = form.value.full_address.replace(/-/g, '');
				form.value.area = (res.data.province + res.data.city + res.data.district) || res.data.full_address;

				form.value.province_id = res.data.province_id != undefined ? res.data.province_id : 0;
				form.value.city_id = res.data.city_id != undefined ? res.data.city_id : 0;
				form.value.district_id = res.data.district_id != undefined ? res.data.district_id : 0;
				// 关键修复：确保address字段被正确更新
				form.value.address = res.data.full_address;
			}
		});
	};

	// onLoad函数 - 完整复制技师端实现
	onLoad((data : any) => {
		// 保存参数以便在getStoreApplyFn中使用
		const fromReapply = data.fromReapply === 'true' || data.fromReapply === true
		getStoreApplyFn(fromReapply) // 调用定义的函数，不需要参数
		if (uni.getStorageSync('serviceArea')) {
			const serviceArea = uni.getStorageSync('serviceArea')
			Object.assign(form.value, serviceArea)
			// 确保地址字段不为空
			if (serviceArea.name) {
				form.value.address = serviceArea.name
			} else if (serviceArea.address) {
				form.value.address = serviceArea.address
			}
			uni.removeStorageSync('serviceArea')
		}

		if (data.name) {
			form.value.address = data.addr
			if (data.latng) {
				getAddress(data.latng)
				// 直接从data参数中获取，移除getQueryVariable调用
				const tempArr = data.latng.split(',')
				form.value.lat = tempArr[0]
				form.value.lng = tempArr[1]
			}
		}
		// 添加地址显示的兜底逻辑
		if (!form.value.address) {
			if (form.value.address_name) {
				form.value.address = form.value.address_name;
			} else if (form.value.full_address) {
				form.value.address = form.value.full_address;
			} else if (form.value.area) {
				form.value.address = form.value.area;
			}
		}
	});

	// 表单验证和提交函数
	const submitForm = () => {

		// 表单验证
		if (!form.value.store_name) {

			uni.showToast({ title: t('pleaseInputStoreName'), icon: 'none' });
			return;
		}
		if (!form.value.license_img) {

			uni.showToast({ title: t('pleaseUploadBusinessLicense'), icon: 'none' });
			return;
		}
		if (!form.value.headimg) {

			uni.showToast({ title: t('pleaseUploadStoreImage'), icon: 'none' });
			return;
		}
		if (!form.value.address) {

			uni.showToast({ title: t('pleaseSelectLocation'), icon: 'none' });
			return;
		}
		if (!form.value.contact_name) {

			uni.showToast({ title: t('pleaseInputContactPersonName'), icon: 'none' });
			return;
		}
		// 手机号11位长度验证
		if (!form.value.mobile) {

			uni.showToast({ title: t('pleaseInputContactPersonMobile'), icon: 'none' });
			return;
		}
		if (!/^1\d{10}$/.test(form.value.mobile)) {

			uni.showToast({ title: t('pleaseInputValidMobile'), icon: 'none' });
			return;
		}
		if (!form.value.id_number) {

			uni.showToast({ title: t('pleaseInputContactPersonIdcard'), icon: 'none' });
			return;
		}
		if (form.value.id_number.length != 18) {

			uni.showToast({ title: t('pleaseInputValidIdcard'), icon: 'none' });
			return;
		}
		if (!form.value.id_card_front) {

			uni.showToast({ title: t('pleaseUploadIdcardFront'), icon: 'none' });
			return;
		}
		if (!form.value.id_card_back) {

			uni.showToast({ title: t('pleaseUploadIdcardBack'), icon: 'none' });
			return;
		}

		// 提交表单
		btnDisabled.value = true;
		operateLoading.value = true;

		// 准备提交的数据，只包含必要的字段
		const submitData = {
			store_name: form.value.store_name,
			license_img: form.value.license_img,
			headimg: form.value.headimg,
			address: form.value.address,
			lat: form.value.latitude || form.value.lat,
			lng: form.value.longitude || form.value.lng,
			contact_name: form.value.contact_name,
			mobile: form.value.mobile,
			id_number: form.value.id_number,
			id_card_front: form.value.id_card_front,
			id_card_back: form.value.id_card_back,
			apply_desc: form.value.apply_desc,
			province_id: form.value.province_id,
			city_id: form.value.city_id,
			district_id: form.value.district_id,
			address_name: form.value.address_name,
			area: form.value.area,
			full_address: form.value.address,
		};


		// 实际提交
		uni.showLoading({ title: t('submitting') })

		const submitPromise = form.value.audit_status == -1 ? reapplyStore(form.value.id, submitData) : applyStore(submitData)

		submitPromise.then(() => {
			uni.hideLoading()
			uni.showToast({ title: t('submitSuccess'), icon: "none" })
			redirect({
				url: '/addon/home_service/user/pages/settle/submit_success',
				param: { status: 0, formType: 'store' }
			})
		}).catch(() => {
			uni.hideLoading()
		})


	};
</script>

<style lang="scss" scoped>
	// 基础输入框样式
	input {
		font-size: 14px;
		line-height: 1.5;
		box-sizing: border-box;
	}

	// 按钮样式优化
	button {
		font-size: 16px;
		letter-spacing: 0.5px;
	}

	// 输入框聚焦效果
	input:focus {
		box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.1);
	}

	// 图片预览样式
	.img-preview {
		width: 100%;
		aspect-ratio: 3/4;
		border-radius: 8rpx;
	}

	// 底部安全区域适配
	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
</style>