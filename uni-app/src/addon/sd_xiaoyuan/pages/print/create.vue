<template>
    <view class="print-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">打印服务</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="main-title">帮打印</text>
                    <view class="sub-tag">
                        <text>· 下单立享优质服务 ·</text>
                    </view>
                </view>
                <view class="header-right">
                    <u-icon name="file-text" size="80" color="#ff9800"></u-icon>
                </view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 送货地址 -->
            <view class="form-card" @click="selectAddress">
                <view class="card-icon gradient-icon orange">
                    <u-icon name="home" size="20" color="#fff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">送到哪个宿舍给您呢?</text>
                    <text class="value" v-else>{{ receiveAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 打印文件上传 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon gradient-icon yellow">
                        <u-icon name="file-text" size="20" color="#fff"></u-icon>
                    </view>
                    <text class="label">打印文件上传({{ files.length }}/8)</text>
                    <view class="add-file-btn" @click="chooseFile">+ 文件</view>
                </view>
                <view class="file-list" v-if="files.length > 0">
                    <view class="file-item" v-for="(file, index) in files" :key="index">
                        <u-icon name="file-text" size="20" color="#ff9800"></u-icon>
                        <text class="file-name">{{ file.name }}</text>
                        <view class="delete-btn" @click="removeFile(index)">×</view>
                    </view>
                </view>
            </view>

            <!-- 纸张数量 -->
            <view class="form-card">
                <view class="card-icon gradient-icon blue">
                    <u-icon name="list" size="20" color="#fff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">纸张数量</text>
                </view>
                <view class="count-control">
                    <view class="count-btn minus" @click="decreasePages">-</view>
                    <text class="count-value">{{ pageCount }}</text>
                    <view class="count-btn plus" @click="increasePages">+</view>
                </view>
            </view>

            <!-- 备注 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon gradient-icon green">
                        <u-icon name="edit-pen" size="20" color="#fff"></u-icon>
                    </view>
                    <text class="label">备注</text>
                </view>
                <textarea 
                    class="remark-input" 
                    v-model="remark" 
                    placeholder="标明需要打印的格式"
                    :maxlength="200"
                ></textarea>
            </view>

            <!-- 打印选项 -->
            <view class="options-section">
                <view class="option-row">
                    <view 
                        class="option-btn" 
                        :class="{ active: printSide === 'single' }"
                        @click="printSide = 'single'"
                    >单面</view>
                    <view 
                        class="option-btn" 
                        :class="{ active: printSide === 'double' }"
                        @click="printSide = 'double'"
                    >双面</view>
                    <view 
                        class="option-btn" 
                        :class="{ active: printColor === 'bw' }"
                        @click="printColor = 'bw'"
                    >黑白</view>
                    <view 
                        class="option-btn" 
                        :class="{ active: printColor === 'color' }"
                        @click="printColor = 'color'"
                    >彩印</view>
                    <view 
                        class="option-btn" 
                        :class="{ active: paperSize === 'A3' }"
                        @click="paperSize = 'A3'"
                    >A3</view>
                </view>
                <view class="option-row">
                    <view 
                        class="option-btn" 
                        :class="{ active: paperSize === 'A4' }"
                        @click="paperSize = 'A4'"
                    >A4</view>
                    <view 
                        class="option-btn" 
                        :class="{ active: paperSize === 'A5' }"
                        @click="paperSize = 'A5'"
                    >A5</view>
                </view>
            </view>

            <!-- 费用 -->
            <view class="form-card">
                <view class="card-icon gradient-icon red">
                    <u-icon name="red-packet" size="20" color="#fff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">费用</text>
                </view>
                <input 
                    type="number" 
                    class="fee-input" 
                    v-model="totalFee" 
                    placeholder="输入金额"
                />
            </view>

            <xy-order-yinsi-field v-model="yinsiText" />

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部占位符 -->
        <view style="height: 200rpx;"></view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder">立即下单</button>
        </view>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { createOrder, uploadDocument } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_print')

const statusBarHeight = ref(0)
const navBarHeight = ref(44)

const receiveAddress = ref('')
const files = ref<any[]>([])
const pageCount = ref(1)
const remark = ref('')
const printSide = ref('single')
const printColor = ref('bw')
const paperSize = ref('A4')
const totalFee = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)
const yinsiText = ref('')

const onAddrSelect = (address: any) => {
    if (address && (address.type === 'receive' || !address.type)) {
        receiveAddress.value = address.address
    }
}

onMounted(() => {
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    uni.$on('onAddressSelect', onAddrSelect)
})

onUnmounted(() => {
    uni.$off('onAddressSelect', onAddrSelect)
})

const selectAddress = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/address/select?type=receive' })
}

