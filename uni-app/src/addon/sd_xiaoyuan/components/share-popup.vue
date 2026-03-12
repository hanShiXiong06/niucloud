<template>
    <view class="">
        <up-popup class="share-popup" :show="showShare" mode="bottom" border-radius="14" :closeable="true"
            @close="tohide" :safe-area-inset-bottom="true" :mask-close-able="false">
            <view class="share-title">分享至</view>
            <view class="share-tab">
                <!-- #ifdef MP-WEIXIN-->
                <button open-type="share" class="share-item" hover-class="none">
                    <view class="share-icon-wrap">
                        <u-icon name="weixin-fill" size="40" color="#07c160"></u-icon>
                    </view>
                    <view class="share-text">转发给好友</view>
                </button>
                <!-- #endif -->
                <!-- #ifdef H5 || APP-PLUS -->
                <view class="share-item" @tap="shareWx">
                    <view class="share-icon-wrap">
                        <u-icon name="weixin-fill" size="40" color="#07c160"></u-icon>
                    </view>
                    <view class="share-text">转发给好友</view>
                </view>
                <!-- #endif -->
            </view>
            <view class="cancel-btn" @tap="tohide">取消</view>
        </up-popup>
        
        <u-popup class="share-poster" :show="showPoster" mode="center" bgColor="transparent" :closeable="true"
            @close="showPoster=false">
            <view class="poster-wrap">
                <!-- #ifndef H5 -->
                <image v-if="posterData" style="width: 600rpx;" mode="widthFix" :src="img(posterData)"></image>
                <!-- #endif -->
                <!-- #ifdef H5 -->
                <img v-if="posterData" style="width: 600rpx;" :src="img(posterData)" />
                <!-- #endif -->
                <view class="save-btn" @tap="savePoster">
                    <!-- #ifndef H5 -->
                    保存图片到相册
                    <!-- #endif -->
                    <!-- #ifdef H5 -->
                    长按保存图片到相册
                    <!-- #endif -->
                </view>
            </view>
        </u-popup>
        
        <!-- #ifdef H5 -->
        <u-popup :custom-style="{'background': 'none'}" class="share-tips" :show="showTips" mode="top">
            <view class="tips-content" @click="showTips=false">
                <view class="tips-arrow">↗</view>
                <view class="tips-text">
                    <view class="tips-title">立即分享给好友吧</view>
                    <view class="tips-desc">点击屏幕右上角将本页面分享给好友</view>
                </view>
            </view>
        </u-popup>
        <!-- #endif -->
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { img } from '@/utils/common'

const props = defineProps({
    showShare: {
        type: Boolean,
        default: false,
    },
    shareId: {
        type: [String, Number],
        default: '',
    },
    shareTitle: {
        type: String,
        default: '',
    },
    shareContent: {
        type: String,
        default: '',
    },
    shareImage: {
        type: String,
        default: '',
    },
    shareUrl: {
        type: String,
        default: '',
    },
})

const posterData = ref('')
const showPoster = ref(false)
const showTips = ref(false)

const emit = defineEmits(["hide", "poster"])

function tohide() {
    emit("hide")
}

async function getPoster() {
    uni.showLoading({ title: '生成中...' })
    
    // 生成简单的海报
    const canvas = uni.createCanvasContext('posterCanvas')
    const width = 600
    const height = 800
    
    // 背景
    canvas.setFillStyle('#ffffff')
    canvas.fillRect(0, 0, width, height)
    
    // 标题
    canvas.setFillStyle('#333333')
    canvas.setFontSize(32)
    const title = props.shareTitle || '校园树洞'
    canvas.fillText(title.substring(0, 15), 30, 60)
    
    // 内容
    canvas.setFillStyle('#666666')
    canvas.setFontSize(24)
    const content = props.shareContent || ''
    const lines = content.match(/.{1,20}/g) || []
    lines.slice(0, 10).forEach((line: string, index: number) => {
        canvas.fillText(line, 30, 120 + index * 40)
    })
    
    // 底部提示
    canvas.setFillStyle('#999999')
    canvas.setFontSize(20)
    canvas.fillText('长按识别二维码查看详情', 30, height - 60)
    
    canvas.draw(false, () => {
        uni.canvasToTempFilePath({
            canvasId: 'posterCanvas',
            success: (res) => {
                posterData.value = res.tempFilePath
                uni.hideLoading()
                showPoster.value = true
                emit("hide")
            },
            fail: () => {
                uni.hideLoading()
                // 简单处理：直接复制链接
                copyLink()
            }
        })
    })
}

