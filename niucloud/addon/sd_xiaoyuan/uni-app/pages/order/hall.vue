<template>
    <view class="hall-page">
        <!-- Custom Header -->
        <view class="custom-header">
            <view class="header-bg"></view>
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>

            <!-- Nav Bar -->
            <view class="nav-bar" :style="{ height: navBarHeight + 'px', paddingRight: menuButtonRight + 'px' }">
                <view class="school-selector" @click="handleSchoolClick">
                    <image v-if="currentSchool?.logo" :src="img(currentSchool.logo)" class="school-logo" mode="aspectFill" />
                    <text v-else class="iconfont icon-location-fill" style="color: #333;"></text>
                    <text class="school-name">{{ currentSchool?.name || '选择学校' }}</text>
                    <u-icon name="arrow-down-fill" size="12" color="#333"></u-icon>
                </view>
                <view class="nav-actions">
                    <view class="action-btn" @click="reLaunch('/addon/sd_xiaoyuan/pages/message/index')">
                        <u-icon name="bell" size="20" color="#333"></u-icon>
                        <view class="badge" v-if="unreadCount > 0">{{ unreadCount }}</view>
                    </view>
                </view>
            </view>

            <!-- Task Type Filter -->
            <view class="filter-tabs">
                <scroll-view scroll-x class="tabs-scroll">
                    <view class="tabs-container">
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === '' }"
                            @click="changeTaskType('')"
                        >全部</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'BUY' }"
                            @click="changeTaskType('BUY')"
                        >帮我买</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'ERRAND' }"
                            @click="changeTaskType('ERRAND')"
                        >跑腿</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'EXPRESS' }"
                            @click="changeTaskType('EXPRESS')"
                        >代取快递</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'PRINT' }"
                            @click="changeTaskType('PRINT')"
                        >代打印</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'QUEUE' }"
                            @click="changeTaskType('QUEUE')"
                        >代排队</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'SEAT' }"
                            @click="changeTaskType('SEAT')"
                        >代占座</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'TRASH' }"
                            @click="changeTaskType('TRASH')"
                        >扔垃圾</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'CARRY' }"
                            @click="changeTaskType('CARRY')"
                        >帮搬运</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'CLEAN' }"
                            @click="changeTaskType('CLEAN')"
                        >代清洁</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'HELP' }"
                            @click="changeTaskType('HELP')"
                        >帮帮忙</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'GROUP' }"
                            @click="changeTaskType('GROUP')"
                        >拼单</view>
                        <view
                            class="tab-item"
                            :class="{ active: currentTaskType === 'GAME' }"
                            @click="changeTaskType('GAME')"
                        >游戏陪玩</view>
                    </view>
                </scroll-view>
            </view>
        </view>

        <!-- Order Hall List -->
        <view class="hall-content" :style="{ paddingTop: (statusBarHeight + navBarHeight + 90) + 'px' }" style="position:reactive;z-index:2">
            <sd-order-item 
                v-for="(item, index) in orderList"
                :key="item.id"
                :order="item"
                :show-user="true"
                @click="goToDetail(item.id)"
                :style="{ animationDelay: index * 0.05 + 's' }"
            >
                <template #actions>
                    <button class="accept-btn" v-if="item.status === 10" @click.stop="handleJoin(item)">接单</button>
                </template>
            </sd-order-item>

            <view class="empty-state" v-if="orderList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无订单</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#1890ff"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && orderList.length > 0">
                <text>没有更多了</text>
            </view>

            <view style="height: 180rpx;"></view>
        </view>


        <!-- School Selection Popup -->
        <u-popup :show="showSchoolPopup" mode="bottom" round="20" @close="showSchoolPopup = false">
            <view class="school-popup">
                <view class="popup-header">
                    <text class="popup-title">选择学校</text>
                    <view class="close-btn" @click="showSchoolPopup = false">
                        <u-icon name="close" size="20" color="#999"></u-icon>
                    </view>
                </view>
                <scroll-view scroll-y class="school-list">
                    <view
                        class="school-item"
                        v-for="school in schoolList"
                        :key="school.id"
                        :class="{ active: currentSchool?.id === school.id }"
                        @click="selectSchool(school)"
                    >
                        {{ school.name }}
                    </view>
                    <view class="empty-school" v-if="schoolList.length === 0">
                        <text>暂无学校数据</text>
                    </view>
                </scroll-view>
            </view>
        </u-popup>

        <!-- Shared Tabbar + Popups -->
        <custom-tabbar current="hall" ref="tabbarRef" />
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import { getOrderHall, getUnreadMessageCount, getSchoolList } from '../../api/xiaoyuan'
import { getRunnerInfo, acceptOrder as acceptOrderApi } from '../../api/runner'
import { img } from '@/utils/common'
import sdOrderItem from '../../components/sd-order-item.vue'
import customTabbar from '../../components/custom-tabbar.vue'
import useMemberStore from '@/stores/member'

