<template>
    <view class="community-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 顶部背景与导航 -->
        <view class="header-section">
            <image class="header-bg" src="https://picsum.photos/750/300?random=community" mode="aspectFill"></image>
            <view class="header-mask"></view>
            <view class="header-content">
                <view class="app-title">校园树洞</view>
                <view class="app-desc">分享生活，倾听心声</view>
                <view class="search-box">
                    <u-icon name="search" size="18" color="#666"></u-icon>
                    <input class="search-input" v-model="keyword" placeholder="搜索感兴趣的话题/内容..." @confirm="onSearch" />
                </view>
            </view>
        </view>

        <!-- 统计与功能区 -->
        <view class="stats-card">
            <view class="stat-item" @click="handleStatClick('post')">
                <text class="num">{{ stats.post_count || 0 }}</text>
                <text class="label">帖子</text>
            </view>
            <view class="stat-item" @click="handleStatClick('view')">
                <text class="num">{{ stats.view_count || 0 }}</text>
                <text class="label">浏览</text>
            </view>
            <view class="stat-item" @click="handleStatClick('like')">
                <text class="num">{{ stats.like_count || 0 }}</text>
                <text class="label">获赞</text>
            </view>
            <view class="divider"></view>
            <view class="action-btn" @click="goToMyPosts">
                <view class="action-icon-wrap">
                    <u-icon name="account" size="24" color="#00c853"></u-icon>
                </view>
                <text>我的发布</text>
            </view>
        </view>

        <!-- 内容区域 -->
        <view class="main-container">
            <!-- 话题/分类 -->
            <view class="topic-section">
                <view class="section-header">
                    <text class="title">热门话题</text>
                    <view class="more" @click="goToTopicList">
                    <text>更多</text>
                    <u-icon name="arrow-right" size="12" color="#999"></u-icon>
                </view>
                </view>
                <scroll-view scroll-x class="topic-scroll" :show-scrollbar="false">
                    <view class="topic-list">
                        <view class="topic-item" :class="{ active: currentCategory === 0 }" @click="changeCategory(0)">
                            <view class="topic-icon">
                                <u-icon name="grid-fill" color="#fff" size="20"></u-icon>
                            </view>
                            <text class="topic-name">全部</text>
                        </view>
                        <view class="topic-item" :class="{ active: currentCategory === item.id }" v-for="item in categoryList" :key="item.id" @click="changeCategory(item.id)">
                            <image v-if="item.icon" :src="img(item.icon)" class="topic-img" mode="aspectFill"></image>
                            <view v-else class="topic-icon" :style="{ background: getRandomColor(item.id) }">
                                <text>{{ item.name.charAt(0) }}</text>
                            </view>
                            <text class="topic-name">{{ item.name }}</text>
                        </view>
                    </view>
                </scroll-view>
            </view>

            <!-- 排序Tab -->
            <view class="tab-section">
                <view class="tab-item" :class="{ active: currentSort === 'new' }" @click="changeSort('new')">最新发布</view>
                <view class="tab-item" :class="{ active: currentSort === 'hot' }" @click="changeSort('hot')">热门讨论</view>
                <view class="tab-item" :class="{ active: currentSort === 'recommend' }" @click="changeSort('recommend')">精选推荐</view>
            </view>

            <!-- 帖子列表 -->
            <scroll-view 
                scroll-y 
                class="post-scroll"
                @scrolltolower="loadMore"
                refresher-enabled
                @refresherrefresh="onRefresh"
                :refresher-triggered="refreshing"
            >
                <!-- 列表为空 -->
                <view class="empty" v-if="postList.length === 0 && !loading">
                    <u-icon name="list" size="80" color="#ccc"></u-icon>
                    <text class="empty-title">这里空空如也</text>
                    <text class="empty-desc">快来发布第一条内容吧~</text>
                </view>

                <!-- 帖子卡片 -->
                <view class="post-card" v-for="(item, index) in postList" :key="item.id" @click="goToDetail(item)">
                    <view class="card-header">
                        <image class="avatar" :src="item.member_headimg ? img(item.member_headimg) : img('/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                        <view class="user-meta">
                            <view class="name-row">
                                <text class="nickname">{{ item.member_nickname || '匿名用户' }}</text>
                            </view>
                            <text class="time">{{ formatTime(item.create_time) }} · {{ item.view_count || 0 }}浏览</text>
                        </view>
                                            </view>

                    <view class="card-body">
                        <view class="post-title" v-if="item.title">
                            <text class="tag" v-if="item.is_top">置顶</text>
                            <text class="tag recommend" v-if="item.is_recommend">精选</text>
                            {{ item.title }}
                        </view>
                        <text class="post-content">{{ item.content }}</text>
                        
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

                        <!-- 话题标签 -->
                        <view class="tags-row" v-if="item.category_name">
                            <view class="topic-tag">
                                <text># {{ item.category_name }}</text>
                            </view>
                        </view>
                    </view>

                    <view class="card-footer">
                        <view class="interaction-btn" @click.stop="likePost(item)">
                            <view class="icon-box" :class="{ active: item.is_liked }">
                                <u-icon :name="item.is_liked ? 'thumb-up-fill' : 'thumb-up'" size="20" :color="item.is_liked ? '#ff6b00' : '#666'"></u-icon>
                            </view>
                            <text :class="{ active: item.is_liked }">{{ item.like_count || '点赞' }}</text>
                        </view>
                        <view class="interaction-btn" @click.stop="goToDetail(item)">
                            <view class="icon-box">
                                <u-icon name="chat" size="20" color="#666"></u-icon>
                            </view>
                            <text>{{ item.comment_count || '评论' }}</text>
                        </view>
                        <view class="interaction-btn" @click.stop="sharePost(item)">
                            <view class="icon-box">
                                <u-icon name="share" size="20" color="#666"></u-icon>
                            </view>
                            <text>分享</text>
                        </view>
                    </view>
                </view>

                <view class="loading-status" v-if="loading || !hasMore">
                    <u-loading-icon v-if="loading" mode="circle" color="#00c853"></u-loading-icon>
                    <text v-else-if="postList.length > 0" class="no-more">—— 到底啦 ——</text>
                </view>
                
                <view style="height: 120rpx;"></view>
            </scroll-view>
        </view>

        <!-- 悬浮发布按钮 -->
        <view class="fab-publish" @click="goToPublish">
            <u-icon name="plus" size="24" color="#fff"></u-icon>
            <text>发布</text>
        </view>

        <custom-tabbar current="community" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCommunityList, likeCommunity, getCommunityCategories, getCommunityStats } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import customTabbar from '../../components/custom-tabbar.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_community')

