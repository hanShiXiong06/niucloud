<template>
    <view class="user-center" :style="{ paddingBottom: '180rpx' }">
        <!-- Custom Header -->
        <view class="custom-header">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
                <view class="settings-btn" @click="navigateTo('/app/pages/setting/index')">
                    <u-icon name="setting" size="24" color="#333"></u-icon>
                </view>
            </view>
        </view>

        <view class="user-header" :style="{ paddingTop: (statusBarHeight + navBarHeight) + 'px' }" @click="handleUserHeaderClick">
            <image class="avatar" :src="img(userInfo.headimg || userInfo.avatar || '/static/resource/images/default_headimg.png')"
                mode="aspectFill"></image>
            <view class="user-info">
                <text class="nickname">{{ userInfo.nickname || '未登录' }}</text>
                <view class="credit-row" v-if="userInfo.member_id">
                    <text class="member-id">ID: {{ userInfo.member_id }}</text>
                    <view class="credit-badge" @click.stop="goToCreditLog">
                        <u-icon name="star-fill" size="14" color="#ff9500"></u-icon>
                        <text>信誉 {{ creditScore }}分</text>
                        <u-icon name="arrow-right" size="12" color="#ff9500"></u-icon>
                    </view>
                </view>
                <text class="desc" v-if="!isLoggedIn">点击登录</text>
                <text class="desc" v-else>欢迎使用校园帮</text>
            </view>
        </view>

        <view class="menu-section" v-for="(group, index) in menuGroups" :key="index">
            <view class="section-title">{{ group.title }}</view>
            <view class="menu-grid">
                <view class="menu-item" v-for="(item, idx) in group.items" :key="idx" @click="navigateTo(item.url)">
                    <view class="menu-icon-bg" :style="{ background: item.bg }">
                        <u-icon :name="item.icon" size="32" :color="item.color"></u-icon>
                    </view>
                    <text>{{ item.name }}</text>
                </view>
            </view>
        </view>

        <!-- Shared Tabbar -->
        <custom-tabbar current="user" />
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { img } from '@/utils/common'
import { getCreditInfo } from '../../api/xiaoyuan'
import { getRunnerInfo } from '../../api/runner'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import customTabbar from '../../components/custom-tabbar.vue'

const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info || {})
const isLoggedIn = computed(() => !!userInfo.value?.member_id)
const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)
const creditScore = ref(100)

