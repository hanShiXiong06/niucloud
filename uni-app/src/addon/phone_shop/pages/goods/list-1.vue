<template>
    <view class="page-container">
        <!-- 自定义标题栏，替换u-navbar以避免重复问题 -->
        <view class="custom-header">
            <view class="header-content">
                <!-- 返回 -->
                <text>
                    <text class="nc-iconfont nc-icon-zuoV6xx" @click="goback()"></text>

                    <text class="header-title">今日上新</text></text>
                <view class="date-display">
                    <text class="date-icon nc-iconfont nc-icon-rili"></text>
                    <text class="date-text">{{ formatDate() }}</text>
                </view>
            </view>
        </view>

        <!-- 商品列表 -->
        <mescroll-body ref="mescrollRef" @init="mescrollInit" @up="upCallback" :down="{ use: false }" :up="{ 
                textLoading: '加载中...',
                textNoMore: '没有更多了',
                bgColor: 'transparent',
                textColor: '#999',
                toTop: {
                    src: '/static/mescroll-uni/mescroll-totop.png',
                    offset: 1000,
                    duration: 300
                }
            }">
            <view class="goods-list" v-if="goodsList.length">
                <view class="goods-item" v-for="(item, index) in goodsList" :key="item.goods_id"
                    @click="toDetail(item.goods_id)">
                    <!-- 序号和品牌标签 -->
                    <view class="item-index">{{ (index + 1).toString().padStart(2, '0') }}</view>
                    <view class="brand-tag" v-if="item.brand_name">
                        {{ item.brand_name }}
                    </view>

                    <!-- 商品内容 -->
                    <view class="goods-content">
                        <!-- 商品图片 -->
                        <view class="goods-image-wrapper">
                            <u--image :src="img(item.goods_cover_thumb_mid)" width="140rpx" height="140rpx" radius="8"
                                :fade="true" mode="aspectFill"></u--image>
                            <template #error>
                                <image class="default-image" :src="img('static/resource/images/diy/shop_default.jpg')"
                                    mode="aspectFill" style="width: 140rpx; height: 140rpx; border-radius: 8rpx;">
                                </image>
                            </template>
                        </view>

                        <!-- 商品信息 -->
                        <view class="goods-info">
                            <!-- 商品名称 -->
                            <view class="goods-name-row">
                                <text class="goods-name">{{ item.goods_name }}</text>
                            </view>

                            <!-- 商品副标题 -->
                            <view class="goods-subtitle-row" v-if="item.sub_title">
                                <text class="subtitle-text">{{ item.sub_title }}</text>
                                <text class="sku-text" v-if="item.goodsSku.sku_no">#{{ item.goodsSku.sku_no }}</text>
                            </view>

                            <!-- 价格和操作 -->
                            <view class="price-action-row">
                                <view class="price-area">
                                    <text class="price-symbol">￥</text>
                                    <text class="price-value">{{ goodsPrice(item).toFixed(2) }}</text>
                                    <image class="price-tag" v-if="priceType(item) === 'member_price'"
                                        :src="img('addon/phone_shop/VIP.png')" mode="heightFix" style="height: 28rpx;">
                                    </image>
                                    <image class="price-tag" v-if="priceType(item) === 'discount_price'"
                                        :src="img('addon/phone_shop/discount.png')" mode="heightFix"
                                        style="height: 28rpx;">
                                    </image>
                                </view>

                                <view class="action-btn" @click.stop="downloadGoods(item)">
                                    <text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
                                </view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <u-empty mode="data" text="暂无新品上架" v-else></u-empty>
        </mescroll-body>

        <up-loading-icon v-if='isLoading' :loading="isLoading" loadingText="加载中..." color="#3376cd" iconSize="24"
            loadingMode="circle"></up-loading-icon>

        <up-text align='center' type="info" v-else text="-- 到头了 --"></up-text>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { redirect, img } from '@/utils/common'
import { getGoodsPages, getGoodsDetail } from '@/addon/phone_shop/api/goods'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'

const { mescrollInit, getMescroll } = useMescroll(onPageScroll, onReachBottom)
const mescrollRef = ref(null)
const goodsList = ref<any[]>([])
const total = ref(0)
const loading = ref(true)
const isLoading = ref(false)

