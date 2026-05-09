<template>
    <view class="page">
        <!-- Banner区域 -->
        <view class="banner-section" v-if="bannerList.length > 0">
            <swiper class="banner-swiper" circular autoplay interval="3000" duration="500" @change="onSwiperChange">
                <swiper-item v-for="(item, index) in bannerList" :key="index">
                    <image :src="img(item.image)" mode="aspectFill" class="banner-image" />
                </swiper-item>
            </swiper>
            <view class="banner-indicator">
                <view v-for="(item, index) in bannerList" :key="index"
                    :class="['indicator-dot', currentBannerIndex === index ? 'active' : '']">
                </view>
            </view>
        </view>

        <!-- 快捷操作区 -->
        <view class="quick-actions">
            <view class="action-item floating-btn" @click="toAddOrder">
                <up-icon name="file-text" size="24" color="#ffffff"></up-icon>
                <text>立即下单</text>
            </view>
            <view class="action-item" @click="redirect({ url: '/addon/recycle/pages/order/list' })">
                <up-icon name="order" size="24" color="#007AFF"></up-icon>
                <text>我的订单</text>
            </view>
            <view class="action-item" @click="redirect({ url: '/addon/recycle/pages/payment/index' })">
                <up-icon name="rmb-circle" size="24" color="#007AFF"></up-icon>
                <text>收款方式</text>
            </view>
        </view>

        <!-- 分类列表区 -->
        <view class="category-section">
            <!-- 分类导航 -->
            <scroll-view class="category-nav" scroll-x :scroll-into-view="`nav-${currentCategoryIndex}`"
                scroll-with-animation>
                <view class="nav-items">
                    <view v-for="(item, index) in categoryList" :key="index" :id="`nav-${index}`" class="nav-item"
                        :class="{ active: currentCategoryIndex === index }" @click="switchCategory(index)"
                        v-show="item.is_show">
                        {{ item.category_name }}
                    </view>
                </view>
            </scroll-view>

            <!-- 分类内容 -->
            <swiper class="swiper-box" :current="currentCategoryIndex" @change="handleSwiperChange"
                :style="{ height: swiperHeight + 'px' }">
                <swiper-item v-for="(category, index) in categoryList" :key="index">
                    <scroll-view scroll-y class="swiper-item-scroll">
                        <view class="swiper-item-content" :id="'swiper-content-' + index">
                            <view class="category-content" v-if="category.child_list && category.child_list.length">
                                <up-grid :border="false" col="4">
                                    <up-grid-item v-for="(listItem, listIndex) in getFilteredChildList(category)"
                                        :key="listIndex" @click="handleClick(listItem.category_id)" class="grid-item">
                                        <view class="grid-item-content">
                                            <view class="brand-icon">
                                                <up-icon :customStyle="{ padding: '10rpx' }" :name="img(listItem.image)"
                                                    :size="40" />
                                            </view>
                                            <text class="brand-name">{{ listItem.category_name }}</text>
                                        </view>
                                    </up-grid-item>
                                </up-grid>
                            </view>
                            <view class="empty-category" v-else>
                                <up-icon name="info" size="24" color="#999"></up-icon>
                                <text>无报价信息</text>
                            </view>
                        </view>
                    </scroll-view>
                </swiper-item>
            </swiper>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { getCategoryTree } from '@/addon/recycle/api/recycle';
import { getPaymentList } from '@/addon/recycle/api/payment';

import { img, redirect } from '@/utils/common';
import useMemberStore from "@/stores/member";
import { useLogin } from "@/hooks/useLogin";
import { onShow } from "@dcloudio/uni-app";
import { useSubscribeMessage } from "@/hooks/useSubscribeMessage";

const memberStore = useMemberStore();
const userInfo = computed(() => memberStore.info);

// Banner数据
const bannerList = ref([]);

// 轮播当前索引
const currentBannerIndex = ref(0);

// 轮播切换事件处理
const onSwiperChange = (e) => {
    currentBannerIndex.value = e.detail.current;
};

// 获取Banner列表
// const _getBannerList = async () => {
//     const res = await getBannerList();
//     if (res.code === 1) {
//         bannerList.value = res.data;
//     }
// };

let categoryList = ref([]);
let flattenCategoryList = ref([]);
const currentCategoryIndex = ref(0)
const swiperHeight = ref(0); // 初始高度设为0

