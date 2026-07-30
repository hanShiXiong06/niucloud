<template>
    <view class="erp-page">
        <ErpPageHeader title="采购开单" />
        <scroll-view scroll-y style="height:calc(100vh - 100rpx)">
            <view class="form-wrap">
                <view class="meta-source-card" :class="goodsMeta.source === 'phone_shop' ? 'shop' : 'erp'">
                    <view class="meta-source__head">
                        <u-icon :name="goodsMeta.source === 'phone_shop' ? 'shopping-cart' : 'file-text'" :color="goodsMeta.source === 'phone_shop' ? '#3b6ef5' : '#64748b'" size="18" />
                        <text class="meta-source__title">{{ goodsMeta.source_label || '商品资料' }}</text>
                    </view>
                    <text class="meta-source__desc">{{ goodsMetaTip }}</text>
                </view>

                <view class="purchase-flow-card" :class="`purchase-flow-card--${purchaseEntryMode}`">
                    <view class="purchase-flow-card__icon">
                        <u-icon :name="purchaseModeMeta.icon" color="#2563eb" size="20" />
                    </view>
                    <view class="purchase-flow-card__content">
                        <view class="purchase-flow-card__title-row">
                            <text class="purchase-flow-card__title">{{ purchaseModeMeta.title }}</text>
                            <text class="purchase-flow-card__tag">系统配置</text>
                        </view>
                        <text class="purchase-flow-card__desc">{{ purchaseModeMeta.description }}</text>
                    </view>
                </view>

                <!-- 供应商 -->
                <view class="form-section">
                    <view class="form-row" @click="showPartyPicker = true">
                        <text class="form-label required">供应商</text>
                        <view class="form-input" :class="{ 'form-input--on': form.party_id }">
                            <text :class="form.party_id ? 'input-text' : 'input-placeholder'">
                                {{ erpPartyDisplayName(form, '点击选择供应商') }}
                            </text>
                            <view v-if="form.party_id" class="inline-clear" @click.stop="clearParty">
                                <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                            </view>
                            <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                        </view>
                    </view>
                    <view class="form-row">
                        <text class="form-label">M号</text>
                        <u-input v-model="form.m_no" placeholder="选填：业务编号/M号" :customStyle="inputStyle" />
                    </view>
                </view>

                <view class="form-section purchase-mode">
                    <view class="form-section__head">
                        <view>
                            <text class="form-section__title">入库方式</text>
                            <text class="mode-desc">{{ form.item_type_mode === 'device' ? '一机一码，逐台追踪' : '壳膜配件，按数量入库' }}</text>
                        </view>
                    </view>
                    <view class="mode-tabs">
                        <view class="mode-tab" :class="{ active: form.item_type_mode === 'device' }" @click="changeItemTypeMode('device')">
                            <u-icon name="phone" :color="form.item_type_mode === 'device' ? '#2563eb' : '#64748b'" size="19" />
                            <text>二手机 / 设备</text>
                        </view>
                        <view class="mode-tab" :class="{ active: form.item_type_mode === 'standard' }" @click="changeItemTypeMode('standard')">
                            <u-icon name="grid" :color="form.item_type_mode === 'standard' ? '#2563eb' : '#64748b'" size="19" />
                            <text>标品 / 配件</text>
                        </view>
                    </view>
                </view>

                <!-- 设备明细 -->
                <view v-if="form.item_type_mode === 'device'" class="form-section">
                    <view class="form-section__head">
                        <view>
                            <text class="form-section__title">设备明细（{{ form.items.length }} 台）</text>
                            <text v-if="form.items.length" class="gesture-tip">左滑设备卡可管理</text>
                        </view>
                        <view class="form-section__actions">
                            <u-button size="small" plain type="primary" icon="scan" @click="scanAddDevice">扫码录入</u-button>
                            <u-button size="small" type="primary" icon="plus" @click="addDevice">添加设备</u-button>
                        </view>
                    </view>
                    <ErpSwipeActionItem
                        v-for="(item, idx) in form.items"
                        :key="idx"
                        class="device-swipe"
                        :name="idx"
                        :open="swipeOpenIndex === idx"
                        :actions="deviceSwipeActions"
                        @update:open="value => setSwipeOpen(idx, value)"
                        @action="action => handleItemSwipeAction(action, idx)"
                    >
                    <view class="device-form-card" :class="{ 'device-form-card--done': deviceCoreComplete(item) }">
                        <view class="device-form__head" @click="toggleDevice(idx)">
                            <view class="device-form__identity">
                                <view class="device-form__number">{{ String(idx + 1).padStart(2, '0') }}</view>
                                <view class="device-form__headline">
                                    <view class="device-form__title-row">
                                        <text class="device-form__index">{{ item.model || `设备 ${idx + 1}` }}</text>
                                        <text class="device-form__status" :class="{ complete: deviceCoreComplete(item) }">
                                            {{ deviceCoreComplete(item) ? '已完善' : '待填写' }}
                                        </text>
                                    </view>
                                    <text class="device-form__summary">{{ deviceSummary(item) }}</text>
                                </view>
                            </view>
                            <view class="device-form__tools">
                                <u-icon :name="expandedDeviceIndex === idx ? 'arrow-up' : 'arrow-down'" color="#94a3b8" size="16" />
                            </view>
                        </view>

                        <view v-show="expandedDeviceIndex === idx" class="device-form__body">
                            <view class="device-subtitle">
                                <text>采购信息</text>
                                <text>必填</text>
                            </view>
                            <view class="form-row">
                                <text class="form-label required">IMEI</text>
                                <u-input v-model="item.imei" placeholder="扫描或手输 IMEI" :customStyle="inputStyle" />
                                <view class="inline-scan" @click="scanDeviceImei(idx)">
                                    <u-icon name="scan" color="#3b6ef5" size="20" />
                                </view>
                            </view>
                            <ErpCatalogProductPopup
                                v-model="item.catalog_product_id"
                                :selected-label="catalogDisplayLabel(item)"
                                :category-path="item.category_path"
                                label="分类"
                                placeholder="先选品类，再选择品牌、系列和型号"
                                layout="horizontal"
                                :embedded="true"
                                :clearable="true"
                                @change="payload => onCatalogProductChange(idx, payload)"
                                @clear="clearItemCatalogProduct(idx)"
                            />
                            <view class="form-row">
                                <text class="form-label required">设备名称</text>
                                <u-input
                                    v-model="item.model"
                                    :disabled="!item.category_path"
                                    :placeholder="item.category_path ? '输入设备名称' : '请先选择商品品类'"
                                    :customStyle="inputStyle"
                                    @input="item._model_manual = item.model !== item._auto_model"
                                />
                            </view>
                            <view class="form-row" @click="openItemWarehouse(idx)">
                                <text class="form-label required">入库位置</text>
                                <view class="form-input" :class="{ 'form-input--on': item.warehouse_id }">
                                    <text :class="item.warehouse_name ? 'input-text' : 'input-placeholder'">
                                        {{ itemWarehouseText(item) || '选择仓库与库位' }}
                                    </text>
                                    <view v-if="item.warehouse_id" class="inline-clear" @click.stop="clearItemWarehouse(idx)">
                                        <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                                    </view>
                                    <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                                </view>
                            </view>
                            <view class="form-row">
                                <text class="form-label required">采购成本</text>
                                <u-input v-model="item.purchase_cost" type="number" placeholder="0.00" :customStyle="inputStyle" />
                            </view>

                            <view v-if="purchaseEntryMode !== 'collaborative'" class="device-material">
                                <view class="device-material__head" @click="item._material_open = !item._material_open">
                                    <view>
                                        <view class="device-material__title-row">
                                            <text class="device-material__title">销售资料</text>
                                            <text class="device-material__optional">{{ purchaseEntryMode === 'complete' ? '本次完成' : '选填' }}</text>
                                        </view>
                                        <text class="device-material__summary">{{ materialSummary(item) }}</text>
                                    </view>
                                    <u-icon :name="item._material_open ? 'arrow-up' : 'arrow-down'" color="#94a3b8" size="15" />
                                </view>
                                <view v-show="item._material_open" class="device-material__body">
                                    <view class="form-row" @click="openSpecPicker(idx)">
                                        <text class="form-label">商品规格</text>
                                        <view class="form-input" :class="{ 'form-input--on': specSummary(item) }">
                                            <text :class="specSummary(item) ? 'input-text' : 'input-placeholder'">
                                                {{ specSummary(item) || (item.category_path ? '内存 / 颜色 / 成色等' : '请先选择品类') }}
                                            </text>
                                            <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                                        </view>
                                    </view>
                                    <view class="form-row">
                                        <text class="form-label">销售价格</text>
                                        <u-input v-model="item.retail_price" type="number" placeholder="0.00（选填）" :customStyle="inputStyle" />
                                    </view>
                                    <template v-if="purchaseEntryMode === 'complete'">
                                        <ErpVoucherUploader v-model="item.image_urls" title="商品图片" hint="可直接作为库存和商城销售资料" add-text="上传图片" :max-count="9" />
                                        <view class="purchase-video">
                                            <view>
                                                <text class="purchase-video__title">展示视频</text>
                                                <text class="purchase-video__hint">选填，最多上传 1 个</text>
                                            </view>
                                            <upload-video v-model="item.video_url" :max-count="1" />
                                        </view>
                                    </template>
                                </view>
                            </view>

                            <view v-else class="handoff-tip">
                                <u-icon name="account-fill" color="#2563eb" size="19" />
                                <view>
                                    <text class="handoff-tip__title">入库后自动进入协作流程</text>
                                    <text class="handoff-tip__desc">采购人员无需填写图片和销售价格，后续由拍摄、销售定价岗位承接。</text>
                                </view>
                            </view>

                            <view class="device-complete-action" @click="finishDevice(idx)">
                                <u-icon :name="deviceCoreComplete(item) ? 'checkmark-circle-fill' : 'info-circle'" :color="deviceCoreComplete(item) ? '#16a34a' : '#94a3b8'" size="18" />
                                <text>{{ deviceCoreComplete(item) ? '完成本台并收起' : '填写完必填项后可收起' }}</text>
                            </view>
                        </view>
                    </view>
                    </ErpSwipeActionItem>
                    <view class="add-hint" v-if="!form.items.length">点击「添加设备」开始录入</view>
                </view>
                <view v-else class="form-section">
                    <view class="form-section__head">
                        <view>
                            <text class="form-section__title">标品明细（{{ form.items.length }} 项）</text>
                            <text class="mode-desc">同款商品一次填写数量，无需录入串号</text>
                            <text v-if="form.items.length" class="gesture-tip">左滑商品行可管理</text>
                        </view>
                        <view class="form-section__actions">
                            <u-button size="small" type="primary" icon="plus" @click="addStandardItem">添加商品</u-button>
                        </view>
                    </view>
                    <ErpSwipeActionItem
                        v-for="(item, idx) in form.items"
                        :key="idx"
                        class="device-swipe"
                        :name="idx"
                        :open="swipeOpenIndex === idx"
                        :actions="deviceSwipeActions"
                        @update:open="value => setSwipeOpen(idx, value)"
                        @action="action => handleItemSwipeAction(action, idx)"
                    >
                    <view class="device-form-card standard-form-card">
                        <view class="device-form__head">
                            <text class="device-form__index">{{ item.quantity_product_id ? '补货' : '标品' }} {{ idx + 1 }}</text>
                        </view>
                        <view class="standard-product-picker">
                            <ErpQuantityProductPopup
                                v-model="item.quantity_product_id"
                                :selected="item.quantity_product_snapshot"
                                @change="product => onStandardProductChange(item, product)"
                            />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">采购数量</text>
                            <u-input v-model="item.quantity" type="number" placeholder="1" :customStyle="inputStyle" />
                            <view class="unit-select" @click="openUnitPicker(idx)">
                                <text>{{ item.unit || '件' }}</text>
                                <u-icon name="arrow-down" size="12" color="#64748b" />
                            </view>
                        </view>
                        <view class="form-row">
                            <text class="form-label required">采购总价</text>
                            <u-input v-model="item.line_total" type="number" placeholder="0.00" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row" @click="openItemWarehouse(idx)">
                            <text class="form-label required">入库位置</text>
                            <view class="form-input" :class="{ 'form-input--on': item.warehouse_id }">
                                <text :class="item.warehouse_name ? 'input-text' : 'input-placeholder'">{{ itemWarehouseText(item) || '选择配件仓或新机仓' }}</text>
                                <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                            </view>
                        </view>
                        <view class="standard-total">
                            <text>折算单价</text>
                            <text class="standard-total__money">¥{{ standardUnitCostText(item) }} / {{ item.unit || '件' }}</text>
                        </view>
                    </view>
                    </ErpSwipeActionItem>
                </view>

                <!-- 备注 -->
                <view class="form-section">
                    <view class="form-row">
                        <text class="form-label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选备注" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 结算区域（ErpSettleBar 组件） -->
                <ErpSettleBar
                    v-model:settle-mode="form.settle_mode"
                    v-model:amount="form.paid_amount"
                    v-model:account-id="form.capital_account_id"
                    :total="totalCost"
                    :accounts="accounts"
                    label-cash="本次付款"
                />
                <view v-if="form.settle_mode === 'cash'" class="form-section">
                    <ErpVoucherUploader v-model="form.voucher_urls" title="付款凭证" @uploading="voucherUploading = $event" />
                </view>

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="float-bar">
            <u-button @click="goBack" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">
                确认开单 ¥{{ money(totalCost) }}
            </u-button>
        </view>

        <!-- 组件弹窗 -->
        <ErpPartyPopup
            v-model:show="showPartyPicker"
            role-type="supplier"
            v-model:party-id="form.party_id"
            v-model:party-name="form.party_name"
            v-model:member-name="form.member_name"
        />

        <ErpWarehousePopup
            v-model:show="showWhPicker"
            v-model:warehouse-id="form.warehouse_id"
            v-model:warehouse-name="form.warehouse_name"
            v-model:location-id="form.location_id"
            v-model:location-name="form.location_name"
            @change="onDefaultWarehouseChange"
        />

        <ErpWarehousePopup
            v-model:show="showItemWhPicker"
            v-model:warehouse-id="itemWarehousePicker.warehouse_id"
            v-model:warehouse-name="itemWarehousePicker.warehouse_name"
            v-model:location-id="itemWarehousePicker.location_id"
            v-model:location-name="itemWarehousePicker.location_name"
            :filter-types="form.item_type_mode === 'standard' ? ['accessory', 'new_device'] : []"
            @change="onItemWarehouseChange"
        />

        <u-action-sheet
            :show="showUnitPicker"
            :actions="unitActions"
            title="选择计量单位"
            @select="selectUnit"
            @close="showUnitPicker = false"
        />

        <ErpGoodsSpecPopup
            v-model:show="showSpecPicker"
            :item="activeSpecItem"
            :meta="activeSpecMeta"
            @confirm="onSpecConfirm"
            @refresh="refreshActiveSpecMeta"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { createMobileErpPurchase, getMobileCapitalAccounts, getMobileErpConfig, getMobileErpGoodsMeta } from '@/addon/hsx_erp/api/erp'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpSettleBar from '@/addon/hsx_erp/components/ErpSettleBar.vue'
