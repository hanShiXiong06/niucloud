<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="game-page" v-if="isFeatureEnabled">
        <!-- 顶部筛选 -->
        <view class="filter-bar">
            <scroll-view scroll-x class="type-tabs">
                <view class="tab-item" :class="{ active: currentGame === '' }" @click="changeGame('')">全部</view>
                <view class="tab-item" :class="{ active: currentGame === item.value }" v-for="item in gameTypes" :key="item.value" @click="changeGame(item.value)">{{ item.label }}</view>
            </scroll-view>
            <scroll-view scroll-x class="service-tabs">
                <view class="stab-item" :class="{ active: currentService === '' }" @click="changeService('')">全部服务</view>
                <view class="stab-item" :class="{ active: currentService === item.value }" v-for="item in serviceTypes" :key="item.value" @click="changeService(item.value)">{{ item.label }}</view>
            </scroll-view>
        </view>

        <!-- 列表 -->
        <view class="list-wrap">
            <view class="game-card" v-for="item in list" :key="item.id" @click="goDetail(item.id)">
                <view class="card-top">
                    <image class="card-avatar" :src="img(item.avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                    <view class="card-info">
                        <view class="name-row">
                            <text class="nickname">{{ item.nickname || '匿名玩家' }}</text>
                            <view class="gender-tag" :class="'g-' + item.gender" v-if="item.gender">
                                <text>{{ item.gender === 1 ? '♂' : '♀' }}</text>
                            </view>
                        </view>
                        <view class="tag-row">
                            <text class="game-tag">{{ getGameName(item.game_type) }}</text>
                            <text class="service-tag">{{ getServiceName(item.service_type) }}</text>
                            <text class="rank-tag" v-if="item.rank_level">{{ item.rank_level }}</text>
                        </view>
                    </view>
                    <view class="price-box">
                        <text class="price">¥{{ item.price }}</text>
                        <text class="unit">/{{ item.unit || '小时' }}</text>
                    </view>
                </view>
                <view class="card-body">
                    <text class="title">{{ item.title }}</text>
                    <text class="content" v-if="item.content">{{ item.content }}</text>
                </view>
                <view class="card-bottom">
                    <view class="meta">
                        <view class="meta-item" v-if="item.voice_chat">
                            <u-icon name="mic" size="22" color="#52c41a"></u-icon>
                            <text>可语音</text>
                        </view>
                        <view class="meta-item" v-if="item.online_time">
                            <u-icon name="clock" size="22" color="#1890ff"></u-icon>
                            <text>{{ item.online_time }}</text>
                        </view>
                    </view>
                    <view class="stats">
                        <text class="score" v-if="item.score">⭐{{ item.score }}</text>
                        <text class="orders">{{ item.order_count || 0 }}单</text>
                    </view>
                </view>
            </view>

            <view class="empty" v-if="list.length === 0 && !loading">
                <u-icon name="search" size="120" color="#ccc"></u-icon>
                <text>暂无陪玩信息</text>
            </view>
            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="load-more-btn" v-if="hasMore && !loading && list.length > 0" @click="loadMore">
                <text>加载更多</text>
            </view>
            <view class="no-more" v-if="!hasMore && list.length > 0">
                <text>没有更多了</text>
            </view>
        </view>

        <!-- 底部按钮 -->
        <view class="bottom-bar">
            <view class="btn-my" @click="goMy">
                <u-icon name="account" size="24" color="#333"></u-icon>
                <text>我的</text>
            </view>
            <view class="btn-publish" @click="goPublish">
                <u-icon name="plus" size="24" color="#000"></u-icon>
                <text>发布陪玩</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getGameList, getGameTypes as fetchGameTypes, getServiceTypes as fetchServiceTypes } from '../../api/game'
import { img } from '@/utils/common'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_game')

const list = ref<any[]>([])
const gameTypes = ref<any[]>([])
const serviceTypes = ref<any[]>([])
const currentGame = ref('')
const currentService = ref('')
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)

const gameMap: Record<string, string> = {
    WZRY: '王者荣耀', LOL: '英雄联盟', PUBG: '和平精英',
    CSGO: 'CS2', YS: '原神', EGG: '蛋仔派对', OTHER: '其他'
}
const serviceMap: Record<string, string> = {
    PLAY_WITH: '陪玩', BOOST: '代练', TEACH: '教学', TEAM: '组队'
}

onMounted(() => {
    loadConfig()
    loadTypes()
    loadList()
})

onShow(() => {
    loadList(true)
})

const loadTypes = async () => {
    try {
        const [gRes, sRes]: any[] = await Promise.all([fetchGameTypes(), fetchServiceTypes()])
        if (gRes.code === 1) gameTypes.value = gRes.data || []
        if (sRes.code === 1) serviceTypes.value = sRes.data || []
    } catch (e) {
        gameTypes.value = Object.keys(gameMap).map(k => ({ value: k, label: gameMap[k] }))
        serviceTypes.value = Object.keys(serviceMap).map(k => ({ value: k, label: serviceMap[k] }))
    }
}