// 更新当前显示内容的高度
const updateHeight = () => {
    // 使用 nextTick 确保内容已经渲染
    nextTick(() => {
        // 在小程序环境中，需要给一个短暂的延时确保内容完全渲染
        setTimeout(() => {
            const query = uni.createSelectorQuery();
            query.select(`#swiper-content-${currentCategoryIndex.value}`).boundingClientRect(data => {
                if (data && data.height) {
                    swiperHeight.value = Math.max(data.height, 100);
                }
            }).exec();
        }, 100);
    });
};

// 监听分类切换
const handleSwiperChange = (e: any) => {
    currentCategoryIndex.value = e.detail.current;
    updateHeight();
};
// switchCategory 
const switchCategory = (index: number) => {
    currentCategoryIndex.value = index;
    updateHeight();
};

// 监听数据变化
watch(() => categoryList.value, () => {
    updateHeight();
}, { deep: true });

// 监听初始数据加载
watch(() => categoryList.value.length, (newVal) => {
    if (newVal > 0) {
        updateHeight();
    }
});

const paymentList = ref([]);
// 获取收款方式列表
const _getPaymentList = async () => {
    const res = await getPaymentList();
    if (res.code === 1) {
        //如果返回的数据是长度 = 0  则跳转 payment/index
        if (res.data.length === 0) {
            uni.navigateTo({ url: '/addon/recycle/pages/payment/index' });
        }
    }
};

onMounted(async () => {
    await _getPaymentList();

    if (!userInfo.value) {
        useLogin().setLoginBack({ url: "/addon/recycle/pages/index" });
    }

    // 使用 Promise.all 同时获取分类和会员数据
    Promise.all([
        getCategoryTree(),
        // _getBannerList()
    ]).then(async ([categoryRes]) => {
        if (categoryRes.code === 1 && categoryRes.data) {
            categoryList.value = categoryRes.data;
            flattenCategoryList.value = flattenArray(categoryRes.data);

            // 在数据加载完成后，给一个合适的延时再计算高度
            setTimeout(() => {
                updateHeight();
            }, 300);
        }
    }).catch(error => {
        console.error('数据获取失败:', error);
    });
});

// 添加页面显示的生命周期钩子
onShow(async () => {
    await _getPaymentList();
   
    // 页面显示时重新计算高度
    if (categoryList.value.length > 0) {
        setTimeout(() => {
            updateHeight();
        }, 300);
    }
});

const getFilteredChildList = (item) => item.child_list.filter(listItem => listItem.is_show);

const handleClick = (id: number) => {
    const category = flattenCategoryList.value.find(item => item.category_id === id);
    if (!category) return;
    
    // 直接预览图片
    if (category.images) {
        previewImages(category.images);
    }
};

const previewImages = (images) => {
    if (!images.startsWith('http')) {
        images = getImgUrl() + images;
    }
    uni.previewImage({
        indicator: "number",
        loop: true,
        urls: Array.isArray(images) ? images : [images]
    });
};

const getImgUrl = () => {
    return import.meta.env.VITE_IMG_DOMAIN || '';
}

const toAddOrder = () => {
    uni.navigateTo({ url: '/addon/recycle/pages/order/order' });
};

function flattenArray(data) {
    return data.reduce((acc, item) => {
        if (item.images) acc.push(item);
        if (item.child_list && item.child_list.length) {
            acc = acc.concat(flattenArray(item.child_list));
        }
        return acc;
    }, []);
}

// 在接受报价时请求订阅通知
const handleAccept = async () => {
  // 请求订阅相关消息通知
  await useSubscribeMessage().request('recycle_order_pay');
  
  // ... 原有接受报价逻辑 ...
}
</script>

<style lang="scss" scoped>
// 全局变量
$primary-color: #007AFF;
$text-primary: #333;
$text-secondary: #666;
$text-light: #999;
$border-color: rgba(60, 60, 67, 0.1);
$card-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.06);

.page {
    min-height: 100vh;
    background-color: #f5f5f5;
    padding-bottom: 100rpx;
}

// Banner样式
.banner-section {
    position: relative;
    height: 300rpx;

    .banner-swiper {
        width: 100%;
        height: 100%;
    }

    .banner-image {
        width: 100%;
        height: 100%;
    }

    .banner-indicator {
        position: absolute;
        bottom: 20rpx;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10rpx;
        z-index: 1;

        .indicator-dot {
            width: 12rpx;
            height: 12rpx;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;

            &.active {
                background: #fff;
                width: 24rpx;
                border-radius: 6rpx;
            }
        }
    }
}

