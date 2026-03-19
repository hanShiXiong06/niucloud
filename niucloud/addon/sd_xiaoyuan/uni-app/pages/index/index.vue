<template>
    <view class="index-page">
        <!-- Page Background Gradient (separate from header to avoid z-index issues) -->
        <view class="page-bg"></view>

        <!-- Custom Header -->
        <view class="custom-header">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>

            <!-- Nav Bar -->
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonInfo.right + 'px' }">
                <view class="school-selector" @click="handleSchoolClick">
                    <image v-if="currentSchool?.logo" class="school-logo" :src="img(currentSchool.logo)" mode="aspectFill"></image>
                    <u-icon v-else name="map-fill" size="20" color="#333"></u-icon>
                    <text class="school-name">{{ currentSchool?.name || '选择学校' }}</text>
                    <u-icon name="arrow-down-fill" size="12" color="#333"></u-icon>
                </view>
                <view class="nav-actions">
                    <view class="action-btn" @click="navigateTo('/addon/sd_xiaoyuan/pages/message/index')">
                        <u-icon name="bell" size="20" color="#333"></u-icon>
                        <view class="badge" v-if="unreadCount > 0">{{ unreadCount }}</view>
                    </view>
                </view>
            </view>

            <!-- Quick Info Bar -->
            <view class="header-info-bar">
                <view class="info-item" @click="navigateTo('/addon/sd_xiaoyuan/pages/order/hall')">
                    <text class="info-value">{{ stats.order_count || 0 }}</text>
                    <text class="info-label">今日任务</text>
                </view>
                <view class="info-divider"></view>
                <view class="info-item" @click="navigateTo('/addon/sd_xiaoyuan/pages/runner/index')">
                    <text class="info-value">¥{{ (parseFloat(String(stats.user_count || 0))).toFixed(2) }}</text>
                    <text class="info-label">累计佣金</text>
                </view>
                <view class="info-divider"></view>
                <view class="search-entry" @click="navigateTo('/addon/sd_xiaoyuan/pages/search/index')">
                    <u-icon name="search" size="16" color="#999"></u-icon>
                    <text class="search-text">搜索任务/服务</text>
                </view>
            </view>
        </view>

        <view class="main-content" :style="{ paddingTop: (statusBarHeight + navBarHeight + 50) + 'px' }">
            <!-- Promo Card -->
            <view style="height: 50rpx;"></view>
            <view class="promo-section">
                <!-- 未认证: 引导认证 -->
                <view class="promo-card" v-if="!isAuthed" @click="goTo('/addon/sd_xiaoyuan/pages/campus/auth')">
                    <view class="promo-left">
                        <view class="promo-title">校园帮互助实名认证</view>
                        <view class="promo-subtitle">安全可靠，快速认证</view>
                        <view class="go-btn">GO</view>
                    </view>
                    <view class="promo-right">
                        <u-icon name="account-fill" size="60" color="#fff"></u-icon>
                    </view>
                </view>
                <!-- 已认证: 显示快捷操作 -->
                <view class="promo-card authed" v-else>
                    <view class="promo-left">
                        <view class="promo-title">Hi, {{ userInfo.nickname || '同学' }} 👋</view>
                        <view class="promo-subtitle">今天需要什么帮助?</view>
                    </view>
                    <view class="promo-actions">
                        <view class="action-pill" @click="openPublish">
                            <u-icon name="edit-pen" size="16" color="#333"></u-icon>
                            <text>发布任务</text>
                        </view>
                        <view class="action-pill" @click="navigateTo('/addon/sd_xiaoyuan/pages/order/list')">
                            <u-icon name="list" size="16" color="#333"></u-icon>
                            <text>我的订单</text>
                        </view>
                    </view>
                </view>
            </view>


            <!-- 公告区域 -->
            <view class="notice-section">
                <u-notice-bar 
                    :text="noticeText || '欢迎使用校园帮，有问题请联系客服~'"
                    icon="volume"
                    color="#ff9500"
                    bgColor="#fff7e6"
                    @click="goToNotice"
                ></u-notice-bar>
            </view>
            <!-- Menu Grid -->
            <view class="menu-section" v-if="config">
                <view class="menu-item" v-for="(item, index) in menuList" :key="index" @click="handleMenuClick(item)">
                    <view class="menu-icon" :style="{ background: item.bgColor, border: item.border }">
                        <u-icon :name="item.icon" size="28" :color="item.iconColor || '#333'"></u-icon>
                    </view>
                    <text class="menu-name">{{ item.name }}</text>
                </view>
            </view>

            <!-- Feature Banner Row 1: 房屋租赁 + 课程表 + 拼单好饭 -->
            <view class="feature-banners feature-banners-three" v-if="config">
                <view v-if="config.enable_house !== 0" class="feature-banner banner-house" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/house/index' })">
                    <view class="banner-info">
                        <text class="banner-title">房屋租赁</text>
                        <text class="banner-desc">校园周边好房</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="home-fill" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
                <view v-if="config.enable_schedule !== 0" class="feature-banner banner-schedule" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/schedule/index' })">
                    <view class="banner-info">
                        <text class="banner-title">课程表</text>
                        <text class="banner-desc">查看课程安排</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="calendar-fill" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
                <view v-if="config.enable_group !== 0" class="feature-banner banner-group" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/group/index' })">
                    <view class="banner-info">
                        <text class="banner-title">拼单好饭</text>
                        <text class="banner-desc">一起拼更划算</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="coupon-fill" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
            </view>

            <!-- Feature Banner Row 2: 闲置 + 失物招领 + 树洞 -->
            <view class="feature-banners feature-banners-three" v-if="config">
                <view v-if="config.enable_secondhand !== 0" class="feature-banner banner-secondhand" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/secondhand/index' })">
                    <view class="banner-info">
                        <text class="banner-title">闲置市场</text>
                        <text class="banner-desc">好物低价转</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="bag-fill" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
                <view v-if="config.enable_lost_found !== 0" class="feature-banner banner-lost" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/lost_found/index' })">
                    <view class="banner-info">
                        <text class="banner-title">失物招领</text>
                        <text class="banner-desc">帮你找回来</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="search" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
                <view v-if="config.enable_community !== 0" class="feature-banner banner-community" @click="handleMenuClick({ url: '/addon/sd_xiaoyuan/pages/community/index' })">
                    <view class="banner-info">
                        <text class="banner-title">校园树洞</text>
                        <text class="banner-desc">匿名说心事</text>
                    </view>
                    <view class="banner-icon">
                        <u-icon name="chat-fill" size="32" color="rgba(255,255,255,0.85)"></u-icon>
                    </view>
                </view>
            </view>

            <!-- 任务大厅 -->
            <view class="my-orders-section">
                <view class="section-header">
                    <text class="section-title">任务大厅</text>
                    <text class="more-link" @click="navigateTo('/addon/sd_xiaoyuan/pages/order/hall')">更多</text>
                </view>
                <scroll-view scroll-x class="task-type-scroll">
                    <view class="task-type-tabs">
                        <view class="type-tab" :class="{ active: currentTab === 0 }" @click="switchTab(0)">全部</view>
                        <view class="type-tab" :class="{ active: currentTab === 1 }" @click="switchTab(1)">代取快递</view>
                        <view class="type-tab" :class="{ active: currentTab === 2 }" @click="switchTab(2)">帮我买</view>
                        <view class="type-tab" :class="{ active: currentTab === 3 }" @click="switchTab(3)">代打印</view>
                        <view class="type-tab" :class="{ active: currentTab === 4 }" @click="switchTab(4)">扔垃圾</view>
                        <view class="type-tab" :class="{ active: currentTab === 5 }" @click="switchTab(5)">帮搬运</view>
                        <view class="type-tab" :class="{ active: currentTab === 6 }" @click="switchTab(6)">代清洁</view>
                        <view class="type-tab" :class="{ active: currentTab === 7 }" @click="switchTab(7)">帮帮忙</view>
                        <view class="type-tab" :class="{ active: currentTab === 8 }" @click="switchTab(8)">游戏陪玩</view>
                    </view>
                </scroll-view>

                <view class="orders-list" v-if="pendingOrders.length > 0">
                    <sd-order-item 
                        v-for="(order, index) in pendingOrders.slice(0, 3)" 
                        :key="order.id"
                        :order="order"
                        :show-user="true"
                        @click="goToOrderDetail(order.id)"
                    >
                        <template #actions>
                            <button class="accept-btn" @click.stop="acceptOrder(order.id)">接单</button>
                        </template>
                    </sd-order-item>
                </view>
            </view>

            <view style="height: 180rpx;"></view>
        </view>

        <!-- Shared Tabbar + Popups -->
        <custom-tabbar current="home" :isAuthed="isAuthed" ref="tabbarRef" />
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getConfig, getOrderHall, getCampusAuthInfo, getUnreadMessageCount, getSchoolList, getHomeStats } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import { getOrderList, cancelOrder as cancelOrderApi, payOrder as payOrderApi } from '../../api/xiaoyuan'
import { getRunnerInfo, acceptOrder as acceptOrderApi } from '../../api/runner'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { img } from '@/utils/common'
import customTabbar from '../../components/custom-tabbar.vue'
import sdOrderItem from '../../components/sd-order-item.vue'