const memberStore = useMemberStore()

const orderList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10
const currentTaskType = ref('')
const currentSchool = ref<any>({ name: '请选择学校', id: 0 })
const showPublishPopup = ref(false)
const showSchoolPopup = ref(false)
const schoolList = ref<any[]>([])
const unreadCount = ref(0)

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const menuButtonRight = ref(0)
const tabbarRef = ref<any>(null)

// 发布类型选项
const publishTypes = [
    {
        id: 'express',
        name: '代取快递',
        icon: 'car',
        color: '#52c41a',
        url: '/addon/sd_xiaoyuan/pages/express/pickup'
    },
    {
        id: 'buy',
        name: '帮我买',
        icon: 'shopping-cart',
        color: '#1890ff',
        url: '/addon/sd_xiaoyuan/pages/buy/create'
    },
    {
        id: 'carry',
        name: '帮搬运',
        icon: 'car',
        color: '#52c41a',
        url: '/addon/sd_xiaoyuan/pages/carry/create'
    },
    {
        id: 'clean',
        name: '代清洁',
        icon: 'broom',
        color: '#ff9800',
        url: '/addon/sd_xiaoyuan/pages/clean/create'
    },
    {
        id: 'print',
        name: '代打印',
        icon: 'file-text',
        color: '#ff9800',
        url: '/addon/sd_xiaoyuan/pages/print/create'
    },
    {
        id: 'trash',
        name: '扔垃圾',
        icon: 'trash',
        color: '#9c27b0',
        url: '/addon/sd_xiaoyuan/pages/trash/create'
    },
    {
        id: 'community',
        name: '树洞发布',
        icon: 'chat',
        color: '#673ab7',
        url: '/addon/sd_xiaoyuan/pages/community/publish'
    },
    {
        id: 'secondhand',
        name: '二手交易',
        icon: 'shopping',
        color: '#4caf50',
        url: '/addon/sd_xiaoyuan/pages/secondhand/publish'
    }
]

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    menuButtonRight.value = sysInfo.windowWidth - menuButton.left
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    loadSchools()
    loadOrders()
    loadUnreadCount()
    uni.hideTabBar()
})

onShow(() => {
    uni.hideTabBar()
    
    // 检查学校是否发生变化
    const cachedSchool = uni.getStorageSync('current_school')
    if (cachedSchool && (!currentSchool.value.id || cachedSchool.id !== currentSchool.value.id)) {
        currentSchool.value = cachedSchool
        loadOrders(true)
    }
})

onReachBottom(() => {
    loadMore()
})

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
        }
    } catch (e) {
        console.error(e)
    }
}

const loadUnreadCount = async () => {
    try {
        const res: any = await getUnreadMessageCount()
        if (res.code === 1) {
            unreadCount.value = res.data.count || 0
        }
    } catch (e: any) {
        // 如果是401未登录错误，静默处理
        if (e?.code === 401 || e?.msg === '请登录') {
            unreadCount.value = 0
            return
        }
        console.error('加载未读消息数失败:', e)
    }
}

