<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="game-my" v-if="isFeatureEnabled">
        <!-- 状态筛选 -->
        <view class="status-tabs">
            <view class="tab" :class="{ active: currentStatus === '' }" @click="changeStatus('')">全部</view>
            <view class="tab" :class="{ active: currentStatus === '0' }" @click="changeStatus('0')">待审核</view>
            <view class="tab" :class="{ active: currentStatus === '1' }" @click="changeStatus('1')">上架中</view>
            <view class="tab" :class="{ active: currentStatus === '2' }" @click="changeStatus('2')">已下架</view>
            <view class="tab" :class="{ active: currentStatus === '3' }" @click="changeStatus('3')">已拒绝</view>
        </view>

        <!-- 列表 -->
        <view class="list-wrap">
            <view class="my-card" v-for="item in list" :key="item.id">
                <view class="card-header">
                    <text class="game-tag">{{ getGameName(item.game_type) }}</text>
                    <text class="service-tag">{{ getServiceName(item.service_type) }}</text>
                    <text class="status-tag" :class="'s-' + item.status">{{ getStatusName(item.status) }}</text>
                </view>
                <view class="card-body" @click="goDetail(item.id)">
                    <text class="title">{{ item.title }}</text>
                    <view class="school-row" v-if="item.school_name">
                        <u-icon name="home" size="14" color="#999"></u-icon>
                        <text class="school-name">{{ item.school_name }}</text>
                    </view>
                    <view class="price-row">
                        <text class="price">¥{{ item.price }}/{{ item.unit || '小时' }}</text>
                        <text class="stats">{{ item.view_count || 0 }}浏览 · {{ item.order_count || 0 }}单</text>
                    </view>
                </view>
                <view class="card-actions">
                    <view class="action-btn" @click="goEdit(item.id)">
                        <u-icon name="edit-pen" size="20" color="#1890ff"></u-icon>
                        <text style="color:#1890ff;">编辑</text>
                    </view>
                    <view class="action-btn" v-if="item.status === 1" @click="toggleStatus(item, 2)">
                        <u-icon name="eye-off" size="20" color="#fa8c16"></u-icon>
                        <text style="color:#fa8c16;">下架</text>
                    </view>
                    <view class="action-btn" v-if="item.status === 2" @click="toggleStatus(item, 1)">
                        <u-icon name="eye" size="20" color="#52c41a"></u-icon>
                        <text style="color:#52c41a;">上架</text>
                    </view>
                    <view class="action-btn" @click="deleteItem(item)">
                        <u-icon name="trash" size="20" color="#ff4d4f"></u-icon>
                        <text style="color:#ff4d4f;">删除</text>
                    </view>
                </view>
                <view class="refuse-reason" v-if="item.status === 3 && item.refuse_reason">
                    <text>拒绝原因：{{ item.refuse_reason }}</text>
                </view>
            </view>

            <view class="empty" v-if="list.length === 0 && !loading">
                <u-icon name="list" size="120" color="#ccc"></u-icon>
                <text>暂无陪玩信息</text>
                <view class="empty-btn" @click="goPublish">去发布</view>
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
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMyGameList, setGameStatus, delGame } from '../../api/game'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_game')

const list = ref<any[]>([])
const currentStatus = ref('')
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
const statusMap: Record<number, string> = { 0: '待审核', 1: '上架中', 2: '已下架', 3: '已拒绝' }

onShow(() => { loadConfig(); loadList(true) })

const loadList = async (refresh = false) => {
    if (loading.value) return
    if (refresh) { page.value = 1; hasMore.value = true }
    if (!hasMore.value) return

    loading.value = true
    try {
        const res: any = await getMyGameList({ page: page.value, limit: 10, status: currentStatus.value })
        if (res.code === 1) {
            const items = res.data?.list || []
            list.value = refresh ? items : [...list.value, ...items]
            hasMore.value = items.length >= 10
            if (items.length >= 10) page.value++
        }
    } catch (e) { console.error(e) }
    finally { loading.value = false }
}

const changeStatus = (s: string) => { currentStatus.value = s; loadList(true) }
const loadMore = () => loadList()

