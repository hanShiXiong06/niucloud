<template>
    <view class="publish-page">
        <view class="form-section">
            <view class="form-item">
                <text class="label">分类</text>
                <picker :range="categoryList" range-key="name" @change="onCategoryChange">
                    <view class="picker-value">
                        {{ selectedCategory ? selectedCategory.name : '请选择分类' }}
                        <u-icon name="arrow-right" size="14" color="#ccc"></u-icon>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <text class="label">标题</text>
                <input class="input" v-model="formData.title" placeholder="请输入标题（选填）" />
            </view>
            
            <view class="form-item column">
                <text class="label">内容</text>
                <textarea class="textarea" v-model="formData.content" placeholder="分享你的校园生活..." :maxlength="1000" />
                <text class="count">{{ formData.content.length }}/1000</text>
            </view>
            
            <view class="form-item column">
                <text class="label">图片（最多9张）</text>
                <xy-upload v-model="formData.images" :maxCount="9" />
            </view>
        </view>

        <view class="submit-bar">
            <view class="bar-space"></view>
            <button class="submit-btn2" :disabled="!canSubmit" @click="handleSubmit">发布</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed, onMounted } from 'vue'
import { publishCommunity, getCommunityCategories } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import xyUpload from '../../components/xy-upload.vue'

const categoryList = ref<any[]>([])

const selectedCategory = ref<any>(null)
const formData = ref({
    category_id: 0,
    title: '',
    content: '',
    images: [] as string[]
})

const canSubmit = computed(() => {
    return formData.value.content.trim().length > 0
})

onMounted(() => {
    loadCategories()
})

const loadCategories = async () => {
    try {
        const res: any = await getCommunityCategories()
        if (res.code === 1 && res.data) {
            categoryList.value = res.data || []
        }
    } catch (e) {
        console.error('加载分类失败', e)
    }
}

const onCategoryChange = (e: any) => {
    const index = e.detail.value
    if (categoryList.value && categoryList.value[index]) {
        selectedCategory.value = categoryList.value[index]
        formData.value.category_id = selectedCategory.value.id
    }
}

const previewImage = (index: number) => {
    uni.previewImage({
        current: index,
        urls: formData.value.images.map((u: string) => img(u))
    })
}

const handleSubmit = async () => {
    if (!canSubmit.value) return
    
    // 获取当前选择的学校
    const currentSchool = uni.getStorageSync('current_school')
    
    uni.showLoading({ title: '发布中...' })
    try {
        const res: any = await publishCommunity({
            ...formData.value,
            school_id: currentSchool?.id || 0,
            campus: currentSchool?.campus || ''
        })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '发布成功', icon: 'success' })
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
        width: 120rpx;
        flex-shrink: 0;
    }
    
    .input {
        flex: 1;
        font-size: 28rpx;
    }
    
    .textarea {
        width: 100%;
        height: 300rpx;
        font-size: 28rpx;
        margin-top: 16rpx;
        padding: 16rpx;
        background: #f8f8f8;
        border-radius: 8rpx;
    }
    
    .count {
        align-self: flex-end;
        font-size: 24rpx;
        color: #999;
        margin-top: 8rpx;
    }
    
    .picker-value {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 28rpx;
        color: #666;
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
    
    image {
        width: 100%;
        height: 100%;
    }
    
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
        border-radius: 0 0 0 8rpx;
    }
    
    &.add {
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2rpx dashed #ddd;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;z-index: 22;
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
        
        &[disabled] {
            background: #ccc;
            color: #fff;
        }
    }
}
</style>
