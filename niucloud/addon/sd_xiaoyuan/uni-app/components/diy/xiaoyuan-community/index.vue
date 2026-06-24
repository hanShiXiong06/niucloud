<template>
    <view :style="warpCss" class="xiaoyuan-community-wrap">
        <view class="community-container">
            <!-- 标题栏 -->
            <view class="header-bar">
                <view class="title-box">
                    <text class="title">{{ diyComponent.title || '校园树洞' }}</text>
                </view>
                <view 
                    v-if="diyComponent.showMore" 
                    class="more-btn" 
                    @click="goMore"
                >
                    <text class="more-text">{{ diyComponent.moreText || '查看更多' }}</text>
                    <u-icon name="arrow-right" size="24" color="#999"></u-icon>
                </view>
            </view>

            <!-- 树洞列表 -->
            <view class="community-list" v-if="list.length">
                <view 
                    v-for="item in list" 
                    :key="item.id" 
                    class="community-item"
                    @click="goDetail(item.id)"
                >
                    <view class="item-header">
                        <view class="user-info">
                            <image 
                                :src="item.member_headimg || defaultAvatar" 
                                class="avatar"
                                mode="aspectFill"
                            />
                            <view class="user-detail">
                                <text class="nickname">{{ item.member_nickname || '匿名用户' }}</text>
                                <text class="time">{{ formatTime(item.create_time) }}</text>
                            </view>
                        </view>
                        <view v-if="item.category_name" class="category-tag">
                            {{ item.category_name }}
                        </view>
                    </view>

                    <view class="item-content">
                        <text class="title">{{ item.title }}</text>
                        <text class="content" v-if="item.content">{{ item.content }}</text>
                    </view>

                    <!-- 图片预览 -->
                    <view v-if="item.images && item.images.length" class="image-grid">
                        <image 
                            v-for="(img, idx) in item.images.slice(0, 3)" 
                            :key="idx"
                            :src="img"
                            class="grid-image"
                            mode="aspectFill"
                        />
                    </view>

                    <view class="item-footer">
                        <view class="stat-item">
                            <u-icon name="eye" size="28" color="#999"></u-icon>
                            <text class="stat-text">{{ item.view_count || 0 }}</text>
                        </view>
                        <view class="stat-item">
                            <u-icon name="chat" size="28" color="#999"></u-icon>
                            <text class="stat-text">{{ item.comment_count || 0 }}</text>
                        </view>
                        <view class="stat-item">
                            <u-icon 
                                :name="item.is_liked ? 'heart-fill' : 'heart'" 
                                size="28" 
                                :color="item.is_liked ? '#ff4d6a' : '#999'"
                            ></u-icon>
                            <text class="stat-text">{{ item.like_count || 0 }}</text>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 空状态 -->
            <view v-else class="empty-state">
                <u-icon name="chat" size="80" color="#ddd"></u-icon>
                <text class="empty-text">暂无树洞内容</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import useDiyStore from '@/app/stores/diy'
import { getCommunityList } from '@/addon/sd_xiaoyuan/api/xiaoyuan'
import { ensureXiaoyuanDefaultSchool } from '@/addon/sd_xiaoyuan/composables/useXiaoyuanDefaultSchool'
import { redirect, img } from '@/utils/common'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

const warpCss = computed(() => {
    let style = ''
    const margin = diyComponent.value.margin || { top: 0, bottom: 0, both: 10 }
    
    style += `margin: ${margin.top}rpx ${margin.both}rpx ${margin.bottom}rpx;`
    style += `border-radius: ${diyComponent.value.topRounded || 12}rpx ${diyComponent.value.topRounded || 12}rpx ${diyComponent.value.bottomRounded || 12}rpx ${diyComponent.value.bottomRounded || 12}rpx;`
    
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += 'background-color:' + diyComponent.value.componentStartBgColor + ';'
        }
    }
    
    return style
})

const list = ref([])
const defaultAvatar = 'static/resource/images/default_headimg.png'

const onSchoolChanged = () => {
    if (diyStore.mode !== 'decorate') loadData()
}

onMounted(() => {
    if (diyStore.mode !== 'decorate') {
        ;(async () => {
            await ensureXiaoyuanDefaultSchool()
            loadData()
        })()
        uni.$on('xiaoyuan_school_changed', onSchoolChanged)
    }
})

onUnmounted(() => {
    uni.$off('xiaoyuan_school_changed', onSchoolChanged)
})

