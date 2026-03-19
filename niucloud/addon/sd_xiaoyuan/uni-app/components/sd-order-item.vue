<template>
    <view class="sd-order-item" @click="$emit('click')">
        <view class="order-top-bar">
            <view class="top-bar-left">
                <view class="type-tag" :class="'type-' + order.task_type">{{ taskTypeName }}</view>
                <text class="order-no-text">订单号：{{ order.order_no || order.id }}</text>
            </view>
            <text class="status" :class="'status-' + order.status">{{ statusText }}</text>
        </view>
                
        <view class="order-header">
            <view class="left-section">
                <view class="user-info" v-if="showUser">
                    <image class="avatar" :src="img(order.member_headimg || '/static/images/default-avatar.png')" mode="aspectFill"></image>
                    <view class="user-detail">
                        <text class="nickname">{{ order.member_nickname || '用户' }}</text>
                        <view class="credit">
                            <text class="credit-score">信誉分</text> <u-icon name="star-fill" size="12" color="#ff9500"></u-icon>
                            <text class="credit-score">{{ order.member_credit || 100 }}</text>
                        </view>
                    </view>
                </view>
                
            </view>
            <view class="right-info">
                <text class="price">¥{{ order.actual_fee || order.total_fee }}</text>
            </view>
        </view>

        <view class="order-body">
            <view class="type-row">
                <view class="ext-tags" v-if="extTags.length > 0">
                    <text class="ext-tag" v-for="(tag, idx) in extTags" :key="idx">{{ tag }}</text>
                </view>
            </view>

            <view class="order-images" v-if="images.length > 0">
                <image 
                    v-for="(image, idx) in images.slice(0, 3)" 
                    :key="idx" 
                    :src="img(image)" 
                    mode="aspectFill"
                    class="order-image"
                ></image>
                <view class="img-more" v-if="images.length > 3">+{{ images.length - 3 }}</view>
            </view>

            <view class="address-info" v-if="(order.pickup_address && order.pickup_address.trim()) || (order.receive_address && order.receive_address.trim())">
                <view class="address-item" v-if="order.pickup_address && order.pickup_address.trim()">
                    <view class="addr-tag pickup">起</view>
                    <text class="address">{{ order.pickup_address }}</text>
                </view>
                <view class="address-item" v-if="order.receive_address && order.receive_address.trim()">
                    <view class="addr-tag receive">终</view>
                    <text class="address">{{ order.receive_address }}</text>
                </view>
            </view>

            <view class="remark" v-if="order.remark">
                <text class="remark-text">{{ order.remark }}</text>
            </view>
        </view>

        <view class="order-footer">
            <text class="time">{{ order.create_time  }}</text>
            <view class="actions">
                <slot name="actions"></slot>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'

const props = defineProps({
    order: {
        type: Object,
        default: () => ({})
    },
    showUser: {
        type: Boolean,
        default: false
    }
})

defineEmits(['click'])

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '帮我买',
    'SEND': '帮我送',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'PRINT': '代打印',
    'SEAT': '代占座',
    'CARRY': '帮搬运',
    'TRASH': '扔垃圾',
    'CLEAN': '代清洁',
    'HELP': '帮帮忙',
    'GAME': '游戏陪练',
    'GROUP': '拼单'
}

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

const taskTypeName = computed(() => taskTypeMap[props.order.task_type] || props.order.task_type || '未知')
const statusText = computed(() => statusMap[props.order.status] ?? '未知')

