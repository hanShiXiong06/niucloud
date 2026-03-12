<template>
    <view class="publish-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="form-item">
                <text class="label required">类型</text>
                <view class="type-list">
                    <view 
                        class="type-item" 
                        :class="{ active: formData.type === 'LOST' }"
                        @click="formData.type = 'LOST'"
                    >
                        <text class="iconfont icon-search"></text>
                        <text>寻物启事</text>
                    </view>
                    <view 
                        class="type-item" 
                        :class="{ active: formData.type === 'FOUND' }"
                        @click="formData.type = 'FOUND'"
                    >
                        <text class="iconfont icon-gift"></text>
                        <text>失物招领</text>
                    </view>
                </view>
            </view>
            
            <view class="form-item">
                <text class="label required">标题</text>
                <input v-model="formData.title" placeholder="请输入标题" maxlength="30" />
            </view>
            
            <view class="form-item">
                <text class="label">物品类别</text>
                <view class="category-list">
                    <view 
                        class="category-item" 
                        :class="{ active: formData.category === item.key }"
                        v-for="item in categoryList"
                        :key="item.key"
                        @click="formData.category = item.key"
                    >{{ item.name }}</view>
                </view>
            </view>
            
            <view class="form-item">
                <text class="label required">图片</text>
                <xy-upload v-model="imageStr" :maxCount="9" />
            </view>
            
            <view class="form-item">
                <text class="label required">详细描述</text>
                <textarea v-model="formData.content" placeholder="请详细描述物品特征、丢失/拾取经过等" maxlength="500"></textarea>
            </view>
            
            <view class="form-item">
                <text class="label">{{ formData.type === 'LOST' ? '丢失地点' : '拾取地点' }}</text>
                <input v-model="formData.lost_address" placeholder="如：图书馆二楼" />
            </view>
            
            <view class="form-item">
                <text class="label required">联系方式</text>
                <input v-model="formData.contact_mobile" placeholder="手机号或微信号(必填)" />
            </view>
            
            <view class="form-item" v-if="formData.type === 'LOST'">
                <text class="label">是否急寻</text>
                <view class="urgent-switch">
                    <view class="urgent-item" :class="{ active: !formData.is_urgent }" @click="formData.is_urgent = 0">
                        <text>普通</text>
                    </view>
                    <view class="urgent-item urgent" :class="{ active: formData.is_urgent === 1 }" @click="formData.is_urgent = 1">
                        <text>急寻</text>
                    </view>
                </view>
            </view>
            
            <view class="form-item" v-if="formData.type === 'LOST'">
                <text class="label">悬赏金额</text>
                <view class="price-input">
                    <text class="unit">¥</text>
                    <input v-model="formData.reward" type="digit" placeholder="选填" />
                </view>
            </view>
        </view>
        
        <button class="submit-btn" @click="submit">{{ isEdit ? '保存修改' : '发布' }}</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { publishLostFound, editLostFound, getLostFoundInfo } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_lost_found')

onMounted(() => {
    loadConfig()
})

const isEdit = ref(false)
const editId = ref(0)

const formData = ref({
    type: 'LOST',
    title: '',
    category: '其他',
    content: '',
    lost_address: '',
    contact_mobile: '',
    reward: '',
    is_urgent: 0
})

const imageStr = ref('')

const categoryList = [
    { key: '证件卡片', name: '证件' },
    { key: '电子产品', name: '数码' },
    { key: '钥匙', name: '钥匙' },
    { key: '衣物饰品', name: '衣物' },
    { key: '书籍文具', name: '书籍' },
    { key: '其他', name: '其他' }
]

onLoad((options: any) => {
    if (options?.id) {
        isEdit.value = true
        editId.value = parseInt(options.id)
        uni.setNavigationBarTitle({ title: '编辑' })
        loadDetail()
    }
    if (options?.type) {
        formData.value.type = options.type
    }
})

