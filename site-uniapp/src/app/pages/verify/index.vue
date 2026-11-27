<template>
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] overflow-hidden" :style="themeColor()">
        <!-- #ifdef H5  -->
        <view v-if="refuseCamera">
            <view class="scan-container" v-if="isshow">
                <!-- 扫码区域 -->
                <view id="qr-reader" style="width: 100%;"></view>
                <!-- 扫描框UI -->
                <view class="scan-mask" v-if="isScanning">
                    <view class="scan-box">
                    <view class="scan-line" :style="{top: linePosition+'px'}"></view>
                    </view>
                </view>
            </view>
            <view class="empty-page" v-else>
                <image class="img" :src="img('/addon/mall/site/default_camera.png')" mode="aspectFill" />
                <view class="desc">暂不支持</view>
            </view>
        </view>
        <view v-else class="empty-page">
            <image class="img" :src="img('/addon/mall/site/default_camera.png')" mode="aspectFill" />
            <view class="desc">未检测到可用摄像头</view>
        </view>
        <!-- #endif -->

        <!-- #ifdef MP-WEIXIN -->
         <view v-if="showCamera" class="camera-box">
            <camera device-position="back" flash="auto" mode="scanCode" style="width: 100%; height: 100vh" @error="handleError" @scancode="handleScancode">
                <image  class="image" :src="img('/addon/mall/site/verify/scan-remark.png')" />
				<image  class="scan-bar" :src="img('/addon/mall/site/verify/scan-bar.png')" />
            </camera>
        </view>
        <!-- #endif -->

        <view class="w-full footer">
            <view class="py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full z-10 fixed bottom-0 left-0 right-0 box-border flex items-center justify-between bg-[#fff]">
                <view class="flex-1 primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500 text-center mr-[30rpx]" @click="toLink">手动核销</view>
                <view class="flex-1 primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500 text-center" @click="toRecord">核销记录</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref,  onUnmounted } from 'vue'
import { redirect, img, isWeixinBrowser } from '@/utils/common';
import { getPermission } from '@/utils/permission';
import { Html5Qrcode } from 'html5-qrcode';
import { onShow, onUnload } from '@dcloudio/uni-app';

// 响应式数据
const html5QrCode = ref(null)
const isScanning = ref(false)
const scanResult = ref(null)
const cameraId = ref(null)
const isshow = ref(false)
const linePosition = ref(0)
const showCamera = ref(false)
const refuseCamera = ref(true)

onShow(() => {
    // #ifdef H5
    if(isWeixinBrowser() || location.origin.indexOf('https://') != -1){
        isshow.value = true;
        initScanner();
    }
    // #endif
    // #ifdef MP-WEIXIN
    getPermissionFn()
    // #endif
})


// #ifdef H5
const initScanner = async () => {
  try {
    // 获取摄像头设备列表
    const devices = await Html5Qrcode.getCameras();
    if (devices && devices.length > 0) {
        // 优先选择后置摄像头
        const backCamera = devices.find(device =>
            device.label.toLowerCase().includes('back') || device.facingMode === 'environment'
        );

        // 如果没有找到后置摄像头，则选择前置摄像头
        const frontCamera = devices.find(device =>
            device.label.toLowerCase().includes('front') || device.facingMode === 'user'
        );

        // 根据设备选择相应的摄像头， devices[0] 是第一个摄像头/前置摄像。
        cameraId.value = backCamera ? backCamera.id : frontCamera ? frontCamera.id : devices[1].id;
    } else {
        throw new Error('未检测到可用摄像头');
    }

    startScan();
  } catch (err) {
    refuseCamera.value = false
    console.error('摄像头初始化失败:', err);
  }
}

const startScan = async () => {
  if (isScanning.value) return;

  try {
    html5QrCode.value = new Html5Qrcode("qr-reader");

    const config = {
      facingMode: "environment", // 明确要求后置摄像头
      fps: 20, // 提高帧率，适当提升扫描速度
      // qrbox: { width: 250, height: 250 }, // 扫码框大小
      aspectRatio: 1.777 // 宽高比
    };

    await html5QrCode.value.start(
        cameraId.value,
        config,
        onScanSuccess,
        onScanError
    );

    isScanning.value = true;
    scanResult.value = null;
  } catch (err) {
    console.error('扫码启动失败:', err);
  }
}

const onScanSuccess = (decodedText, decodedResult) => {
    scanResult.value = decodedText;
    stopScan();

    // 处理扫描结果
    handleScanResult(decodedText);
}

const onScanError = (errorMessage) => {
  // 忽略部分非致命错误
  if (!errorMessage.includes('NotFoundException')) {
    console.warn('扫码错误:', errorMessage);
  }
}

const stopScan = () => {
  if (html5QrCode.value && isScanning.value) {
        html5QrCode.value.stop().then(() => {
            isScanning.value = false;
            html5QrCode.value.clear();
        }).catch(err => {
            console.error('扫码停止失败:', err);
        });
    }
}
// #endif

// 扫描结果处理
const handleScanResult = (result) => {
  redirect({url: '/app/pages/verify/verify', param: {code: result}})
}

const showToast = (message) => {
    uni.showToast({
        title: message,
        icon: 'none'
    })
}



onUnmounted(() => {
  // #ifdef H5
  stopScan();
  // #endif
})

onUnload(() => {
    // #ifdef H5
    stopScan();
    // #endif
})

const getPermissionFn = () => {
    getPermission({
        permission: 'camera',
        permissionName: '摄像头',
        success: () => {
            showCamera.value = true
        },
        cancel: () => {
            uni.navigateBack()
        }
    })
}
const  handleScancode = (e: any) => {
    let scanResult = e.detail.result
    redirect({ url: '/app/pages/verify/verify', param: { code: scanResult } })
}   
const handleError = (e: any) => {
    console.error('相机初始化失败', e)
}

const toLink = () => {
   redirect({url: '/app/pages/verify/manual', mode: 'redirectTo'})
}

const toRecord = () => {
   redirect({url: '/app/pages/verify/record', mode: 'redirectTo'})
}
</script>

<style lang="scss" scoped>
#qr-reader {
  height: 100vh;
  overflow: hidden;
}

.result-box {
  margin: 20px;
  padding: 15px;
  background-color: #f5f5f5;
  border-radius: 8px;
  width: 80%;
  text-align: center;
}

.scan-mask {
  position: absolute;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 500px;
  height: calc(100% - 160px);
  display: flex;
  justify-content: center;
  align-items: center;
  pointer-events: none;
}

.scan-box {
  width: 250px;
  height: 250px;
  border: 2px solid rgba(0, 255, 0, 0.5);
  position: relative;
  overflow: hidden;
}

.scan-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(to right, transparent, #00ff00, transparent);
  animation: scanLine 3s linear infinite;
}

@keyframes scanLine {
  0% { top: 0; }
  100% { top: 100%; }
}


.camera-box{
    width: 100%;
    height: 100vh;
    text-align: center;
    color: white;
    position: fixed;
    .image {
        width: 100%;
        height: 100vh;
        z-index: 1;
    }
    .scan-bar {
        position: absolute;
        left: 50%;
        top: 23vh;
        width: 428rpx;
        height: 12rpx;
        transform: translateX(-50%);
        z-index: 1;
        animation: scan 1.6s linear infinite;
    }
    @keyframes scan {
        0% {
            top: 23vh;
            opacity: 0;
        }

        15% {
            opacity: 1;
        }

        90% {
            opacity: 1;
        }

        100% {
            top: 55.5vh;
            opacity: 0;
        }
    }
}

.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
</style>