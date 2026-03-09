<template>
    <view class="lostfound-detail">
        <!-- 类型标签 -->
        <view class="type-banner" :class="item.type">
            <text class="type-text">{{ item.type === 'LOST' ? '寻物启事' : '失物招领' }}</text>
            <text class="status-text">{{ getStatusText(item.status) }}</text>
        </view>

        <!-- 图片 -->
        <view class="image-section" v-if="images.length > 0">
            <swiper class="image-swiper" :indicator-dots="true" :autoplay="false">
                <swiper-item v-for="(item2, index) in images" :key="index">
                    <image :src="img(item2)" mode="aspectFill" @click="previewImage(index)"></image>
                </swiper-item>
            </swiper>
        </view>

        <!-- 基本信息 -->
        <view class="info-section">
            <text class="title">{{ item.title }}</text>
            <text class="desc">{{ item.content }}</text>
            
            <view class="meta-list">
                <view class="meta-item" v-if="item.category">
                    <text class="label">物品类别</text>
                    <text class="value">{{ item.category }}</text>
                </view>
                <view class="meta-item" v-if="item.lost_time">
                    <text class="label">{{ item.type === 'LOST' ? '丢失时间' : '拾取时间' }}</text>
                    <text class="value">{{ formatDate(item.lost_time) }}</text>
                </view>
                <view class="meta-item" v-if="item.lost_address">
                    <text class="label">{{ item.type === 'LOST' ? '丢失地点' : '拾取地点' }}</text>
                    <text class="value">{{ item.lost_address }}</text>
                </view>
                <view class="meta-item" v-if="item.reward > 0">
                    <text class="label">悬赏金额</text>
                    <text class="value reward">¥{{ item.reward }}</text>
                </view>
            </view>
        </view>

        <!-- 发布者信息 -->
        <view class="publisher-section">
            <view class="publisher-info">
                <image class="avatar" :src="img(item.member_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="publisher-detail">
                    <text class="nickname">{{ item.member_nickname || '用户' }}</text>
                    <text class="time">发布于 {{ (item.create_time) }}</text>
                </view>
            </view>
        </view>

        <!-- 底部操作 - 发布者 -->
        <view class="action-bar" v-if="isOwner">
            <button class="btn-contact" @click="editItem">编辑</button>
            <button class="btn-help" v-if="item.status === 1" @click="closeItem">关闭</button>
        </view>
        <!-- 底部操作 - 其他人 -->
        <view class="action-bar" v-else-if="item.status === 1">
            <button class="btn-contact" @click="contactPublisher">
                <text class="iconfont icon-phone"></text>
                联系TA
            </button>
            <button class="btn-help" @click="helpFind">
                {{ item.type === 'LOST' ? '我找到了' : '是我的' }}
            </button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getLostFoundDetail, contactLostFound, closeLostFound } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'

const memberStore = useMemberStore()
const isOwner = computed(() => item.value.member_id && memberStore.info?.member_id && item.value.member_id == memberStore.info.member_id)

const item = ref<any>({})

const images = computed(() => {
    if (!item.value.images) return []
    if (typeof item.value.images === 'string') {
        try {
            const parsed = JSON.parse(item.value.images)
            if (Array.isArray(parsed)) return parsed
            return item.value.images.split(',').filter((s: string) => s)
        } catch {
            return item.value.images.split(',').filter((s: string) => s)
        }
    }
    return item.value.images
})

const itemId = ref(0)

onLoad((options: any) => {
    itemId.value = parseInt(options?.id || '0')
    if (itemId.value) loadDetail()
})

