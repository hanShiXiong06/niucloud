<template>
    <view class="publish-page">
        <!-- 功能关闭提示 -->
        <view v-if="config && config.enable_secondhand == 0" style="text-align:center;margin-top:150rpx;">
            <u-icon name="info-circle" size="60" color="#ccc"></u-icon>
            <text style="display:block;margin-top:20rpx;color:#999;font-size:28rpx;">{{ config.close_text || '功能已下架' }}</text>
        </view>
        
        <!-- 正常内容 -->
        <view v-else>
        <view class="form-section">
            <view class="form-item">
                <text class="label required">标题</text>
                <input v-model="formData.title" placeholder="请输入物品标题" maxlength="30" />
            </view>
            
            <view class="form-item">
                <text class="label required">分类</text>
                <view class="category-list">
                    <view 
                        class="category-item" 
                        :class="{ active: formData.category_id === item.id }"
                        v-for="item in categoryList"
                        :key="item.id"
                        @click="formData.category_id = item.id"
                    >{{ item.name }}</view>
                </view>
            </view>
            
            <view class="form-item">
                <text class="label required">价格</text>
                <view class="price-input">
                    <text class="unit">¥</text>
                    <input v-model="formData.price" type="digit" placeholder="0.00" />
                </view>
            </view>
            
            <view class="form-item">
                <text class="label">原价</text>
                <view class="price-input">
                    <text class="unit">¥</text>
                    <input v-model="formData.original_price" type="digit" placeholder="选填" />
                </view>
            </view>
            
            <view class="form-item">
                <text class="label required">图片</text>
                <view class="image-list">
                    <view class="image-item" v-for="(url, index) in imageList" :key="index">
                        <image :src="img(url)" mode="aspectFill"></image>
                        <view class="delete-btn" @click="removeImage(index)">
                            <u-icon name="close" size="14" color="#fff"></u-icon>
                        </view>
                    </view>
                    <view class="add-image" v-if="imageList.length < 9" @click="chooseImage">
                        <u-icon name="camera" size="40" color="#ccc"></u-icon>
                        <text>{{ imageList.length }}/9</text>
                    </view>
                </view>
            </view>
            
            <view class="form-item">
                <text class="label required">描述</text>
                <textarea v-model="formData.content" placeholder="请描述物品的详细信息、成色等" maxlength="500"></textarea>
            </view>
            
            <view class="form-item">
                <text class="label">交易方式</text>
                <view class="trade-list">
                    <view 
                        class="trade-item" 
                        :class="{ active: formData.trade_method === 'FACE' }"
                        @click="formData.trade_method = 'FACE'"
                    >当面交易</view>
                    <view 
                        class="trade-item" 
                        :class="{ active: formData.trade_method === 'EXPRESS' }"
                        @click="formData.trade_method = 'EXPRESS'"
                    >快递邮寄</view>
                    <view 
                        class="trade-item" 
                        :class="{ active: formData.trade_method === 'BOTH' }"
                        @click="formData.trade_method = 'BOTH'"
                    >均可</view>
                </view>
            </view>
            
            <view class="form-item">
                <text class="label">交易地点</text>
                <input v-model="formData.trade_address" placeholder="如：西门快递站" />
            </view>
            
            <view class="form-item">
                <text class="label required">联系电话</text>
                <input v-model="formData.contact_mobile" type="number" placeholder="请输入手机号" maxlength="11" />
            </view>
        </view>
        
        <view class="submit-section">
            <button class="submit-btn" @click="submit">{{ isEdit ? '保存修改' : '发布闲置' }}</button>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { publishSecondhand, editSecondhand, getSecondhandInfo, getSecondhandCategoryList, getConfig } from '../../api/xiaoyuan'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'

const config = ref<any>(null)

const isEdit = ref(false)
const editId = ref(0)

const formData = ref({
    title: '',
    category_id: 0,
    price: '',
    original_price: '',
    content: '',
    trade_method: 'FACE',
    trade_address: '',
    contact_mobile: ''
})

const imageList = ref<string[]>([])

