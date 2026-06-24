<template>
    <view class="diy-xiaoyuan-header" :style="headerStyle">
        <!-- 导航栏 -->
        <view class="nav-bar">
            <view class="school-selector" @click="goSchoolSelect">
                <u-icon name="map-fill" size="20" color="#333"></u-icon>
                <text class="school-name">{{ schoolDisplayName }}</text>
                <u-icon name="arrow-down-fill" size="12" color="#333"></u-icon>
            </view>
            <view class="nav-actions">
                <view class="action-btn" @click="toLink(diyComponent.messageUrl)">
                    <u-icon name="bell-fill" size="22" color="#333"></u-icon>
                    <view class="message-badge" v-if="unreadCount > 0">
                        <text class="badge-text">{{ unreadCount > 99 ? '99+' : unreadCount }}</text>
                    </view>
                </view>
            </view>
        </view>
        
        <!-- 统计信息栏 -->
        <view class="header-info-bar">
            <view v-if="showTaskStat" class="info-item" @click="toLink(diyComponent.taskUrl)">
                <text class="info-value">{{ displayTaskCount }}</text>
                <text class="info-label">{{ diyComponent.taskLabel || '今日任务' }}</text>
            </view>
            <view v-if="showTaskStat && showEarningStat" class="info-divider"></view>
            <view v-if="showEarningStat" class="info-item" @click="toLink(diyComponent.earningUrl)">
                <text class="info-value">¥{{ displayEarningAmount }}</text>
                <text class="info-label">{{ diyComponent.earningLabel || '累计佣金' }}</text>
            </view>
            <view v-if="showTaskStat || showEarningStat" class="info-divider"></view>
            <view class="search-entry" @click="toLink(diyComponent.searchUrl)">
                <u-icon name="search" size="16" color="#999"></u-icon>
                <text class="search-text">{{ diyComponent.searchPlaceholder || '搜索任务/服务' }}</text>
            </view>
        </view>
        
        <!-- 推广卡片（实名认证引导） -->
        <view class="promo-card" v-if="showPromoCard" @click="toLink(diyComponent.promoUrl || '/addon/sd_xiaoyuan/pages/campus/auth')">
            <view class="promo-left">
                <view class="promo-title">{{ diyComponent.promoTitle || '校园帮实名认证' }}</view>
                <view class="promo-subtitle">{{ diyComponent.promoSubtitle || '安全可靠，快速认证' }}</view>
                <view class="go-btn">{{ diyComponent.promoBtnText || 'GO' }}</view>
            </view>
            <view class="promo-right">
                <image v-if="diyComponent.promoIconImage" class="promo-icon-image" :src="img(diyComponent.promoIconImage)" mode="aspectFit" />
                <u-icon v-else :name="diyComponent.promoIcon || 'account-fill'" size="50" color="rgba(255,255,255,0.8)"></u-icon>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { redirect, img } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import { getUnreadMessageCount, getConfig, getHomeStats } from '@/addon/sd_xiaoyuan/api/xiaoyuan'
import { ensureXiaoyuanDefaultSchool } from '@/addon/sd_xiaoyuan/composables/useXiaoyuanDefaultSchool'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const unreadCount = ref(0)
const requireAuth = ref(true) // 系统是否需要认证
const currentSchoolName = ref('')
/** 接口返回的今日订单数（与后台抽成无关，仅统计） */
const liveOrderCount = ref<number | null>(null)
/** 已完成订单接单员收益合计，与结算字段 runner_income 一致 */
const liveRunnerIncome = ref<string | null>(null)

onMounted(() => {
    loadUnreadCount()
    uni.$on('xiaoyuan_school_changed', onSchoolChanged)
    ;(async () => {
        await loadSystemConfig()
        await ensureXiaoyuanDefaultSchool()
        refreshCurrentSchool()
        loadHomeStats()
    })()
})

onUnmounted(() => {
    uni.$off('xiaoyuan_school_changed', onSchoolChanged)
})

const onSchoolChanged = () => {
    refreshCurrentSchool()
    loadHomeStats()
}

const loadSystemConfig = async () => {
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            // require_auth_publish: 1=需要认证, 0=不需要认证
            requireAuth.value = res.data.require_auth_publish !== 0
        }
    } catch (e) {
        console.error('获取配置失败:', e)
    }
}

const loadHomeStats = async () => {
    if (diyStore.mode === 'decorate') {
        return
    }
    try {
        const school = uni.getStorageSync('current_school')
        const schoolId = school && school.id ? parseInt(String(school.id), 10) : 0
        const params: Record<string, number> = {}
        if (schoolId > 0) {
            params.school_id = schoolId
        }
        const res: any = await getHomeStats(params)
        if (res.code === 1 && res.data) {
            liveOrderCount.value = parseInt(String(res.data.order_count ?? 0), 10) || 0
            const raw = res.data.total_runner_income ?? res.data.user_count ?? 0
            const n = Number(raw)
            liveRunnerIncome.value = Number.isFinite(n) ? n.toFixed(2) : '0.00'
        }
    } catch (e) {
        console.error('home_stats', e)
    }
}

const loadUnreadCount = async () => {
    try {
        const res: any = await getUnreadMessageCount()
        if (res.code === 1 && res.data) {
            unreadCount.value = res.data.count || res.data.total || 0
        }
    } catch (e) {
        console.error('获取未读消息数量失败:', e)
    }
}

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index] || props.component
    } else {
        return props.component
    }
})

const clampInt = (raw: unknown, max: number) => {
    const n = parseInt(String(raw ?? 0), 10)
    if (!Number.isFinite(n) || n < 0) return 0
    return Math.min(n, max)
}

