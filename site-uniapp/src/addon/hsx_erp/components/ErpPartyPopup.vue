<!--
  ErpPartyPopup - 往来单位选择弹窗

  用法：
    <ErpPartyPopup
      v-model:show="showPartyPicker"
      role-type="supplier"          // supplier | customer | all
      v-model:party-id="form.party_id"
      v-model:party-name="form.party_name"
      @select="onPartySelected"     // { id, party_name, contact_mobile, m_no }
    />
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="popup-wrap">
            <view class="popup-header">
                <text class="popup-title">{{ title }}</text>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <view class="popup-search">
                <u-search
                    v-model="keyword"
                    :placeholder="'搜索' + typeLabel + '名称/手机'"
                    :showAction="false"
                    bgColor="#f1f5f9"
                    height="34"
                    @search="search"
                    @clear="search"
                />
            </view>

            <scroll-view scroll-y class="popup-list">
                <view v-if="loading" class="popup-loading">
                    <u-loading-icon size="24" />
                </view>
                <template v-else>
                    <view
                        v-for="item in list"
                        :key="item.id"
                        class="party-item"
                        :class="{ selected: item.id === partyId }"
                        @click="select(item)"
                    >
                        <view class="party-item__main">
                            <text class="party-item__name">{{ item.party_name }}</text>
                            <u-icon v-if="item.id === partyId" name="checkmark-circle-fill" color="#3b6ef5" size="20" />
                        </view>
                        <text class="party-item__sub" v-if="item.contact_mobile">{{ item.contact_mobile }}</text>
                        <text class="party-item__sub" v-if="item.m_no"> · M号 {{ item.m_no }}</text>
                    </view>
                    <view class="popup-empty" v-if="!list.length && !loading">
                        <u-empty mode="search" text="暂无结果" :image-size="60" />
                    </view>
                </template>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import request from '@/utils/request'

const props = withDefaults(defineProps<{
    show: boolean
    roleType?: 'supplier' | 'customer' | 'all'
    partyId?: number
    partyName?: string
}>(), {
    show: false,
    roleType: 'all',
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

const typeLabel = computed(() => ({ supplier: '供应商', customer: '客户', all: '往来单位' }[props.roleType] || '往来单位'))
const title = computed(() => `选择${typeLabel.value}`)

// 打开时自动搜索
watch(() => props.show, (v) => { if (v) { keyword.value = ''; search() } })

async function search() {
    loading.value = true
    try {
        const params: any = { keyword: keyword.value, limit: 30 }
        if (props.roleType !== 'all') params.role_type = props.roleType
        const res: any = await request.get('erp/counterparty/options', params)
        list.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } catch {
        list.value = []
    } finally {
        loading.value = false
    }
}

function select(item: any) {
    emit('update:partyId', item.id)
    emit('update:partyName', item.party_name)
    emit('select', item)
    close()
}

function close() {
    emit('update:show', false)
}
</script>

<style scoped lang="scss">
.popup-wrap {
    height: 75vh;
    display: flex;
    flex-direction: column;
    padding: 0 0 env(safe-area-inset-bottom);
}
.popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 32rpx 16rpx;
}
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.popup-search { padding: 0 24rpx 16rpx; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-empty { padding: 32rpx 0; }
.party-item {
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f1f5f9;
    &.selected { background: #eff6ff; border-radius: 8rpx; padding: 20rpx 12rpx; }
}
.party-item__main { display: flex; align-items: center; justify-content: space-between; }
.party-item__name { font-size: 28rpx; color: #0f172a; }
.party-item__sub { font-size: 24rpx; color: #64748b; display: block; margin-top: 4rpx; }
</style>
