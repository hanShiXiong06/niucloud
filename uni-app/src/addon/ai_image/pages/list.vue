<template>
    <view class="min-h-screen relative" :style="themeColor()">
        <!-- 背景渐变光晕 -->
        <!-- 背景层 -->
        <view class="bg-layer"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #f8fafc 0%, #f0f4ff 30%, #e0e7ff 50%, #f0f4ff 70%, #f8fafc 100%);">
        </view>
        <!-- 顶部光晕 -->
        <view class="glow-layer-1"
            style="position: fixed; top: -150rpx; left: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>
        <!-- 底部光晕 -->
        <view class="glow-layer-2"
            style="position: fixed; bottom: -150rpx; right: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>

        <view class="relative p-4" style="position: relative; z-index: 10;">
            <!-- #ifdef MP -->
            <top-tabbar :data="topTabbarData" :customBack="goBack" />
            <!-- #endif -->
            <mescroll-body top="28" ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getImageListFn">
                <!-- 状态切换 -->
                <view class="mb-2 rounded-xl overflow-hidden px-4 py-3"
                    style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.8) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                    <view class="flex items-center gap-3 justify-center">
                        <view v-for="(item, index) in stateList" :key="index" role="button" tabindex="0"
                            @click="onItemClick(item)" @keyup.enter.space="onItemClick(item)"
                            class="relative h-[64rpx] px-4 rounded-full inline-flex items-center justify-center text-[28rpx] font-semibold transition-all duration-200 active:scale-95 border cursor-pointer"
                            :style="state === item.value
                                ? 'background: linear-gradient(135deg, #3b82f6, #2563eb); color:#ffffff; border-color: transparent; box-shadow: 0 4rpx 16rpx rgba(59, 130, 246, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2);'
                                : 'background: rgba(255, 255, 255, 0.8); color:#64748b; border-color: rgba(59, 130, 246, 0.25); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.08);'">
                            <text>{{ item.name }}</text>
                        </view>
                    </view>
                </view>

                <!-- 提示卡片 -->
                <view class="mb-2 rounded-xl overflow-hidden"
                    style="background: linear-gradient(135deg, rgba(239, 246, 255, 0.6) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(245, 247, 250, 0.8) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                    <view class="px-4 py-3 flex items-center gap-2"
                        style="background: rgba(59, 130, 246, 0.08); border-radius: 12rpx; margin: 12rpx;">
                        <text class="text-base font-bold" style="color: #3b82f6;">!</text>
                        <text class="text-sm" style="color: #475569;">生成图像24小时有效，请及时保存</text>
                    </view>
                </view>

                <!-- 图片列表 -->
                <view class="flex flex-wrap" style="margin: 0 -6rpx;">
                    <view v-for="(item, index) in listData" :key="index" class="rounded-xl overflow-hidden"
                        :style="`width: calc((100% - 12rpx) / 2); margin-right: ${index % 2 === 0 ? '12rpx' : '0'}; margin-bottom: 12rpx; box-sizing: border-box; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);`">
                        <!-- 图片显示区域 -->
                        <view class="p-3">
                            <view v-if="getDisplayImage(item)" class="rounded-lg overflow-hidden"
                                style="width: 100%; aspect-ratio: 1; border: 1px solid rgba(59, 130, 246, 0.1); background: rgba(248, 250, 252, 0.5);">
                                <image :src="img(getDisplayImage(item)!)" mode="aspectFill"
                                    style="width: 100%; height: 100%;" @click="openImagePopup(item)" />
                            </view>
                            <!-- 如果没有图片 -->
                            <view v-else class="rounded-lg flex items-center justify-center"
                                style="width: 100%; aspect-ratio: 1; border: 1px solid rgba(59, 130, 246, 0.1); background: rgba(248, 250, 252, 0.5);">
                                <text class="text-xs" style="color: #94a3b8;">暂无图片</text>
                            </view>
                        </view>
                        <!-- 信息区域 -->
                        <view class="px-3 pb-3">
                            <view class="flex items-center justify-between mb-2">
                                <view class="flex items-center gap-1.5 flex-1 min-w-0">
                                    <view class="px-1.5 py-0.5 rounded text-[20rpx] font-medium flex-shrink-0" :style="item.status === 0
                                        ? 'background: rgba(59, 130, 246, 0.1); color: #3b82f6;'
                                        : item.status === 1
                                            ? 'background: rgba(34, 197, 94, 0.1); color: #22c55e;'
                                            : 'background: rgba(239, 68, 68, 0.1); color: #ef4444;'">
                                        {{ item.status === 0 ? '生成中' : item.status === 1 ? '已完成' : '失败' }}
                                    </view>
                                    <view class="text-[20rpx] truncate" style="color: #94a3b8;">
                                        {{ item.create_time }}
                                    </view>
                                </view>
                            </view>
                            <view class="text-xs font-medium mb-2" style="color: #64748b;">
                                {{ item.point }}{{ config?.alias_name || '' }}
                            </view>
                            <!-- 按钮组 -->
                            <view class="flex justify-between gap-1.5">
                                <!-- 查看按钮（有图片时显示） -->
                                <view v-if="getDisplayImage(item)" @click="openImagePopup(item)"
                                    class="px-2.5 py-1.5 rounded-lg cursor-pointer active:opacity-80 flex items-center gap-1"
                                    style="background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(59, 130, 246, 0.2); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                    <u-icon name="eye" size="14" color="#3b82f6" />
                                    <text class="text-xs font-medium" style="color: #3b82f6;">查看</text>
                                </view>
                                <!-- 下载按钮（仅生成成功时显示） -->
                                <view v-if="item.status === 1 && item.images" @click="downloadImage(item.images)"
                                    class="px-2.5 py-1.5 rounded-lg cursor-pointer active:opacity-80 flex items-center gap-1"
                                    style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.3);">
                                    <u-icon name="download" size="14" color="#ffffff" />
                                    <text class="text-xs text-white font-medium">下载</text>
                                </view>
                                <!-- 刷新按钮（仅生成中时显示） -->
                                <view v-if="item.status === 0" @click="refreshImageStatus(item, index)"
                                    class="px-2.5 py-1.5 rounded-lg cursor-pointer active:opacity-80 flex items-center gap-1"
                                    :class="refreshingIndex === index ? 'opacity-60' : ''"
                                    style="background: linear-gradient(135deg, #06b6d4, #0ea5e9); box-shadow: 0 2rpx 8rpx rgba(6, 182, 212, 0.3);">
                                    <text class="text-xs text-white font-medium">{{ refreshingIndex === index ? '刷新中' :
                                        '刷新' }}</text>
                                </view>
                            </view>
                        </view>
                    </view>
                </view>

                <!-- 图片查看弹窗 -->
                <u-popup :show="imagePopupShow" mode="bottom" :round="20" @close="closeImagePopup">
                    <view class="w-screen max-w-[600rpx] mx-auto rounded-t-2xl overflow-hidden"
                        style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px);">
                        <view class="px-4 py-3 flex items-center justify-between border-b"
                            style="border-color: rgba(59, 130, 246, 0.1);">
                            <view class="text-base font-bold" style="color: #1e293b;">查看图片</view>
                            <u-icon name="close" size="20" color="#64748b" @click="closeImagePopup" />
                        </view>
                        <view class="p-4" v-if="currentViewItem">
                            <!-- 生成的图片 -->
                            <view class="mb-4">
                                <view class="mb-3" v-if="currentViewItem.images">
                                    <image :src="img(currentViewItem.images)" mode="widthFix"
                                        style="width: 100%; border-radius: 12rpx;"
                                        @click="previewSingleImage(currentViewItem.images)" />
                                </view>
                                <!-- 按钮组 -->
                                <view class="flex items-center gap-2">
                                    <view v-if="currentViewItem.status === 1"
                                        @click="downloadImage(currentViewItem.images)"
                                        class="flex-1 px-3 py-2 rounded-lg cursor-pointer active:opacity-80 flex items-center justify-center gap-1.5"
                                        style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.3);">
                                        <u-icon name="download" size="16" color="#ffffff" />
                                        <text class="text-sm text-white font-medium">下载</text>
                                    </view>
                                    <view @click="redirect({ url: '/addon/ai_image/pages/model' })"
                                        class="flex-1 px-3 py-2 rounded-lg cursor-pointer active:opacity-80 flex items-center justify-center gap-1.5"
                                        style="background: linear-gradient(135deg, #06b6d4, #0ea5e9); box-shadow: 0 2rpx 8rpx rgba(6, 182, 212, 0.3);">
                                        <u-icon name="edit-pen" size="16" color="#ffffff" />
                                        <text class="text-sm text-white font-medium">创作</text>
                                    </view>
                                    <view @click="deleteImageFn(currentViewItem.id)"
                                        class="flex-1 px-3 py-2 rounded-lg cursor-pointer active:opacity-80 flex items-center justify-center gap-1.5"
                                        style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 2rpx 8rpx rgba(239, 68, 68, 0.3);">
                                        <u-icon name="trash" size="16" color="#ffffff" />
                                        <text class="text-sm text-white font-medium">删除</text>
                                    </view>
                                </view>
                            </view>
                            <!-- 原始图片 -->
                            <view v-if="currentViewItem.image_urls && currentViewItem.image_urls.length > 0">
                                <view class="text-sm font-medium mb-2" style="color: #64748b;">原始图片</view>
                                <view class="flex flex-wrap" style="gap: 8rpx;">
                                    <view v-for="(imageUrl, imgIndex) in currentViewItem.image_urls" :key="imgIndex"
                                        class="rounded-lg overflow-hidden"
                                        style="width: calc(33.333% - 5.33rpx); aspect-ratio: 1; border: 1px solid rgba(59, 130, 246, 0.1);">
                                        <image :src="img(imageUrl)" mode="aspectFill" style="width: 100%; height: 100%;"
                                            @click="previewImage(currentViewItem.image_urls, imgIndex)" />
                                    </view>
                                </view>
                            </view>
                        </view>
                        <up-safe-bottom></up-safe-bottom>
                    </view>
                </u-popup>

                <!-- 空状态 -->
                <view v-if="listData.length === 0 && !loading" class="flex items-center justify-center mt-44">
                    <view class="flex flex-col items-center">
                        <view class="text-lg font-bold text-center mb-4" style="color: #94a3b8;">还没数据~~~</view>
                        <button hover-class="opacity-90" @click="redirect({ url: '/addon/ai_image/pages/model' })"
                            class="px-8 py-3 rounded-full text-base font-semibold text-white transition active:opacity-90"
                            style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.3);">
                            去创作
                        </button>
                    </view>
                </view>
            </mescroll-body>
            <tabbar name="ai_image" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getImageList, getConfig, getImageInfo, deleteImage } from '@/addon/ai_image/api/aiimage'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom, onLoad } from '@dcloudio/uni-app'