const currentCategory = ref(0)
const currentSort = ref('new')
const categoryList = ref<any[]>([])
const postList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const keyword = ref('')
const stats = ref({
    post_count: 0,
    view_count: 0,
    like_count: 0
})

onMounted(() => {
    loadConfig()
    loadCategories()
    loadPosts()
    loadStats()
})

onShow(() => {
    loadPosts(true)
    loadStats()
})

const loadStats = async () => {
    const res: any = await getCommunityStats()
    if (res.code === 1 && res.data) {
        stats.value = res.data
    }
}

const loadCategories = async () => {
    try {
        const res: any = await getCommunityCategories()
        if (res.code === 1 && res.data) {
            categoryList.value = res.data || []
        }
    } catch (e) {
        console.error('加载分类失败', e)
    }
}

const changeSort = (sort: string) => {
    currentSort.value = sort
    loadPosts(true)
}

const loadPosts = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const params: any = {
            page: page.value,
            limit,
            category_id: currentCategory.value,
            sort: currentSort.value,
            keyword: keyword.value
        }
        
        // 获取当前选择的学校
        const currentSchool = uni.getStorageSync('current_school')
        if (currentSchool?.id) {
            params.school_id = currentSchool.id
        }
        
        const res: any = await getCommunityList(params)
        
        if (res.code === 1) {
            if (refresh) {
                postList.value = res.data.list || []
            } else {
                postList.value = [...postList.value, ...(res.data.list || [])]
            }
            
            if ((res.data.list || []).length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        refreshing.value = false
    }
}