import ErpGoodsSpecPopup from '@/addon/hsx_erp/components/ErpGoodsSpecPopup.vue'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import ErpCatalogProductPopup from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpSwipeActionItem from '@/addon/hsx_erp/components/ErpSwipeActionItem.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import ErpQuantityProductPopup from '@/addon/hsx_erp/components/ErpQuantityProductPopup.vue'
import { erpPartyDisplayName } from '@/addon/hsx_erp/hooks/useErpPartyText'



const submitting = ref(false)
const goBack = () => uni.navigateBack()
const accounts = ref<any[]>([])
const goodsMeta = ref<any>({})
const purchaseEntryMode = ref<'quick' | 'complete' | 'collaborative'>('quick')
const expandedDeviceIndex = ref(0)
const swipeOpenIndex = ref(-1)
const deviceSwipeActions = [
    { key: 'delete', text: '删除', icon: 'trash', backgroundColor: '#ef4444', width: '74px' },
]
const showPartyPicker = ref(false)
const showWhPicker = ref(false)
const showItemWhPicker = ref(false)
const showSpecPicker = ref(false)
const showUnitPicker = ref(false)
const activeUnitItemIndex = ref(-1)
const unitActions = ['件', '张', '个', '盒', '台'].map(name => ({ name }))
const activeWarehouseItemIndex = ref(-1)
const activeSpecItemIndex = ref(-1)
const itemWarehousePicker = ref({
    warehouse_id: 0,
    warehouse_name: '',
    location_id: 0,
    location_name: '',
})
const form = ref({
    party_id: 0, party_name: '', member_name: '', m_no: '',
    warehouse_id: 0, warehouse_name: '',
    location_id: 0, location_name: '',
    settle_mode: 'credit' as 'credit' | 'cash',
    paid_amount: 0, capital_account_id: 0,
    voucher_urls: '',
    remark: '',
    item_type_mode: 'device' as 'device' | 'standard',
    items: [] as any[],
})

