<!--
  ErpPartyPopup - 往来单位选择弹窗（对齐PC端 CounterpartySelect 逻辑）

  正确流程：
  1. 搜索会员列表（erp/counterparty/member_options）
  2. 用户选中会员
  3. 调用 resolve_contact(member_id, role_type) 获取或创建往来主体
  4. 返回 party_id + party_name
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="popup-wrap">
            <view class="popup-header">
                <text class="popup-title">{{ roleType === 'supplier' ? '选择供应商' : '选择客户' }}</text>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>
            <view class="popup-search">
                <u-search
                    v-model="keyword"
                    placeholder="搜索姓名 / 手机号 / 会员号"
                    :showAction="false"
                    bgColor="#f1f5f9"
                    height="34"
                    @search="search"
                    @clear="search"
                />
            </view>
            <scroll-view scroll-y class="popup-list">
                <view v-if="loading" class="popup-loading"><u-loading-icon size="24" /></view>
                <template v-else>
                    <view
                        v-for="item in list"
                        :key="item.member_id"
                        class="party-item"
                        :class="{ selected: item.party_id === partyId }"
                        @click="selectMember(item)"
                    >
                        <view class="party-item__main">
                            <view class="party-item__left">
                                <text class="party-item__name">
                                    {{ item.party_name || item.counterparty_name || item.nickname || item.username || '未命名' }}
                                </text>
                                <text v-if="item.party_name" class="party-item__member">
                                    会员：{{ item.nickname || item.username }}
                                </text>
                            </view>
                            <view class="party-item__right">
                                <text class="party-item__mobile">{{ item.mobile }}</text>
                                <text v-if="item.m_no" class="party-item__mno">M号 {{ item.m_no }}</text>
                            </view>
                        </view>
                        <view v-if="item.party_id" class="party-item__badge">
                            <u-tag text="已有往来主体" type="success" plain plainFill size="mini" />
                        </view>
                    </view>
                    <view class="popup-empty" v-if="!list.length && !loading">
                        <u-empty mode="search" text="未找到匹配的会员" :image-size="60" />
                        <text class="popup-empty__hint">请先在会员模块添加该用户</text>
                    </view>
                </template>
            </scroll-view>
        </view>
        <u-overlay :show="resolving">
            <view class="resolving-wrap">
                <u-loading-icon size="32" color="#fff" />
                <text class="resolving-text">正在处理往来主体...</text>
            </view>
        </u-overlay>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import request from '@/utils/request'

const props = withDefaults(defineProps<{
    show: boolean
    roleType?: 'supplier' | 'customer'
    partyId?: number
    partyName?: string
}>(), {
    show: false,
    roleType: 'customer',
    partyId: 0,
    partyName: '',
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:partyId', v: number): void
    (e: 'update:partyName', v: string): void
    (e: 'select', v: any): void
}>()

const keyword = ref('')
const list = ref<any[]>([])
const loading = ref(false)
const resolving = ref(false)

watch(() => props.show, (v) => { if (v) { keyword.value = ''; search() } })

async function search() {
    loading.value = true
    try {
        // 第一步：搜索会员（包含已绑定往来主体信息）
        const res: any = await request.get('erp/counterparty/member_options', { keyword: keyword.value, limit: 30 })
        list.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } catch { list.value = [] }
    finally { loading.value = false }
}

async function selectMember(member: any) {
    resolving.value = true
    try {
        // 第二步：获取已有主体 or 自动创建并绑定
        const res: any = await request.post('erp/counterparty/resolve_contact', {
            member_id: member.member_id,
            name: member.party_name || member.counterparty_name || member.nickname || member.username,
            mobile: member.mobile,
            role_type: props.roleType,
        })
        const party = res?.data || {}
        if (!party.party_id) { uni.showToast({ title: '获取往来主体失败', icon: 'none' }); return }
        emit('update:partyId', party.party_id)
        emit('update:partyName', party.party_name || party.counterparty_name)
        emit('select', party)
        close()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '操作失败', icon: 'none' })
    } finally { resolving.value = false }
}

function close() { emit('update:show', false) }
</script>

<style scoped lang="scss">
.popup-wrap { height: 78vh; display: flex; flex-direction: column; }
.popup-header { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 32rpx 16rpx; }
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.popup-search { padding: 0 24rpx 16rpx; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; gap: 16rpx; box-sizing: border-box; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-empty { padding: 32rpx 0; text-align: center; }
.popup-empty__hint { font-size: 24rpx; color: #94a3b8; display: block; margin-top: 12rpx; }
.party-item {
    padding: 20rpx 12rpx; border-radius: 8rpx; border-bottom: 1rpx solid #f1f5f9;
    &.selected { background: #eff6ff; }
    &:active { background: #f8fafc; }
}
.party-item__main { display: flex; align-items: flex-start; justify-content: space-between; }
.party-item__left { flex: 1; }
.party-item__right { text-align: right; flex-shrink: 0; }
.party-item__name { font-size: 28rpx; font-weight: 600; color: #0f172a; display: block; }
.party-item__member { font-size: 22rpx; color: #94a3b8; display: block; margin-top: 4rpx; }
.party-item__mobile { font-size: 26rpx; color: #64748b; display: block; }
.party-item__mno { font-size: 22rpx; color: #94a3b8; display: block; margin-top: 4rpx; }
.party-item__badge { margin-top: 8rpx; }
.resolving-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 16rpx; }
.resolving-text { font-size: 28rpx; color: #fff; }
</style>
