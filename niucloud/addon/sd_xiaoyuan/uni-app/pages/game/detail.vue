<template>
    <view class="game-detail">
        <view class="detail-card" v-if="info.id">
            <!-- 头部 -->
            <view class="profile-section">
                <image class="avatar" :src="img(info.avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="profile-info">
                    <view class="name-row">
                        <text class="nickname">{{ info.nickname || '匿名玩家' }}</text>
                        <view class="gender-tag" :class="'g-' + info.gender" v-if="info.gender">
                            <text>{{ info.gender === 1 ? '♂' : '♀' }}</text>
                        </view>
                    </view>
                    <view class="score-row">
                        <text class="score">⭐ {{ info.score || '5.0' }}</text>
                        <text class="orders">{{ info.order_count || 0 }}单</text>
                        <text class="views">{{ info.view_count || 0 }}浏览</text>
                    </view>
                </view>
            </view>

            <!-- 价格 -->
            <view class="price-section">
                <text class="price">¥{{ info.price }}</text>
                <text class="unit">/{{ info.unit || '小时' }}</text>
            </view>

            <!-- 标签 -->
            <view class="tags-section">
                <text class="tag game">{{ getGameName(info.game_type) }}</text>
                <text class="tag service">{{ getServiceName(info.service_type) }}</text>
                <text class="tag rank" v-if="info.rank_level">{{ info.rank_level }}</text>
                <text class="tag voice" v-if="info.voice_chat">🎤 可语音</text>
            </view>

            <!-- 标题和描述 -->
            <view class="content-section">
                <text class="title">{{ info.title }}</text>
                <text class="content" v-if="info.content">{{ info.content }}</text>
            </view>

            <!-- 图片 -->
            <view class="images-section" v-if="images.length > 0">
                <image v-for="(imgUrl, i) in images" :key="i" :src="img(imgUrl)" mode="aspectFill" class="img-item" @click="previewImage(i)"></image>
            </view>

            <!-- 详细信息 -->
            <view class="info-section">
                <view class="info-row" v-if="info.game_name">
                    <text class="label">游戏ID</text>
                    <text class="value">{{ info.game_name }}</text>
                </view>
                <view class="info-row" v-if="info.online_time">
                    <text class="label">在线时间</text>
                    <text class="value">{{ info.online_time }}</text>
                </view>
                <view class="info-row">
                    <text class="label">性别要求</text>
                    <text class="value">{{ info.gender === 0 ? '不限' : info.gender === 1 ? '仅男生' : '仅女生' }}</text>
                </view>
                <view class="info-row">
                    <text class="label">发布时间</text>
                    <text class="value">{{ (info.create_time) }}</text>
                </view>
            </view>
        </view>

        <!-- 底部操作 - 发布者 -->
        <view class="bottom-action" v-if="info.id && isOwner">
            <button class="contact-btn" @click="editGame">
                <text>编辑</text>
            </button>
        </view>
        <!-- 底部联系 - 其他人 -->
        <view class="bottom-action" v-else-if="info.id">
            <button class="contact-btn" open-type="contact">
                <u-icon name="chat" size="24" color="#000"></u-icon>
                <text>联系TA</text>
            </button>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getGameDetail } from '../../api/game'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'

const memberStore = useMemberStore()
const info = ref<any>({})
let detailId = 0
const isOwner = computed(() => info.value.member_id && memberStore.info?.member_id && info.value.member_id == memberStore.info.member_id)

const gameMap: Record<string, string> = {
    WZRY: '王者荣耀', LOL: '英雄联盟', PUBG: '和平精英',
    CSGO: 'CS2', YS: '原神', EGG: '蛋仔派对', OTHER: '其他'
}
const serviceMap: Record<string, string> = {
    PLAY_WITH: '陪玩', BOOST: '代练', TEACH: '教学', TEAM: '组队'
}

const images = computed(() => {
    if (!info.value.images) return []
    if (typeof info.value.images === 'string') {
        try {
            const parsed = JSON.parse(info.value.images)
            if (Array.isArray(parsed)) return parsed
            return info.value.images.split(',').filter((s: string) => s)
        } catch {
            return info.value.images.split(',').filter((s: string) => s)
        }
    }
    return info.value.images || []
})