const loadDetail = async () => {
    if (!itemId.value) return
    
    try {
        const res: any = await getLostFoundDetail(itemId.value)
        if (res.code === 1) {
            item.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const getStatusText = (status: number) => {
    const map: Record<number, string> = {
        0: '已关闭',
        1: '进行中',
        2: '已找到'
    }
    return map[status] || '进行中'
}

const formatDate = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const previewImage = (index: any) => {
    uni.previewImage({
        urls: images.value.map((u: string) => img(u)),
        current: Number(index)
    })
}

const editItem = () => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/lost_found/publish?id=${itemId.value}` })
}

const closeItem = () => {
    uni.showModal({
        title: '提示',
        content: '确定关闭该信息？关闭后将不再展示',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await closeLostFound({ id: itemId.value })
                if (result.code === 1) {
                    uni.showToast({ title: '关闭成功', icon: 'success' })
                    loadDetail()
                }
            }
        }
    })
}

const contactPublisher = async () => {
    if (item.value.contact_mobile) {
        uni.makePhoneCall({
            phoneNumber: item.value.contact_mobile
        })
    } else {
        try {
            const res: any = await contactLostFound({ id: item.value.id })
            if (res.code === 1 && res.data.mobile) {
                uni.makePhoneCall({
                    phoneNumber: res.data.mobile
                })
            } else {
                uni.showToast({ title: '暂无联系方式', icon: 'none' })
            }
        } catch (e) {
            uni.showToast({ title: '获取联系方式失败', icon: 'none' })
        }
    }
}

const helpFind = () => {
    uni.showModal({
        title: '提示',
        content: item.value.type === 'LOST' ? '确认您找到了这个物品吗？' : '确认这是您丢失的物品吗？',
        success: (res) => {
            if (res.confirm) {
                contactPublisher()
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.lostfound-detail {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 150rpx;
}

.type-banner {
    padding: 40rpx 30rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    &.LOST {
        background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    }
    
    &.FOUND {
        background: linear-gradient(135deg, #52c41a, #73d13d);
    }
    
    .type-text {
        font-size: 30rpx;
        color: #fff;
        font-weight: bold;
    }
    
    .status-text {
        font-size: 26rpx;
        color: rgba(255, 255, 255, 0.9);
        background: rgba(255, 255, 255, 0.2);
        padding: 8rpx 20rpx;
        border-radius: 20rpx;
    }
}

.image-section {
    background: #fff;
}

.image-swiper {
    height: 500rpx;
    
    image {
        width: 100%;
        height: 100%;
    }
}

.info-section {
    background: #fff;
    margin-top: 20rpx;
    padding: 24rpx;
    
    .title {
        display: block;
        font-size: 30rpx;
        color: #333;
        font-weight: bold;
        line-height: 1.5;
    }
    
    .desc {
        display: block;
        font-size: 28rpx;
        color: #666;
        line-height: 1.8;
        margin-top: 20rpx;
    }
}

.meta-list {
    margin-top: 30rpx;
    padding-top: 30rpx;
    border-top: 1rpx solid #f0f0f0;
}

.meta-item {
    display: flex;
    padding: 16rpx 0;
    
    .label {
        width: 160rpx;
        font-size: 28rpx;
        color: #999;
    }
    
    .value {
        flex: 1;
        font-size: 28rpx;
        color: #333;
        
        &.reward {
            color: #ff6b00;
            font-weight: bold;
        }
    }
}

.publisher-section {
    background: #fff;
    margin-top: 20rpx;
    padding: 24rpx;
}

.publisher-info {
    display: flex;
    align-items: center;
    
    .avatar {
        width: 80rpx;
        height: 80rpx;
        border-radius: 50%;
        margin-right: 20rpx;
    }
    
    .publisher-detail {
        .nickname {
            display: block;
            font-size: 30rpx;
            color: #333;
            font-weight: bold;
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
            margin-top: 8rpx;
        }
    }
}

.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    gap: 20rpx;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
    
    button {
        flex: 1;
        height: 72rpx;
        line-height: 72rpx;
        font-size: 30rpx;
        border-radius: 44rpx;
        border: none;
    }
    
    .btn-contact {
        background: #fff;
        color: #333;
        border: 2rpx solid #333;
        
        .iconfont {
            margin-right: 8rpx;
        }
    }
    
    .btn-help {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}
</style>