import { img, redirect } from '@/utils/common';

// 自定义返回逻辑（参考 top-tabbar 组件）
const goBack = () => {
    uni.navigateBack()
}


const stateList = ref([
    {
        name: '全部',
        value: '',
    },
    {
        name: '生成中',
        value: 0,
    },
    {
        name: '已完成',
        value: 1,
    },
    {
        name: '失败',
        value: 2,
    }
])
const state = ref('')


import { topTabar } from '@/utils/topTabbar'
/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({
    title: '创作列表', topStatusBar: {
        textColor: '#000000',
    }
})
/********* 自定义头部 - end ***********/
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)

const listData = ref<any[]>([])
let loading = ref<boolean>(false);
const mescrollRef = ref<any>(null);
const refreshingIndex = ref<number | null>(null); // 正在刷新的索引

const getImageListFn = (mescroll: any) => {
    const data = {
        page: mescroll.num || 1,
        limit: mescroll.size || 10,
        status: state.value
    };

    loading.value = true;
    getImageList(data).then((res: any) => {
        loading.value = false;

        if (res.data && res.code === 1) {
            let newArr = res.data.data || [];
            // 设置列表数据
            if (mescroll.num == 1) {
                listData.value = newArr; // 如果是第一页需手动制空列表
            } else {
                listData.value = listData.value.concat(newArr);
            }

            // 结束加载状态
            if (mescroll.endSuccess) {
                mescroll.endSuccess(newArr.length);
            }
        } else {
            // 处理接口返回错误
            if (mescroll.endErr) {
                mescroll.endErr();
            }
            uni.showToast({
                title: res.message || '获取数据失败',
                icon: 'none'
            });
        }
    }).catch((e: any) => {
        console.log('获取订单列表失败:', e);
        loading.value = false;

        if (mescroll.endErr) {
            mescroll.endErr(); // 请求失败, 结束加载
        }

        uni.showToast({
            title: '网络错误，请重试',
            icon: 'none'
        });
    })
}

