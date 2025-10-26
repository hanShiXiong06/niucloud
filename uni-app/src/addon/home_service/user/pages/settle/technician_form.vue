<template>
	<view class="bg-[#f5f5f5] min-h-screen" v-if="!loading" :style="themeColor()">
		<!-- 姓名 -->
		<view class="bg-white px-4 py-2 mt-1">
			<text class="text-[28rpx] text-[30rpx] font-bold mb-[25rpx] block">{{ t('name') }}</text>
			<input type="text" :placeholder="t('inputName')" v-model="form.real_name"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 身份证号 -->
		<view class="bg-white px-4 py-2">
			<text class="text-[28rpx] text-[30rpx] font-bold mb-[25rpx] block">{{ t('idcard') }}</text>
			<input type="text" :placeholder="t('inputIdcard')" v-model="form.id_number"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]"  :maxlength="18"/>
		</view>

		<!-- 手机号码 -->
		<view class="bg-white px-4 py-2">
			<text class="text-[28rpx] text-[30rpx] font-bold mb-[25rpx] block">{{ t('mobiles') }}</text>
			<input type="number" :placeholder="t('inputMobile')" v-model="form.mobile" :maxlength="11"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 所属门店 -->
		<view class="bg-white px-4 py-2" @click="!storeLoading && (showStorePicker = true)">
			<text class="text-[28rpx] text-[30rpx] font-bold mb-[25rpx] block">{{ t('store') }}</text>
			<view class="relative">
				<view class="bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx] flex text-[28rpx]"
					:class="form.store_id ? '' : 'text-999'">
					<text v-if="storeLoading">加载中...</text>
					<text v-else>{{form.store_name ? form.store_name : t('selectStore')}}</text>
				</view>
			</view>
		</view>

		<!-- 师傅头像 -->
		<view class="bg-white px-4 py-2">
			<view class="flex items-center mb-3">
				<text class="text-[28rpx] text-[30rpx] font-bold block">{{ t('technicianAvatar') }}</text>
				<text class="text-[#999999] ml-2 text-sm">({{ t('avatarUploadTip') }})</text>
			</view>

			<view class="flex items-start justify-between mb-3 bg-[#F5F9FA] p-[20rpx] rounded-lg">
				<view class="pr-3">
					<text class="text-[28rpx] font-medium text-gray-800 block mb-2">{{ t('uploadAvatar') }}</text>
					<view class="text-[22rpx] text-[#999999] leading-[34rpx]">

						<view>1.{{ t('avatarUploadTip1') }}</view>
					</view>
				</view>
				<upload-img v-model="form.headimg" :max-count="1" :multiple="false" />
			</view>
		</view>

		<!-- 身份证照片 -->
		<view class="bg-white px-4 ">
			<view class="flex items-center mb-3">
				<text class="text-[28rpx] text-[30rpx] font-bold block">{{ t('idcardUpload') }}</text>
				<text class="text-[#999999] ml-2 text-sm">({{ t('idcardUploadTip') }})</text>
			</view>

			<!-- 证件头像面 -->
			<view class="flex items-start justify-between mb-3 bg-[#F5F9FA] p-[20rpx] pt-[30rpx] rounded-lg">
				<view class=" pr-3">
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
			<view class="flex items-start justify-between bg-[#F5F9FA] p-[20rpx] pt-[30rpx] rounded-lg">
				<view class=" pr-3">
					<text class="text-[28rpx] font-medium text-gray-800 block mb-2">证件国徽面</text>
					<view class="text-[22rpx] text-[#999999] leading-[34rpx]">
						<view>1.{{ t('idcardBackTip1') }}</view>
						<view>2.{{ t('idcardBackTip3') }}</view>
					</view>
				</view>
				<upload-img v-model="form.id_card_back " :bgUrl="img('addon/home_service/user/settle/id_card2.png')"
					:max-count="1" :multiple="false" />
			</view>
		</view>
		<!-- 技能证书 -->
		<view class="bg-white px-4 py-2 pt-4">
			<text class="text-[28rpx] text-[30rpx] font-bold mb-[25rpx] block">{{ t('skillCertificate') }}</text>
			<upload-img v-model="form.certificate" :max-count="5" :multiple="true" />
		</view>



		<!-- 我的定位 -->
		<view class="bg-white px-4 pt-3">
			<view class="flex items-center mb-3">
				<text class="text-[28rpx] text-[30rpx] font-bold block">{{ t('serviceArea') }}</text>
			</view>
			<view
				class="bg-[#F6F6F6] rounded-lg px-3 py-3 flex items-center justify-between border border-gray-200 hover:border-blue-200 transition-all cursor-pointer text-[28rpx]"
				@tap="chooseLocation">
				<text class="text-gray-400" v-if="!form.address">{{ t('selectServiceArea') }}</text>
				<text class="text-gray-700" v-else>{{ form.address }}</text>
				<u-icon name="arrow-right" class="text-gray-400" size="16"></u-icon>
			</view>
		</view>


		<!-- 申请说明 -->
		<view class="bg-white px-4 py-3">
			<view class="flex items-center mb-3">
				<text class="text-[28rpx] text-[30rpx] font-bold block">{{ t('applyDescription') }}</text>
			</view>
			<input type="text" :placeholder="t('inputDescription')" v-model="form.notes"
				class="w-full bg-[#F6F6F6] px-[20rpx] rounded-lg h-[80rpx] leading-[80rpx]" />
		</view>

		<!-- 提交按钮 -->
		<view class="w-full footer bg-[#fff]">
			<view
				class="py-[var(--top-m)]  bg-[#fff] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border z-10">
				<button hover-class="none"
					class="!bg-[var(--primary-color)] !text-[#fff] !rounded-lg h-[80rpx] leading-[80rpx] rounded-[10rpx] text-[26rpx] font-500 relative z-999"
					@click="submitForm" :disabled="btnDisabled" :loading="operateLoading"
					:class="{'opacity-50': btnDisabled}">{{ t('submitForm') }}
				</button>
			</view>
		</view>
		<view class="footer"></view>
		<!-- 门店选择器 -->
		<u-picker :show="showStorePicker" :columns="storeColumns" keyName="display_name"
			@confirm="onStoreConfirm" @cancel="onStoreCancel"></u-picker>
	</view>
	<loading-page :loading="loading"></loading-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { t } from '@/locale';
