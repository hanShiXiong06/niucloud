<template>
    <view class="publish-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="form-item column">
                <view class="label-row">
                    <u-icon name="edit-pen" size="16" color="#ff7243"></u-icon>
                    <text class="label">表白内容</text>
                </view>
                <textarea class="textarea" v-model="formData.content" placeholder="写下你想说的话..." :maxlength="500" />
                <text class="count">{{ formData.content.length }}/500</text>
            </view>
            
            <view class="form-item">
                <view class="label-row">
                    <u-icon name="list" size="16" color="#ff7243"></u-icon>
                    <text class="label">表白分类</text>
                </view>
                <view class="category-list">
                    <view 
                        class="category-item" 
                        :class="{ active: formData.type === item.value }"
                        v-for="item in categoryList"
                        :key="item.value"
                        @click="formData.type = item.value"
                    >{{ item.label }}</view>
                </view>
            </view>
            
            <view class="form-item">
                <view class="label-row">
                    <u-icon name="heart" size="16" color="#ff7243"></u-icon>
                    <text class="label">表白对象</text>
                </view>
                <input class="input" v-model="formData.target_name" placeholder="TA的名字（选填）" />
            </view>
            
            <view class="form-item">
                <view class="label-row">
                    <u-icon name="info-circle" size="16" color="#ff7243"></u-icon>
                    <text class="label">对象信息</text>
                </view>
                <input class="input" v-model="formData.target_info" placeholder="如：计算机学院大三（选填）" />
            </view>
            
            <view class="form-item">
                <view class="label-row">
                    <u-icon name="eye-off" size="16" color="#ff7243"></u-icon>
                    <text class="label">匿名发布</text>
                </view>
                <switch :checked="formData.is_anonymous" @change="onAnonymousChange" color="#ff7243" />
            </view>
            
            <view class="form-item column">
                <view class="label-row">
                    <u-icon name="photo" size="16" color="#ff7243"></u-icon>
                    <text class="label">添加图片（最多3张）</text>
                </view>
                <view class="upload-wrap">
                    <view class="img-list">
                        <view class="img-item" v-for="(url, index) in imageList" :key="index">
                            <image :src="img(url)" mode="aspectFill" @click="previewImg(index)"></image>
                            <view class="img-del" @click.stop="removeImg(index)">
                                <u-icon name="close" size="12" color="#fff"></u-icon>
                            </view>
                        </view>
                        <view class="img-add" v-if="imageList.length < 3" @click="chooseImage">
                            <u-icon name="camera" size="40" color="#ccc"></u-icon>
                            <text class="img-add-text">{{ imageList.length }}/3</text>
                        </view>
                    </view>
                </view>
            </view>
        </view>

        <view class="tips">
            <text v-if="config?.confession_auto_approve === 1">温馨提示：表白内容将直接发布，请文明表白~</text>
            <text v-else>温馨提示：表白内容需审核后才能展示，请文明表白~</text>
        </view>

        <view class="submit-bar">
            <button class="submit-btn" :disabled="!canSubmit || submitting" @click="handleSubmit">
                {{ submitting ? '发布中...' : '发布表白' }}
            </button>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { publishConfession } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_confession')

onMounted(() => {
    loadConfig()
})

const formData = ref({
    content: '',
    type: 'ALL',
    target_name: '',
    target_info: '',
    is_anonymous: false
})

const categoryList = ref([
    { label: '全部', value: 'ALL' },
    { label: '暗恋', value: 'CRUSH' },
    { label: '实名', value: 'REAL' },
    { label: '寻人', value: 'FIND' },
    { label: '祝福', value: 'WISH' }
])

const imageList = ref<string[]>([])
const submitting = ref(false)

const canSubmit = computed(() => {
    return formData.value.content.trim().length > 0
})

const onAnonymousChange = (e: any) => {
    formData.value.is_anonymous = e.detail.value
}

const chooseImage = () => {
    uni.chooseImage({
        count: 3 - imageList.value.length,
        sizeType: ['compressed'],
        sourceType: ['album', 'camera'],
        success: (res) => {
            const paths = res.tempFilePaths as string[]
            for (let i = 0; i < paths.length; i++) {
                doUpload(paths[i])
            }
        },
        fail: (err) => {
            handleUploadError(err)
        }
    })
}

const doUpload = async (filePath: string) => {
    if (imageList.value.length >= 3) return
    uni.showLoading({ title: '上传中...' })
    const res: any = await uploadImage({ filePath, name: 'file' })
    uni.hideLoading()
    if (res.data?.url) {
        imageList.value.push(res.data.url)
    } else {
        uni.showToast({ title: '上传失败', icon: 'none' })
    }
}

