<template>
    <view class="detail-page">
        <view class="confession-detail" v-if="confessionInfo">
            <view class="detail-header">
                <image class="avatar" :src="confessionInfo.is_anonymous ? 'https://cdn.niucloud.com/img/default_headimg.png' : img(confessionInfo.avatar || confessionInfo.headimg || 'https://cdn.niucloud.com/img/default_headimg.png')" mode="aspectFill"></image>
                <view class="user-info">
                    <text class="nickname">{{ confessionInfo.is_anonymous ? '匿名用户' : (confessionInfo.nickname || '用户') }}</text>
                    <text class="time">{{ (confessionInfo.create_time) }}</text>
                </view>
            </view>
            
            <view class="target-info" v-if="confessionInfo.target_name">
                <u-icon name="heart-fill" size="14" color="#ff6b81"></u-icon>
                <text class="label">表白对象：</text>
                <text class="name">{{ confessionInfo.target_name }}</text>
                <text class="info" v-if="confessionInfo.target_info">（{{ confessionInfo.target_info }}）</text>
            </view>
            
            <view class="detail-content">
                <text>{{ confessionInfo.content }}</text>
            </view>
            
            <view class="detail-images" v-if="imageList.length > 0">
                <image 
                    v-for="(imgUrl, index) in imageList" 
                    :key="index" 
                    :src="img(imgUrl)" 
                    mode="aspectFill"
                    @click="previewImage(index)"
                ></image>
            </view>
            
            <view class="detail-stats">
                <view class="stat-item" @click="handleLike">
                    <u-icon name="heart-fill" size="20" :color="isLiked ? '#ff6b81' : '#ccc'"></u-icon>
                    <text :class="{ liked: isLiked }">{{ confessionInfo.like_count || 0 }}</text>
                </view>
                <view class="stat-item" v-if="isOwner" @click="handleDelete">
                    <u-icon name="trash" size="20" color="#ff4d4f"></u-icon>
                    <text style="color:#ff4d4f">删除</text>
                </view>
                <view class="stat-item">
                    <u-icon name="chat" size="20" color="#999"></u-icon>
                    <text>{{ confessionInfo.comment_count || 0 }}</text>
                </view>
                <view class="stat-item">
                    <u-icon name="eye" size="20" color="#999"></u-icon>
                    <text>{{ confessionInfo.view_count || 0 }}</text>
                </view>
            </view>
        </view>

        <view class="comment-section">
            <view class="section-title">评论 ({{ commentTotal }})</view>
            <view class="comment-list">
                <view class="comment-item" v-for="item in commentList" :key="item.id">
                    <image class="avatar" :src="item.is_anonymous ? 'https://cdn.niucloud.com/img/default_headimg.png' : img(item.avatar || item.headimg || 'https://cdn.niucloud.com/img/default_headimg.png')"></image>
                    <view class="comment-content">
                        <text class="nickname">{{ item.is_anonymous ? '匿名用户' : (item.nickname || '用户') }}</text>
                        <text class="content">{{ item.content }}</text>
                        <text class="time">{{ (item.create_time) }}</text>
                    </view>
                </view>
                <view class="empty" v-if="commentList.length === 0">
                    <text>暂无评论，快来说点什么吧~</text>
                </view>
            </view>
        </view>

        <view class="comment-bar">
            <view class="anonymous-switch">
                <switch :checked="isAnonymous" @change="onAnonymousChange" color="#ff6b81" />
                <text>匿名</text>
            </view>
            <input class="comment-input" v-model="commentContent" placeholder="说点什么..." />
            <button class="send-btn" @click="sendComment">发送</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getConfessionDetail, likeConfession, getConfessionComments, addConfessionComment, deleteConfession } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'

const memberStore = useMemberStore()
const isOwner = computed(() => confessionInfo.value?.member_id && memberStore.info?.member_id && confessionInfo.value.member_id == memberStore.info.member_id)

const confessionId = ref(0)
const confessionInfo = ref<any>(null)
const commentList = ref<any[]>([])
const commentTotal = ref(0)
const commentContent = ref('')
const isLiked = ref(false)
const isAnonymous = ref(false)

const imageList = computed(() => {
    if (!confessionInfo.value?.images) return []
    const imgs = confessionInfo.value.images
    if (typeof imgs === 'string') {
        try {
            return JSON.parse(imgs)
        } catch {
            return imgs.split(',').filter((s: string) => s)
        }
    }
    return imgs
})

onLoad((options: any) => {
    confessionId.value = Number(options?.id) || 0
    
    if (confessionId.value) {
        loadDetail()
        loadComments()
    }
})