const doUploadFile = async (filePath: string, fileName: string) => {
    try {
        const res: any = await uploadDocument(filePath)
        if (res.code === 1 && res.data?.url) {
            files.value.push({
                name: fileName,
                path: filePath,
                url: res.data.url
            })
        } else {
            uni.showToast({ title: res.msg || '上传失败，请重试', icon: 'none' })
        }
    } catch (e) {
        console.error('上传错误:', e)
        uni.showToast({ title: '上传失败，请检查文件格式或重试', icon: 'none' })
    }
}

const chooseFile = () => {
    // #ifdef H5
    const input = document.createElement('input')
    input.type = 'file'
    input.multiple = true
    input.accept = '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png'
    input.onchange = async (e: any) => {
        const selectedFiles = Array.from(e.target.files || []) as File[]
        const remaining = 8 - files.value.length
        uni.showLoading({ title: '上传中...' })
        for (const f of selectedFiles.slice(0, remaining)) {
            const tempPath = URL.createObjectURL(f)
            await doUploadFile(tempPath, f.name)
        }
        uni.hideLoading()
    }
    input.click()
    // #endif
    
    // #ifdef MP-WEIXIN
    uni.chooseMessageFile({
        count: 8 - files.value.length,
        type: 'file',
        success: async (res) => {
            uni.showLoading({ title: '上传中...' })
            for (const f of res.tempFiles) {
                await doUploadFile(f.path, f.name)
            }
            uni.hideLoading()
        },
        fail: (err: any) => {
            handleUploadError(err)
        }
    })
    // #endif
    
    // #ifdef APP-PLUS
    uni.chooseImage({
        count: 8 - files.value.length,
        success: async (res) => {
            const tempPaths = Array.isArray(res.tempFilePaths) ? res.tempFilePaths : []
            uni.showLoading({ title: '上传中...' })
            for (let i = 0; i < tempPaths.length; i++) {
                await doUploadFile(tempPaths[i], `文件${files.value.length + 1}`)
            }
            uni.hideLoading()
        },
        fail: (err) => {
            handleUploadError(err)
        }
    })
    // #endif
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
            content: event.errMsg || '上传文件失败，请重试',
            showCancel: false
        })
    }
}

const removeFile = (index: number) => {
    files.value.splice(index, 1)
}

const increasePages = () => {
    pageCount.value++
}

const decreasePages = () => {
    if (pageCount.value > 1) {
        pageCount.value--
    }
}

