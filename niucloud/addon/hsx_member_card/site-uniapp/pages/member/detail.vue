<template>
    <view class="min-h-screen bg-[#f0f3f9] px-[24rpx] pt-[24rpx] pb-[160rpx]">
        <!-- 会员信息卡片 -->
        <view
            class="rounded-[28rpx] bg-white p-[26rpx] shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]"
        >
            <view class="flex items-center gap-[18rpx]">
                <!-- 头像：渐变背景 + 首字 -->
                <view
                    class="h-[80rpx] w-[80rpx] flex flex-none items-center justify-center rounded-full bg-gradient-to-br from-[#dbeafe] to-[#eff6ff] text-[32rpx] text-[#2563eb] font-bold shadow-sm"
                >
                    {{ String(member.display_name || '客').slice(0, 1) }}
                </view>
                <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                    <text class="text-[33rpx] text-[#1e293b] font-bold">
                        {{ member.display_name || '会员' }}
                    </text>
                    <text class="text-[25rpx] text-[#64748b]">
                        {{ member.mobile_masked || '—' }}
                    </text>
                    <text class="text-[22rpx] text-[#94a3b8]">
                        {{ member.member_no || '暂无会员号' }}
                    </text>
                </view>
                <view
                    class="h-[68rpx] w-[68rpx] flex items-center justify-center rounded-[18rpx] bg-[#eff6ff] active:scale-95 transition-all"
                    @click="call"
                >
                    <u-icon name="phone" color="#2563eb" size="22" />
                </view>
            </view>
        </view>

        <!-- 数据统计 -->
        <view
            class="mt-[20rpx] grid grid-cols-3 overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]"
        >
            <view class="flex flex-col items-center gap-[6rpx] py-[24rpx]">
                <text class="text-[32rpx] font-bold text-[#1e293b]">
                    {{ member.card_count || 0 }}
                </text>
                <text class="text-[22rpx] text-[#94a3b8]">全部卡</text>
            </view>
            <view
                class="flex flex-col items-center gap-[6rpx] border-x border-[#f1f5f9] py-[24rpx]"
            >
                <text class="text-[32rpx] font-bold text-[#16a34a]">
                    {{ member.available_card_count || 0 }}
                </text>
                <text class="text-[22rpx] text-[#94a3b8]">可用卡</text>
            </view>
            <view class="flex flex-col items-center gap-[6rpx] py-[24rpx]">
                <text class="text-[32rpx] font-bold text-[#1e293b]">
                    {{ redemptions.length }}
                </text>
                <text class="text-[22rpx] text-[#94a3b8]">核销记录</text>
            </view>
        </view>

        <!-- Tab 切换 -->
        <view
            class="my-[22rpx] grid grid-cols-2 overflow-hidden rounded-[20rpx] bg-[#f1f5f9] p-[6rpx]"
        >
            <view
                class="rounded-[16rpx] py-[16rpx] text-center text-[27rpx] font-medium transition-all"
                :class="
                    tab === 'cards'
                        ? 'bg-white text-[#2563eb] font-bold shadow-[0_4rpx_12rpx_rgba(15,23,42,0.06)]'
                        : 'text-[#64748b]'
                "
                @click="tab = 'cards'"
            >
                会员卡
            </view>
            <view
                class="rounded-[16rpx] py-[16rpx] text-center text-[27rpx] font-medium transition-all"
                :class="
                    tab === 'records'
                        ? 'bg-white text-[#2563eb] font-bold shadow-[0_4rpx_12rpx_rgba(15,23,42,0.06)]'
                        : 'text-[#64748b]'
                "
                @click="tab = 'records'"
            >
                消费记录
            </view>
        </view>

        <!-- 会员卡列表 -->
        <view v-if="tab === 'cards'">
            <view
                v-for="card in cards"
                :key="card.id"
                class="mb-[18rpx] overflow-hidden rounded-[26rpx] bg-white shadow-[0_6rpx_20rpx_rgba(15,23,42,0.04)]"
            >
                <view class="p-[22rpx]">
                    <!-- 卡片头部 -->
                    <view class="flex items-start justify-between gap-[14rpx]">
                        <view class="min-w-0 flex flex-1 flex-col gap-[6rpx]">
                            <text class="text-[30rpx] font-bold text-[#1e293b]">
                                {{ card.product_name }}
                            </text>
                            <text class="text-[22rpx] text-[#94a3b8]">
                                {{ card.card_no }}
                            </text>
                        </view>
                        <u-tag
                            :text="card.status_text"
                            :type="cardType(card.status)"
                            plain
                            plainFill
                            size="mini"
                        />
                    </view>

                    <!-- 卡内项目 -->
                    <view
                        v-for="item in card.items || []"
                        :key="item.id"
                        class="mt-[16rpx] flex items-center justify-between gap-[16rpx] rounded-[16rpx] bg-[#f8fafc] p-[18rpx]"
                    >
                        <view class="min-w-0 flex flex-1 flex-col gap-[6rpx]">
                            <text class="text-[26rpx] font-semibold text-[#334155]">
                                {{ item.item_name }}
                            </text>
                            <text class="text-[22rpx] text-[#64748b]">
                                {{ card.validity_text }}
                            </text>
                        </view>
                        <view class="flex flex-none flex-col items-end gap-[4rpx]">
                            <text class="text-[34rpx] font-bold text-[#2563eb]">
                                {{ item.usage_mode === 'unlimited' ? '不限次' : item.remaining_times }}
                            </text>
                            <text class="text-[21rpx] text-[#64748b]">
                                {{ item.usage_mode === 'unlimited' ? '使用次数' : `共 ${item.granted_times} 次` }}
                            </text>
                        </view>
                    </view>

                    <!-- 底部信息 -->
                    <view
                        class="mt-[16rpx] flex justify-between gap-[14rpx] border-t border-[#f1f5f9] pt-[14rpx] text-[22rpx] text-[#94a3b8]"
                    >
                        <text>开卡人 {{ card.issuer_name || '—' }}</text>
                        <text>{{ time(card.create_at) }}</text>
                    </view>
                </view>
            </view>
            <view v-if="!cards.length" class="py-[120rpx]">
                <u-empty text="该会员暂未购卡" mode="list" />
            </view>
        </view>

        <!-- 消费记录列表 -->
        <view v-else>
            <view
                v-for="row in redemptions"
                :key="row.id"
                class="mb-[16rpx] flex items-center gap-[16rpx] rounded-[26rpx] bg-white p-[22rpx] shadow-[0_6rpx_20rpx_rgba(15,23,42,0.04)]"
            >
                <!-- 状态图标 -->
                <view
                    class="h-[58rpx] w-[58rpx] flex flex-none items-center justify-center rounded-[16rpx]"
                    :class="
                        row.status === 'success'
                            ? 'bg-[#dcfce7]'
                            : 'bg-[#f1f5f9]'
                    "
                >
                    <u-icon
                        :name="row.status === 'success' ? 'checkmark-circle' : 'reload'"
                        :color="row.status === 'success' ? '#16a34a' : '#64748b'"
                        size="20"
                    />
                </view>

                <view class="min-w-0 flex flex-1 flex-col gap-[4rpx]">
                    <text class="text-[28rpx] font-semibold text-[#1e293b]">
                        {{ row.item_name }}
                    </text>
                    <text class="text-[23rpx] text-[#475569]">
                        {{ row.status === 'success' ? `核销 1 次 · 剩余 ${row.after_remaining} 次` : '本次核销已冲正' }}
                    </text>
                    <text class="text-[21rpx] text-[#94a3b8]">
                        {{ row.operator_name || '—' }} · {{ time(row.occurred_at) }}
                    </text>
                </view>

                <text class="text-[26rpx] font-bold text-[#16a34a]">
                    ¥{{ money(row.recognized_amount) }}
                </text>
            </view>
            <view v-if="!redemptions.length" class="py-[120rpx]">
                <u-empty text="暂无消费记录" mode="list" />
            </view>
        </view>

        <!-- 底部操作栏 毛玻璃按钮组 -->
        <view
            class="fixed bottom-0 left-0 right-0 z-20 grid grid-cols-2 gap-[16rpx] border-t border-[#e8ecf1] bg-white/80 px-[24rpx] pt-[16rpx] backdrop-blur-[20rpx]"
            :style="{ paddingBottom: 'calc(16rpx + env(safe-area-inset-bottom))' }"
        >
            <view>
                <MemberCardButton
                    plain
                    type="primary"
                    icon="order"
                    text="购买次卡"
                    @click="buy"
                    class="!h-[84rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold"
                />
            </view>
            <view>
                <MemberCardButton
                    type="primary"
                    icon="checkmark"
                    text="次卡核销"
                    @click="redeem"
                    class="!h-[84rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold shadow-[0_8rpx_20rpx_rgba(37,99,235,0.25)]"
                />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getCardMember } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const id = ref(0)
const tab = ref('cards')
const data = ref<any>({})
const member = computed(() => data.value.member || {})
const cards = computed(() => data.value.cards || [])
const redemptions = computed(() => data.value.redemptions || [])
const load = async () => { if (id.value) data.value = ((await getCardMember(id.value)) as any)?.data || {} }
const money = (value: any) => Number(value || 0).toFixed(2)
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const cardType = (status: string) => ({ active: 'success', pending: 'primary', frozen: 'warning' }[status] || 'info') as any
const call = () => member.value.mobile && uni.makePhoneCall({ phoneNumber: String(member.value.mobile) })
const buy = () => uni.navigateTo({ url: `/addon/hsx_member_card/pages/order/create?member_id=${id.value}` })
const redeem = () => uni.navigateTo({ url: `/addon/hsx_member_card/pages/card/search?mobile=${encodeURIComponent(member.value.mobile || '')}` })
onLoad((options: any) => { id.value = Number(options?.id || 0) })
onShow(load)
</script>