const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }
const whDisplayText = computed(() => {
    if (!form.value.warehouse_name) return ''
    return form.value.location_name
        ? `${form.value.warehouse_name} / ${form.value.location_name}`
        : form.value.warehouse_name
})
const totalCost = computed(() => form.value.items.reduce((s, i) =>
    s + (i.item_type === 'standard' ? standardLineTotal(i) : Number(i.purchase_cost || 0)), 0))
const purchaseModeMeta = computed(() => ({
    quick: {
        title: '快速入库',
        description: '先完成采购与入库，销售资料可按需展开补充。',
        icon: 'flash',
    },
    complete: {
        title: '一次完成',
        description: '在当前页面同时完成规格、图片和销售价格。',
        icon: 'checkmark-circle',
    },
    collaborative: {
        title: '分工协作',
        description: '采购先入库，拍摄和销售定价由后续岗位自动承接。',
        icon: 'account',
    },
}[purchaseEntryMode.value]))
const activeSpecItem = computed(() => form.value.items[activeSpecItemIndex.value] || {})
const activeSpecMeta = computed(() => metaFor(activeSpecItem.value))
const goodsMetaTip = computed(() => {
    const tips = Array.isArray(goodsMeta.value?.tips) ? goodsMeta.value.tips : []
    if (tips.length) return tips[0]
    return 'ERP 会优先复用站点内可用的商城资料，无法读取时自动使用本地资料。'
})
const canSubmit = computed(() =>
    !voucherUploading.value &&
    form.value.party_id > 0 &&
    form.value.items.length > 0 &&
    form.value.items.every(i => i.item_type === 'standard'
        ? (i.model && i.warehouse_id && i.location_id && Number(i.quantity) > 0 && standardLineTotal(i) > 0)
        : (i.imei && i.model && i.category_path && i.warehouse_id && i.location_id && Number(i.purchase_cost) > 0)) &&
    (
        form.value.settle_mode !== 'cash' ||
        (
            Number(form.value.paid_amount || 0) > 0 &&
            Number(form.value.paid_amount || 0) <= totalCost.value &&
            Number(form.value.capital_account_id || 0) > 0
        )
    )
)
const voucherUploading = ref(false)

