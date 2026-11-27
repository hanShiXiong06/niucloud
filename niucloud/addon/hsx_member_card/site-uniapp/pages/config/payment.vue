<template>
    <view class="mc-page mc-page--action">
        <view class="mc-content">
            <MemberCardState
                v-if="loading || error"
                :loading="loading"
                :error="error"
                action="重新加载"
                @action="load"
            />
            <template v-else>
                <MemberCardSegmented
                    v-model="tab"
                    :options="[
                        { label: '收款账户', value: 'accounts' },
                        { label: inventoryDirty ? '耗材规则 · 未保存' : '耗材规则', value: 'inventory' }
                    ]"
                />
                <MemberCardNotice v-if="actionError" tone="error" :text="actionError" />
                <template v-if="tab === 'accounts'">
                    <view class="mc-account-summary">
                        <view class="mc-between">
                            <text class="mc-title"
                                >{{ accounts.filter((a) => Number(a.status) === 1).length }} 个可用账户</text
                            >
                            <u-tag :text="isErp ? 'ERP 托管' : '独立运行'" type="primary" size="mini" plain />
                        </view>
                        <view class="mc-sub">{{
                            isErp ? '在 ERP 维护账户，这里选择默认收款账户。' : '由本店自行管理，不依赖 ERP。'
                        }}</view>
                    </view>
                    <MemberCardState v-if="!accounts.length" text="暂无收款账户" />
                    <view v-if="accounts.length" class="mc-card mc-card--flat">
                        <u-cell-group :border="false">
                            <u-cell
                                v-for="(account, index) in accounts"
                                :key="account.id"
                                :title="account.name"
                                :label="
                                    (account.type_name || typeName(account.type)) +
                                    ' · ' +
                                    (Number(account.status) === 1 ? '已启用' : '已停用')
                                "
                                :border="index < accounts.length - 1"
                                :isLink="!isErp"
                                center
                                :titleStyle="{ fontSize: '15px', fontWeight: '500' }"
                                @click="!isErp && edit(account)"
                            >
                                <template #icon
                                    ><view class="mc-cell-icon" :class="'mc-cell-icon--' + account.type"
                                        ><u-icon
                                            :name="
                                                account.type === 'wechat'
                                                    ? 'weixin-fill'
                                                    : account.type === 'alipay'
                                                      ? 'zhifubao'
                                                      : 'rmb-circle'
                                            "
                                            size="23" /></view
                                ></template>
                                <template #value>
                                    <u-tag
                                        v-if="
                                            Number(account.status) === 1 &&
                                            Number(config.default_capital_account_id) === Number(account.id)
                                        "
                                        text="默认"
                                        type="success"
                                        plain
                                        size="mini"
                                    />
                                    <view
                                        v-else-if="Number(account.status) === 1"
                                        class="mc-account-default"
                                        @click.stop="setDefault(account)"
                                        >{{ pendingAccountId === Number(account.id) ? '更新中…' : '设默认' }}</view
                                    >
                                </template>
                            </u-cell>
                        </u-cell-group>
                    </view>
                    <view class="mc-footnote"
                        ><u-icon name="info-circle" color="#8893a3" size="14" /><text
                            >仅记录款项收到哪里，不会自动向客户扣款。</text
                        ></view
                    >
                    <view v-if="!isErp" class="mc-footnote">点击账户可修改名称、停用或删除。历史收款记录不变。</view>
                </template>
                <template v-else>
                    <view class="mc-card">
                        <view class="mc-title">核销时如何处理耗材</view>
                        <view class="mc-sub">会员卡次数与实际用料分别记录</view>
                        <MemberCardNotice
                            v-if="config.inventory_warning"
                            tone="warning"
                            :text="config.inventory_warning"
                        />
                        <u-radio-group
                            :modelValue="inventoryForm.mode"
                            placement="column"
                            @change="chooseInventoryMode"
                        >
                            <u-cell
                                v-for="item in inventoryModes"
                                :key="item.value"
                                :title="item.label"
                                :label="item.description"
                                :disabled="item.value !== 'none' && !inventoryAvailable"
                                :customStyle="{ margin: '0 -15px' }"
                                @click="chooseInventoryMode(item.value)"
                            >
                                <template #icon
                                    ><u-radio
                                        :name="item.value"
                                        :disabled="item.value !== 'none' && !inventoryAvailable"
                                        activeColor="#2868ce"
                                        :customStyle="{ marginRight: '8px' }"
                                /></template>
                            </u-cell>
                        </u-radio-group>
                    </view>
                    <view v-if="inventoryForm.mode !== 'none'" class="mc-card">
                        <view class="mc-section-title">耗材扣减位置</view>
                        <view class="mc-field" @click="picker = 'warehouse'">
                            <text class="mc-label mc-required">仓库</text>
                            <text class="mc-grow">{{ warehouseName || '请选择仓库' }}</text>
                            <u-icon name="arrow-right" size="16" color="#9ba7b7" />
                        </view>
                        <view class="mc-field" @click="openLocation">
                            <text class="mc-label mc-required">具体库位</text>
                            <text class="mc-grow">{{ locationName || '请选择库位' }}</text>
                            <u-icon name="arrow-right" size="16" color="#9ba7b7" />
                        </view>
                        <view class="mc-sub">新核销按此位置扣减。修改设置不会重算已有服务记录。</view>
                    </view>
                    <MemberCardNotice
                        v-if="inventoryDirty"
                        title="规则尚未保存"
                        text="确认仓库与库位后，点击底部保存才会生效。"
                    />
                </template>
            </template>
        </view>
        <MemberCardActionBar v-if="!loading && !error && (tab === 'inventory' || !isErp)" fixed>
            <view class="mc-actionbar__button">
                <MemberCardButton
                    type="primary"
                    :text="tab === 'inventory' ? '保存耗材规则' : '新增收款账户'"
                    :loading="busy"
                    @click="mainAction"
                />
            </view>
        </MemberCardActionBar>
        <MemberCardSheet
            v-model:show="editorVisible"
            :title="editor.id ? '编辑收款账户' : '新增收款账户'"
            subtitle="按实际使用的收款渠道设置"
            :busy="busy"
            height="70vh"
        >
            <u-form :model="editor" labelWidth="90" :labelStyle="{ color: '#53637a', fontSize: '14px' }">
                <u-form-item label="账户名称" prop="name" required borderBottom>
                    <u-input
                        v-model="editor.name"
                        border="none"
                        maxlength="40"
                        placeholder="例如：门店微信"
                        :disabled="busy"
                    />
                </u-form-item>
                <u-form-item label="账户类型" labelPosition="top">
                    <MemberCardSegmented v-model="editor.type" :options="types" :disabled="busy" />
                </u-form-item>
                <u-form-item label="启用账户" borderBottom>
                    <view class="mc-switch-value">
                        <u-switch
                            v-model="editor.status"
                            :activeValue="1"
                            :inactiveValue="0"
                            size="22"
                            :disabled="busy"
                            @change="accountStatusChanged"
                        />
                    </view>
                </u-form-item>
                <u-form-item label="设为默认" borderBottom>
                    <view class="mc-switch-value">
                        <u-switch
                            v-model="editor.is_default"
                            :disabled="busy || Number(editor.status) !== 1"
                            :activeValue="1"
                            :inactiveValue="0"
                            size="22"
                        />
                    </view>
                </u-form-item>
            </u-form>
            <MemberCardCollapse title="更多设置" summary="显示排序">
                <view class="mc-field">
                    <text class="mc-label">排序</text>
                    <view class="mc-field__value">
                        <u-input v-model="editor.sort" type="number" border="none" placeholder="数字越小越靠前" />
                    </view>
                </view>
            </MemberCardCollapse>
            <u-cell
                v-if="editor.id"
                title="删除此账户"
                :border="false"
                :disabled="busy"
                :titleStyle="{ color: '#b95151', fontSize: '13px' }"
                @click="removeEditingAccount"
            />
            <MemberCardNotice v-if="editorError" tone="error" :text="editorError" />
            <template #footer>
                <MemberCardButton type="primary" text="保存账户" :loading="busy" @click="save" />
            </template>
        </MemberCardSheet>
        <MemberCardSheet
            :show="!!picker"
            :title="picker === 'warehouse' ? '选择耗材仓库' : '选择具体库位'"
            height="65vh"
            @update:show="picker = ''"
        >
            <view
                v-for="item in picker === 'warehouse' ? inventoryWarehouses : inventoryLocations"
                :key="item.id"
                class="mc-select"
                @click="chooseLocation(item)"
            >
                <text class="mc-grow">{{ item.name }}</text>
                <u-icon
                    v-if="
                        Number(item.id) ===
                        (picker === 'warehouse' ? inventoryForm.warehouseId : inventoryForm.locationId)
                    "
                    name="checkmark"
                    size="20"
                    color="#536e96"
                />
            </view>
            <MemberCardState
                v-if="!(picker === 'warehouse' ? inventoryWarehouses : inventoryLocations).length"
                text="暂无可选位置，请先在 ERP 维护仓库与库位"
            />
        </MemberCardSheet>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import {
    deleteMemberCardCapitalAccount,
    getMemberCardConfig,
    saveMemberCardCapitalAccount,
    saveMemberCardConfig
} from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardSheet from '../../components/MemberCardSheet.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardSegmented from '../../components/MemberCardSegmented.vue'
import { errorText, accountOptions, markMemberCardChanged } from '../../utils/presentation'
const config = ref<any>({ capital_account_options: [] })
const tab = ref('accounts'),
    picker = ref(''),
    error = ref(''),
    actionError = ref(''),
    editorError = ref('')