const memberStore = useMemberStore()
const userInfo = computed(() => memberStore.info || {})
const isLoggedIn = computed(() => !!userInfo.value?.member_id)
const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonInfo = ref({ top: 0, height: 0, right: 0 })
const unreadCount = ref(0)
const myOrders = ref<any[]>([])
const currentTab = ref(0)
const tabTypes = ['', 'EXPRESS', 'BUY', 'PRINT', 'TRASH', 'CARRY', 'CLEAN', 'HELP', 'GAME']

const switchTab = (index: number) => {
    currentTab.value = index
    loadMyOrders()
}

const pendingOrders = computed(() => {
    return myOrders.value.filter(order => order.status === 10)
})
const currentSchool = ref<any>({ name: '请选择学校', id: 0 })
const showSuccessPopup = ref(false)
const showSchoolPopup = ref(false)
const schoolList = ref<any[]>([])
const noticeText = ref('')
const isAuthed = ref(false)
const stats = ref({
    goods_count: 0,
    view_count: 0,
    order_count: 0,
    user_count: 0
})

const config = ref<any>(null)

const allMenuItems = [
    { name: '帮我买', icon: 'shopping-cart-fill', url: '/addon/sd_xiaoyuan/pages/buy/create', iconColor: '#ff6b00', bgColor: '#fff4e6', border: 'none', key: 'enable_buy' },
    { name: '帮我送', icon: 'car', url: '/addon/sd_xiaoyuan/pages/send/create', iconColor: '#13c2c2', bgColor: '#e6fffb', border: 'none', key: 'enable_send' },
    { name: '代取快递', icon: 'gift-fill', url: '/addon/sd_xiaoyuan/pages/express/pickup', iconColor: '#52c41a', bgColor: '#f6ffed', border: 'none', key: 'enable_express' },
    { name: '帮打印', icon: 'file-text-fill', url: '/addon/sd_xiaoyuan/pages/print/create', iconColor: '#ff9800', bgColor: '#fff8e1', border: 'none', key: 'enable_print' },
    { name: '扔垃圾', icon: 'trash-fill', url: '/addon/sd_xiaoyuan/pages/trash/create', iconColor: '#9c27b0', bgColor: '#f3e5f5', border: 'none', key: 'enable_trash' },
    { name: '帮搬运', icon: 'car-fill', url: '/addon/sd_xiaoyuan/pages/carry/create', iconColor: '#4caf50', bgColor: '#e8f5e9', border: 'none', key: 'enable_carry' },
    { name: '代清洁', icon: 'star-fill', url: '/addon/sd_xiaoyuan/pages/clean/create', iconColor: '#2196f3', bgColor: '#e3f2fd', border: 'none', key: 'enable_clean' },
    { name: '帮帮忙', icon: 'question-circle-fill', url: '/addon/sd_xiaoyuan/pages/help/create', iconColor: '#e91e63', bgColor: '#fce4ec', border: 'none', key: 'enable_help' },
    { name: '表白墙', icon: 'heart-fill', url: '/addon/sd_xiaoyuan/pages/confession/index', iconColor: '#fa709a', bgColor: '#fff0f5', border: 'none', key: 'enable_confession' },
    { name: '游戏陪练', icon: 'red-packet-fill', url: '/addon/sd_xiaoyuan/pages/game/publish', iconColor: '#ff7243', bgColor: '#fff3e0', border: 'none', key: 'enable_game' },
]