// 状态切换处理
const onItemClick = (item: any) => {
    // 如果点击的是当前选中的状态，则不处理
    if (state.value === item.value) {
        return
    }

    // 更新状态
    state.value = item.value

    // 重置列表数据
    listData.value = []

    // 重置并重新加载数据
    const mescroll = getMescroll()
    if (mescroll) {
        // 重置到第一页并触发上拉加载
        mescroll.resetUpScroll()
    } else if (mescrollRef.value) {
        // 备用方案：直接使用 ref
        mescrollRef.value.resetUpScroll()
    }
}

// 获取显示的图片（优先显示生成的图片，否则显示原始图片的第一张）
const getDisplayImage = (item: any): string | null => {
    if (item.images) {
        return item.images
    }
    if (item.image_urls && item.image_urls.length > 0) {
        return item.image_urls[0]
    }
    return null
}

// 图片弹窗相关
const imagePopupShow = ref(false)
const currentViewItem = ref<any>(null)

// 打开图片弹窗
const openImagePopup = (item: any) => {
    currentViewItem.value = item
    imagePopupShow.value = true
}

// 关闭图片弹窗
const closeImagePopup = () => {
    imagePopupShow.value = false
    currentViewItem.value = null
}

// 预览图片
const previewImage = (urls: string[], currentIndex: number) => {
    const imageUrls = urls.map(url => img(url))
    uni.previewImage({
        urls: imageUrls,
        current: imageUrls[currentIndex] || imageUrls[0]
    })
}

