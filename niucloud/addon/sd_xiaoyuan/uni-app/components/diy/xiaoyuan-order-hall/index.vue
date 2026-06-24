<template>
    <view :style="warpCss">
        <view class="diy-xiaoyuan-order-hall">
            <view class="hall-header">
                <text class="hall-title">{{ diyComponent.title || '任务大厅' }}</text>
                <view class="hall-more" v-if="diyComponent.showMore" @click="toLink(diyComponent.moreLink || diyComponent.moreUrl)">
                    <text>{{ diyComponent.moreText || '查看更多' }}</text>
                    <u-icon name="arrow-right" size="12" color="#999"></u-icon>
                </view>
            </view>
            
            <view class="hall-tabs" v-if="tabList.length > 0">
                <view 
                    class="tab-item" 
                    :class="{ active: currentTab === idx }"
                    v-for="(tab, idx) in tabList" 
                    :key="idx"
                    @click="switchTab(idx)"
                >{{ tab.name }}</view>
            </view>
            
            <view class="hall-list">
                <sd-order-item 
                    v-for="(order, idx) in orderList" 
                    :key="idx"
                    :order="order"
                    :show-user="true"
                    @click="toOrderDetail(order)"
                >
                    <template #actions>
                        <button
                            v-if="Number(order.status) === 10"
                            class="accept-btn"
                            @click.stop="onAcceptOrder(order)"
                        >接单</button>
                    </template>
                </sd-order-item>
                <view class="empty-tip" v-if="orderList.length === 0">暂无任务</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import useMemberStore from '@/stores/member'
import { getOrderHall, getConfig } from '@/addon/sd_xiaoyuan/api/xiaoyuan'
import { getRunnerInfo, acceptOrder as acceptOrderApi } from '@/addon/sd_xiaoyuan/api/runner'
import { ensureXiaoyuanDefaultSchool } from '@/addon/sd_xiaoyuan/composables/useXiaoyuanDefaultSchool'
import sdOrderItem from '@/addon/sd_xiaoyuan/components/sd-order-item.vue'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()
const memberStore = useMemberStore()

const currentTab = ref(0)
const orderList = ref<any[]>([])
const config = ref<any>(null)
const currentSchoolId = ref(0)

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    } else {
        return props.component
    }
})

const tabList = computed(() => {
    return (diyComponent.value.tabs || []).filter((item: any) => item.isShow !== false)
})

const warpCss = computed(() => {
    let style = ''
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += 'background-color:' + diyComponent.value.componentStartBgColor + ';'
        }
    }
    if (diyComponent.value.topRounded) style += 'border-top-left-radius:' + diyComponent.value.topRounded * 2 + 'rpx;border-top-right-radius:' + diyComponent.value.topRounded * 2 + 'rpx;'
    if (diyComponent.value.bottomRounded) style += 'border-bottom-left-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;border-bottom-right-radius:' + diyComponent.value.bottomRounded * 2 + 'rpx;'
    return style
})

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
    let tag1 = '', tag2 = '', tag3 = ''
    
    if (item.task_type === 'HELP') {
        const helpTypeMap: Record<string, string> = { 'ERRAND': '跑腿帮忙', 'ONLINE': '线上帮忙', 'STUDY': '学习辅导', 'TECH': '技术支持', 'OTHER': '其他' }
        tag1 = helpTypeMap[ext.help_type] || ''
        tag2 = item.is_urgent == 1 ? '加急' : ''
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
    } else if (item.task_type === 'CLASS') {
        tag1 = ext.class_subject || ''
        tag2 = ext.class_location || ''
        tag3 = ext.class_time || ''
    } else if (item.task_type === 'SEAT') {
        tag1 = ext.seat_location || ''
        tag2 = ext.seat_count ? ext.seat_count + '个座位' : ''
        tag3 = ext.seat_duration ? ext.seat_duration + '小时' : ''
    } else if (item.task_type === 'GAME') {
        tag1 = ext.game_type || ''
        tag2 = ext.service_type || ''
        tag3 = ext.voice_chat ? '语音陪玩' : ''
    } else if (item.task_type === 'PARTTIME') {
        tag1 = ext.job_type || ''
        tag2 = ext.salary || ''
        tag3 = ext.recruit_count ? `${ext.recruit_count}人` : (ext.work_time || '')
    } else if (item.task_type === 'COMPANION') {
        tag1 = ext.activity_type || ''
        tag2 = ext.activity_time || ''
        tag3 = ext.people_count ? `${ext.people_count}人` : ''
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
        create_time_text: item.create_time_text || '刚刚',
        remark: item.remark || item.task_desc || item.title || item.content || '暂无描述',
        pickup_address: item.pickup_address || item.pickup || item.address || item.start_address || item.clean_address || item.service_address || '起点',
        receive_address: item.receive_address || item.to_address || '终点',
        total_fee: parseFloat(item.total_fee) || parseFloat(item.tip_fee) || parseFloat(item.total_amount) || 0,
        actual_fee: parseFloat(item.actual_fee) || parseFloat(item.total_fee) || 0,
        status: item.status || 10,
        is_urgent: item.is_urgent,
        weight: item.weight,
        distance: item.distance,
        ext: ext,
        tag1, tag2, tag3,
        price: item.total_fee,
        ext_data: ext
    }
}

