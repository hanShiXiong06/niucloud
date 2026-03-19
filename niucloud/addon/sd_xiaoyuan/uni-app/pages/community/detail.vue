<template>
    <view class="detail-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <view class="post-detail" v-if="postInfo">
            <view class="post-header">
                <image class="avatar" :src="img(postInfo.member_headimg || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="user-info">
                    <text class="nickname">{{ postInfo.member_nickname || '用户' }}</text>
                    <text class="time">{{ formatTime(postInfo.create_time) }}</text>
                </view>
            </view>
            
            <view class="post-content">
                <text class="title" v-if="postInfo.title">{{ postInfo.title }}</text>
                <text class="content">{{ postInfo.content }}</text>
            </view>
            
            <view class="post-images" v-if="images.length > 0">
                <image 
                    v-for="(imgUrl, index) in images" 
                    :key="index" 
                    :src="img(imgUrl)" 
                    mode="aspectFill"
                    @click="previewImage(index)"
                ></image>
            </view>
            
            <view class="post-stats">
                <view class="stat-item">
                    <u-icon name="eye" size="20" color="#999"></u-icon>
                    <text>{{ postInfo.view_count || 0 }}</text>
                </view>
                <view class="stat-item" @click="handleLike">
                    <u-icon name="heart" size="20" :color="isLiked ? '#ff6b00' : '#999'"></u-icon>
                    <text>{{ postInfo.like_count || 0 }}</text>
                </view>
                <view class="stat-item">
                    <u-icon name="chat" size="20" color="#999"></u-icon>
                    <text>{{ postInfo.comment_count || 0 }}</text>
                </view>
                <view class="stat-item" @click="showSharePopup = true">
                    <u-icon name="share" size="20" color="#999"></u-icon>
                    <text>分享</text>
                </view>
                <view class="stat-item" v-if="isOwner" @click="handleDelete">
                    <u-icon name="trash" size="20" color="#ff4d4f"></u-icon>
                    <text style="color:#ff4d4f">删除</text>
                </view>
            </view>
        </view>

        <view class="comment-section">
            <view class="section-title">评论 ({{ commentList.length }})</view>
            <view class="comment-list">
                <view class="comment-item" v-for="item in commentList" :key="item.id">
                    <image class="avatar" :src="img(item.member_headimg || '/static/resource/images/default_headimg.png')"></image>
                    <view class="comment-content">
                        <text class="nickname">{{ item.member_nickname || '用户' }}</text>
                        <text class="content">{{ item.content }}</text>
                        <text class="time">{{ formatTime(item.create_time) }}</text>
                    </view>
                </view>
                <view class="empty" v-if="commentList.length === 0">
                    <text>暂无评论，快来抢沙发~</text>
                </view>
            </view>
        </view>

        <view class="comment-bar">
            <input class="comment-input" v-model="commentContent" placeholder="说点什么..." />
            <button class="send-btn" @click="sendComment">发送</button>
        </view>
        
        <!-- 分享弹窗 -->
        <share-popup 
            :showShare="showSharePopup" 
            :shareId="postId"
            :shareTitle="postInfo?.title || '校园树洞'"
            :shareContent="postInfo?.content || ''"
            :shareImage="images.length > 0 ? img(images[0]) : ''"
            :shareUrl="shareUrl"
            @hide="showSharePopup = false"
        />
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getCommunityDetail, likeCommunity, getCommunityComments, addCommunityComment, deleteCommunity } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'
import sharePopup from '../../components/share-popup.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_community')
const memberStore = useMemberStore()
const isOwner = computed(() => postInfo.value?.member_id && memberStore.info?.member_id && postInfo.value.member_id == memberStore.info.member_id)

const postId = ref(0)
const postInfo = ref<any>(null)
const commentList = ref<any[]>([])
const commentContent = ref('')
const isLiked = ref(false)
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const showSharePopup = ref(false)

