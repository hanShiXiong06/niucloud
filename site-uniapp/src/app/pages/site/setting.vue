<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()" v-if="!loading">
        <view class="card-template !rounded-none">
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">店铺名称</view>
                <view class="text-[28rpx]">{{ formData.site_name }}</view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[20rpx]">联系电话</view>
                <view class="text-[28rpx] flex-1">
                    <input  v-model.trim="formData.phone"  maxlength="25" placeholderStyle="color: #888" placeholder="请输入联系电话" class="h-full text-[28rpx] text-right" />
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[20rpx]">营业时间</view>
                <view class="text-[28rpx] flex-1">
                    <input v-model.trim="formData.business_hours"   placeholderStyle="color: #888" placeholder="请输入营业时间" class="h-full text-[28rpx] text-right" />
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx]">营业状态</view>
                <view class="text-[28rpx]">
                    <up-switch v-model="formData.business_status" :activeValue="1" :inactiveValue="0" size="20"></up-switch>
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[20rpx]">网站关键字</view>
                <view class="text-[28rpx] flex-1 ">
                    <input v-model.trim="formData.keywords"  maxlength="20" placeholderStyle="color: #888" placeholder="请输入网站关键字" class="h-full text-[28rpx] text-right" />
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[20rpx]">网站简介</view>
                <view class="text-[28rpx] flex-1">
                    <textarea v-model.trim="formData.desc"  class="leading-[1.5] h-[40rpx] w-[100%] text-[28rpx] text-right" :maxlength="100" cols="30" rows="2" placeholder="请输入网站简介" placeholder-class="text-[28rpx] !text-[var(--text-color-light9)]"></textarea>
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[20rpx]">店铺地址</view>
                <view class="text-[28rpx] flex-1 text-right" @click="chooseLocation">
                    <view class="text-[28rpx] text-[#303133] leading-[1.4]" v-if="formData.area">{{ formData.area  }}</view>
                    <view class="text-[#888] text-[28rpx]" v-else>请选择地址</view>
                </view>
            </view>
            <view class="justify-between item-wrap">
                <view class="text-[28rpx] mr-[40rpx]">详细地址</view>
                <view class="text-[28rpx] flex-1">
                    <textarea v-model.trim="formData.address"  class="leading-[1.5] h-[40rpx] w-[100%] text-[28rpx] text-right" :maxlength="100" cols="30" rows="2" placeholder="请输入详细地址" placeholder-class="text-[28rpx] !text-[var(--text-color-light9)]"></textarea>
                </view>
            </view>
            <view class="justify-between item-wrap !h-[160rpx]">
                <view class="text-[28rpx] mr-[20rpx]">正方形Logo</view>
                <view class="text-[28rpx] flex-1 flex justify-end">
                    <upload-img  v-model="formData.front_end_logo" :max-count="1"  />
                </view>
            </view>
            <view class="justify-between item-wrap !h-[160rpx]">
                <view class="text-[28rpx] mr-[20rpx]">背景图</view>
                <view class="text-[28rpx] flex-1 flex justify-end">
                    <upload-img  v-model="formData.logo" :max-count="1"  />
                </view>
            </view>
        </view>
        <view class="w-full footer">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
                <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500"  @click="save">确定</button>
            </view>
        </view>
        <area-select ref="areaRef" @complete="areaSelectComplete" :area-id="formData.district_id || formData.city_id" />
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getShopBaseInfo, setShopBaseInfo } from '@/app/api/site'
import manifestJson from '@/manifest.json';
import { getAddressByLatlng } from '@/app/api/system';

const formData = reactive<Record<string, any>>({
    site_name: '',
    phone: '',
    business_hours: '',
    business_status: 1,
    logo: '',
    icon: '',
    keywords: '',
    desc: '',
    front_end_name: '',
    front_end_logo: '',
    province_id: 0,
    city_id: 0,
    district_id: 0,
    address: '',
    full_address: '',
    area: '',
    latitude: 39.908626,
    longitude: 116.397190
})

onLoad((data: any) => {
    if (data.name) {
        if (uni.getStorageSync('siteAddressInfo')) {
            Object.assign(formData, uni.getStorageSync('siteAddressInfo'))
        }
        getAddress(data.latng);
        const tempArr = getQueryVariable('latng').split(',');
        formData.latitude = tempArr[0];
        formData.longitude = tempArr[1];
        loading.value = false
    } else {
        getShopBaseInfoFn()
    }
    

})
const loading = ref(true)
const  getShopBaseInfoFn = () => {
    loading.value = true
    getShopBaseInfo().then((res: any) => {
        Object.keys(formData).forEach((key: string) => {
            if (res.data[key] != undefined) formData[key] = res.data[key]
        })
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}

const areaSelectComplete = (event: any) => {
    formData.province_id = event.province?.id || 0
    formData.city_id = event.city?.id || 0
    formData.district_id = event.district?.id || 0
    formData.area = `${ event.province?.name || '' }${ event.city?.name || '' }${ event.district?.name || '' }`
}

// 选择地址
const chooseLocation = () => {
    // #ifdef MP
    uni.chooseLocation({
        success: (res) => {
            res.latitude && (formData.latitude = res.latitude)
            res.longitude && (formData.longitude = res.longitude)
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
    uni.setStorageSync('siteAddressInfo', urlencode);
    let backurl = location.origin + location.pathname;
    window.location.href = 'https://apis.map.qq.com/tools/locpicker?search=1&type=0&backurl=' + encodeURIComponent(backurl) + '&key=' + manifestJson.h5.sdkConfigs.maps.qqmap.key + '&referer=myapp';
    // #endif
}

//获取详细地址
const getAddress = (latlng: any) => {
    getAddressByLatlng({ latlng }).then((res: any) => {
        if (res.data) {
            formData.full_address = '';
            formData.full_address += res.data.province != undefined ? res.data.province : '';
            formData.full_address += res.data.city != undefined ? '' + res.data.city : '';
            formData.full_address += res.data.district != undefined ? '' + res.data.district : '';

            formData.area = (res.data.province + res.data.city + res.data.district) || res.data.full_address;

            formData.province_id = res.data.province_id != undefined ? res.data.province_id : 0;
            formData.city_id = res.data.city_id != undefined ? res.data.city_id : 0;
            formData.district_id = res.data.district_id != undefined ? res.data.district_id : 0;
            formData.address = res.data.community!= undefined ? res.data.community : '';
        } else {
            uni.showToast({ title: res.msg, icon: 'none' })
        }
    })

}


const repeatLoading = ref(false)
const save = () => {
    if (!formData.site_name) {
        uni.showToast({
            title: '请输入店铺名称',
            icon: 'none'
        })
        return
    }
    if (!formData.phone) {
        uni.showToast({
            title: '请输入联系电话',
            icon: 'none'
        })
        return
    }
    if(repeatLoading.value) return
    repeatLoading.value = true

    formData.full_address = formData.area + formData.address
    setShopBaseInfo(formData).then(() => {
        repeatLoading.value = false
        getShopBaseInfoFn()
    }).catch(() => {
        repeatLoading.value = false
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
.item-wrap{
    display: flex;
    align-items: center;
    min-height: 80rpx;
    box-sizing: border-box;
    border-bottom:  solid 1px #f5f5f5;
}
.footer {
    height: calc(100rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(100rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>