import { img, redirect } from '@/utils/common';
import manifestJson from '@/manifest.json';
import { getAddressByLatlng } from '@/app/api/system';
import { getSettleStoreList, applyTechnician, getTechnicianApply } from '@/addon/home_service/user/api/settle';
import { onLoad } from '@dcloudio/uni-app';
import uploadImg from '@/addon/home_service/user/components/upload-img/upload-img.vue';
import useSystemStore from '@/stores/system';

const systemStore = useSystemStore();

// 注意：这里移除了对u-loading组件的导入，因为系统中不存在此组件
const loading = ref<boolean>(true);
// 表单数据
const form = ref({
    store_id: '',
    real_name: '',
    mobile: '',
    id_number: '',
    id_card_front: '',
    id_card_back: '',
    certificate: [] as string[],
    address: '',
    latitude: '',
    longitude: '',
    description: '',
    // 添加缺失的字段定义，与address_edit.vue保持一致
    province_id: 0,
    city_id: 0,
    district_id: 0,
    lat: '',
    lng: '',
    full_address: '',
    address_name: '',
    // 添加师傅头像字段
    headimg: '',
    notes:'',
    store_name: '',
    store_name_with_distance: '', // 用于存储带距离的门店名称
    original_store_name: '', // 添加一个新字段用于存储原始门店名称
    category_id: '' // 添加分类ID字段并初始化
});

const storeColumns = ref([[]]);
// 门店加载状态
const storeLoading = ref(false);
// 选择器状态
const showStorePicker = ref(false);

const getTechnicianApplyFn = () => {
    loading.value = true;
    getTechnicianApply().then((res) => {
        Object.assign(form.value, res.data);
        if(form.value.certificate ){
            form.value.certificate = res.data.certificate?.split(',');
        }
        form.value.address = res.data.full_address;
        loading.value = false;
    });
};

getTechnicianApplyFn();

// 获取门店列表函数
const getSettleStoreListFn = () => {
    storeLoading.value = true;

    let params = {
        lng: systemStore.diyAddressInfo?.longitude,
        lat: systemStore.diyAddressInfo?.latitude,
    };

    getSettleStoreList(params).then((res) => {
        if (res.code === 1 && res.data) {
            // 处理门店数据，使用store_name_with_distance显示，但保留原始store_name
            const processedStores = res.data.map((item) => {
                // 如果存在store_name_with_distance，则使用它，否则使用store_name
                const displayName = item.store_name_with_distance || item.store_name || '未知门店';
                return {
                    ...item,
                    display_name: displayName, // 新增display_name字段用于显示
                    original_store_name: item.store_name // 保留原始门店名称
                };
            });
            
            // 更新表单中的门店名称（如果已选择门店）
            res.data.forEach((item) => {
                if(item.store_id == form.value.store_id){
                    form.value.store_name = item.store_name; // 选择后显示原始名称
                    form.value.store_name_with_distance = item.store_name_with_distance || item.store_name;
                }
            });
            
            storeColumns.value[0] = processedStores;
			console.log(storeColumns.value)
			let obj = {
				store_id:'',
				store_name_with_distance:'请选择',
				display_name:"请选择"
			}
			storeColumns.value[0].unshift(obj)
        } else {
            uni.showToast({ title: res.msg || '获取门店列表失败', icon: 'none' });
        }
    }).catch((error) => {
        uni.showToast({ title: '获取门店列表失败', icon: 'none' });
        console.error('获取门店列表错误:', error);
    }).finally(() => {
        storeLoading.value = false;
    });
};

// 页面加载时获取门店列表
getSettleStoreListFn();

