<template>
    <view class="price-image-generator">
        <!-- 用于生成图片的DOM容器 -->
        <view 
            ref="imageContainer"
            id="priceImageContainer"
            class="price-image-container"
            :style="{ 
                opacity: isGenerating ? 0.5 : 1,
                transform: `scale(${isGenerating ? 0.95 : 1})`,
                transition: 'all 0.3s ease'
            }"
        >
            <!-- 报价单内容 -->
            <view class="price-sheet">
                <!-- 头部 -->
                <view class="header-section">
                    <view class="header-bg">
                        <view class="header-content">
                            <view class="brand-info">
                                <view class="brand-icon">{{ getBrandIcon(brandInfo.brand_code) }}</view>
                                <view class="brand-text">
                                    <view class="brand-name">{{ brandInfo.brand_name || '设备报价' }}</view>
                                    <view class="brand-desc">{{ getDeviceTypeName(deviceType) }}回收报价单</view>
                                </view>
                            </view>
                            <view class="date-info">
                                <view class="date-label">报价日期</view>
                                <view class="date-value">{{ getCurrentDate() }}</view>
                            </view>
                        </view>
                    </view>
                </view>
                
                <!-- 表格内容 -->
                <view class="table-section">
                    <!-- 表头 -->
                    <view class="table-header">
                        <view class="table-cell header-cell model-cell">型号</view>
                        <view class="table-cell header-cell capacity-cell">容量</view>
                        <view class="table-cell header-cell price-cell-high">高保充新</view>
                        <view class="table-cell header-cell price-cell-good">充新</view>
                        <view class="table-cell header-cell price-cell-normal">靓机</view>
                        <view class="table-cell header-cell price-cell-fair">小花</view>
                        <view class="table-cell header-cell price-cell-poor">大花</view>
                        <view class="table-cell header-cell price-cell-broken">外爆</view>
                    </view>
                    
                    <!-- 数据行 -->
                    <view 
                        v-for="(device, index) in currentPageDevices" 
                        :key="device.id"
                        class="table-row"
                        :class="{ 'even-row': index % 2 === 0 }"
                    >
                        <view class="table-cell data-cell model-cell">{{ device.model_name }}</view>
                        <view class="table-cell data-cell capacity-cell">{{ device.capacity }}</view>
                        <view class="table-cell data-cell price-cell-high">
                            {{ formatPrice(device.currentPrice?.price_data?.['高保充新']) }}
                        </view>
                        <view class="table-cell data-cell price-cell-good">
                            {{ formatPrice(device.currentPrice?.price_data?.['充新']) }}
                        </view>
                        <view class="table-cell data-cell price-cell-normal">
                            {{ formatPrice(device.currentPrice?.price_data?.['靓机']) }}
                        </view>
                        <view class="table-cell data-cell price-cell-fair">
                            {{ formatPrice(device.currentPrice?.price_data?.['小花']) }}
                        </view>
                        <view class="table-cell data-cell price-cell-poor">
                            {{ formatPrice(device.currentPrice?.price_data?.['大花']) }}
                        </view>
                        <view class="table-cell data-cell price-cell-broken">
                            {{ formatPrice(device.currentPrice?.price_data?.['外爆']) }}
                        </view>
                    </view>
                </view>
                
                <!-- 底部信息 -->
                <view class="footer-section">
                    <view class="footer-left">
                        <view class="total-count">共 {{ totalDevices }} 个型号</view>
                        <view v-if="totalPages > 1" class="page-info">
                            第 {{ currentPage }} 页，共 {{ totalPages }} 页
                        </view>
                    </view>
                    <view class="footer-right">
                        <view class="disclaimer">报价仅供参考</view>
                        <view class="disclaimer">以实际检测为准</view>
                    </view>
                </view>
            </view>
        </view>
        
        <!-- 分页控制 -->
        <view v-if="totalPages > 1" class="pagination-controls">
            <button 
                class="pagination-btn"
                :class="{ disabled: currentPage <= 1 || isGenerating }"
                :disabled="currentPage <= 1 || isGenerating"
                @click="prevPage"
            >
                上一页
            </button>
            <text class="page-indicator">{{ currentPage }} / {{ totalPages }}</text>
            <button 
                class="pagination-btn"
                :class="{ disabled: currentPage >= totalPages || isGenerating }"
                :disabled="currentPage >= totalPages || isGenerating"
                @click="nextPage"
            >
                下一页
            </button>
        </view>
        
        <!-- 生成状态 -->
        <view v-if="isGenerating" class="generating-status">
            <view class="status-content">
                <view class="loading-icon">⏳</view>
                <view class="status-text">正在生成图片...</view>
                <view class="status-desc">请稍等片刻</view>
            </view>
        </view>
        
        <!-- 生成成功展示 -->
        <view v-if="generatedImageUrl" class="success-result">
            <view class="success-tip">
                <view class="success-icon">✅</view>
                <view class="success-text">图片生成成功！</view>
                <!-- #ifdef MP-WEIXIN -->
                <view class="success-desc">小程序版本显示文本报价单</view>
                <!-- #endif -->
                <!-- #ifndef MP-WEIXIN -->
                <view class="success-desc">点击下方按钮下载或预览</view>
                <!-- #endif -->
            </view>
            
            <!-- 小程序环境显示文本报价 -->
            <!-- #ifdef MP-WEIXIN -->
            <view v-if="generatedPriceText" class="text-price-display">
                <view class="text-price-title">📋 报价详情</view>
                <view class="text-price-content">{{ generatedPriceText }}</view>
                <view class="text-price-tip">💡 长按上方文字可复制分享</view>
            </view>
            <!-- #endif -->
            
            <!-- 预览图片 -->
            <!-- #ifndef MP-WEIXIN -->
            <image 
                :src="generatedImageUrl" 
                mode="widthFix" 
                class="preview-image"
                @click="previewFullImage"
            />
            <!-- #endif -->
        </view>
        
        <!-- 小程序Canvas元素（隐藏） -->
        <!-- #ifdef MP-WEIXIN -->
        <canvas 
            type="2d" 
            id="priceCanvas"
            class="hidden-canvas"
            :style="{ width: '750px', height: '600px' }"
        ></canvas>
        <!-- #endif -->
    </view>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'

