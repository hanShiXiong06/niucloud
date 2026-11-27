<template>
    <view class="mc-page">
        <z-paging ref="paging" v-model="rows" @query="query">
            <template #top>
                <MemberCardListHeader v-model="keyword" placeholder="姓名 / 手机号 / 卡号" @search="reload" />
            </template>
            <template #empty>
                <MemberCardState
                    text="暂无持卡会员"
                    :error="listError"
                    :action="listError ? '重新加载' : '新增会员'"
                    @action="listError ? reload() : (popupVisible = true)"
                />
            </template>
            <view class="mc-content">
                <view
                    v-for="row in rows"
                    :key="row.member_id"
                    class="mc-card mc-card--flat"
                    hover-class="opacity-80"
                >
                    <u-cell
                        :title="row.display_name || '未命名会员'"
                        :label="row.mobile_masked || '未留手机号'"
                        isLink
                        center
                        :border="false"
                        @click="detail(row)"
                    >
                        <template #icon
                            ><view class="mc-avatar" style="margin-right: 8px">{{
                                String(row.display_name || '客').slice(0, 1)
                            }}</view></template
                        >
                    </u-cell>
                    <view class="mc-member-row-foot">
                        <text class="mc-small"
                            >共 {{ row.card_count || 0 }} 张卡 · {{ row.available_card_count || 0 }} 张有效 /
                            待激活</text
                        >
                        <text class="mc-small">{{ dateTime(row.latest_card_at).slice(0, 10) }}</text>
                    </view>
                </view>
            </view>
            <template #bottom>
                <MemberCardActionBar>
                    <view class="mc-actionbar__summary mc-small">共 {{ total }} 位持卡会员</view>
                    <view class="mc-actionbar__button">
                        <MemberCardButton text="新增会员" type="primary" icon="plus" @click="popupVisible = true" />
                    </view>
                </MemberCardActionBar>
            </template>
        </z-paging>
        <MemberCardMemberPopup :show="popupVisible" @update:show="popupVisible = $event" @select="created" />
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { ref } from 'vue'
import { getCardMembers } from '../../api'
import { useMemberCardList } from '../../hooks/useMemberCardList'
import { dateTime } from '../../utils/presentation'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'
const keyword = ref(''),
    popupVisible = ref(false)
const { paging, rows, total, listError, query, reload } = useMemberCardList(getCardMembers, () => ({
    keyword: keyword.value.trim()
}))
const detail = (row: any) => uni.navigateTo({ url: '/addon/hsx_member_card/pages/member/detail?id=' + row.member_id })
const created = (row: any) => {
    popupVisible.value = false
    detail(row)
}
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