onLoad((options: any) => {
    detailId = parseInt(options.id)
    loadDetail()
})

const loadDetail = async () => {
    try {
        const res: any = await getGameDetail({ id: detailId })
        if (res.code === 1) {
            info.value = res.data
        }
    } catch (e: any) {
        uni.showToast({ title: e.msg || '加载失败', icon: 'none' })
    }
}

const getGameName = (t: string) => gameMap[t] || t
const getServiceName = (t: string) => serviceMap[t] || t

const formatTime = (ts: number) => {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const previewImage = (index: any) => {
    uni.previewImage({ urls: images.value.map((u: string) => img(u)), current: index })
}

const editGame = () => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/game/publish?id=${detailId}` })
}
</script>

<style lang="scss" scoped>
.game-detail { min-height: 100vh; background: #f5f5f5; padding: 20rpx; padding-bottom: 160rpx; }

.detail-card { background: #fff; border-radius: 20rpx; padding: 32rpx; }

.profile-section { display: flex; align-items: center; margin-bottom: 24rpx; }
.avatar { width: 110rpx; height: 110rpx; border-radius: 50%; margin-right: 24rpx; background: #f0f0f0; }
.profile-info { flex: 1; }
.name-row { display: flex; align-items: center; gap: 10rpx; margin-bottom: 10rpx; }
.nickname { font-size: 34rpx; font-weight: bold; color: #333; }
.gender-tag {
    width: 40rpx; height: 40rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24rpx;
    &.g-1 { background: #e6f7ff; color: #1890ff; }
    &.g-2 { background: #fff0f5; color: #eb2f96; }
}
.score-row { display: flex; align-items: center; gap: 20rpx; }
.score { font-size: 26rpx; color: #ff9500; font-weight: bold; }
.orders, .views { font-size: 24rpx; color: #999; }

.price-section {
    background: linear-gradient(135deg, #fff7e6, #fff1f0); border-radius: 16rpx; padding: 24rpx; margin-bottom: 24rpx; text-align: center;
}
.price { font-size: 48rpx; font-weight: bold; color: #ff6b00; }
.unit { font-size: 26rpx; color: #999; }

.tags-section { display: flex; flex-wrap: wrap; gap: 12rpx; margin-bottom: 24rpx; }
.tag {
    padding: 8rpx 20rpx; border-radius: 8rpx; font-size: 24rpx;
    &.game { background: #e6f7ff; color: #1890ff; }
    &.service { background: #f6ffed; color: #52c41a; }
    &.rank { background: #fff7e6; color: #fa8c16; }
    &.voice { background: #f0f5ff; color: #2f54eb; }
}

.content-section { margin-bottom: 24rpx; }
.title { display: block; font-size: 32rpx; font-weight: bold; color: #333; margin-bottom: 12rpx; }
.content { display: block; font-size: 28rpx; color: #666; line-height: 1.6; }

.images-section { display: flex; flex-wrap: wrap; gap: 12rpx; margin-bottom: 24rpx; }
.img-item { width: 210rpx; height: 210rpx; border-radius: 12rpx; }

.info-section { border-top: 1rpx solid #f0f0f0; padding-top: 24rpx; }
.info-row { display: flex; justify-content: space-between; padding: 16rpx 0; }
.label { font-size: 26rpx; color: #999; }
.value { font-size: 26rpx; color: #333; }

.bottom-action {
    position: fixed; bottom: 40rpx; left: 40rpx; right: 40rpx; z-index: 100;
}
.contact-btn {
    display: flex; align-items: center; justify-content: center; gap: 12rpx;
    width: 100%; padding: 28rpx; background: linear-gradient(to top, #aaf69b, #d1ff7c);
    border-radius: 16rpx; box-shadow: 0 4rpx 20rpx rgba(170,246,155,0.5); border: none;
    text { font-size: 32rpx; color: #000; font-weight: bold; }
}
</style>
