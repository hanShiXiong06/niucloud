<template>
    <view class="min-h-screen relative" :style="themeColor()" v-if="config">
        <!-- 背景渐变光晕 -->
        <!-- 背景层 -->
        <view class="bg-layer"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #f8fafc 0%, #f0f4ff 30%, #e0e7ff 50%, #f0f4ff 70%, #f8fafc 100%);">
        </view>
        <!-- 顶部光晕 -->
        <view class="glow-layer-1"
            style="position: fixed; top: -150rpx; left: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>
        <!-- 底部光晕 -->
        <view class="glow-layer-2"
            style="position: fixed; bottom: -150rpx; right: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>

        <view class="relative p-4" style="position: relative; z-index: 10;">
            <!-- 提示卡片 -->
            <view class="mb-4 rounded-xl overflow-hidden"
                style="background: linear-gradient(135deg, rgba(239, 246, 255, 0.6) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(245, 247, 250, 0.8) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                <view class="px-4 py-3 flex items-center gap-2"
                    style="background: rgba(59, 130, 246, 0.08); border-radius: 12rpx; margin: 12rpx;">
                    <text class="text-base font-bold" style="color: #3b82f6;">!</text>
                    <text class="text-sm flex-1" style="color: #475569;">
                        充值即时到账；卡密兑换后生效；支持跨账户兑换。
                    </text>
                </view>
            </view>

            <!-- 套餐列表 -->
            <view v-for="(item, index) in listData" :key="item.id || index" class="mb-4">
                <view class="rounded-xl overflow-hidden"
                    style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                    <view class="p-4 relative">
                        <!-- 顶角状态徽标 -->
                        <view v-if="item.type == 'card'" class="px-3 py-1 rounded-full text-[24rpx] font-medium"
                            style="position:absolute;top:16rpx;right:16rpx;z-index:50;background: rgba(139, 92, 246, 0.15); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.3);">
                            卡密 × {{ item.num || 0 }}张 有效 {{ item.day == 0 ? '无限期' : item.day + '天' }}
                        </view>
                        <view v-else class="px-3 py-1 rounded-full text-[24rpx] font-medium"
                            style="position:absolute;top:16rpx;right:16rpx;z-index:50;background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3);">
                            充值
                        </view>

                        <!-- 左右布局 -->
                        <view class="flex gap-3 items-stretch mt-3">
                            <!-- 左侧图片 -->
                            <view class="w-[200rpx] h-[200rpx] rounded-lg overflow-hidden flex-shrink-0 relative"
                                style="border: 1px solid rgba(59, 130, 246, 0.1); box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.08);">
                                <view class="absolute inset-0 pointer-events-none rounded-lg"
                                    style="background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.15), transparent 60%);">
                                </view>
                                <image :src="img(item.image)" mode="aspectFill" lazy-load="true" loading="lazy"
                                    class="w-full h-full object-cover" />
                            </view>
                            <!-- 右侧信息 -->
                            <view class="flex-1 flex flex-col justify-between min-w-0">
                                <!-- 套餐名称 -->
                                <view class="">
                                    <text class="text-[32rpx] font-bold leading-tight line-clamp-1"
                                        style="color: #1e293b;">
                                        {{ item.name || '未命名套餐' }}
                                    </text>
                                </view>
                                <!-- 分割线 -->
                                <view class="my-2 h-[1rpx] rounded-full"
                                    style="background: linear-gradient(to right, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.05));">
                                </view>
                                <!-- 价格 -->
                                <view class="mb-1">
                                    <text class="text-lg font-bold" style="color: #16a34a;">
                                        <text class="text-xs" style="color: #16a34a; opacity: 0.8;">￥</text>
                                        <text class="text-[32rpx] ml-1">{{ item.price }}</text>
                                    </text>
                                </view>
                                <!-- 底部操作区 -->
                                <view class="flex items-center justify-between">
                                    <view>
                                        <text class="text-xs font-semibold px-2 py-1 rounded-full"
                                            style="background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.25);">
                                            {{ item.point }}{{ config.alias_name }}
                                        </text>
                                    </view>
                                    <view @tap.stop="onBtnTap(item)" @click.stop="onBtnTap(item)"
                                        @touchend.stop.prevent="onBtnTap(item)" @longpress.stop.prevent="onBtnTap(item)"
                                        hover-class="opacity-90" hover-start-time="0" hover-stay-time="50" role="button"
                                        class="px-4 py-1.5 rounded-full text-sm font-semibold text-white transition-all active:scale-95"
                                        style="background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4rpx 12rpx rgba(37, 99, 235, 0.35);">
                                        立即{{ item.type == 'card' ? '购买' : '充值' }}
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </view>

        <mescroll-empty v-if="listData.length === 0 && !loading" :option="{ tip: '还没有数据哟~~~' }"></mescroll-empty>

        <pay ref="payRef" @close="payLoading = false"></pay>

        <view v-show="fabOpen" class="fixed inset-0 z-[998]" @tap.stop="fabOpen = false" catchtouchmove="true"
            @touchmove.stop.prevent></view>

        <tabbar name="ai_image" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getPackageList, addOrder, getConfig } from '@/addon/ai_image/api/aiimage'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import { onLoad } from '@dcloudio/uni-app'
