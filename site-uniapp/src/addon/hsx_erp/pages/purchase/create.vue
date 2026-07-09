<template>
    <view class="erp-page">
        <RecyclePageHeader title="采购开单" />
        <scroll-view scroll-y style="height:calc(100vh - 100rpx)">
            <view class="form-wrap">
                <view class="meta-source-card" :class="goodsMeta.source === 'phone_shop' ? 'shop' : 'erp'">
                    <view class="meta-source__head">
                        <u-icon :name="goodsMeta.source === 'phone_shop' ? 'shopping-cart' : 'file-text'" :color="goodsMeta.source === 'phone_shop' ? '#3b6ef5' : '#64748b'" size="18" />
                        <text class="meta-source__title">{{ goodsMeta.source_label || '商品资料' }}</text>
                    </view>
                    <text class="meta-source__desc">{{ goodsMetaTip }}</text>
                </view>

                <!-- 供应商 -->
                <view class="form-section">
                    <view class="form-row" @click="showPartyPicker = true">
                        <text class="form-label required">供应商</text>
                        <view class="form-input" :class="{ 'form-input--on': form.party_id }">
                            <text :class="form.party_name ? 'input-text' : 'input-placeholder'">
                                {{ form.party_name || '点击选择供应商' }}
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

                <!-- 设备明细 -->
                <view class="form-section">
                    <view class="form-section__head">
                        <text class="form-section__title">设备明细（{{ form.items.length }} 台）</text>
                        <view class="form-section__actions">
                            <u-button size="small" plain type="primary" icon="scan" @click="scanAddDevice">扫码录入</u-button>
                            <u-button size="small" type="primary" icon="plus" @click="addDevice">添加设备</u-button>
                        </view>
                    </view>
                    <view v-for="(item, idx) in form.items" :key="idx" class="device-form-card">
                        <view class="device-form__head">
                            <text class="device-form__index">设备 {{ idx + 1 }}</text>
                            <u-icon name="close-circle" color="#94a3b8" size="20" @click="removeDevice(idx)" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">IMEI</text>
                            <u-input v-model="item.imei" placeholder="扫描或手输 IMEI" :customStyle="inputStyle" />
                            <view class="inline-scan" @click="scanDeviceImei(idx)">
                                <u-icon name="scan" color="#3b6ef5" size="20" />
                            </view>
                        </view>
                        <view class="form-row">
                            <text class="form-label required">型号</text>
                            <u-input v-model="item.model" placeholder="如：iPhone 15 128G 黑色" :customStyle="inputStyle" />
                        </view>
                        <ErpCategoryPopup
                            v-model="item.category_id"
                            label="设备分类"
                            placeholder="请选择设备分类"
                            layout="horizontal"
                            :embedded="true"
                            :clearable="true"
                            :api-path="categoryApiPath"
                            @change="payload => onCategoryChange(idx, payload)"
                            @clear="clearItemCategory(idx)"
                        />
                        <view class="form-row" @click="openSpecPicker(idx)">
                            <text class="form-label">商品规格</text>
                            <view class="form-input" :class="{ 'form-input--on': specSummary(item) }">
                                <text :class="specSummary(item) ? 'input-text' : 'input-placeholder'">
                                    {{ specSummary(item) || (item.category_id ? '点击选择内存/颜色/保修/成色' : '请先选择分类') }}
                                </text>
                                <view v-if="specSummary(item)" class="inline-clear" @click.stop="clearItemSpec(idx)">
                                    <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                                </view>
                                <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                            </view>
                        </view>
                        <view class="form-row" @click="openItemWarehouse(idx)">
                            <text class="form-label required">入库仓库</text>
                            <view class="form-input" :class="{ 'form-input--on': item.warehouse_id }">
                                <text :class="item.warehouse_name ? 'input-text' : 'input-placeholder'">
                                    {{ itemWarehouseText(item) || '点击选择仓库' }}
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
                        <view class="form-row">
                            <text class="form-label">预估售价</text>
                            <u-input v-model="item.estimate_sale_price" type="number" placeholder="0.00（选填）" :customStyle="inputStyle" />
                        </view>
                    </view>
                    <view class="add-hint" v-if="!form.items.length">点击「添加设备」开始录入</view>
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

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="float-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
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
            @change="onItemWarehouseChange"
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
import { getMobileCapitalAccounts, getMobileErpGoodsMeta } from '@/addon/hsx_erp/api/erp'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpSettleBar from '@/addon/hsx_erp/components/ErpSettleBar.vue'
import ErpGoodsSpecPopup from '@/addon/hsx_erp/components/ErpGoodsSpecPopup.vue'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import ErpCategoryPopup from '@/addon/hsx_erp/components/ErpCategoryPopup.vue'
import request from '@/utils/request'



const submitting = ref(false)
const accounts = ref<any[]>([])
const goodsMeta = ref<any>({})
const showPartyPicker = ref(false)
const showWhPicker = ref(false)
const showItemWhPicker = ref(false)
const showSpecPicker = ref(false)
const activeWarehouseItemIndex = ref(-1)
const activeSpecItemIndex = ref(-1)
const itemWarehousePicker = ref({
    warehouse_id: 0,
    warehouse_name: '',
    location_id: 0,
    location_name: '',
})
const form = ref({
    party_id: 0, party_name: '', m_no: '',
    warehouse_id: 0, warehouse_name: '',
    location_id: 0, location_name: '',
    settle_mode: 'credit' as 'credit' | 'cash',
    paid_amount: 0, capital_account_id: 0,
    remark: '',
    items: [] as any[],
})

