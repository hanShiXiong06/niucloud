<template>
    <view class="min-h-screen bg-gradient-to-b from-[#080e1f] via-[#0e1b33] to-[#1a2a4f] p-3" :style="themeColor()">
        <mescroll-body top="44" ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getOrderListFn">
            <view class="flex items-center gap-2 justify-center mb-2">
                <view v-for="(item, index) in statusList" :key="index" role="button" tabindex="0"
                    @click="onStatusClick(item)" @keyup.enter.space="onStatusClick(item)"
                    class="relative h-[56rpx] px-8 rounded-full inline-flex items-center justify-center text-[28rpx] font-medium transition active:scale-95 border"
                    :style="status === item.status
                        ? 'background-image: linear-gradient(90deg, #06b6d4, #0ea5e9, #7c3aed); color:#ffffff; border-color: transparent; box-shadow: 0 6rpx 18rpx rgba(34,211,238,0.35);'
                        : 'background-color: rgba(30,41,59,0.50); color:#e5e7eb; border-color: rgba(75,85,99,0.60);'">
                    <text>{{ item.name }}</text>

                </view>
            </view>
            <view v-for="(item, index) in listData" :key="index" class="mb-3">
                <!-- 外层渐变描边容器：统一视觉与层次 -->
                <view class="p-[2px] rounded-2xl bg-gradient-to-r from-cyan-500/25 via-sky-500/25 to-violet-600/25">
                    <!-- 内层玻璃拟态卡片 -->
                    <view
                        class="relative group bg-slate-900/75 rounded-xl border border-slate-600/60 ring-1 ring-white/10 shadow-[0_12rpx_34rpx_rgba(34,211,238,0.30),0_10rpx_28rpx_rgba(0,0,0,0.40)] backdrop-blur p-4 transition-all duration-200 ease-out hover:border-cyan-400/70 hover:shadow-[0_16rpx_40rpx_rgba(34,211,238,0.40),0_12rpx_32rpx_rgba(0,0,0,0.50)]">

                        <!-- 顶角类型徽标 -->
                        <view v-if="item.type == 'card'" style="position:absolute;top:8rpx;right:12rpx;z-index:50;"
                            class="px-2 py-0.5 rounded-full text-[22rpx] text-violet-100 bg-violet-500/20 ring-1 ring-violet-400/30">
                            卡密
                        </view>
                        <view v-else style="position:absolute;top:8rpx;right:12rpx;z-index:50;"
                            class="px-2 py-0.5 rounded-full text-[22rpx] text-emerald-100 bg-emerald-500/15 ring-1 ring-emerald-400/30">
                            充值
                        </view>

                        <view class="flex items-start justify-between gap-3">
                            <!-- 左侧信息块 -->
                            <view class="flex-1">
                                <view class="flex items-center gap-2">
                                    <text class="text-slate-100 font-semibold text-[28rpx] leading-tight line-clamp-1">
                                        {{ item.name || '未命名订单' }}
                                    </text>

                                </view>
                                <view
                                    class="text-[22rpx] mt-2 rounded-full bg-slate-800/60 text-slate-300 ring-1 ring-white/10">
                                    {{ item.create_time }}
                                </view>
                                <view
                                    class="mt-2 h-[2rpx] bg-gradient-to-r from-cyan-400/20 via-sky-400/20 to-violet-400/20 rounded-full">
                                </view>

                                <!-- 金额与状态 -->
                                <view class="mt-2 flex items-center justify-between">
                                    <view class="text-slate-300 text-[26rpx] font-medium">
                                        <text class="text-emerald-300 font-bold">
                                            <text class="text-[22rpx] opacity-90">￥</text>
                                            <text class="text-[34rpx] ml-1">{{ item.order_money }}</text>
                                        </text>
                                    </view>

                                    <view class="flex items-center gap-2">
                                        <view
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[22rpx] ring-1 ring-white/10"
                                            :style="item.status == 10 ? 'background-color: rgba(16,185,129,0.2); color:#A7F3D0' : 'background-color: rgba(55,65,81,0.4); color:#E5E7EB'">
                                            <text>{{ item.status == 10 ? '已支付' : '待支付' }}</text>
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <mescroll-empty v-if="listData.length === 0 && !loading" :option="{ tip: '还没有数据哟~~~' }"></mescroll-empty>
        </mescroll-body>
        <view v-show="fabOpen" class="fixed inset-0 z-[998]" @tap.stop="fabOpen = false" catchtouchmove="true"
            @touchmove.stop.prevent></view>
        <!-- 左侧可折叠悬浮按钮 -->
        <view class="fixed left-3 bottom-28 z-[999]" style="pointer-events:auto;">
            <view class="relative">
                <!-- 主按钮 -->
                <view @tap.stop="toggleFab"
                    class="w-[72rpx] h-[72rpx] rounded-full bg-cyan-500/80 text-white flex items-center justify-center shadow-lg ring-1 ring-white/10 active:scale-95 transition">
                    <text class="text-[32rpx]">≡</text>
                </view>
                <!-- 折叠面板 -->
                <view class="mt-2">
                    <view v-show="fabOpen" catchtouchmove="true" @touchmove.stop.prevent
                        class="transition-all duration-200">
                        <view class="flex flex-col gap-2">
                            <view @tap="goTo('/addon/ai_image/pages/create')"
                                class="px-3 py-2 rounded-full bg-slate-800/80 text-slate-100 text-[24rpx] ring-1 ring-white/10 shadow">
                                视频创建
                            </view>
                            <view @tap="goTo('/addon/ai_image/pages/package')"
                                class="px-3 py-2 rounded-full bg-slate-800/80 text-slate-100 text-[24rpx] ring-1 ring-white/10 shadow">
                                套餐购买
                            </view>
                            <view @tap="goTo('/addon/ai_image/pages/index')"
                                class="px-3 py-2 rounded-full bg-slate-800/80 text-slate-100 text-[24rpx] ring-1 ring-white/10 shadow">
                                返回首页
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </view>
        <tabbar name="ai_image" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getOrderList, getConfig } from '@/addon/ai_image/api/aiimage'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