const loadDetail = async () => {
    try {
        const res: any = await getConfessionDetail(confessionId.value)
        if (res.code === 1) {
            confessionInfo.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const loadComments = async () => {
    try {
        const res: any = await getConfessionComments({ confession_id: confessionId.value, page: 1, limit: 50 })
        if (res.code === 1) {
            commentList.value = res.data?.list || []
            commentTotal.value = res.data?.count || 0
        }
    } catch (e) {
        console.error(e)
    }
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const d = new Date(timestamp * 1000)
    const Y = d.getFullYear()
    const M = (d.getMonth() + 1).toString().padStart(2, '0')
    const D = d.getDate().toString().padStart(2, '0')
    const h = d.getHours().toString().padStart(2, '0')
    const m = d.getMinutes().toString().padStart(2, '0')
    return `${Y}-${M}-${D} ${h}:${m}`
}

const previewImage = (index: any) => {
    uni.previewImage({
        current: index,
        urls: imageList.value.map((u: string) => img(u))
    })
}

const handleDelete = () => {
    uni.showModal({
        title: '提示',
        content: '确定删除该表白？删除后不可恢复',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await deleteConfession({ id: confessionId.value })
                if (result.code === 1) {
                    uni.showToast({ title: '删除成功', icon: 'success' })
                    setTimeout(() => uni.navigateBack(), 1500)
                }
            }
        }
    })
}

const handleLike = async () => {
    try {
        const res: any = await likeConfession({ confession_id: confessionId.value })
        if (res.code === 1) {
            isLiked.value = true
            confessionInfo.value.like_count = (confessionInfo.value.like_count || 0) + 1
        }
    } catch (e) {
        console.error(e)
    }
}

const onAnonymousChange = (e: any) => {
    isAnonymous.value = e.detail.value
}

const sendComment = async () => {
    if (!commentContent.value.trim()) {
        uni.showToast({ title: '请输入评论内容', icon: 'none' })
        return
    }
    try {
        uni.showLoading({ title: '发送中...' })
        const res: any = await addConfessionComment({
            confession_id: confessionId.value,
            content: commentContent.value.trim(),
            is_anonymous: isAnonymous.value ? 1 : 0
        })
        uni.hideLoading()
        if (res.code === 1) {
            commentContent.value = ''
            uni.showToast({ title: '评论成功', icon: 'success' })
            loadComments()
            if (confessionInfo.value) {
                confessionInfo.value.comment_count = (confessionInfo.value.comment_count || 0) + 1
            }
        } else {
            uni.showToast({ title: res.msg || '评论失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.message || '评论失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #fff5f5, #f5f5f5 100rpx);
    padding-bottom: 140rpx;
}

.confession-detail {
    background: #fff;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.detail-header {
    display: flex;
    align-items: center;
    margin-bottom: 24rpx;
    
    .avatar {
        width: 80rpx;
        height: 80rpx;
        border-radius: 50%;
        margin-right: 20rpx;
    }
    
    .user-info {
        .nickname {
            display: block;
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.target-info {
    display: flex;
    align-items: center;
    background: #fff5f5;
    padding: 20rpx;
    border-radius: 8rpx;
    margin-bottom: 20rpx;
    
    .label {
        font-size: 26rpx;
        color: #666;
        margin-left: 8rpx;
    }
    
    .name {
        font-size: 28rpx;
        color: #ff6b6b;
        font-weight: bold;
    }
    
    .info {
        font-size: 24rpx;
        color: #999;
    }
}

.detail-content {
    margin-bottom: 24rpx;
    
    text {
        font-size: 28rpx;
        color: #333;
        line-height: 1.8;
    }
}

.detail-images {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
    margin-bottom: 24rpx;
    
    image {
        width: 220rpx;
        height: 220rpx;
        border-radius: 8rpx;
    }
}

.detail-stats {
    display: flex;
    padding-top: 24rpx;
    border-top: 1rpx solid #f0f0f0;
    
    .stat-item {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        
        .iconfont {
            font-size: 30rpx;
            color: #999;
            margin-right: 8rpx;
            
            &.liked { color: #ff6b6b; }
        }
        
        text { font-size: 26rpx; color: #999; }
    }
}

.comment-section {
    background: #fff;
    padding: 24rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 24rpx;
}

.comment-item {
    display: flex;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    .avatar {
        width: 64rpx;
        height: 64rpx;
        border-radius: 50%;
        margin-right: 16rpx;
    }
    
    .comment-content {
        flex: 1;
        
        .nickname {
            display: block;
            font-size: 26rpx;
            color: #ff6b6b;
            margin-bottom: 8rpx;
        }
        
        .content {
            font-size: 28rpx;
            color: #333;
            line-height: 1.6;
        }
        
        .time {
            display: block;
            font-size: 22rpx;
            color: #999;
            margin-top: 8rpx;
        }
    }
}

.empty {
    text-align: center;
    padding: 60rpx;
    
    text { font-size: 28rpx; color: #999; }
}

.comment-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    padding: 16rpx 20rpx;
    background: #fff;
    box-shadow: 0 -2rpx 10rpx rgba(0,0,0,0.05);
    
    .anonymous-switch {
        display: flex;
        align-items: center;
        margin-right: 16rpx;
        
        text {
            font-size: 24rpx;
            color: #666;
            margin-left: 8rpx;
        }
    }
    
    .comment-input {
        flex: 1;
        height: 72rpx;
        background: #f5f5f5;
        border-radius: 36rpx;
        padding: 0 24rpx;
        font-size: 28rpx;
    }
    
    .send-btn {
        margin-left: 16rpx;
        padding: 0 32rpx;
        height: 72rpx;
        line-height: 72rpx;
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        border: none;
        border-radius: 36rpx;
        font-size: 28rpx;
        font-weight: bold;
    }
}
</style>
