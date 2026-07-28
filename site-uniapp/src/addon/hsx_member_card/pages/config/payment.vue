<template>
    <view class="min-h-screen bg-[#f5f6f8] px-[24rpx] pb-[170rpx] pt-[20rpx] text-[#344054]">
        <view class="rounded-[24rpx] bg-white p-[22rpx]">
            <view class="flex items-center gap-[15rpx]">
                <view class="flex h-[62rpx] w-[62rpx] items-center justify-center rounded-[18rpx] bg-[#f1efff]">
                    <u-icon name="grid" color="#6d4aff" size="22" />
                </view>
                <view class="min-w-0 flex-1">
                    <text class="block text-[29rpx] font-semibold">核销耗材库存</text>
                    <text class="mt-[3rpx] block text-[21rpx] text-[#8a96a8]">会员卡次数与实际用料分别记录</text>
                </view>
                <text class="rounded-full px-[12rpx] py-[5rpx] text-[20rpx]" :class="inventoryAvailable ? 'bg-[#ecfdf3] text-[#16a34a]' : 'bg-[#f2f4f7] text-[#667085]'">
                    {{ inventoryAvailable ? 'ERP 已接入' : '独立运行' }}
                </text>
            </view>

            <view class="mt-[18rpx] flex flex-col gap-[12rpx]">
                <view
                    v-for="item in inventoryModes"
                    :key="item.value"
                    class="flex items-start gap-[14rpx] rounded-[18rpx] border px-[18rpx] py-[17rpx]"
                    :class="[
                        inventoryForm.mode === item.value ? 'border-[#93c5fd] bg-[#f5f9ff]' : 'border-[#e8ecf1] bg-[#fafbfc]',
                        item.value !== 'none' && !inventoryAvailable ? 'opacity-50' : ''
                    ]"
                    @click="chooseInventoryMode(item.value)"
                >
                    <view class="mt-[3rpx] flex h-[32rpx] w-[32rpx] flex-none items-center justify-center rounded-full border" :class="inventoryForm.mode === item.value ? 'border-[#2468f2]' : 'border-[#cbd5e1]'">
                        <view v-if="inventoryForm.mode === item.value" class="h-[18rpx] w-[18rpx] rounded-full bg-[#2468f2]" />
                    </view>
                    <view class="min-w-0 flex-1">
                        <view class="flex items-center gap-[8rpx]">
                            <text class="text-[26rpx] font-semibold text-[#344054]">{{ item.label }}</text>
                            <text v-if="item.recommended" class="rounded-full bg-[#eaf2ff] px-[9rpx] py-[2rpx] text-[18rpx] text-[#2468f2]">推荐</text>
                        </view>
                        <text class="mt-[4rpx] block text-[21rpx] leading-[1.55] text-[#7b8798]">{{ item.description }}</text>
                    </view>
                </view>
            </view>

            <view v-if="inventoryForm.mode !== 'none'" class="mt-[18rpx] rounded-[18rpx] bg-[#f8fafc] p-[16rpx]">
                <text class="mb-[12rpx] block text-[23rpx] font-semibold">扣减仓库</text>
                <scroll-view scroll-x class="whitespace-nowrap">
                    <view class="inline-flex gap-[10rpx]">
                        <text
                            v-for="warehouse in inventoryWarehouses"
                            :key="warehouse.id"
                            class="rounded-[13rpx] px-[18rpx] py-[11rpx] text-[22rpx]"
                            :class="Number(inventoryForm.warehouseId) === Number(warehouse.id) ? 'bg-[#2468f2] text-white' : 'bg-white text-[#64748b]'"
                            @click="selectWarehouse(warehouse)"
                        >{{ warehouse.name }}</text>
                    </view>
                </scroll-view>
                <text class="mb-[12rpx] mt-[18rpx] block text-[23rpx] font-semibold">具体库位</text>
                <view class="flex flex-wrap gap-[10rpx]">
                    <text
                        v-for="location in inventoryLocations"
                        :key="location.id"
                        class="rounded-[13rpx] px-[18rpx] py-[11rpx] text-[22rpx]"
                        :class="Number(inventoryForm.locationId) === Number(location.id) ? 'bg-[#2468f2] text-white' : 'bg-white text-[#64748b]'"
                        @click="inventoryForm.locationId = Number(location.id)"
                    >{{ location.name }}</text>
                </view>
            </view>
            <view class="mt-[18rpx]">
                <MemberCardButton type="primary" text="保存耗材规则" :loading="inventorySaving" @click="saveInventory" class="!h-[76rpx] !rounded-[16rpx] !text-[25rpx] !font-semibold" />
            </view>
        </view>

        <view class="mt-[18rpx] rounded-[24rpx] bg-white p-[22rpx]">
            <view class="flex items-center gap-[15rpx]">
                <view class="flex h-[62rpx] w-[62rpx] items-center justify-center rounded-[18rpx] bg-[#ecfeff]">
                    <u-icon name="rmb-circle" color="#0891b2" size="23" />
                </view>
                <view class="min-w-0 flex-1">
                    <text class="block text-[29rpx] font-semibold">收款账户</text>
                    <text class="mt-[3rpx] block text-[21rpx] text-[#8a96a8]">{{ config.finance_provider_name || '会员卡独立收款' }}</text>
                </view>
                <text class="rounded-full px-[12rpx] py-[5rpx] text-[20rpx]" :class="isErp ? 'bg-[#ecfdf3] text-[#16a34a]' : 'bg-[#edf4ff] text-[#2468f2]'">{{ isErp ? 'ERP 托管' : '独立运行' }}</text>
            </view>
            <view class="mt-[18rpx] rounded-[16rpx] bg-[#f8fafc] px-[16rpx] py-[14rpx] text-[21rpx] leading-[1.6] text-[#667085]">
                {{ isErp ? '资金账户由 ERP 统一维护，本页面用于查看和选择默认到账账户。' : '账户保存在当前站点配置中，店主可直接维护，无需 PC 管理员介入。' }}
            </view>
        </view>

        <view class="mb-[12rpx] mt-[24rpx] flex items-center justify-between px-[4rpx]">
            <text class="text-[28rpx] font-semibold">可用账户</text>
            <text class="text-[21rpx] text-[#98a2b3]">{{ accounts.length }} 个</text>
        </view>
        <view v-if="!accounts.length" class="rounded-[22rpx] bg-white py-[80rpx]">
            <u-empty text="暂无收款账户" mode="list" />
        </view>
        <view v-for="account in accounts" :key="account.id" class="mb-[14rpx] rounded-[22rpx] bg-white px-[20rpx] py-[18rpx]">
            <view class="flex items-center gap-[14rpx]">
                <view class="flex h-[58rpx] w-[58rpx] flex-none items-center justify-center rounded-[16rpx]" :class="Number(account.status) === 1 ? 'bg-[#edf4ff]' : 'bg-[#f2f4f7]'">
                    <u-icon name="wallet" :color="Number(account.status) === 1 ? '#2468f2' : '#98a2b3'" size="20" />
                </view>
                <view class="min-w-0 flex-1">
                    <view class="flex items-center gap-[8rpx]">
                        <text class="truncate text-[27rpx] font-semibold">{{ account.name }}</text>
                        <text v-if="Number(account.is_default) === 1 || Number(config.default_capital_account_id) === Number(account.id)" class="rounded-full bg-[#ecfdf3] px-[9rpx] py-[3rpx] text-[18rpx] text-[#16a34a]">默认</text>
                    </view>
                    <text class="mt-[4rpx] block text-[21rpx] text-[#8a96a8]">{{ account.type_name || typeName(account.type) }} · {{ Number(account.status) === 1 ? '已启用' : '已停用' }}</text>
                </view>
                <view v-if="!isErp" class="flex items-center gap-[20rpx] text-[23rpx]">
                    <text class="text-[#475569]" @click="edit(account)">编辑</text>
                    <text class="text-[#dc2626]" @click="remove(account)">删除</text>
                </view>
            </view>
            <view v-if="Number(account.status) === 1 && Number(config.default_capital_account_id) !== Number(account.id)" class="mt-[14rpx] flex justify-end border-t border-[#f0f2f5] pt-[13rpx]">
                <text class="text-[22rpx] font-medium text-[#2468f2]" @click="setDefault(account)">设为默认账户</text>
            </view>
        </view>

        <view v-if="!isErp" class="fixed bottom-0 left-0 right-0 z-20 border-t border-[#e8ecf1] bg-white px-[24rpx] pt-[14rpx]" :style="{ paddingBottom: 'calc(14rpx + env(safe-area-inset-bottom))' }">
            <MemberCardButton type="primary" icon="plus" text="新增收款账户" @click="openCreate" class="!h-[86rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold" />
        </view>

        <u-popup :show="editorVisible" mode="bottom" round="24" :safe-area-inset-bottom="true" @close="editorVisible = false">
            <view class="px-[28rpx] pb-[38rpx] pt-[28rpx]">
                <view class="mb-[22rpx] flex items-center justify-between">
                    <text class="text-[31rpx] font-semibold">{{ editor.id ? '编辑收款账户' : '新增收款账户' }}</text>
                    <view class="flex h-[54rpx] w-[54rpx] items-center justify-center rounded-full bg-[#f2f4f7]" @click="editorVisible = false"><u-icon name="close" color="#64748b" size="18" /></view>
                </view>
                <view class="mb-[14rpx] rounded-[16rpx] bg-[#f5f7fa] px-[16rpx] py-[4rpx]">
                    <view class="flex min-h-[88rpx] items-center border-b border-[#e8ecf1]"><text class="w-[150rpx] text-[25rpx]">账户名称</text><u-input v-model="editor.name" border="none" placeholder="例如：门店微信" class="flex-1 text-[25rpx]" /></view>
                    <view class="flex min-h-[88rpx] items-center"><text class="w-[150rpx] text-[25rpx]">账户类型</text><view class="flex flex-1 flex-wrap gap-[10rpx]"><text v-for="type in types" :key="type.value" class="rounded-[12rpx] px-[15rpx] py-[9rpx] text-[22rpx]" :class="editor.type === type.value ? 'bg-[#2468f2] text-white' : 'bg-white text-[#64748b]'" @click="editor.type = type.value">{{ type.label }}</text></view></view>
                </view>
                <view class="mb-[22rpx] rounded-[16rpx] bg-[#f5f7fa] px-[16rpx]">
                    <view class="flex min-h-[82rpx] items-center justify-between border-b border-[#e8ecf1]"><text class="text-[25rpx]">启用账户</text><u-switch v-model="editor.status" :activeValue="1" :inactiveValue="0" size="22" /></view>
                    <view class="flex min-h-[82rpx] items-center justify-between"><text class="text-[25rpx]">设为默认</text><u-switch v-model="editor.is_default" :activeValue="1" :inactiveValue="0" size="22" /></view>
                </view>
                <MemberCardButton type="primary" text="保存账户" :loading="saving" @click="save" class="!h-[86rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold" />
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { deleteMemberCardCapitalAccount, getMemberCardConfig, saveMemberCardCapitalAccount, saveMemberCardConfig } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const config = ref<any>({ capital_account_options: [] })
const editorVisible = ref(false)
const saving = ref(false)
const inventorySaving = ref(false)
const inventoryForm = reactive({ mode: 'none', warehouseId: 0, locationId: 0 })
const inventoryModes = [
    { value: 'none', label: '不管理库存', description: '只完成会员卡核销，不记录耗材库存。' },
    { value: 'auto', label: '自动管理', description: '自动扣库存；不足仍可核销并形成缺货提醒。', recommended: true },
    { value: 'strict', label: '严格库存', description: '库存不足禁止核销，适合管理规范的团队。' },
]
const editor = reactive<any>({ id: 0, name: '', type: 'wechat', status: 1, is_default: 0, sort: 0 })
const types = [{ label: '微信', value: 'wechat' }, { label: '支付宝', value: 'alipay' }, { label: '银行卡', value: 'bank' }, { label: '现金', value: 'cash' }, { label: '其他', value: 'other' }]
const accounts = computed(() => config.value.capital_account_options || [])
const isErp = computed(() => config.value.finance_provider === 'erp')
const inventoryAvailable = computed(() => Number(config.value.inventory_available || 0) === 1)
const inventoryWarehouses = computed(() => config.value.inventory_warehouses || [])
const inventoryLocations = computed(() => inventoryWarehouses.value.find((item: any) => Number(item.id) === Number(inventoryForm.warehouseId))?.locations || [])
const typeName = (value: string) => types.find(item => item.value === value)?.label || '其他'
const applyConfig = (data: any) => {
    config.value = data || config.value
    inventoryForm.mode = ['none', 'auto', 'strict'].includes(data?.inventory_mode) ? data.inventory_mode : 'none'
    inventoryForm.warehouseId = Number(data?.inventory_warehouse_id || 0)
    inventoryForm.locationId = Number(data?.inventory_location_id || 0)
}
const load = async () => { applyConfig(((await getMemberCardConfig()) as any)?.data || config.value) }
const chooseInventoryMode = (mode: string) => {
    if (mode !== 'none' && !inventoryAvailable.value) return uni.showToast({ title: '请先安装并启用 ERP', icon: 'none' })
    inventoryForm.mode = mode
    if (mode !== 'none' && !inventoryForm.warehouseId && inventoryWarehouses.value.length) selectWarehouse(inventoryWarehouses.value[0])
}
const selectWarehouse = (warehouse: any) => {
    inventoryForm.warehouseId = Number(warehouse.id || 0)
    inventoryForm.locationId = Number(warehouse.locations?.[0]?.id || 0)
}
const saveInventory = async () => {
    if (inventoryForm.mode !== 'none' && (!inventoryForm.warehouseId || !inventoryForm.locationId)) return uni.showToast({ title: '请选择耗材仓库和库位', icon: 'none' })
    inventorySaving.value = true
    try {
        applyConfig(((await saveMemberCardConfig({
            inventory_mode: inventoryForm.mode,
            inventory_warehouse_id: inventoryForm.warehouseId,
            inventory_location_id: inventoryForm.locationId,
        })) as any)?.data || config.value)
        uni.showToast({ title: '耗材规则已保存', icon: 'success' })
    } finally { inventorySaving.value = false }
}
const openCreate = () => { Object.assign(editor, { id: 0, name: '', type: 'wechat', status: 1, is_default: accounts.value.length ? 0 : 1, sort: 0 }); editorVisible.value = true }
const edit = (account: any) => { Object.assign(editor, { id: Number(account.id), name: account.name, type: account.type || 'other', status: Number(account.status), is_default: Number(account.is_default), sort: Number(account.sort || 0) }); editorVisible.value = true }
const save = async () => {
    if (!editor.name.trim()) return uni.showToast({ title: '请填写账户名称', icon: 'none' })
    saving.value = true
    try { applyConfig(((await saveMemberCardCapitalAccount({ ...editor })) as any)?.data || config.value); editorVisible.value = false; uni.showToast({ title: '账户已保存', icon: 'success' }) }
    finally { saving.value = false }
}
const setDefault = async (account: any) => { applyConfig(((await saveMemberCardConfig({ default_capital_account_id: Number(account.id) })) as any)?.data || config.value); uni.showToast({ title: '默认账户已更新', icon: 'success' }) }
const remove = async (account: any) => {
    const modal = await uni.showModal({ title: '删除收款账户', content: `确认删除“${account.name}”？`, confirmColor: '#dc2626' })
    if (!modal.confirm) return
    applyConfig(((await deleteMemberCardCapitalAccount(Number(account.id))) as any)?.data || config.value)
    uni.showToast({ title: '账户已删除', icon: 'success' })
}
onShow(load)
</script>