onMounted(async () => {
    await Promise.all([
        getMobileCapitalAccounts().then((res: any) => { accounts.value = res?.data?.list || [] }).catch(() => {}),
        getMobileErpConfig().then((res: any) => {
            const mode = String(res?.data?.purchase?.mobile_entry_mode || 'quick')
            purchaseEntryMode.value = ['quick', 'complete', 'collaborative'].includes(mode)
                ? mode as 'quick' | 'complete' | 'collaborative'
                : 'quick'
        }).catch(() => {}),
        loadGoodsMeta(),
    ])
})

async function loadGoodsMeta() {
    try {
        const res: any = await getMobileErpGoodsMeta()
        goodsMeta.value = res?.data || {}
        return goodsMeta.value
    } catch {
        goodsMeta.value = {
            source: 'erp',
            source_label: 'ERP 本地资料',
            title_rules: { category_mode: 'auto', spec_in_title: true, grade_in_title: false, separator: ' ' },
            tips: ['暂未读取到商城资料，当前使用 ERP 本地资料兜底。'],
        }
        return goodsMeta.value
    }
}

function addDevice() {
    collapseCompletedDevices()
    form.value.items.push(newDeviceItem())
    expandedDeviceIndex.value = form.value.items.length - 1
}
function removeDevice(idx: number) {
    form.value.items.splice(idx, 1)
    swipeOpenIndex.value = -1
    if (!form.value.items.length) {
        expandedDeviceIndex.value = -1
    } else if (expandedDeviceIndex.value > idx) {
        expandedDeviceIndex.value -= 1
    } else if (expandedDeviceIndex.value >= form.value.items.length) {
        expandedDeviceIndex.value = form.value.items.length - 1
    }
}

function setSwipeOpen(idx: number, open: boolean) {
    swipeOpenIndex.value = open ? idx : (swipeOpenIndex.value === idx ? -1 : swipeOpenIndex.value)
}

function handleItemSwipeAction(action: string, idx: number) {
    if (action !== 'delete') return
    const item = form.value.items[idx]
    const label = String(item?.model || '').trim() || (form.value.item_type_mode === 'standard' ? `标品 ${idx + 1}` : `设备 ${idx + 1}`)
    uni.showModal({
        title: '确认删除',
        content: `删除“${label}”后，本行已填写的信息将无法恢复。`,
        confirmText: '删除',
        confirmColor: '#ef4444',
        success: result => {
            if (result.confirm) removeDevice(idx)
        },
    })
}

function changeItemTypeMode(mode: 'device' | 'standard') {
    if (form.value.item_type_mode === mode) return
    form.value.item_type_mode = mode
    form.value.items = [mode === 'standard' ? newStandardItem() : newDeviceItem()]
    expandedDeviceIndex.value = mode === 'device' ? 0 : -1
    swipeOpenIndex.value = -1
}

function addStandardItem() {
    form.value.items.push(newStandardItem())
}

function newStandardItem() {
    return {
        item_type: 'standard',
        quantity_product_id: 0,
        quantity_product_snapshot: null,
        model: '',
        spec: '',
        product_code: '',
        unit: '件',
        quantity: 1,
        unit_cost: 0,
        line_total: 0,
        purchase_cost: 0,
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        remark: '',
    }
}

function onStandardProductChange(item: any, product: any | null) {
    item.quantity_product_id = Number(product?.id || 0)
    item.quantity_product_snapshot = product || null
    item.model = String(product?.product_name || '')
    item.spec = String(product?.spec || '')
    item.product_code = String(product?.product_code || '')
    item.unit = String(product?.unit || '件')
}

function standardLineTotal(item: any) {
    if (item?.line_total !== undefined && item?.line_total !== null && item?.line_total !== '') {
        return Math.round(Number(item.line_total || 0) * 100) / 100
    }
    return Math.round(Number(item?.quantity || 0) * Number(item?.unit_cost || 0) * 100) / 100
}

function standardUnitCost(item: any) {
    const quantity = Number(item?.quantity || 0)
    return quantity > 0 ? Math.round((standardLineTotal(item) / quantity) * 1000000) / 1000000 : 0
}

function standardUnitCostText(item: any) {
    return standardUnitCost(item).toFixed(6).replace(/0+$/, '').replace(/\.$/, '') || '0'
}

function openUnitPicker(index: number) {
    activeUnitItemIndex.value = index
    showUnitPicker.value = true
}

function selectUnit(action: any) {
    const item = form.value.items[activeUnitItemIndex.value]
    if (item) item.unit = action?.name || '件'
    showUnitPicker.value = false
}

function clearParty() {
    form.value.party_id = 0
    form.value.party_name = ''
    form.value.member_name = ''
}

