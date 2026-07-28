<template>
    <view class="min-h-screen bg-[#f0f3f9]">
        <!-- 顶部搜索栏 不变 -->
        <MemberCardListHeader
            v-model="keyword"
            :activeTab="status"
            :tabs="tabItems"
            placeholder="客户 / 手机号 / 卡号 / 操作人"
            @search="reload"
            @tab-change="changeTab"
        />

        <!-- 分页列表 -->
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty>
                <view class="px-[24rpx] py-[120rpx]">
                    <u-empty text="暂无核销记录" mode="list" />
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
                        <!-- 头部：状态图标 + 服务名称 + 标签 -->
                        <view class="flex items-center gap-[16rpx]">
                            <view
                                class="h-[62rpx] w-[62rpx] flex flex-none items-center justify-center rounded-[18rpx]"
                                :class="row.status === 'success' ? 'bg-[#dcfce7]' : 'bg-[#f1f5f9]'"
                            >
                                <u-icon
                                    :name="row.status === 'success' ? 'checkmark-circle' : 'reload'"
                                    :color="row.status === 'success' ? '#16a34a' : '#64748b'"
                                    size="21"
                                />
                            </view>
                            <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                                <text class="truncate text-[28rpx] font-bold text-[#1e293b]">
                                    {{ row.item_name }}
                                </text>
                                <text class="text-[23rpx] text-[#64748b]">
                                    {{ row.holder_name }} · {{ mask(row.holder_mobile) }}
                                </text>
                            </view>
                            <u-tag
                                :text="row.status === 'success' ? '核销成功' : '已冲正'"
                                :type="row.status === 'success' ? 'success' : 'info'"
                                plain
                                plainFill
                                size="mini"
                            />
                        </view>

                        <!-- 核心数据双栏 -->
                        <view class="mt-[18rpx] grid grid-cols-2 overflow-hidden rounded-[18rpx] bg-[#f8fafc]">
                            <view class="flex flex-col gap-[6rpx] px-[20rpx] py-[18rpx]">
                                <text class="text-[21rpx] text-[#94a3b8]">权益次数</text>
                                <text class="text-[27rpx] font-bold text-[#334155]">
                                    {{ row.before_remaining }} → {{ row.after_remaining }}
                                </text>
                            </view>
                            <view class="flex flex-col gap-[6rpx] border-l border-[#eef2f6] px-[20rpx] py-[18rpx]">
                                <text class="text-[21rpx] text-[#94a3b8]">本次确认收入</text>
                                <text class="text-[27rpx] font-bold text-[#16a34a]">
                                    ¥{{ money(row.recognized_amount) }}
                                </text>
                            </view>
                        </view>

                        <!-- 详细信息列表 -->
                        <view class="mt-[16rpx] grid gap-[8rpx] text-[23rpx] text-[#64748b]">
                            <view class="flex items-center gap-[8rpx]">
                                <u-icon name="account" color="#94a3b8" size="15" />
                                <text>操作人 {{ row.operator_name || '—' }}</text>
                            </view>
                            <view class="flex items-center gap-[8rpx]">
                                <u-icon name="clock" color="#94a3b8" size="15" />
                                <text>{{ time(row.occurred_at) }}</text>
                            </view>
                            <view class="flex items-center gap-[8rpx]">
                                <u-icon name="file-text" color="#94a3b8" size="15" />
                                <text class="truncate">{{ row.redeem_no }}</text>
                            </view>
                        </view>

                        <!-- 冲正按钮 -->
                        <view v-if="row.status === 'success'" class="mt-[18rpx]">
                            <MemberCardButton
                                type="warning"
                                plain
                                compact
                                icon="reload"
                                text="冲正并恢复次数"
                                @click="reverse(row)"
                                class="!rounded-[14rpx] !text-[24rpx] !font-medium"
                            />
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardRedemptions, memberCardRequestId, reverseCardRedemption } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [{ name: '全部', value: '' }, { name: '核销成功', value: 'success' }, { name: '已冲正', value: 'reversed' }]
const tabItems = tabs.map(item => ({ label: item.name, value: item.value }))
const pagingStyle = computed(() => ({ top: '158rpx', bottom: '0' }))
const reload = () => paging.value?.reload()
const changeTab = (value: string) => { status.value = value; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardRedemptions({ keyword: keyword.value, status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const mask = (value: string) => /^\d{11}$/.test(value || '') ? `${value.slice(0, 3)}****${value.slice(-4)}` : (value || '—')
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const reverse = async (row: any) => {
    const modal = await uni.showModal({ title: '核销冲正', editable: true, placeholderText: '必须填写冲正原因', content: '冲正会恢复本次扣减的次数，原记录永久保留。', confirmText: '确认冲正' })
    if (!modal.confirm) return
    const reason = String((modal as any).content || '').trim()
    if (!reason) return uni.showToast({ title: '必须填写冲正原因', icon: 'none' })
    await reverseCardRedemption(row.id, { request_id: memberCardRequestId('reverse'), reason })
    uni.showToast({ title: '冲正成功', icon: 'success' })
    reload()
}
onShow(reload)
</script>