const submitOrder = async () => {
    if (!receiveAddress.value) {
        uni.showToast({ title: '请选择送货地址', icon: 'none' })
        return
    }
    if (files.value.length === 0) {
        uni.showToast({ title: '请上传打印文件', icon: 'none' })
        return
    }

    const ext = {
        files: files.value.map(f => f.url || f.name),
        page_count: pageCount.value,
        print_side: printSide.value,
        print_color: printColor.value,
        paper_size: paperSize.value,
        remark: remark.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'PRINT',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            receive_address: receiveAddress.value,
            goods_name: '打印文件',
            task_desc: remark.value,
            total_fee: parseFloat(totalFee.value) || 0,
            remark: remark.value || '',
            yinsi_text: yinsiText.value,
            ext: JSON.stringify(ext)
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            // 使用框架支付组件
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const goBack = () => {
    uni.navigateBack()
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.print-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    padding-bottom: 40rpx;
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    
    .back-btn, .placeholder {
        width: 60rpx;
    }
    
    .title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 30rpx;
    
    .header-left {
        .main-title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 10rpx;
        }
        
        .sub-tag {
            display: inline-block;
            background: linear-gradient(135deg, #ff9800, #ffb74d);
            padding: 8rpx 20rpx;
            border-radius: 30rpx;
            
            text {
                font-size: 22rpx;
                color: #fff;
            }
        }
    }
}

.form-content {
    height: calc(100vh - 350rpx);
    width: auto;
    padding: 20rpx;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    display: flex;
    align-items: center;
    width: auto;
    
    &.column {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-icon {
        width: 50rpx;
        height: 50rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
        font-size: 28rpx;
        
        &.gradient-icon {
            border-radius: 50%;
            
            &.orange {
                background: linear-gradient(135deg, #ff9800, #ffb74d);
            }
            
            &.yellow {
                background: linear-gradient(135deg, #ffc107, #ffca28);
            }
            
            &.blue {
                background: linear-gradient(135deg, #2196f3, #64b5f6);
            }
            
            &.green {
                background: linear-gradient(135deg, #4caf50, #81c784);
            }
            
            &.red {
                background: linear-gradient(135deg, #f44336, #e57373);
            }
            
            &.purple {
                background: linear-gradient(135deg, #9c27b0, #ba68c8);
            }
        }
    }
    
    .card-content {
        flex: 1;
        
        .placeholder {
            color: #999;
            font-size: 28rpx;
        }
        
        .value, .label {
            color: #333;
            font-size: 28rpx;
        }
    }
    
    .card-header {
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 20rpx;
        
        .label {
            flex: 1;
            font-size: 28rpx;
            color: #333;
            font-weight: bold;
        }
        
        .add-file-btn {
            padding: 10rpx 24rpx;
            background: #fff3e0;
            color: #ff9800;
            font-size: 24rpx;
            border-radius: 20rpx;
        }
    }
}

.file-list {
    width: 100%;
    
    .file-item {
        display: flex;
        align-items: center;
        padding: 16rpx 20rpx;
        background: #f8f8f8;
        border-radius: 8rpx;
        margin-bottom: 12rpx;
        
        .file-name {
            flex: 1;
            margin-left: 16rpx;
            font-size: 26rpx;
            color: #333;
        }
        
        .delete-btn {
            width: 36rpx;
            height: 36rpx;
            background: #ff4d4f;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24rpx;
        }
    }
}

.count-control {
    display: flex;
    align-items: center;
    
    .count-btn {
        width: 50rpx;
        height: 50rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28rpx;
        
        &.minus {
            background: #f5f5f5;
            color: #666;
        }
        
        &.plus {
            background: #ff9800;
            color: #fff;
        }
    }
    
    .count-value {
        width: 80rpx;
        text-align: center;
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
    }
}

.remark-input {
    width: 94%;
    min-height: 120rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
}

.options-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    
    .option-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;
        margin-bottom: 16rpx;
        
        &:last-child {
            margin-bottom: 0;
        }
    }
    
    .option-btn {
        padding: 16rpx 32rpx;
        background: #f5f5f5;
        border-radius: 8rpx;
        font-size: 26rpx;
        color: #666;
        border: 2rpx solid transparent;
        
        &.active {
            background: #fff3e0;
            color: #ff9800;
            border-color: #ff9800;
        }
    }
}

.fee-input {
    width: 200rpx;
    text-align: right;
    font-size: 28rpx;
    color: #333;
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    
    .price-info {
        .label {
            font-size: 26rpx;
            color: #666;
        }
        
        .price {
            font-size: 40rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
    .submit-btn2 {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 28rpx;
        padding: 16rpx 60rpx;
        border-radius: 40rpx;
        border: none;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
    }
}
</style>