const menuList = computed(() => {
    if (!config.value) return allMenuItems
    return allMenuItems.filter(item => config.value[item.key] !== 0)
})

const tabbarRef = ref<any>(null)

const feedList = ref<any[]>([])

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonInfo.value = {
        top: menuButton.top,
        height: menuButton.height,
        right: sysInfo.windowWidth - menuButton.left
    }
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadSchools()
    loadAuthStatus()
    loadStats()
    loadMyOrders()
    loadFeatureConfig()
    uni.hideTabBar()
})

onShow(() => {
    const cachedSchool = uni.getStorageSync('current_school')
    if (cachedSchool && cachedSchool.id !== currentSchool.value.id) {
        currentSchool.value = cachedSchool
        loadData()
    }
    loadUnreadCount()
    loadMyOrders()
    loadAuthStatus()
    loadStats()
    tryBindFenxiao()
    uni.hideTabBar()
})

const loadAuthStatus = async () => {
    try {
        const res: any = await getCampusAuthInfo()
        if (res.code === 1 && res.data) {
            isAuthed.value = res.data.status === 1 || res.data.status === 'passed'
        }
    } catch (e) {
        console.error('获取认证状态失败:', e)
    }
}

const loadFeatureConfig = async () => {
    // 先从缓存读取配置
    const cachedConfig = uni.getStorageSync('xiaoyuan_config')
    if (cachedConfig) {
        config.value = cachedConfig
    }
    
    // 异步请求最新配置
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            // 更新配置
            config.value = res.data
            // 缓存到本地
            uni.setStorageSync('xiaoyuan_config', res.data)
        }
    } catch (e) {
        console.error('获取配置失败:', e)
        // 如果请求失败且没有缓存，使用默认配置（全部开启）
        if (!cachedConfig) {
            config.value = {
                enable_buy: 1,
                enable_send: 1,
                enable_express: 1,
                enable_print: 1,
                enable_trash: 1,
                enable_carry: 1,
                enable_clean: 1,
                enable_help: 1,
                enable_game: 1,
                enable_house: 1,
                enable_schedule: 1,
                enable_group: 1,
                enable_secondhand: 1,
                enable_lost_found: 1,
                enable_community: 1,
                enable_confession: 1,
                enable_sign: 1,
                enable_points_mall: 1,
            }
        }
    }
}

// 上拉加载更多
onReachBottom(() => {
    // 这里可以添加上拉加载更多逻辑
    console.log('触发上拉加载')
})

const loadStats = async () => {
    try {
        const params: any = {}
        if (currentSchool.value.id) {
            params.school_id = currentSchool.value.id
        }
        const res: any = await getHomeStats(params)
        if (res.code === 1) {
            stats.value = res.data
        }
    } catch (e: any) {
        console.error(e)
    }
}

const loadSchools = async () => {
    try {
        const res: any = await getSchoolList()
        if (res.code === 1) {
            schoolList.value = res.data.list || res.data || []
            // 如果有缓存的学校，优先使用缓存
            const cachedSchool = uni.getStorageSync('current_school')
            if (cachedSchool) {
                currentSchool.value = cachedSchool
            } else if (schoolList.value.length > 0) {
                // 默认选中第一个
                currentSchool.value = schoolList.value[0]
                uni.setStorageSync('current_school', schoolList.value[0])
            }
            // 加载数据
            loadData()
        }
    } catch (e: any) {
        console.error(e)
    }
}

const loadData = async () => {
    loadOrderStat()
    loadStats()
    loadMyOrders()
}

const loadOrderStat = async () => {
    try {
        const params: any = {
            page: 1,
            limit: 10,
        }
        if (currentSchool.value.id) {
            params.school_id = currentSchool.value.id
        }
        if (currentTab.value > 0) {
            params.task_type = tabTypes[currentTab.value]
        }

        const res: any = await getOrderHall(params)
        if (res.code === 1 && res.data.list) {
            feedList.value = res.data.list.map((item: any) => {
                return {
                    id: item.id,
                    avatar: item.member_headimg || '',
                    nickname: item.member_nickname || '校园同学',
                    title: formatOrderTitle(item),
                    create_time: item.create_time || '',
                    activity_time: item.create_time || '',
                    location: item.pickup_address || '',
                    tag1: parseFloat(item.weight) > 0 ? `${parseFloat(item.weight)}kg` : '',
                    tag2: parseFloat(item.distance) > 0 ? `${parseFloat(item.distance)}km` : '',
                    tag3: item.is_urgent == 1 ? '加急' : '',
                    price: item.total_fee
                }
            })
        } else {
            feedList.value = []
        }
    } catch (e: any) {
        console.error(e)
        feedList.value = []
    }
}