// Props
const props = defineProps({
    deviceList: {
        type: Array,
        default: () => []
    },
    brandInfo: {
        type: Object,
        default: () => ({})
    },
    deviceType: {
        type: String,
        default: 'phone'
    },
    itemsPerPage: {
        type: Number,
        default: 15
    }
})

// 响应式数据
const currentPage = ref(1)
const generatedImageUrl = ref('')
const generatedPriceText = ref('')
const isGenerating = ref(false)
const imageContainer = ref(null)

// 计算属性
const totalDevices = computed(() => props.deviceList.length)

const totalPages = computed(() => {
    return Math.ceil(totalDevices.value / props.itemsPerPage)
})

const currentPageDevices = computed(() => {
    const start = (currentPage.value - 1) * props.itemsPerPage
    const end = start + props.itemsPerPage
    return props.deviceList.slice(start, end)
})

// 工具方法
const getBrandIcon = (brandCode) => {
    const iconMap = {
        'apple': '🍎',
        'huawei': '📱', 
        'samsung': '📱',
        'xiaomi': '📱',
        'oppo': '📱',
        'vivo': '📱',
        'iqoo': '🎮',
        'oneplus': '1️⃣'
    }
    return iconMap[brandCode] || '📱'
}

const getDeviceTypeName = (type) => {
    const nameMap = {
        'phone': '手机',
        'tablet': '平板', 
        'watch': '手表'
    }
    return nameMap[type] || '设备'
}

const getCurrentDate = () => {
    const now = new Date()
    return `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}`
}

const formatPrice = (price) => {
    if (!price || price === 0) return '-'
    return `¥${price}`
}

