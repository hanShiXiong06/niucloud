<template>
    <view class="order-detail">
        <!-- 订单状态 -->
        <view class="status-section" :class="'status-bg-' + order.status">
            <text class="status-text">{{ getStatusText(order.status) }}</text>
            <text class="status-desc">{{ getStatusDesc(order.status) }}</text>
        </view>

        <!-- 接单员信息(已接单后显示) -->
        <view class="runner-section" v-if="order.runner_id && order.status >= 20 && order.status < 90">
            <view class="runner-info">
                <image class="runner-avatar" :src="img(order.runner_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="runner-detail">
                    <text class="runner-name">{{ order.runner_name }}</text>
                    <view class="runner-score">
                        <text style="color:#ff9500;">★</text>
                        <text>{{ order.runner_score }}</text>
                    </view>
                </view>
            </view>
            <view class="runner-actions">
                <view class="action-btn" @click="callRunner">
                    <text class="iconfont icon-phone"></text>
                    <text>联系接单员</text>
                </view>
                <view class="action-btn" @click="viewRunnerLocation">
                    <text class="iconfont icon-location"></text>
                    <text>查看位置</text>
                </view>
            </view>
        </view>

        <!-- 地图显示接单员位置 -->
        <view class="map-section" v-if="showMap && order.status >= 20 && order.status < 50">
            <map 
                id="runnerMap"
                class="runner-map"
                :latitude="mapCenter.lat"
                :longitude="mapCenter.lng"
                :markers="markers"
                :polyline="polyline"
                :scale="15"
                show-location
            ></map>
        </view>

        <!-- 地址信息 -->
        <view class="address-section" v-if="order.pickup_address || order.receive_address">
            <view class="address-item" v-if="order.pickup_address">
                <view class="addr-tag pickup">起</view>
                <view class="address-content">
                    <view class="address-header" v-if="order.pickup_name || order.pickup_mobile">
                        <text class="name">{{ order.pickup_name || '取货点' }}</text>
                        <text class="mobile">{{ order.pickup_mobile }}</text>
                    </view>
                    <text class="address">{{ order.pickup_address }}</text>
                </view>
            </view>
            
            <view class="address-item" v-if="order.receive_address">
                <view class="addr-tag receive">终</view>
                <view class="address-content">
                    <view class="address-header" v-if="order.receive_name || order.receive_mobile">
                        <text class="name">{{ order.receive_name || '收货人' }}</text>
                        <text class="mobile">{{ order.receive_mobile }}</text>
                    </view>
                    <text class="address">{{ order.receive_address }}</text>
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
                <text class="value">{{ order.pickup_code }}</text>
            </view>
            <view class="info-item" v-if="order.task_desc">
                <text class="label">任务描述</text> 
                <text class="value" style="flex:1;text-align: right;">{{ order.task_desc }}</text>
            </view>
            <view class="info-item" v-if="order.remark && order.remark !== order.task_desc">
                <text class="label">备注</text>
                <text class="value" style="flex:1;text-align: right;">{{ formatRemark(order.remark) }}</text>
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
            <view class="info-item" v-if="order.task_type === 'PRINT' && extData.files && extData.files.length > 0 && order.status < 20">
                <text class="label">打印文件</text>
                <text class="value hint">接单后可查看文件</text>
            </view>
                        <view class="info-item" v-if="order.task_type === 'BUY' && extData.expect_time">
                <text class="label">期望送达</text>
                <text class="value">{{ extData.expect_time }}</text>
            </view>
            <!-- 帮我送 SEND -->
            <view class="info-item" v-if="order.task_type === 'SEND' && extData.expect_time">
                <text class="label">期望送达</text>
                <text class="value">{{ extData.expect_time }}</text>
            </view>
            <!-- 图片显示 -->
            <view class="info-item column" v-if="extData.images && extData.images.length > 0">
                <text class="label">物品图片</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in (typeof extData.images === 'string' ? extData.images.split(',').filter((i: string) => i) : extData.images)" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, extData.images)"
                    />
                </view>
            </view>
            <view class="info-item" v-if="order.task_type === 'EXPRESS' && extData.station_name">
                <text class="label">快递站点</text>
                <text class="value">{{ extData.station_name }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'EXPRESS' && extData.pickup_code">
                <text class="label">取件码</text>
                <text class="value">{{ extData.pickup_code }}</text>
            </view>
            <view class="info-item column" v-if="order.task_type === 'EXPRESS' && extData.packages && extData.packages.length > 0">
                <text class="label">包裹详情</text>
                <view class="package-detail-list">
                    <view class="package-detail-item" v-for="(pkg, idx) in extData.packages" :key="idx">
                        <text class="pkg-name">{{ pkg.name }}</text>
                        <text class="pkg-count">x{{ pkg.count }}</text>
                        <text class="pkg-price">¥{{ (pkg.price * pkg.count).toFixed(2) }}</text>
                    </view>
                </view>
            </view>
            <view class="info-item" v-if="order.task_type === 'EXPRESS' && extData.expect_time">
                <text class="label">期望送达</text>
                <text class="value">{{ extData.expect_time }}</text>
            </view>
            <!-- 游戏陪玩 GAME -->
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.game_type">
                <text class="label">游戏类型</text>
                <text class="value">{{ gameTypeMap[extData.game_type] || extData.game_type }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.service_type">
                <text class="label">服务类型</text>
                <text class="value">{{ gameServiceMap[extData.service_type] || extData.service_type }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.game_name">
                <text class="label">游戏ID</text>
                <text class="value">{{ extData.game_name }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.rank_level">
                <text class="label">段位等级</text>
                <text class="value">{{ extData.rank_level }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.unit">
                <text class="label">计价单位</text>
                <text class="value">{{ extData.unit }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.voice_chat">
                <text class="label">语音陪玩</text>
                <text class="value">{{ extData.voice_chat ? '是' : '否' }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GAME' && extData.online_time">
                <text class="label">在线时间</text>
                <text class="value">{{ extData.online_time }}</text>
            </view>
            <view class="info-item column" v-if="order.task_type === 'GAME' && order.goods_image && order.goods_image.length > 0">
                <text class="label">展示图片</text>
                <view class="image-list">
                    <image 
                        v-for="(imgUrl, idx) in (typeof order.goods_image === 'string' ? order.goods_image.split(',').filter((i: string) => i) : order.goods_image)" 
                        :key="idx" 
                        :src="img(imgUrl)" 
                        mode="aspectFill" 
                        class="order-image"
                        @click="previewImage(imgUrl, order.goods_image)"
                    />
                </view>
            </view>
            <!-- 代清洁 CLEAN -->
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.clean_type">
                <text class="label">清洁类型</text>
                <text class="value">{{ getCleanTypeName(extData.clean_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.room_type">
                <text class="label">房间类型</text>
                <text class="value">{{ getRoomTypeName(extData.room_type) }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.area">
                <text class="label">清洁面积</text>
                <text class="value">{{ extData.area }}㎡</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.appointment_time">
                <text class="label">预约时间</text>
                <text class="value">{{ extData.appointment_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'CLEAN' && extData.tools_provided">
                <text class="label">工具提供</text>
                <text class="value">{{ extData.tools_provided ? '需要提供工具' : '自带工具' }}</text>
            </view>
            
            <!-- 扔垃圾 TRASH -->
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
            
            <!-- 帮搬运 CARRY -->
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
            <view class="info-item" v-if="order.task_type === 'CARRY' && extData.need_helper">
                <text class="label">需要帮手</text>
                <text class="value">{{ extData.need_helper ? '是' : '否' }}</text>
            </view>
            
            <!-- 帮帮忙 HELP -->
            <view class="info-item" v-if="order.task_type === 'HELP' && extData.help_type">
                <text class="label">帮忙类型</text>
                <text class="value">{{ getHelpTypeName(extData.help_type) }}</text>
            </view>
                        <view class="info-item" v-if="order.task_type === 'HELP' && extData.duration">
                <text class="label">预计时长</text>
                <text class="value">{{ extData.duration }}分钟</text>
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
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.end_time">
                <text class="label">截止时间</text>
                <text class="value">{{ extData.end_time }}</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'GROUP' && extData.min_amount">
                <text class="label">起送金额</text>
                <text class="value">¥{{ extData.min_amount }}</text>
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
                <text class="value" style="flex:1;text-align:right;">{{ extData.order_content }}</text>
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
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.seat_duration">
                <text class="label">占座时长</text>
                <text class="value">{{ extData.seat_duration }}小时</text>
            </view>
            <view class="info-item" v-if="order.task_type === 'SEAT' && extData.seat_requirement">
                <text class="label">座位要求</text>
                <text class="value">{{ extData.seat_requirement }}</text>
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
                <text class="value" style="color:#f97316;">是</text>
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

        <!-- 费用明细 -->
        <view class="fee-section">
            <view class="section-title">费用明细</view>
            <view class="fee-item">
                <text class="label">基础费用</text>
                <text class="value">¥{{ order.base_fee }}</text>
            </view>
            <view class="fee-item" v-if="order.distance_fee > 0">
                <text class="label">距离费用</text>
                <text class="value">¥{{ order.distance_fee }}</text>
            </view>
            <view class="fee-item" v-if="order.weight_fee > 0">
                <text class="label">重量费用</text>
                <text class="value">¥{{ order.weight_fee }}</text>
            </view>
            <view class="fee-item" v-if="order.urgent_fee > 0">
                <text class="label">加急费用</text>
                <text class="value">¥{{ order.urgent_fee }}</text>
            </view>
            <view class="fee-item" v-if="order.tip_fee > 0">
                <text class="label">累计打赏</text>
                <text class="value" style="color:#f97316;">¥{{ order.tip_fee }}</text>
            </view>
            <view class="fee-item" v-if="order.coupon_discount > 0">
                <text class="label">优惠券抵扣</text>
                <text class="value discount">-¥{{ order.coupon_discount }}</text>
            </view>
            <view class="fee-total">
                <text class="label">实付金额</text>
                <text class="value total">¥{{ (parseFloat(order.actual_fee || 0) + parseFloat(order.tip_fee || 0)).toFixed(2) }}</text>
            </view>
        </view>

        <!-- 底部操作 -->
        <view class="action-bar" v-if="order.status === 0">
            <button class="btn-cancel" @click="cancelOrder">取消订单</button>
            <button class="btn-pay" @click="payOrder">立即支付 ¥{{ order.actual_fee }}</button>
        </view>
        <view class="action-bar" v-if="order.status === 50 && (!order.is_evaluated)">
            <button class="btn-tip" @click="showTipPopup = true">打赏接单员</button>
            <button class="btn-evaluate" @click="goToEvaluate">评价订单</button>
        </view>

        <!-- 打赏弹窗 -->
        <view class="tip-mask" v-if="showTipPopup" @click="showTipPopup = false"></view>
        <view class="tip-popup" v-if="showTipPopup">
            <view class="tip-title">打赏接单员</view>
            <view class="tip-desc">您的打赏将全额发放给接单员</view>
            <view class="tip-amounts">
                <view class="tip-amount-item" 
                    v-for="amount in [1, 2, 5, 10]" 
                    :key="amount"
                    :class="{ active: tipAmount === amount }"
                    @click="tipAmount = amount"
                >¥{{ amount }}</view>
            </view>
            <view class="tip-custom">
                <text>自定义金额</text>
                <input type="digit" v-model="customTipAmount" placeholder="输入金额" @input="tipAmount = 0" />
            </view>
            <view class="tip-btns">
                <button class="tip-btn-cancel" @click="showTipPopup = false">取消</button>
                <button class="tip-btn-confirm" @click="submitTip">确认打赏</button>
            </view>
        </view>
        
        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getOrderDetail, cancelOrder as cancelOrderApi, getRunnerLocation, tipOrder } from '../../api/xiaoyuan'
import pay from '@/components/pay/pay.vue'
import { img } from '@/utils/common'

const order = ref<any>({})
const extData = ref<any>({})
const runnerInfo = ref<any>({})
const payRef = ref<any>(null)
const orderId = ref(0)
const showMap = ref(false)
const showTipPopup = ref(false)
const tipAmount = ref(2)
const customTipAmount = ref('')
const mapCenter = ref({ lat: 0, lng: 0 })
const markers = ref<any[]>([])
const polyline = ref<any[]>([])

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

const cleanTypeMap: Record<string, string> = {
    'DAILY': '日常保洁',
    'DEEP': '深度清洁',
    'MOVE_IN': '入住清洁',
    'MOVE_OUT': '退租清洁',
    'GLASS': '玻璃清洁',
    'APPLIANCE': '家电清洗',
    'DORM': '宿舍清洁',
    'OFFICE': '办公室清洁',
    'CLASSROOM': '教室清洁',
    'LAB': '实验室清洁',
    'OTHER': '其他'
}

const roomTypeMap: Record<string, string> = {
    'SINGLE': '单间',
    'DOUBLE': '双人间',
    'SUITE': '套间',
    'DORM': '宿舍',
    'STUDIO': '开间',
    'OTHER': '其他'
}

const trashTypeMap: Record<string, string> = {
    'HOUSEHOLD': '生活垃圾',
    'RECYCLABLE': '可回收物',
    'KITCHEN': '厨余垃圾',
    'HAZARDOUS': '有害垃圾',
    'BULKY': '大件垃圾',
    'OTHER': '其他'
}

const carryItemTypeMap: Record<string, string> = {
    'LUGGAGE': '行李箱',
    'BOX': '纸箱/包裹',
    'FURNITURE': '家具',
    'APPLIANCE': '电器',
    'BOOKS': '书籍',
    'OTHER': '其他'
}

const helpTypeMap: Record<string, string> = {
    'ERRAND': '跑腿帮忙',
    'ONLINE': '线上帮忙',
    'STUDY': '学习辅导',
    'TECH': '技术支持',
    'OTHER': '其他'
}

const genderLimitMap: Record<string, string> = {
    'MALE': '仅限男生',
    'FEMALE': '仅限女生',
    'ALL': '不限'
}

const gameTypeMap: Record<string, string> = {
    'WZRY': '王者荣耀',
    'LOL': '英雄联盟',
    'PUBG': '和平精英',
    'CSGO': 'CS2',
    'YS': '原神',
    'EGG': '蛋仔派对',
    'OTHER': '其他'
}

const gameServiceMap: Record<string, string> = {
    'PLAY_WITH': '陪玩',
    'BOOST': '代练',
    'TEACH': '教学',
    'TEAM': '组队'
}


const groupTypeMap: Record<string, string> = {
    'TEA': '拼奶茶',
    'FOOD': '拼外卖',
    'FRUIT': '拼水果',
    'RIDE': '拼车',
    'OTHER': '其他'
}

const queueLocationTypeMap: Record<string, string> = {
    'CANTEEN': '食堂',
    'EXPRESS': '快递点',
    'SERVICE': '服务窗口',
    'OTHER': '其他'
}

const seatLocationTypeMap: Record<string, string> = {
    'LIBRARY': '图书馆',
    'STUDY_ROOM': '自习室',
    'CLASSROOM': '教室',
    'OTHER': '其他'
}

const getCleanTypeName = (type: string) => cleanTypeMap[type] || type || '清洁服务'
const getRoomTypeName = (type: string) => roomTypeMap[type] || type || ''
const getTrashTypeName = (type: string) => trashTypeMap[type] || type || '生活垃圾'
const getCarryItemTypeName = (type: string) => carryItemTypeMap[type] || type || '物品'
const getHelpTypeName = (type: string) => helpTypeMap[type] || type || '帮忙'
const getGenderLimitName = (type: string) => genderLimitMap[type] || type || '不限'
const getGroupTypeName = (type: string) => groupTypeMap[type] || type || '拼单'
const getQueueLocationTypeName = (type: string) => queueLocationTypeMap[type] || type || '其他'
const getSeatLocationTypeName = (type: string) => seatLocationTypeMap[type] || type || '其他'

const previewImage = (current: string, images: string | string[]) => {
    const urls = typeof images === 'string' ? images.split(',').filter((i: string) => i) : images
    uni.previewImage({
        current: img(current),
        urls: urls.map((u: string) => img(u))
    })
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

const statusDescMap: Record<number, string> = {
    0: '请在30分钟内完成支付',
    10: '正在等待接单员接单',
    20: '接单员已接单，正在前往取货点',
    30: '接单员正在取货',
    40: '接单员正在配送中',
    50: '订单已完成',
    90: '订单已取消',
    91: '订单已退款'
}

onLoad((options: any) => {
    if (options.id) {
        orderId.value = parseInt(options.id)
    }
})

onShow(() => {
    if (orderId.value) {
        loadOrderDetail()
    }
})

const loadOrderDetail = async () => {
    try {
        const res: any = await getOrderDetail(orderId.value)
        if (res.code === 1 && res.data) {
            order.value = res.data
            console.log('订单数据:', res.data)
            if (res.data.ext) {
                try {
                    extData.value = typeof res.data.ext === 'string' ? JSON.parse(res.data.ext) : res.data.ext
                    console.log('扩展数据:', extData.value)
                } catch (e) {
                    extData.value = {}
                }
            }
        }
    } catch (e) {
        console.error('加载订单详情失败:', e)
    }
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

const loadRunnerLocation = async () => {
    try {
        const res: any = await getRunnerLocation(order.value.id)
        if (res.code === 1) {
            runnerInfo.value = res.data
            
            if (res.data.lng && res.data.lat) {
                mapCenter.value = {
                    lat: parseFloat(res.data.lat),
                    lng: parseFloat(res.data.lng)
                }
                
                markers.value = [
                    {
                        id: 1,
                        latitude: parseFloat(res.data.lat),
                        longitude: parseFloat(res.data.lng),
                        iconPath: '/static/addon/sd_xiaoyuan/images/runner-marker.png',
                        width: 40,
                        height: 40,
                        callout: {
                            content: res.data.real_name,
                            display: 'ALWAYS',
                            padding: 10,
                            borderRadius: 5
                        }
                    }
                ]
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const getStatusText = (status: number) => {
    return statusMap[status] || '未知'
}

const getStatusDesc = (status: number) => {
    return statusDescMap[status] || ''
}

const getTaskTypeName = (type: string) => {
    return taskTypeMap[type] || type
}

const formatRemark = (remark: string) => {
    if (!remark) return ''
    try {
        const ext = JSON.parse(remark)
        let formatted = ''
        if (ext.goods_name) formatted += `商品：${ext.goods_name}\n`
        if (ext.expect_time) formatted += `期望时间：${ext.expect_time}\n`
        if (ext.quick_tags && ext.quick_tags.length > 0) {
            formatted += `标签：${ext.quick_tags.join('、')}\n`
        }
        return formatted.trim()
    } catch (e) {
        return remark // 如果不是JSON格式，直接返回原内容
    }
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const callRunner = () => {
    if (order.value.runner_mobile) {
        uni.makePhoneCall({
            phoneNumber: order.value.runner_mobile
        })
    }
}

const viewRunnerLocation = () => {
    showMap.value = !showMap.value
}

const cancelOrder = () => {
    uni.showModal({
        title: '提示',
        content: '确定要取消订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const result: any = await cancelOrderApi({ id: order.value.id })
                    if (result.code === 1) {
                        uni.showToast({ title: '取消成功', icon: 'success' })
                        loadOrderDetail()
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

const payOrder = () => {
    // 使用框架支付组件
    payRef.value?.open('sd_xiaoyuan_order', order.value.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + order.value.id)
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    loadOrderDetail()
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}

const goToEvaluate = () => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/order/evaluate?id=${order.value.id}`
    })
}

const submitTip = async () => {
    const amount = tipAmount.value > 0 ? tipAmount.value : parseFloat(customTipAmount.value)
    if (!amount || amount <= 0) {
        uni.showToast({ title: '请选择或输入打赏金额', icon: 'none' })
        return
    }
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await tipOrder({ id: order.value.id, amount })
        uni.hideLoading()
        if (res.code === 1 && res.data?.trade_id) {
            showTipPopup.value = false
            // 调用收银台支付
            payRef.value?.open('sd_xiaoyuan_tip', res.data.trade_id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + order.value.id)
        } else {
            uni.showToast({ title: res.msg || '打赏失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.order-detail {
    min-height: 100vh;
    background: #f7f7f7;
    padding-bottom: 150rpx;
}

.status-section {
    padding: 60rpx 30rpx;
    color: #fff;
    
    &.status-bg-0 { background: linear-gradient(135deg, #ff9500, #ff6b00); }
    
    &.status-bg-10, &.status-bg-20, &.status-bg-30, &.status-bg-40 {
        background: linear-gradient(135deg, #c0fe95, #88f78d);
        color: #333;
        
        .status-desc { opacity: 0.8; }
    }
    
    &.status-bg-50 { background: linear-gradient(135deg, #999, #666); }
    &.status-bg-90, &.status-bg-91 { background: linear-gradient(135deg, #999, #666); }
    
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

.runner-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.runner-info {
    display: flex;
    align-items: center;
    margin-bottom: 20rpx;
    
    .runner-avatar {
        width: 100rpx;
        height: 100rpx;
        border-radius: 50%;
        margin-right: 20rpx;
    }
    
    .runner-detail {
        .runner-name {
            font-size: 28rpx;
            font-weight: bold;
            color: #333;
        }
        
        .runner-score {
            display: flex;
            align-items: center;
            margin-top: 8rpx;
            color: #ff9500;
            font-size: 26rpx;
        }
    }
}

.runner-actions {
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
            font-size: 26rpx;
            color: #666;
        }
    }
}

.map-section {
    margin: 20rpx;
    border-radius: 16rpx;
    overflow: hidden;
    
    .runner-map {
        width: 100%;
        height: 400rpx;
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
    
    .addr-tag {
        width: 40rpx;
        height: 40rpx;
        border-radius: 50%;
        font-size: 22rpx;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16rpx;
        flex-shrink: 0;
        margin-top: 4rpx;
        
        &.pickup { background: #52c41a; }
        &.receive { background: #ff4d4f; }
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
}

.address-line {
    width: 2rpx;
    height: 40rpx;
    background: #e0e0e0;
    margin-left: 24rpx;
    margin: 16rpx 0 16rpx 24rpx;
}

.info-section, .fee-section {
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

.info-item, .fee-item {
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
        
        &.discount {
            color: #52c41a;
        }
        
        &.hint {
            color: #ff9500;
            font-size: 26rpx;
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
    
    .package-detail-list {
        width: 100%;
        
        .package-detail-item {
            display: flex;
            align-items: center;
            padding: 12rpx 20rpx;
            background: #f5f5f5;
            border-radius: 8rpx;
            margin-bottom: 12rpx;
            
            .pkg-name {
                flex: 1;
                font-size: 28rpx;
                color: #333;
            }
            
            .pkg-count {
                font-size: 26rpx;
                color: #666;
                margin-right: 20rpx;
            }
            
            .pkg-price {
                font-size: 28rpx;
                color: #ff6b00;
                font-weight: bold;
            }
        }
    }
    
    .image-list {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;
        margin-top: 12rpx;
        
        .order-image {
            width: 160rpx;
            height: 160rpx;
            border-radius: 8rpx;
            object-fit: cover;
        }
    }
}

.fee-total {
    display: flex;
    justify-content: space-between;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
    margin-top: 10rpx;
    
    .label {
        font-size: 30rpx;
        font-weight: bold;
    }
    
    .value.total {
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
    
    .btn-cancel {
        background: #fff;
        color: #666;
        border: 1rpx solid #ddd;
    }
    
    .btn-pay, .btn-evaluate {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
        border: none;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    }
    
    .btn-tip {
        background: #fff7ed;
        color: #f97316;
        border: 1rpx solid #fed7aa;
    }
}

.tip-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 998;
}

.tip-popup {
    position: fixed;
    left: 40rpx; right: 40rpx;
    top: 50%; transform: translateY(-50%);
    background: #fff;
    border-radius: 24rpx;
    padding: 40rpx;
    z-index: 999;
}

.tip-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    text-align: center;
    margin-bottom: 12rpx;
}

.tip-desc {
    font-size: 26rpx;
    color: #999;
    text-align: center;
    margin-bottom: 30rpx;
}

.tip-amounts {
    display: flex;
    gap: 16rpx;
    margin-bottom: 24rpx;
    
    .tip-amount-item {
        flex: 1;
        text-align: center;
        padding: 20rpx 0;
        background: #f8fafc;
        border-radius: 12rpx;
        font-size: 28rpx;
        font-weight: bold;
        color: #333;
        border: 2rpx solid transparent;
        
        &.active {
            border-color: #f97316;
            background: #fff7ed;
            color: #f97316;
        }
    }
}

.tip-custom {
    display: flex;
    align-items: center;
    gap: 16rpx;
    margin-bottom: 30rpx;
    
    text {
        font-size: 26rpx;
        color: #666;
        white-space: nowrap;
    }
    
    input {
        flex: 1;
        height: 72rpx;
        background: #f8fafc;
        border-radius: 12rpx;
        padding: 0 20rpx;
        font-size: 28rpx;
    }
}

.tip-btns {
    display: flex;
    gap: 20rpx;
    
    button {
        flex: 1;
        height: 72rpx;
        line-height: 72rpx;
        font-size: 28rpx;
        font-weight: bold;
        border-radius: 36rpx;
        border: none;
        &::after { border: none; }
    }
    
    .tip-btn-cancel {
        background: #f5f5f5;
        color: #666;
    }
    
    .tip-btn-confirm {
        background: linear-gradient(to top, #ff9500, #ffb347);
        color: #fff;
        box-shadow: 0 4rpx 12rpx rgba(255, 149, 0, 0.3);
    }
}
</style>