const loading = ref(true),
    busy = ref(false),
    editorVisible = ref(false),
    pendingAccountId = ref(0)
const inventoryForm = reactive({ mode: 'none', warehouseId: 0, locationId: 0 })
const savedInventory = ref('')
const inventoryDirty = computed(() => savedInventory.value !== JSON.stringify(inventoryForm))
const inventoryModes = [
    { value: 'none', label: '不管理库存', description: '只记服务次数，不扣耗材库存。' },
    { value: 'auto', label: '自动扣减', description: '库存不足仍可服务，产生缺货提醒。' },
    { value: 'strict', label: '严格库存', description: '库存不足不能核销，不扣会员卡次数。' }
]
const editor = reactive<any>({ id: 0, name: '', type: 'wechat', status: 1, is_default: 0, sort: 0 })
const types = [
    { label: '微信', value: 'wechat' },
    { label: '支付宝', value: 'alipay' },
    { label: '银行卡', value: 'bank' },
    { label: '现金', value: 'cash' },
    { label: '其他', value: 'other' }
]
const accounts = computed(() => accountOptions(config.value, true))
const isErp = computed(() => config.value.finance_provider === 'erp')
const inventoryAvailable = computed(() => Number(config.value.inventory_available) === 1)
const inventoryWarehouses = computed(() => config.value.inventory_warehouses || [])
const selectedWarehouse = computed(() =>
    inventoryWarehouses.value.find((item: any) => Number(item.id) === inventoryForm.warehouseId)
)
const inventoryLocations = computed(() => selectedWarehouse.value?.locations || [])
const warehouseName = computed(() => selectedWarehouse.value?.name || '')
const locationName = computed(
    () => inventoryLocations.value.find((item: any) => Number(item.id) === inventoryForm.locationId)?.name || ''
)
const typeName = (value: string) => types.find((item) => item.value === value)?.label || '其他'
const applyConfig = (data: any, applyInventory = false) => {
    config.value = data || config.value
    // 修改账户不覆盖尚未保存的耗材选择。
    if (applyInventory) {
        inventoryForm.mode = ['none', 'auto', 'strict'].includes(data?.inventory_mode) ? data.inventory_mode : 'none'
        inventoryForm.warehouseId = Number(data?.inventory_warehouse_id || 0)
        inventoryForm.locationId = Number(data?.inventory_location_id || 0)
        savedInventory.value = JSON.stringify(inventoryForm)
    }
}
const load = async () => {
    loading.value = true
    error.value = ''
    try {
        applyConfig(((await getMemberCardConfig()) as any)?.data, true)
    } catch (e) {
        error.value = errorText(e, '设置加载失败，请重试')
    } finally {
        loading.value = false
    }
}
const chooseInventoryMode = (mode: string) => {
    if (busy.value) return
    if (mode !== 'none' && !inventoryAvailable.value) {
        uni.showToast({ title: '需先安装并启用 ERP 库存', icon: 'none' })
        return
    }
    inventoryForm.mode = mode
}
const openLocation = () => {
    if (!inventoryForm.warehouseId) {
        uni.showToast({ title: '请先选择仓库', icon: 'none' })
        return
    }
    picker.value = 'location'
}
const chooseLocation = (item: any) => {
    if (picker.value === 'warehouse') {
        if (inventoryForm.warehouseId !== Number(item.id)) inventoryForm.locationId = 0
        inventoryForm.warehouseId = Number(item.id)
    } else inventoryForm.locationId = Number(item.id)
    picker.value = ''
}
const saveInventory = async () => {
    if (busy.value) return
    if (inventoryForm.mode !== 'none' && (!warehouseName.value || !locationName.value)) {
        actionError.value = '请选择有效的耗材仓库和具体库位'
        return
    }
    busy.value = true
    actionError.value = ''
    try {
        applyConfig(
            (
                (await saveMemberCardConfig({
                    inventory_mode: inventoryForm.mode,
                    inventory_warehouse_id: inventoryForm.warehouseId,
                    inventory_location_id: inventoryForm.locationId
                })) as any
            )?.data,
            true
        )
        markMemberCardChanged()
        uni.showToast({ title: '耗材规则已保存', icon: 'success' })
    } catch (e) {
        actionError.value = errorText(e, '保存失败，请重试')
    } finally {
        busy.value = false
    }
}
const openCreate = () => {
    if (busy.value) return
    Object.assign(editor, {
        id: 0,
        name: '',
        type: 'wechat',
        status: 1,
        is_default: accounts.value.length ? 0 : 1,
        sort: 0
    })
    editorError.value = ''
    editorVisible.value = true
}
const mainAction = () => {
    if (tab.value === 'inventory') void saveInventory()
    else openCreate()
}
const edit = (account: any) => {
    if (busy.value) return
    Object.assign(editor, {
        id: Number(account.id),
        name: account.name,
        type: account.type || 'other',
        status: Number(account.status),
        is_default:
            Number(account.status) === 1 && Number(config.value.default_capital_account_id) === Number(account.id)
                ? 1
                : 0,
        sort: Number(account.sort || 0)
    })
    editorError.value = ''
    editorVisible.value = true
}
const accountStatusChanged = () => {
    if (Number(editor.status) !== 1) editor.is_default = 0
}
const save = async () => {
    if (busy.value) return
    if (!editor.name.trim()) {
        editorError.value = '请填写账户名称'
        return
    }
    busy.value = true
    editorError.value = ''
    try {
        applyConfig(
            (
                (await saveMemberCardCapitalAccount({
                    ...editor,
                    name: editor.name.trim(),
                    is_default: Number(editor.status) === 1 ? editor.is_default : 0
                })) as any
            )?.data
        )
        markMemberCardChanged()
        editorVisible.value = false
        uni.showToast({ title: '账户已保存', icon: 'success' })
    } catch (e) {
        editorError.value = errorText(e, '账户保存失败，请刷新核对后再试')
    } finally {
        busy.value = false
    }
}
const setDefault = async (account: any) => {
    if (busy.value) return
    busy.value = true
    pendingAccountId.value = Number(account.id)
    actionError.value = ''
    try {
        applyConfig(((await saveMemberCardConfig({ default_capital_account_id: Number(account.id) })) as any)?.data)
        markMemberCardChanged()
        uni.showToast({ title: '默认账户已更新', icon: 'success' })
    } catch (e) {
        actionError.value = errorText(e, '更新默认账户失败')
    } finally {
        busy.value = false
        pendingAccountId.value = 0
    }
}
const remove = async (account: any) => {
    if (busy.value) return
    busy.value = true
    try {
        const modal = await uni.showModal({
            title: '删除收款账户',
            content: '删除“' + account.name + '”后，新开卡将不能再选择它；已有订单的收款记录保留。',
            confirmText: '确认删除',
            confirmColor: '#ac3939'
        })
        if (!modal.confirm) return
        applyConfig(((await deleteMemberCardCapitalAccount(Number(account.id))) as any)?.data)
        markMemberCardChanged()
        if (editorVisible.value && Number(editor.id) === Number(account.id)) editorVisible.value = false
        uni.showToast({ title: '账户已删除', icon: 'success' })
    } catch (e) {
        if (editorVisible.value) editorError.value = errorText(e, '删除失败，请重试')
        else actionError.value = errorText(e, '删除失败，请重试')
    } finally {
        busy.value = false
    }
}
const removeEditingAccount = () => {
    const account = accounts.value.find((item) => Number(item.id) === Number(editor.id))
    if (account) void remove(account)
}
onLoad(load)
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