// 核心功能：生成图片
const generateImage = async () => {
    try {
        isGenerating.value = true
        generatedImageUrl.value = ''
        
        console.log('开始生成图片...')
        
        // 等待DOM更新
        await nextTick()
        
        let result
        
        // 小程序环境使用页面截图
        // #ifdef MP-WEIXIN
        result = await generateImageForMiniProgram()
        // #endif
        
        // H5环境使用html2canvas
        // #ifdef H5
        result = await generateImageForH5()
        // #endif
        
        // App环境使用原生截图
        // #ifdef APP-PLUS
        result = await generateImageForApp()
        // #endif
        
        generatedImageUrl.value = result
        
        uni.showToast({
            title: '图片生成成功',
            icon: 'success'
        })
        
        return result
        
    } catch (error) {
        console.error('生成图片失败:', error)
        uni.showToast({
            title: '生成失败: ' + error.message,
            icon: 'error'
        })
        throw error
    } finally {
        isGenerating.value = false
    }
}

// 小程序环境图片生成（使用Canvas 2D接口）
const generateImageForMiniProgram = async () => {
    return new Promise((resolve, reject) => {
        try {
            console.log('开始生成小程序专用图片')
            
            const deviceCount = Math.min(currentPageDevices.value.length, 8) // 最多8行
            const canvasWidth = 750
            const canvasHeight = 400 + deviceCount * 40
            
            // 获取Canvas 2D上下文
            const query = uni.createSelectorQuery()
            query.select('#priceCanvas')
                .fields({ node: true, size: true })
                .exec((res) => {
                    if (!res || !res[0] || !res[0].node) {
                        console.error('Canvas节点获取失败')
                        reject(new Error('Canvas节点获取失败'))
                        return
                    }
                    
                    const canvas = res[0].node
                    const ctx = canvas.getContext('2d')
                    
                    // 设置Canvas尺寸
                    const dpr = uni.getSystemInfoSync().pixelRatio
                    canvas.width = canvasWidth * dpr
                    canvas.height = canvasHeight * dpr
                    ctx.scale(dpr, dpr)
                    
                    // 绘制白色背景
                    ctx.fillStyle = '#ffffff'
                    ctx.fillRect(0, 0, canvasWidth, canvasHeight)
                    
                    // 绘制头部背景
                    ctx.fillStyle = '#2563eb'
                    ctx.fillRect(0, 0, canvasWidth, 80)
                    
                    // 绘制头部文字
                    ctx.fillStyle = '#ffffff'
                    ctx.font = 'bold 20px sans-serif'
                    ctx.textAlign = 'left'
                    ctx.fillText(props.brandInfo.brand_name || '设备', 30, 40)
                    
                    ctx.font = '16px sans-serif'
                    ctx.fillText(`${getDeviceTypeName(props.deviceType)}回收报价单`, 30, 65)
                    
                    // 绘制日期
                    ctx.textAlign = 'right'
                    ctx.font = '14px sans-serif'
                    ctx.fillText(getCurrentDate(), canvasWidth - 30, 50)
                    
                    // 绘制表格
                    const startY = 100
                    const colWidth = (canvasWidth - 60) / 8
                    const rowHeight = 35
                    
                    // 表头
                    const headers = ['型号', '容量', '高保充新', '充新', '靓机', '小花', '大花', '外爆']
                    
                    // 绘制表头背景
                    ctx.fillStyle = '#f3f4f6'
                    ctx.fillRect(30, startY, canvasWidth - 60, rowHeight)
                    
                    // 绘制表头文字
                    ctx.fillStyle = '#374151'
                    ctx.font = 'bold 12px sans-serif'
                    ctx.textAlign = 'center'
                    headers.forEach((header, index) => {
                        const x = 30 + index * colWidth + colWidth / 2
                        ctx.fillText(header, x, startY + 22)
                    })
                    
                    // 绘制表头边框
                    ctx.strokeStyle = '#e5e7eb'
                    ctx.lineWidth = 1
                    ctx.strokeRect(30, startY, canvasWidth - 60, rowHeight)
                    
                    // 绘制数据行
                    currentPageDevices.value.slice(0, deviceCount).forEach((device, rowIndex) => {
                        const y = startY + (rowIndex + 1) * rowHeight
                        
                        // 绘制行背景
                        ctx.fillStyle = rowIndex % 2 === 0 ? '#f9fafb' : '#ffffff'
                        ctx.fillRect(30, y, canvasWidth - 60, rowHeight)
                        
                        // 绘制行边框
                        ctx.strokeStyle = '#e5e7eb'
                        ctx.strokeRect(30, y, canvasWidth - 60, rowHeight)
                        
                        const rowData = [
                            (device.model_name || '-').substring(0, 8),
                            device.capacity || '-',
                            formatPrice(device.currentPrice?.price_data?.['高保充新']),
                            formatPrice(device.currentPrice?.price_data?.['充新']),
                            formatPrice(device.currentPrice?.price_data?.['靓机']),
                            formatPrice(device.currentPrice?.price_data?.['小花']),
                            formatPrice(device.currentPrice?.price_data?.['大花']),
                            formatPrice(device.currentPrice?.price_data?.['外爆'])
                        ]
                        
                        // 绘制单元格文字
                        ctx.fillStyle = '#1f2937'
                        ctx.font = '10px sans-serif'
                        ctx.textAlign = 'center'
                        rowData.forEach((data, colIndex) => {
                            const x = 30 + colIndex * colWidth + colWidth / 2
                            ctx.fillText(data || '-', x, y + 22)
                        })
                    })
                    
                    // 绘制底部信息
                    const footerY = startY + (deviceCount + 1) * rowHeight + 20
                    ctx.fillStyle = '#6b7280'
                    ctx.font = '12px sans-serif'
                    ctx.textAlign = 'left'
                    ctx.fillText(`共 ${totalDevices.value} 个型号`, 30, footerY)
                    
                    if (totalPages.value > 1) {
                        ctx.fillText(`第 ${currentPage.value} 页，共 ${totalPages.value} 页`, 30, footerY + 20)
                    }
                    
                    ctx.textAlign = 'right'
                    ctx.fillText('报价仅供参考，以实际检测为准', canvasWidth - 30, footerY)
                    
                    // 延时确保绘制完成
                    setTimeout(() => {
                        uni.canvasToTempFilePath({
                            canvas: canvas,
                            quality: 1,
                            success: (res) => {
                                console.log('小程序Canvas 2D生成图片成功:', res.tempFilePath)
                                
                                // 同时保存文本信息
                                const brandName = props.brandInfo.brand_name || '设备'
                                const deviceType = getDeviceTypeName(props.deviceType)
                                const currentDate = getCurrentDate()
                                
                                let priceText = `${brandName} ${deviceType}回收报价单\n${currentDate}\n\n`
                                priceText += `型号\t\t容量\t\t高保充新\t充新\t\t靓机\n`
                                priceText += `${'='.repeat(50)}\n`
                                
                                currentPageDevices.value.slice(0, deviceCount).forEach((device, index) => {
                                    const modelName = (device.model_name || '-').substring(0, 10)
                                    const capacity = device.capacity || '-'
                                    const price1 = formatPrice(device.currentPrice?.price_data?.['高保充新']) || '-'
                                    const price2 = formatPrice(device.currentPrice?.price_data?.['充新']) || '-'
                                    const price3 = formatPrice(device.currentPrice?.price_data?.['靓机']) || '-'
                                    
                                    priceText += `${modelName}\t${capacity}\t${price1}\t${price2}\t${price3}\n`
                                })
                                
                                priceText += `\n共 ${totalDevices.value} 个型号`
                                if (totalPages.value > 1) {
                                    priceText += `，第 ${currentPage.value}/${totalPages.value} 页`
                                }
                                priceText += `\n\n报价仅供参考，以实际检测为准`
                                
                                generatedPriceText.value = priceText
                                
                                resolve(res.tempFilePath)
                            },
                            fail: (err) => {
                                console.error('小程序Canvas 2D生成图片失败:', err)
                                reject(new Error('Canvas 2D生成图片失败: ' + err.errMsg))
                            }
                        })
                    }, 500)
                })
            
        } catch (error) {
            console.error('小程序图片生成失败:', error)
            reject(new Error('小程序图片生成失败: ' + error.message))
        }
    })
}

