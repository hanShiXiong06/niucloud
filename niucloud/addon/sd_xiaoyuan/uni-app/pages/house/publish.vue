<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="publish-page" v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="form-item">
                <text class="label">房源标题</text>
                <input class="input" v-model="formData.title" placeholder="请输入房源标题" />
            </view>
            
            <view class="form-item">
                <text class="label">房源类型</text>
                <picker :range="houseTypes" range-key="name" @change="onTypeChange">
                    <view class="picker-value">
                        {{ selectedType?.name || '请选择' }}
                        <text class="iconfont icon-arrow-right"></text>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">户型</text>
                <input class="input" v-model="formData.room_type" placeholder="如：1室1厅1卫" />
            </view>
            
            <view class="form-item">
                <text class="label">面积(㎡)</text>
                <input class="input" type="digit" v-model="formData.area" placeholder="请输入面积" />
            </view>
            
            <view class="form-item">
                <text class="label">月租金(元)</text>
                <input class="input" type="digit" v-model="formData.price" placeholder="请输入月租金" />
            </view>
            
            <view class="form-item">
                <text class="label">押金(元)</text>
                <input class="input" type="digit" v-model="formData.deposit" placeholder="请输入押金" />
            </view>
            
            <view class="form-item">
                <text class="label">详细地址</text>
                <input class="input" v-model="formData.address" placeholder="请输入详细地址" />
            </view>
            
            <view class="form-item">
                <text class="label">联系人</text>
                <input class="input" v-model="formData.contact_name" placeholder="请输入联系人姓名" />
            </view>
            
            <view class="form-item">
                <text class="label">联系电话</text>
                <input class="input" type="tel" v-model="formData.contact_mobile" placeholder="请输入联系电话" />
            </view>
            
            <view class="form-item column">
                <text class="label">封面图</text>
                <xy-upload v-model="formData.cover_image" :maxCount="1" />
            </view>
            
            <view class="form-item column">
                <text class="label">轮播图（最多9张）</text>
                <xy-upload v-model="formData.images" :maxCount="9" />
            </view>
            
            <view class="form-item column">
                <text class="label">房源描述</text>
                <textarea class="textarea" v-model="formData.description" placeholder="详细描述房源情况..." :maxlength="1000" />
            </view>
        </view>

        <view class="submit-bar">
            <view class="bar-space"></view>
            <button class="submit-btn2" @click="handleSubmit">{{ isEdit ? '保存修改' : '发布房源' }}</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { publishHouse, editHouse, getHouseDetail } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'

const isEdit = ref(false)
const editId = ref(0)

const houseTypes = [
    { value: 'RENT', name: '整租' },
    { value: 'SHARE', name: '合租' },
    { value: 'SUBLEASE', name: '转租' }
]

const selectedType = ref<any>(null)
const formData = ref({
    title: '',
    house_type: '',
    room_type: '',
    area: '',
    price: '',
    deposit: '',
    address: '',
    contact_name: '',
    contact_mobile: '',
    cover_image: '',
    images: [] as string[],
    description: ''
})

onLoad((options: any) => {
    loadConfig()
    if (options?.id) {
        isEdit.value = true
        editId.value = parseInt(options.id)
        uni.setNavigationBarTitle({ title: '编辑房源' })
        loadDetail()
    }
})

const loadDetail = async () => {
    const res: any = await getHouseDetail(editId.value)
    if (res.code === 1 && res.data) {
        const d = res.data
        formData.value.title = d.title || ''
        formData.value.house_type = d.house_type || ''
        formData.value.room_type = d.room_type || ''
        formData.value.area = d.area ? String(d.area) : ''
        formData.value.price = d.price ? String(d.price) : ''
        formData.value.deposit = d.deposit ? String(d.deposit) : ''
        formData.value.address = d.address || ''
        formData.value.contact_name = d.contact_name || ''
        formData.value.contact_mobile = d.contact_mobile || ''
        formData.value.cover_image = d.cover_image || ''
        formData.value.description = d.description || d.content || ''
        if (d.images) {
            if (typeof d.images === 'string') {
                try {
                    const parsed = JSON.parse(d.images)
                    formData.value.images = Array.isArray(parsed) ? parsed : d.images.split(',').filter((s: string) => s)
                } catch {
                    formData.value.images = d.images.split(',').filter((s: string) => s)
                }
            } else if (Array.isArray(d.images)) {
                formData.value.images = d.images
            }
        }
        if (d.house_type) {
            selectedType.value = houseTypes.find(t => t.value === d.house_type) || null
        }
    }
}

const onTypeChange = (e: any) => {
    const index = e.detail.value
    selectedType.value = houseTypes[index]
    formData.value.house_type = selectedType.value.value
}

const handleSubmit = async () => {
    if (!formData.value.title.trim()) {
        uni.showToast({ title: '请输入房源标题', icon: 'none' })
        return
    }
    if (!formData.value.house_type) {
        uni.showToast({ title: '请选择房源类型', icon: 'none' })
        return
    }
    if (!formData.value.price) {
        uni.showToast({ title: '请输入月租金', icon: 'none' })
        return
    }
    if (!formData.value.address) {
        uni.showToast({ title: '请输入详细地址', icon: 'none' })
        return
    }
    if (!formData.value.contact_mobile) {
        uni.showToast({ title: '请输入联系电话', icon: 'none' })
        return
    }
    
    uni.showLoading({ title: '发布中...' })
    try {
        const res: any = isEdit.value
            ? await editHouse({ id: editId.value, ...formData.value })
            : await publishHouse(formData.value)
        uni.hideLoading()
        
        if (res.code === 1) {
            // 根据返回的状态判断提示文字
            let successMsg = isEdit.value ? '修改成功' : '发布成功'
            if (res.data?.status === 0) {
                successMsg = '提交审核成功，请等待审核'
            }
            uni.showToast({ title: successMsg, icon: 'success' })
            setTimeout(() => uni.navigateBack(), 1500)
        } else {
            uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.publish-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 120rpx;
}

.form-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 0 20rpx;
}

.form-item {
    display: flex;
    align-items: center;
    padding: 24rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &.column {
        flex-direction: column;
        align-items: flex-start;
    }
    
    &:last-child { border-bottom: none; }
    
    .label {
        font-size: 28rpx;
        color: #333;
        width: 160rpx;
        flex-shrink: 0;
    }
    
    .input {
        flex: 1;
        font-size: 28rpx;
    }
    
    .textarea {
        width: 100%;
        height: 200rpx;
        font-size: 28rpx;
        margin-top: 16rpx;
        padding: 16rpx;
        background: #f8f8f8;
        border-radius: 8rpx;
    }
    
    .picker-value {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 28rpx;
        color: #666;
        
        .iconfont { color: #ccc; }
    }
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-top: 16rpx;
    width: 100%;
}

.image-item {
    width: 200rpx;
    height: 200rpx;
    border-radius: 8rpx;
    position: relative;
    overflow: hidden;
    
    image { width: 100%; height: 100%; }
    
    .delete-btn {
        position: absolute;
        top: 0;
        right: 0;
        width: 40rpx;
        height: 40rpx;
        background: rgba(0,0,0,0.5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    &.add {
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2rpx dashed #ddd;
        
        .iconfont { font-size: 48rpx; color: #ccc; }
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    justify-content: flex-end;

    .submit-btn2 {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        border: none;
        border-radius: 40rpx;
        padding: 16rpx 60rpx;
        font-size: 28rpx;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
    }
}
</style>