onLoad((options: any) => {
    if (options?.id) {
        isEdit.value = true
        editId.value = parseInt(options.id)
        uni.setNavigationBarTitle({ title: '编辑闲置' })
        loadDetail()
    }
})

const loadDetail = async () => {
    const res: any = await getSecondhandInfo(editId.value)
    if (res.code === 1 && res.data) {
        const d = res.data
        formData.value.title = d.title || ''
        formData.value.category_id = d.category_id || 'OTHER'
        formData.value.price = d.price ? String(d.price) : ''
        formData.value.original_price = d.original_price ? String(d.original_price) : ''
        formData.value.content = d.content || ''
        formData.value.trade_method = d.trade_method || 'FACE'
        formData.value.trade_address = d.trade_address || ''
        formData.value.contact_mobile = d.contact_mobile || ''
        if (d.images) {
            if (typeof d.images === 'string') {
                try {
                    const parsed = JSON.parse(d.images)
                    if (Array.isArray(parsed)) { imageList.value = parsed }
                    else { imageList.value = d.images.split(',').filter((s: string) => s) }
                } catch {
                    imageList.value = d.images.split(',').filter((s: string) => s)
                }
            } else if (Array.isArray(d.images)) {
                imageList.value = d.images
            }
        }
    }
}

const chooseImage = () => {
    uni.chooseImage({
        count: 9 - imageList.value.length,
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

const removeImage = (index: number) => {
    imageList.value.splice(index, 1)
}

const categoryList = ref<any[]>([])

onMounted(() => {
    loadConfig()
    loadCategories()
})

const loadConfig = async () => {
    const cachedConfig = uni.getStorageSync('xiaoyuan_config')
    if (cachedConfig) config.value = cachedConfig
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            config.value = res.data
            uni.setStorageSync('xiaoyuan_config', res.data)
        }
    } catch (e) {
        console.error('获取配置失败:', e)
    }
}

const loadCategories = async () => {
    const res: any = await getSecondhandCategoryList()
    if (res.code === 1) {
        categoryList.value = res.data || []
        if (categoryList.value.length > 0 && !formData.value.category_id) {
            formData.value.category_id = categoryList.value[0].id
        }
    }
}

const submit = async () => {
    if (!formData.value.title) {
        uni.showToast({ title: '请输入标题', icon: 'none' })
        return
    }
    if (!formData.value.price) {
        uni.showToast({ title: '请输入价格', icon: 'none' })
        return
    }
    if (imageList.value.length === 0) {
        uni.showToast({ title: '请上传图片', icon: 'none' })
        return
    }
    if (!formData.value.content) {
        uni.showToast({ title: '请输入描述', icon: 'none' })
        return
    }
    if (!formData.value.contact_mobile) {
        uni.showToast({ title: '请输入联系电话', icon: 'none' })
        return
    }
    if (!/^1[3-9]\d{9}$/.test(formData.value.contact_mobile)) {
        uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
        return
    }
    
    try {
        // 获取当前选择的学校
        const currentSchool = uni.getStorageSync('current_school')
        
        uni.showLoading({ title: '发布中...' })
        
        const data = {
            ...formData.value,
            images: imageList.value.join(','),
            school_id: currentSchool?.id || 0,
            campus: currentSchool?.campus || ''
        }
        
        const res: any = isEdit.value
            ? await editSecondhand({ id: editId.value, ...data })
            : await publishSecondhand(data)
        uni.hideLoading()
        
        if (res.code === 1) {
            // 根据返回的状态判断提示文字
            let successMsg = isEdit.value ? '修改成功' : '发布成功'
            if (res.data?.status === 0) {
                successMsg = '提交审核成功，请等待审核'
            }
            uni.showToast({ title: successMsg, icon: 'success' })
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

.category-list, .trade-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.category-item, .trade-item {
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
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 40rpx;
    font-size: 30rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;

    &::after { border: none; }
    
    &[disabled] {
        background: #ccc;
        color: #fff;
        box-shadow: none;
    }
}
</style>