function newDeviceItem(imei = '') {
    return {
        item_type: 'device',
        imei,
        model: '',
        spec: '',
        catalog_product_id: '',
        catalog_product_name: '',
        category_name: '',
        category_path: '',
        brand_name: '',
        series_name: '',
        category_names: [] as string[],
        goods_meta: null,
        selected_specs: {},
        selected_grade: null,
        color: '',
        battery: '',
        warranty: 0,
        warehouse_id: form.value.warehouse_id || 0,
        warehouse_name: form.value.warehouse_name || '',
        location_id: form.value.location_id || 0,
        location_name: form.value.location_name || '',
        purchase_cost: 0,
        estimate_sale_price: 0,
        retail_price: 0,
        image_urls: '',
        video_url: '',
        remark_public: '',
        _material_open: purchaseEntryMode.value === 'complete',
    }
}

function deviceCoreComplete(item: any) {
    return !!(
        String(item?.imei || '').trim() &&
        String(item?.model || '').trim() &&
        String(item?.category_path || '').trim() &&
        Number(item?.warehouse_id || 0) > 0 &&
        Number(item?.location_id || 0) > 0 &&
        Number(item?.purchase_cost || 0) > 0
    )
}

function deviceSummary(item: any) {
    const parts = [
        String(item?.imei || '').trim() || '未填 IMEI',
        itemWarehouseText(item) || '待选入库位置',
        Number(item?.purchase_cost || 0) > 0 ? `成本 ¥${money(item.purchase_cost)}` : '待填成本',
    ]
    return parts.join(' · ')
}

function materialSummary(item: any) {
    const parts = []
    if (specSummary(item)) parts.push(specSummary(item))
    if (Number(item?.retail_price || 0) > 0) parts.push(`售价 ¥${money(item.retail_price)}`)
    const imageCount = String(item?.image_urls || '').split(',').filter(Boolean).length
    if (imageCount) parts.push(`${imageCount} 张图片`)
    return parts.length ? parts.join(' · ') : '规格、销售价格与图片可在此完善'
}

function toggleDevice(idx: number) {
    expandedDeviceIndex.value = expandedDeviceIndex.value === idx ? -1 : idx
}

function finishDevice(idx: number) {
    const item = form.value.items[idx]
    if (!deviceCoreComplete(item)) {
        uni.showToast({ title: '请先完成本台设备的必填信息', icon: 'none' })
        return
    }
    expandedDeviceIndex.value = -1
}

function collapseCompletedDevices() {
    if (expandedDeviceIndex.value < 0) return
    const item = form.value.items[expandedDeviceIndex.value]
    if (deviceCoreComplete(item)) expandedDeviceIndex.value = -1
}

function itemWarehouseText(item: any) {
    if (!item?.warehouse_name) return ''
    return item.location_name ? `${item.warehouse_name} / ${item.location_name}` : item.warehouse_name
}

function clearDefaultWarehouse() {
    form.value.warehouse_id = 0
    form.value.warehouse_name = ''
    form.value.location_id = 0
    form.value.location_name = ''
}

function clearItemWarehouse(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    item.warehouse_id = 0
    item.warehouse_name = ''
    item.location_id = 0
    item.location_name = ''
}

function specSummary(item: any) {
    return item?.spec || ''
}

function catalogDisplayLabel(item: any) {
    const parts = [
        item?.category_path || '',
        item?.brand_name || '',
        item?.series_name || '',
        item?.catalog_product_name || '',
    ].map(value => String(value || '').trim()).filter(Boolean)
    return parts.join(' / ')
}

function clearItemSpec(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    item.selected_specs = {}
    item.selected_grade = null
    item.color = ''
    item.battery = ''
    item.warranty = 0
    item.spec = ''
    if (item.model === item._auto_model) {
        item.model = categoryTitleByRule(item)
        item._auto_model = item.model
    }
}

function openSpecPicker(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    if (!item.category_path) {
        uni.showToast({ title: '请先选择商品品类', icon: 'none' })
        return
    }
    activeSpecItemIndex.value = idx
    showSpecPicker.value = true
}

function onSpecConfirm(payload: any) {
    const item = form.value.items[activeSpecItemIndex.value]
    if (!item) return
    item.selected_specs = payload?.selected_specs || {}
    item.selected_grade = payload?.selected_grade || null
    item.color = payload?.color || ''
    item.battery = payload?.battery || ''
    item.warranty = Number(payload?.warranty || 0)
    item.spec = payload?.spec || ''
    item.model = payload?.model || item.model
    item._auto_model = item.model
}

async function refreshActiveSpecMeta() {
    const item = form.value.items[activeSpecItemIndex.value]
    if (!item) {
        await loadGoodsMeta()
        return
    }
    item.goods_meta = await loadGoodsMeta()
}

function openItemWarehouse(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    activeWarehouseItemIndex.value = idx
    itemWarehousePicker.value = {
        warehouse_id: Number(item.warehouse_id || 0),
        warehouse_name: item.warehouse_name || '',
        location_id: Number(item.location_id || 0),
        location_name: item.location_name || '',
    }
    showItemWhPicker.value = true
}

function applyWarehouseToItem(item: any, warehouse: any, location: any) {
    item.warehouse_id = Number(warehouse?.id || 0)
    item.warehouse_name = warehouse?.warehouse_name || ''
    item.location_id = Number(location?.id || 0)
    item.location_name = location?.location_name || ''
}

function onDefaultWarehouseChange(warehouse: any, location: any) {
    form.value.items.forEach(item => {
        if (!item.warehouse_id) applyWarehouseToItem(item, warehouse, location)
    })
}

function onItemWarehouseChange(warehouse: any, location: any) {
    const item = form.value.items[activeWarehouseItemIndex.value]
    if (!item) return
    applyWarehouseToItem(item, warehouse, location)
    activeWarehouseItemIndex.value = -1
}

