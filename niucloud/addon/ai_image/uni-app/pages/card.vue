<template>
    <view class="min-h-screen relative" :style="themeColor()">
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
            <mescroll-body top="44" ref="mescrollRef" @init="mescrollInit" @down="downCallback" @up="getLogListFn">
                <!-- 订单列表 -->
                <view v-for="(item, index) in listData" :key="index" class="mb-3">
                    <view class="rounded-xl overflow-hidden"
                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                        <view class="p-3">
                            <!-- 卡号区域 -->
                            <view class="mb-2.5">
                                <view @click="copyText(item.card_num)"
                                    class="flex items-center justify-between px-3 py-2 rounded-xl cursor-pointer active:opacity-80 transition-all"
                                    style="background: rgba(255, 255, 255, 0.9); border: 2px solid rgba(59, 130, 246, 0.2); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                    <view class="flex items-center gap-2 flex-1 min-w-0">
                                        <view class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                            style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.08));">
                                            <u-icon name="order" size="18" color="#3b82f6" />
                                        </view>
                                        <view class="flex-1 min-w-0">
                                            <text
                                                class="font-semibold font-mono tracking-wider text-[26rpx] block truncate"
                                                :style="`color:${Number(item.num) >= 0 ? '#16a34a' : '#ef4444'}`">
                                                {{ item.card_num }}
                                            </text>
                                        </view>
                                    </view>
                                    <view class="ml-2 px-2.5 py-1 rounded-lg flex-shrink-0"
                                        style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <text class="text-[22rpx] font-semibold" style="color: #3b82f6;">复制</text>
                                    </view>
                                </view>
                            </view>

                            <!-- 信息区域 -->
                            <view class="mb-2.5">
                                <view class="flex items-center flex-wrap gap-1.5 mb-2">
                                    <view v-if="item.expire_time && item.expire_time != 0"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[22rpx]"
                                        style="background: rgba(59, 130, 246, 0.1); color: #475569; border: 1px solid rgba(59, 130, 246, 0.2);">
                                        <text>到期：{{ item.expire_time }}</text>
                                    </view>
                                    <view v-if="item.expire_time == 0"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[22rpx]"
                                        style="background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3);">
                                        <text>永久有效</text>
                                    </view>
                                    <view v-if="config"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[22rpx] font-semibold"
                                        style="background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.25);">
                                        {{ item.point }}{{ config.alias_name }}
                                    </view>
                                </view>

                                <!-- 状态标签 -->
                                <view class="flex items-center gap-1.5 mb-2.5">
                                    <view
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[22rpx] font-medium"
                                        :style="item.is_use == 1 ? 'background-color: rgba(34, 197, 94, 0.15); color:#16a34a; border: 1px solid rgba(34, 197, 94, 0.3);' : 'background-color: rgba(148, 163, 184, 0.1); color:#64748b; border: 1px solid rgba(148, 163, 184, 0.2);'">
                                        <text>{{ item.is_use == 1 ? '已使用' : '未使用' }}</text>
                                    </view>
                                    <view
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[22rpx] font-medium"
                                        :style="item.is_export == 1 ? 'background-color: rgba(59, 130, 246, 0.15); color:#2563eb; border: 1px solid rgba(59, 130, 246, 0.3);' : 'background-color: rgba(148, 163, 184, 0.1); color:#64748b; border: 1px solid rgba(148, 163, 184, 0.2);'">
                                        <text>{{ item.is_export == 1 ? '已分配' : '未分配' }}</text>
                                    </view>
                                </view>
                            </view>

                            <!-- 操作按钮 -->
                            <view class="flex items-center gap-2 pt-2 border-t"
                                style="border-color: rgba(59, 130, 246, 0.1);">
                                <view @click="changeExportFn(item.id)"
                                    class="flex-1 px-3 py-2 rounded-lg text-center text-sm font-semibold transition-all active:opacity-80 cursor-pointer"
                                    style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">
                                    {{ item.is_export == 1 ? '取消分配' : '分配' }}
                                </view>
                                <view @click="deltetCardFn(item.id)"
                                    class="flex-1 px-3 py-2 rounded-lg text-center text-sm font-semibold transition-all active:opacity-80 cursor-pointer"
                                    style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                                    删除
                                </view>
                            </view>
                        </view>
                    </view>
                </view>

                <mescroll-empty v-if="listData.length === 0 && !loading"
                    :option="{ tip: '还没有数据哟~~~' }"></mescroll-empty>
            </mescroll-body>
        </view>
        <tabbar name="ai_image" />
        <view v-show="fabOpen" class="fixed inset-0 z-[998]" @tap.stop="fabOpen = false" catchtouchmove="true"
            @touchmove.stop.prevent></view>

    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { getCardList, changeExport, deltetCard, getConfig } from '@/addon/ai_image/api/aiimage'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js'
import { onPageScroll, onReachBottom } from '@dcloudio/uni-app'
import { img } from '@/utils/common';
import { onLoad } from '@dcloudio/uni-app'
const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom)
const copyText = (text: string | number) => {
    uni.setClipboardData({
        data: String(text),
        success: () => uni.$u.toast('已复制')
    })
}
const changeExportFn = (id: number) => {
    changeExport(id).then((res: any) => {
        uni.$u.toast('操作成功')
        // 重新加载数据 - 使用正确的方法
        if (mescrollRef.value && mescrollRef.value.mescroll) {
            // 重置到第一页并刷新数据
            mescrollRef.value.mescroll.resetUpScroll()
        } else {
            // 备用方案：直接调用获取数据的方法
            const mockMescroll = { num: 1, size: 10 }
            getLogListFn(mockMescroll)
        }
    })
}
const deltetCardFn = (id: number) => {
    uni.showModal({
        title: '确认删除',
        content: '删除后不可恢复，是否继续？',
        confirmText: '删除',
        confirmColor: '#ef4444',
        cancelText: '取消',
        success: ({ confirm }) => {
            if (!confirm) return
            deltetCard(id).then((res: any) => {
                uni.$u.toast('删除成功')
                // 重新加载数据 - 使用正确的方法
                if (mescrollRef.value?.mescroll) {
                    // 重置到第一页并刷新数据
                    mescrollRef.value.mescroll.resetUpScroll()
                } else {
                    // 备用方案：直接调用获取数据的方法
                    const mockMescroll = { num: 1, size: 10 }
                    getLogListFn(mockMescroll)
                }
            })
        }
    })
}
const listData = ref<any[]>([])
let loading = ref<boolean>(false);
const mescrollRef = ref<any>(null);

const fabOpen = ref(false)
const toggleFab = () => fabOpen.value = !fabOpen.value
const goTo = (url: string) => uni.navigateTo({ url })



const getLogListFn = (mescroll: any) => {
    const data = {
        page: mescroll.num || 1,
        limit: mescroll.size || 10
    };

    loading.value = true;
    getCardList(data).then((res: any) => {
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
const config = ref()
onLoad(() => {
    getConfig().then((res: any) => {
        config.value = res.data
    })
})
</script>
<style lang="scss" scoped></style>