const formatOrderTitle = (item: any) => {
    const typeMap: Record<string, string> = {
        'BUY': '帮我买',
        'ERRAND': '帮我送',
        'EXPRESS': '代取快递',
        'PRINT': '帮打印',
        'QUEUE': '代排队',
        'SEAT': '代占座'
    }
    const typeName = typeMap[item.task_type] || '校园服务'
    return `${typeName} - ${item.remark || item.goods_name || '无备注'}`
}



const loadUnreadCount = async () => {
    try {
        const res: any = await getUnreadMessageCount()
        if (res.code === 1) {
            unreadCount.value = res.data.count || 0
        }
    } catch (e: any) {
        console.error(e)
    }
}

const navigateTo = (url: string) => {
    uni.navigateTo({ url })
}

const reLaunch = (url: string) => {
    uni.reLaunch({ url })
}

const goTo = (url: string) => {
    if (!url) return
    uni.navigateTo({ url })
}

const handleSchoolClick = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/school/select' })
}

const selectSchool = (school: any) => {
    currentSchool.value = school
    uni.setStorageSync('current_school', school)
    showSchoolPopup.value = false
    // 重新加载数据
    loadData()
}

const needsCertCheck = (url: string) => {
    return url && (url.includes('/create') || url.includes('/publish') || url.includes('/pickup'))
}

const openPublish = async () => {
    // 优先检测登录
    if (!isLoggedIn.value) {
        useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/index/index' })
        return
    }
    // 从接口获取最新认证状态
    try {
        const res: any = await getCampusAuthInfo()
        if (res.code === 1 && res.data) {
            isAuthed.value = res.data.status === 1 || res.data.status === 'passed'
        }
    } catch (e) {
        console.error('获取认证状态失败:', e)
    }
    // 再检测认证
    if (!isAuthed.value) {
        tabbarRef.value?.triggerCertPopup()
        return
    }
    uni.$emit('openPublishPopup')
}

const handleMenuClick = async (item: any) => {
    if (item.url) {
        // 需要认证的操作优先检测登录
        if (needsCertCheck(item.url)) {
            if (!isLoggedIn.value) {
                useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/index/index' })
                return
            }
            // 从接口获取最新认证状态
            try {
                const res: any = await getCampusAuthInfo()
                if (res.code === 1 && res.data) {
                    isAuthed.value = res.data.status === 1 || res.data.status === 'passed'
                }
            } catch (e) {
                console.error('获取认证状态失败:', e)
            }
            if (!isAuthed.value) {
                tabbarRef.value?.triggerCertPopup()
                return
            }
        }
        goTo(item.url)
    } else {
        uni.showToast({ title: item.name + '功能即将上线', icon: 'none' })
    }
}

const goToDetail = (item: any) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${item.id}`
    })
}

const handleJoin = (item: any) => {
    showSuccessPopup.value = true
}

const handleFeatureClick = (type: string) => {
    switch (type) {
        case 'want':
            uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/secondhand/index' })
            break
        case 'ad':
            uni.showToast({ title: '广告位招租', icon: 'none' })
            break
        case 'invite':
            uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/invite/index' })
            break
        case 'sell':
            if (!isLoggedIn.value) { useLogin().setLoginBack({ url: '/addon/sd_xiaoyuan/pages/index/index' }); return }
            if (!isAuthed.value) { tabbarRef.value?.triggerCertPopup(); return }
            uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/secondhand/publish' })
            break
    }
}


const goToNotice = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/notice/index' })
}

// 订单相关方法
const getOrderStatusText = (status: number) => {
    const statusMap: Record<number, string> = {
        0: '待支付',
        10: '待接单',
        20: '已接单',
        30: '取货中',
        40: '配送中',
        50: '已完成',
        90: '已取消',
        91: '已退款'
    }
    return statusMap[status] || '未知'
}

const getOrderTaskTypeName = (type: string) => {
    const typeMap: Record<string, string> = {
        'EXPRESS': '代取快递',
        'BUY': '帮我买',
        'ERRAND': '跑腿服务',
        'QUEUE': '代排队',
        'PRINT': '代打印',
        'SEAT': '代占座',
        'TRASH': '扔垃圾',
        'CARRY': '帮搬运',
        'CLEAN': '代清洁',
        'GAME': '游戏陪玩',
        'HELP': '帮帮忙'
    }
    return typeMap[type] || type
}

const getOrderImages = (order: any) => {
    if (order.images) {
        try {
            let images = order.images
            if (typeof images === 'string') {
                try {
                    const parsed = JSON.parse(images)
                    images = Array.isArray(parsed) ? parsed : images.split(',').filter((s: string) => s)
                } catch {
                    images = images.split(',').filter((s: string) => s)
                }
            }
            return Array.isArray(images) ? images.slice(0, 3) : []
        } catch (e) {
            return []
        }
    }
    return []
}

const getExtTags = (order: any) => {
    const tags: string[] = []
    let ext: any = {}
    if (order.ext) {
        try { ext = typeof order.ext === 'string' ? JSON.parse(order.ext) : order.ext } catch {}
    }
    const taskType = order.task_type || ''
    if (taskType === 'GAME') {
        if (ext.game_type) tags.push(ext.game_type)
        if (ext.service_type) tags.push(ext.service_type)
        if (ext.rank_level) tags.push(ext.rank_level)
        if (ext.voice_chat) tags.push('语音陪玩')
    } else if (taskType === 'PRINT') {
        if (ext.print_side) tags.push(ext.print_side === 'single' ? '单面' : '双面')
        if (ext.print_color) tags.push(ext.print_color === 'color' ? '彩印' : '黑白')
        if (ext.paper_size) tags.push(ext.paper_size)
        if (ext.page_count) tags.push(ext.page_count + '页')
        if (ext.files && ext.files.length > 0) tags.push(ext.files.length + '个文件')
    } else if (taskType === 'EXPRESS') {
        if (ext.express_company) tags.push(ext.express_company)
        if (ext.weight) tags.push(ext.weight + 'kg')
    } else if (taskType === 'BUY') {
        if (ext.shop_name) tags.push(ext.shop_name)
    }
    if (order.is_urgent) tags.push('加急')
    return tags.slice(0, 4)
}

const cancelOrder = async (id: number) => {
    uni.showModal({
        title: '提示',
        content: '确定要取消订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await cancelOrderApi({ id })
                    if (result.code === 1) {
                        uni.showToast({ title: '取消成功', icon: 'success' })
                        // 重新加载订单数据
                        loadMyOrders()
                    } else {
                        uni.showToast({ title: result.msg || '取消失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}

const payOrder = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

const goToEvaluate = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/evaluate?id=${id}`
    })
}

const goToOrderDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

// 加载任务大厅订单
const loadMyOrders = async () => {
    try {
        const params: any = {
            page: 1,
            limit: 10,
            status: 10
        }
        if (currentSchool.value.id) {
            params.school_id = currentSchool.value.id
        }
        if (currentTab.value > 0) {
            params.task_type = tabTypes[currentTab.value]
        }
        const res: any = await getOrderHall(params)

        if (res.code === 1) {
            myOrders.value = (res.data.list || res.data || []).map((item: any) => mapOrderItemForIndex(item))
        }
    } catch (e) {
        console.error('加载任务大厅失败:', e)
    }
}

// 类型映射表
const cleanTypeMapIndex: Record<string, string> = {
    'DAILY': '日常保洁', 'DEEP': '深度清洁', 'MOVE_IN': '入住清洁',
    'MOVE_OUT': '退租清洁', 'GLASS': '玻璃清洁', 'APPLIANCE': '家电清洗', 'DORM': '宿舍清洁', 'OTHER': '其他'
}
const trashTypeMapIndex: Record<string, string> = {
    'HOUSEHOLD': '生活垃圾', 'RECYCLABLE': '可回收物', 'KITCHEN': '厨余垃圾',
    'HAZARDOUS': '有害垃圾', 'BULKY': '大件垃圾', 'OTHER': '其他'
}
const carryItemTypeMapIndex: Record<string, string> = {
    'LUGGAGE': '行李箱', 'BOX': '纸箱/包裹', 'FURNITURE': '家具',
    'APPLIANCE': '电器', 'BOOKS': '书籍', 'OTHER': '其他'
}

// 订单数据映射函数（与hall页面保持一致）
const mapOrderItemForIndex = (item: any) => {
    const ext = item.ext_data || {}
    let tag1 = '', tag2 = '', tag3 = ''
    if (item.task_type === 'HELP') {
        const helpTypeMap: Record<string, string> = { 'ERRAND': '跑腿帮忙', 'ONLINE': '线上帮忙', 'STUDY': '学习辅导', 'TECH': '技术支持', 'OTHER': '其他' }
        const genderMap: Record<string, string> = { 'MALE': '限男生', 'FEMALE': '限女生', 'ALL': '不限' }
        tag1 = helpTypeMap[ext.help_type] || ''
        tag2 = genderMap[ext.gender_limit] || ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'GROUP') {
        const groupTypeMap: Record<string, string> = { 'TEA': '拼奶茶', 'FOOD': '拼外卖', 'FRUIT': '拼水果', 'RIDE': '拼车', 'OTHER': '其他' }
        tag1 = groupTypeMap[ext.group_type] || '拼单'
        tag2 = `${ext.current_members || 1}/${ext.max_members || 10}人`
        tag3 = ext.shop_name || ''
    } else if (item.task_type === 'CLEAN') {
        tag1 = cleanTypeMapIndex[ext.clean_type] || ext.clean_type || '清洁服务'
        tag2 = ext.area ? ext.area + '㎡' : ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'TRASH') {
        tag1 = trashTypeMapIndex[ext.trash_type] || ext.trash_type || '生活垃圾'
        tag2 = ext.bag_count ? ext.bag_count + '袋' : ''
        tag3 = ext.floor ? ext.floor + '楼' : ''
    } else if (item.task_type === 'CARRY') {
        tag1 = carryItemTypeMapIndex[ext.item_type] || ext.item_type || '物品'
        tag2 = ext.item_count ? ext.item_count + '件' : ''
        tag3 = ext.from_floor && ext.to_floor ? ext.from_floor + '楼→' + ext.to_floor + '楼' : ''
    } else if (item.task_type === 'PRINT') {
        tag1 = ext.print_side === 'single' ? '单面' : '双面'
        tag2 = ext.print_color === 'color' ? '彩印' : '黑白'
        tag3 = ext.page_count ? ext.page_count + '页' : ''
    } else if (item.task_type === 'EXPRESS') {
        tag1 = ext.station_name || ext.express_company || ''
        tag2 = ext.pickup_code ? '取件码:' + ext.pickup_code : ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'BUY') {
        tag1 = ext.shop_name || ext.goods_name || ''
        tag2 = ext.expect_time || ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'SEND') {
        tag1 = ext.goods_desc || ''
        tag2 = ext.weight ? ext.weight + 'kg' : ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'QUEUE') {
        tag1 = ext.queue_location || ''
        tag2 = ext.queue_time || ''
        tag3 = ext.estimated_duration ? '约' + ext.estimated_duration + '分钟' : ''
    } else if (item.task_type === 'SEAT') {
        tag1 = ext.seat_location || ''
        tag2 = ext.seat_count ? ext.seat_count + '个座位' : ''
        tag3 = ext.seat_duration ? ext.seat_duration + '小时' : ''
    } else if (item.task_type === 'GAME') {
        tag1 = ext.game_type || ''
        tag2 = ext.service_type || ''
        tag3 = ext.voice_chat ? '语音陪玩' : ''
    } else {
        tag1 = parseFloat(item.weight) > 0 ? `${parseFloat(item.weight)}kg` : ''
        tag2 = parseFloat(item.distance) > 0 ? `${parseFloat(item.distance)}km` : ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    }
    
    const typeMap: Record<string, string> = {
        'BUY': '帮我买', 'SEND': '帮我送', 'EXPRESS': '代取快递', 'PRINT': '帮打印',
        'QUEUE': '代排队', 'SEAT': '代占座', 'TRASH': '扔垃圾', 'CARRY': '帮搬运',
        'CLEAN': '代清洁', 'HELP': '帮帮忙', 'GROUP': '拼单', 'GAME': '游戏陪练', 'ERRAND': '跑腿'
    }
    const typeName = typeMap[item.task_type] || '校园服务'
    const title = `${typeName} - ${item.remark || item.goods_name || '无备注'}`
    
    return {
        id: item.id,
        order_no: item.order_no || item.id,
        task_type: item.task_type,
        member_headimg: item.member_headimg || '',
        member_nickname: item.member_nickname || '校园同学',
        member_credit: item.credit_score || 100,
        create_time: item.create_time || '',
        create_time_text: item.create_time || '',
        remark: title,
        pickup_address: item.pickup_address || item.pickup || item.address || item.start_address || item.clean_address || item.service_address || '',
        receive_address: item.receive_address || '',
        total_fee: item.total_fee,
        actual_fee: item.actual_fee || item.total_fee,
        status: item.status || 10,
        is_urgent: item.is_urgent,
        ext: ext,
        goods_image: item.goods_image || ''
    }
}