// 合并后的onLoad函数（保留更完善的实现）
// 优化后的onLoad函数，直接从data参数获取
onLoad((data: any) => {
	console.log(data)
    if (uni.getStorageSync('serviceArea')) {
        const serviceArea = uni.getStorageSync('serviceArea');
        Object.assign(form.value, serviceArea);
        // 确保地址字段不为空
        if (serviceArea.name) {
            form.value.address = serviceArea.name;
        } else if (serviceArea.address) {
            form.value.address = serviceArea.address;
        }
        uni.removeStorageSync('serviceArea');
    }

    if (data.name) {
        form.value.address = data.name;
        if (data.latng) {
            getAddress(data.latng);
            // 直接从data参数中获取，移除getQueryVariable调用
            const tempArr = data.latng.split(',');
            form.value.lat = tempArr[0];
            form.value.lng = tempArr[1];
        }
    }
    
    // 确保category_id字段被正确赋值
    if (data.selectedSkills) {
        form.value.category_id = data.selectedSkills;
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


// 获取详细地址
const getAddress = (latlng: string) => {
    getAddressByLatlng({ latlng }).then((res) => {
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
            uni.showToast({ title: res.msg, icon: 'none' });
        }
    });
};

// 选择位置
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

// 新增：门店选择确认函数
const onStoreConfirm = (e) => {
    const selectedStore = e.value[0];
    if (selectedStore) {
        form.value.store_id = selectedStore.store_id;
        form.value.store_name = selectedStore.original_store_name || selectedStore.store_name;
        form.value.store_name_with_distance = selectedStore.store_name_with_distance || selectedStore.store_name;
    }
    showStorePicker.value = false;
};

// 新增：门店选择取消函数
const onStoreCancel = () => {
    showStorePicker.value = false;
};

// 表单提交
const submitForm = () => {
 
    if (!form.value.real_name) {
        uni.showToast({ title: t('inputName'), icon: 'none' });
        return;
    }
    if (!form.value.id_number) {
        uni.showToast({ title: t('inputIdcard'), icon: 'none' });
        return;
    }
    if (form.value.id_number.length != 18) {
        uni.showToast({ title: t('inputIdcardErr'), icon: 'none' });
        return;
    }
    if (!form.value.mobile) {
        uni.showToast({ title: t('inputMobile'), icon: 'none' });
        return;
    }
    if (form.value.mobile.length != 11) {
        uni.showToast({ title: t('inputMobileErr'), icon: 'none' });
        return;
    }
    // if (!form.value.store_id) {
    //     uni.showToast({ title: t('selectStore'), icon: 'none' });
    //     return;
    // }
	// 添加头像上传的验证
	if (!form.value.headimg) {
	    uni.showToast({ title: t('uploadAvatar'), icon: 'none' });
	    return;
	}
    if (!form.value.id_card_front) {
        uni.showToast({ title: t('uploadIdcardFront'), icon: 'none' });
        return;
    }
    if (!form.value.id_card_back) {
        uni.showToast({ title: t('uploadIdcardBack'), icon: 'none' });
        return;
    }
    if (form.value.certificate?.length==0) {
        uni.showToast({ title: t('uploadCertificate'), icon: 'none' });
        return;
    }
    // 添加省市区ID的验证，确保不为空
    if (!form.value.province_id) {
        uni.showToast({ title: '请选择服务区域', icon: 'none' });
        return;
    }

    // 准备提交数据 - 根据curl命令格式
    const submitData = {
        real_name: form.value.real_name,
        id_card_front: form.value.id_card_front,
        id_card_back: form.value.id_card_back,
        id_number: form.value.id_number,
        mobile: form.value.mobile,
        certificate: form.value?.certificate?.join(','), // 将数组转换为逗号分隔的字符串
        notes: form.value.notes,
        // 确保省市区ID不为空，使用0作为默认值而不是空字符串
        province_id: form.value.province_id || 0,
        city_id: form.value.city_id || 0,
        district_id: form.value.district_id || 0,
        full_address: form.value.address,
        lng: form.value.lng || '',
        lat: form.value.lat || '',
        store_id: form.value.store_id || '', // 允许为空字符串
        // 注意：以下字段需要根据实际业务需求进行补充
        category_id:  form.value.category_id, // 分类ID信息
        headimg: form.value.headimg // 使用用户上传的头像，而不是空字符串
    };

    // 实际提交
    uni.showLoading({ title: t('submitting') });
    applyTechnician(submitData).then(() => {
        uni.hideLoading();
        uni.showToast({ title: '操作成功',icon:'none' });
        redirect({ url: '/addon/home_service/user/pages/settle/submit_success', param: { status:0, formType: 'technician' } });
    }).catch(() => {
        uni.hideLoading();
    });
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

	// 门店选择器样式
	.store-selector {
		cursor: pointer;
	}

	// 图片预览样式
	.img-preview {
		width: 100%;
		aspect-ratio: 3/4;
		border-radius: 8rpx;
	}

	.footer {
		height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
		height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
	}
    .text-999{
        color: #999999;
    }
</style>