async function scanAddDevice() {
    try {
        const imei = await scanErpCode()
        collapseCompletedDevices()
        form.value.items.push(newDeviceItem(imei))
        expandedDeviceIndex.value = form.value.items.length - 1
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

async function onCatalogProductChange(idx: number, payload: any) {
    const item = form.value.items[idx]
    if (!item) return
    const previousAutoModel = item._auto_model || ''
    const isProduct = payload?.node_type === 'product' || Number(payload?.catalog_product_id || payload?.site_product_id || 0) > 0
    const productName = payload?.product_name || (payload?.node_type === 'product' ? payload?.label : '') || ''
    item.catalog_product_id = Number(payload?.catalog_product_id || payload?.site_product_id || 0)
    item.catalog_product_name = productName
    item.category_name = payload?.category_name || ''
    item.category_path = payload?.category_path || ''
    item.brand_name = payload?.brand_name || ''
    item.series_name = payload?.series_name || ''
    item.category_names = String(item.category_path || '').split('/').filter(Boolean)
    if (productName) {
        item.model = productName
        item._auto_model = productName
        item._model_manual = false
    } else if (!item.model || item.model === previousAutoModel) {
        item.model = ''
        item._auto_model = ''
        item._model_manual = false
    }
    item.selected_specs = {}
    item.selected_grade = null
    item.color = ''
    item.battery = ''
    item.warranty = 0
    item.spec = ''
    if (isProduct) rebuildItemTitles(item, true)
    if (item.category_path) {
        item.goods_meta = await loadGoodsMeta()
        if (isProduct) rebuildItemTitles(item, true)
    }
}

function clearItemCatalogProduct(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    item.catalog_product_id = ''
    item.catalog_product_name = ''
    item.category_name = ''
    item.category_path = ''
    item.brand_name = ''
    item.series_name = ''
    item.category_names = []
    item.goods_meta = null
    item.selected_specs = {}
    item.selected_grade = null
    item.color = ''
    item.battery = ''
    item.warranty = 0
    rebuildItemTitles(item, true)
}

function normalizeOptions(list: any): any[] {
    if (!Array.isArray(list)) return []
    return list.map((option: any, index: number) => {
        const label = String(option?.label ?? option?.item_value ?? option?.grade_name ?? option?.name ?? option?.value ?? '').trim()
        return {
            ...option,
            id: option?.id ?? option?.item_id ?? option?.grade_id ?? index + 1,
            label,
            value: String(option?.value ?? option?.item_value ?? option?.grade_name ?? label).trim(),
        }
    }).filter((option: any) => option.label && option.value)
}

function metaFor(item: any): any {
    return item?.goods_meta || goodsMeta.value || {}
}

function specGroupsFor(item: any): any[] {
    const meta = metaFor(item)
    const groups = Array.isArray(meta?.specs?.groups) ? meta.specs.groups : []
    return groups
        .map((group: any) => ({
            ...group,
            key: group.key || `spec_${group.id || group.source_id || group.label}`,
            items: normalizeOptions(group.items),
        }))
        .filter((group: any) => group.items.length)
}

function gradeOptionsFor(item: any): any[] {
    return normalizeOptions(metaFor(item)?.specs?.grades || [])
}

function categoryTitleByRule(item: any): string {
    const names = Array.isArray(item.category_names) && item.category_names.length
        ? item.category_names
        : String(item.category_name || '').split(/[>\-/\\｜|,，]+/).filter(Boolean)
    if (!names.length) return ''
    const mode = metaFor(item)?.title_rules?.category_mode || 'auto'
    if (mode === 'full') return names.join(' ')
    if (mode === 'level_1_2') return names.slice(0, 2).join(' ')
    if (mode === 'level_2_3') return names.slice(-2).join(' ')
    if (mode === 'level_3') return names[names.length - 1] || ''
    if (names.length >= 3) return names.slice(1, 3).join(' ')
    if (names.length === 2) return names.join(' ')
    return names[0] || ''
}

function selectedSpecValues(item: any, onlyTitle = false): string[] {
    const selected = item.selected_specs || {}
    return specGroupsFor(item)
        .filter((group: any) => !onlyTitle || !!group.title_part)
        .map((group: any) => selected[group.key]?.value || '')
        .filter(Boolean)
}

function uniqueParts(parts: string[]): string[] {
    const seen = new Set<string>()
    return parts.map(part => String(part || '').trim()).filter(part => {
        if (!part || seen.has(part)) return false
        seen.add(part)
        return true
    })
}

function buildModelTitle(item: any): string {
    const rules = metaFor(item)?.title_rules || {}
    const parts = [String(item?.catalog_product_name || '').trim() || categoryTitleByRule(item)]
    if (rules.spec_in_title !== false) parts.push(...selectedSpecValues(item, true))
    if (rules.grade_in_title && item.selected_grade?.value) parts.push(item.selected_grade.value)
    return uniqueParts(parts).join(rules.separator || ' ')
}

function buildSpecText(item: any): string {
    const parts = selectedSpecValues(item, false)
    if (item.selected_grade?.value) parts.push(item.selected_grade.value)
    return uniqueParts(parts).join(' ')
}

function rebuildItemTitles(item: any, forceModel = false) {
    const model = buildModelTitle(item)
    const spec = buildSpecText(item)
    if (forceModel || !item.model || item.model === item._auto_model) {
        item.model = model
        item._auto_model = model
    }
    item.spec = spec
}

function applyModelTitle(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    item.model = buildModelTitle(item)
    item._auto_model = item.model
}

function selectSpec(idx: number, group: any, option: any) {
    const item = form.value.items[idx]
    if (!item) return
    const current = item.selected_specs?.[group.key]?.value
    item.selected_specs = { ...(item.selected_specs || {}) }
    if (current === option.value) delete item.selected_specs[group.key]
    else item.selected_specs[group.key] = { label: option.label, value: option.value, group_key: group.key, group_label: group.label, title_part: !!group.title_part }
    rebuildItemTitles(item)
}

function selectGrade(idx: number, option: any) {
    const item = form.value.items[idx]
    if (!item) return
    item.selected_grade = item.selected_grade?.value === option.value ? null : { label: option.label, value: option.value }
    rebuildItemTitles(item)
}

async function scanDeviceImei(idx: number) {
    try {
        form.value.items[idx].imei = await scanErpCode()
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

async function submit() {
    if (submitting.value) return
    if (!canSubmit.value) {
        uni.showToast({ title: `请完善供应商、${form.value.item_type_mode === 'standard' ? '标品' : '设备'}和付款信息`, icon: 'none' })
        return
    }
    submitting.value = true
    const confirmed = await confirmErpSensitiveAction({
        title: '确认采购开单',
        content: `供货商：${erpPartyDisplayName(form.value)}\n${form.value.item_type_mode === 'standard' ? '标品' : '设备'}：${form.value.items.length} ${form.value.item_type_mode === 'standard' ? '项' : '台'}\n采购总额：¥${money(totalCost.value)}\n${form.value.settle_mode === 'cash' ? `将立即从所选账户付款 ¥${money(form.value.paid_amount)}，无需再次到财务确认。` : '本次全部挂账，后续到应付款结算。'}`,
        confirmText: '确认开单',
    })
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        await createMobileErpPurchase({
            party_id: form.value.party_id,
            party_name: form.value.party_name,
            m_no: form.value.m_no,
            warehouse_id: form.value.warehouse_id,
            warehouse_name: form.value.warehouse_name,
            location_id: form.value.location_id || 0,
            location_name: form.value.location_name,
            settle_mode: form.value.settle_mode,
            paid_amount: form.value.settle_mode === 'cash' ? Number(form.value.paid_amount) : 0,
            capital_account_id: form.value.settle_mode === 'cash' ? form.value.capital_account_id : 0,
            voucher_urls: form.value.settle_mode === 'cash' ? form.value.voucher_urls : '',
            remark: form.value.remark,
            items: form.value.items.map(i => i.item_type === 'standard' ? {
                item_type: 'standard',
                quantity_product_id: Number(i.quantity_product_id || 0),
                model: i.model,
                spec: i.spec || '',
                product_code: i.product_code || '',
                unit: i.unit || '件',
                quantity: Number(i.quantity || 0),
                unit_cost: standardUnitCost(i),
                purchase_cost: standardLineTotal(i),
                warehouse_id: Number(i.warehouse_id || 0),
                warehouse_name: i.warehouse_name || '',
                location_id: Number(i.location_id || 0),
                location_name: i.location_name || '',
                remark: i.remark || '',
            } : ({
                item_type: 'device',
                imei: i.imei, model: i.model, spec: i.spec,
                spec_json: {
                    specs: i.selected_specs || {},
                    grade: i.selected_grade || null,
                    color: i.color || '',
                    battery: i.battery || '',
                    warranty: Number(i.warranty || 0),
                    title_rule: metaFor(i)?.title_rules || {},
                },
                color: i.color || '',
                battery: i.battery || '',
                warranty: Number(i.warranty || 0),
                catalog_product_id: Number(i.catalog_product_id || 0),
                category_name: i.category_name || '',
                category_path: i.category_path || '',
                warehouse_id: Number(i.warehouse_id || 0),
                warehouse_name: i.warehouse_name || '',
                location_id: Number(i.location_id || 0),
                location_name: i.location_name || '',
                purchase_cost: Number(i.purchase_cost),
                estimate_sale_price: Number(i.retail_price || i.estimate_sale_price || 0),
                retail_price: Number(i.retail_price || i.estimate_sale_price || 0),
                image_urls: i.image_urls || '',
                video_url: i.video_url || '',
                remark_public: i.remark_public || '',
            }))
        })
        const remaining = Math.max(0, totalCost.value - Number(form.value.paid_amount || 0))
        uni.showToast({ title: form.value.settle_mode === 'cash'
            ? (remaining > 0.0001 ? `已现付，剩余¥${money(remaining)}待付` : '入库与付款已完成')
            : '采购已入库，等待付款', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1200)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '开单失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.form-wrap { padding: 24rpx 24rpx 120rpx; display: flex; flex-direction: column; gap: 20rpx; }
.meta-source-card { background:#fff; border-radius:28rpx; padding:20rpx 24rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); border:2rpx solid transparent; }
.meta-source-card.shop { background:#f8fbff; border-color:#dbe7ff; }
.meta-source-card.erp { background:#f8fafc; border-color:#eef2f7; }
.meta-source__head { display:flex; align-items:center; gap:10rpx; }
.meta-source__title { font-size:27rpx; font-weight:700; color:#0f172a; }
.meta-source__desc { display:block; margin-top:8rpx; font-size:23rpx; color:#64748b; line-height:1.45; }
.purchase-flow-card {
    display:flex; align-items:flex-start; gap:18rpx; padding:22rpx 24rpx; border:2rpx solid #dbeafe;
    border-radius:24rpx; background:linear-gradient(135deg,#f8fbff 0%,#eff6ff 100%);
}
.purchase-flow-card--collaborative { border-color:#ddd6fe; background:linear-gradient(135deg,#fafaff 0%,#f5f3ff 100%); }
.purchase-flow-card__icon {
    width:64rpx; height:64rpx; flex:none; display:flex; align-items:center; justify-content:center;
    border-radius:18rpx; background:#fff; box-shadow:0 4rpx 14rpx rgba(37,99,235,.1);
}
.purchase-flow-card__content { min-width:0; flex:1; }
.purchase-flow-card__title-row { display:flex; align-items:center; justify-content:space-between; gap:16rpx; }
.purchase-flow-card__title { color:#1e3a8a; font-size:27rpx; font-weight:750; }
.purchase-flow-card__tag { padding:5rpx 12rpx; border-radius:999rpx; color:#3b6ef5; background:rgba(255,255,255,.8); font-size:19rpx; }
.purchase-flow-card__desc { display:block; margin-top:6rpx; color:#64748b; font-size:22rpx; line-height:1.45; }
.form-section { background:#fff; border-radius:28rpx; padding:0 28rpx; overflow:hidden; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); }
.form-section__head { display:flex; align-items:center; justify-content:space-between; padding-top:20rpx; margin-bottom:16rpx; }
.form-section__actions { display:flex; align-items:center; gap:12rpx; }
.gesture-tip { display:block; margin-top:6rpx; color:#94a3b8; font-size:20rpx; line-height:1.35; }
.device-swipe { display:block; margin-bottom:18rpx; overflow:hidden; border-radius:22rpx; }
.mode-desc { display:block; margin-top:5rpx; color:#94a3b8; font-size:22rpx; }
.mode-tabs { display:grid; grid-template-columns:1fr 1fr; gap:14rpx; }
.mode-tab {
    height:82rpx; display:flex; align-items:center; justify-content:center; gap:10rpx;
    color:#64748b; font-size:26rpx; font-weight:600; background:#f8fafc;
    border:2rpx solid #e2e8f0; border-radius:16rpx;
}
.mode-tab.active { color:#2563eb; background:#eff6ff; border-color:#93c5fd; }
.standard-form-card { padding-bottom:12rpx; }
.unit-select {
    min-width:104rpx; height:68rpx; padding:0 16rpx; box-sizing:border-box; display:flex;
    align-items:center; justify-content:center; gap:8rpx; background:#f1f5f9;
    border-radius:12rpx; color:#475569; font-size:25rpx;
}
.standard-total {
    display:flex; align-items:center; justify-content:space-between;
    padding:20rpx 4rpx 6rpx; color:#64748b; font-size:24rpx;
}
.standard-total__money { color:#0f172a; font-size:30rpx; font-weight:700; }
.form-section__title { font-size:30rpx; font-weight:700; color:#0f172a; }
.field-tip { margin: -4rpx 0 22rpx; font-size: 23rpx; color: #94a3b8; line-height: 1.5; }
.form-row { min-height:92rpx; display:flex; align-items:center; gap:16rpx; border-bottom:2rpx solid #f3f4f6; &:last-child { border-bottom:0; } }
.form-label { font-size:26rpx; color:#374151; width:150rpx; flex-shrink:0; font-weight:600; }
.form-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.inline-scan { width:64rpx; height:64rpx; border-radius:50%; background:#eff3ff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.form-input { flex:1; min-width:0; min-height:72rpx; display:flex; align-items:center; justify-content:space-between; gap:16rpx; background:#f8fafc; border:2rpx solid transparent; border-radius:12rpx; padding:0 18rpx; box-sizing:border-box; overflow:hidden; }
.form-input--on { background:#f8fbff; border-color:#3b6ef5; }
.input-text { flex:1; min-width:0; font-size:26rpx; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.input-placeholder { flex:1; min-width:0; font-size:26rpx; color:#94a3b8; }
.inline-clear { width:40rpx; height:40rpx; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.device-form-card {
    overflow:hidden; border:2rpx solid #e2e8f0; border-radius:22rpx;
    background:#fff; transition:border-color .2s ease,box-shadow .2s ease;
}
.device-form-card--done { border-color:#bbf7d0; box-shadow:0 5rpx 18rpx rgba(22,163,74,.06); }
.device-form__head {
    min-height:104rpx; padding:16rpx 18rpx; box-sizing:border-box; display:flex; align-items:center;
    justify-content:space-between; gap:14rpx; background:linear-gradient(90deg,#fff 0%,#f8fafc 100%);
}
.device-form__identity { min-width:0; flex:1; display:flex; align-items:center; gap:14rpx; }
.device-form__number {
    width:54rpx; height:54rpx; flex:none; border-radius:15rpx; display:flex; align-items:center; justify-content:center;
    color:#2563eb; background:#eff6ff; font-size:21rpx; font-weight:750;
}
.device-form__headline { min-width:0; flex:1; }
.device-form__title-row { display:flex; align-items:center; gap:10rpx; min-width:0; }
.device-form__index {
    min-width:0; max-width:390rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    font-size:26rpx; font-weight:750; color:#172033;
}
.device-form__status { flex:none; padding:4rpx 10rpx; border-radius:999rpx; color:#d97706; background:#fff7ed; font-size:18rpx; }
.device-form__status.complete { color:#16a34a; background:#f0fdf4; }
.device-form__summary {
    display:block; max-width:500rpx; margin-top:5rpx; overflow:hidden; text-overflow:ellipsis;
    white-space:nowrap; color:#94a3b8; font-size:20rpx;
}
.device-form__tools { flex:none; display:flex; align-items:center; gap:14rpx; }
.device-form__body { padding:0 18rpx 18rpx; border-top:2rpx solid #f1f5f9; }
.device-subtitle { display:flex; align-items:center; justify-content:space-between; padding:18rpx 2rpx 4rpx; color:#334155; font-size:23rpx; font-weight:700; }
.device-subtitle text:last-child { color:#94a3b8; font-size:19rpx; font-weight:500; }
.device-material { margin-top:18rpx; overflow:hidden; border:2rpx solid #e8edf5; border-radius:18rpx; background:#f8fafc; }
.device-material__head { display:flex; align-items:center; justify-content:space-between; gap:16rpx; padding:20rpx; }
.device-material__title-row { display:flex; align-items:center; gap:10rpx; }
.device-material__title { color:#334155; font-size:25rpx; font-weight:700; }
.device-material__optional { padding:3rpx 9rpx; border-radius:999rpx; color:#64748b; background:#eef2f7; font-size:18rpx; }
.device-material__summary { display:block; margin-top:5rpx; color:#94a3b8; font-size:20rpx; }
.device-material__body { padding:0 16rpx 18rpx; border-top:2rpx solid #eef2f7; background:#fff; }
.device-material__body :deep(.erp-voucher) { margin-top:18rpx; }
.purchase-video {
    display:flex; align-items:center; justify-content:space-between; gap:18rpx; margin-top:16rpx;
    padding:18rpx; border-radius:14rpx; background:#f8fafc;
}
.purchase-video__title,.purchase-video__hint { display:block; }
.purchase-video__title { color:#334155; font-size:23rpx; font-weight:700; }
.purchase-video__hint { margin-top:4rpx; color:#94a3b8; font-size:19rpx; }
.handoff-tip { display:flex; align-items:flex-start; gap:14rpx; margin-top:18rpx; padding:20rpx; border-radius:18rpx; background:#eff6ff; }
.handoff-tip__title,.handoff-tip__desc { display:block; }
.handoff-tip__title { color:#1e3a8a; font-size:23rpx; font-weight:700; }
.handoff-tip__desc { margin-top:5rpx; color:#64748b; font-size:20rpx; line-height:1.5; }
.device-complete-action {
    height:72rpx; margin-top:18rpx; display:flex; align-items:center; justify-content:center; gap:9rpx;
    border-radius:15rpx; color:#475569; background:#f8fafc; font-size:23rpx; font-weight:650;
}
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
</style>