const acceptOrder = async (id: number) => {
    if (!memberStore.token) {
        uni.navigateTo({ url: '/app/pages/auth/login' })
        return
    }
    
    const runnerRes: any = await getRunnerInfo()
    if (runnerRes.code !== 1 || !runnerRes.data || !runnerRes.data.id) {
        uni.showModal({
            title: '温馨提示',
            content: '您还未入驻成为接单员，是否立即入驻？',
            confirmText: '立即入驻',
            confirmColor: '#00c853',
            success: (res) => {
                if (res.confirm) {
                    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/apply' })
                }
            }
        })
        return
    }
    
    if (runnerRes.data.status !== 1) {
        uni.showToast({ title: '您的接单员账号未通过审核', icon: 'none' })
        return
    }
    
    uni.showModal({
        title: '确认接单',
        content: '确定要接取这个订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '接单中...' })
                    const result: any = await acceptOrderApi({ id })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '接单成功', icon: 'success' })
                        loadMyOrders()
                    } else {
                        uni.showToast({ title: result.msg || '接单失败', icon: 'none' })
                    }
                } catch (e: any) {
                    uni.hideLoading()
                    if (e && e.msg) {
                        uni.showToast({ title: e.msg, icon: 'none' })
                    } else if (!e || !e.code) {
                        uni.showToast({ title: '网络错误', icon: 'none' })
                    }
                }
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.index-page {
    min-height: 100vh;
    background: #f7f7f7;
    font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
    --primary-color: #c0fe95;
}

.page-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 500rpx;
    background: linear-gradient(to bottom, #c0fe95, #f7f7f7);
    z-index: 0;
    pointer-events: none;
}

.custom-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10;
    padding-bottom: 10rpx;
    background: transparent;

    .nav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10rpx 30rpx;

        .school-selector {
            display: flex;
            align-items: center;
            font-size: 32rpx;
            font-weight: bold;
            color: #333;

            .school-logo {
                width: 48rpx;
                height: 48rpx;
                border-radius: 50%;
                margin-right: 12rpx;
                border: 2rpx solid rgba(0,0,0,0.06);
            }

            .school-name {
                margin: 0 8rpx;
                max-width: 360rpx;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
        }

        .nav-actions {
            display: flex;
            align-items: center;
margin-right: 20rpx;
            .action-btn {
                width: 60rpx;
                height: 60rpx;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;

                .badge {
                    position: absolute;
                    top: -6rpx;
                    right: -6rpx;
                    min-width: 28rpx;
                    height: 28rpx;
                    background: #ff4d4f;
                    color: #fff;
                    font-size: 20rpx;
                    border-radius: 14rpx;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 6rpx;
                    z-index: 1;
                }
            }
        }
    }

    .header-info-bar {
        display: flex;
        align-items: center;
        padding: 6rpx 30rpx;

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
                margin-top: 2rpx;
            }
        }

        .info-divider {
            width: 1rpx;
            height: 40rpx;
            background: rgba(0, 0, 0, 0.1);
            margin: 0 10rpx;
        }

        .search-entry {
            flex: 1;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 30rpx;
            padding: 12rpx 20rpx;
            margin-left: 16rpx;

            .search-text {
                font-size: 24rpx;
                color: #999;
                margin-left: 8rpx;
            }
        }
    }
}


.main-content {
    box-sizing: border-box;
    position: relative;
    z-index: 5;
}