// 预览单张图片
const previewSingleImage = (url: string) => {
    uni.previewImage({
        urls: [img(url)],
        current: img(url)
    })
}

// 下载图片（区分小程序和H5）
const downloadImage = (imageUrl: string) => {
    const fullUrl = img(imageUrl)

    // #ifdef MP
    // 小程序端：下载并保存到相册
    uni.showLoading({
        title: '下载中...',
        mask: true
    })

    uni.downloadFile({
        url: fullUrl,
        success: (res) => {
            if (res.statusCode === 200) {
                // 保存图片到相册
                uni.saveImageToPhotosAlbum({
                    filePath: res.tempFilePath,
                    success: () => {
                        uni.hideLoading()
                        uni.showToast({
                            title: '保存成功',
                            icon: 'success'
                        })
                    },
                    fail: (err) => {
                        uni.hideLoading()
                        if (err.errMsg.includes('auth deny')) {
                            uni.showModal({
                                title: '提示',
                                content: '需要授权保存图片到相册',
                                showCancel: false
                            })
                        } else {
                            uni.showToast({
                                title: '保存失败',
                                icon: 'none'
                            })
                        }
                    }
                })
            } else {
                uni.hideLoading()
                uni.showToast({
                    title: '下载失败',
                    icon: 'none'
                })
            }
        },
        fail: () => {
            uni.hideLoading()
            uni.showToast({
                title: '下载失败',
                icon: 'none'
            })
        }
    })
    // #endif

    // #ifdef H5
    // H5端：创建临时链接下载
    uni.showLoading({
        title: '下载中...',
        mask: true
    })

    // 创建一个临时的 a 标签来触发下载
    const link = document.createElement('a')
    link.href = fullUrl
    link.download = `ai_image_${Date.now()}.jpg` // 设置下载文件名
    link.style.display = 'none'
    document.body.appendChild(link)

    // 如果图片是跨域的，需要先转换为 blob
    fetch(fullUrl)
        .then(response => response.blob())
        .then(blob => {
            const blobUrl = URL.createObjectURL(blob)
            link.href = blobUrl
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(blobUrl)
            uni.hideLoading()
            uni.showToast({
                title: '下载成功',
                icon: 'success'
            })
        })
        .catch(() => {
            // 如果 fetch 失败，尝试直接下载（可能跨域）
            link.click()
            document.body.removeChild(link)
            uni.hideLoading()
            uni.showToast({
                title: '下载中',
                icon: 'success'
            })
        })
    // #endif
}
const deleteImageFn = (id: any) => {
    uni.showModal({
        title: '提示',
        content: '确定要删除这张图片吗？',
        success: (res) => {
            if (res.confirm) {
                deleteImage(id).then((res: any) => {
                    uni.showToast({
                        title: '删除成功',
                        icon: 'success'
                    })
                    // 关闭弹窗
                    closeImagePopup()
                    // 刷新列表
                    const mescroll = getMescroll()
                    if (mescroll) {
                        mescroll.resetUpScroll()
                    } else if (mescrollRef.value) {
                        mescrollRef.value.resetUpScroll()
                    }
                }).catch((err: any) => {
                    uni.showToast({
                        title: err.message || '删除失败',
                        icon: 'none'
                    })
                })
            }
        }
    })
}
// 刷新图片状态
const refreshImageStatus = async (item: any, index: number) => {
    if (!item.id) {
        uni.showToast({
            title: '无法刷新',
            icon: 'none'
        })
        return
    }

    // 防止重复刷新
    if (refreshingIndex.value === index) {
        return
    }

    refreshingIndex.value = index

    try {
        const res: any = await getImageInfo(item.id)

        if (res.code === 1 && res.data) {
            // 更新列表中的数据（使用响应式更新）
            const updatedItem = res.data
            // 使用 Object.assign 确保响应式更新
            Object.assign(listData.value[index], updatedItem)

            // 如果状态变为已完成或失败，提示用户
            if (updatedItem.status === 1) {
                uni.showToast({
                    title: '生成完成',
                    icon: 'success'
                })
            } else if (updatedItem.status === 2) {
                uni.showToast({
                    title: '生成失败',
                    icon: 'none'
                })
            } else {
                uni.showToast({
                    title: '状态已更新',
                    icon: 'success'
                })
            }
            getImageListFn(getMescroll())
        } else {
            uni.showToast({
                title: res.message || '刷新失败',
                icon: 'none'
            })
        }
    } catch (error: any) {
        console.error('刷新状态失败:', error)
        uni.showToast({
            title: '刷新失败，请重试',
            icon: 'none'
        })
    } finally {
        refreshingIndex.value = null
    }
}