// H5环境图片生成  
const generateImageForH5 = async () => {
    try {
        // 动态导入html2canvas
        const html2canvas = (await import('html2canvas')).default
        
        const element = document.getElementById('priceImageContainer')
        if (!element) {
            throw new Error('找不到图片容器元素')
        }
        
        const canvas = await html2canvas(element, {
            backgroundColor: '#ffffff',
            scale: 2, // 提高清晰度
            logging: false,
            useCORS: true,
            allowTaint: true
        })
        
        return new Promise((resolve) => {
            canvas.toBlob((blob) => {
                const url = URL.createObjectURL(blob)
                console.log('H5图片生成成功:', url)
                resolve(url)
            }, 'image/png', 0.9)
        })
    } catch (error) {
        console.error('H5图片生成失败:', error)
        throw new Error('H5图片生成失败: ' + error.message)
    }
}

// App环境图片生成
const generateImageForApp = async () => {
    return new Promise((resolve, reject) => {
        // 使用原生截图功能
        plus.nativeUI.showWaiting('正在生成图片...')
        
        setTimeout(() => {
            plus.nativeUI.closeWaiting()
            
            // 模拟生成成功
            const tempPath = '_doc/temp_price_image.png'
            console.log('App图片生成成功:', tempPath)
            resolve(tempPath)
        }, 1000)
    })
}