const getGameName = (t: string) => gameMap[t] || t
const getServiceName = (t: string) => serviceMap[t] || t
const getStatusName = (s: number) => statusMap[s] || '未知'

const toggleStatus = async (item: any, status: number) => {
    try {
        const res: any = await setGameStatus({ id: item.id, status })
        if (res.code === 1) {
            uni.showToast({ title: '操作成功', icon: 'success' })
            loadList(true)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.showToast({ title: e.msg || '操作失败', icon: 'none' })
    }
}

const deleteItem = (item: any) => {
    uni.showModal({
        title: '确认删除',
        content: '删除后无法恢复，确定删除？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const r: any = await delGame({ id: item.id })
                    if (r.code === 1) {
                        uni.showToast({ title: '删除成功', icon: 'success' })
                        loadList(true)
                    }
                } catch (e: any) {
                    uni.showToast({ title: e.msg || '删除失败', icon: 'none' })
                }
            }
        }
    })
}

const goDetail = (id: number) => uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/game/detail?id=${id}` })
const goEdit = (id: number) => uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/game/publish?id=${id}` })
const goPublish = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/game/publish' })
</script>

<style lang="scss" scoped>
.game-my { min-height: 100vh; background: #f5f5f5; }

.status-tabs {
    display: flex; background: #fff; padding: 20rpx;
    .tab { flex: 1; text-align: center; padding: 14rpx 0; font-size: 26rpx; color: #666; border-radius: 8rpx;
        &.active { background: #c0fe95; color: #333; font-weight: bold; }
    }
}

.list-wrap { padding: 20rpx; }

.my-card { background: #fff; border-radius: 16rpx; padding: 24rpx; margin-bottom: 20rpx; }
.card-header { display: flex; align-items: center; gap: 12rpx; margin-bottom: 16rpx; }
.game-tag { padding: 4rpx 14rpx; border-radius: 6rpx; font-size: 22rpx; background: #e6f7ff; color: #1890ff; }
.service-tag { padding: 4rpx 14rpx; border-radius: 6rpx; font-size: 22rpx; background: #f6ffed; color: #52c41a; }
.status-tag {
    margin-left: auto; padding: 4rpx 16rpx; border-radius: 6rpx; font-size: 22rpx;
    &.s-0 { background: #fff7e6; color: #fa8c16; }
    &.s-1 { background: #f6ffed; color: #52c41a; }
    &.s-2 { background: #f5f5f5; color: #999; }
    &.s-3 { background: #fff2f0; color: #ff4d4f; }
}

.card-body { margin-bottom: 16rpx; }
.title { display: block; font-size: 30rpx; font-weight: bold; color: #333; margin-bottom: 10rpx; }
.school-row { display: flex; align-items: center; gap: 6rpx; margin-bottom: 10rpx; }
.school-name { font-size: 24rpx; color: #999; }
.price-row { display: flex; justify-content: space-between; align-items: center; }
.price { font-size: 28rpx; color: #ff6b00; font-weight: bold; }
.stats { font-size: 24rpx; color: #999; }

.card-actions { display: flex; gap: 24rpx; padding-top: 16rpx; border-top: 1rpx solid #f0f0f0; }
.action-btn { display: flex; align-items: center; gap: 6rpx; font-size: 24rpx; }

.refuse-reason { margin-top: 12rpx; padding: 16rpx; background: #fff2f0; border-radius: 8rpx; font-size: 24rpx; color: #ff4d4f; }

.empty { display: flex; flex-direction: column; align-items: center; padding: 100rpx 0; text { font-size: 28rpx; color: #999; margin-top: 20rpx; } }
.empty-btn { margin-top: 24rpx; padding: 16rpx 48rpx; background: #c0fe95; border-radius: 12rpx; font-size: 28rpx; font-weight: bold; color: #333; }
.loading-more, .no-more { padding: 24rpx 0; text-align: center; display: flex; justify-content: center; text { font-size: 24rpx; color: #999; } }
.load-more-btn { text-align: center; padding: 20rpx; text { font-size: 26rpx; color: #1890ff; } }
</style>