// 获取今日日期
const formatDate = () => {
    const date = new Date()
    return `${date.getMonth() + 1}月${date.getDate()}日  今日上新${total.value}件`
}

// 格式化时间
// const formatTime = (timestamp: number) => {
//     const date = new Date(timestamp * 1000)
//     const hours = date.getHours().toString().padStart(2, '0')
//     const minutes = date.getMinutes().toString().padStart(2, '0')
//     return `${hours}:${minutes}`
// }
const startTime = Math.floor(new Date().getTime() / 1000) - 24 * 60 * 60
const endTime = Math.floor(new Date().getTime() / 1000)

// 上拉加载
const upCallback = async (mescroll: any) => {
    if (mescroll.num === 1) {
        loading.value = true
    }
    isLoading.value = true

    try {
        const today = new Date()
        today.setHours(0, 0, 0, 0)

        const params = {
            page: mescroll.num,
            limit: mescroll.size,
            create_time: [startTime, endTime],
            order: 'create_time',
            sort: 'desc'
        }

        const res = await getGoodsPages(params)
        const { data, total: totalCount } = res.data

        if (mescroll.num === 1) goodsList.value = []
        goodsList.value = goodsList.value.concat(data)
        total.value = totalCount
        loading.value = false
        isLoading.value = false
        // 判断是否还有更多数据
        if (goodsList.value.length >= totalCount) {
            // 停止 加载的动画
            loading.value = false
            isLoading.value = false
            mescroll.endSuccess(data.length, false)

        } else {
            mescroll.endSuccess(data.length, true)
        }
    } catch (error) {
        console.error(error)
        loading.value = false
        isLoading.value = false
        mescroll.endErr()
    }
}


const goback = () => {
    if (getCurrentPages().length > 1) {
        uni.navigateBack({
            delta: 1
        });
    } else {
        redirect({
            url: '/addon/phone_shop/pages/index',
            mode: 'reLaunch'
        });
    }
}


// 下载单个商品
const downloadGoods = async (item: any) => {
    const res = await getGoodsDetail({ goods_id: item.goods_id })
    if (!res.data.goods) {
        uni.showToast({ title: '商品信息获取失败', icon: 'none' })
        return
    }
    const images = res.data.goods.goods_image.split(',')
    downloadImages(images, item)
}

// 下载图片并复制文案
const downloadImages = (images: string[], item: any, showToast: boolean = true) => {
    return new Promise((resolve) => {
        const tasks = images.map((url: string) => {
            return new Promise((resolve, reject) => {
                uni.downloadFile({
                    url,
                    success: (res) => {
                        if (res.statusCode === 200) {
                            uni.saveImageToPhotosAlbum({
                                filePath: res.tempFilePath,
                                success: resolve,
                                fail: reject
                            })
                        } else {
                            reject()
                        }
                    },
                    fail: reject
                })
            })
        })

        Promise.all(tasks).then(() => {
            const sku_no = item.goodsSku.sku_no ? '#' + item.goodsSku.sku_no + ' ' : ''
            const text = `${item.goods_name} ${item.sub_title} ${sku_no}${goodsPrice(item)}`
            uni.setClipboardData({
                data: text,
                success: () => {
                    if (showToast) {
                        uni.showToast({ title: '图片下载及文案复制成功', icon: 'none' })
                    }
                    resolve(true)
                }
            })
        }).catch(() => {
            if (showToast) {
                uni.showToast({ title: '下载失败', icon: 'none' })
            }
            resolve(false)
        })
    })
}

// 下载所有商品
const downloadAll = () => {
    // 实现批量下载逻辑
    uni.showToast({
        title: '开始批量下载...',
        icon: 'none'
    })
}

// 跳转商品详情
const toDetail = (goodsId: number) => {
    redirect({
        url: '/addon/phone_shop/pages/goods/detail',
        param: { goods_id: goodsId },
        mode: 'navigateTo'
    })
}

// 价格相关方法
const priceType = (data: any) => {
    if (data.is_discount && data.goodsSku.sale_price != data.goodsSku.price) {
        return 'discount_price'
    } else if (data.member_discount && data.goodsSku.member_price != data.goodsSku.price) {
        return 'member_price'
    }
    return ''
}