// 统一的上传错误处理
const handleUploadError = (event: any) => {
    console.log('上传错误:', event)
    if (event.errno == 112 || event.errCode == 112) {
        uni.showModal({
            title: '权限不足',
            content: '请在用户隐私保护指引里面声明【收集你选中的照片或视频信息】',
            showCancel: false
        })
    } else {
        uni.showModal({
            title: '上传失败',
            content: event.errMsg || '上传图片失败，请重试',
            showCancel: false
        })
    }
}

const removeImg = (index: number) => {
    imageList.value.splice(index, 1)
}

const previewImg = (index: number) => {
    uni.previewImage({
        urls: imageList.value.map(u => img(u)),
        current: index
    })
}

const handleSubmit = async () => {
    if (!canSubmit.value || submitting.value) return
    submitting.value = true
    
    // 获取当前选择的学校
    const currentSchool = uni.getStorageSync('current_school')
    
    uni.showLoading({ title: '发布中...' })
    const res: any = await publishConfession({
        content: formData.value.content,
        type: formData.value.type,
        target_name: formData.value.target_name,
        target_info: formData.value.target_info,
        is_anonymous: formData.value.is_anonymous ? 1 : 0,
        images: imageList.value,
        school_id: currentSchool?.id || 0,
        campus: currentSchool?.campus || ''
    })
    uni.hideLoading()
    submitting.value = false
    if (res.code === 1) {
        const successMsg = config.value?.confession_auto_approve === 1 ? '发布成功' : '发布成功，等待审核'
        uni.showToast({ title: successMsg, icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1500)
    } else {
        uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.publish-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #fff5f5, #f5f5f5 300rpx);
    padding-bottom: 160rpx;
}

.form-section {
    background: #fff;
    margin: 24rpx;
    border-radius: 24rpx;
    padding: 8rpx 28rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

.label-row {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-bottom: 4rpx;
}

.form-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 0;
    border-bottom: 1rpx solid #f5f5f5;
    
    &.column {
        flex-direction: column;
        align-items: flex-start;
    }
    
    &:last-child { border-bottom: none; }
    
    .label {
        font-size: 28rpx;
        color: #333;
        font-weight: 600;
    }
    
    .input {
        flex: 1;
        font-size: 28rpx;
        text-align: right;
        color: #333;
    }
    
    .textarea {
        width: 94%;
        height: 260rpx;
        font-size: 28rpx;
        margin-top: 16rpx;
        padding: 20rpx;
        background: #fef6f4;
        border-radius: 16rpx;
        border: 1rpx solid #ffe0d6;
        line-height: 1.7;
    }
    
    .count {
        align-self: flex-end;
        font-size: 22rpx;
        color: #bbb;
        margin-top: 8rpx;
    }
}

.upload-wrap {
    width: 100%;
    margin-top: 16rpx;
}

.img-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.img-item {
    width: 200rpx;
    height: 200rpx;
    border-radius: 16rpx;
    overflow: hidden;
    position: relative;

    image {
        width: 100%;
        height: 100%;
    }

    .img-del {
        position: absolute;
        top: 0;
        right: 0;
        width: 40rpx;
        height: 40rpx;
        background: rgba(0, 0, 0, 0.45);
        border-radius: 0 0 0 16rpx;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.img-add {
    width: 200rpx;
    height: 200rpx;
    background: #fef6f4;
    border-radius: 16rpx;
    border: 2rpx dashed #ffc8b8;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8rpx;

    .img-add-text {
        font-size: 22rpx;
        color: #bbb;
    }
}

.category-list {
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;flex:1;justify-content: flex-end;
    margin-top: 16rpx;
}

.category-item {
    padding: 16rpx 32rpx;
    background: #fef6f4;
    border-radius: 30rpx;
    font-size: 26rpx;
    color: #666;
    border: 2rpx solid #ffe0d6;
    transition: all 0.3s;
    
    &.active {
        background: linear-gradient(135deg, #ff7243, #ff9a6c);
        color: #fff;
        border-color: #ff7243;
    }
}

.tips {
    margin: 0 24rpx;
    padding: 20rpx 24rpx;
    background: #fff9f0;
    border-radius: 16rpx;
    border: 1rpx solid #ffe8cc;
    
    text {
        font-size: 24rpx;
        color: #cc8800;
        line-height: 1.6;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 40rpx;z-index: 22;
    background: #fff;
    box-shadow: 0 -4rpx 24rpx rgba(0, 0, 0, 0.06);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));

    .submit-btn {
        width: 100%;
        height: 88rpx;
        background: linear-gradient(135deg, #ff7243, #ff9a6c);
        color: #fff;
        border: none;
        border-radius: 48rpx;
        font-weight: bold;
        letter-spacing: 4rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        
        &[disabled] {
            background: #ddd;
            color: #999;
        }
    }
}
</style>