onShow(() => {
    if (diyStore.mode !== 'decorate') {
        ensureXiaoyuanDefaultSchool().then(() => loadData())
    }
})

const loadData = async () => {
    try {
        const params: any = {
            page: 1,
            limit: diyComponent.value.num || 10,
            sort: diyComponent.value.sort || 'new'
        }
        
        if (diyComponent.value.category_id) {
            params.category_id = diyComponent.value.category_id
        }
        const school = uni.getStorageSync('current_school')
        if (school && school.id) {
            params.school_id = school.id
        }
        
        const res: any = await getCommunityList(params)
        if (res.code === 1 && res.data && res.data.list) {
            list.value = res.data.list.map((item: any) => {
                // 处理图片
                if (item.images) {
                    if (typeof item.images === 'string') {
                        try {
                            item.images = JSON.parse(item.images)
                        } catch (e) {
                            item.images = []
                        }
                    }
                    if (Array.isArray(item.images)) {
                        item.images = item.images.map((imgPath: string) => img(imgPath))
                    }
                }
                
                // 处理头像
                if (item.member_headimg) {
                    item.member_headimg = img(item.member_headimg)
                }
                
                return item
            })
        } else {
            list.value = []
        }
    } catch (e) {
        console.error('加载树洞列表失败', e)
    }
}

const goMore = () => {
    if (diyComponent.value.moreUrl) {
        const url = diyComponent.value.moreUrl.url || diyComponent.value.moreUrl
        redirect({ url })
    }
}

const goDetail = (id: number) => {
    redirect({ url: '/addon/sd_xiaoyuan/pages/community/detail', param: { id } })
}

const formatTime = (time: any) => {
    if (!time) return ''
    if (typeof time === 'string') return time
    
    const now = Date.now()
    const timestamp = typeof time === 'number' ? time * 1000 : new Date(time).getTime()
    const diff = now - timestamp
    
    const minute = 60 * 1000
    const hour = 60 * minute
    const day = 24 * hour
    
    if (diff < minute) {
        return '刚刚'
    } else if (diff < hour) {
        return Math.floor(diff / minute) + '分钟前'
    } else if (diff < day) {
        return Math.floor(diff / hour) + '小时前'
    } else if (diff < 7 * day) {
        return Math.floor(diff / day) + '天前'
    } else {
        const date = new Date(timestamp)
        return `${date.getMonth() + 1}-${date.getDate()}`
    }
}
</script>

<style lang="scss" scoped>
.xiaoyuan-community-wrap {
    overflow: hidden;
}

.community-container {
    padding: 24rpx;
}

.header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;
    
    .title-box {
        .title {
            font-size: 32rpx;
            font-weight: 700;
            color: #1a1a1a;
        }
    }
    
    .more-btn {
        display: flex;
        align-items: center;
        gap: 4rpx;
        
        .more-text {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.community-list {
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.community-item {
    background: #f8f9fb;
    border-radius: 16rpx;
    padding: 24rpx;
    
    .item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16rpx;
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16rpx;
            flex: 1;
            
            .avatar {
                width: 64rpx;
                height: 64rpx;
                border-radius: 50%;
                background: #eee;
            }
            
            .user-detail {
                display: flex;
                flex-direction: column;
                gap: 4rpx;
                
                .nickname {
                    font-size: 26rpx;
                    font-weight: 600;
                    color: #333;
                }
                
                .time {
                    font-size: 22rpx;
                    color: #999;
                }
            }
        }
        
        .category-tag {
            padding: 4rpx 16rpx;
            background: #e6f7ff;
            color: #1890ff;
            font-size: 20rpx;
            border-radius: 8rpx;
        }
    }
    
    .item-content {
        margin-bottom: 16rpx;
        
        .title {
            display: block;
            font-size: 28rpx;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8rpx;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .content {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            font-size: 26rpx;
            color: #666;
            line-height: 1.6;
        }
    }
    
    .image-grid {
        display: flex;
        gap: 12rpx;
        margin-bottom: 16rpx;
        
        .grid-image {
            width: 200rpx;
            height: 200rpx;
            border-radius: 12rpx;
            background: #f0f0f0;
        }
    }
    
    .item-footer {
        display: flex;
        align-items: center;
        gap: 32rpx;
        padding-top: 16rpx;
        border-top: 1rpx solid #eee;
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8rpx;
            
            .stat-text {
                font-size: 24rpx;
                color: #999;
            }
        }
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80rpx 0;
    
    .empty-text {
        margin-top: 24rpx;
        font-size: 26rpx;
        color: #999;
    }
}
</style>
