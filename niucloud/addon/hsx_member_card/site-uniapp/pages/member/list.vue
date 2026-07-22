<template>
    <view class="member-list-page">
        <view class="member-search"><u-search v-model="keyword" placeholder="会员姓名 / 手机号 / 卡号" :showAction="false" bgColor="#f1f5f9" @search="reload" @clear="reload" /></view>
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty><view class="mc-empty"><u-empty text="暂无持卡会员" mode="list" /></view></template>
            <view class="mc-list-wrap">
                <view v-for="row in rows" :key="row.member_id" class="member-card mc-surface" @click="detail(row)">
                    <view class="member-card__main">
                        <view class="member-avatar">{{ String(row.display_name || '客').slice(0, 1) }}</view>
                        <view class="member-copy"><strong>{{ row.display_name }}</strong><text>{{ row.mobile_masked }}</text></view>
                        <view class="member-count"><strong>{{ row.card_count || 0 }}</strong><text>张卡</text></view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>
                    <view class="member-card__foot">
                        <view><u-icon name="checkmark-circle" color="#16a34a" size="14" /><text>可用 {{ row.available_card_count || 0 }} 张</text></view>
                        <view><u-icon name="clock" color="#94a3b8" size="14" /><text>最近开卡 {{ time(row.latest_card_at) }}</text></view>
                    </view>
                </view>
            </view>
        </z-paging>
        <view class="member-bottom">
            <text>共 {{ total }} 位持卡会员</text>
            <view class="member-bottom__button"><MemberCardButton type="primary" icon="plus" text="新增会员" @click="popupVisible = true" /></view>
        </view>
        <MemberCardMemberPopup v-model:show="popupVisible" @select="created" />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardMembers } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'

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

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.member-list-page { min-height: 100vh; background: #f5f7fb; }
.member-search { position: fixed; z-index: 10; top: 0; right: 0; left: 0; padding: 16rpx 24rpx; border-bottom: 1rpx solid #e6ebf2; background: #fff; }
.member-card { margin-bottom: 16rpx; padding: 22rpx; }
.member-card__main { display: flex; align-items: center; gap: 14rpx; }
.member-avatar { display: flex; width: 62rpx; height: 62rpx; flex: 0 0 62rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-size: 25rpx; font-weight: 700; }
.member-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }.member-copy strong { font-size: 28rpx; }.member-copy text { color: #64748b; font-size: 22rpx; }
.member-count { display: flex; min-width: 56rpx; flex-direction: column; align-items: center; }.member-count strong { color: #2563eb; font-size: 27rpx; }.member-count text { color: #94a3b8; font-size: 18rpx; }
.member-card__foot { display: flex; margin-top: 18rpx; padding-top: 16rpx; align-items: center; justify-content: space-between; gap: 15rpx; border-top: 1rpx solid #edf1f6; }.member-card__foot view { display: flex; min-width: 0; align-items: center; gap: 7rpx; color: #64748b; font-size: 20rpx; }.member-card__foot text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.member-bottom { position: fixed; z-index: 20; right: 0; bottom: 0; left: 0; display: flex; padding: 16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom)); align-items: center; gap: 20rpx; border-top: 1rpx solid #e6ebf2; background: rgba(255,255,255,.97); }.member-bottom > text { flex: 1; color: #64748b; font-size: 22rpx; }.member-bottom__button { width: 390rpx; }
</style>