const clampMoney = (raw: unknown, max: number) => {
    const n = Number(raw)
    if (!Number.isFinite(n) || n < 0) return 0
    return Math.min(n, max)
}

const taskVirtualAdd = computed(() => clampInt(diyComponent.value.taskVirtualAdd, 999999))
const earningVirtualAdd = computed(() => clampMoney(diyComponent.value.earningVirtualAdd, 9999999.99))

const showTaskStat = computed(() => diyComponent.value.showTaskStat !== false)
const showEarningStat = computed(() => diyComponent.value.showEarningStat !== false)

const schoolDisplayName = computed(() => {
    return currentSchoolName.value || '选择学校'
})

const displayTaskCount = computed(() => {
    let base = 0
    if (diyStore.mode === 'decorate') {
        base = Number(diyComponent.value.taskCount ?? 0) || 0
    } else if (liveOrderCount.value !== null) {
        base = liveOrderCount.value
    } else {
        base = Number(diyComponent.value.taskCount ?? 0) || 0
    }
    return base + taskVirtualAdd.value
})

const displayEarningAmount = computed(() => {
    let base = 0
    const ph = String(diyComponent.value.earningAmount || '0.00').replace(/[^\d.-]/g, '')
    const parsed = Number(ph)
    if (diyStore.mode === 'decorate') {
        base = Number.isFinite(parsed) ? parsed : 0
    } else if (liveRunnerIncome.value !== null) {
        base = Number(liveRunnerIncome.value)
        if (!Number.isFinite(base)) base = 0
    } else {
        base = Number.isFinite(parsed) ? parsed : 0
    }
    const sum = base + earningVirtualAdd.value
    return sum.toFixed(2)
})

// 是否显示推广卡片
const showPromoCard = computed(() => {
    // 装修模式下总是显示
    if (diyStore.mode == 'decorate') {
        return diyComponent.value.showPromoCard !== false
    }
    // 非装修模式下，需要同时满足：组件配置显示 && 系统配置需要认证
    return diyComponent.value.showPromoCard !== false && requireAuth.value
})

const headerStyle = computed(() => {
    let style = ''
    if (diyComponent.value) {
        const bgStart = diyComponent.value.bgStartColor || '#c0fe95'
        const bgEnd = diyComponent.value.bgEndColor || '#e8ffcc'
        style += `background: linear-gradient(180deg, ${bgStart} 0%, ${bgEnd} 100%);`
    }
    return style
})

const toLink = (link: any) => {
    if (diyStore.mode == 'decorate') return
    let url = ''
    if (typeof link === 'string') {
        url = link
    } else if (link && typeof link === 'object') {
        url = link.wap_url || link.url || ''
    }
    if (url) redirect({ url })
}

const refreshCurrentSchool = () => {
    const school = uni.getStorageSync('current_school')
    currentSchoolName.value = school?.name || ''
}

const goSchoolSelect = () => {
    if (diyStore.mode == 'decorate') return
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/school/select' })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-header {
    padding-top: 0;
    padding-bottom: 20rpx;
    
    .nav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0rpx 30rpx 20rpx;
        box-sizing: border-box;
        
        .school-selector {
            display: flex;
            align-items: center;
            gap: 8rpx;
            
            .school-name {
                font-size: 28rpx;
                font-weight: 600;
                color: #333;
            }
        }
        
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16rpx;

            .action-btn {
                width: 60rpx;
                height: 60rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                
                .message-badge {
                    position: absolute;
                    top: 0rpx;
                    right: -20rpx;
                    min-width: 32rpx;
                    height: 32rpx;
                    background: #ff4d4f;
                    border-radius: 16rpx;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 8rpx;
                    border: 2rpx solid #fff;
                    z-index: 10;
                    
                    .badge-text {
                        font-size: 20rpx;
                        color: #fff;
                        line-height: 1;
                        transform: scale(0.85);
                    }
                }
            }
        }
    }
    
    .promo-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 16rpx 30rpx;
        padding: 24rpx 30rpx;
        background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
        border-radius: 20rpx;
        box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.05);
        
        .promo-left {
            .promo-title {
                font-size: 28rpx;
                font-weight: bold;
                color: #333;
                margin-bottom: 6rpx;
            }
            
            .promo-subtitle {
                font-size: 22rpx;
                color: #666;
                margin-bottom: 12rpx;
            }
            
            .go-btn {
                display: inline-block;
                padding: 6rpx 20rpx;
                background: #333;
                color: #fff;
                font-size: 22rpx;
                border-radius: 16rpx;
            }
        }
        
        .promo-right {
            width: 80rpx;
            height: 80rpx;
            background: linear-gradient(135deg, #c0fe95, #a8e063);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;

            .promo-icon-image {
                width: 52rpx;
                height: 52rpx;
            }
        }
    }
    
    .header-info-bar {
        display: flex;
        align-items: center;
        margin: 0 30rpx;
        padding: 20rpx 30rpx;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 20rpx;
        
        .info-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 20rpx;
            
            .info-value {
                font-size: 32rpx;
                font-weight: bold;
                color: #333;
            }
            
            .info-label {
                font-size: 22rpx;
                color: #666;
                margin-top: 4rpx;
            }
        }
        
        .info-divider {
            width: 1rpx;
            height: 50rpx;
            background: #ddd;
        }
        
        .search-entry {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10rpx;
            margin-left: 20rpx;
            padding: 16rpx 24rpx;
            background: #f5f5f5;
            border-radius: 30rpx;
            
            .search-text {
                font-size: 24rpx;
                color: #999;
            }
        }
    }
}
</style>