const changeCategory = (id: number) => {
    currentCategory.value = id
    loadPosts(true)
}

const loadMore = () => {
    loadPosts()
}

const onRefresh = () => {
    refreshing.value = true
    loadPosts(true)
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

const getRandomColor = (id: number) => {
    const colors = ['#FF9800', '#2196F3', '#4CAF50', '#E91E63', '#9C27B0', '#00BCD4']
    return colors[id % colors.length]
}

const goToDetail = (item: any) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/community/detail?id=${item.id}`
    })
}

const goToPublish = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/community/publish'
    })
}

const onSearch = () => {
    loadPosts(true)
}

const goToMyPosts = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/community/my' })
}

const handleStatClick = (type: string) => {
    // Implement stat click logic
}

const likePost = async (item: any) => {
    try {
        const res: any = await likeCommunity({ post_id: item.id })
        if (res.code === 1) {
            item.is_liked = true
            item.like_count = (item.like_count || 0) + 1
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        console.error('点赞失败', e)
        uni.showToast({ title: e.msg || '操作失败', icon: 'none' })
    }
}

const sharePost = (item: any) => {
    goToDetail(item)
}

const previewImage = (urls: string[], index: any) => {
    uni.previewImage({
        urls: urls.map(u => img(u)),
        current: Number(index)
    })
}

const showActionSheet = (item: any) => {
    const isMine = item.member_id === uni.getStorageSync('member_id')
    const itemList = isMine ? ['编辑', '删除', '取消'] : ['举报', '不感兴趣', '取消']
    uni.showActionSheet({
        itemList: itemList.slice(0, -1),
        success: (res) => {
            if (isMine) {
                if (res.tapIndex === 0) {
                    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/community/publish?id=${item.id}` })
                } else if (res.tapIndex === 1) {
                    uni.showModal({
                        title: '提示',
                        content: '确定删除这条帖子吗？',
                        success: (modalRes) => {
                            if (modalRes.confirm) {
                                deletePost(item.id)
                            }
                        }
                    })
                }
            } else {
                if (res.tapIndex === 0) {
                    uni.showToast({ title: '举报已提交', icon: 'none' })
                } else if (res.tapIndex === 1) {
                    uni.showToast({ title: '将减少此类内容推荐', icon: 'none' })
                }
            }
        }
    })
}