const loadOrders = async () => {
    if (diyStore.mode == 'decorate') {
        orderList.value = [
            { id: 1, task_type: 'EXPRESS', member_nickname: '张同学', member_headimg: '', remark: '取快递示例', pickup_address: '菜鸟驿站', receive_address: '1号楼', total_fee: 5, tag1: '菜鸟驿站', tag2: '', tag3: '', create_time_text: '刚刚', member_credit: 100 },
            { id: 2, task_type: 'BUY', member_nickname: '李同学', member_headimg: '', remark: '代买示例', pickup_address: '食堂', receive_address: '2号楼', total_fee: 8, tag1: '食堂', tag2: '', tag3: '', create_time_text: '5分钟前', member_credit: 100 }
        ]
        return
    }
    
    // 配置未加载时，不加载订单
    if (!config.value) {
        orderList.value = []
        return
    }
    
    try {
        const tabType = tabList.value[currentTab.value]?.type || 'all'
        const params: any = { page: 1, limit: diyComponent.value.num || 10, status: 10 }
        if (tabType !== 'all') params.task_type = tabType
        if (currentSchoolId.value > 0) params.school_id = currentSchoolId.value
        
        const res: any = await getOrderHall(params)
        if (res.code === 1) {
            const list = res.data?.data || res.data?.list || []
            // 过滤掉未启用的任务类型
            const filteredList = list.filter((item: any) => {
                const featureMap: Record<string, string> = {
                    'EXPRESS': 'enable_express',
                    'BUY': 'enable_buy',
                    'ERRAND': 'enable_send',
                    'QUEUE': 'enable_queue',
                    'CLASS': 'enable_class',
                    'PRINT': 'enable_print',
                    'SEAT': 'enable_seat',
                    'TRASH': 'enable_trash',
                    'CARRY': 'enable_carry',
                    'CLEAN': 'enable_clean',
                    'HELP': 'enable_help',
                    'GAME': 'enable_game',
                    'PARTTIME': 'enable_parttime',
                    'COMPANION': 'enable_companion'
                }
                const featureKey = featureMap[item.task_type]
                if (featureKey) {
                    return config.value[featureKey] !== 0
                }
                return true
            })
            orderList.value = filteredList.map((item: any) => mapOrderItem(item))
        } else {
            orderList.value = []
        }
    } catch (e) {
        console.error('加载订单大厅异常:', e)
        orderList.value = []
    }
}

const syncCurrentSchool = () => {
    const school = uni.getStorageSync('current_school')
    currentSchoolId.value = Number(school?.id || 0)
}