const loadOrders = async (refresh = false) => {
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
            limit: limit,
            status: 10, // 只获取待接单状态的订单
            school_id: currentSchool.value.id
        }

        if (currentTaskType.value) {
            params.task_type = currentTaskType.value
        }

        const res: any = await getOrderHall(params)
        if (res.code === 1 && res.data.list) {
            if (refresh) {
                orderList.value = res.data.list.map((item: any) => mapOrderItem(item))
            } else {
                const newOrders = res.data.list.map((item: any) => mapOrderItem(item))
                orderList.value = [...orderList.value, ...newOrders]
            }

            if (!res.data.list || res.data.list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        } else {
            if (refresh) {
                orderList.value = []
            }
            hasMore.value = false
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        isRefreshing.value = false
    }
}

const cleanTypeMap: Record<string, string> = {
    'DAILY': '日常保洁', 'DEEP': '深度清洁', 'MOVE_IN': '入住清洁',
    'MOVE_OUT': '退租清洁', 'GLASS': '玻璃清洁', 'APPLIANCE': '家电清洗', 'DORM': '宿舍清洁', 'OTHER': '其他'
}
const trashTypeMap: Record<string, string> = {
    'HOUSEHOLD': '生活垃圾', 'RECYCLABLE': '可回收物', 'KITCHEN': '厨余垃圾',
    'HAZARDOUS': '有害垃圾', 'BULKY': '大件垃圾', 'OTHER': '其他'
}
const carryItemTypeMap: Record<string, string> = {
    'LUGGAGE': '行李箱', 'BOX': '纸箱/包裹', 'FURNITURE': '家具',
    'APPLIANCE': '电器', 'BOOKS': '书籍', 'OTHER': '其他'
}

const mapOrderItem = (item: any) => {
    const ext = item.ext_data || {}
    // 根据不同任务类型生成标签
    let tag1 = '', tag2 = '', tag3 = ''
    if (item.task_type === 'HELP') {
        const helpTypeMap: Record<string, string> = { 'ERRAND': '跑腿帮忙', 'ONLINE': '线上帮忙', 'STUDY': '学习辅导', 'TECH': '技术支持', 'OTHER': '其他' }
        tag1 = helpTypeMap[ext.help_type] || ''
        tag2 = item.is_urgent == 1 ? '加急' : ''
        tag3 = ''
    } else if (item.task_type === 'GROUP') {
        const groupTypeMap: Record<string, string> = { 'TEA': '拼奶茶', 'FOOD': '拼外卖', 'FRUIT': '拼水果', 'RIDE': '拼车', 'OTHER': '其他' }
        tag1 = groupTypeMap[ext.group_type] || '拼单'
        tag2 = `${ext.current_members || 1}/${ext.max_members || 10}人`
        tag3 = ext.shop_name || ''
    } else if (item.task_type === 'CLEAN') {
        tag1 = cleanTypeMap[ext.clean_type] || ext.clean_type || '清洁服务'
        tag2 = ext.area ? ext.area + '㎡' : ''
        tag3 = item.is_urgent == 1 ? '加急' : ''
    } else if (item.task_type === 'TRASH') {
        tag1 = trashTypeMap[ext.trash_type] || ext.trash_type || '生活垃圾'
        tag2 = ext.bag_count ? ext.bag_count + '袋' : ''
        tag3 = ext.floor ? ext.floor + '楼' : ''
    } else if (item.task_type === 'CARRY') {
        tag1 = carryItemTypeMap[ext.item_type] || ext.item_type || '物品'
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
    return {
        id: item.id,
        order_no: item.order_no || item.id,
        task_type: item.task_type,
        member_headimg: item.member_headimg || '',
        member_nickname: item.member_nickname || '校园同学',
        member_credit: item.credit_score || 100,
        create_time: item.create_time || '',
        create_time_text: item.create_time || '',
        remark: formatOrderTitle(item),
        pickup_address: item.pickup_address || item.pickup || item.address || item.start_address || item.clean_address || item.service_address || '',
        receive_address: item.receive_address || '',
        total_fee: parseFloat(item.total_fee) || 0,
        actual_fee: parseFloat(item.actual_fee) || parseFloat(item.total_fee) || 0,
        status: item.status || 10,
        is_urgent: item.is_urgent,
        weight: item.weight,
        distance: item.distance,
        ext: ext,
        // 保留原有字段以备兼容
        avatar: item.member_headimg || '',
        nickname: item.member_nickname || '校园同学',
        credit_score: item.credit_score || 100,
        title: formatOrderTitle(item),
        activity_time: item.create_time_text || '刚刚',
        location: item.pickup_address || item.pickup || item.address || item.start_address || item.clean_address || item.service_address || '',
        tag1, tag2, tag3,
        price: item.total_fee,
        ext_data: ext
    }
}

const formatOrderTitle = (item: any) => {
    const typeMap: Record<string, string> = {
        'BUY': '帮我买',
        'ERRAND': '帮我送',
        'EXPRESS': '代取快递',
        'PRINT': '帮打印',
        'QUEUE': '代排队',
        'SEAT': '代占座',
        'TRASH': '扔垃圾',
        'CARRY': '帮搬运',
        'CLEAN': '代清洁',
        'HELP': '帮帮忙',
        'GROUP': '拼单',
        'GAME': '游戏陪玩'
    }
    const typeName = typeMap[item.task_type] || '校园服务'
    return `${typeName} - ${item.remark || item.goods_name || '无备注'}`
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
}

const changeTaskType = (taskType: string) => {
    currentTaskType.value = taskType
    loadOrders(true)
}

const handleSchoolClick = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/school/select' })
}

