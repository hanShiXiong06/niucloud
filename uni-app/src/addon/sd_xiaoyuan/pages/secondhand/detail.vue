<template>
    <view class="secondhand-detail">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 图片轮播 -->
        <swiper class="goods-swiper" :indicator-dots="true" :autoplay="false">
            <swiper-item v-for="(item, index) in images" :key="index">
                <image :src="img(item)" mode="aspectFill" @click="previewImage(index)"></image>
            </swiper-item>
        </swiper>

        <!-- 价格信息 -->
        <view class="price-section">
            <view class="price-row">
                <text class="price">¥{{ goods.price }}</text>
                <text class="original-price" v-if="goods.original_price">原价 ¥{{ goods.original_price }}</text>
            </view>
            <view class="status-tag" :class="'status-' + goods.status">{{ getStatusText(goods.status) }}</view>
        </view>

        <!-- 商品信息 -->
        <view class="info-section">
            <text class="title">{{ goods.title }}</text>
            <text class="desc">{{ goods.content }}</text>
            <view class="meta-list">
                <view class="meta-item">
                    <text class="label">分类</text>
                    <text class="value">{{ goods.category_name || getCategoryName(goods.category_id) }}</text>
                </view>
                <view class="meta-item">
                    <text class="label">成色</text>
                    <text class="value">{{ goods.condition || '九成新' }}</text>
                </view>
                <view class="meta-item">
                    <text class="label">交易方式</text>
                    <text class="value">{{ getTradeMethodName(goods.trade_method) }}</text>
                </view>
                <view class="meta-item" v-if="goods.trade_address">
                    <text class="label">交易地点</text>
                    <text class="value">{{ goods.trade_address }}</text>
                </view>
            </view>
        </view>

        <!-- 卖家信息 -->
        <view class="seller-section">
            <view class="seller-info">
                <image class="avatar" :src="img(goods.member_avatar || '/static/resource/images/default_headimg.png')" mode="aspectFill"></image>
                <view class="seller-detail">
                    <text class="nickname">{{ goods.member_nickname || '用户' }}</text>
                    <text class="time">发布于 {{ (goods.create_time) }}</text>
                </view>
            </view>
        </view>

        <!-- 底部操作 -->
        <view class="action-bar" v-if="isOwner">
            <view class="action-left">
                <button class="btn-edit" @click="editGoods">编辑</button>
            </view>
            <view class="action-right">
                <button class="btn-contact" v-if="goods.status === 1" @click="offGoods">下架</button>
                <button class="btn-want" v-if="goods.status === 1" @click="soldGoods">标记已售出</button>
            </view>
        </view>
        <view class="action-bar" v-else>
            <view class="action-left">
                <view class="action-item" @click="collectGoods">
                    <u-icon :name="goods.is_collected ? 'heart-fill' : 'heart'" size="22" :color="goods.is_collected ? '#ff4d4f' : '#666'"></u-icon>
                    <text>收藏</text>
                </view>
                <view class="action-item" @click="shareGoods">
                    <u-icon name="share" size="22" color="#666"></u-icon>
                    <text>分享</text>
                </view>
            </view>
            <view class="action-right">
                <button class="btn-contact" @click="callSeller">
                    <u-icon name="phone" size="18" color="#fff"></u-icon>
                    联系卖家
                </button>
                <button class="btn-want" @click="wantGoods">我想要</button>
            </view>
        </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getSecondhandDetail, wantSecondhand, offSecondhand, soldSecondhand, getSecondhandCategoryList } from '../../api/xiaoyuan'
import { img } from '@/utils/common'
import useMemberStore from '@/stores/member'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_secondhand')
const memberStore = useMemberStore()
const isOwner = computed(() => goods.value.member_id && memberStore.info?.member_id && goods.value.member_id == memberStore.info.member_id)

const goods = ref<any>({})
const categoryList = ref<any[]>([])

const categoryMap: Record<string, string> = {
    'DIGITAL': '数码电子',
    'BOOK': '图书教材',
    'CLOTHES': '服饰鞋包',
    'DAILY': '生活用品',
    'SPORT': '运动户外',
    'OTHER': '其他'
}

const tradeMethodMap: Record<string, string> = {
    'FACE': '当面交易',
    'EXPRESS': '快递邮寄',
    'BOTH': '均可'
}

const images = computed(() => {
    if (!goods.value.images) return []
    if (typeof goods.value.images === 'string') {
        try {
            const parsed = JSON.parse(goods.value.images)
            if (Array.isArray(parsed)) return parsed
            return goods.value.images.split(',').filter((s: string) => s)
        } catch {
            return goods.value.images.split(',').filter((s: string) => s)
        }
    }
    return goods.value.images
})

const goodsId = ref(0)

onLoad((options: any) => {
    loadConfig()
    goodsId.value = parseInt(options?.id || '0')
    loadCategories()
    if (goodsId.value) loadDetail()
})

const loadCategories = async () => {
    const res: any = await getSecondhandCategoryList()
    if (res.code === 1) {
        categoryList.value = res.data || []
    }
}

