<template>
    <view class="min-h-screen bg-[#f0f3f9]">
        <!-- 顶部搜索栏 逻辑不动 -->
        <MemberCardListHeader
            v-model="keyword"
            :activeTab="status"
            :tabs="tabItems"
            placeholder="姓名 / 手机号 / 订单号 / 卡种"
            @search="reload"
            @tab-change="changeTab"
        />

        <!-- 分页列表 -->
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty>
                <view class="px-[24rpx] py-[120rpx]">
                    <u-empty text="暂无开卡订单" mode="list" />
                </view>
            </template>

            <view class="px-[24rpx] py-[20rpx] pb-[40rpx]">
                <view
                    v-for="row in rows"
                    :key="row.id"
                    class="mb-[20rpx] overflow-hidden rounded-[26rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)] transition-all active:scale-[0.98]"
                >
                    <!-- 卡片主体 -->
                    <view class="p-[24rpx]">
                        <!-- 头部：头像 + 姓名 + 财务标签 -->
                        <view class="flex items-center gap-[16rpx]">
                            <view
                                class="h-[68rpx] w-[68rpx] flex flex-none items-center justify-center rounded-full bg-gradient-to-br from-[#dbeafe] to-[#eff6ff] text-[28rpx] text-[#2563eb] font-bold shadow-sm"
                            >
                                {{ String(row.holder_name || '客').slice(0, 1) }}
                            </view>
                            <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                                <text class="truncate text-[30rpx] font-bold text-[#1e293b]">
                                    {{ row.holder_name || '未命名客户' }}
                                </text>
                                <text class="text-[23rpx] text-[#64748b]">
                                    {{ mask(row.holder_mobile) }}
                                </text>
                            </view>
                            <u-tag
                                :text="financeText(row.finance_status)"
                                :type="financeType(row.finance_status)"
                                plain
                                plainFill
                                size="mini"
                            />
                        </view>

                        <!-- 产品信息 + 金额 -->
                        <view
                            class="my-[18rpx] flex items-center gap-[14rpx] rounded-[16rpx] bg-[#f8fafc] py-[16rpx] px-[18rpx]"
                        >
                            <view
                                class="h-[56rpx] w-[56rpx] flex flex-none items-center justify-center rounded-[16rpx] bg-[#eff6ff]"
                            >
                                <u-icon name="order" color="#2563eb" size="20" />
                            </view>
                            <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                                <text class="truncate text-[26rpx] font-semibold text-[#334155]">
                                    {{ row.product_name }}
                                </text>
                                <text class="text-[22rpx] text-[#94a3b8]">开卡金额</text>
                            </view>
                            <text class="flex-none text-[30rpx] font-bold text-[#ea580c]">
                                ¥{{ money(row.order_amount) }}
                            </text>
                        </view>

                        <!-- 订单信息 -->
                        <view class="grid gap-[8rpx]">
                            <view class="flex items-center gap-[8rpx] text-[23rpx] text-[#64748b]">
                                <u-icon name="file-text" color="#94a3b8" size="15" />
                                <text class="truncate">{{ row.order_no }}</text>
                            </view>
                            <view class="flex items-center gap-[8rpx] text-[23rpx] text-[#64748b]">
                                <u-icon name="account" color="#94a3b8" size="15" />
                                <text>开单 {{ row.issuer_name || '—' }}</text>
                            </view>
                            <view class="flex items-center gap-[8rpx] text-[23rpx] text-[#64748b]">
                                <u-icon name="clock" color="#94a3b8" size="15" />
                                <text>{{ time(row.create_at) }}</text>
                            </view>
                        </view>

                        <!-- 错误信息 -->
                        <view
                            v-if="row.last_error"
                            class="mt-[16rpx] flex items-start gap-[8rpx] rounded-[12rpx] bg-[#fef2f2] px-[16rpx] py-[14rpx] text-[22rpx] text-[#dc2626]"
                        >
                            <u-icon name="warning" color="#dc2626" size="16" />
                            <text>{{ row.last_error }}</text>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 浮动快速开卡按钮 保持原有交互 -->
        <view
            class="fixed bottom-[50rpx] right-[28rpx] z-20 flex h-[84rpx] items-center justify-center gap-[10rpx] rounded-full bg-gradient-to-r from-[#2563eb] to-[#3b82f6] px-[28rpx] text-white shadow-[0_12rpx_28rpx_rgba(37,99,235,0.25)] active:scale-95 transition-all"
            @click="goCreate"
        >
            <u-icon name="plus" color="#ffffff" size="21" />
            <text class="text-[26rpx] font-semibold">快速开卡</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardOrders } from '../../api'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [
    { name: '全部', value: '' }, { name: '待收款', value: 'pending' }, { name: '部分收款', value: 'partial' },
    { name: '已结清', value: 'settled' }, { name: '失败', value: 'failed' },
]
const tabItems = tabs.map(item => ({ label: item.name, value: item.value }))
const pagingStyle = computed(() => ({ top: '158rpx', bottom: '0' }))
const reload = () => paging.value?.reload()
const changeTab = (value: string) => { status.value = value; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardOrders({ keyword: keyword.value, finance_status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const mask = (value: string) => /^\d{11}$/.test(value || '') ? `${value.slice(0, 3)}****${value.slice(-4)}` : (value || '—')
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const financeText = (value: string) => ({ pending: '待收款', partial: '部分收款', settled: '已结清', failed: '处理失败', refunded: '已退款' }[value] || value)
const financeType = (value: string) => ({ settled: 'success', failed: 'error', partial: 'warning', pending: 'warning' }[value] || 'info') as any
const goCreate = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/order/create' })
onShow(reload)
</script>