// 搜索区域样式
.search-section {
    padding: 0 20rpx;
    background: transparent;
    margin-bottom: 10rpx;

    .search-box {
        display: flex;
        align-items: center;
        background: rgba(142, 142, 147, 0.12);
        padding: 12rpx 20rpx;
        border-radius: 10rpx;

        .search-input {
            flex: 1;
            margin-left: 20rpx;
            font-size: 28rpx;
            color: $text-primary;

            &::placeholder {
                color: $text-light;
            }
        }
    }
}

// 快捷操作区样式
.quick-actions {
    margin: 20rpx;
    background: #fff;
    border-radius: 16rpx;
    padding: 10rpx;
    box-shadow: $card-shadow;
    display: flex;
    justify-content: space-around;

    .action-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12rpx;
        padding: 16rpx;
        border-radius: 12rpx;
        transition: all 0.3s;

        &:active {
            transform: scale(0.96);
            background: rgba(0, 0, 0, 0.02);
        }

        .up-icon {
            background: rgba($primary-color, 0.1);
            padding: 16rpx;
            border-radius: 12rpx;
        }

        text {
            font-size: 24rpx;
            color: $text-primary;
            font-weight: normal;
        }
    }
}

// 分类列表样式
.category-section {
    margin: 20rpx;
    background: #fff;
    border-radius: 16rpx;
    overflow: hidden;
    box-shadow: $card-shadow;
    margin-bottom: 120rpx;

    .category-nav {
        background: rgba($primary-color, 0.03);
        border-bottom: 0.5rpx solid $border-color;
        padding: 0 12rpx;
        position: sticky;
        top: 0;
        z-index: 100;
        box-sizing: border-box;

        .nav-items {
            display: flex;
            white-space: nowrap;
            padding: 8rpx 0;
        }

        .nav-item {
            padding: 20rpx 24rpx;
            color: $text-secondary;
            font-size: 28rpx;
            position: relative;
            transition: all 0.3s ease;

            &.active {
                color: $primary-color;
                font-weight: 500;

                &::after {
                    content: '';
                    position: absolute;
                    bottom: -8rpx;
                    left: 24rpx;
                    right: 24rpx;
                    height: 2rpx;
                    background: $primary-color;
                    transition: all 0.3s ease;
                }
            }
        }
    }

    .swiper-box {
        width: 100%;
    }

    .swiper-item-scroll {
        height: 100%;
    }

    .swiper-item-content {
        width: 100%;
    }

    .category-item {
        background: #fff;
        border-radius: 16rpx;
        margin-bottom: 20rpx;
        overflow: hidden;
        box-shadow: $card-shadow;

        .category-header {
            padding: 24rpx;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;

            .category-title {
                font-size: 32rpx;
                font-weight: 600;
                color: $text-primary;
            }

            .category-count {
                font-size: 24rpx;
                color: $text-light;
                background: rgba(0, 0, 0, 0.05);
                padding: 4rpx 12rpx;
                border-radius: 20rpx;
            }
        }

        .category-content {
            padding: 24rpx;
        }
    }
}

// 网格项样式
.grid-item-content {
    position: relative;
    padding: 20rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12rpx;
    transition: all 0.3s;

    &:active {
        transform: scale(0.96);
    }
}

// 品牌图标样式
.brand-icon {
    width: 88rpx;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border-radius: 16rpx;
    transition: all 0.3s;
    position: relative;
}

// 品牌名称样式
.brand-name {
    font-size: 23rpx;
    color: $text-primary;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    padding: 0 10rpx;
    box-sizing: border-box;
    font-weight: normal;
}

// 悬浮按钮样式
.floating-btn {
    position: fixed;
    bottom: 40rpx;
    left: 40rpx;
    right: 40rpx;
    background: linear-gradient(135deg, $primary-color, #0056b3);
    padding: 24rpx;
    border-radius: 100rpx;
    box-shadow: 0 6rpx 20rpx rgba($primary-color, 0.3);
    z-index: 999;
    animation: breathing 2s ease-in-out infinite;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12rpx;

    .up-icon {
        margin: 0;
    }

    text {
        color: #ffffff !important;
        font-size: 32rpx;
        font-weight: 600;
    }
}

// 动画
@keyframes breathing {
    0% {
        transform: scale(1);
        box-shadow: 0 6rpx 20rpx rgba($primary-color, 0.3);
    }

    50% {
        transform: scale(1.02);
        box-shadow: 0 8rpx 30rpx rgba($primary-color, 0.5);
    }

    100% {
        transform: scale(1);
        box-shadow: 0 6rpx 20rpx rgba($primary-color, 0.3);
    }
}
</style>