function shareWx() {
    // #ifdef H5
    showTips.value = true
    emit("hide")
    // #endif
    // #ifdef APP-PLUS
    // APP环境下使用uni.share
    if (typeof uni.share === 'function') {
        uni.share({
            provider: 'weixin',
            scene: 'WXSceneSession',
            type: 0,
            href: props.shareUrl,
            title: props.shareTitle,
            summary: props.shareContent,
            imageUrl: props.shareImage,
            success: () => {
                uni.showToast({ title: '分享成功', icon: 'success' })
            },
            fail: () => {
                copyLink()
            },
        })
    } else {
        copyLink()
    }
    // #endif
    // #ifdef MP-WEIXIN
    // 小程序环境下提示使用按钮分享
    emit("hide")
    // #endif
}

function copyLink() {
    const url = props.shareUrl || window?.location?.href || ''
    uni.setClipboardData({
        data: url,
        success: () => {
            uni.showToast({ title: '链接已复制', icon: 'success' })
            emit("hide")
        }
    })
}

async function savePoster() {
    // #ifdef H5
    if (posterData.value) {
        const oA = document.createElement("a")
        oA.download = 'poster.png'
        oA.href = posterData.value
        document.body.appendChild(oA)
        oA.click()
        oA.remove()
        uni.showToast({ title: "图片已保存", icon: "success" })
        emit("hide")
    }
    // #endif

    // #ifdef MP
    if (posterData.value) {
        uni.saveImageToPhotosAlbum({
            filePath: posterData.value,
            success: () => {
                uni.showToast({ title: "图片已保存到相册", icon: "success" })
                emit("hide")
            },
            fail: (err) => {
                if (err.errMsg.includes('auth')) {
                    uni.showModal({
                        title: '提示',
                        content: '需要您授权保存相册',
                        success: (res) => {
                            if (res.confirm) {
                                uni.openSetting()
                            }
                        }
                    })
                }
            }
        })
    }
    // #endif
}
</script>

<style lang="scss" scoped>
.share-title {
    text-align: center;
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    padding: 30rpx 0;
}

.share-tab {
    display: flex;
    justify-content: space-around;
    padding: 30rpx 20rpx 40rpx;
}

.share-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    
    &::after {
        border: none;
    }
    
    .share-icon-wrap {
        width: 100rpx;
        height: 100rpx;
        border-radius: 50%;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16rpx;
    }
    
    .share-text {
        font-size: 24rpx;
        color: #666;
    }
}

.cancel-btn {
    text-align: center;
    padding: 30rpx 0;
    border-top: 20rpx solid #f5f5f5;
    font-size: 30rpx;
    color: #666;
}

.poster-wrap {
    background: #fff;
    border-radius: 20rpx;
    overflow: hidden;
    
    .save-btn {
        background: #000;
        color: #fae301;
        text-align: center;
        padding: 24rpx 0;
        font-size: 28rpx;
    }
}

.tips-content {
    padding: 30rpx;
    
    .tips-arrow {
        font-size: 80rpx;
        color: #fff;
        text-align: right;
        padding-right: 60rpx;
    }
    
    .tips-text {
        text-align: center;
        color: #fff;
        margin-top: 40rpx;
        
        .tips-title {
            font-size: 36rpx;
            font-weight: bold;
            margin-bottom: 16rpx;
        }
        
        .tips-desc {
            font-size: 26rpx;
            opacity: 0.8;
        }
    }
}
</style>
