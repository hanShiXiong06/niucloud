<template>
    <view class="mc-page">
        <z-paging ref="paging" v-model="rows" @query="query">
            <template #top>
                <MemberCardListHeader
                    v-model="keyword"
                    :activeTab="status"
                    :tabs="tabs"
                    placeholder="搜索卡种名称"
                    @search="reload"
                    @tab-change="changeTab"
                />
            </template>
            <template #empty>
                <MemberCardState
                    text="暂无符合条件的卡种"
                    :error="listError"
                    :action="listError ? '重新加载' : '新增卡种'"
                    @action="listError ? reload() : add()"
                />
            </template>
            <view class="mc-content">
                <MemberCardNotice v-if="actionError" tone="error" :text="actionError" closable />
                <view v-for="row in rows" :key="row.id" class="mc-card">
                    <view class="mc-between">
                        <text class="mc-title mc-grow">{{ row.product_name }}</text>
                        <text class="mc-badge" :class="'mc-badge--' + statusTone(row.status)">
                            {{ statusText(row.status) }}
                        </text>
                    </view>
                    <view class="mc-sub">{{ row.item?.item_name || '服务权益' }} · {{ bindingText(row.item) }}</view>
                    <view class="mc-card-preview mc-between">
                        <text class="mc-money">¥{{ money(row.sale_price) }}</text>
                        <view style="text-align: right">
                            <view>{{ usageText(row.item) }}</view>
                            <text class="mc-small">{{ row.validity_text || '有效期待确认' }}</text>
                        </view>
                    </view>
                    <view class="mc-between" style="margin-top: 14rpx">
                        <text class="mc-small" @click="copyText(row.product_no)">卡种编号 · 复制</text>
                        <view class="mc-row">
                            <text class="mc-link" @click="more(row)">更多</text>
                            <text class="mc-link" @click="edit(row)">编辑卡种</text>
                        </view>
                    </view>
                </view>
            </view>
            <template #bottom>
                <MemberCardActionBar>
                    <view class="mc-actionbar__button">
                        <MemberCardButton text="新增卡种" icon="plus" type="primary" :loading="busy" @click="add" />
                    </view>
                </MemberCardActionBar>
            </template>
        </z-paging>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { ref } from 'vue'
import { getCardProducts, deleteCardProduct, disableCardProduct, enableCardProduct } from '../../api'
import { useMemberCardList } from '../../hooks/useMemberCardList'
import { copyText, markMemberCardChanged, money, errorText } from '../../utils/presentation'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardListHeader from '../../components/MemberCardListHeader.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardState from '../../components/MemberCardState.vue'
const keyword = ref(''),
    status = ref(''),
    busy = ref(false),
    actionError = ref('')
const tabs = [
    { label: '全部', value: '' },
    { label: '已启用', value: 'enabled' },
    { label: '草稿', value: 'draft' },
    { label: '已停用', value: 'disabled' }
]
const { paging, rows, listError, query, reload, refresh } = useMemberCardList(getCardProducts, () => ({
    keyword: keyword.value.trim(),
    status: status.value
}))
const changeTab = (value: string) => {
    status.value = value
    reload()
}
const usageText = (item: any) =>
    item?.usage_mode === 'unlimited' ? '不限次' : String(Number(item?.total_times || 0)) + ' 次'
const bindingText = (item: any) =>
    ({ imei: '一机一卡', model: '限定型号', member: '按会员' })[item?.binding_mode || 'member'] || '按会员'
const statusText = (value: string) => ({ draft: '草稿', enabled: '已启用', disabled: '已停用' })[value] || '待确认'
const statusTone = (value: string) => ({ enabled: 'success', disabled: 'muted', draft: 'primary' })[value] || 'muted'
const add = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/product/edit' })
const edit = (row: any) => uni.navigateTo({ url: '/addon/hsx_member_card/pages/product/edit?id=' + row.id })

const more = async (row: any) => {
    if (busy.value) return
    busy.value = true
    actionError.value = ''
    try {
        const choices = [
            row.status === 'enabled' ? '停用卡种' : '启用卡种',
            ...(row.status === 'draft' ? ['删除草稿'] : [])
        ]
        let choice: any
        try {
            choice = await uni.showActionSheet({ itemList: choices })
        } catch {
            return
        }
        const remove = choice.tapIndex === 1,
            enabled = row.status !== 'enabled'
        const modal = await uni.showModal({
            title: remove ? '删除草稿卡种' : enabled ? '启用卡种' : '停用卡种',
            content: remove
                ? '删除后不能恢复，确认删除“' + row.product_name + '”？'
                : enabled
                  ? '启用后可销售此卡种。'
                  : '停用后不能开新卡，已售会员卡仍按原规则使用。',
            confirmText: remove ? '确认删除' : '确认',
            confirmColor: remove ? '#bb3e3e' : '#2563eb'
        })
        if (!modal.confirm) return
        if (remove) await deleteCardProduct(row.id)
        else if (enabled) await enableCardProduct(row.id)
        else await disableCardProduct(row.id)
        markMemberCardChanged()
        uni.showToast({ title: '操作成功', icon: 'success' })
        refresh()
    } catch (e) {
        actionError.value = errorText(e, '操作未完成，请刷新核对后再试')
    } finally {
        busy.value = false
    }
}
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
