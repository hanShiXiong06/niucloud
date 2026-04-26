<template>
    <view class="address bg-[var(--page-bg-color)] min-h-[100vh]" v-if="!loading" :style="themeColor()">
        <scroll-view scroll-y="true">
            <view class="sidebar-margin pt-[var(--top-m)]" v-if="addressList.length">
                <view class="mb-[var(--top-m)] rounded-[var(--rounded-big)] overflow-hidden" v-for="(item, index) in addressList">
                    <view class="flex flex-col card-template">
                        <view class="flex-1 line-feed mr-[20rpx]" @click.stop="selectAddress(item)">
                            <view class="flex items-center">
                                <view class="text-[#333] text-[30rpx] leading-[34rpx] font-500">{{ item.name }}</view>
                                <text class="text-[#333] text-[30rpx] ml-[10rpx]">{{ mobileHide(item.mobile) }}</text>
                            </view>
                            <view class="mt-[16rpx] text-[26rpx] line-feed text-[var(--text-color-light9)] leading-[1.4]">{{ item.full_address }}</view>
                        </view>
                        <view class="flex justify-between pt-[26rpx]">
                            <view class="flex items-center text-[26rpx] leading-none" @click.stop="setDefault(index)">
                                <text class="iconfont !text-[26rpx] mr-[10rpx]" :class="{ 'iconduigou text-primary': item.is_default, 'iconcheckbox_nol': !item.is_default }"></text>
                                设为默认
                            </view>
                            <view class="flex">
                                <view class="text-[26rpx]" @click.stop="editAddressFn(item.id)">
                                    <text class="nc-iconfont nc-icon-xiugaiV6xx shrink-0 text-[26rpx] mr-[4rpx]"></text>
                                    编辑
                                </view>
                                <view @click.stop="deleteAddressFn(index)" class="ml-[40rpx] text-[26rpx]">
                                    <text class="nc-iconfont nc-icon-shanchu-yuangaizhiV6xx shrink-0 text-[26rpx] mr-[4rpx]"></text>
                                    删除
                                </view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
            <mescroll-empty v-if="!addressList.length" :option="{tip : '暂无上门地址'}"></mescroll-empty>
            <view class="w-full footer">
                <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border">
                    <button hover-class="none" class="bg-[#4CAF50] text-[#fff] h-[80rpx] leading-[80rpx] rounded-[100rpx] text-[26rpx] font-500" @click="addAddress">{{ t('addHomeAddress') }}</button>
                </view>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/locale'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { redirect, img, mobileHide } from '@/utils/common'
import { getAddressList, deleteAddress, editAddress } from '@/app/api/member'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';

const loading = ref(true)
const addressList = ref<object[]>([])
const getAddressListFn = () => {
    getAddressList({}).then(({ data }) => {
        addressList.value = data
        loading.value = false
    })
    .catch(() => {
        loading.value = false
    })

}
onShow(() => {
    getAddressListFn()
})

const addAddress = () => {
    redirect({ url: '/addon/wj_books/pages/address/address_edit' })
}

const editAddressFn = (id: number) => {
    redirect({ url: '/addon/wj_books/pages/address/address_edit', param: { id } })
}

const selectAddress = (data: any) => {
    // 存储选中的地址数据，方便其他页面使用
    uni.setStorage({
        key: 'selected_address',
        data: JSON.stringify({
            id: data.id,
            name: data.name,
            phone: data.mobile,
            province: data.province || '',
            city: data.city || '',
            district: data.district || '',
            address: data.address || data.full_address || ''
        })
    })
    
    // #ifdef H5
    // H5环境下，额外保存一份到localStorage，避免编译后键名变化问题
    try {
        localStorage.setItem('h5_selected_address', JSON.stringify({
            id: data.id,
            name: data.name,
            phone: data.mobile,
            province: data.province || '',
            city: data.city || '',
            district: data.district || '',
            address: data.address || data.full_address || ''
        }));
    } catch (e) {
        console.error('保存地址到localStorage失败:', e);
    }
    // #endif
    
    // 获取回调页面路径
    let selectAddressCallback;
    
    // #ifdef H5
    // 在H5环境下，先尝试从localStorage获取
    try {
        const h5Callback = localStorage.getItem('h5_selectAddressCallback');
        if (h5Callback) {
            selectAddressCallback = JSON.parse(h5Callback);
            console.log('从localStorage获取回调信息:', selectAddressCallback);
        }
    } catch (e) {
        console.error('从localStorage获取回调信息失败:', e);
    }
    // #endif
    
    // 如果localStorage中没有，再尝试从uni存储获取
    if (!selectAddressCallback) {
        // 尝试获取可能带有前缀的缓存键
        try {
            // 先尝试不带前缀的键名
            selectAddressCallback = uni.getStorageSync('selectAddressCallback');
            
            // #ifdef H5
            // 如果没有找到，尝试遍历localStorage查找可能带有前缀的键
            if (!selectAddressCallback && window.localStorage) {
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.includes('selectAddressCallback')) {
                        try {
                            const value = localStorage.getItem(key);
                            if (value) {
                                selectAddressCallback = JSON.parse(value);
                                console.log('找到带前缀的回调缓存键:', key);
                                break;
                            }
                        } catch (e) {
                            console.error('解析localStorage数据失败:', e);
                        }
                    }
                }
            }
            // #endif
        } catch (e) {
            console.error('获取回调缓存时出错:', e);
        }
    }
    
    if (selectAddressCallback) {
        selectAddressCallback.address_id = data.id;
        
        // #ifdef H5
        // H5环境下，额外保存一份到localStorage
        try {
            localStorage.setItem('h5_selectAddressCallback', JSON.stringify(selectAddressCallback));
        } catch (e) {
            console.error('保存回调信息到localStorage失败:', e);
        }
        // #endif
        
        uni.setStorage({
            key: 'selectAddressCallback',
            data: selectAddressCallback,
            success() {
                // #ifdef H5
                // H5环境下，先延迟一下再跳转，确保缓存写入成功
                setTimeout(() => {
                    // 检查路径是否已经包含前缀，避免重复
                    let backUrl = selectAddressCallback.back;
                    if (backUrl.startsWith('/addon/wj_books/pages/address/')) {
                        // 修复重复路径问题
                        backUrl = backUrl.replace('/addon/wj_books/pages/address/', '/');
                    }
                    console.log('H5环境跳转路径:', backUrl);
                    redirect({ url: backUrl, mode: 'redirectTo' });
                }, 100);
                // #endif
                
                // #ifndef H5
                // 非H5环境下正常跳转
                redirect({ url: selectAddressCallback.back, mode: 'redirectTo' });
                // #endif
            }
        });
    }
}

const deleteAddressFn = (index: any) => {
    const data: any = addressList.value[index]

    deleteAddress(data.id).then(() => {
        addressList.value.splice(index, 1)
    }).catch()
}
const setDefault = (index: any) => {
    const data: any = addressList.value[index]
    if (data.is_default) return

    data.is_default = 1;
    editAddress(data).then(() => {
        addressList.value.forEach((item, itemIndex) => {
            item.is_default && (item.is_default = 0)
            itemIndex == index && (item.is_default = 1)
        })
    }).catch()
}
</script>

<style lang="scss" scoped>
.line-feed {
    word-wrap: break-word;
    word-break: break-all;
}

.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>