const config = ref<any>(null)
onLoad(() => {
    getConfig().then((res: any) => {
        config.value = res.data
    })
})
</script>
<style lang="scss" scoped>
/* 旋转动画 */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

/* 仅在当前页面的弹窗中允许触摸手势传递到 video 控件（覆盖全局 touch-action:none） */
:deep(.u-popup .u-transition) {
    touch-action: auto !important;
}

/* 一键高清弹窗样式 */
.enhance-popup-content {
    padding: 32rpx;
    background: rgba(11, 20, 42, 0.95);
    backdrop-filter: blur(20rpx);
}

.enhance-popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;
    padding-bottom: 20rpx;
    border-bottom: 1rpx solid rgba(34, 211, 238, 0.2);
}

.enhance-popup-tip {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 16rpx 20rpx;
    margin-bottom: 24rpx;
    background: rgba(34, 211, 238, 0.1);
    border-radius: 12rpx;
    border: 1rpx solid rgba(34, 211, 238, 0.2);
}

.enhance-popup-tip-text {
    font-size: 24rpx;
    color: #93c5fd;
    line-height: 1.6;
}

.enhance-info-card {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 32rpx;
    border: 1rpx solid rgba(34, 211, 238, 0.2);
}

.enhance-info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16rpx 0;

    &:not(:last-child) {
        border-bottom: 1rpx solid rgba(148, 163, 184, 0.1);
    }
}

.enhance-info-label {
    font-size: 28rpx;
    color: #94a3b8;
}

.enhance-info-value {
    font-size: 28rpx;
    font-weight: 600;
    color: #e2e8f0;
}

.enhance-info-value-highlight {
    color: #22d3ee;
    font-size: 32rpx;
}

.enhance-popup-actions {
    display: flex;
    gap: 16rpx;
    margin-top: 24rpx;
}

:deep(.u-button) {
    border-radius: 24rpx !important;
    height: 88rpx !important;
    font-size: 30rpx !important;
    font-weight: 600 !important;
}

/* 统一内容卡片 */
.content-card {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 24rpx;
    padding: 32rpx;
    margin-bottom: 24rpx;
    border: 1rpx solid rgba(34, 211, 238, 0.2);
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10rpx);
}

.tip-section {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 16rpx;
    background: rgba(34, 211, 238, 0.1);
    border-radius: 12rpx;
    border: 1rpx solid rgba(34, 211, 238, 0.2);
}

.tip-highlight {
    color: #22d3ee;
    font-weight: 600;
    font-size: 24rpx;
}

