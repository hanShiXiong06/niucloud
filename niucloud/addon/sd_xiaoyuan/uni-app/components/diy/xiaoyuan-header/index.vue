<template>
    <view class="diy-xiaoyuan-header" :style="headerStyle">
        <!-- 状态栏占位 -->
        <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
        
        <!-- 导航栏 -->
        <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
            <view class="school-selector" @click="toLink(diyComponent.schoolUrl)">
                <u-icon name="map-fill" size="20" color="#333"></u-icon>
                <text class="school-name">{{ diyComponent.schoolName || '选择学校' }}</text>
                <u-icon name="arrow-down-fill" size="12" color="#333"></u-icon>
            </view>
            <view class="nav-actions">
                <view class="action-btn" @click="toLink(diyComponent.messageUrl)">
                    <u-icon name="bell" size="20" color="#333"></u-icon>
                </view>
            </view>
        </view>
        
        <!-- 统计信息栏 -->
        <view class="header-info-bar">
            <view class="info-item" @click="toLink(diyComponent.taskUrl)">
                <text class="info-value">{{ diyComponent.taskCount || 0 }}</text>
                <text class="info-label">{{ diyComponent.taskLabel || '今日任务' }}</text>
            </view>
            <view class="info-divider"></view>
            <view class="info-item" @click="toLink(diyComponent.earningUrl)">
                <text class="info-value">¥{{ diyComponent.earningAmount || '0.00' }}</text>
                <text class="info-label">{{ diyComponent.earningLabel || '累计佣金' }}</text>
            </view>
            <view class="info-divider"></view>
            <view class="search-entry" @click="toLink(diyComponent.searchUrl)">
                <u-icon name="search" size="16" color="#999"></u-icon>
                <text class="search-text">{{ diyComponent.searchPlaceholder || '搜索任务/服务' }}</text>
            </view>
        </view>
        
        <!-- 推广卡片（实名认证引导） -->
        <view class="promo-card" v-if="diyComponent.showPromoCard !== false" @click="toLink(diyComponent.promoUrl || '/addon/sd_xiaoyuan/pages/campus/auth')">
            <view class="promo-left">
                <view class="promo-title">{{ diyComponent.promoTitle || '校园帮实名认证' }}</view>
                <view class="promo-subtitle">{{ diyComponent.promoSubtitle || '安全可靠，快速认证' }}</view>
                <view class="go-btn">{{ diyComponent.promoBtnText || 'GO' }}</view>
            </view>
            <view class="promo-right">
                <u-icon :name="diyComponent.promoIcon || 'account-fill'" size="50" color="rgba(255,255,255,0.8)"></u-icon>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
})

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index] || props.component
    } else {
        return props.component
    }
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

const toLink = (url: string) => {
    if (diyStore.mode == 'decorate') return
    if (url) redirect({ url })
}
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-header {
    padding-bottom: 20rpx;
    
    .nav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 30rpx;
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
            margin-right: 20rpx;
            .action-btn {
                width: 60rpx;
                height: 60rpx;
                display: flex;
                align-items: center;
                justify-content: center;
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
