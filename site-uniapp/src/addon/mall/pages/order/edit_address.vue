<template>
    <view class="bg-[var(--page-bg-color)] min-h-screen overflow-hidden" :style="themeColor()">
        <u-form labelPosition="left" :model="formData" errorType='toast' :rules="rules" ref="formRef">
            <view class="sidebar-margin card-template mt-[var(--top-m)] py-[20rpx]">
                <view>
                    <u-form-item label="联系人" prop="taker_name" labelWidth="200rpx">
                        <u-input fontSize="28rpx" v-model.trim="formData.taker_name" border="none" clearable maxlength="25" placeholderStyle="color: #888" placeholder="请输入联系人" />
                    </u-form-item>
                </view>
                <view class="mt-[16rpx]">
                    <u-form-item label="联系方式" prop="taker_mobile" labelWidth="200rpx">
                        <u-input fontSize="28rpx" v-model.trim="formData.taker_mobile" maxlength="11" border="none" clearable placeholder="请输入联系方式" placeholderStyle="color: #888" />
                    </u-form-item>
                </view>
                <view class="mt-[16rpx]">
                    <u-form-item label="联系地址" prop="area" labelWidth="200rpx">
                        <view class="flex w-full items-center h-[52rpx]" v-if="formData.delivery_type == 'express'" @click="selectArea">
                            <view v-if="!formData.area" class="text-[#888] text-[28rpx] flex-1">请选择地址</view>
                            <view v-else class="text-[28rpx] flex-1 leading-[1.4]">{{ formData.area }}</view>
                            <view @click.stop="chooseLocation" class="flex items-center">
                                <text class="nc-iconfont nc-icon-dizhiguanliV6xx mr-[4rpx] text-[32rpx] text-[var(--primary-color)]"></text>
                                <text class="text-[24rpx] whitespace-nowrap text-[var(--primary-color)]">定位</text>
                            </view>
                        </view>
                        <view v-else class="flex justify-between items-center flex-1 h-[52rpx]" @click="chooseLocation">
                            <view class="text-[28rpx] text-[#303133] leading-[1.4]" v-if="formData.area || formData.address_name">{{ formData.area || formData.address_name }}</view>
                            <view class="text-[#888] text-[28rpx]" v-else>请选择地址</view>
                            <view class="flex items-center">
                                <text class="nc-iconfont nc-icon-dizhiguanliV6xx text-[32rpx] mr-[4rpx] text-[var(--primary-color)]"></text>
                                <text class="text-[24rpx] whitespace-nowrap text-[var(--primary-color)]">定位</text>
                            </view>
                        </view>
                    </u-form-item>
                </view>
                <view class="mt-[16rpx]">
                    <u-form-item label="详细地址" prop="taker_address" labelWidth="200rpx">
                        <u-input fontSize="28rpx" v-model.trim="formData.taker_address" border="none" clearable maxlength="120" placeholder="请输入详细地址" placeholderStyle="color: #888" />
                    </u-form-item>
                </view>
                <view class="mt-[16rpx]" v-if="formData.delivery_type == 'store' || formData.delivery_type == 'local_delivery'">
                    <u-form-item :label="formData.delivery_type == 'local_delivery' ? '配送门店' : '提货门店'" prop="take_store_id" labelWidth="180rpx">
                        <view class="flex items-center flex-1" @click="showTakeStoreIdPopup = true">
                            <view class="flex-1" :class="{'text-[var(--text-color-light9)]': !formData.take_store_id }" >{{ formData.take_store_name ? formData.take_store_name : '请选择门店' }}</view>
                            <text class="nc-iconfont nc-icon-youV6xx text-[30rpx] text-[var(--text-color-light9)]"></text>
                        </view>
                    </u-form-item>
                </view>
            </view>
        </u-form>
        <view class="w-full footer">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
                <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx]  text-[26rpx] font-500"  @click="save" :disabled="btnDisabled" :loading="operateLoading" :class="{'opacity-50': btnDisabled}">确定</button>
            </view>
        </view>
        <area-select ref="areaRef" @complete="areaSelectComplete" :area-id="formData.taker_district || formData.taker_city" />
        <!-- 配送门店 -->
        <u-popup :show="showTakeStoreIdPopup" @close="showTakeStoreIdPopup = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">{{ formData.delivery_type == 'local_delivery' ? '配送门店' : '提货门店' }}</view>
                <scroll-view scroll-y="true" class="h-[400rpx] px-[30rpx] box-border">
                    <u-radio-group v-model="formData.take_store_id" placement="column" iconPlacement="right">
                       <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333"  :customStyle="{marginBottom: '30rpx'}" v-for="(item, index) in deliveryList" :key="index" :label="item.store_name" :name="item.store_id"></u-radio>
                    </u-radio-group>
                </scroll-view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="handleTakeStoreId">确定</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, nextTick } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import manifestJson from '@/manifest.json';