const loadList = async (refresh = false) => {
    if (loading.value) return
    if (refresh) { page.value = 1; hasMore.value = true }
    if (!hasMore.value) return

    loading.value = true
    try {
        const res: any = await getGameList({
            page: page.value, limit: 10,
            game_type: currentGame.value,
            service_type: currentService.value
        })
        if (res.code === 1) {
            const items = res.data?.list || []
            list.value = refresh ? items : [...list.value, ...items]
            hasMore.value = items.length >= 10
            if (items.length >= 10) page.value++
        }
    } catch (e) { console.error(e) }
    finally { loading.value = false }
}

const changeGame = (v: string) => { currentGame.value = v; loadList(true) }
const changeService = (v: string) => { currentService.value = v; loadList(true) }
const loadMore = () => loadList()

const getGameName = (t: string) => gameMap[t] || t
const getServiceName = (t: string) => serviceMap[t] || t

const goDetail = (id: number) => uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/game/detail?id=${id}` })
const goPublish = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/game/publish' })
const goMy = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/game/my' })
</script>

<style lang="scss" scoped>
.game-page { min-height: 100vh; background: #f5f5f5; padding-bottom: 160rpx; }

.filter-bar { background: #fff; padding: 16rpx 0; }
.type-tabs, .service-tabs { white-space: nowrap; padding: 8rpx 20rpx; }
.tab-item {
    display: inline-block; padding: 12rpx 28rpx; margin-right: 14rpx;
    border-radius: 30rpx; font-size: 26rpx; color: #666; background: #f5f5f5;
    &.active { background: #c0fe95; color: #333; font-weight: bold; }
}
.stab-item {
    display: inline-block; padding: 8rpx 24rpx; margin-right: 12rpx;
    border-radius: 20rpx; font-size: 24rpx; color: #999; background: #f9f9f9; border: 1rpx solid #eee;
    &.active { border-color: #333; color: #333; font-weight: bold; }
}

.list-wrap { padding: 20rpx; }

.game-card {
    background: #fff; border-radius: 20rpx; padding: 28rpx; margin-bottom: 20rpx;
    box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
}
.card-top { display: flex; align-items: center; }
.card-avatar { width: 90rpx; height: 90rpx; border-radius: 50%; margin-right: 20rpx; background: #f0f0f0; flex-shrink: 0; }
.card-info { flex: 1; overflow: hidden; }
.name-row { display: flex; align-items: center; gap: 10rpx; margin-bottom: 8rpx; }
.nickname { font-size: 30rpx; font-weight: bold; color: #333; }
.gender-tag {
    width: 36rpx; height: 36rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22rpx;
    &.g-1 { background: #e6f7ff; color: #1890ff; }
    &.g-2 { background: #fff0f5; color: #eb2f96; }
}
.tag-row { display: flex; gap: 10rpx; flex-wrap: wrap; }
.game-tag, .service-tag, .rank-tag {
    padding: 4rpx 14rpx; border-radius: 6rpx; font-size: 22rpx;
}
.game-tag { background: #e6f7ff; color: #1890ff; }
.service-tag { background: #f6ffed; color: #52c41a; }
.rank-tag { background: #fff7e6; color: #fa8c16; }

.price-box { text-align: right; flex-shrink: 0; margin-left: 16rpx; }
.price { font-size: 36rpx; font-weight: bold; color: #ff6b00; }
.unit { font-size: 22rpx; color: #999; }

.card-body { margin-top: 16rpx; }
.title { display: block; font-size: 28rpx; color: #333; font-weight: 600; margin-bottom: 6rpx; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.content { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 26rpx; color: #666; line-height: 1.5; }

.card-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 16rpx; padding-top: 16rpx; border-top: 1rpx solid #f5f5f5; }
.meta { display: flex; gap: 20rpx; }
.meta-item { display: flex; align-items: center; gap: 6rpx; font-size: 22rpx; color: #999; }
.stats { display: flex; align-items: center; gap: 16rpx; }
.score { font-size: 24rpx; color: #ff9500; }
.orders { font-size: 22rpx; color: #999; }

.bottom-bar {
    position: fixed; bottom: 40rpx; left: 40rpx; right: 40rpx;
    display: flex; gap: 20rpx; z-index: 100;
}
.btn-my {
    display: flex; align-items: center; gap: 8rpx; padding: 24rpx 32rpx;
    background: #fff; border-radius: 16rpx; box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.1);
    text { font-size: 28rpx; color: #333; font-weight: 600; }
}
.btn-publish {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 12rpx;
    padding: 24rpx; background: linear-gradient(to top, #aaf69b, #d1ff7c);
    border-radius: 16rpx; box-shadow: 0 4rpx 20rpx rgba(170,246,155,0.5);
    text { font-size: 30rpx; color: #000; font-weight: bold; }
}

.empty { display: flex; flex-direction: column; align-items: center; padding: 100rpx 0; text { font-size: 28rpx; color: #999; margin-top: 20rpx; } }
.loading-more, .no-more { padding: 24rpx 0; text-align: center; display: flex; justify-content: center; text { font-size: 24rpx; color: #999; } }
.load-more-btn { text-align: center; padding: 20rpx; text { font-size: 26rpx; color: #1890ff; } }
</style>