const switchTab = (idx: any) => {
    currentTab.value = Number(idx)
    loadOrders()
}

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

const toOrderDetail = (order: any) => {
    if (diyStore.mode == 'decorate') return
    redirect({ url: `/addon/sd_xiaoyuan/pages/order/detail?id=${order.id}` })
}

const applyRunnerUrl = '/addon/sd_xiaoyuan/pages/runner/apply'

const onAcceptOrder = async (order: any) => {
    if (diyStore.mode == 'decorate') return
    if (!memberStore.token) {
        redirect({ url: '/app/pages/auth/login' })
        return
    }
    let runnerRes: any = null
    try {
        runnerRes = await getRunnerInfo()
    } catch (e) {
        uni.showToast({ title: '获取接单员信息失败', icon: 'none' })
        return
    }
    if (runnerRes.code !== 1 || !runnerRes.data) {
        uni.showModal({
            title: '提示',
            content: '您还不是接单员，是否前往申请认证？',
            success: (res) => {
                if (res.confirm) uni.navigateTo({ url: applyRunnerUrl })
            }
        })
        return
    }
    if (runnerRes.data.status !== 1) {
        uni.showModal({
            title: '提示',
            content: '您的接单员尚未通过审核，是否前往申请认证页面？',
            success: (res) => {
                if (res.confirm) uni.navigateTo({ url: applyRunnerUrl })
            }
        })
        return
    }
    uni.showModal({
        title: '确认接单',
        content: '确定要接取这个订单吗？',
        success: async (res) => {
            if (!res.confirm) return
            try {
                uni.showLoading({ title: '接单中...' })
                const result: any = await acceptOrderApi({ id: order.id })
                uni.hideLoading()
                if (result.code === 1) {
                    uni.showToast({ title: '接单成功', icon: 'success' })
                    loadOrders()
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
    })
}

const loadConfig = async () => {
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            config.value = res.data
        }
    } catch (e) {
        console.error('加载配置失败:', e)
    }
}

const onSchoolChanged = () => {
    syncCurrentSchool()
    loadOrders()
}

onMounted(() => {
    ;(async () => {
        await ensureXiaoyuanDefaultSchool()
        syncCurrentSchool()
        await loadConfig()
        loadOrders()
    })()
    uni.$on('xiaoyuan_school_changed', onSchoolChanged)
})

onUnmounted(() => {
    uni.$off('xiaoyuan_school_changed', onSchoolChanged)
})

watch(() => diyComponent.value, () => {
    loadOrders()
}, { deep: true })

onShow(() => {
    ensureXiaoyuanDefaultSchool().then(() => {
        syncCurrentSchool()
        loadOrders()
    })
})
</script>

<style lang="scss" scoped>
.diy-xiaoyuan-order-hall {
    padding: 0;
    
    .hall-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20rpx;
        
        .hall-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
        
        .hall-more {
            display: flex;
            align-items: center;
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .hall-tabs {
        display: flex;
        gap: 16rpx;
        margin-bottom: 20rpx;
        overflow-x: auto;
        
        .tab-item {
            padding: 12rpx 24rpx;
            background: #f5f5f5;
            border-radius: 30rpx;
            font-size: 26rpx;
            color: #666;
            white-space: nowrap;
            
            &.active {
                background: linear-gradient(135deg, #c0fe95, #d1ff7c);
                color: #333;
            }
        }
    }
    
    .hall-list {
        .accept-btn {
            background: linear-gradient(to top, #aaf69b, #d1ff7c);
            color: #000000;
            border: none;
            border-radius: 16rpx;
            padding: 0 50rpx;
            height: 70rpx;
            line-height: 70rpx;
            font-size: 28rpx;
            font-weight: bold;
            box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
        }
        .accept-btn::after {
            border: none;
        }
        .empty-tip {
            text-align: center;
            padding: 80rpx 40rpx;
            color: #999;
            font-size: 26rpx;
        }
    }
}
</style>
