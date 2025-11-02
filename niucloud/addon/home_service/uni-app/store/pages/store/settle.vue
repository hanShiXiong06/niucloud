<template>
	<view class="bg-[#f5f5f5] min-h-screen" v-if="!loading">
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
				<upload-img v-model="form.license_img" :bgUrl="img('addon/home_service/user/settle/store_form/store_example1.png')" :max-count="1" :multiple="false" />
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
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('myLocation') }}</text>
			<view
				class="bg-gray-50 rounded-lg px-3 py-3 flex items-center justify-between border border-gray-200 hover:border-blue-200 transition-all cursor-pointer"
				@tap="chooseLocation">
				<text class="text-gray-400" v-if="!form.address">{{ t('selectLocation') }}</text>
				<text class="text-gray-700" v-else>{{ form.address }}</text>
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
			<input type="number" :placeholder="t('inputContactPersonMobile')" v-model="form.mobile"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 身份证号 -->
		<view class="bg-white px-4 py-2">
			<text class="text-base text-[30rpx] font-bold mb-[25rpx] block">{{ t('contactPersonIdcard') }}</text>
			<input type="text" :placeholder="t('inputContactPersonIdcard')" v-model="form.id_number"
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
				<upload-img v-model="form.id_card_front" :bgUrl="img('addon/home_service/user/settle/id_card1.png')" :max-count="1" :multiple="false" />
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
				<upload-img v-model="form.id_card_back" :bgUrl="img('addon/home_service/user/settle/id_card2.png')" :max-count="1" :multiple="false" />
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
		<view class="w-full bg-[#fff]">
			<view
				class="z-10 py-[var(--top-m)] px-[var(--sidebar-m)] w-full fixed bottom-0 left-0 right-0 box-border">
				<button hover-class="none"
					class="relative z-999 !text-[#fff] !bg-[var(--store-bg-one)] h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500"
					@click="submitForm" :disabled="btnDisabled" :loading="operateLoading"
					:class="{'opacity-50': btnDisabled}">{{ t('confirmSubmit') }}
				</button>
			</view>
		</view>
	</view>
	<view class="footer"></view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
	import { ref, onMounted } from 'vue'
	// 注意：loading-page组件需要在项目中存在，否则需要移除或替换为可用的加载组件
	const loading = ref<boolean>(false);
	const btnDisabled = ref<boolean>(false);
	const operateLoading = ref<boolean>(false);
	import { t } from '@/locale'
	import { img,redirect } from '@/utils/common'
	import manifestJson from '@/manifest.json'
	import { getAddressByLatlng } from '@/app/api/system'
	import { onLoad } from '@dcloudio/uni-app'
	import uploadImg from '@/addon/home_service/store/components/upload-img/upload-img.vue'
	import { topTabar } from '@/utils/topTabbar';
	const topTabarObj = topTabar()
	let topTabbarData = topTabarObj.setTopTabbarParam({ title: '机构入驻', topStatusBar: { textColor: '#333' }})
	// 导入新增的API函数
	import { getStoreApply, applyStore, reapplyStore } from '@/addon/home_service/store/api/store'

	// 表单数据
	const form = ref({
		store_name: '',
		license_img: '',
		headimg: '', // 修改为字符串类型
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

	// 获取URL参数
	const getQueryVariable = (variable : any) => {
		const query = window.location.search.substring(1);
		const vars = query.split('&');
		for (let i = 0; i < vars.length; i++) {
			const pair = vars[i].split('=');
			if (pair[0] == variable) {
				return pair[1];
			}
		}
		return false;
	}

	// 获取详细地址
	const getAddress = (latlng : any) => {
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
				form.value.address = res.data.full_address;
			} else {
				uni.showToast({ title: res.msg, icon: 'none' })
			}
		})
	}

	// 选择位置
	const chooseLocation = () => {
		// #ifndef H5
		// 非H5环境（小程序、App等）使用原生位置选择
		uni.chooseLocation({
			success: (res) => {
				res.latitude && (form.value.lat = res.latitude)
				res.longitude && (form.value.lng = res.longitude)
				res.address && (form.value.area = res.address)
				res.name && (form.value.address_name = res.address)
				res.name && (form.value.address = res.name)
				if (res.latitude && res.longitude) {
					let latng = res.latitude + ',' + res.longitude;
					getAddress(latng);
				}
			},
			fail: (res) => {
				// 处理失败情况
				if (res.errMsg && res.errno) {
					if (res.errno == 104) {
						uni.showToast({ title: '用户未授权隐私权限，选择位置失败', icon: 'none' })
					} else if (res.errno == 112) {
						uni.showToast({ title: '隐私协议中未声明，打开地图选择位置失败', icon: 'none' })
					} else {
						uni.showToast({ title: res.errMsg || '选择位置失败', icon: 'none' })
					}
				}
			}
		})
		// #endif

		// #ifdef H5
		// H5环境使用腾讯地图API
		const urlencode = form.value;
		uni.setStorageSync('serviceArea', urlencode);
		let backurl = location.origin + location.pathname;
		window.location.href = 'https://apis.map.qq.com/tools/locpicker?search=1&type=0&backurl=' + encodeURIComponent(backurl) + '&key=' + manifestJson.h5.sdkConfigs.maps.qqmap.key + '&referer=myapp';
		// #endif
	}

	// 表单提交
	const submitForm = () => {
		// 表单验证
		if (!form.value.store_name) {
			uni.showToast({ title: t('inputStoreName'), icon: 'none' })
			return
		}
		if (!form.value.license_img) {
			uni.showToast({ title: t('businessLicenseRequired'), icon: 'none' })
			return
		}
		if (!form.value.contact_name) {
			uni.showToast({ title: t('contactNameRequired'), icon: 'none' })
			return
		}
		if (!form.value.mobile) {
			uni.showToast({ title: t('mobileRequired'), icon: 'none' })
			return
		}
		if (form.value.mobile.length != 11) {
			uni.showToast({ title: t('inputMobileErr'), icon: 'none' })
			return
		}
		if (!form.value.id_number) {
			uni.showToast({ title: t('idNumberRequired'), icon: 'none' })
			return
		}
		if (form.value.id_number.length != 18) {
			uni.showToast({ title: t('inputIdcardErr'), icon: 'none' })
			return
		}
		if (!form.value.id_card_front) {
			uni.showToast({ title: t('idcardFrontRequired'), icon: 'none' })
			return
		}
		if (!form.value.id_card_back) {
			uni.showToast({ title: t('idcardBackRequired'), icon: 'none' })
			return
		}
		// 添加省市区ID的验证
		if (!form.value.province_id) {
			uni.showToast({ title: t('locationRequired'), icon: 'none' })
			return
		}

		// 准备提交数据
		// 根据实际API请求参数调整submitData，只包含必要字段
		const submitData = {
			// 基本信息字段
			store_name: form.value.store_name || '',
			license_img: form.value.license_img || '',
			headimg: form.value.headimg || '', // 修改默认值为空字符串
			apply_desc: form.value.apply_desc || '',
			
			// 地址相关字段
			province_id: form.value.province_id || 0,
			city_id: form.value.city_id || 0,
			district_id: form.value.district_id || 0,
			full_address: form.value.address ,
			lat: form.value.lat || '',
			lng: form.value.lng || '',
			
			// 联系人信息字段
			contact_name: form.value.contact_name || '',
			mobile: form.value.mobile || '',
			id_number: form.value.id_number || '',
			id_card_front: form.value.id_card_front || '',
			id_card_back: form.value.id_card_back || '',
		
		}
		
		// 实际提交
		uni.showLoading({ title: t('submitting') })
		// 根据audit_status决定使用哪个接口
		const submitPromise = form.value.audit_status == -1 ? reapplyStore(form.value.id, submitData) : applyStore(submitData)
		
		submitPromise.then(() => {
			uni.hideLoading()
			uni.showToast({ title: t('submitSuccess'),icon:'none' })
			redirect({
				url: '/addon/home_service/store/pages/store/submit_success',
				param: { status: 0, formType: 'store' }
			})
		}).catch(() => {
			uni.hideLoading()
		})
	}

	// 获取门店申请数据
	const getStoreApplyFn = (fromReapply: boolean = false) => {
	    loading.value = true;
	
	    getStoreApply().then((res) => {
	        if (res.data) {
	            // 检查audit_status是否为1（审核通过）
	            if (res.data.audit_status === 1) {
	                // 审核通过时，不显示原有数据，重置表单为空状态
	                // 保留id和audit_status以便系统识别
	                const keepFields = {
	                    id: res.data.id,
	                    audit_status: res.data.audit_status
	                };
	                // 重置表单
	                Object.assign(form.value, {
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
	                    province_id: 0,
	                    city_id: 0,
	                    district_id: 0,
	                    lat: '',
	                    lng: '',
	                    full_address: '',
	                    address_name: '',
	                    area: ''
	                }, keepFields);
	            } else {
	                // 非审核通过状态，正常赋值数据
	                Object.assign(form.value, res.data);
	                
	                // 确保 id 字段被正确设置
	                if (res.data.id) {
	                    form.value.id = res.data.id;
	                }
	                
	                // 处理 headimg 字段
	                if(res.data.headimg) {
	                    form.value.headimg = res.data.headimg; // 直接使用字符串，不再转换为数组
	                }
	                
	                // 确保 audit_status 字段存在
	                form.value.audit_status = res.data.audit_status || 0;
	                
	                // 修复位置数据显示问题：如果 address 为空，尝试使用其他位置相关字段
	                if (!form.value.address) {
	                    if (form.value.address_name) {
	                        form.value.address = form.value.address_name;
	                    } else if (form.value.full_address) {
	                        form.value.address = form.value.full_address;
	                    } else if (form.value.area) {
	                        form.value.address = form.value.area;
	                    }
	                }
	                
	                // 如果有经纬度但没有详细地址信息，尝试重新获取地址
	                if (form.value.lat && form.value.lng && !form.value.full_address) {
	                    const latng = form.value.lat + ',' + form.value.lng;
	                    getAddress(latng);
	                }
	                
	                // 只有当有数据且不是重新申请时才重定向
	                if(res.data.store_name && res.data.audit_status!=1 && !fromReapply && !addressStatus.value ){
	                    redirect({
	                        url: '/addon/home_service/store/pages/store/submit_success',
	                        param: { status: res.data.audit_status, formType:'store' }
	                    })
						addressStatus.value =false
	                }
	            }
	        } else {
	            console.warn('getStoreApply returned no data');
	        }
	        loading.value = false
	    }).catch((error) => {
	        console.error('Error in getStoreApply:', error);
	        loading.value = false
	    })
	}
	const addressStatus = ref(false)

	// 页面加载时获取数据
	onLoad((data : any) => {
	// 保存参数以便在getStoreApplyFn中使用
	const fromReapply = data.fromReapply === 'true' || data.fromReapply === true;
	getStoreApplyFn(fromReapply)
		if (uni.getStorageSync('serviceArea')) {
			Object.assign(form.value, uni.getStorageSync('serviceArea'))
			uni.removeStorageSync('serviceArea')
		}
		if (data.name) {
			form.value.address = data.name;
			if (data.latng) {
				addressStatus.value = true
				getAddress(data.latng);
				const tempArr = getQueryVariable('latng')?.split(',') || data.latng.split(',');
				form.value.lat = tempArr[0];
				form.value.lng = tempArr[1];
			}
		}
	})
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

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>