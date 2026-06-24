<template>
    <view class="guestbook-page">
        <!-- 顶部背景与导航 -->
        <view class="header-section">
            <image class="header-bg" src="https://picsum.photos/750/300?random=guestbook" mode="aspectFill"></image>
            <view class="header-mask"></view>
            <view class="header-content">
                <view class="app-title">留言板</view>
                <view class="app-desc">分享心声，留下足迹</view>
            </view>
        </view>

        <!-- 统计区 -->
        <view class="stats-card">
            <view class="stat-item">
                <text class="num">{{ stats.total_count || 0 }}</text>
                <text class="label">总留言</text>
            </view>
            <view class="stat-item">
                <text class="num">{{ stats.today_count || 0 }}</text>
                <text class="label">今日新增</text>
            </view>
            <view class="divider"></view>
            <view class="action-btn" @click="goToMy">
                <view class="action-icon-wrap">
                    <u-icon name="account" size="24" color="#00c853"></u-icon>
                </view>
                <text>我的留言</text>
            </view>
        </view>

        <!-- 留言列表 -->
        <view class="main-container">
            <scroll-view 
                scroll-y 
                class="message-scroll"
                @scrolltolower="loadMore"
                refresher-enabled
                @refresherrefresh="onRefresh"
                :refresher-triggered="refreshing"
            >
                <!-- 列表为空 -->
                <view class="empty" v-if="messageList.length === 0 && !loading">
                    <image src="/static/resource/images/empty.png" mode="widthFix" style="width: 200rpx;"></image>
                    <text class="empty-title">暂无留言</text>
                    <text class="empty-desc">快来留下第一条留言吧~</text>
                </view>

                <!-- 留言卡片 -->
                <view class="message-card" v-for="item in messageList" :key="item.id">
                    <view class="card-header">
                        <image class="avatar" :src="img(item.member_headimg || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                        <view class="user-meta">
                            <view class="name-row">
                                <text class="nickname">{{ item.is_anonymous ? '匿名用户' : (item.member_nickname || '匿名用户') }}</text>
                                <view class="top-tag" v-if="item.is_top">
                                    <text>置顶</text>
                                </view>
                            </view>
                            <text class="time">{{ formatTime(item.create_time) }}</text>
                        </view>
                        <view class="more-btn" @click.stop="showActionSheet(item)" v-if="item.member_id === currentMemberId">
                            <u-icon name="more-dot-fill" size="20" color="#ccc"></u-icon>
                        </view>
                    </view>

                    <view class="card-body">
                        <text class="message-content">{{ item.content }}</text>
                        
                        <!-- 图片网格 -->
                        <view class="media-grid" v-if="item.images && parseImages(item.images).length > 0">
                            <view class="grid-layout" :class="'layout-' + getGridClass(parseImages(item.images).length)">
                                <image 
                                    v-for="(imgUrl, idx) in parseImages(item.images).slice(0, 9)" 
                                    :key="idx"
                                    :src="img(imgUrl)"
                                    mode="aspectFill"
                                    class="media-item"
                                    @click.stop="previewImage(parseImages(item.images), idx)"
                                ></image>
                            </view>
                        </view>

                        <!-- 管理员回复 -->
                        <view class="reply-box" v-if="item.reply">
                            <view class="reply-header">
                                <u-icon name="chat-fill" size="14" color="#00c853"></u-icon>
                                <text class="reply-label">管理员回复</text>
                            </view>
                            <text class="reply-content">{{ item.reply }}</text>
                        </view>
                    </view>
                </view>

                <view class="loading-status" v-if="loading || !hasMore">
                    <u-loading-icon v-if="loading" mode="circle" color="#00c853"></u-loading-icon>
                    <text v-else-if="messageList.length > 0" class="no-more">—— 到底啦 ——</text>
                </view>
                
                <view style="height: 120rpx;"></view>
            </scroll-view>
        </view>

        <!-- 悬浮发布按钮 -->
        <view class="fab-publish" @click="showPublishPopup = true">
            <u-icon name="edit-pen" size="24" color="#fff"></u-icon>
            <text>留言</text>
        </view>

        <!-- 发布弹窗 -->
        <u-popup :show="showPublishPopup" mode="bottom" round="20" @close="showPublishPopup = false">
            <view class="publish-popup">
                <view class="popup-header">
                    <text class="popup-title">发布留言</text>
                    <view class="close-btn" @click="showPublishPopup = false">
                        <u-icon name="close" size="20" color="#999"></u-icon>
                    </view>
                </view>
                
                <view class="popup-body">
                    <textarea 
                        v-model="publishForm.content" 
                        placeholder="写下你想说的话..." 
                        maxlength="500"
                        class="content-input"
                    ></textarea>
                    
                    <view class="image-upload">
                        <view class="upload-list">
                            <view class="upload-item" v-for="(imgUrl, idx) in publishForm.images" :key="idx">
                                <image :src="img(imgUrl)" mode="aspectFill"></image>
                                <view class="remove-btn" @click="removeImage(idx)">
                                    <u-icon name="close" size="12" color="#fff"></u-icon>
                                </view>
                            </view>
                            <view class="upload-btn" @click="chooseImage" v-if="publishForm.images.length < 9">
                                <u-icon name="plus" size="30" color="#ccc"></u-icon>
                            </view>
                        </view>
                    </view>

                    <view class="anonymous-row">
                        <text>匿名发布</text>
                        <u-switch v-model="publishForm.is_anonymous" activeColor="#00c853" size="22"></u-switch>
                    </view>
                </view>

                <view class="popup-footer">
                    <view class="submit-btn" :class="{ disabled: !publishForm.content.trim() }" @click="submitPublish">
                        <text>发布留言</text>
                    </view>
                </view>
            </view>
        </u-popup>
        <!-- #ifdef MP-WEIXIN -->
        <wx-privacy-popup ref="wxPrivacyPopupRef" @agree="onWxPrivacyAgree" @disagree="onWxPrivacyDisagree"></wx-privacy-popup>
        <!-- #endif -->
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useWxPrivacyBeforeAlbum } from '../../composables/useWxPrivacy'
import { getGuestbookList, publishGuestbook, deleteGuestbook, getGuestbookStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const messageList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const stats = ref({
    total_count: 0,
    today_count: 0
})
const currentMemberId = ref(0)
const showPublishPopup = ref(false)
const publishForm = ref({
    content: '',
    images: [] as string[],
    is_anonymous: false
})

const { wxPrivacyPopupRef, onWxPrivacyAgree, onWxPrivacyDisagree, requestPrivacyThen } = useWxPrivacyBeforeAlbum()

onMounted(() => {
    loadMessages()
    loadStats()
    getCurrentMember()
})

onShow(() => {
    loadMessages(true)
    loadStats()
})

const getCurrentMember = () => {
    const memberInfo = uni.getStorageSync('member_info')
    if (memberInfo && memberInfo.member_id) {
        currentMemberId.value = memberInfo.member_id
    }
}

const loadStats = async () => {
    const res: any = await getGuestbookStats()
    if (res.code === 1 && res.data) {
        stats.value = res.data
    }
}

const loadMessages = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    const params = {
        page: page.value,
        limit
    }
    
    const res: any = await getGuestbookList(params)
    
    if (res.code === 1) {
        if (refresh) {
            messageList.value = res.data.list || []
        } else {
            messageList.value = [...messageList.value, ...(res.data.list || [])]
        }
        
        if ((res.data.list || []).length < limit) {
            hasMore.value = false
        } else {
            page.value++
        }
    }
    
    loading.value = false
    refreshing.value = false
}