const loadDetail = async () => {
    if (!goodsId.value) return
    
    try {
        const res: any = await getSecondhandDetail(goodsId.value)
        if (res.code === 1) {
            goods.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const getStatusText = (status: number) => {
    const map: Record<number, string> = {
        1: '在售',
        2: '已下架',
        3: '已售出'
    }
    return map[status] || '未知'
}

const getCategoryName = (categoryId: any) => {
    if (!categoryId) return '其他'
    const cat = categoryList.value.find((c: any) => c.id == categoryId)
    if (cat) return cat.name
    return categoryMap[categoryId] || '其他'
}

const getTradeMethodName = (method: string) => {
    return tradeMethodMap[method] || '当面交易'
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const previewImage = (index: any) => {
    uni.previewImage({
        urls: images.value.map((u: string) => img(u)),
        current: Number(index)
    })
}

const collectGoods = () => {
    uni.showToast({ title: '收藏成功', icon: 'success' })
}

const shareGoods = () => {
    // #ifdef MP-WEIXIN
    // 小程序使用转发功能
    uni.showToast({ title: '请点击右上角“...”进行分享', icon: 'none' })
    // #endif
    // #ifdef H5
    uni.showToast({ title: '请点击右上角进行分享', icon: 'none' })
    // #endif
}

const contactSeller = () => {
    if (goods.value.contact_mobile) {
        uni.makePhoneCall({
            phoneNumber: goods.value.contact_mobile
        })
    } else {
        uni.showToast({ title: '卖家未留联系方式', icon: 'none' })
    }
}

const callSeller = () => {
    if (goods.value.contact_mobile) {
        uni.makePhoneCall({
            phoneNumber: goods.value.contact_mobile,
            fail: () => {
                uni.showToast({ title: '拨打电话失败', icon: 'none' })
            }
        })
    } else {
        uni.showToast({ title: '卖家未留联系电话', icon: 'none' })
    }
}

const editGoods = () => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/secondhand/publish?id=${goodsId.value}` })
}

const offGoods = () => {
    uni.showModal({
        title: '提示',
        content: '确定下架该商品？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await offSecondhand({ id: goodsId.value })
                if (result.code === 1) {
                    uni.showToast({ title: '下架成功', icon: 'success' })
                    loadDetail()
                }
            }
        }
    })
}

const soldGoods = () => {
    uni.showModal({
        title: '提示',
        content: '确定标记为已售出？',
        success: async (res) => {
            if (res.confirm) {
                const result: any = await soldSecondhand({ id: goodsId.value })
                if (result.code === 1) {
                    uni.showToast({ title: '操作成功', icon: 'success' })
                    loadDetail()
                }
            }
        }
    })
}

const wantGoods = async () => {
    try {
        const res: any = await wantSecondhand({ goods_id: goods.value.id })
        if (res.code === 1) {
            uni.showToast({ title: '已通知卖家', icon: 'success' })
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.secondhand-detail {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 150rpx;
}

.goods-swiper {
    height: 600rpx;
    
    image {
        width: 100%;
        height: 100%;
    }
}

.price-section {
    background: #fff;
    padding: 24rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    .price-row {
        .price {
            font-size: 48rpx;
            color: #ff6b00;
            font-weight: bold;
        }
        
        .original-price {
            font-size: 26rpx;
            color: #999;
            text-decoration: line-through;
            margin-left: 16rpx;
        }
    }
    
    .status-tag {
        padding: 8rpx 20rpx;
        border-radius: 20rpx;
        font-size: 24rpx;
        
        &.status-1 {
            background: #e6fffb;
            color: #13c2c2;
        }
        
        &.status-2 {
            background: #f5f5f5;
            color: #999;
        }
        
        &.status-3 {
            background: #fff7e6;
            color: #fa8c16;
        }
    }
}

.info-section {
    background: #fff;
    margin-top: 20rpx;
    padding: 24rpx;
    
    .title {
        display: block;
        font-size: 34rpx;
        color: #333;
        font-weight: bold;
        line-height: 1.5;
    }
    
    .desc {
        display: block;
        font-size: 28rpx;
        color: #666;
        line-height: 1.8;
        margin-top: 20rpx;
    }
    
    .meta-list {
        margin-top: 30rpx;
        padding-top: 30rpx;
        border-top: 1rpx solid #f0f0f0;
    }
    
    .meta-item {
        display: flex;
        padding: 12rpx 0;
        
        .label {
            width: 160rpx;
            font-size: 28rpx;
            color: #999;
        }
        
        .value {
            flex: 1;
            font-size: 28rpx;
            color: #333;
        }
    }
}

.seller-section {
    background: #fff;
    margin-top: 20rpx;
    padding: 24rpx;
}

.seller-info {
    display: flex;
    align-items: center;
    
    .avatar {
        width: 80rpx;
        height: 80rpx;
        border-radius: 50%;
        margin-right: 20rpx;
    }
    
    .seller-detail {
        .nickname {
            display: block;
            font-size: 30rpx;
            color: #333;
            font-weight: bold;
        }
        
        .time {
            font-size: 24rpx;
            color: #999;
            margin-top: 8rpx;
        }
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
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
}

.action-left {
    display: flex;
    gap: 40rpx;
    
    .action-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        
        .iconfont {
            font-size: 40rpx;
            color: #666;
            
            &.icon-heart-fill {
                color: #ff6b6b;
            }
        }
        
        text {
            font-size: 22rpx;
            color: #666;
            margin-top: 4rpx;
        }
    }
}

.action-right {
    display: flex;
    gap: 20rpx;
    
    button {
        height: 80rpx;
        line-height: 80rpx;
        padding: 0 40rpx;
        border-radius: 16rpx;
        font-size: 28rpx;
        border: none;
    }
    
    .btn-edit {
        background: #fff;
        color: #00c853;
        border: 2rpx solid #00c853;
    }

    .btn-contact {
        background: #fff;
        color: #333;
        border: 2rpx solid #333;
        
        .iconfont {
            margin-right: 8rpx;
        }
    }
    
    .btn-want {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000000;
    }
}
</style>
