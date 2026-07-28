<template>
    <view class="min-h-screen bg-[#f0f3f9]">
        <!-- 搜索栏 保持原组件不变 -->
        <MemberCardListHeader v-model="keyword" placeholder="会员姓名 / 手机号 / 卡号" @search="reload" />

        <!-- 列表区域 -->
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty>
                <view class="px-[24rpx] py-[120rpx]">
                    <u-empty text="暂无持卡会员" mode="list" />
                </view>
            </template>

            <view class="px-[24rpx] py-[20rpx] pb-[40rpx]">
                <view
                    v-for="row in rows"
                    :key="row.member_id"
                    class="mb-[20rpx] overflow-hidden rounded-[26rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)] transition-all active:scale-[0.98]"
                    hover-class="opacity-80"
                    @click="detail(row)"
                >
                    <!-- 卡片主体 -->
                    <view class="p-[24rpx]">
                        <view class="flex items-center gap-[16rpx]">
                            <!-- 头像：渐变背景 + 首字 -->
                            <view
                                class="h-[72rpx] w-[72rpx] flex flex-none items-center justify-center rounded-full bg-gradient-to-br from-[#dbeafe] to-[#eff6ff] text-[30rpx] text-[#2563eb] font-bold shadow-sm"
                            >
                                {{ String(row.display_name || '客').slice(0, 1) }}
                            </view>

                            <!-- 姓名 + 手机号 -->
                            <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                                <text class="truncate text-[29rpx] text-[#1e293b] font-bold">
                                    {{ row.display_name || '未命名会员' }}
                                </text>
                                <text class="text-[23rpx] text-[#64748b]">
                                    {{ row.mobile_masked || '暂无手机号' }}
                                </text>
                            </view>

                            <!-- 卡数 + 箭头 -->
                            <view class="flex items-center gap-[10rpx]">
                                <view class="flex flex-col items-center min-w-[60rpx]">
                                    <text class="text-[32rpx] font-bold text-[#2563eb]">
                                        {{ row.card_count || 0 }}
                                    </text>
                                    <text class="text-[20rpx] text-[#94a3b8]">张卡</text>
                                </view>
                                <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                            </view>
                        </view>

                        <!-- 底部信息条 -->
                        <view class="mt-[18rpx] flex items-center justify-between gap-[16rpx] border-t border-[#f1f5f9] pt-[16rpx]">
                            <view class="flex items-center gap-[6rpx]">
                                <view class="h-[16rpx] w-[16rpx] rounded-full bg-[#16a34a]"></view>
                                <text class="text-[22rpx] font-medium text-[#475569]">
                                    可用 {{ row.available_card_count || 0 }} 张
                                </text>
                            </view>
                            <view class="flex items-center gap-[6rpx]">
                                <u-icon name="clock" color="#94a3b8" size="15" />
                                <text class="text-[22rpx] text-[#94a3b8]">
                                    最近开卡 {{ time(row.latest_card_at) }}
                                </text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <!-- 底部操作栏 玻璃质感 + 清晰按钮 -->
        <view
            class="fixed bottom-0 left-0 right-0 z-20 flex items-center gap-[24rpx] border-t border-[#e8ecf1] bg-white/80 px-[24rpx] pt-[16rpx] backdrop-blur-[20rpx]"
            :style="{ paddingBottom: 'calc(16rpx + env(safe-area-inset-bottom))' }"
        >
            <text class="flex-1 text-[24rpx] font-medium text-[#64748b]">
                共 {{ total }} 位持卡会员
            </text>
            <view class="w-[400rpx]">
                <MemberCardButton
                    type="primary"
                    icon="plus"
                    text="新增会员"
                    @click="popupVisible = true"
                    class="!h-[84rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold shadow-[0_8rpx_20rpx_rgba(37,99,235,0.25)]"
                />
            </view>
        </view>

        <!-- 新增会员弹窗 保持原逻辑 -->
        <MemberCardMemberPopup
            v-if="popupVisible"
            :show="popupVisible"
            @update:show="popupVisible = Boolean($event)"
            @select="created"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardMembers } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'

const keyword = ref('')
const rows = ref<any[]>([])
const total = ref(0)
const paging = ref<any>()
const popupVisible = ref(false)
const pagingStyle = computed(() => ({ top: '94rpx', bottom: '120rpx' }))
const reload = () => paging.value?.reload()
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardMembers({ keyword: keyword.value, page, limit })
        total.value = Number(result?.data?.total || 0)
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleDateString('zh-CN') : '—'
const detail = (row: any) => uni.navigateTo({ url: `/addon/hsx_member_card/pages/member/detail?id=${row.member_id}` })
const created = (row: any) => { popupVisible.value = false; detail(row) }
onShow(reload)
</script>