const loadMore = () => {
    loadMessages()
}

const onRefresh = () => {
    refreshing.value = true
    loadMessages(true)
}

const parseImages = (images: any) => {
    if (typeof images === 'string') {
        if (!images) return []
        try {
            const parsed = JSON.parse(images)
            if (Array.isArray(parsed)) return parsed
            return images.split(',').filter((s: string) => s)
        } catch {
            return images.split(',').filter((s: string) => s)
        }
    }
    return images || []
}

const getGridClass = (count: number) => {
    if (count === 1) return '1'
    if (count === 2) return '2'
    if (count === 4) return '2' 
    return '3'
}

const formatTime = (time: any) => {
    if (!time) return ''
    if (typeof time === 'string') return time
    const now = Date.now() / 1000
    const diff = now - time
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    const date = new Date(time * 1000)
    return `${date.getMonth() + 1}-${date.getDate()}`
}

const previewImage = (urls: string[], index: any) => {
    uni.previewImage({
        urls: urls.map(u => img(u)),
        current: Number(index)
    })
}

const showActionSheet = (item: any) => {
    uni.showActionSheet({
        itemList: ['删除'],
        success: async (res) => {
            if (res.tapIndex === 0) {
                uni.showModal({
                    title: '提示',
                    content: '确定要删除这条留言吗？',
                    success: async (modalRes) => {
                        if (modalRes.confirm) {
                            const delRes: any = await deleteGuestbook({ id: item.id })
                            if (delRes.code === 1) {
                                uni.showToast({ title: '删除成功', icon: 'success' })
                                loadMessages(true)
                                loadStats()
                            } else {
                                uni.showToast({ title: delRes.msg || '删除失败', icon: 'none' })
                            }
                        }
                    }
                })
            }
        }
    })
}

