<template>
    <view class="erp-page">
        <ErpPageHeader title="设备档案" />
        <view v-if="loading" class="loading-wrap"><u-loading-icon size="36" /></view>
        <view v-else-if="loadError" class="error-wrap">
            <u-empty mode="data" :text="loadError" />
            <view class="retry-btn">
                <u-button type="primary" size="small" text="重新加载" @click="loadDetail" />
            </view>
        </view>
        <scroll-view v-else-if="asset" scroll-y style="height:100%">
            <view class="detail-wrap">
                <view class="form-card asset-summary-card">
                    <view class="asset-head">
                        <view class="asset-title">
                            <text class="asset-title__model">{{ asset.model || '-' }}</text>
                            <text class="asset-title__sub">{{ deviceIdentityLine(asset) }}</text>
                        </view>
                        <view class="asset-head__tags">
                            <u-tag v-if="asset.ownership_type === 'consigned' || asset.warehouse_policy?.warehouse_type === 'consignment'" text="客户代卖" type="warning" plain plainFill size="mini" />
                            <u-tag :text="statusLabel(asset.status)" :type="statusType(asset.status)" plain plainFill size="mini" />
                        </view>
                    </view>

                    <view v-if="asset.status === 'sold'" class="asset-banner sold">
                        <u-icon name="checkmark-circle" color="#2563eb" size="14" />
                        <text>已售出{{ asset.sale_order?.sale_no ? ' · ' + asset.sale_order.sale_no : '' }}</text>
                    </view>
                    <view v-else-if="asset.status === 'void'" class="asset-banner void">
                        <u-icon name="info-circle" color="#64748b" size="14" />
                        <text>该设备已作废</text>
                    </view>
                    <view v-else-if="asset.status === 'in_stock'" class="turnover-action-card" :class="`is-${asset.turnover_level || 'healthy'}`">
                        <view class="turnover-action-card__main">
                            <text class="turnover-action-card__title">{{ asset.turnover_label || '库存周转' }} · 在库 {{ asset.stock_age_days || 0 }} 天</text>
                            <text class="turnover-action-card__desc">{{ asset.turnover_action || asset.warehouse_policy?.primary_action_reason || '根据仓库规则处理当前设备' }}</text>
                        </view>
                        <view class="turnover-action-card__btn">
                            <u-button size="small" :type="primaryActionType" :loading="syncingListing && asset.turnover_action_key === 'publish_listing'" :text="asset.turnover_action_label || '处理'" @click="handlePrimaryAction" />
                        </view>
                    </view>

                    <view class="erp-card__foot asset-finance-foot">
                        <view class="amount-box">
                            <text class="amt-label">总成本</text>
                            <text class="amt-value">¥{{ money(asset.total_cost) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ asset.status === 'sold' ? '实际收入' : '预估价' }}</text>
                            <text class="amt-value blue">{{ asset.status === 'sold' ? ('¥' + money(netSaleAmount(asset))) : displayEstimate(asset) }}</text>
                        </view>
                        <view class="amount-box">
                            <text class="amt-label">{{ asset.status === 'sold' ? '毛利' : '库龄' }}</text>
                            <text class="amt-value" :class="asset.status === 'sold' ? (Number(asset.profit)>=0?'green':'red') : ''">
                                {{ asset.status === 'sold' ? ('¥' + money(asset.profit)) : ageText(asset) }}
                            </text>
                        </view>
                    </view>
                    <view v-if="asset.status === 'sold' && compensationAmount(asset) > 0" class="sale-adjust-note">
                        原成交 ¥{{ money(originalSaleAmount(asset)) }} · 售后补差 -¥{{ money(compensationAmount(asset)) }} · 实际收入 ¥{{ money(netSaleAmount(asset)) }}
                    </view>

                    <view class="field">
                        <text class="label">资产号</text>
                        <text class="value">{{ asset.asset_no || '-' }}</text>
                    </view>
                    <view class="field">
                        <text class="label">IMEI</text>
                        <text class="value identity-value">{{ asset.imei || '-' }}</text>
                    </view>
                    <view v-if="asset.sn" class="field">
                        <text class="label">SN</text>
                        <text class="value identity-value">{{ asset.sn }}</text>
                    </view>
                    <view class="field">
                        <text class="label">仓库</text>
                        <text class="value">{{ asset.warehouse_name || '-' }}{{ asset.location_name ? ' / '+asset.location_name : '' }}</text>
                    </view>
                    <view v-if="asset.ownership_type === 'consigned' || asset.owner_party_name" class="field">
                        <text class="label">物权归属</text>
                        <text class="value">{{ asset.ownership_type === 'consigned' ? (asset.owner_party_name || asset.party_name || '客户') : '本公司' }}</text>
                    </view>
                    <view v-if="asset.category_name" class="field">
                        <text class="label">分类</text>
                        <text class="value">{{ asset.category_name }}</text>
                    </view>
                    <view v-if="asset.party_name" class="field field--last">
                        <text class="label">来源</text>
                        <text class="value">{{ asset.party_name }}</text>
                    </view>
                </view>

                <view class="form-card">
                    <view class="form-card-title">成本拆解</view>
                    <view class="field"><text class="label">采购成本</text><text class="value">¥{{ money(asset.purchase_cost) }}</text></view>
                    <view class="field" v-if="Number(asset.adjust_cost)">
                        <text class="label">成本调整</text>
                        <text class="value orange">{{ Number(asset.adjust_cost)>0?'+':'' }}¥{{ money(asset.adjust_cost) }}</text>
                    </view>
                    <view class="field" v-if="Number(asset.refurbish_cost)">
                        <text class="label">整备成本</text>
                        <text class="value">¥{{ money(asset.refurbish_cost) }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">当前总成本</text>
                        <text class="value strong">¥{{ money(asset.total_cost) }}</text>
                    </view>
                </view>

                <view class="form-card" v-if="asset.purchase_order">
                    <view class="form-card-title">采购信息</view>
                    <view class="field"><text class="label">供应商</text><text class="value">{{ asset.party_name }}</text></view>
                    <view class="field"><text class="label">采购单号</text><text class="value">{{ asset.purchase_order.purchase_no }}</text></view>
                    <view v-if="asset.purchase_order.m_no || asset.m_no" class="field"><text class="label">M号</text><text class="value">{{ asset.purchase_order.m_no || asset.m_no }}</text></view>
                    <view class="field field--last"><text class="label">付款状态</text>
                        <u-tag :text="financeLabel(asset.purchase_order.finance_status)" :type="financeType(asset.purchase_order.finance_status)" plain plainFill size="mini" />
                    </view>
                </view>

                <view class="form-card" v-if="asset.status === 'sold' && asset.sale_order">
                    <view class="form-card-title">销售信息</view>
                    <view class="field"><text class="label">客户</text><text class="value">{{ asset.sale_order.party_name }}</text></view>
                    <view class="field"><text class="label">销售单号</text><text class="value">{{ asset.sale_order.sale_no }}</text></view>
                    <view class="field"><text class="label">实际销售收入</text><text class="value blue">¥{{ money(netSaleAmount(asset)) }}</text></view>
                    <view v-if="compensationAmount(asset) > 0" class="field">
                        <text class="label">原成交 / 售后补差</text>
                        <text class="value orange">¥{{ money(originalSaleAmount(asset)) }} / -¥{{ money(compensationAmount(asset)) }}</text>
                    </view>
                    <view class="field field--last"><text class="label">毛利</text>
                        <text class="value" :class="Number(asset.profit || asset.last_sale_item?.profit)>=0?'green':'red'">¥{{ money(asset.profit || asset.last_sale_item?.profit) }}</text>
                    </view>
                </view>

                <view class="section-title">设备履历</view>
                <view class="flow-card" v-for="(flow, idx) in ledger" :key="idx">
                    <view class="flow-head">
                        <text class="flow-action">{{ flow.action_text || actionLabel(flow.action) }}</text>
                        <text class="flow-time">{{ formatDate(flow.occurred_at || flow.create_at) }}</text>
                    </view>
                    <view class="card-meta" v-if="flow.source_no">单据：{{ flow.source_no }}</view>
                    <view class="card-meta" v-if="flow.before_status && flow.after_status">
                        {{ flow.before_status_text || statusLabel(flow.before_status) }} → {{ flow.after_status_text || statusLabel(flow.after_status) }}
                    </view>
                    <view class="card-meta" v-if="flow.cost_delta && Number(flow.cost_delta)">
                        成本变化：{{ Number(flow.cost_delta)>0?'+':'' }}¥{{ money(flow.cost_delta) }}
                    </view>
                    <view class="card-meta" v-if="flow.remark">{{ flow.remark }}</view>
                </view>
                <view class="empty-tip" v-if="!ledger.length">暂无设备履历</view>

                <view class="section-title account-section-title">设备账务轨迹</view>
                <view class="account-tip">按设备展示采购应付、销售应收、实际收付款、折账与冲销结果；售后补差的应付和实际付款会合并，避免重复理解金额。</view>
                <view class="account-card" v-for="row in visibleAccountTimeline" :key="row.id || row.ledger_no">
                    <view class="account-card__head">
                        <text class="account-card__title">{{ accountBizLabel(row) }}</text>
                        <text class="account-card__state" :class="accountStateClass(row)">{{ accountSettlementText(row) }}</text>
                    </view>
                    <view class="account-card__result">
                        <text>{{ accountImpactText(row) }}</text>
                        <text class="account-card__amount" :class="accountAmountClass(row)">¥{{ money(row.amount) }}</text>
                    </view>
                    <view class="account-card__meta" v-if="row._display_source_no || row.source_no">来源：{{ row._display_source_no || row.source_no }}</view>
                    <view class="account-card__meta">{{ accountRemark(row) }}</view>
                    <view class="account-card__time">{{ formatTime(row.occurred_at || row.create_at) }}</view>
                </view>
                <view class="empty-tip account-empty" v-if="!accountTimeline.length">暂无设备账务记录</view>
                <view v-if="accountTimeline.length > accountPreviewLimit" class="account-more" @click="showAllAccountLedger = !showAllAccountLedger">
                    <text>{{ showAllAccountLedger ? '收起账务轨迹' : `展开全部 ${accountTimeline.length} 条` }}</text>
                    <u-icon :name="showAllAccountLedger ? 'arrow-up' : 'arrow-down'" color="#2563eb" size="13" />
                </view>

                <!-- 底部操作 -->
                <view class="bottom-actions">
                    <view v-if="asset.status === 'in_stock'" class="bottom-action-row primary-turnover-row">
                        <view class="bottom-action-btn full"><u-button type="primary" :text="asset.turnover_action_label || '处理库存'" @click="handlePrimaryAction" /></view>
                    </view>
                    <view v-if="asset.status === 'in_stock'" class="bottom-action-row">
                        <view class="bottom-action-btn"><u-button type="primary" text="调整成本" @click="goAdjust" /></view>
                        <view class="bottom-action-btn">
                            <u-button type="primary" plain :text="asset.ownership_type === 'consigned' || asset.warehouse_policy?.warehouse_type === 'consignment' ? '转为自有' : '库存调拨'" :loading="transferring" :disabled="!canOpenTransfer" @click="openTransfer" />
                        </view>
                        <view class="bottom-action-btn">
                            <u-button
                                type="warning"
                                :text="returnActionLabel(asset)"
                                :disabled="asset.return_flow?.returnable === false"
                                @click="goReturn"
                            />
                        </view>
                    </view>
                    <view v-if="asset.status === 'sold'" class="bottom-action-row">
                        <view class="bottom-action-btn full">
                            <u-button type="warning" text="发起销售退货" @click="goSaleReturn" />
                        </view>
                    </view>
                    <view v-if="asset.status === 'in_stock' && asset.return_flow?.returnable === false" class="return-disabled-reason">
                        <u-icon name="info-circle" color="#94a3b8" size="14" />
                        <text>{{ asset.return_flow?.block_reason || '当前设备不可采购退货' }}</text>
                    </view>
                </view>
            </view>
        </scroll-view>

        <u-popup :show="productVisible" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="productVisible = false">
            <view class="action-popup">
                <view class="action-popup__head"><view><text class="action-popup__title">完善商品资料</text><text class="action-popup__sub">用于商城展示和销售定价，不修改采购成本</text></view><u-icon name="close" color="#94a3b8" size="20" @click="productVisible=false" /></view>
                <scroll-view scroll-y class="action-popup__body">
                    <ErpCatalogProductPopup v-model="productForm.catalog_product_id" :selected-label="productForm.catalog_product_name" label="商品型号" :embedded="true" :clearable="true" @change="onProductCatalogChange" />
                    <view class="popup-form-row"><text>设备规格</text><u-input v-model="productForm.spec" placeholder="容量、颜色、成色、电池等" border="none" inputAlign="right" /></view>
                    <view class="popup-form-row"><text>零售价</text><u-input v-model="productForm.retail_price" type="number" placeholder="0.00" border="none" inputAlign="right" /></view>
                    <ErpVoucherUploader v-model="productForm.image_urls" title="商品图片" hint="上传正面、背面、边框和瑕疵图，支持点击预览" add-text="上传图片" :max-count="9" />
                </scroll-view>
                <view class="action-popup__foot"><u-button type="primary" :loading="productSaving" text="保存商品资料" @click="submitProduct" /></view>
            </view>
        </u-popup>

        <u-popup :show="retailVisible" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="retailVisible=false">
            <view class="action-popup action-popup--compact">
                <view class="action-popup__head"><view><text class="action-popup__title">{{ Number(asset?.retail_price || 0) > 0 ? '调整零售价' : '设置零售价' }}</text><text class="action-popup__sub">零售价用于销售，不改变设备成本</text></view><u-icon name="close" color="#94a3b8" size="20" @click="retailVisible=false" /></view>
                <view class="popup-form-row"><text>新零售价</text><u-input v-model="retailForm.retail_price" type="number" placeholder="0.00" border="none" inputAlign="right" /></view>
                <view class="popup-form-row"><text>调整原因</text><u-input v-model="retailForm.reason" placeholder="已有价格调整时必填" border="none" inputAlign="right" /></view>
                <view class="action-popup__foot"><u-button type="primary" :loading="retailSaving" text="确认保存" @click="submitRetail" /></view>
            </view>
        </u-popup>

        <ErpWarehousePopup v-model:show="warehouseVisible" v-model:warehouse-id="transferForm.warehouse_id" v-model:warehouse-name="transferForm.warehouse_name" v-model:location-id="transferForm.location_id" v-model:location-name="transferForm.location_name" @change="submitTransfer" />
        <u-popup :show="buyoutVisible" mode="bottom" round="20" :safe-area-inset-bottom="true" @close="buyoutVisible=false">
            <view class="action-popup action-popup--compact">
                <view class="action-popup__head"><view><text class="action-popup__title">代卖设备转为自有</text><text class="action-popup__sub">确认回收价后生成设备级采购应付</text></view><u-icon name="close" color="#94a3b8" size="20" @click="buyoutVisible=false" /></view>
                <view class="buyout-summary"><text>{{ asset?.model || '-' }}</text><text>物权客户 {{ buyoutPreview?.items?.[0]?.party_name || '-' }} · IMEI {{ asset?.imei || '-' }}</text></view>
                <view class="popup-form-row"><text>确认回收价</text><u-input v-model="buyoutForm.amount" type="number" placeholder="0.00" border="none" inputAlign="right" /></view>
                <view class="popup-form-row"><text>买断说明</text><u-input v-model="buyoutForm.reason" placeholder="可选" border="none" inputAlign="right" /></view>
                <view class="buyout-tip">回收价形成应付给客户的采购本金；设备原有内部整备费用不会重复付给客户。</view>
                <view class="action-popup__foot"><u-button type="primary" :loading="transferring" text="确认转为自有" @click="confirmBuyout" /></view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { adjustMobileStockRetailPrice, buyoutMobileConsignment, getMobileStockInfo, previewMobileStockTransfer, syncMobileStockListing, transferMobileStock, updateMobileStockFlow } from '@/addon/hsx_erp/api/erp'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import { erpNetSaleAmount, erpOriginalSaleAmount, erpSaleCompensationAmount, firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import { erpDeviceIdentityLine } from '@/addon/hsx_erp/hooks/useErpDeviceText'
import { formatErpDate, formatErpTime } from '@/addon/hsx_erp/hooks/useErpTime'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpCatalogProductPopup from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'

const asset = ref<any>(null)
const ledger = ref<any[]>([])
const showAllAccountLedger = ref(false)
const accountPreviewLimit = 4
const loading = ref(true)
const syncingListing = ref(false)
const loadError = ref('')
const assetId = ref(0)
const detailLoaded = ref(false)
const productVisible = ref(false)
const productSaving = ref(false)
const productForm = ref<any>({ catalog_product_id: 0, catalog_product_name: '', category_name: '', category_path: '', spec: '', retail_price: '', image_urls: '' })
const retailVisible = ref(false)
const retailSaving = ref(false)
const retailForm = ref({ retail_price: '', reason: '' })
const warehouseVisible = ref(false)
const transferring = ref(false)
const transferForm = ref({ warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' })
const buyoutVisible = ref(false)
const buyoutPreview = ref<any>(null)
const buyoutForm = ref({ amount: '', reason: '' })
let loadSeq = 0

onLoad((query: any) => {
    assetId.value = Number(query?.id || 0)
    loadDetail()
})

onShow(() => {
    if (detailLoaded.value) reload()
})

async function loadDetail(options: { silent?: boolean } = {}) {
    const seq = ++loadSeq
    if (!assetId.value) {
        loading.value = false
        loadError.value = '缺少设备ID'
        return
    }
    const silent = !!options.silent || !!asset.value
    if (!silent) loading.value = true
    loadError.value = ''
    try {
        const res: any = await withTimeout(getMobileStockInfo(assetId.value), 12000)
        if (seq !== loadSeq) return
        const data = res?.data || {}
        if (!data?.id) {
            throw new Error('设备不存在或无权查看')
        }
        asset.value = data
        ledger.value = data.asset_ledgers || data.ledger || data.asset_ledger || []
        showAllAccountLedger.value = false
    } catch (e: any) {
        if (seq !== loadSeq) return
        const message = e?.message || e?.msg || '设备档案加载失败'
        if (silent && asset.value) {
            uni.showToast({ title: message, icon: 'none' })
        } else {
            loadError.value = message
            asset.value = null
            ledger.value = []
            uni.showToast({ title: message, icon: 'none' })
        }
    } finally {
        if (seq === loadSeq) {
            loading.value = false
            detailLoaded.value = true
        }
    }
}

function reload() {
    return loadDetail({ silent: true })
}

const primaryActionType = computed(() => ['transfer', 'resolve_warehouse', 'complete_refurbish', 'resolve_refurbish'].includes(String(asset.value?.turnover_action_key || '')) ? 'warning' : 'primary')
const canOpenTransfer = computed(() => Number(asset.value?.can_warehouse_action ?? asset.value?.warehouse_policy?.can_warehouse_action ?? asset.value?.can_transfer ?? asset.value?.warehouse_policy?.can_transfer ?? 0) === 1
    || String(asset.value?.turnover_action_key || asset.value?.warehouse_policy?.primary_action || '') === 'resolve_warehouse')

function handlePrimaryAction() {
    if (!asset.value) return
    const action = String(asset.value.turnover_action_key || asset.value.warehouse_policy?.primary_action || 'view')
    if (['set_retail_price', 'adjust_retail_price'].includes(action)) return openRetail()
    if (action === 'complete_listing') return openProduct()
    if (action === 'publish_listing') return publishListing()
    if (['transfer', 'resolve_warehouse'].includes(action)) return openTransfer()
    if (action === 'direct_sale') return uni.navigateTo({ url: `/addon/hsx_erp/pages/sale/create?asset_ids=${asset.value.id}` })
    if (action === 'start_refurbish') return uni.navigateTo({ url: `/addon/hsx_erp/pages/stock/list?refurbish_status=pending` })
    if (['complete_refurbish', 'resolve_refurbish'].includes(action)) return goAdjustRefurbish()
    uni.showToast({ title: asset.value.warehouse_policy?.primary_action_reason || '当前仅支持查看设备档案', icon: 'none' })
}

function openProduct() {
    if (!asset.value) return
    productForm.value = {
        catalog_product_id: Number(asset.value.catalog_product_id || 0), catalog_product_name: asset.value.model || '', category_name: asset.value.category_name || '', category_path: asset.value.category_path || '',
        spec: asset.value.spec || '', retail_price: Number(asset.value.retail_price || 0) || '', image_urls: asset.value.image_urls || '',
    }
    productVisible.value = true
}

function onProductCatalogChange(payload: any) {
    productForm.value.catalog_product_id = Number(payload?.catalog_product_id || payload?.site_product_id || 0)
    productForm.value.catalog_product_name = payload?.product_name || payload?.label || ''
    productForm.value.category_name = payload?.category_name || ''
    productForm.value.category_path = payload?.category_path || ''
}

async function submitProduct() {
    if (!asset.value?.id) return
    if (!Number(productForm.value.catalog_product_id || 0)) return uni.showToast({ title: '请选择商品型号', icon: 'none' })
    if (!String(productForm.value.spec || '').trim()) return uni.showToast({ title: '请填写设备规格', icon: 'none' })
    if (asset.value.warehouse_policy?.need_photo && !String(productForm.value.image_urls || '').trim()) return uni.showToast({ title: '当前仓库要求上传商品图片', icon: 'none' })
    if (asset.value.warehouse_policy?.need_pricing && Number(productForm.value.retail_price || 0) <= 0) return uni.showToast({ title: '当前仓库要求填写零售价', icon: 'none' })
    productSaving.value = true
    try {
        await updateMobileStockFlow(asset.value.id, { ...productForm.value, retail_price: Number(productForm.value.retail_price || 0), remark: '移动端完善商城商品资料' })
        productVisible.value = false
        uni.showToast({ title: '商品资料已保存', icon: 'success' })
        await reload()
    } catch (e: any) { uni.showToast({ title: e?.message || '保存失败', icon: 'none' }) }
    finally { productSaving.value = false }
}

function openRetail() {
    retailForm.value = { retail_price: String(Number(asset.value?.retail_price || 0) || ''), reason: '' }
    retailVisible.value = true
}

async function submitRetail() {
    const price = Number(retailForm.value.retail_price || 0)
    if (price <= 0) return uni.showToast({ title: '请填写有效零售价', icon: 'none' })
    if (Number(asset.value?.retail_price || 0) > 0 && !retailForm.value.reason.trim()) return uni.showToast({ title: '调整已有价格时请填写原因', icon: 'none' })
    const confirmed = await confirmErpSensitiveAction({ title: '确认零售价', content: `零售价将设置为 ¥${money(price)}。本操作不会修改采购成本。`, confirmText: '确认保存' })
    if (!confirmed) return
    retailSaving.value = true
    try {
        await adjustMobileStockRetailPrice(asset.value.id, { retail_price: price, reason: retailForm.value.reason })
        retailVisible.value = false
        uni.showToast({ title: '零售价已保存', icon: 'success' })
        await reload()
    } catch (e: any) { uni.showToast({ title: e?.message || '保存失败', icon: 'none' }) }
    finally { retailSaving.value = false }
}

function openTransfer() {
    if (!canOpenTransfer.value) {
        uni.showToast({ title: asset.value?.warehouse_policy?.primary_action_reason || '当前设备不可调拨', icon: 'none' })
        return
    }
    transferForm.value = { warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '' }
    warehouseVisible.value = true
}

async function submitTransfer(warehouse: any, location: any) {
    if (!asset.value?.id || !warehouse?.id || !location?.id) return
    let preview: any
    try {
        const res: any = await previewMobileStockTransfer({ asset_ids: [Number(asset.value.id)], warehouse_id: Number(warehouse.id), location_id: Number(location.id) })
        preview = res?.data || null
    } catch (e: any) {
        return uni.showToast({ title: e?.message || '目标仓规则校验失败', icon: 'none' })
    }
    if (!preview?.allowed) return uni.showToast({ title: preview?.reason || '当前设备不能调入所选仓库', icon: 'none' })
    if (preview.action === 'buyout') {
        buyoutPreview.value = preview
        buyoutForm.value = { amount: '', reason: '' }
        buyoutVisible.value = true
        return
    }
    const confirmed = await confirmErpSensitiveAction({ title: '确认库存调拨', content: `设备将调拨到「${warehouse.warehouse_name} / ${location.location_name}」，并重新应用目标仓库的销售和商城规则。`, confirmText: '确认调拨' })
    if (!confirmed) return
    transferring.value = true
    try {
        await transferMobileStock({ asset_ids: [asset.value.id], warehouse_id: warehouse.id, location_id: location.id, reason: '移动端库存周转调拨' })
        uni.showToast({ title: '调拨完成', icon: 'success' })
        await reload()
    } catch (e: any) { uni.showToast({ title: e?.message || '调拨失败', icon: 'none' }) }
    finally { transferring.value = false }
}

async function confirmBuyout() {
    const amount = Number(buyoutForm.value.amount || 0)
    if (!asset.value?.id || amount <= 0) return uni.showToast({ title: '请填写有效回收价', icon: 'none' })
    const customer = buyoutPreview.value?.items?.[0]?.party_name || '物权客户'
    const confirmed = await confirmErpSensitiveAction({ title: '确认代卖转自有', content: `确认按 ¥${money(amount)} 向「${customer}」买断该设备？确认后将生成设备级采购应付。`, confirmText: '确认买断' })
    if (!confirmed) return
    transferring.value = true
    try {
        const response: any = await buyoutMobileConsignment({ asset_id: Number(asset.value.id), warehouse_id: Number(transferForm.value.warehouse_id), location_id: Number(transferForm.value.location_id), buyout_amount: amount, reason: buyoutForm.value.reason || '移动端代卖转自有' })
        buyoutVisible.value = false
        uni.showToast({ title: response?.data?.plugin_sync?.ok === false ? '已完成，回收端同步待重试' : '已转为自有并生成应付', icon: 'none' })
        await reload()
    } catch (e: any) { uni.showToast({ title: e?.message || '转为自有失败', icon: 'none' }) }
    finally { transferring.value = false }
}

function goAdjustRefurbish() {
    if (!asset.value) return
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?id=${asset.value.id}&mode=refurbish_complete` })
}

function withTimeout<T>(promise: Promise<T>, timeoutMs: number): Promise<T> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('设备档案加载超时，请重试')), timeoutMs)
        promise.then(resolve).catch(reject).finally(() => clearTimeout(timer))
    })
}

const goAdjust = () => {
    if (!asset.value) return
    const a = asset.value
    const q = `id=${a.id}&model=${encodeURIComponent(a.model || '')}&asset_no=${encodeURIComponent(a.asset_no || '')}&imei=${encodeURIComponent(a.imei || '')}&status=${encodeURIComponent(a.status || '')}&cost=${a.total_cost || 0}&wh=${encodeURIComponent(a.warehouse_name || '')}&loc=${encodeURIComponent(a.location_name || '')}`
    uni.navigateTo({ url: `/addon/hsx_erp/pages/cost_adjust/detail?${q}` })
}

const publishListing = async () => {
    if (!asset.value || syncingListing.value || Number(asset.value.warehouse_policy?.can_list_mall || 0) !== 1) return
    const confirmed = await confirmErpSensitiveAction({
        title: '上架商城',
        content: `确认将「${asset.value.model || asset.value.imei || '-'}」直接上架商城？系统将使用当前分类、规格、图片和零售价创建一机一品商品。`,
        confirmText: '确认上架',
    })
    if (!confirmed) return
    syncingListing.value = true
    try {
        const res: any = await syncMobileStockListing(asset.value.id)
        if (res?.data?.ok === false) {
            uni.showToast({ title: res?.data?.message || '上架失败', icon: 'none' })
            return
        }
        uni.showToast({ title: res?.data?.message || '已上架商城', icon: 'success' })
        await reload()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '上架失败，请重试', icon: 'none' })
        await reload()
    } finally {
        syncingListing.value = false
    }
}

const returnActionLabel = (_row: any) => '采购退货'
const goReturn = () => {
    if (!asset.value || asset.value.return_flow?.returnable === false) return
    const a = asset.value
    uni.navigateTo({
        url: `/addon/hsx_erp/pages/purchase_return/create?purchase_order_id=${a.purchase_order_id || ''}&purchase_no=${encodeURIComponent(a.purchase_order?.purchase_no || '')}&party_name=${encodeURIComponent(a.party_name || '')}&asset_id=${a.id || ''}`
    })
}

const goSaleReturn = () => {
    if (!asset.value || asset.value.status !== 'sold') return
    const row = asset.value
    const saleOrderId = Number(row.sale_order_id || row.sale_order?.id || row.last_sale_item?.sale_order_id || 0)
    if (!saleOrderId) {
        uni.showToast({ title: '未找到关联销售单，请刷新设备档案', icon: 'none' })
        return
    }
    uni.navigateTo({
        url: `/addon/hsx_erp/pages/sale_return/create?sale_order_id=${saleOrderId}&sale_no=${encodeURIComponent(row.sale_order?.sale_no || '')}&party_name=${encodeURIComponent(row.sale_order?.party_name || row.sale_party_name || '')}&asset_id=${Number(row.id || 0)}`
    })
}

const money = (v: any) => Number(v || 0).toFixed(2)
const formatDate = (ts: number) => formatErpDate(ts)
const formatTime = (ts: number) => formatErpTime(ts)
const ageDays = (ts: number) => ts ? Math.floor((Date.now() / 1000 - ts) / 86400) : 0
const ageText = (row: any) => {
    const ts = Number(row?.stock_in_at || row?.create_at || 0)
    return ts ? `${ageDays(ts)}天` : '-'
}
const displayEstimate = (row: any) => {
    const price = firstPositiveErpAmount(row?.retail_price, row?.estimate_sale_price)
    return Number(price || 0) > 0 ? `¥${money(price)}` : '-'
}
const netSaleAmount = (row: any) => erpNetSaleAmount({ ...row, ...(row?.last_sale_item || {}) })
const originalSaleAmount = (row: any) => erpOriginalSaleAmount({ ...row, ...(row?.last_sale_item || {}) })
const compensationAmount = (row: any) => erpSaleCompensationAmount({ ...row, ...(row?.last_sale_item || {}) })
const deviceIdentityLine = (row: any) => erpDeviceIdentityLine(row)
const statusLabel = (s: string) => ({ in_stock: '在库', sold: '已售', returned: '已退', void: '已作废' }[s] || s || '-')
const statusType = (s: string) => ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info' }[s] || 'info')
const financeLabel = (s: string) => ({ pending: '待付款', partial: '部分付款', settled: '已结清', void: '已作废' }[s] || s || '-')
const financeType = (s: string) => ({ pending: 'warning', partial: 'primary', settled: 'success', void: 'info' }[s] || 'info')
const actionLabel = (a: string) => ({
    inbound: '采购入库', sold: '销售出库', purchase_return: '采购退货',
    sale_return: '销售退货', cost_adjust: '成本调整', purchase_cancel: '采购撤销',
    sale_cancel: '整单销售撤销', sale_item_cancel: '单台销售撤销', refurbish: '整备',
    retail_price_adjust: '零售价调整', transfer: '库存调拨', ownership_purchase: '代卖转自有', flow: '流转设置', flow_set: '流转设置'
}[a] || '库存调整')

function mergeAccountTimeline(rows: any[] = []) {
    const source = (rows || []).map((row: any) => ({ ...row }))
    const compensations = source.filter((row: any) => String(row.biz_type || '').toLowerCase() === 'sale_compensation')
    const mergedCompensationIds = new Set<number>()
    source.forEach((row: any) => {
        if (String(row.biz_type || '').toLowerCase() !== 'payment') return
        const paymentAt = Number(row.occurred_at || row.create_at || 0)
        const match = compensations
            .filter((comp: any) => {
                const hasLifecycle = Boolean(row.lifecycle_key || comp.lifecycle_key)
                const sameLifecycle = hasLifecycle ? Boolean(row.lifecycle_key && comp.lifecycle_key && row.lifecycle_key === comp.lifecycle_key) : true
                return !mergedCompensationIds.has(Number(comp.id)) && sameLifecycle && Number(comp.asset_id || 0) === Number(row.asset_id || 0) && Math.abs(Number(comp.amount || 0) - Number(row.amount || 0)) < 0.001 && Number(comp.occurred_at || comp.create_at || 0) <= paymentAt
            })
            .sort((a: any, b: any) => Number(b.occurred_at || b.create_at || 0) - Number(a.occurred_at || a.create_at || 0))[0]
        if (!match) return
        mergedCompensationIds.add(Number(match.id))
        row._merged_compensation = true
        row._display_source_no = match.source_no || row.source_no
        row.settlement_methods = Array.from(new Set([...(row.settlement_methods || []), ...(match.settlement_methods || []), '实际付款']))
    })
    return source.filter((row: any) => !mergedCompensationIds.has(Number(row.id)))
}

const accountTimeline = computed(() => mergeAccountTimeline(asset.value?.account_ledgers || []))
const visibleAccountTimeline = computed(() => showAllAccountLedger.value ? accountTimeline.value : accountTimeline.value.slice(0, accountPreviewLimit))

function accountBizLabel(row: any) {
    if (row?._merged_compensation) return '售后补差付款'
    const type = String(row?.biz_type || '').toLowerCase()
    const map: Record<string, string> = {
        purchase: '采购应付', purchase_cancel: '采购撤销冲回', purchase_return: '采购退货冲回', purchase_return_loss: '采购退货损失',
        consignment_buyout: '代卖买断应付',
        sale: '销售应收', sale_cancel: '整单销售撤销', sale_item_cancel: '单台销售撤销', sale_return: '销售退货冲回',
        sale_compensation: '售后补差应付', adjust: '成本调整', refurbish: '整备成本',
        payment: '实际付款', receipt: '实际收款', offset: '往来折账'
    }
    if (type === 'sale_compensation' && (row?.settlement_methods || []).includes('折账结清')) return '售后补差折账'
    return row?.biz_type_text || map[type] || '账务调整'
}

function accountImpactText(row: any) {
    const type = String(row?.biz_type || '').toLowerCase()
    if (row?._merged_compensation) return '公司已向客户支付售后补差'
    if (type === 'purchase') return '采购入库形成应付款'
    if (type === 'consignment_buyout') return '买断客户代卖设备并形成应付款'
    if (['purchase_cancel', 'purchase_return'].includes(type)) return '原采购应付已经冲回'
    if (type === 'purchase_return_loss') return '采购退货形成不可收回损失'
    if (type === 'sale') return row?.business_state === 'reversed' ? '原销售应收已经冲销' : '销售出库形成应收款'
    if (['sale_cancel', 'sale_item_cancel'].includes(type)) return '撤销销售并冲回设备应收'
    if (type === 'sale_return') return '客户退货并冲回销售应收'
    if (type === 'sale_compensation') return '公司新增一笔待付给客户的补差款'
    if (type === 'payment') return '公司已经完成实际付款'
    if (type === 'receipt') return '公司已经确认实际收款'
    if (type === 'offset') return '应收与应付完成折账核销'
    if (type === 'refurbish') return '设备整备成本增加'
    return '设备账务发生调整'
}

function accountSettlementText(row: any) {
    const methods = Array.from(new Set((row?.settlement_methods || []).filter(Boolean)))
    if (methods.length) return methods.join('、')
    if (row?.business_state_text) return row.business_state_text
    const type = String(row?.biz_type || '').toLowerCase()
    if (type === 'sale') return '待客户付款'
    if (['purchase', 'refurbish', 'sale_compensation'].includes(type)) return '待财务付款'
    return '账务已记录'
}

function accountStateClass(row: any) {
    const text = accountSettlementText(row)
    if (text.includes('待')) return 'is-pending'
    if (text.includes('冲销') || text.includes('结清') || text.includes('收款')) return 'is-settled'
    return 'is-paid'
}

function accountAmountClass(row: any) {
    const type = String(row?.biz_type || '').toLowerCase()
    if (['receipt', 'sale_cancel', 'sale_item_cancel', 'sale_return', 'purchase_cancel', 'purchase_return'].includes(type)) return 'is-income'
    if (['payment', 'sale_compensation', 'purchase_return_loss'].includes(type) || row?._merged_compensation) return 'is-expense'
    return ''
}

function accountRemark(row: any) {
    const type = String(row?.biz_type || '').toLowerCase()
    const remark = String(row?.remark || '').trim()
    if (row?._merged_compensation) return '补差应付与实际付款已合并展示，本次只计一笔。'
    if (type === 'sale_compensation' && (!remark || remark === '售后补差')) return '这是公司欠客户的补差款，不是资金收入；财务付款后才形成实际支出。'
    if (type === 'sale_cancel' && !remark) return '销售单已撤销，设备回到库存，原销售应收已冲回。'
    if (type === 'sale_item_cancel' && !remark) return '该设备销售已撤销，设备应收已冲回。'
    if (type === 'offset' && !remark) return '本次通过应收应付互抵完成结算，不产生资金收付。'
    return remark || accountImpactText(row)
}
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.detail-wrap { padding: 16rpx 0 120rpx; }
.asset-summary-card { padding-top:24rpx; }
.asset-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18rpx; }
.asset-head__tags { display:flex; flex-shrink:0; flex-direction:column; align-items:flex-end; gap:8rpx; }
.asset-title { flex:1; min-width:0; display:flex; flex-direction:column; gap:8rpx; }
.asset-title__model { font-size:32rpx; font-weight:700; color:#0f172a; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.asset-title__sub { font-size:24rpx; color:#64748b; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.asset-banner { display:flex; align-items:center; gap:8rpx; margin:16rpx 0 0; padding:12rpx 16rpx; border-radius:12rpx; font-size:22rpx; line-height:1.45; }
.asset-banner.sold { background:#eff6ff; color:#2563eb; }
.asset-banner.void { background:#f1f5f9; color:#64748b; }
.asset-banner text { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.turnover-action-card { display:flex; align-items:center; justify-content:space-between; gap:16rpx; margin:16rpx 0 0; padding:18rpx; border:2rpx solid #dbeafe; border-radius:16rpx; background:#eff6ff; }
.turnover-action-card.is-warning { border-color:#fed7aa; background:#fff7ed; }.turnover-action-card.is-critical { border-color:#fecaca; background:#fef2f2; }
.turnover-action-card__main { flex:1; min-width:0; }.turnover-action-card__title,.turnover-action-card__desc { display:block; }.turnover-action-card__title { color:#0f172a; font-size:24rpx; font-weight:700; }.turnover-action-card__desc { margin-top:5rpx; color:#64748b; font-size:21rpx; line-height:1.45; }
.asset-finance-foot { justify-content:space-between; gap:10rpx; margin:16rpx 0 10rpx; padding:18rpx 0; border-top:0; border-bottom:2rpx solid #f3f4f6; }
.asset-finance-foot .amount-box { flex:1; min-width:0; }
.asset-finance-foot .amt-value { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sale-adjust-note { margin:0 0 12rpx; padding:12rpx 15rpx; border-radius:12rpx; background:#fff7ed; color:#c2410c; font-size:21rpx; line-height:1.45; }
.form-card .value { word-break:break-all; }
.form-card .value.blue { color:#2563eb; }
.form-card .value.green { color:#16a34a; }
.form-card .value.red { color:#dc2626; }
.form-card .value.orange { color:#ea580c; }
.form-card .value.strong { font-weight:700; }
.section-title { font-size:28rpx; font-weight:600; color:#374151; padding:8rpx 28rpx 16rpx; }
.flow-card { background:#fff; border-radius:20rpx; padding:18rpx 22rpx; margin:0 24rpx 14rpx; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); }
.flow-head { display:flex; justify-content:space-between; margin-bottom:6rpx; }
.flow-action { font-size:26rpx; font-weight:600; color:#0f172a; }
.flow-time { font-size:22rpx; color:#94a3b8; }
.empty-tip { text-align:center; color:#94a3b8; font-size:26rpx; padding:40rpx 0; }
.account-section-title { padding-bottom:12rpx; }
.account-tip { margin:0 24rpx 14rpx; padding:16rpx 18rpx; border-radius:14rpx; background:#eff6ff; color:#64748b; font-size:22rpx; line-height:1.55; }
.account-card { margin:0 24rpx 14rpx; padding:20rpx 22rpx; border:2rpx solid #e5e7eb; border-radius:18rpx; background:#fff; box-shadow:0 2rpx 10rpx rgba(15,23,42,.035); }
.account-card__head,.account-card__result { display:flex; align-items:center; justify-content:space-between; gap:16rpx; }
.account-card__title { color:#0f172a; font-size:27rpx; font-weight:650; }
.account-card__state { flex:none; padding:5rpx 12rpx; border-radius:999rpx; background:#eff6ff; color:#2563eb; font-size:21rpx; }
.account-card__state.is-pending { background:#fff7ed; color:#ea580c; }
.account-card__state.is-settled { background:#f0fdf4; color:#16a34a; }
.account-card__result { margin-top:15rpx; color:#475569; font-size:23rpx; }
.account-card__result > text:first-child { flex:1; min-width:0; }
.account-card__amount { flex:none; color:#1e293b; font-size:27rpx; font-weight:700; }
.account-card__amount.is-income { color:#16a34a; }
.account-card__amount.is-expense { color:#dc2626; }
.account-card__meta { margin-top:11rpx; color:#64748b; font-size:22rpx; line-height:1.5; word-break:break-all; }
.account-card__time { margin-top:11rpx; color:#94a3b8; font-size:21rpx; }
.account-empty { margin:0 24rpx; padding:28rpx 0; border-radius:16rpx; background:#fff; }
.account-more { display:flex; align-items:center; justify-content:center; gap:8rpx; margin:0 24rpx 20rpx; padding:14rpx; color:#2563eb; font-size:23rpx; }
.bottom-actions { margin:32rpx; }
.bottom-action-row { display:flex; gap:16rpx; }
.primary-turnover-row { margin-bottom:16rpx; }
.bottom-action-btn { flex:1; min-width:0; }
.return-disabled-reason { display:flex; align-items:flex-start; gap:8rpx; margin-top:14rpx; color:#94a3b8; font-size:22rpx; line-height:1.5; }
.return-disabled-reason text { flex:1; }
.error-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:520rpx; padding:40rpx; box-sizing:border-box; }
.retry-btn { margin-top:24rpx; width:180rpx; }
.loading-wrap { display:flex; justify-content:center; align-items:center; height:400rpx; }
.action-popup { height:78vh; display:flex; flex-direction:column; background:#fff; }.action-popup--compact { height:auto; min-height:520rpx; }.action-popup__head { display:flex; align-items:flex-start; justify-content:space-between; gap:20rpx; padding:28rpx 30rpx 20rpx; border-bottom:1rpx solid #f1f5f9; }.action-popup__title,.action-popup__sub { display:block; }.action-popup__title { color:#0f172a; font-size:32rpx; font-weight:750; }.action-popup__sub { margin-top:5rpx; color:#94a3b8; font-size:21rpx; }.action-popup__body { flex:1; min-height:0; padding:12rpx 30rpx; box-sizing:border-box; }.action-popup__foot { padding:20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom)); border-top:1rpx solid #f1f5f9; }.popup-form-row { display:flex; align-items:center; gap:20rpx; min-height:96rpx; padding:0 30rpx; border-bottom:1rpx solid #f1f5f9; color:#334155; font-size:25rpx; }.action-popup__body .popup-form-row { padding:0; }
.buyout-summary { margin:22rpx 30rpx 8rpx; padding:18rpx 20rpx; border-radius:14rpx; background:#fff7ed; }
.buyout-summary text { display:block; color:#0f172a; font-size:26rpx; font-weight:650; }
.buyout-summary text + text { margin-top:7rpx; color:#64748b; font-size:21rpx; font-weight:400; }
.buyout-tip { margin:18rpx 30rpx 0; padding:15rpx 17rpx; border-radius:12rpx; background:#f8fafc; color:#64748b; font-size:21rpx; line-height:1.55; }
</style>