.promo-section {
    padding: 0 20rpx;
    margin-bottom: 20rpx;

    .promo-card {
        height: 240rpx;
        background: linear-gradient(135deg, #c0fe95, #88f78d);
        border-radius: 24rpx;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 40rpx;
        position: relative;
        overflow: hidden;

        .promo-left {
            z-index: 1;

            .promo-title {
                font-size: 36rpx;
                font-weight: 900;
                color: #333;
                margin-bottom: 10rpx;
            }

            .promo-subtitle {
                font-size: 44rpx;
                font-weight: 900;
                color: #333;
                margin-bottom: 24rpx;
            }

            .go-btn {
                display: inline-block;
                padding: 10rpx 40rpx;
                background: linear-gradient(to top, #aaf69b, #d1ff7c);
                color: #000000;
                border-radius: 16rpx;
                font-weight: bold;
                font-size: 28rpx;
                box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
            }
        }

        .promo-right {
            opacity: 0.8;
        }

        .promo-actions {
            display: flex;
            gap: 16rpx;

            .action-pill {
                display: flex;
                align-items: center;
                gap: 8rpx;
                padding: 14rpx 24rpx;
                background: rgba(255, 255, 255, 0.7);
                border-radius: 30rpx;
                
                text {
                    font-size: 24rpx;
                    color: #333;
                    font-weight: 500;
                }
            }
        }

        &.authed {
            height: auto;
            padding: 30rpx 40rpx;
            flex-direction: column;
            align-items: flex-start;

            .promo-left {
                margin-bottom: 20rpx;

                .promo-title {
                    font-size: 32rpx;
                }

                .promo-subtitle {
                    font-size: 26rpx;
                    font-weight: 500;
                    color: #555;
                    margin-bottom: 0;
                }
            }
        }

        &::after {
            content: '';
            position: absolute;
            right: -20rpx;
            bottom: -20rpx;
            width: 160rpx;
            height: 160rpx;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }
    }
}

.notice-section {
    margin: 0 30rpx 20rpx;
  
}

.menu-section {
    display: flex;
    flex-wrap: wrap;
    padding: 0 0;
    margin-bottom: 10rpx;

    .menu-item {
        width: 20%;
        margin-bottom: 30rpx;
        display: flex;
        flex-direction: column;
        align-items: center;

        .menu-icon {
            width: 88rpx;
            height: 88rpx;
            border-radius: 22rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12rpx;
        }

        .menu-name {
            font-size: 26rpx;
            color: #333;
            font-weight: 500;
        }
    }
}

.feature-banners {
    display: flex;
    gap: 20rpx;
    padding: 0 20rpx;
    margin-bottom: 20rpx;

    .feature-banner {
        flex: 1;
        height: 140rpx;
        border-radius: 20rpx;
        padding:0rpx 14rpx;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;

        &::after {
            content: '';
            position: absolute;
            right: -30rpx;
            bottom: -30rpx;
            width: 120rpx;
            height: 120rpx;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
        }

        .banner-info {
            z-index: 1;

            .banner-title {
                display: block;
                font-size: 30rpx;
                font-weight: bold;
                color: #fff;
                margin-bottom: 8rpx;
            }

            .banner-desc {
                display: block;
                font-size: 22rpx;
                color: rgba(255, 255, 255, 0.85);
            }
        }

        .banner-icon {
            z-index: 1;
            opacity: 0.9;margin-right: -10rpx;
        }

        &.banner-house {
            background: linear-gradient(135deg, #7c5cfc, #a78bfa);
        }

        &.banner-group {
            background: linear-gradient(135deg, #ff6b35, #ff9a5c);
        }

        &.banner-secondhand {
            background: linear-gradient(135deg, #13c2c2, #36d6b7);
        }

        &.banner-lost {
            background: linear-gradient(135deg, #ff4d6a, #ff7eb3);
        }
        
        &.banner-community {
            background: linear-gradient(135deg, #722ed1, #b37feb);
        }
        
        &.banner-schedule {
            background: linear-gradient(135deg, #1890ff, #40a9ff);
        }
    }
}

.feature-banners-three {
    .feature-banner {
        height: 110rpx;
        padding: 0rpx 20rpx;
        
        .banner-info {
            .banner-title {
                font-size: 24rpx;
            }
            
            .banner-desc {
                font-size: 20rpx;
            }
        }
        
        .banner-icon {
            u-icon {
                font-size: 32rpx !important;
            }
        }
    }
}


.feed-list {
    padding: 0 20rpx;

    .feed-card {
        background: #fff;
        border-radius: 24rpx;
        padding: 30rpx;
        margin-bottom: 24rpx;

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20rpx;

            .avatar {
                width: 80rpx;
                height: 80rpx;
                border-radius: 50%;
                margin-right: 20rpx;
                background: #f0f0f0;
            }

            .user-info {
                .nickname {
                    display: block;
                    font-size: 30rpx;
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 6rpx;
                }

                .time {
                    font-size: 24rpx;
                    color: #999;
                }
            }
        }

        .card-body {
            margin-bottom: 30rpx;

            .card-title {
                font-size: 32rpx;
                font-weight: bold;
                color: #333;
                margin-bottom: 20rpx;
            }

            .info-row {
                display: flex;
                align-items: center;
                margin-bottom: 12rpx;

                .info-text {
                    font-size: 26rpx;
                    color: #666;
                    margin-left: 10rpx;
                }
            }
            .tags-row {
                display: flex;
                flex-wrap: wrap;
            }
        }

        .card-footer {
            display: flex;
            justify-content: flex-end;

            .action-btn {
                margin: 0;
                background: #000;
                color: #fff;
                font-size: 28rpx;
                border-radius: 16rpx;
                padding: 0 40rpx;
                height: 64rpx;
                line-height: 64rpx;
                font-weight: bold;

                &::after {
                    border: none;
                }
            }
        }
    }
}

.my-orders-section {
    margin: 20rpx 20rpx 0;
    // background: #fff;
    border-radius: 24rpx;
    padding: 30rpx 0;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0 0 20rpx;

        .section-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }

        .more-link {
            font-size: 26rpx;
            color: #999;
        }
    }

    .task-type-scroll {
        white-space: nowrap;
        margin-bottom: 20rpx;

        .task-type-tabs {
            display: inline-flex;
            gap: 16rpx;
            padding: 0 0;width: 94%;

            .type-tab {
                display: inline-block;
                padding: 2rpx 24rpx;
                background: #f5f5f5;
                border-radius: 110rpx;
                font-size: 26rpx;
                color: #666;

                &.active {
                    background: #c0fe95;
                    color: #333;
                    font-weight: bold;
                }
            }
        }
    }

    .orders-list {
        .accept-btn {
            background: linear-gradient(to top, #aaf69b, #d1ff7c);
            color: #000;
            border: none;
            border-radius: 16rpx;
            padding: 0 40rpx;
            height: 64rpx;
            line-height: 64rpx;
            font-size: 28rpx;
            font-weight: bold;
            box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

            &::after {
                border: none;
            }
        }
        
        .order-card {
            background: #f8fafc;
            border-radius: 16rpx;
            padding: 24rpx;
            margin-bottom: 16rpx;
            border: 1rpx solid transparent;
            transition: all 0.2s;

            &:active {
                transform: scale(0.98);
                background: #f0f0f0;
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16rpx;

                .publisher-info {
                    display: flex;
                    align-items: center;
                    flex: 1;

                    .publisher-avatar {
                        width: 64rpx;
                        height: 64rpx;
                        border-radius: 50%;
                        margin-right: 16rpx;
                        background: #f0f0f0;
                    }

                    .publisher-detail {
                        display: flex;
                        flex-direction: column;

                        .publisher-name {
                            font-size: 26rpx;
                            font-weight: bold;
                            color: #333;
                            margin-bottom: 4rpx;
                        }

                        .credit-badge {
                            display: flex;
                            align-items: center;

                            .credit-score {
                                font-size: 20rpx;
                                color: #ff9500;
                                margin-left: 4rpx;
                                font-weight: bold;
                            }
                        }
                    }
                }

                .order-price {
                    font-size: 32rpx;
                    font-weight: bold;
                    color: #ff6b00;
                }
            }

            .card-content {
                .order-main {
                    display: flex;
                    align-items: center;
                    margin-bottom: 16rpx;

                    .task-type {
                        margin-right: 16rpx;

                        .type-tag {
                            display: inline-block;
                            padding: 6rpx 16rpx;
                            background: #c0fe95;
                            color: #333;
                            font-size: 22rpx;
                            font-weight: bold;
                            border-radius: 8rpx;
                        }
                    }

                    .ext-tags {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8rpx;
                        margin-right: 16rpx;

                        .ext-tag {
                            display: inline-block;
                            padding: 4rpx 12rpx;
                            background: #f0f0f0;
                            color: #666;
                            font-size: 20rpx;
                            border-radius: 6rpx;
                        }
                    }

                    .order-images {
                        display: flex;
                        gap: 8rpx;

                        .order-image {
                            width: 80rpx;
                            height: 80rpx;
                            border-radius: 8rpx;
                        }
                    }
                }

                .address-info {
                    display: flex;
                    flex-direction: column;
                    gap: 12rpx;

                    .address-item {
                        display: flex;
                        align-items: flex-start;

                        .addr-tag {
                            width: 36rpx;
                            height: 36rpx;
                            border-radius: 50%;
                            font-size: 20rpx;
                            color: #fff;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-right: 12rpx;
                            flex-shrink: 0;

                            &.pickup { background: #52c41a; }
                            &.receive { background: #ff4d4f; }
                        }

                        .address {
                            font-size: 26rpx;
                            color: #1e293b;
                            flex: 1;
                            line-height: 1.4;
                        }
                    }
                }
            }

            .card-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 20rpx;
                padding-top: 16rpx;
                border-top: 1rpx solid #e5e7eb;

                .time {
                    font-size: 22rpx;
                    color: #94a3b8;
                }

                .price-wrap {
                    display: flex;
                    align-items: baseline;

                    .unit {
                        font-size: 22rpx;
                        color: #ef4444;
                        font-weight: bold;
                        margin-right: 2rpx;
                    }

                    .price {
                        font-size: 32rpx;
                        color: #ef4444;
                        font-weight: 900;
                    }
                }
            }

            .card-actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 16rpx;
                gap: 16rpx;

                button {
                    margin: 0;
                    padding: 0 24rpx;
                    height: 56rpx;
                    line-height: 56rpx;
                    font-size: 24rpx;
                    font-weight: bold;
                    border-radius: 28rpx;
                    transition: all 0.2s;

                    &::after { border: none; }
                }

                .btn-cancel {
                    background: #f1f5f9;
                    color: #64748b;
                }

                .btn-pay, .btn-evaluate {
                    background: #000;
                    color: #fff;
                }
            }
        }

        .empty-orders {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 60rpx 0;

            text {
                font-size: 26rpx;
                color: #999;
                margin-top: 16rpx;
            }
        }
    }
}

.success-popup {
    padding: 50rpx 40rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #fff;
    border-radius: 24rpx;

    .popup-icon {
        margin-bottom: 30rpx;
    }

    .popup-title {
        font-size: 36rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
    }

    .popup-desc {
        font-size: 28rpx;
        color: #666;
        text-align: center;
        margin-bottom: 50rpx;
        line-height: 1.5;
    }

    .popup-btn {
        width: 100%;
        height: 88rpx;
        line-height: 88rpx;
        border-radius: 44rpx;
        font-size: 32rpx;
        font-weight: bold;
        margin-bottom: 24rpx;
        border: none;

        &::after {
            border: none;
        }

        &.primary {
            background: linear-gradient(to right, #d1ff7c, #aaf69b);
            color: #333;
        }

        &.close {
            background: #f5f5f5;
            color: #999;
            margin-bottom: 0;
        }
    }
}

.school-popup {
    background: #fff;
    padding: 30rpx;
    max-height: 80vh;
    display: flex;
    flex-direction: column;

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30rpx;

        .title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
    }

    .school-list {
        max-height: 600rpx;

        .school-item {
            padding: 30rpx 0;
            border-bottom: 1rpx solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 28rpx;
            color: #333;

            &.active {
                color: #c0fe95;
                font-weight: bold;
            }
        }

        .empty-school {
            padding: 60rpx 0;
            text-align: center;
            color: #999;
            font-size: 26rpx;
        }
    }
}
</style>