const loadDetail = async () => {
    const res: any = await getLostFoundInfo(editId.value)
    if (res.code === 1 && res.data) {
        const d = res.data
        formData.value.type = d.type || 'LOST'
        formData.value.title = d.title || ''
        formData.value.category = d.category || ''
        formData.value.content = d.content || ''
        formData.value.lost_address = d.lost_address || ''
        formData.value.contact_mobile = d.contact_mobile || ''
        formData.value.reward = d.reward ? String(d.reward) : ''
        if (d.images) {
            if (typeof d.images === 'string') {
                try {
                    const parsed = JSON.parse(d.images)
                    imageStr.value = Array.isArray(parsed) ? parsed.join(',') : d.images
                } catch {
                    imageStr.value = d.images
                }
            } else if (Array.isArray(d.images)) {
                imageStr.value = d.images.join(',')
            }
        }
    }
}

const submit = async () => {
    if (!formData.value.title) {
        uni.showToast({ title: '请输入标题', icon: 'none' })
        return
    }
    if (!imageStr.value) {
        uni.showToast({ title: '请上传图片', icon: 'none' })
        return
    }
    if (!formData.value.content) {
        uni.showToast({ title: '请输入描述', icon: 'none' })
        return
    }
    if (!formData.value.contact_mobile) {
        uni.showToast({ title: '请填写联系方式(手机或微信)', icon: 'none' })
        return
    }
    
    try {
        // 获取当前选择的学校
        const currentSchool = uni.getStorageSync('current_school')
        
        uni.showLoading({ title: '发布中...' })
        
        const data = {
            ...formData.value,
            images: imageStr.value,
            reward: formData.value.reward ? parseFloat(formData.value.reward) : 0,
            school_id: currentSchool?.id || 0,
            campus: currentSchool?.campus || ''
        }
        
        const res: any = isEdit.value
            ? await editLostFound({ id: editId.value, ...data })
            : await publishLostFound(data)
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: isEdit.value ? '修改成功' : '发布成功', icon: 'success' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
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
    
    textarea {
        width: 100%;
        height: 200rpx;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 24rpx;
        font-size: 28rpx;
        box-sizing: border-box;
    }
}

.type-list {
    display: flex;
    gap: 20rpx;
}

.type-item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    border: 2rpx solid transparent;
    
    .iconfont {
        font-size: 30rpx;
        margin-right: 12rpx;
        color: #666;
    }
    
    text {
        font-size: 28rpx;
        color: #666;
    }
    
    &.active {
        background: #c0fe95;
        border-color: #c0fe95;
        
        .iconfont, text {
            color: #333;
            font-weight: bold;
        }
    }
}

.urgent-switch {
    display: flex;
    gap: 20rpx;
}

.urgent-item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    border: 2rpx solid transparent;
    font-size: 28rpx;
    color: #666;
    
    &.active {
        background: #c0fe95;
        border-color: #c0fe95;
        color: #333;
        font-weight: bold;
    }
    
    &.urgent.active {
        background: #fff1f0;
        border-color: #ff4d4f;
        color: #ff4d4f;
    }
}

.price-input {
    display: flex;
    align-items: center;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 24rpx;
    
    .unit {
        font-size: 28rpx;
        color: #ff6b00;
        font-weight: bold;
        margin-right: 8rpx;
    }
    
    input {
        background: transparent;
        padding: 0;
    }
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.image-item {
    width: 200rpx;
    height: 200rpx;
    border-radius: 12rpx;
    overflow: hidden;
    position: relative;
    
    image {
        width: 100%;
        height: 100%;
    }
    
    .delete-btn {
        position: absolute;
        top: 8rpx;
        right: 8rpx;
        width: 40rpx;
        height: 40rpx;
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28rpx;
    }
}

.add-image {
    width: 200rpx;
    height: 200rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2rpx dashed #ddd;
    
    .iconfont {
        font-size: 48rpx;
        color: #ccc;
        margin-bottom: 8rpx;
    }
    
    text {
        font-size: 24rpx;
        color: #999;
    }
}

.category-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.category-item {
    padding: 16rpx 24rpx;
    background: #f8f8f8;
    border-radius: 8rpx;
    font-size: 26rpx;
    color: #666;
    border: 2rpx solid transparent;
    
    &.active {
        background: #c0fe95;
        color: #333;
        border-color: #c0fe95;
    }
}

.submit-btn {
    position: fixed;
    bottom: 50rpx;
    left: 30rpx;
    right: 30rpx;
    height: 80rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}
</style>