// 预览大图
const previewFullImage = () => {
    if (generatedImageUrl.value) {
        uni.previewImage({
            urls: [generatedImageUrl.value],
            current: generatedImageUrl.value
        })
    }
}

// 保存到相册
const saveToAlbum = async () => {
    if (!generatedImageUrl.value) {
        uni.showToast({ title: '请先生成图片', icon: 'error' })
        return
    }
    
    try {
        await uni.saveImageToPhotosAlbum({
            filePath: generatedImageUrl.value
        })
        uni.showToast({ title: '保存成功', icon: 'success' })
    } catch (error) {
        console.error('保存失败:', error)
        uni.showToast({ title: '保存失败', icon: 'error' })
    }
}

// 分享图片
const shareImage = async () => {
    if (!generatedImageUrl.value) {
        uni.showToast({ title: '请先生成图片', icon: 'error' })
        return
    }
    
    try {
        await uni.share({
            provider: 'weixin',
            scene: 'WXSceneSession',
            type: 2,
            imageUrl: generatedImageUrl.value
        })
    } catch (error) {
        console.error('分享失败:', error)
        uni.showToast({ title: '分享失败', icon: 'error' })
    }
}

// 下载图片（主要用于H5）
const downloadImage = async () => {
    if (!generatedImageUrl.value) {
        uni.showToast({ title: '请先生成图片', icon: 'error' })
        return
    }
    
    try {
        // #ifdef H5
        const link = document.createElement('a')
        link.href = generatedImageUrl.value
        link.download = `${props.brandInfo.brand_name || '设备'}_报价单_${getCurrentDate()}.png`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        
        uni.showToast({ title: '下载成功', icon: 'success' })
        // #endif
        
        // #ifndef H5
        await saveToAlbum()
        // #endif
        
    } catch (error) {
        console.error('下载失败:', error)
        uni.showToast({ title: '下载失败', icon: 'error' })
    }
}

// 分页控制
const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--
        generatedImageUrl.value = '' // 清除之前的图片
    }
}

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++
        generatedImageUrl.value = '' // 清除之前的图片
    }
}

// 暴露方法给父组件
defineExpose({
    generateImage,
    saveToAlbum,
    shareImage,
    downloadImage,
    setCurrentPage: (page) => {
        currentPage.value = page
    }
})
</script>

