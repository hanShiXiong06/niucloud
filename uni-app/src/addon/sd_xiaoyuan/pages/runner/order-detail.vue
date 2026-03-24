<template>
    <view class="order-detail">
        <!-- 订单状态 -->
        <view class="status-section" :class="'status-bg-' + order.status">
            <text class="status-text">{{ getStatusText(order.status) }}</text>
            <text class="status-desc">{{ getStatusDesc(order.status) }}</text>
        </view>

        <!-- 用户信息 -->
        <view class="user-section">
            <view class="user-info">
                <text class="label">下单用户</text>
                <text class="value">{{ order.member_nickname || ('用户' + order.member_id) }}</text>
            </view>
            <view class="action-btns">
                <view class="action-btn" v-if="order.pickup_mobile" @click="callUser('pickup')">
                    <text class="iconfont icon-phone"></text>
                    <text>联系取件人</text>
                </view>
                <view class="action-btn" v-if="order.receive_mobile" @click="callUser('receive')">
                    <text class="iconfont icon-phone"></text>
                    <text>联系收件人</text>
                </view>
            </view>
        </view>

        <!-- 地址信息 -->
        <view class="address-section" v-if="order.pickup_address || order.receive_address">
            <view class="address-item" v-if="order.pickup_address">
                <view class="address-icon pickup">
                    <text class="iconfont icon-location"></text>
                </view>
                <view class="address-content">
                    <view class="address-header" v-if="order.pickup_name || order.pickup_mobile">
                        <text class="name">{{ order.pickup_name }}</text>
                        <text class="mobile">{{ order.pickup_mobile }}</text>
                    </view>
                    <text class="address">{{ order.pickup_address }}</text>
                </view>
                <view class="nav-btn" @click="navigateTo('pickup')">
                    <text class="iconfont icon-navigation"></text>
                    <text>导航</text>
                </view>
            </view>
            <view class="address-line" v-if="order.pickup_address && order.receive_address"></view>
            <view class="address-item" v-if="order.receive_address">
                <view class="address-icon receive">
                    <text class="iconfont icon-location"></text>
                </view>
                <view class="address-content">
                    <view class="address-header" v-if="order.receive_name || order.receive_mobile">
                        <text class="name">{{ order.receive_name }}</text>
                        <text class="mobile">{{ order.receive_mobile }}</text>
                    </view>
                    <text class="address">{{ order.receive_address }}</text>
                </view>
                <view class="nav-btn" @click="navigateTo('receive')">
                    <text class="iconfont icon-navigation"></text>
                    <text>导航</text>
                </view>
            </view>
        </view>

        <!-- 订单信息 -->
        <view class="info-section">
            <view class="section-title">订单信息</view>
            <view class="info-item">
                <text class="label">订单编号</text>
                <text class="value">{{ order.order_no }}</text>
            </view>
            <view class="info-item">
                <text class="label">服务类型</text>
                <text class="value">{{ getTaskTypeName(order.task_type) }}</text>
            </view>
            <view class="info-item" v-if="order.express_company">
                <text class="label">快递公司</text>
                <text class="value">{{ order.express_company }}</text>
            </view>
            <view class="info-item" v-if="order.express_no">
                <text class="label">快递单号</text>
                <text class="value">{{ order.express_no }}</text>
            </view>
            <view class="info-item" v-if="order.pickup_code">
                <text class="label">取件码</text>
                <text class="value highlight">{{ order.pickup_code }}</text>
            </view>
            <view class="info-item" v-if="order.task_desc">
                <text class="label">任务描述</text>
                <text class="value">{{ order.task_desc }}</text>
            </view>
            <view class="info-item" v-if="order.remark">
                <text class="label">备注</text>
                <text class="value">{{ order.remark }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'PRINT' && extData.print_side">
                <text class="label">打印方式</text>
                <text class="value">{{ extData.print_side === 'single' ? '单面' : '双面' }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'PRINT' && extData.print_color">
                <text class="label">打印颜色</text>
                <text class="value">{{ extData.print_color === 'color' ? '彩印' : '黑白' }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'PRINT' && extData.paper_size">
                <text class="label">纸张规格</text>
                <text class="value">{{ extData.paper_size }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'PRINT' && extData.page_count">
                <text class="label">打印页数</text>
                <text class="value">{{ extData.page_count }}页</text>
            </view>
            <view class="info-item column" v-if="order.task_type === 'PRINT' && extData.files && extData.files.length > 0 && order.status >= 20">
                <text class="label">打印文件</text>
                <view class="file-list">
                    <view class="file-item" v-for="(file, fi) in extData.files" :key="fi" @click="openFile(file)">
                        <text class="file-name">{{ getFileName(file) }}</text>
                        <text class="file-action">预览/下载</text>
                    </view>
                </view>
            </view>
            <view class="info-item" v-if="order.task_type === 'BUY' && extData.gender_limit && extData.gender_limit !== 'none'">
                <text class="label">性别限制</text>
                <text class="value">{{ extData.gender_limit === 'male' ? '仅限男生' : '仅限女生' }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'BUY' && extData.expect_time">
                <text class="label">期望送达</text>
                <text class="value">{{ extData.expect_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'EXPRESS' && extData.station_name">
                <text class="label">快递站点</text>
                <text class="value">{{ extData.station_name }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'EXPRESS' && extData.pickup_code">
                <text class="label">取件码</text>
                <text class="value highlight">{{ extData.pickup_code }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.game_type">
                <text class="label">游戏类型</text>
                <text class="value">{{ extData.game_type }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.service_type">
                <text class="label">服务类型</text>
                <text class="value">{{ extData.service_type }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.rank_level">
                <text class="label">段位等级</text>
                <text class="value">{{ extData.rank_level }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.voice_chat">
                <text class="label">语音陪玩</text>
                <text class="value">是</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.clean_type">
                <text class="label">清洁类型</text>
                <text class="value">{{ extData.clean_type }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.area">
                <text class="label">清洁面积</text>
                <text class="value">{{ extData.area }}㎡</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.appointment_time">
                <text class="label">预约时间</text>
                <text class="value">{{ extData.appointment_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'TRASH' && extData.trash_desc">
                <text class="label">垃圾描述</text>
                <text class="value">{{ extData.trash_desc }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.remark">
                <text class="label">搬运说明</text>
                <text class="value">{{ extData.remark }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'HELP' && extData.help_type">
                <text class="label">求助类型</text>
                <text class="value">{{ getHelpTypeName(extData.help_type) }}</text>
            </view>
            
            <!-- 代排队 QUEUE -->
            <view class="info-item" v-if="order.task_type === 'QUEUE' && extData.location_type">
                <text class="label">地点类型</text>
                <text class="value">{{ getQueueLocationTypeName(extData.location_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'QUEUE' && extData.queue_location">
                <text class="label">排队地点</text>
                <text class="value">{{ extData.queue_location }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'QUEUE' && extData.queue_time">
                <text class="label">排队时间</text>
                <text class="value">{{ extData.queue_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'QUEUE' && extData.estimated_duration">
                <text class="label">预计时长</text>
                <text class="value">{{ extData.estimated_duration }}分钟</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'QUEUE' && extData.queue_purpose">
                <text class="label">排队目的</text>
                <text class="value">{{ extData.queue_purpose }}</text>
            </view>
            
            <!-- 代占座 SEAT -->
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.location_type">
                <text class="label">地点类型</text>
                <text class="value">{{ getSeatLocationTypeName(extData.location_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.location_detail">
                <text class="label">具体位置</text>
                <text class="value">{{ extData.location_detail }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.seat_location">
                <text class="label">占座地点</text>
                <text class="value">{{ extData.seat_location }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.seat_time">
                <text class="label">占座时间</text>
                <text class="value">{{ extData.seat_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.seat_count">
                <text class="label">座位数量</text>
                <text class="value">{{ extData.seat_count }}个</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && (extData.seat_duration || extData.duration)">
                <text class="label">占座时长</text>
                <text class="value">{{ extData.seat_duration || extData.duration }}分钟</text>
            </view>
            
            <!-- 拼单 GROUP -->
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.group_type">
                <text class="label">拼单类型</text>
                <text class="value">{{ getGroupTypeName(extData.group_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.shop_name">
                <text class="label">商家名称</text>
                <text class="value">{{ extData.shop_name }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.max_members">
                <text class="label">拼单人数</text>
                <text class="value">{{ extData.current_members || 1 }}/{{ extData.max_members }}人</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.per_price">
                <text class="label">人均价格</text>
                <text class="value">¥{{ extData.per_price }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.delivery_fee">
                <text class="label">配送费</text>
                <text class="value">¥{{ extData.delivery_fee }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.delivery_address">
                <text class="label">配送地址</text>
                <text class="value">{{ extData.delivery_address }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.order_content">
                <text class="label">点单内容</text>
                <text class="value">{{ extData.order_content }}</text>
            </view>
            
            <!-- 游戏陪玩补充字段 -->
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.game_name">
                <text class="label">游戏ID</text>
                <text class="value">{{ extData.game_name }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.unit">
                <text class="label">计价单位</text>
                <text class="value">{{ extData.unit }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.online_time">
                <text class="label">在线时间</text>
                <text class="value">{{ extData.online_time }}</text>
            </view>
            
            <!-- 帮我送 SEND -->
            <view class="info-item" v-if="order.task_type === 'SEND' && extData.expect_time">
                <text class="label">期望送达</text>
                <text class="value">{{ extData.expect_time }}</text>
            </view>
            
            <!-- 扔垃圾补充字段 -->
            <view class="info-item" v-if="order.task_type === 'TRASH' && extData.trash_type">
                <text class="label">垃圾类型</text>
                <text class="value">{{ getTrashTypeName(extData.trash_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'TRASH' && extData.bag_count">
                <text class="label">垃圾袋数</text>
                <text class="value">{{ extData.bag_count }}袋</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'TRASH' && extData.floor">
                <text class="label">楼层</text>
                <text class="value">{{ extData.floor }}楼</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'TRASH' && extData.has_elevator !== undefined">
                <text class="label">电梯</text>
                <text class="value">{{ extData.has_elevator ? '有电梯' : '无电梯' }}</text>
            </view>
            
            <!-- 帮搬运补充字段 -->
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.item_type">
                <text class="label">物品类型</text>
                <text class="value">{{ getCarryItemTypeName(extData.item_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.item_count">
                <text class="label">物品数量</text>
                <text class="value">{{ extData.item_count }}件</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.from_floor">
                <text class="label">起始楼层</text>
                <text class="value">{{ extData.from_floor }}楼</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.to_floor">
                <text class="label">目标楼层</text>
                <text class="value">{{ extData.to_floor }}楼</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.has_elevator !== undefined">
                <text class="label">电梯</text>
                <text class="value">{{ extData.has_elevator ? '有电梯' : '无电梯' }}</text>
            </view>
            
            <!-- 代清洁补充字段 -->
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.room_type">
                <text class="label">房间类型</text>
                <text class="value">{{ getRoomTypeName(extData.room_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.tools_provided !== undefined">
                <text class="label">工具提供</text>
                <text class="value">{{ extData.tools_provided ? '需要提供工具' : '自带工具' }}</text>
            </view>
            
            <!-- 图片显示 -->
            <view class="info-item column" v-if="extData.images && extData.images.length > 0">
                <text class="label">物品图片</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in parseImages(extData.images)" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, extData.images)"
                    />
                </view>
            </view>
            <view class="info-item column" v-if="order.goods_image && order.goods_image.length > 0">
                <text class="label">展示图片</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in parseImages(order.goods_image)" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, order.goods_image)"
                    />
                </view>
            </view>
            
            <!-- 通用字段 -->
            <view class="info-item" v-if="order.weight > 0">
                <text class="label">物品重量</text>
                <text class="value">{{ order.weight }}kg</text>
            </view>
            <view class="info-item" v-if="order.distance > 0">
                <text class="label">配送距离</text>
                <text class="value">{{ order.distance }}km</text>
            </view>
            <view class="info-item" v-if="order.is_urgent">
                <text class="label">加急服务</text>
                <text class="value highlight">是</text>
            </view>
            <view class="info-item">
                <text class="label">下单时间</text>
                <text class="value">{{ order.create_time }}</text>
            </view>
            
            <!-- 任务凭证 -->
            <view class="info-item column" v-if="order.status >= 40 && extData.delivery_images && extData.delivery_images.length > 0">
                <text class="label">任务凭证</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in extData.delivery_images" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, extData.delivery_images)"
                    />
                </view>
            </view>
            
            <!-- 完成凭证 -->
            <view class="info-item column" v-if="order.status === 50 && extData.proof_images && extData.proof_images.length > 0">
                <text class="label">完成凭证</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in extData.proof_images" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, extData.proof_images)"
                    />
                </view>
            </view>
        </view>

        <!-- 收益信息 -->
        <view class="income-section">
            <view class="section-title">收益信息</view>
            <view class="income-item">
                <text class="label">订单金额</text>
                <text class="value">¥{{ order.actual_fee }}</text>
            </view>
            <view class="income-item">
                <text class="label">平台抽成</text>
                <text class="value">{{ order.commission_rate || 20 }}%</text>
            </view>
            <view class="income-total">
                <text class="label">预计收益</text>
                <text class="value">¥{{ order.runner_income || calculateIncome(order.actual_fee) }}</text>
            </view>
        </view>

        <!-- 底部操作 -->
        <view class="action-bar" v-if="order.status >= 20 && order.status < 50">
            <button class="btn-reject" v-if="order.status === 20" @click="rejectOrder">拒绝订单</button>
            <button class="btn-action" v-if="order.status === 20" @click="handleStatus20Action">{{ getStatus20Text(order.task_type) }}</button>
            <button class="btn-action" v-if="order.status === 30" @click="deliveryOrder">{{ getDeliveryText(order.task_type) }}</button>
            <button class="btn-action" v-if="order.status === 40" @click="openProofPopup">{{ getCompleteText(order.task_type) }}</button>
        </view>

        <!-- 完成凭证弹窗 -->
        <view class="proof-mask" v-if="showProofPopup" @click="showProofPopup = false"></view>
        <view class="proof-popup" v-if="showProofPopup">
            <view class="proof-title">上传完成凭证</view>
            <view class="proof-desc">请上传送达/完成凭证图片（必填）</view>
            
            <!-- 图片列表 -->
            <view class="proof-images">
                <view class="proof-img-item" v-for="(url, index) in proofImages" :key="index">
                    <image :src="img(url)" mode="aspectFill"></image>
                    <view class="proof-img-del" @click="removeProofImage(index)">×</view>
                </view>
                <view class="proof-img-add" v-if="proofImages.length < 4" @click="chooseProofImage">
                    <u-icon name="plus" size="40" color="#999"></u-icon>
                    <text>添加图片</text>
                </view>
            </view>
            
            <view class="proof-btns">
                <button class="proof-btn-cancel" @click="showProofPopup = false">取消</button>
                <button class="proof-btn-confirm" @click="confirmComplete" :disabled="proofImages.length === 0">确认提交</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderDetail, pickupOrder as pickupOrderApi, deliveryOrder as deliveryOrderApi, completeOrder as completeOrderApi, rejectOrder as rejectOrderApi } from '../../api/runner'
import { img } from '@/utils/common'
import { getStatusText, getStatusDesc, getStatus20Text, getDeliveryText, getCompleteText, getTaskTypeName, needPickupStep, taskTypeMap } from '../../utils/order-status'
import { uploadImage } from '@/app/api/system'

const order = ref<any>({})
const extData = ref<any>({})

const queueLocationTypeMap: Record<string, string> = {
    'CANTEEN': '食堂', 'EXPRESS': '快递点', 'SERVICE': '服务窗口', 'OTHER': '其他'
}
const seatLocationTypeMap: Record<string, string> = {
    'LIBRARY': '图书馆', 'STUDY_ROOM': '自习室', 'CLASSROOM': '教室', 'OTHER': '其他'
}
const groupTypeMap: Record<string, string> = {
    'TEA': '拼奶茶', 'FOOD': '拼外卖', 'FRUIT': '拼水果', 'RIDE': '拼车', 'OTHER': '其他'
}
const helpTypeMap: Record<string, string> = {
    'ERRAND': '跑腿帮忙', 'ONLINE': '线上帮忙', 'STUDY': '学习辅导', 'TECH': '技术支持', 'OTHER': '其他'
}
const trashTypeMap: Record<string, string> = {
    'HOUSEHOLD': '生活垃圾', 'RECYCLABLE': '可回收物', 'KITCHEN': '厨余垃圾', 'HAZARDOUS': '有害垃圾', 'BULKY': '大件垃圾', 'OTHER': '其他'
}
const carryItemTypeMap: Record<string, string> = {
    'LUGGAGE': '行李箱', 'BOX': '纸箱/包裹', 'FURNITURE': '家具', 'APPLIANCE': '电器', 'BOOKS': '书籍', 'OTHER': '其他'
}
const roomTypeMap: Record<string, string> = {
    'SINGLE': '单间', 'DOUBLE': '双人间', 'SUITE': '套间', 'DORM': '宿舍', 'STUDIO': '开间', 'OTHER': '其他'
}

const currentOrderId = ref(0)

onLoad((options: any) => {
    if (options.id) {
        currentOrderId.value = parseInt(options.id)
        loadOrderDetail()
    }
})

const loadOrderDetail = async () => {
    if (!currentOrderId.value) return
    
    try {
        const res: any = await getOrderDetail(currentOrderId.value)
        if (res.code === 1 && res.data) {
            order.value = res.data
            
            // 如果订单状态是待接单（10），说明已被拒绝或未接单，跳转到订单大厅
            if (order.value.status === 10) {
                uni.showToast({ title: '订单已返回大厅', icon: 'none' })
                setTimeout(() => {
                    uni.redirectTo({ url: '/addon/sd_xiaoyuan/pages/runner/order-hall' })
                }, 1500)
                return
            }
            
            if (order.value.ext) {
                try {
                    extData.value = typeof order.value.ext === 'string' ? JSON.parse(order.value.ext) : order.value.ext
                } catch {
                    extData.value = {}
                }
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const getQueueLocationTypeName = (type: string) => queueLocationTypeMap[type] || type || '其他'

// 状态20时的操作处理
const handleStatus20Action = () => {
    const type = order.value.task_type
    // 需要取货步骤的类型
    if (needPickupStep(type)) {
        pickupOrder()
    } else {
        // 其他类型直接进入下一步
        deliveryOrder()
    }
}

const getSeatLocationTypeName = (type: string) => seatLocationTypeMap[type] || type || '其他'
const getGroupTypeName = (type: string) => groupTypeMap[type] || type || '拼单'
const getHelpTypeName = (type: string) => helpTypeMap[type] || type || '帮忙'
const getTrashTypeName = (type: string) => trashTypeMap[type] || type || '垃圾'
const getCarryItemTypeName = (type: string) => carryItemTypeMap[type] || type || '物品'
const getRoomTypeName = (type: string) => roomTypeMap[type] || type || ''

const parseImages = (images: string | string[]) => {
    if (!images) return []
    if (typeof images === 'string') {
        return images.split(',').filter((i: string) => i)
    }
    return images
}

const previewImage = (current: string, images: string | string[]) => {
    const urls = parseImages(images)
    uni.previewImage({
        current: img(current),
        urls: urls.map((u: string) => img(u))
    })
}

const calculateIncome = (fee: number) => {
    return (fee * 0.8).toFixed(2)
}

const getFileName = (url: string) => {
    if (!url) return ''
    const parts = url.split('/')
    return parts[parts.length - 1] || url
}

const openFile = (url: string) => {
    if (!url) return
    const fullUrl = img(url)
    const ext = url.split('.').pop()?.toLowerCase() || ''
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']
    if (imageExts.includes(ext)) {
        uni.previewImage({ urls: [fullUrl], current: fullUrl })
    } else {
        // #ifdef H5
        window.open(fullUrl, '_blank')
        // #endif
        // #ifndef H5
        uni.downloadFile({
            url: fullUrl,
            success: (res) => {
                if (res.statusCode === 200) {
                    uni.openDocument({
                        filePath: res.tempFilePath,
                        showMenu: true,
                        fail: () => {
                            uni.showToast({ title: '无法打开此文件', icon: 'none' })
                        }
                    })
                }
            },
            fail: () => {
                uni.showToast({ title: '下载失败', icon: 'none' })
            }
        })
        // #endif
    }
}

const callUser = (type: string) => {
    const mobile = type === 'pickup' ? order.value.pickup_mobile : order.value.receive_mobile
    if (mobile) {
        uni.makePhoneCall({ phoneNumber: mobile })
    }
}

const navigateTo = (type: string) => {
    const lat = type === 'pickup' ? order.value.pickup_lat : order.value.receive_lat
    const lng = type === 'pickup' ? order.value.pickup_lng : order.value.receive_lng
    const name = type === 'pickup' ? order.value.pickup_address : order.value.receive_address
    
    if (lat && lng) {
        uni.openLocation({
            latitude: parseFloat(lat),
            longitude: parseFloat(lng),
            name: name
        })
    } else {
        uni.showToast({ title: '暂无位置信息', icon: 'none' })
    }
}

const pickupOrder = async () => {
    try {
        uni.showLoading({ title: '操作中...' })
        const res: any = await pickupOrderApi({ id: order.value.id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '已确认取货', icon: 'success' })
            loadOrderDetail()
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const deliveryOrder = async () => {
    try {
        uni.showLoading({ title: '操作中...' })
        const res: any = await deliveryOrderApi({ id: order.value.id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '已开始配送', icon: 'success' })
            loadOrderDetail()
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const showProofPopup = ref(false)
const proofImages = ref<string[]>([])

const chooseProofImage = () => {
    uni.chooseImage({
        count: 4 - proofImages.value.length,
        sizeType: ['compressed'],
        sourceType: ['album', 'camera'],
        success: async (res) => {
            try {
                uni.showLoading({ title: '上传中...' })
                for (const tempFilePath of res.tempFilePaths) {
                    const uploadRes = await uploadImage(tempFilePath)
                    if (uploadRes.code === 1 && uploadRes.data?.url) {
                        proofImages.value.push(uploadRes.data.url)
                    }
                }
                uni.hideLoading()
            } catch (e: any) {
                uni.hideLoading()
                handleUploadError(e)
            }
        },
        fail: (e) => {
            handleUploadError(e)
        }
    })
}

const removeProofImage = (index: number) => {
    proofImages.value.splice(index, 1)
}

const handleUploadError = (error: any) => {
    console.error('上传错误:', error)
    if (error.errno === 112 || error.errCode === 112) {
        uni.showToast({ 
            title: '上传失败，请在隐私保护指引中声明相册权限', 
            icon: 'none',
            duration: 3000
        })
    } else {
        const message = error.errMsg || error.msg || '上传失败，请重试'
        uni.showToast({ title: message, icon: 'none' })
    }
}

const openProofPopup = () => {
    proofImages.value = []
    showProofPopup.value = true
}

const confirmComplete = async () => {
    if (proofImages.value.length === 0) {
        uni.showToast({ title: '请上传凭证图片', icon: 'none' })
        return
    }
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await completeOrderApi({ id: order.value.id, proof_images: proofImages.value })
        uni.hideLoading()
        showProofPopup.value = false
        if (res.code === 1) {
            uni.showToast({ title: '订单已完成', icon: 'success' })
            loadOrderDetail()
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const rejectOrder = () => {
    uni.showModal({
        title: '拒绝订单',
        content: '确定要拒绝这个订单吗？订单将返回大厅等待其他接单员接单',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '操作中...' })
                    const result: any = await rejectOrderApi({ id: order.value.id, reason: '骑手拒绝接单' })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '订单已返回大厅', icon: 'success' })
                        setTimeout(() => {
                            uni.redirectTo({ url: '/addon/sd_xiaoyuan/pages/runner/order-hall' })
                        }, 1500)
                    } else {
                        uni.showToast({ title: result.msg || '操作失败', icon: 'none' })
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
.order-detail {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 150rpx;
}

.status-section {
    padding: 60rpx 30rpx;
    color: #fff;
    
    &.status-bg-20 { background: linear-gradient(135deg, #ff9500, #ff6b00); }
    &.status-bg-30 { background: linear-gradient(135deg, #c0fe95, #88f78d); color: #333; }
    &.status-bg-40 { background: linear-gradient(135deg, #c0fe95, #88f78d); color: #333; }
    &.status-bg-50 { background: linear-gradient(135deg, #999, #666); }
    
    .status-text {
        display: block;
        font-size: 40rpx;
        font-weight: bold;
        margin-bottom: 16rpx;
    }
    
    .status-desc {
        font-size: 28rpx;
        opacity: 0.9;
    }
}

.user-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
    
    .user-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20rpx;
        
        .label {
            color: #999;
        }
        
        .value {
            font-weight: bold;
        }
    }
    
    .action-btns {
        display: flex;
        gap: 20rpx;
        
        .action-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20rpx;
            background: #f8f8f8;
            border-radius: 12rpx;
            
            .iconfont {
                font-size: 40rpx;
                color: #52c41a;
                margin-bottom: 8rpx;
            }
            
            text {
                font-size: 24rpx;
                color: #666;
            }
        }
    }
}

.address-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.address-item {
    display: flex;
    align-items: flex-start;
    
    .address-icon {
        width: 50rpx;
        height: 50rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
        
        &.pickup { background: #c0fe95; color: #333; }
        &.receive { background: #000; color: #fff; }
    }
    
    .address-content {
        flex: 1;
        
        .address-header {
            margin-bottom: 8rpx;
            
            .name {
                font-size: 30rpx;
                font-weight: bold;
                color: #333;
                margin-right: 20rpx;
            }
            
            .mobile {
                font-size: 28rpx;
                color: #666;
            }
        }
        
        .address {
            font-size: 26rpx;
            color: #999;
        }
    }
    
    .nav-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 10rpx 20rpx;
        
        .iconfont {
            font-size: 30rpx;
            color: #52c41a;
        }
        
        text {
            font-size: 22rpx;
            color: #52c41a;
        }
    }
}

.address-line {
    width: 2rpx;
    height: 40rpx;
    background: #e0e0e0;
    margin: 16rpx 0 16rpx 24rpx;
}

.info-section, .income-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.info-item, .income-item {
    display: flex;
    justify-content: space-between;
    padding: 16rpx 0;
    
    &.column {
        flex-direction: column;
        
        .label {
            margin-bottom: 16rpx;
        }
    }
    
    .label {
        font-size: 28rpx;
        color: #666;
    }
    
    .value {
        font-size: 28rpx;
        color: #333;
        
        &.highlight {
            color: #ff6b00;
            font-weight: bold;
            font-size: 28rpx;
        }
    }
    
    .file-list {
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16rpx 20rpx;
            background: #f5f5f5;
            border-radius: 8rpx;
            margin-bottom: 12rpx;
            
            .file-name {
                flex: 1;
                font-size: 26rpx;
                color: #333;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .file-action {
                font-size: 24rpx;
                color: #1890ff;
                margin-left: 20rpx;
            }
        }
    }
}

.income-total {
    display: flex;
    justify-content: space-between;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
    margin-top: 10rpx;
    
    .label {
        font-size: 30rpx;
        font-weight: bold;
    }
    
    .value {
        font-size: 30rpx;
        color: #ff6b00;
        font-weight: bold;
    }
}

.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    gap: 20rpx;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
    
    button {
        flex: 1;
        height: 72rpx;
        line-height: 72rpx;
        font-size: 30rpx;
        border-radius: 44rpx;
    }
    
    .btn-reject {
        background: #fff;
        color: #666;
        border: 1rpx solid #ddd;
    }
    
    .btn-action {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        border: none;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
}

.proof-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100;
}

.proof-popup {
    position: fixed;
    left: 30rpx; right: 30rpx; bottom: 0;
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    padding: 40rpx 30rpx calc(40rpx + env(safe-area-inset-bottom));
    z-index: 101;

    .proof-title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 12rpx;
    }

    .proof-desc {
        font-size: 26rpx;
        color: #999;
        margin-bottom: 30rpx;
    }

    .proof-images {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;
        margin-bottom: 30rpx;

        .proof-img-item {
            width: 150rpx;
            height: 150rpx;
            border-radius: 12rpx;
            overflow: hidden;
            position: relative;

            image { width: 100%; height: 100%; }

            .proof-img-del {
                position: absolute;
                top: 0; right: 0;
                width: 40rpx; height: 40rpx;
                background: rgba(0,0,0,0.5);
                color: #fff;
                font-size: 28rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0 0 0 12rpx;
            }
        }

        .proof-img-add {
            width: 150rpx;
            height: 150rpx;
            border: 2rpx dashed #ddd;
            border-radius: 12rpx;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8rpx;

            text {
                font-size: 22rpx;
                color: #999;
            }
        }
    }

    .proof-img-item {
        width: 150rpx;
        height: 150rpx;
        border-radius: 12rpx;
        overflow: hidden;
        position: relative;

        image { width: 100%; height: 100%; }

        .proof-img-del {
            position: absolute;
            top: 0; right: 0;
            width: 40rpx; height: 40rpx;
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 28rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0 0 0 12rpx;
        }
    }

    .proof-img-add {
        width: 150rpx;
        height: 150rpx;
        border: 2rpx dashed #ddd;
        border-radius: 12rpx;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .proof-btns {
        display: flex;
        gap: 20rpx;

        button {
            flex: 1;
            height: 80rpx;
            line-height: 80rpx;
            border-radius: 40rpx;
            font-size: 28rpx;
        }

        .proof-btn-cancel {
            background: #f5f5f5;
            color: #666;
        }

        .proof-btn-confirm {
            background: linear-gradient(to top, #aaf69b, #d1ff7c);
            color: #000;

            &[disabled] {
                opacity: 0.5;
            }
        }
    }
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.order-image {
    width: 150rpx;
    height: 150rpx;
    border-radius: 12rpx;
    overflow: hidden;
}
</style>