import { img } from '@/utils/common';
const payRef = ref<any>(null);
const payLoading = ref(false);

const fabOpen = ref(false)
const toggleFab = () => fabOpen.value = !fabOpen.value
const goTo = (url: string) => uni.navigateTo({ url })
const buyPackage = (item: any) => {
    // 判断是否在微信小程序iOS端
    if (iosPay.value) {
        uni.showToast({
            title: config.value?.ios_notice,
            icon: 'none',
            duration: 4000
        });
        return;
    }
    return addOrder({ package_id: item.id })
        .then((res: any) => {
            let url = ''
            if (item.type == 'card') {
                url = '/addon/ai_image/pages/card'
            }
            payRef.value?.open(res.data.trade_type, res.data.trade_id, url);
        })
        .catch((err: any) => {
            console.error('addOrder error', err)
            uni.showToast({ title: err?.message || '下单失败，请稍后重试', icon: 'none' })
            throw err
        })
}

const onBtnTap = (item: any) => {
    try { uni.vibrateShort && uni.vibrateShort(); } catch (e) { }
    Promise.resolve()
        .then(() => buyPackage(item))
        .catch(() => { })
}

const getPackageListFn = () => {
    const data = {
        page: 1,
        limit: 100
    };

    loading.value = true;
    getPackageList(data).then((res: any) => {
        loading.value = false;

        if (res.data && res.code === 1) {
            let newArr = res.data.data || [];
            listData.value = newArr;
            loading.value = false;
        } else {
            uni.showToast({
                title: res.message || '获取数据失败',
                icon: 'none'
            });
            loading.value = false;
        }
    })
}
const config = ref()
onLoad(() => {
    getConfig().then((res: any) => {
        config.value = res.data
    })
    getPackageListFn()
})
const listData = ref<any[]>([])
let loading = ref<boolean>(false);

const iosPay = computed(() => {
    // #ifdef MP-WEIXIN
    const systemInfo = uni.getSystemInfoSync();
    return systemInfo.platform == 'ios' && config.value?.ios_pay == 0;
    // #endif
    // #ifndef MP-WEIXIN
    return false;
    // #endif
});


</script>
<style lang="scss" scoped>
.card-box::before {
    pointer-events: none;
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: 14px;
    background: linear-gradient(90deg, rgba(34, 211, 238, 0.15), rgba(99, 102, 241, 0.12));
    filter: blur(6px);
    opacity: .4;
    z-index: 0;
}

.card-box {
    position: relative;
}

.card-box>view {
    position: relative;
    z-index: 1;
}

.shine-wrap::after {
    content: '';
    position: absolute;
    top: 0;
    left: -40%;
    width: 40%;
    height: 100%;
    background: linear-gradient(100deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .12), rgba(255, 255, 255, 0));
    transform: skewX(-15deg);
    animation: shine-move 4.5s ease-in-out infinite;
}

@keyframes shine-move {
    0% {
        left: -40%;
    }

    55% {
        left: 140%;
    }

    100% {
        left: 140%;
    }
}

/* 小屏适配：极窄屏时略缩小封面 */
@media (max-width: 360px) {
    .shine-wrap {
        width: 200rpx;
        height: 200rpx;
    }
}

.card-box {
    will-change: transform;
    backface-visibility: hidden;
}

.card-box:hover {
    transform: translateY(-2rpx) scale(1.01);
}

.card-box::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 12px;
    background: radial-gradient(140rpx 100rpx at 85% 5%, rgba(99, 102, 241, .10), transparent);
    z-index: 0;
    pointer-events: none;
}

.gradient-frame {
    position: relative
}

.gradient-frame::after {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 16px;
    background: conic-gradient(from 180deg, rgba(34, 211, 238, .16), rgba(99, 102, 241, .16), rgba(14, 165, 233, .16), rgba(34, 211, 238, .16));
    filter: blur(6px);
    opacity: .2;
    z-index: 0;
    pointer-events: none;
}

@keyframes frame-glow {
    0% {
        opacity: .35;
        transform: rotate(0deg)
    }

    50% {
        opacity: .55;
    }

    100% {
        opacity: .35;
        transform: rotate(360deg)
    }
}

.card-box button:hover .btn-arrow {
    transform: translateX(6rpx);
}

.card-box {
    backdrop-filter: blur(6px)
}

.card-box:hover {
    backdrop-filter: blur(10px)
}
</style>