const shareUrl = computed(() => {
    // #ifdef H5
    return window.location.href
    // #endif
    // #ifndef H5
    return `/addon/sd_xiaoyuan/pages/community/detail?id=${postId.value}`
    // #endif
})

const images = computed(() => {
    if (!postInfo.value?.images) return []
    if (typeof postInfo.value.images === 'string') {
        try {
            const parsed = JSON.parse(postInfo.value.images)
            if (Array.isArray(parsed)) return parsed
            return postInfo.value.images.split(',').filter((s: string) => s)
        } catch {
            return postInfo.value.images.split(',').filter((s: string) => s)
        }
    }
    return postInfo.value.images
})

onLoad((options: any) => {
    loadConfig()
    postId.value = Number(options?.id) || 0
    
    if (postId.value) {
        loadDetail()
        loadComments()
    }
})

const loadDetail = async () => {
    try {
        const res: any = await getCommunityDetail(postId.value)
        if (res.code === 1) {
            postInfo.value = res.data
        } else {
            uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
        }
    } catch (e) {
        console.error(e)
        uni.showToast({ title: '加载失败', icon: 'none' })
    }
}

const formatTime = (timeVal: any) => {
    if (!timeVal) return ''
    let date: Date
    if (typeof timeVal === 'string') {
        date = new Date(timeVal.replace(/-/g, '/'))
    } else if (typeof timeVal === 'number') {
        date = timeVal > 9999999999 ? new Date(timeVal) : new Date(timeVal * 1000)
    } else {
        return ''
    }
    if (isNaN(date.getTime())) return ''
    return `${date.getMonth() + 1}-${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const loadComments = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getCommunityComments({
            post_id: postId.value,
            page: page.value,
            limit
        })
        
        if (res.code === 1) {
            const list = res.data.list || []
            if (refresh) {
                commentList.value = list
            } else {
                commentList.value = [...commentList.value, ...list]
            }
            
            if (list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const parseImages = (images: any) => {
    if (typeof images === 'string') {
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

const previewImage = (index: any) => {
    uni.previewImage({
        current: Number(index),
        urls: images.value.map((u: string) => img(u))
    })
}

const handleDelete = () => {
    uni.showModal({
        title: '提示',
        content: '确定删除该帖子？删除后不可恢复',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await deleteCommunity({ id: postId.value })
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
        const res: any = await likeCommunity({ post_id: postId.value })
        if (res.code === 1) {
            isLiked.value = true
            postInfo.value.like_count = (postInfo.value.like_count || 0) + 1
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        console.error('点赞失败', e)
        uni.showToast({ title: e.msg || '操作失败', icon: 'none' })
    }
}

const sendComment = async () => {
    if (!commentContent.value.trim()) {
        uni.showToast({ title: '请输入评论内容', icon: 'none' })
        return
    }
    
    try {
        const res: any = await addCommunityComment({
            post_id: postId.value,
            content: commentContent.value
        })
        
        if (res.code === 1) {
            commentContent.value = ''
            // 重新加载评论
            loadComments(true)
            // 更新评论数
            if (postInfo.value) {
                postInfo.value.comment_count = (postInfo.value.comment_count || 0) + 1
            }
            uni.showToast({ title: '评论成功', icon: 'success' })
        }
    } catch (e) {
        console.error(e)
        uni.showToast({ title: '评论失败', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.detail-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 120rpx;
}

.post-detail {
    background: #fff;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.post-header {
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

.post-content {
    .title {
        display: block;
        font-size: 34rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 16rpx;
    }
    
    .content {
        font-size: 30rpx;
        color: #333;
        line-height: 1.8;
    }
}

.post-images {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
    margin-top: 24rpx;
    
    image {
        width: 220rpx;
        height: 220rpx;
        border-radius: 8rpx;
    }
}

.post-stats {
    display: flex;
    margin-top: 30rpx;
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
            color: #52c41a;
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
