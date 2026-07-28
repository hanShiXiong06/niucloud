<template>
    <view class="min-h-screen bg-[#f0f3f9]">
        <!-- 顶部搜索栏 不变 -->
        <MemberCardListHeader
            v-model="keyword"
            :activeTab="status"
            :tabs="tabItems"
            placeholder="搜索卡种名称"
            @search="reload"
            @tab-change="changeTab"
        />

        <!-- 分页列表 -->
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty>
                <view class="px-[24rpx] py-[120rpx]">
                    <u-empty text="暂无卡种，先创建一张服务卡" mode="list" />
                </view>
            </template>

            <view class="px-[24rpx] py-[20rpx] pb-[40rpx]">
                <view
                    v-for="row in rows"
                    :key="row.id"
                    class="mb-[20rpx] overflow-hidden rounded-[26rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)] transition-all active:scale-[0.98]"
                >
                    <!-- 卡片头部：图标 + 卡种名 + 标签 -->
                    <view class="flex items-center gap-[16rpx] p-[24rpx]">
                        <view class="flex h-[64rpx] w-[64rpx] flex-none items-center justify-center rounded-[18rpx] bg-[#eff6ff]">
                            <u-icon name="order" color="#2563eb" size="23" />
                        </view>
                        <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                            <text class="truncate text-[29rpx] font-bold text-[#1e293b]">
                                {{ row.product_name }}
                            </text>
                            <text class="text-[23rpx] text-[#64748b]">
                                {{ row.item?.item_name || '服务权益' }}
                            </text>
                        </view>
                        <u-tag
                            :text="statusText(row.status)"
                            :type="statusType(row.status)"
                            plain
                            plainFill
                            size="mini"
                        />
                    </view>

                    <!-- 三个关键数据格 -->
                    <view class="mx-[24rpx] grid grid-cols-3 overflow-hidden rounded-[18rpx] bg-[#f8fafc]">
                        <view class="flex flex-col gap-[6rpx] px-[16rpx] py-[18rpx]">
                            <text class="text-[21rpx] text-[#94a3b8]">销售价</text>
                            <text class="truncate text-[26rpx] font-bold text-[#ea580c]">
                                ¥{{ money(row.sale_price) }}
                            </text>
                        </view>
                        <view class="flex flex-col gap-[6rpx] border-l border-[#eef2f6] px-[16rpx] py-[18rpx]">
                            <text class="text-[21rpx] text-[#94a3b8]">权益次数</text>
                            <text class="truncate text-[26rpx] font-semibold text-[#334155]">
                                {{ usageText(row.item) }}
                            </text>
                        </view>
                        <view class="flex flex-col gap-[6rpx] border-l border-[#eef2f6] px-[16rpx] py-[18rpx]">
                            <text class="text-[21rpx] text-[#94a3b8]">有效期</text>
                            <text class="truncate text-[26rpx] font-semibold text-[#334155]">
                                {{ row.validity_text || '永久有效' }}
                            </text>
                        </view>
                    </view>

                    <!-- 底部操作栏 -->
                    <view class="flex items-center justify-between gap-[16rpx] px-[24rpx] py-[18rpx]">
                        <text class="min-w-0 truncate text-[22rpx] text-[#94a3b8]">
                            {{ row.product_no }}
                        </text>
                        <view class="flex flex-none items-center gap-[24rpx] text-[24rpx] font-medium">
                            <view
                                v-if="row.status === 'draft'"
                                class="text-[#dc2626] active:text-[#b91c1c]"
                                @click="remove(row)"
                            >
                                删除
                            </view>
                            <view class="text-[#475569] active:text-[#1e293b]" @click="edit(row)">
                                编辑
                            </view>
                            <view
                                v-if="row.status !== 'enabled'"
                                class="text-[#2563eb] active:text-[#1d4ed8]"
                                @click="changeStatus(row, true)"
                            >
                                启用
                            </view>
                            <view
                                v-else
                                class="text-[#d97706] active:text-[#b45309]"
                                @click="changeStatus(row, false)"
                            >
                                停用
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 底部新增按钮 毛玻璃统一风格 -->
        <view
            class="fixed bottom-0 left-0 right-0 z-20 border-t border-[#e8ecf1] bg-white/80 px-[24rpx] pt-[16rpx] backdrop-blur-[20rpx]"
            :style="{ paddingBottom: 'calc(16rpx + env(safe-area-inset-bottom))' }"
        >
            <MemberCardButton
                type="primary"
                icon="plus"
                text="新增卡种"
                @click="add"
                class="!h-[88rpx] !rounded-[20rpx] !text-[29rpx] !font-bold shadow-[0_8rpx_20rpx_rgba(37,99,235,0.25)]"
            />
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { deleteCardProduct, disableCardProduct, enableCardProduct, getCardProducts } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [{ name: '全部', value: '' }, { name: '已启用', value: 'enabled' }, { name: '草稿', value: 'draft' }, { name: '已停用', value: 'disabled' }]
const tabItems = tabs.map(item => ({ label: item.name, value: item.value }))
const pagingStyle = computed(() => ({ top: '158rpx', bottom: '118rpx' }))
const reload = () => paging.value?.reload()
const changeTab = (value: string) => { status.value = value; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardProducts({ keyword: keyword.value, status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const usageText = (item: any) => item?.usage_mode === 'unlimited' ? '不限次' : `${Number(item?.total_times || 0)} 次`
const statusText = (value: string) => ({ draft: '草稿', enabled: '已启用', disabled: '已停用' }[value] || value)
const statusType = (value: string) => ({ enabled: 'success', disabled: 'warning', draft: 'info' }[value] || 'info') as any
const add = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/product/edit' })
const edit = (row: any) => uni.navigateTo({ url: `/addon/hsx_member_card/pages/product/edit?id=${row.id}` })
const changeStatus = async (row: any, enabled: boolean) => {
    const modal = await uni.showModal({ title: enabled ? '启用卡种' : '停用卡种', content: enabled ? '启用后店员可以立即销售该卡种。' : '停用后不能继续开新卡，已售会员卡不受影响。', confirmText: enabled ? '确认启用' : '确认停用' })
    if (!modal.confirm) return
    enabled ? await enableCardProduct(row.id) : await disableCardProduct(row.id)
    uni.showToast({ title: enabled ? '卡种已启用' : '卡种已停用', icon: 'success' })
    reload()
}
const remove = async (row: any) => {
    const modal = await uni.showModal({ title: '删除草稿卡种', content: `确认删除“${row.product_name}”？删除后不能恢复。`, confirmColor: '#dc2626', confirmText: '删除' })
    if (!modal.confirm) return
    await deleteCardProduct(row.id)
    uni.showToast({ title: '已删除', icon: 'success' })
    reload()
}
onShow(reload)
</script>