const goToMy = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/guestbook/my' })
}

const runChooseImage = () => {
    uni.chooseImage({
        count: 9 - publishForm.value.images.length,
        sizeType: ['compressed'],
        sourceType: ['album', 'camera'],
        success: (res) => {
            const paths = res.tempFilePaths as string[]
            paths.forEach((path: string) => {
                uni.uploadFile({
                    url: '/api/upload/image',
                    filePath: path,
                    name: 'file',
                    success: (uploadRes) => {
                        const data = JSON.parse(uploadRes.data)
                        if (data.code === 1 && data.data && data.data.url) {
                            publishForm.value.images.push(data.data.url)
                        }
                    },
                    fail: (err) => {
                        handleUploadError(err)
                    }
                })
            })
        },
        fail: (err) => {
            handleUploadError(err)
        }
    })
}

const chooseImage = () => {
    requestPrivacyThen(runChooseImage)
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

const removeImage = (idx: number) => {
    publishForm.value.images.splice(idx, 1)
}

const submitPublish = async () => {
    if (!publishForm.value.content.trim()) {
        uni.showToast({ title: '请输入留言内容', icon: 'none' })
        return
    }

    const res: any = await publishGuestbook({
        content: publishForm.value.content,
        images: publishForm.value.images,
        is_anonymous: publishForm.value.is_anonymous ? 1 : 0
    })

    if (res.code === 1) {
        uni.showToast({ title: '发布成功', icon: 'success' })
        showPublishPopup.value = false
        publishForm.value = { content: '', images: [], is_anonymous: false }
        loadMessages(true)
        loadStats()
    } else {
        uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.guestbook-page {
    min-height: 100vh;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    height: 350rpx;
    
    .header-bg {
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0; left: 0;
    }
    
    .header-mask {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.6));
    }
    
    .header-content {
        position: relative;
        z-index: 10;
        padding: 100rpx 30rpx 40rpx;
        color: #fff;
        
        .app-title {
            font-size: 48rpx;
            font-weight: bold;
            margin-bottom: 10rpx;
            letter-spacing: 2rpx;
        }
        
        .app-desc {
            font-size: 28rpx;
            opacity: 0.9;
        }
    }
}

.stats-card {
    margin: -60rpx 24rpx 0;
    background: #fff;
    border-radius: 20rpx;
    padding: 30rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 20;
    box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.06);
    
    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        
        .num {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
        
        .label {
            font-size: 24rpx;
            color: #999;
            margin-top: 4rpx;
        }
    }
    
    .divider {
        width: 1rpx;
        height: 40rpx;
        background: #eee;
        margin: 0 20rpx;
    }
    
    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        
        .action-icon-wrap {
            width: 48rpx;
            height: 48rpx;
            border-radius: 50%;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4rpx;
        }
        
        text {
            font-size: 24rpx;
            color: #00c853;
            font-weight: bold;
        }
    }
}

.main-container {
    padding: 24rpx;
    flex: 1;
}

.message-scroll {
    height: calc(100vh - 450rpx);
}