const deletePost = async (id: number) => {
    try {
        const { deleteCommunity } = await import('../../api/xiaoyuan')
        const res: any = await deleteCommunity({ id })
        if (res.code === 1) {
            uni.showToast({ title: '删除成功', icon: 'success' })
            loadPosts(true)
        } else {
            uni.showToast({ title: res.msg || '删除失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '删除失败', icon: 'none' })
    }
}

const goToTopicList = () => {
    // 显示所有话题选择
    if (categoryList.value.length > 0) {
        const names = categoryList.value.map((c: any) => c.name)
        uni.showActionSheet({
            itemList: names,
            success: (res) => {
                const selected = categoryList.value[res.tapIndex]
                if (selected) {
                    changeCategory(selected.id)
                }
            }
        })
    } else {
        uni.showToast({ title: '暂无更多话题', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.community-page {
    min-height: 100vh;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
}

.header-section {
    position: relative;
    height: 400rpx;
    
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
            margin-bottom: 30rpx;
        }
        
        .search-box {
            background: rgba(255,255,255,0.9);
            border-radius: 40rpx;
            height: 72rpx;
            display: flex;
            align-items: center;
            padding: 0 30rpx;
            
            .search-input {
                flex: 1;
                margin-left: 16rpx;
                font-size: 26rpx;
                color: #333;
            }
        }
    }
}

.stats-card {
    margin: -40rpx 24rpx 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx 24rpx;
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
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
        }
        
        .label {
            font-size: 22rpx;
            color: #999;
            margin-top: 2rpx;
        }
    }
    
    .divider {
        width: 1rpx;
        height: 32rpx;
        background: #eee;
        margin: 0 16rpx;
    }
    
    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        
        .action-icon {
            width: 40rpx;
            height: 40rpx;
            border-radius: 50%;
            margin-bottom: 2rpx;
        }
        
        .action-icon-wrap {
            width: 40rpx;
            height: 40rpx;
            border-radius: 50%;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rpx;
        }
        
        text {
            font-size: 22rpx;
            color: #00c853;
            font-weight: bold;
        }
    }
}

.main-container {
    padding: 24rpx;
}

.topic-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx;
    margin-bottom: 20rpx;
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16rpx;
        
        .title {
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
        }
        
        .more {
            font-size: 24rpx;
            color: #999;
            display: flex;
            align-items: center;
            gap: 4rpx;
            flex-shrink: 0;
        }
    }
    
    .topic-scroll {
        white-space: nowrap;
        width: 100%;
    }
    
    .topic-list {
        display: inline-flex;
        gap: 16rpx;
        padding-right: 20rpx;
    }
    
    .topic-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100rpx;
        flex-shrink: 0;
        
        .topic-icon, .topic-img {
            width: 64rpx;
            height: 64rpx;
            border-radius: 24rpx;
            margin-bottom: 8rpx;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .topic-icon {
            background: linear-gradient(135deg, #00c853, #69f0ae);
            
            text {
                color: #fff;
                font-size: 28rpx;
                font-weight: bold;
            }
        }
        
        .topic-name {
            font-size: 22rpx;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            text-align: center;
        }
        
        &.active {
            .topic-name {
                color: #00c853;
                font-weight: bold;
            }
        }
    }
}

.tab-section {
    display: flex;
    background: #fff;
    border-radius: 20rpx;
    padding: 10rpx;
    margin-bottom: 24rpx;
    
    .tab-item {
        flex: 1;
        text-align: center;
        padding: 16rpx 0;
        font-size: 28rpx;
        color: #666;
        position: relative;
        transition: all 0.3s;
        
        &.active {
            color: #00c853;
            font-weight: bold;
            
            &::after {
                content: '';
                position: absolute;
                bottom: 6rpx;
                left: 50%;
                transform: translateX(-50%);
                width: 30rpx;
                height: 4rpx;
                background: #00c853;
                border-radius: 4rpx;
            }
        }
    }
}

.post-scroll {
    height: calc(100vh - 700rpx); // Estimate height
}

.post-card {
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
                
                .level-tag {
                    display: flex;
                    align-items: center;
                    background: linear-gradient(135deg, #ff9800, #ff5722);
                    padding: 2rpx 10rpx;
                    border-radius: 12rpx;
                    
                    text {
                        color: #fff;
                        font-size: 18rpx;
                        margin-left: 4rpx;
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
        .post-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #222;
            margin-bottom: 12rpx;
            line-height: 1.4;
            
            .tag {
                display: inline-block;
                padding: 2rpx 10rpx;
                font-size: 20rpx;
                color: #fff;
                background: #ff5722;
                border-radius: 8rpx;
                margin-right: 10rpx;
                vertical-align: middle;
                font-weight: normal;
                
                &.recommend {
                    background: #2196f3;
                }
            }
        }
        
        .post-content {
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
        
        .tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16rpx;
            margin-bottom: 20rpx;
            
            .topic-tag {
                background: #f0f7ff;
                padding: 6rpx 16rpx;
                border-radius: 30rpx;
                
                text {
                    font-size: 24rpx;
                    color: #2196f3;
                }
            }
        }
    }
    
    .card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 20rpx;
        border-top: 1rpx solid #f8f8f8;
        
        .interaction-btn {
            display: flex;
            align-items: center;
            gap: 8rpx;
            padding: 10rpx 0;
            
            .icon-box {
                transition: transform 0.2s;
                
                &.active {
                    transform: scale(1.1);
                }
            }
            
            text {
                font-size: 26rpx;
                color: #666;
                
                &.active {
                    color: #ff6b00;
                }
            }
        }
    }
}

.fab-publish {
    position: fixed;
    right: 30rpx;
    bottom: 220rpx;
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
</style>