const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }
const whDisplayText = computed(() => {
    if (!form.value.warehouse_name) return ''
    return form.value.location_name
        ? `${form.value.warehouse_name} / ${form.value.location_name}`
        : form.value.warehouse_name
})
const totalCost = computed(() => form.value.items.reduce((s, i) => s + Number(i.purchase_cost || 0), 0))
const activeSpecItem = computed(() => form.value.items[activeSpecItemIndex.value] || {})
const activeSpecMeta = computed(() => metaFor(activeSpecItem.value))
const categoryApiPath = computed(() => goodsMeta.value?.category_source === 'phone_shop' ? 'phone_shop/goods/category/tree' : 'erp/goods/category/tree')
const goodsMetaTip = computed(() => {
    const tips = Array.isArray(goodsMeta.value?.tips) ? goodsMeta.value.tips : []
    if (tips.length) return tips[0]
    return 'ERP 会优先复用站点内可用的商城资料，无法读取时自动使用本地资料。'
})
const canSubmit = computed(() =>
    form.value.party_id > 0 &&
    form.value.items.length > 0 &&
    form.value.items.every(i => i.imei && i.model && i.category_id && i.warehouse_id && Number(i.purchase_cost) > 0) &&
    (
        form.value.settle_mode !== 'cash' ||
        (
            Number(form.value.paid_amount || 0) > 0 &&
            Number(form.value.paid_amount || 0) <= totalCost.value &&
            Number(form.value.capital_account_id || 0) > 0
        )
    )
)

onMounted(async () => {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {}
    loadGoodsMeta()
})

async function loadGoodsMeta(categoryId = 0, categoryPath: any[] = []) {
    try {
        const res: any = await getMobileErpGoodsMeta({ category_id: categoryId, category_path: categoryPath })
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
    form.value.items.push(newDeviceItem())
}
function removeDevice(idx: number) { form.value.items.splice(idx, 1) }

function clearParty() {
    form.value.party_id = 0
    form.value.party_name = ''
}

function newDeviceItem(imei = '') {
    return {
        imei,
        model: '',
        spec: '',
        category_id: '',
        category_name: '',
        category_path: [] as any[],
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
    }
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
    if (!item.category_id) {
        uni.showToast({ title: '请先选择设备分类', icon: 'none' })
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
    item.goods_meta = await loadGoodsMeta(Number(item.category_id || 0), item.category_path || [])
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
        form.value.items.push(newDeviceItem(imei))
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

async function onCategoryChange(idx: number, payload: any) {
    const item = form.value.items[idx]
    if (!item) return
    item.category_id = payload?.category_id || ''
    item.category_name = payload?.node?.category_full_name || payload?.node?.category_name || ''
    item.category_path = payload?.category_path || []
    item.category_names = categoryNamesFromPayload(payload)
    item.selected_specs = {}
    item.selected_grade = null
    item.color = ''
    item.battery = ''
    item.warranty = 0
    rebuildItemTitles(item, true)
    if (item.category_id) {
        item.goods_meta = await loadGoodsMeta(Number(item.category_id), item.category_path)
        rebuildItemTitles(item, true)
    }
}

function clearItemCategory(idx: number) {
    const item = form.value.items[idx]
    if (!item) return
    item.category_id = ''
    item.category_name = ''
    item.category_path = []
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

function categoryNamesFromPayload(payload: any): string[] {
    const nodes = Array.isArray(payload?.category_nodes) ? payload.category_nodes : []
    if (nodes.length) {
        return nodes.map((node: any) => String(node?.category_name || '').trim()).filter(Boolean)
    }
    const node = payload?.node || {}
    const full = String(node.category_full_name || '').trim()
    if (full) {
        return full.split(/[>\-/\\｜|,，\s]+/).map((name: string) => name.trim()).filter(Boolean)
    }
    const name = String(node.category_name || '').trim()
    return name ? [name] : []
}

function categoryTitleByRule(item: any): string {
    const names = Array.isArray(item.category_names) && item.category_names.length
        ? item.category_names
        : String(item.category_name || '').split(/[>\-/\\｜|,，\s]+/).filter(Boolean)
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
    const parts = [categoryTitleByRule(item)]
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
    if (!canSubmit.value) {
        uni.showToast({ title: '请完善供应商、设备和付款信息', icon: 'none' })
        return
    }
    submitting.value = true
    try {
        await request.post('erp/purchase/create', {
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
            remark: form.value.remark,
            items: form.value.items.map(i => ({
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
                category_id: Number(i.category_id || 0),
                category_name: i.category_name || '',
                category_path: i.category_path || [],
                warehouse_id: Number(i.warehouse_id || 0),
                warehouse_name: i.warehouse_name || '',
                location_id: Number(i.location_id || 0),
                location_name: i.location_name || '',
                purchase_cost: Number(i.purchase_cost),
                estimate_sale_price: Number(i.estimate_sale_price || 0),
            }))
        })
        uni.showToast({ title: '采购开单成功', icon: 'success' })
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
.form-section { background:#fff; border-radius:28rpx; padding:0 28rpx; overflow:hidden; box-shadow:0 2rpx 12rpx rgba(0,0,0,.04); }
.form-section__head { display:flex; align-items:center; justify-content:space-between; padding-top:20rpx; margin-bottom:16rpx; }
.form-section__actions { display:flex; align-items:center; gap:12rpx; }
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
.device-form-card { border:2rpx solid #e2e8f0; border-radius:20rpx; padding:0 18rpx 18rpx; margin-bottom:18rpx; }
.device-form__head { height:72rpx; display:flex; align-items:center; justify-content:space-between; border-bottom:2rpx solid #f3f4f6; margin-bottom:4rpx; }
.device-form__index { font-size:26rpx; font-weight:700; color:#0f172a; }
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
</style>
