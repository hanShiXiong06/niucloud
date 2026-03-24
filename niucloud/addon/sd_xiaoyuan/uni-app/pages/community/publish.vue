<template>
    <view class="publish-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="form-item">
                <view class="item-header">
                    <u-icon name="grid" size="18" color="#52c41a"></u-icon>
                    <text class="label">分类</text>
                </view>
                <picker :range="categoryList" range-key="name" @change="onCategoryChange">
                    <view class="picker-value">
                        <text :class="{ placeholder: !selectedCategory }">
                            {{ selectedCategory ? selectedCategory.name : '请选择分类' }}
                        </text>
                        <u-icon name="arrow-right" size="14" color="#ccc"></u-icon>
                    </view>
                </picker>
            </view>
            
            <view class="form-item">
                <view class="item-header">
                    <u-icon name="edit-pen" size="18" color="#1890ff"></u-icon>
                    <text class="label">标题</text>
                    <text class="optional">选填</text>
                </view>
                <input class="input" v-model="formData.title" placeholder="给你的帖子起个标题吧~" />
            </view>
            
            <view class="form-item column">
                <view class="item-header">
                    <u-icon name="chat" size="18" color="#ff9800"></u-icon>
                    <text class="label">内容</text>
                </view>
                <textarea 
                    class="textarea" 
                    v-model="formData.content" 
                    placeholder="分享你的校园生活，记录美好瞬间..." 
                    :maxlength="1000"
                    :auto-height="true"
                    :show-confirm-bar="false"
                />
                <text class="count">{{ formData.content.length }}/1000</text>
            </view>
            
            <view class="form-item column">
                <view class="item-header">
                    <u-icon name="photo" size="18" color="#e91e63"></u-icon>
                    <text class="label">图片</text>
                    <text class="hint">最多9张</text>
                </view>
                <view class="upload-container">
                    <xy-upload v-model="imageStr" :maxCount="9" />
                </view>
            </view>
        </view>

        <view class="submit-bar">
            <view class="tips">
                <u-icon name="info-circle" size="14" color="#999"></u-icon>
                <text>发布即表示同意社区规范</text>
            </view>
            <button class="submit-btn" :disabled="!canSubmit" @click="handleSubmit">
                <u-icon name="checkmark-circle" size="18" color="#333"></u-icon>
                <text>发布</text>
            </button>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { publishCommunity, getCommunityCategories } from '../../api/xiaoyuan'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'
import xyUpload from '../../components/xy-upload.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_community')
const categoryList = ref<any[]>([])
const imageStr = ref('')

const selectedCategory = ref<any>(null)
const formData = ref({
    category_id: 0,
    title: '',
    content: '',
    images: ''
})

const canSubmit = computed(() => {
    return formData.value.content.trim().length > 0
})

onMounted(() => {
    loadConfig()
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

const handleSubmit = async () => {
    if (!canSubmit.value) return
    
    // 获取当前选择的学校
    const currentSchool = uni.getStorageSync('current_school')
    
    uni.showLoading({ title: '发布中...' })
    try {
        const res: any = await publishCommunity({
            ...formData.value,
            images: imageStr.value,
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
    background: linear-gradient(to bottom, #f0f9ff 0%, #f5f5f5 200rpx);
    padding-bottom: 140rpx;
}

.form-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 20rpx;
    padding: 0 30rpx;
    width: auto;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
}

.form-item {
    display: flex;
    flex-direction: column;
    padding: 32rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child { 
        border-bottom: none; 
    }
    
    .item-header {
        display: flex;
        align-items: center;
        margin-bottom: 20rpx;
        
        .label {
            font-size: 30rpx;
            color: #333;
            font-weight: 600;
            margin-left: 12rpx;
            flex: 1;
        }
        
        .optional {
            font-size: 24rpx;
            color: #999;
            background: #f5f5f5;
            padding: 4rpx 12rpx;
            border-radius: 8rpx;
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .input {
        width: 100%;
        font-size: 28rpx;
        padding: 20rpx;
        background: #f8f9fa;
        border-radius: 12rpx;
        border: 2rpx solid transparent;
        transition: all 0.3s;
        
        &:focus {
            background: #fff;
            border-color: #52c41a;
        }
    }
    
    .textarea {
        width: 100%;
        min-height: 300rpx;
        font-size: 28rpx;
        padding: 20rpx;
        background: #f8f9fa;
        border-radius: 12rpx;
        line-height: 1.6;
        border: 2rpx solid transparent;
        transition: all 0.3s;
        box-sizing: border-box;
        
        &:focus {
            background: #fff;
            border-color: #52c41a;
        }
    }
    
    .count {
        align-self: flex-end;
        font-size: 24rpx;
        color: #999;
        margin-top: 12rpx;
    }
    
    .picker-value {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 28rpx;
        padding: 20rpx;
        background: #f8f9fa;
        border-radius: 12rpx;
        
        text {
            color: #333;
            
            &.placeholder {
                color: #999;
            }
        }
    }
    
    .upload-container {
        width: 100%;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 16rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.08);
    padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 22;
    width: auto;
    
    .tips {
        display: flex;
        align-items: center;
        gap: 8rpx;
        flex: 1;
        
        text {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .submit-btn {
        background: linear-gradient(135deg, #52c41a, #73d13d);
        color: #fff;
        border: none;
        border-radius: 50rpx;
        padding: 0 48rpx;
        font-size: 30rpx;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8rpx;
        height: 80rpx;
        box-shadow: 0 4rpx 12rpx rgba(82, 196, 26, 0.3);
        transition: all 0.3s;
        
        &:active {
            transform: scale(0.95);
            box-shadow: 0 2rpx 8rpx rgba(82, 196, 26, 0.2);
        }
        
        &[disabled] {
            background: linear-gradient(135deg, #d9d9d9, #e8e8e8);
            color: #999;
            box-shadow: none;
        }
        
        text {
            color: #fff;
        }
        
        &[disabled] text {
            color: #999;
        }
    }
}
</style>