.tip-text {
    color: #93c5fd;
    font-size: 24rpx;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

/* 视频列表容器 */
.video-list-container {
    padding: 0;
}

/* 视频项卡片 */
.video-item-card {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 24rpx;
    padding: 24rpx;
    margin-bottom: 24rpx;
    border: 1rpx solid rgba(34, 211, 238, 0.15);
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.3), 0 0 0 1rpx rgba(34, 211, 238, 0.05);
    backdrop-filter: blur(12rpx);
    -webkit-font-smoothing: antialiased;
    overflow: hidden;
}

/* 视频缩略图 */
.video-thumbnail-wrapper {
    margin-bottom: 20rpx;
}

.video-thumbnail {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 16rpx;
    overflow: hidden;
    background: rgba(15, 23, 42, 0.8);
    border: 1rpx solid rgba(34, 211, 238, 0.1);
    box-shadow: inset 0 2rpx 8rpx rgba(0, 0, 0, 0.4), 0 2rpx 12rpx rgba(0, 0, 0, 0.2);
}

.video-thumbnail-success {
    border-color: rgba(34, 211, 238, 0.3);
    box-shadow: inset 0 2rpx 8rpx rgba(0, 0, 0, 0.4), 0 4rpx 16rpx rgba(34, 211, 238, 0.2);
}

.video-thumbnail-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-thumbnail-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    pointer-events: none;
}

.video-thumbnail-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-play-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(2rpx);
    transition: all 0.3s ease;

    &:active {
        background: rgba(0, 0, 0, 0.5);
    }
}

.video-play-icon-wrapper {
    width: 80rpx;
    height: 80rpx;
    border-radius: 50%;
    background: rgba(34, 211, 238, 0.3);
    border: 2rpx solid rgba(34, 211, 238, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10rpx);
}

.video-loading-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(6rpx);
    z-index: 2;
}

.video-loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16rpx;
}

.video-loading-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-loading-pulse {
    position: absolute;
    width: 80rpx;
    height: 80rpx;
    border-radius: 50%;
    background: rgba(34, 211, 238, 0.3);
    animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
    0% {
        transform: scale(0.8);
        opacity: 1;
    }

    50% {
        transform: scale(1.2);
        opacity: 0.6;
    }

    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

.video-loading-text {
    font-size: 26rpx;
    font-weight: 600;
    color: #22d3ee;
    letter-spacing: 1rpx;
    text-shadow: 0 2rpx 8rpx rgba(34, 211, 238, 0.5);
    -webkit-font-smoothing: antialiased;
}

.video-loading-dots {
    display: flex;
    align-items: center;
    gap: 8rpx;
}

.video-loading-dot {
    width: 8rpx;
    height: 8rpx;
    border-radius: 50%;
    background: #22d3ee;
    animation: dot-bounce 1.4s ease-in-out infinite;
    box-shadow: 0 2rpx 6rpx rgba(34, 211, 238, 0.4);
}

.video-loading-dot:nth-child(1) {
    animation-delay: 0s;
}

.video-loading-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.video-loading-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes dot-bounce {

    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.5;
    }

    40% {
        transform: translateY(-12rpx);
        opacity: 1;
    }
}

/* 生成失败遮罩 */
.video-error-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8rpx);
    border-radius: 16rpx;
}

.video-error-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    padding: 32rpx;
}

.video-error-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 96rpx;
    height: 96rpx;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.15);
    border: 2rpx solid rgba(239, 68, 68, 0.3);
    box-shadow: 0 4rpx 16rpx rgba(239, 68, 68, 0.2);
}

.video-error-text {
    font-size: 28rpx;
    font-weight: 600;
    color: #ef4444;
    letter-spacing: 1rpx;
    text-shadow: 0 2rpx 8rpx rgba(239, 68, 68, 0.4);
    -webkit-font-smoothing: antialiased;
}

.video-error-desc {
    font-size: 24rpx;
    color: #94a3b8;
    text-align: center;
    line-height: 1.5;
}

/* 视频信息区域 */
.video-item-info {
    display: flex;
    flex-direction: column;
    gap: 16rpx;
}

.video-item-title {
    font-size: 28rpx;
    font-weight: 600;
    color: #e2e8f0;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.video-item-title text {
    color: #e2e8f0;
    font-weight: 600;
    font-size: 28rpx;
}

/* 视频状态和元数据行 */
.video-item-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    flex-wrap: wrap;
}