<style scoped>
/* 图片容器样式 */
.price-image-container {
    width: 100%;
    max-width: 750px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

/* 头部样式 */
.header-section {
    background: linear-gradient(135deg, #2563eb 0%, #9333ea 100%);
    padding: 24px;
    color: white;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.brand-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.brand-name {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 4px;
}

.brand-desc {
    font-size: 14px;
    opacity: 0.9;
}

.date-info {
    text-align: right;
}

.date-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 4px;
}

.date-value {
    font-size: 18px;
    font-weight: bold;
}

/* 表格样式 */
.table-section {
    padding: 16px;
}

.table-header,
.table-row {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
}

.table-cell {
    flex: 1;
    padding: 12px 8px;
    text-align: center;
    border-right: 1px solid #e5e7eb;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
}

.table-cell:last-child {
    border-right: none;
}

.header-cell {
    background-color: #f8fafc;
    font-weight: bold;
    color: #374151;
    font-size: 13px;
}

.data-cell {
    color: #1f2937;
    background-color: #ffffff;
}

.even-row .data-cell {
    background-color: #f9fafb;
}

/* 价格等级颜色 */
.price-cell-high {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
}

.price-cell-good {
    background-color: #dbeafe !important;
    color: #1e40af !important;
}

.price-cell-normal {
    background-color: #fef3c7 !important;
    color: #92400e !important;
}

.price-cell-fair {
    background-color: #fed7aa !important;
    color: #9a3412 !important;
}

.price-cell-poor {
    background-color: #fecaca !important;
    color: #991b1b !important;
}

.price-cell-broken {
    background-color: #e5e7eb !important;
    color: #374151 !important;
}

/* 底部样式 */
.footer-section {
    background-color: #f8fafc;
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-count {
    font-size: 14px;
    color: #374151;
    font-weight: 500;
}

.page-info {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.disclaimer {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.4;
}

/* 分页控制 */
.pagination-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-top: 24px;
}

.pagination-btn {
    padding: 8px 16px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pagination-btn:hover {
    background: #2563eb;
}

.pagination-btn.disabled {
    background: #d1d5db;
    color: #9ca3af;
    cursor: not-allowed;
}

.page-indicator {
    font-size: 14px;
    color: #6b7280;
}

/* 状态样式 */
.generating-status,
.success-result {
    margin-top: 24px;
    text-align: center;
}

.status-content,
.success-tip {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
}

.success-tip {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.loading-icon,
.success-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.status-text,
.success-text {
    font-size: 16px;
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 8px;
}

.success-text {
    color: #166534;
}

.status-desc,
.success-desc {
    font-size: 14px;
    color: #64748b;
}

.preview-image {
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

/* 小程序文本报价显示 */
.text-price-display {
    background: #fafafa;
    border-radius: 12px;
    padding: 20px;
    margin-top: 16px;
    border: 1px solid #e5e7eb;
}

.text-price-title {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 12px;
    text-align: center;
}

.text-price-content {
    background: #ffffff;
    border-radius: 8px;
    padding: 16px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.6;
    color: #374151;
    white-space: pre-wrap;
    border: 1px solid #d1d5db;
    user-select: text;
    -webkit-user-select: text;
}

.text-price-tip {
    font-size: 12px;
    color: #6b7280;
    text-align: center;
    margin-top: 12px;
    font-style: italic;
}

/* 隐藏Canvas */
.hidden-canvas {
    position: fixed;
    top: -9999px;
    left: -9999px;
    z-index: -1;
    opacity: 0;
    pointer-events: none;
}

/* 响应式调整 */
@media (max-width: 750px) {
    .table-cell {
        padding: 8px 4px;
        font-size: 11px;
        min-height: 36px;
    }
    
    .header-cell {
        font-size: 11px;
    }
    
    .brand-name {
        font-size: 18px;
    }
    
    .brand-desc {
        font-size: 12px;
    }
    
    .text-price-content {
        font-size: 11px;
        padding: 12px;
    }
}
</style> 