import { redirect } from '@/utils/common'
import { getDeliveryStoreListAll } from '@/app/api/delivery_site'
import { getOrderEditAddress, orderEditAddress } from '@/addon/mall/api/order'
import { getAddressByLatlng } from '@/app/api/system';

const formData = reactive<any>({
    order_id: 0,
    delivery_type: '',
    taker_name: '',
    taker_mobile: '',
    taker_latitude: '',
    taker_longitude: '',
    taker_full_address: '',
    taker_address: '',
    taker_province: 0,
    taker_city: 0,
    taker_district: 0,
    area: '',
    address_name: '',
    take_store_id: '',
    take_store_name: ''
})
const orderId = ref('')
const isSelectAddress = ref(false)
const areaRef = ref()
const formRef: any = ref(null)
const isSelectMap = ref(2) // 值为1，该地址需要有经纬度，反之不需要
const deliveryList = ref([])

const wxPrivacyPopupRef: any = ref(null)

onLoad((data: any) => {
    orderId.value = data.order_id || 0;
    if (data.order_id && !data.name) {
        getOrderEditAddressFn({ order_id: orderId.value });
    } else if (data.name) {
        if (uni.getStorageSync('addressInfo')) {
            Object.assign(formData, uni.getStorageSync('addressInfo'))
        }
        getAddress(data.latng);
        const tempArr = getQueryVariable('latng').split(',');
        formData.taker_latitude = tempArr[0];
        formData.taker_longitude = tempArr[1];
    }
    // #ifdef MP
    nextTick(() => {
        if (wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive();
    })
    // #endif
})

const  getOrderEditAddressFn = (params: any) => {
    getOrderEditAddress(params).then((res: any) => {
		Object.keys(formData).forEach((key: string) => {
            if (res.data[key] != undefined) formData[key] = res.data[key]
        })
        getDeliveryStoreListAllFn({ pick_up_type: formData.delivery_type });
        isSelectMap.value = formData.delivery_type == 'express' ? 2 : 1;
	}).catch(() => {

	})
}

// 门店自提列表
const showTakeStoreIdPopup = ref(false)
const getDeliveryStoreListAllFn = (data: any) => {
    getDeliveryStoreListAll(data).then((res: any) => {
        deliveryList.value = res.data
        formData.take_store_name = deliveryList.value.find((item: any) => item.store_id == formData.take_store_id).store_name
    })
}
const handleTakeStoreId = () => {
		if (!formData.take_store_id) {
        uni.showToast({
            title: '请选择门店',
            icon: 'none'
        })
        return
    }
    const store = deliveryList.value.find((item: any) => item.store_id == formData.take_store_id)
    formData.take_store_name = store.store_id
    showTakeStoreIdPopup.value = false
}
const rules = computed(() => {
    return {
        'taker_name': {
            type: 'string',
            required: true,
            message: '请输入联系人',
            trigger: ['blur', 'change'],
        },
        'taker_mobile': [
            {
                type: 'string',
                required: true,
                message: '请输入联系方式',
                trigger: ['blur', 'change'],
            },
            {
                validator(rule: any, value: any, callback: any) {
                    let mobile = /^1[3-9]\d{9}$/;
                    if (!mobile.test(value)) {
                        callback(new Error('请输入正确的手机号'))
                    } else {
                        callback()
                    }
                }
            }
        ],
        'area': {
            validator() {
                let bool = true;
                if (uni.$u.test.isEmpty(formData.area) && uni.$u.test.isEmpty(formData.address_name)) {
                    bool = false;
                }
                return bool
            },
            message: '请选择地址',
            trigger: ['blur', 'change']
        },
        'taker_address': {
            type: 'string',
            required: true,
            message: '请输入详细地址',
            trigger: ['blur', 'change']
        }
    }
})

const operateLoading = ref(false)
const btnDisabled = ref(false)
const save = () => {
    formRef.value.validate().then(() => {
        if (operateLoading.value) return
        operateLoading.value = true

        btnDisabled.value = true

        formData.taker_full_address = formData.area + formData.taker_address

        if (isSelectMap.value == 1 && !formData.taker_latitude && !formData.taker_longitude) {
            uni.showToast({
                title: '缺少经纬度，请在地图上重新选点',
                icon: 'none'
            });
            operateLoading.value = false;
            btnDisabled.value = false
            return false;
        }
        orderEditAddress(formData).then((res: any) => {
            operateLoading.value = false
            setTimeout(() => {
                btnDisabled.value = false
                uni.removeStorageSync('addressInfo');
                if (getCurrentPages().length > 1) {
                    uni.navigateBack({
                        delta: 1
                    });
                } else {
                    redirect({url: '/addon/mall/pages/order/list'});
                }
            }, 1000)
        }).catch(() => {
            operateLoading.value = false
            btnDisabled.value = false
        })
    })
}
const selectArea = () => {
    isSelectAddress.value = true
    areaRef.value.open()
}

const areaSelectComplete = (event: any) => {
    if (isSelectAddress.value && (formData.taker_province == event.province?.id || formData.taker_city != event.city?.id || formData.taker_district != event.district?.id)) {
        formData.taker_latitude = '';
        formData.taker_longitude = '';
    }
    formData.taker_province = event.province?.id || 0
    formData.taker_city = event.city?.id || 0
    formData.taker_district = event.district?.id || 0
    formData.area = `${ event.province?.name || '' }${ event.city?.name || '' }${ event.district?.name || '' }`
    isSelectAddress.value = false;
}
// 选择地址
const chooseLocation = () => {
    // #ifdef MP
    uni.chooseLocation({
        success: (res) => {
            res.latitude && (formData.taker_latitude = res.latitude)
            res.longitude && (formData.taker_longitude = res.longitude)
            res.address && (formData.area = res.address)
            res.name && (formData.address_name = res.address)
            res.name && (formData.taker_full_address = res.name)
            if (res.latitude && res.longitude) {
                let latng = res.latitude + ',' + res.longitude;
                getAddress(latng);
            }
        },
        fail: (res) => {
            // 在隐私协议中没有声明chooseLocation:fail api作用域
            if (res.errMsg && res.errno) {
                if (res.errno == 104) {
                    let msg = '用户未授权隐私权限，选择位置失败';
                    uni.showToast({ title: msg, icon: 'none' })
                } else if (res.errno == 112) {
                    let msg = '隐私协议中未声明，打开地图选择位置失败';
                    uni.showToast({ title: msg, icon: 'none' })
                } else {
                    uni.showToast({ title: res.errMsg, icon: 'none' })
                }
            }
        }
    });
    // #endif

    // #ifdef H5
    const urlencode = formData;
    uni.setStorageSync('addressInfo', urlencode);
    let backurl = location.origin + location.pathname + '?order_id=' + formData.order_id;
    if (isSelectMap.value) {
        backurl = backurl + '&isSelectMap=' + isSelectMap.value
    }
    window.location.href = 'https://apis.map.qq.com/tools/locpicker?search=1&type=0&backurl=' + encodeURIComponent(backurl) + '&key=' + manifestJson.h5.sdkConfigs.maps.qqmap.key + '&referer=myapp';
    // #endif
}

//获取详细地址
const getAddress = (latlng: any) => {
    getAddressByLatlng({ latlng }).then((res: any) => {
        if (res.data) {
            formData.taker_full_address = '';
            formData.taker_full_address += res.data.province != undefined ? res.data.province : '';
            formData.taker_full_address += res.data.city != undefined ? '' + res.data.city : '';
            formData.taker_full_address += res.data.district != undefined ? '' + res.data.district : '';

            formData.address_name = formData.taker_full_address.replace(/-/g, '');
            formData.area = (res.data.province + res.data.city + res.data.district) || res.data.full_address;

            formData.taker_province = res.data.province_id != undefined ? res.data.province_id : 0;
            formData.taker_city = res.data.city_id != undefined ? res.data.city_id : 0;
            formData.taker_district = res.data.district_id != undefined ? res.data.district_id : 0;
            formData.taker_address = res.data.community!= undefined ? res.data.community : '';
        } else {
            uni.showToast({ title: res.msg, icon: 'none' })
        }
    })

}

const getQueryVariable = (variable: any) => {
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
</script>

<style lang="scss" scoped>
.footer {
    height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>