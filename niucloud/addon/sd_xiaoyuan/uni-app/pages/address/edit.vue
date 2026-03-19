<template>
    <view class="address-edit">
        <view class="form-section">
            <view class="form-item">
                <text class="label required">联系人</text>
                <input v-model="formData.name" placeholder="请输入联系人姓名" />
            </view>
            <view class="form-item">
                <text class="label required">手机号</text>
                <input v-model="formData.mobile" type="number" placeholder="请输入手机号" maxlength="11" />
            </view>
            <view class="form-item">
                <text class="label required">详细地址</text>
                <view class="location-input" @click="chooseLocation">
                    <text class="address" v-if="formData.address">{{ formData.address }}</text>
                    <text class="placeholder" v-else>点击选择地址</text>
                    <u-icon name="map-fill" size="20" color="#00c853"></u-icon>
                </view>
                <view class="coord-info" v-if="formData.lng && formData.lat">
                    <u-icon name="map" size="14" color="#999"></u-icon>
                    <text>经度: {{ formData.lng }} 纬度: {{ formData.lat }}</text>
                </view>
            </view>
            <view class="form-item">
                <text class="label">地址类型</text>
                <view class="type-list">
                    <view 
                        class="type-item" 
                        :class="{ active: formData.address_type === type.key }"
                        v-for="type in addressTypes"
                        :key="type.key"
                        @click="formData.address_type = type.key"
                    >{{ type.name }}</view>
                </view>
            </view>
            <view class="form-item">
                <text class="label">楼栋</text>
                <input v-model="formData.building" placeholder="如：1号楼" />
            </view>
            <view class="form-item">
                <text class="label">房间号</text>
                <input v-model="formData.room" placeholder="如：101室" />
            </view>
        </view>

        <view class="default-section" @click="formData.is_default = formData.is_default ? 0 : 1">
            <text>设为默认地址</text>
            <switch :checked="formData.is_default === 1" color="#c0fe95" />
        </view>

        <view class="submit-section">
            <view class="submit-btn" @click="submitAddress">保存地址</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getAddressDetail, addAddress, editAddress } from '../../api/xiaoyuan'

const isEdit = ref(false)
const addressId = ref(0)

const formData = ref({
    name: '',
    mobile: '',
    address: '',
    address_type: 'OTHER',
    building: '',
    room: '',
    lng: '',
    lat: '',
    is_default: 0
})

const addressTypes = [
    { key: 'DORM', name: '宿舍' },
    { key: 'TEACHING', name: '教学楼' },
    { key: 'EXPRESS', name: '快递点' },
    { key: 'OTHER', name: '其他' }
]

onLoad((options: any) => {
    if (options?.id) {
        isEdit.value = true
        addressId.value = parseInt(options.id)
        uni.setNavigationBarTitle({ title: '编辑地址' })
        loadAddressDetail()
    } else {
        uni.setNavigationBarTitle({ title: '添加地址' })
    }
})

onShow(() => {
    if (isEdit.value && addressId.value) {
        loadAddressDetail()
    }
})

const loadAddressDetail = async () => {
    try {
        const res: any = await getAddressDetail(addressId.value)
        if (res.code === 1 && res.data) {
            formData.value = {
                name: res.data.name,
                mobile: res.data.mobile,
                address: res.data.address,
                address_type: res.data.address_type,
                building: res.data.building || '',
                room: res.data.room || '',
                lng: res.data.lng || '',
                lat: res.data.lat || '',
                is_default: res.data.is_default
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const chooseLocation = () => {
    uni.chooseLocation({
        success: (res) => {
            formData.value.address = res.name || res.address
            formData.value.lng = res.longitude.toString()
            formData.value.lat = res.latitude.toString()
        }
    })
}

const submitAddress = async () => {
    if (!formData.value.name) {
        uni.showToast({ title: '请输入联系人', icon: 'none' })
        return
    }
    if (!formData.value.mobile || formData.value.mobile.length !== 11) {
        uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
        return
    }
    if (!formData.value.address) {
        uni.showToast({ title: '请选择地址', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '保存中...' })
        
        let res: any
        if (isEdit.value) {
            res = await editAddress({ id: addressId.value, ...formData.value })
        } else {
            res = await addAddress(formData.value)
        }
        
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '保存成功', icon: 'success' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
        } else {
            uni.showToast({ title: res.msg || '保存失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.address-edit {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.form-item {
    margin-bottom: 20rpx;
    
    &:last-child {
        margin-bottom: 0;
    }
    
    .label {
        display: block;
        font-size: 28rpx;
        color: #333;
        margin-bottom: 16rpx;
        
        &.required::before {
            content: '*';
            color: #ff4d4f;
            margin-right: 8rpx;
        }
    }
    
    input {
        width: 100%;
        height: 72rpx;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 0 24rpx;
        font-size: 28rpx;
        box-sizing: border-box;
    }
}

.location-input {
    display: flex;
    align-items: center;
    height: 72rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 24rpx;
    
    .address {
        flex: 1;
        font-size: 28rpx;
        color: #333;
    }
    
    .placeholder {
        flex: 1;
        font-size: 28rpx;
        color: #999;
    }
    
}

.coord-info {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-top: 12rpx;
    padding: 0 8rpx;

    text {
        font-size: 22rpx;
        color: #999;
    }
}

.type-list {
    display: flex;
    gap: 20rpx;
}

.type-item {
    padding: 16rpx 30rpx;
    background: #f8f8f8;
    border-radius: 30rpx;
    font-size: 26rpx;
    color: #666;
    border: 2rpx solid transparent;
    
    &.active {
        background: #c0fe95;
        color: #333;
        border-color: #c0fe95;
        font-weight: bold;
    }
}

.default-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-top: 20rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    text {
        font-size: 28rpx;
        color: #333;
    }
}

.submit-section {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
}

.submit-btn {
    width: 100%;
    height: 80rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000;
    font-size: 30rpx;
    font-weight: bold;
    border: none;
    border-radius: 40rpx;
    padding: 0;
    margin: 0;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}

</style>