.video-status-badge {
    display: flex;
    align-items: center;
    gap: 6rpx;
    padding: 6rpx 14rpx;
    border-radius: 16rpx;
    font-size: 24rpx;
    font-weight: 600;
    line-height: 1.4;
    -webkit-font-smoothing: antialiased;
    white-space: nowrap;
    backdrop-filter: blur(8rpx);
}

.video-status-badge text {
    font-weight: 600;
    font-size: 24rpx;
    letter-spacing: 0.3rpx;
}

.video-status-processing {
    background: rgba(34, 211, 238, 0.2);
    border: 1.5rpx solid rgba(34, 211, 238, 0.4);
    color: #06b6d4;
}

.video-status-processing text {
    color: #06b6d4;
    text-shadow: 0 1rpx 2rpx rgba(34, 211, 238, 0.3);
}

.video-status-success {
    background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 50%, #7c3aed 100%);
    border: 1.5rpx solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.video-status-success text {
    color: #ffffff;
    text-shadow: 0 1rpx 3rpx rgba(0, 0, 0, 0.3);
}

.video-status-error {
    background: rgba(239, 68, 68, 0.2);
    border: 1.5rpx solid rgba(239, 68, 68, 0.4);
    color: #ef4444;
}

.video-status-error text {
    color: #ef4444;
    text-shadow: 0 1rpx 2rpx rgba(239, 68, 68, 0.3);
}

/* 视频元数据组 */
.video-meta-group {
    display: flex;
    align-items: center;
    gap: 16rpx;
    flex-wrap: wrap;
    flex: 1;
    justify-content: flex-end;
}

.video-meta-item {
    display: flex;
    align-items: center;
    gap: 6rpx;
    color: #cbd5e1;
    font-size: 24rpx;
    font-weight: 500;
    -webkit-font-smoothing: antialiased;
}

.video-meta-item text {
    color: #e2e8f0;
    font-weight: 500;
    font-size: 24rpx;
}

/* 视频操作区域 */
.video-item-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    flex-wrap: wrap;
    padding-top: 4rpx;
}

/* 操作图标按钮组 */
.action-icons-group {
    display: flex;
    align-items: center;
    gap: 24rpx;
    padding: 6rpx 10rpx;
    border-radius: 20rpx;
    background: rgba(30, 41, 59, 0.5);
    border: 1rpx solid rgba(34, 211, 238, 0.2);
    backdrop-filter: blur(8rpx);
    min-height: 56rpx;
}

.action-icon-btn {
    width: 44rpx;
    height: 44rpx;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(34, 211, 238, 0.12);
    border: 1.5rpx solid rgba(34, 211, 238, 0.3);
    transition: all 0.2s ease;
    -webkit-font-smoothing: antialiased;
    backdrop-filter: blur(6rpx);
    box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.15);

    &:active {
        transform: scale(0.92);
        background: rgba(34, 211, 238, 0.25);
        border-color: rgba(34, 211, 238, 0.5);
        box-shadow: 0 3rpx 10rpx rgba(34, 211, 238, 0.3);
    }
}

/* 一键高清按钮样式 */
.enhance-link-btn {
    display: flex;
    align-items: center;
    gap: 6rpx;
    padding: 6rpx 18rpx;
    border-radius: 20rpx;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.18) 0%, rgba(124, 58, 237, 0.18) 100%);
    border: 1.5rpx solid rgba(34, 211, 238, 0.35);
    color: #22d3ee;
    font-size: 24rpx;
    font-weight: 600;
    transition: all 0.2s ease;
    -webkit-font-smoothing: antialiased;
    white-space: nowrap;
    backdrop-filter: blur(8rpx);
    box-shadow: 0 2rpx 8rpx rgba(34, 211, 238, 0.18);
    min-height: 56rpx;

    &:active {
        transform: scale(0.95);
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.28) 0%, rgba(124, 58, 237, 0.28) 100%);
        border-color: rgba(34, 211, 238, 0.5);
        box-shadow: 0 4rpx 12rpx rgba(34, 211, 238, 0.25);
    }
}

.enhance-link-text {
    font-weight: 600;
    font-size: 24rpx;
    color: #22d3ee;
    letter-spacing: 0.3rpx;
}

.action-icon-btn-danger {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.3);

    &:active {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.5);
        box-shadow: 0 4rpx 12rpx rgba(239, 68, 68, 0.3);
    }
}
</style>