const goodsPrice = (data: any) => {
    if (data.is_discount && data.goodsSku.sale_price != data.goodsSku.price) {
        return parseFloat(data.goodsSku.sale_price || data.goodsSku.price)
    } else if (data.member_discount && data.goodsSku.member_price != data.goodsSku.price) {
        return parseFloat(data.goodsSku.member_price || data.goodsSku.price)
    }
    return parseFloat(data.goodsSku.price)
}

// 在页面加载时设置小程序导航栏标题
onMounted(() => {
    // 设置小程序原生导航栏标题
    uni.setNavigationBarTitle({
        title: '今日上新'
    })

    // 设置导航栏背景色
    uni.setNavigationBarColor({
        frontColor: '#000000',
        backgroundColor: '#f0f9ff'
    })
})
</script>

<style lang="scss" scoped>
.page-container {
    min-height: 100vh;
    background-color: #f8faff;
    padding-bottom: 30rpx;
    /* 移除顶部padding，使用自定义标题栏 */
    padding-top: 0;
}

/* 自定义标题栏样式 */
.custom-header {
    background-color: #f0f9ff;
    padding: 20rpx;
    /*如果是 小程序端 则需要 padding-top 及 padding-right 撑开 到 胶囊导航 */
    // #ifdef MP-WEIXIN
    padding-top: 100rpx;
    padding-right: 220rpx;
    // #endif
    border-bottom: 1px solid rgba(186, 230, 253, 0.5);
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-title {
    font-size: 34rpx;
    color: #0c4a6e;
    font-weight: bold;
}

/* 日期显示样式 */
.date-display {
    display: flex;
    align-items: center;
    background-color: rgba(59, 130, 246, 0.1);
    padding: 4rpx 12rpx;
    border-radius: 16rpx;
}

.date-icon {
    font-size: 22rpx;
    color: #0284c7;
    margin-right: 4rpx;
}

.date-text {
    font-size: 22rpx;
    color: #0c4a6e;
    font-weight: 500;
}

/* 商品列表样式 */
.goods-list {
    padding: 20rpx 20rpx 0;
}

.goods-item {
    position: relative;
    background-color: #ffffff;
    border-radius: 12rpx;
    padding: 16rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05);
}

/* 序号标签 */
.item-index {
    position: absolute;
    top: 0;
    left: 0;
    background-color: #4e82c7;
    color: #ffffff;
    font-size: 20rpx;
    font-weight: 600;
    padding: 4rpx 10rpx;
    border-radius: 12rpx 0 12rpx 0;
    z-index: 1;
}

/* 品牌标签 */
.brand-tag {
    position: absolute;
    top: 0;
    right: 0;
    background-color: #3376cd;
    color: #ffffff;
    font-size: 20rpx;
    font-weight: 600;
    padding: 4rpx 10rpx;
    border-radius: 0 12rpx 0 12rpx;
    z-index: 1;
}

/* 商品内容 */
.goods-content {
    display: flex;
    padding-top: 16rpx;
}

/* 图片样式 */
.goods-image-wrapper {
    width: 140rpx;
    height: 140rpx;
    margin-right: 16rpx;
    border-radius: 8rpx;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.05);
}

.default-image {
    width: 100%;
    height: 100%;
    border-radius: 8rpx;
}

/* 商品信息样式 */
.goods-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
}

.goods-name-row {
    margin-bottom: 6rpx;
}

.goods-name {
    font-size: 28rpx;
    font-weight: 600;
    color: #1e293b;
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.goods-subtitle-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12rpx;
    align-items: center;
}

.subtitle-text {
    font-size: 24rpx;
    color: #64748b;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sku-text {
    font-size: 22rpx;
    color: #3b82f6;
    flex-shrink: 0;
    margin-left: 8rpx;
}

.price-action-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.price-area {
    display: flex;
    align-items: baseline;
}

.price-symbol {
    font-size: 22rpx;
    color: #ef4444;
    font-weight: bold;
}

.price-value {
    font-size: 32rpx;
    font-weight: bold;
    color: #ef4444;
    margin-right: 6rpx;
}

.price-tag {
    margin-left: 6rpx;
}

.action-btn {
    width: 56rpx;
    height: 56rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #3b82f6;
    border-radius: 28rpx;
    color: #ffffff;
}

.action-btn .nc-iconfont {
    font-size: 26rpx;
}
</style>