const menuGroups = ref([
    // 跑腿服务相关
    {
        title: '校园服务',
        items: [
            { name: '接单员主页', icon: 'home', url: '/addon/sd_xiaoyuan/pages/runner/index', color: '#fff', bg: 'linear-gradient(135deg, #52c41a 0%, #73d13d 100%)' },
            { name: '我的订单', icon: 'list', url: '/addon/sd_xiaoyuan/pages/order/list', color: '#fff', bg: 'linear-gradient(135deg, #ff7243 0%, #ff9a44 100%)' },
            { name: '我的地址', icon: 'map', url: '/addon/sd_xiaoyuan/pages/address/list', color: '#fff', bg: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)' },
    //     ]
    // },
    // // 校园生活相关
    // {
    //     title: '校园生活',
    //     items: [
            { name: '我的闲置', icon: 'shopping-cart', url: '/addon/sd_xiaoyuan/pages/secondhand/my', color: '#fff', bg: 'linear-gradient(135deg, #ff9a44 0%, #fc6076 100%)' },
            { name: '我的帖子', icon: 'edit-pen', url: '/addon/sd_xiaoyuan/pages/community/my', color: '#fff', bg: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
            { name: '我的表白', icon: 'heart-fill', url: '/addon/sd_xiaoyuan/pages/confession/my', color: '#fff', bg: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
            { name: '失物招领', icon: 'search', url: '/addon/sd_xiaoyuan/pages/lost_found/my', color: '#fff', bg: 'linear-gradient(135deg, #00c853 0%, #69f0ae 100%)' },
    //     ]
    // },
    // // 娱乐休闲相关
    // {
    //     title: '娱乐休闲',
    //     items: [
            { name: '我的游戏', icon: 'red-packet', url: '/addon/sd_xiaoyuan/pages/game/my', color: '#fff', bg: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
            // { name: '我的房源', icon: 'home', url: '/addon/sd_xiaoyuan/pages/house/my', color: '#fff', bg: 'linear-gradient(135deg, #cd9cf2 0%, #f6f3ff 100%)' },
            { name: '我的课表', icon: 'calendar', url: '/addon/sd_xiaoyuan/pages/schedule/index', color: '#fff', bg: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' }
        ]
    },
    // 推广相关
    {
        title: '推广赚钱',
        items: [
            { name: '邀请有礼', icon: 'gift', url: '/addon/sd_xiaoyuan/pages/invite/index', color: '#fff', bg: 'linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%)' },
            { name: '我的团队', icon: 'account-fill', url: '/addon/sd_xiaoyuan/pages/invite/team', color: '#fff', bg: 'linear-gradient(135deg, #ff9500 0%, #ffb347 100%)' },

            { name: '我的收益', icon: 'rmb-circle', url: '/app/pages/member/commission', color: '#fff', bg: 'linear-gradient(135deg, #ff9500 0%, #ffb347 100%)' },
            { name: '佣金提现', icon: 'red-packet', url: '/app/pages/member/apply_cash_out', color: '#fff', bg: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
            { name: '提现记录', icon: 'list', url: '/app/pages/member/cash_out', color: '#fff', bg: 'linear-gradient(135deg, #52c41a 0%, #73d13d 100%)' },
        ]
    },
    // 账户相关
    {
        title: '账户中心',
        items: [
            { name: '每日签到', icon: 'checkmark-circle', url: '/addon/sd_xiaoyuan/pages/sign/index', color: '#fff', bg: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
            { name: '积分商城', icon: 'gift', url: '/addon/sd_xiaoyuan/pages/points/mall', color: '#fff', bg: 'linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%)' },
            { name: '兑换记录', icon: 'order', url: '/addon/sd_xiaoyuan/pages/points/orders', color: '#fff', bg: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
            // { name: '我的优惠券', icon: 'coupon', url: '/addon/sd_xiaoyuan/pages/coupon/list', color: '#fff', bg: 'linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%)' },
            { name: '我的评价', icon: 'star', url: '/addon/sd_xiaoyuan/pages/user/evaluates', color: '#fff', bg: 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)' },
            { name: '校园认证', icon: 'integral', url: '/addon/sd_xiaoyuan/pages/campus/auth', color: '#fff', bg: 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)' }
        ]
    },
])

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    uni.hideTabBar()
})

onShow(() => {
    loadUserInfo()
    // 只有登录后才加载信用分和接单员状态
    if (isLoggedIn.value) {
        loadCreditScore()
        loadRunnerStatus()
    }
    tryBindFenxiao()
    uni.hideTabBar()
})

const loadUserInfo = () => {
    // userInfo is now a computed property from memberStore
}

const loadCreditScore = async () => {
    try {
        const res: any = await getCreditInfo()
        if (res.code === 1 && res.data) {
            creditScore.value = res.data.credit_score ?? 100
        }
    } catch (e) {
        console.error(e)
    }
}

const loadRunnerStatus = async () => {
    try {
        const res: any = await getRunnerInfo()
        if (res.code === 1 && res.data && res.data.id) {
            // 已申请过接单员，更新菜单
            const group = menuGroups.value.find(g => g.title === '推广赚钱')
            if (group) {
                const item = group.items.find(i => i.name === '成为接单员' || i.name === '接单员主页')
                if (item) {
                    item.name = '接单员主页'
                    item.url = '/addon/sd_xiaoyuan/pages/runner/index'
                    item.icon = 'car'
                }
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const navigateTo = (url: string) => {
    if (!url) return
    // 检测是否登录
    if (!isLoggedIn.value) {
        useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/user/index' })
        return
    }
    uni.navigateTo({ url })
}

const goToCreditLog = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/credit/log' })
}

const handleUserHeaderClick = () => {
    // 未登录时点击头像跳转登录
    if (!isLoggedIn.value) {
        useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/user/index' })
    }
}

const reLaunch = (url: string) => {
    uni.reLaunch({ url })
}
</script>

<style lang="scss" scoped>
.user-center {
    min-height: 100vh;
    background: #f7f7f7;
    font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
}

.custom-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;

    .status-bar {
        width: 100%;
    }

    .nav-bar {
        height: 44px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding: 0 30rpx;

        .settings-btn {
            width: 60rpx;
            height: 60rpx;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
}

.user-header {
    display: flex;
    align-items: center;
    padding: 0 30rpx 60rpx;
    background: linear-gradient(135deg, #c0fe95, #f7f7f7);
    margin-bottom: -30rpx;

    .avatar {
        width: 120rpx;
        height: 120rpx;
        border-radius: 50%;
        border: 4rpx solid rgba(255, 255, 255, 0.8);
        margin-right: 24rpx;
        background: #fff;
    }

    .user-info {
        .nickname {
            display: block;
            font-size: 34rpx;
            font-weight: 900;
            color: #333;
            margin-bottom: 8rpx;
        }

        .credit-row {
            display: flex;
            align-items: center;
            margin-bottom: 4rpx;
        }

        .member-id {
            font-size: 24rpx;
            color: #666;
            margin-right: 16rpx;
        }

        .credit-badge {
            display: flex;
            align-items: center;
            background: rgba(255, 149, 0, 0.15);
            border-radius: 20rpx;
            padding: 2rpx 14rpx;

            text {
                font-size: 22rpx;
                color: #ff9500;
                margin-left: 4rpx;
                font-weight: bold;
            }
        }

        .desc {
            display: block;
            font-size: 26rpx;
            color: #666;
        }
    }
}

.menu-section {
    background: #fff;
    margin: 24rpx;
    border-radius: 24rpx;
    padding: 30rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
}

.section-title {
    font-size: 30rpx;
    font-weight: 900;
    color: #333;
    margin-bottom: 30rpx;
    padding-left: 20rpx;
    border-left: 8rpx solid #c0fe95;
    line-height: 1;
}

.menu-grid {
    display: flex;
    flex-wrap: wrap;
}

.menu-item {
    width: 20%;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20rpx 0;

    .menu-icon-bg {
        width: 88rpx;
        height: 88rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12rpx;
        box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.1);
    }

    text:last-child {
        font-size: 24rpx;
        color: #333;
        margin-top: 4rpx;
        font-weight: 500;
    }
}

</style>