import { img } from '@/utils/common';
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
const statusList = ref([
    { status: '', name: '全部' },
    { status: 1, name: '待支付' },
    { status: 10, name: '已支付' },
])
const status = ref('');
const statusCountMap = computed(() => {
    const map: Record<string, number> = { '': 0, '1': 0, '10': 0 }
    listData.value.forEach(v => {
        const s = String(v.status ?? '')
        if (s in map) map[s]++
        map['']++
    })
    return map
})
const getCount = (v: any) => statusCountMap.value[String(v ?? '')] || 0
const config = ref();
getConfig().then((res: any) => {
    config.value = res.data;
})
const listData = ref<any[]>([])
let loading = ref<boolean>(false);
const mescrollRef = ref<any>(null);

const fabOpen = ref(false)
const toggleFab = () => fabOpen.value = !fabOpen.value
const goTo = (url: string) => uni.navigateTo({ url })
const onStatusClick = (item: any) => (status.value = item.status, mescrollRef.value?.mescroll?.resetUpScroll())

const getOrderListFn = (mescroll: any) => {
    const data = {
        page: mescroll.num || 1,
        limit: mescroll.size || 10,
        status: status.value
    };

    loading.value = true;
    getOrderList(data).then((res: any) => {
        loading.value = false;

        if (res.data && res.code === 1) {
            let newArr = res.data.data || [];
            // 设置列表数据
            if (mescroll.num == 1) {
                listData.value = newArr; // 如果是第一页需手动制空列表
            } else {
                listData.value = listData.value.concat(newArr);
            }

            // 结束加载状态
            if (mescroll.endSuccess) {
                mescroll.endSuccess(newArr.length);
            }
        } else {
            // 处理接口返回错误
            if (mescroll.endErr) {
                mescroll.endErr();
            }
            uni.showToast({
                title: res.message || '获取数据失败',
                icon: 'none'
            });
        }
    }).catch((e: any) => {
        console.log('获取订单列表失败:', e);
        loading.value = false;

        if (mescroll.endErr) {
            mescroll.endErr(); // 请求失败, 结束加载
        }

        uni.showToast({
            title: '网络错误，请重试',
            icon: 'none'
        });
    })
}
</script>
<style lang="scss" scoped></style>