const extData = computed(() => {
    if (!props.order.ext) return {}
    if (typeof props.order.ext === 'string') {
        try { return JSON.parse(props.order.ext) } catch { return {} }
    }
    return props.order.ext
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
const helpTypeMap: Record<string, string> = {
    'ERRAND': '跑腿帮忙', 'ONLINE': '线上帮忙', 'STUDY': '学习辅导',
    'TECH': '技术支持', 'OTHER': '其他'
}
const groupTypeMap: Record<string, string> = {
    'TEA': '拼奶茶', 'FOOD': '拼外卖', 'FRUIT': '拼水果', 'RIDE': '拼车', 'OTHER': '其他'
}
const genderLimitMap: Record<string, string> = {
    'MALE': '限男生', 'FEMALE': '限女生', 'ALL': '不限'
}
const queueLocationTypeMap: Record<string, string> = {
    'CANTEEN': '食堂', 'EXPRESS': '快递点', 'SERVICE': '服务窗口', 'OTHER': '其他'
}
const seatLocationTypeMap: Record<string, string> = {
    'LIBRARY': '图书馆', 'STUDY_ROOM': '自习室', 'CLASSROOM': '教室', 'OTHER': '其他'
}

const extTags = computed(() => {
    const tags: string[] = []
    const ext = extData.value
    const taskType = props.order.task_type || ''
    
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
        if (ext.station_name) tags.push(ext.station_name)
        if (ext.express_company) tags.push(ext.express_company)
        if (ext.pickup_code) tags.push('取件码:' + ext.pickup_code)
    } else if (taskType === 'BUY') {
        if (ext.shop_name) tags.push(ext.shop_name)
        if (ext.goods_name) tags.push(ext.goods_name)
        if (ext.expect_time) tags.push(ext.expect_time)
    } else if (taskType === 'SEND') {
        if (ext.goods_desc) tags.push(ext.goods_desc)
        if (ext.weight) tags.push(ext.weight + 'kg')
        if (ext.expect_time) tags.push(ext.expect_time)
    } else if (taskType === 'HELP') {
        if (ext.help_type) tags.push(helpTypeMap[ext.help_type] || ext.help_type)
        if (ext.gender_limit && ext.gender_limit !== 'ALL') tags.push(genderLimitMap[ext.gender_limit] || '')
        if (ext.duration) tags.push(ext.duration + '分钟')
    } else if (taskType === 'CLEAN') {
        if (ext.clean_type) tags.push(cleanTypeMap[ext.clean_type] || ext.clean_type)
        if (ext.room_type) tags.push(ext.room_type)
        if (ext.area) tags.push(ext.area + '㎡')
        if (ext.appointment_time) tags.push(ext.appointment_time)
    } else if (taskType === 'TRASH') {
        if (ext.trash_type) tags.push(trashTypeMap[ext.trash_type] || ext.trash_type)
        if (ext.bag_count) tags.push(ext.bag_count + '袋')
        if (ext.floor) tags.push(ext.floor + '楼')
        if (ext.has_elevator !== undefined) tags.push(ext.has_elevator ? '有电梯' : '无电梯')
    } else if (taskType === 'CARRY') {
        if (ext.item_type) tags.push(carryItemTypeMap[ext.item_type] || ext.item_type)
        if (ext.item_count) tags.push(ext.item_count + '件')
        if (ext.from_floor && ext.to_floor) tags.push(ext.from_floor + '楼→' + ext.to_floor + '楼')
        if (ext.has_elevator !== undefined) tags.push(ext.has_elevator ? '有电梯' : '无电梯')
    } else if (taskType === 'GROUP') {
        if (ext.group_type) tags.push(groupTypeMap[ext.group_type] || ext.group_type)
        if (ext.shop_name) tags.push(ext.shop_name)
        if (ext.max_members) tags.push((ext.current_members || 1) + '/' + ext.max_members + '人')
        if (ext.end_time) tags.push('截止:' + ext.end_time)
    } else if (taskType === 'QUEUE') {
        if (ext.location_type) tags.push(queueLocationTypeMap[ext.location_type] || ext.location_type)
        if (ext.queue_location) tags.push(ext.queue_location)
        if (ext.queue_time) tags.push(ext.queue_time)
        if (ext.estimated_duration) tags.push('约' + ext.estimated_duration + '分钟')
        if (ext.queue_purpose) tags.push(ext.queue_purpose)
    } else if (taskType === 'SEAT') {
        if (ext.location_type) tags.push(seatLocationTypeMap[ext.location_type] || ext.location_type)
        if (ext.location_detail) tags.push(ext.location_detail)
        if (ext.seat_location) tags.push(ext.seat_location)
        if (ext.seat_count) tags.push(ext.seat_count + '个座位')
        if (ext.seat_duration || ext.duration) tags.push((ext.seat_duration || ext.duration) + '分钟')
    }
    
    if (props.order.weight > 0) tags.push(props.order.weight + 'kg')
    if (props.order.distance > 0) tags.push(props.order.distance + 'km')
    if (props.order.is_urgent) tags.push('加急')
    return tags.slice(0, 5)
})

const images = computed(() => {
    const imgs: string[] = []
    if (props.order.goods_image) {
        imgs.push(...props.order.goods_image.split(',').filter(Boolean))
    }
    const ext = extData.value
    if (ext.images) {
        if (typeof ext.images === 'string') {
            imgs.push(...ext.images.split(',').filter(Boolean))
        } else if (Array.isArray(ext.images)) {
            imgs.push(...ext.images)
        }
    }
    return [...new Set(imgs)].slice(0, 5)
})
</script>

<style lang="scss" scoped>
.sd-order-item {
    background: #fff;
    border-radius: 16rpx;
    margin: 20rpx 0;
    padding: 24rpx;
    // box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.05);
}

.order-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12rpx;
    padding-bottom: 12rpx;
    border-bottom: 1rpx solid #f5f5f5;
    
    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 12rpx;
        flex: 1;
    }
    
    .type-tag {
        padding: 4rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        font-weight: 500;
        color: #fff;
        background: linear-gradient(135deg, #00c853, #69f0ae);
        flex-shrink: 0;
        
        &.type-EXPRESS { background: linear-gradient(135deg, #1890ff, #69c0ff); }
        &.type-BUY { background: linear-gradient(135deg, #ff7243, #ffab91); }
        &.type-PRINT { background: linear-gradient(135deg, #722ed1, #b37feb); }
        &.type-GAME { background: linear-gradient(135deg, #eb2f96, #ff85c0); }
        &.type-HELP { background: linear-gradient(135deg, #faad14, #ffd666); }
        &.type-GROUP { background: linear-gradient(135deg, #13c2c2, #5cdbd3); }
        &.type-CLEAN { background: linear-gradient(135deg, #52c41a, #95de64); }
        &.type-TRASH { background: linear-gradient(135deg, #9c27b0, #ce93d8); }
        &.type-CARRY { background: linear-gradient(135deg, #4caf50, #81c784); }
    }
    
    .order-no-text {
        font-size: 22rpx;
        color: #999;
        flex: 1;
    }
    
    .status {
        font-size: 22rpx;
        padding: 2rpx 12rpx;
        border-radius: 4rpx;
        flex-shrink: 0;
        
        &.status-0 { color: #faad14; background: #fff7e6; }
        &.status-10 { color: #1890ff; background: #e6f7ff; }
        &.status-20, &.status-30, &.status-40 { color: #00c853; background: #f6ffed; }
        &.status-50 { color: #52c41a; background: #f6ffed; }
        &.status-90, &.status-91 { color: #999; background: #f5f5f5; }
    }
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20rpx;
    
    .left-section {
        flex: 1;
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 8rpx;
            
            .avatar {
                width: 74rpx;
                height: 74rpx;
                border-radius: 50%;
                margin-right: 16rpx;
            }
            
            .user-detail {
                .nickname {
                    font-size: 28rpx;
                    color: #333;
                    font-weight: 500;
                }
                
                .credit {
                    display: flex;
                    align-items: center;
                    margin-top: 4rpx;
                    
                    .credit-score {
                        font-size: 22rpx;
                        color: #ff9500;
                        margin-left: 4rpx;
                    }
                }
            }
        }
        
        .order-no {
            font-size: 24rpx;
            color: #999;
            margin-left: 90rpx;
        }
    }
    
    .right-info {
        text-align: right;
        
        .price {
            font-size: 32rpx;
            color: #ff7243;
            font-weight: bold;
            display: block;
        }
        
        .status {
            font-size: 24rpx;
            margin-top: 4rpx;
            display: block;
            
            &.status-0 { color: #faad14; }
            &.status-10 { color: #1890ff; }
            &.status-20, &.status-30, &.status-40 { color: #00c853; }
            &.status-50 { color: #52c41a; }
            &.status-90, &.status-91 { color: #999; }
        }
    }
}

.order-body {
    .type-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12rpx;
        margin-bottom: 16rpx;
        
        .type-tag {
            padding: 6rpx 16rpx;
            border-radius: 6rpx;
            font-size: 24rpx;
            color: #fff;
            background: linear-gradient(135deg, #00c853, #69f0ae);
            
            &.type-EXPRESS { background: linear-gradient(135deg, #1890ff, #69c0ff); }
            &.type-BUY { background: linear-gradient(135deg, #ff7243, #ffab91); }
            &.type-PRINT { background: linear-gradient(135deg, #722ed1, #b37feb); }
            &.type-GAME { background: linear-gradient(135deg, #eb2f96, #ff85c0); }
            &.type-HELP { background: linear-gradient(135deg, #faad14, #ffd666); }
            &.type-GROUP { background: linear-gradient(135deg, #13c2c2, #5cdbd3); }
        }
        
        .ext-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8rpx;
            
            .ext-tag {
                padding: 4rpx 12rpx;
                background: #f5f5f5;
                border-radius: 4rpx;
                font-size: 22rpx;
                color: #666;
            }
        }
    }
    
    .order-images {
        display: flex;
        gap: 12rpx;
        margin-bottom: 16rpx;
        
        .order-image {
            width: 160rpx;
            height: 160rpx;
            border-radius: 12rpx;
            object-fit: cover;
        }
        
        .img-more {
            width: 160rpx;
            height: 160rpx;
            border-radius: 12rpx;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28rpx;
        }
    }
    
    .address-info {
        background: #f9f9f9;
        border-radius: 8rpx;
        padding: 16rpx;
        margin-bottom: 12rpx;
        
        .address-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12rpx;
            
            &:last-child {
                margin-bottom: 0;
            }
            
            .addr-tag {
                width: 32rpx;
                height: 32rpx;
                border-radius: 50%;
                font-size: 20rpx;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 12rpx;
                flex-shrink: 0;
                
                &.pickup { background: #00c853; }
                &.receive { background: #ff7243; }
            }
            
            .address {
                font-size: 26rpx;
                color: #333;
                flex: 1;
                line-height: 1.4;
            }
        }
    }
    
    .remark {
        .remark-text {
            font-size: 24rpx;
            color: #999;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    }
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16rpx;
    padding-top: 16rpx;
    border-top: 1rpx solid #f0f0f0;
    
    .time {
        font-size: 24rpx;
        color: #999;
    }
    
    .actions {
        display: flex;
        gap: 16rpx;
    }
}
</style>