const selectSchool = (school: any) => {
    currentSchool.value = school
    uni.setStorageSync('current_school', school)
    showSchoolPopup.value = false
    loadOrders(true)
}

const goToDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/detail?id=${id}`
    })
}

const handleJoin = async (item: any) => {
    if (!memberStore.token) {
        uni.navigateTo({ url: '/app/pages/auth/login' })
        return
    }
    
    // 检查是否是接单员
    try {
        const runnerRes: any = await getRunnerInfo()
        if (runnerRes.code !== 1 || !runnerRes.data) {
            uni.showModal({
                title: '提示',
                content: '您还不是接单员，是否前往申请？',
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
        
        if (runnerRes.data.is_online !== 1) {
            uni.showToast({ title: '请先上线接单', icon: 'none' })
            return
        }
    } catch (e) {
        uni.showToast({ title: '获取接单员信息失败', icon: 'none' })
        return
    }
    
    uni.showModal({
        title: '确认接单',
        content: '确定要接取这个订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '接单中...' })
                    const result: any = await acceptOrderApi({ id: item.id })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '接单成功', icon: 'success' })
                        loadOrders(true)
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

const onRefresh = () => {
    isRefreshing.value = true
    loadOrders(true)
}

const loadMore = () => {
    loadOrders()
}

const reLaunch = (url: string) => {
    uni.reLaunch({ url })
}

const selectPublishType = (item: any) => {
    showPublishPopup.value = false
    uni.navigateTo({
        url: item.url
    })
}
</script>

<style lang="scss" scoped>
.hall-page {
    min-height: 100vh;
    background: #f7f7f7;
    font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
}

.custom-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10;
    padding-bottom: 0;

    .header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 300rpx;
        background: linear-gradient(to bottom, #c0fe95, #f7f7f7);
        z-index: -1;
    }

    .status-bar {
        pointer-events: none;
    }

    .nav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20rpx 30rpx;
        pointer-events: auto;

        .school-selector {
            display: flex;
            align-items: center;
            font-size: 32rpx;
            font-weight: bold;
            color: #333;

            .school-logo {
                width: 40rpx;
                height: 40rpx;
                border-radius: 50%;
                margin-right: 8rpx;
            }

            .icon-location-fill {
                margin-right: 8rpx;
                font-size: 36rpx;
            }

            .school-name {
                margin-right: 8rpx;
                max-width: 400rpx;
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

    .filter-tabs {
        padding: 20rpx 30rpx 0;

        .tabs-scroll {
            white-space: nowrap;

            .tabs-container {
                display: inline-flex;
                gap: 20rpx;
            }
        }

        .tab-item {
            padding: 12rpx 24rpx;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20rpx;
            font-size: 26rpx;
            color: #666;
            white-space: nowrap;
            transition: all 0.3s;

            &.active {
                background: #c0fe95;
                color: #333;
                font-weight: bold;
            }
        }
    }
}


.hall-content {
    margin: -50rpx 20rpx 0;position: relative;z-index: 21;
}

.order-card {
    background: #fff;
    border-radius: 24rpx;
    padding: 30rpx;
    margin-bottom: 24rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
    animation: slideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    opacity: 0;
    border: 1rpx solid transparent;
    transition: all 0.2s;

    &:active {
        transform: scale(0.98);
        background: #fafafa;
    }

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
            flex: 1;

            .nickname-row {
                display: flex;
                align-items: center;
                margin-bottom: 6rpx;
            }

            .nickname {
                font-size: 30rpx;
                font-weight: bold;
                color: #333;
                margin-right: 12rpx;
            }

            .credit-tag {
                display: flex;
                align-items: center;
                background: #fff8e6;
                border-radius: 16rpx;
                padding: 2rpx 12rpx;
                
                text {
                    font-size: 20rpx;
                    color: #ff9500;
                    margin-left: 4rpx;
                    font-weight: bold;
                }
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
            gap: 8rpx;
            margin-bottom: 20rpx;

            .tag {
                display: flex;
                align-items: center;
                padding: 4rpx 12rpx;
                border-radius: 12rpx;
                font-size: 22rpx;

                &.blue {
                    background: #e3f2fd;
                    color: #1976d2;
                }

                &.gray {
                    background: #f5f5f5;
                    color: #666;
                }

                &.green {
                    background: #e8f5e9;
                    color: #2e7d32;
                }
            }
        }

        .input-placeholder {
            font-size: 24rpx;
            color: #999;
            text-align: center;
            padding: 20rpx 0;
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

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;

    text {
        font-size: 28rpx;
        color: #999;
        margin-top: 20rpx;
    }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;

    text {
        font-size: 24rpx;
        color: #94a3b8;
    }
}

.custom-tabbar {
    position: fixed;
    bottom: calc(10rpx + env(safe-area-inset-bottom));
    left: 40rpx;
    right: 40rpx;
    height: 120rpx;
    background: #fff;
    border-radius: 60rpx;
    display: flex;
    justify-content: space-around;
    align-items: center;
    box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.1);
    z-index: 100;

    .tabbar-item {
        display: flex;
        flex-direction: column;
        align-items: center;

        .icon-box {
            margin-bottom: 4rpx;
        }

        text {
            font-size: 22rpx;
            color: #999;
        }

        &.active {
            text {
                color: #333;
                font-weight: bold;
            }
        }

        &.center {
            position: relative;
            top: -30rpx;

            .plus-btn {
                width: 100rpx;
                height: 100rpx;
                background: #000;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.3);
            }
        }
    }
}

@keyframes slideUp {
    from {
        transform: translateY(20rpx);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.publish-popup {
    background: #fff;
    padding: 20rpx;
    max-height: 70vh;

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30rpx;
        padding-bottom: 20rpx;
        border-bottom: 1rpx solid #f0f0f0;

        .popup-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            width: 60rpx;
            height: 60rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f5f5f5;
        }
    }

    .publish-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 30rpx;

        .publish-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20rpx;
            border-radius: 16rpx;
            background: #fafafa;
            transition: all 0.2s;

            &:active {
                transform: scale(0.95);
                background: #f0f0f0;
            }

            .publish-icon {
                width: 100rpx;
                height: 100rpx;
                border-radius: 20rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12rpx;
                border: 3rpx solid transparent;
                box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
            }

            .publish-name {
                font-size: 24rpx;
                color: #666;
                text-align: center;
                line-height: 1.3;
            }
        }
    }
}

.school-popup {
    background: #fff;
    padding: 20rpx;
    max-height: 70vh;
    display: flex;
    flex-direction: column;

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30rpx;
        padding-bottom: 20rpx;
        border-bottom: 1rpx solid #f0f0f0;

        .popup-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            width: 60rpx;
            height: 60rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f5f5f5;
        }
    }

    .school-list {
        flex: 1;

        .school-item {
            padding: 30rpx 0;
            border-bottom: 1rpx solid #f5f5f5;
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
</style>