.message-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 30rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 2rpx 10rpx rgba(0,0,0,0.02);
    
    .card-header {
        display: flex;
        align-items: flex-start;
        margin-bottom: 20rpx;
        
        .avatar {
            width: 80rpx;
            height: 80rpx;
            border-radius: 50%;
            margin-right: 20rpx;
            border: 2rpx solid #f5f5f5;
        }
        
        .user-meta {
            flex: 1;
            
            .name-row {
                display: flex;
                align-items: center;
                margin-bottom: 6rpx;
                
                .nickname {
                    font-size: 30rpx;
                    font-weight: bold;
                    color: #333;
                    margin-right: 12rpx;
                }
                
                .top-tag {
                    display: flex;
                    align-items: center;
                    background: linear-gradient(135deg, #ff9800, #ff5722);
                    padding: 2rpx 10rpx;
                    border-radius: 12rpx;
                    
                    text {
                        color: #fff;
                        font-size: 18rpx;
                    }
                }
            }
            
            .time {
                font-size: 22rpx;
                color: #999;
            }
        }
    }
    
    .card-body {
        .message-content {
            font-size: 28rpx;
            color: #444;
            line-height: 1.6;
            margin-bottom: 20rpx;
            display: block;
        }
        
        .media-grid {
            margin-bottom: 20rpx;
            
            .grid-layout {
                display: grid;
                gap: 10rpx;
                
                &.layout-1 {
                    grid-template-columns: 1fr;
                    .media-item { height: 360rpx; border-radius: 12rpx; max-width: 70%; }
                }
                
                &.layout-2 {
                    grid-template-columns: repeat(2, 1fr);
                    .media-item { height: 240rpx; border-radius: 12rpx; }
                }
                
                &.layout-3 {
                    grid-template-columns: repeat(3, 1fr);
                    .media-item { height: 200rpx; border-radius: 12rpx; }
                }
                
                .media-item {
                    width: 100%;
                    background: #f5f5f5;
                }
            }
        }

        .reply-box {
            background: #f8f9fa;
            border-radius: 12rpx;
            padding: 20rpx;
            margin-top: 16rpx;

            .reply-header {
                display: flex;
                align-items: center;
                margin-bottom: 10rpx;

                .reply-label {
                    font-size: 24rpx;
                    color: #00c853;
                    font-weight: bold;
                    margin-left: 8rpx;
                }
            }

            .reply-content {
                font-size: 26rpx;
                color: #666;
                line-height: 1.5;
            }
        }
    }
}

.fab-publish {
    position: fixed;
    right: 30rpx;
    bottom: 100rpx;
    background: linear-gradient(135deg, #00c853, #69f0ae);
    padding: 20rpx 40rpx;
    border-radius: 50rpx;
    display: flex;
    align-items: center;
    gap: 10rpx;
    box-shadow: 0 8rpx 24rpx rgba(0, 200, 83, 0.4);
    z-index: 99;
    
    text {
        color: #fff;
        font-weight: bold;
        font-size: 28rpx;
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    .empty-title {
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
        margin: 20rpx 0 10rpx;
    }
    
    .empty-desc {
        font-size: 26rpx;
        color: #999;
    }
}

.loading-status {
    padding: 30rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    .no-more {
        font-size: 24rpx;
        color: #ccc;
    }
}

.publish-popup {
    padding: 30rpx;
    
    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30rpx;
        
        .popup-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
        
        .close-btn {
            padding: 10rpx;
        }
    }
    
    .popup-body {
        .content-input {
            width: 100%;
            height: 200rpx;
            background: #f8f9fa;
            border-radius: 16rpx;
            padding: 20rpx;
            font-size: 28rpx;
            box-sizing: border-box;
        }
        
        .image-upload {
            margin-top: 20rpx;
            
            .upload-list {
                display: flex;
                flex-wrap: wrap;
                gap: 16rpx;
            }
            
            .upload-item {
                width: 160rpx;
                height: 160rpx;
                border-radius: 12rpx;
                overflow: hidden;
                position: relative;
                
                image {
                    width: 100%;
                    height: 100%;
                }
                
                .remove-btn {
                    position: absolute;
                    top: 6rpx;
                    right: 6rpx;
                    width: 36rpx;
                    height: 36rpx;
                    background: rgba(0,0,0,0.5);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
            }
            
            .upload-btn {
                width: 160rpx;
                height: 160rpx;
                border-radius: 12rpx;
                background: #f8f9fa;
                border: 2rpx dashed #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        .anonymous-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30rpx;
            padding: 20rpx 0;
            border-top: 1rpx solid #f5f5f5;
            
            text {
                font-size: 28rpx;
                color: #333;
            }
        }
    }
    
    .popup-footer {
        margin-top: 30rpx;
        
        .submit-btn {
            background: linear-gradient(135deg, #00c853, #69f0ae);
            border-radius: 50rpx;
            height: 88rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            
            text {
                color: #fff;
                font-size: 30rpx;
                font-weight: bold;
            }
            
            &.disabled {
                opacity: 0.5;
            }
        }
    }
}
</style>
