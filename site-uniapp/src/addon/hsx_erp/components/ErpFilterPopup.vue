<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="close">
        <view class="erp-filter">
            <view class="filter-head">
                <text class="filter-title">{{ title }}</text>
                <view class="close-btn" @click="close">
                    <u-icon name="close" color="#64748b" size="20" />
                </view>
            </view>

            <scroll-view scroll-y class="filter-body">
                <view v-for="field in fields" :key="field.key" class="filter-field">
                    <view v-if="field.type !== 'category'" class="field-label-row">
                        <text class="field-label">{{ field.label }}</text>
                        <view v-if="hasFieldValue(field)" class="field-clear" @click="clearField(field)">
                            <u-icon name="close-circle-fill" color="#94a3b8" size="16" />
                        </view>
                    </view>

                    <view
                        v-if="field.type === 'text' || field.type === 'number'"
                        class="clearable-control"
                        :class="{ 'clearable-control--on': hasFieldValue(field) }"
                    >
                        <u-input
                            v-model="localValue[field.key]"
                            :type="field.type === 'number' ? 'number' : 'text'"
                            :placeholder="field.placeholder || ('请输入' + field.label)"
                            :customStyle="inputStyle"
                        />
                        <view v-if="hasFieldValue(field)" class="inline-clear" @click="clearField(field)">
                            <u-icon name="close-circle-fill" color="#cbd5e1" size="17" />
                        </view>
                    </view>

                    <view v-else-if="field.type === 'range'" class="range-row">
                        <u-input
                            v-model="localValue[field.minKey || field.key + '_min']"
                            type="number"
                            :placeholder="field.minPlaceholder || '最小值'"
                            :customStyle="inputStyle"
                        />
                        <text class="range-split">至</text>
                        <u-input
                            v-model="localValue[field.maxKey || field.key + '_max']"
                            type="number"
                            :placeholder="field.maxPlaceholder || '最大值'"
                            :customStyle="inputStyle"
                        />
                    </view>

                    <view v-else-if="field.type === 'dateRange'" class="range-row">
                        <view class="date-box" :class="{ empty: !localValue[field.startKey || 'start_at'] }" @click="openDatePicker(field, field.startKey || 'start_at')">
                            {{ localValue[field.startKey || 'start_at'] || '开始日期' }}
                        </view>
                        <text class="range-split">至</text>
                        <view class="date-box" :class="{ empty: !localValue[field.endKey || 'end_at'] }" @click="openDatePicker(field, field.endKey || 'end_at')">
                            {{ localValue[field.endKey || 'end_at'] || '结束日期' }}
                        </view>
                    </view>

                    <view v-else-if="field.type === 'select'" class="option-wrap">
                        <view
                            v-for="option in field.options || []"
                            :key="String(option.value)"
                            class="option-chip"
                            :class="{ 'option-chip--on': String(localValue[field.key] || '') === String(option.value) }"
                            @click="localValue[field.key] = option.value"
                        >
                            <text>{{ erpOptionLabel(option.label, option.value) }}</text>
                            <u-icon
                                v-if="String(localValue[field.key] || '') === String(option.value)"
                                name="checkmark-circle-fill"
                                color="#3b6ef5"
                                size="14"
                            />
                        </view>
                    </view>

                    <view v-else-if="field.type === 'party'" class="select-box" :class="{ 'select-box--on': hasFieldValue(field) }" @click="openPartyPicker(field)">
                        <text :class="localValue[field.labelKey || field.key + '_name'] ? 'select-text' : 'select-placeholder'">
                            {{ localValue[field.labelKey || field.key + '_name'] || field.placeholder || '请选择' + field.label }}
                        </text>
                        <view v-if="hasFieldValue(field)" class="inline-clear" @click.stop="clearField(field)">
                            <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                        </view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>

                    <view v-else-if="field.type === 'staff'" class="select-box" :class="{ 'select-box--on': hasFieldValue(field) }" @click="openStaffPicker(field)">
                        <text :class="localValue[field.labelKey || field.key + '_name'] ? 'select-text' : 'select-placeholder'">
                            {{ localValue[field.labelKey || field.key + '_name'] ? staffName({ name: localValue[field.labelKey || field.key + '_name'] }) : (field.placeholder || '请选择' + field.label) }}
                        </text>
                        <view v-if="hasFieldValue(field)" class="inline-clear" @click.stop="clearField(field)">
                            <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                        </view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>

                    <CategoryPicker
                        v-else-if="field.type === 'category'"
                        :model-value="localValue[field.key]"
                        :label="field.label"
                        :placeholder="field.placeholder || '请选择分类'"
                        :layout="field.layout || layout"
                        :embedded="true"
                        :clearable="true"
                        @update:modelValue="value => localValue[field.key] = value"
                        @change="payload => onCategoryChange(field, payload)"
                        @clear="clearField(field)"
                    />

                    <view v-else-if="field.type === 'warehouse'" class="select-box" :class="{ 'select-box--on': hasFieldValue(field) }" @click="openWarehousePicker(field)">
                        <text :class="localValue[field.labelKey || field.key + '_name'] ? 'select-text' : 'select-placeholder'">
                            {{ warehouseText(field) || field.placeholder || '请选择' + field.label }}
                        </text>
                        <view v-if="hasFieldValue(field)" class="inline-clear" @click.stop="clearField(field)">
                            <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                        </view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>
                </view>
            </scroll-view>

            <view class="filter-footer">
                <view class="action-btn action-btn--minor">
                    <u-button @click="reset">重置</u-button>
                </view>
                <view class="action-btn action-btn--major">
                    <u-button type="primary" @click="confirm">完成筛选</u-button>
                </view>
            </view>
        </view>
    </u-popup>

    <u-popup :show="partyPicker.show" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="partyPicker.show = false">
        <view class="picker-panel">
            <view class="filter-head">
                <text class="filter-title">{{ partyPicker.title }}</text>
                <view class="close-btn" @click="partyPicker.show = false">
                    <u-icon name="close" color="#64748b" size="20" />
                </view>
            </view>
            <view class="picker-search">
                <u-icon name="search" color="#94a3b8" size="18" />
                <input
                    v-model="partyPicker.keyword"
                    class="picker-input"
                    placeholder="搜索名称/手机号/会员号"
                    placeholder-class="select-placeholder"
                    confirm-type="search"
                    @confirm="loadPartyOptions"
                />
                <view v-if="partyPicker.keyword" class="inline-clear" @click="partyPicker.keyword = ''; loadPartyOptions()">
                    <u-icon name="close-circle-fill" color="#cbd5e1" size="17" />
                </view>
                <view>
                <u-button  type="primary" @click="loadPartyOptions">搜索</u-button></view>
            </view>
            <scroll-view scroll-y class="picker-list">
                <view v-if="partyPicker.currentKey" class="picker-item clear-item" @click="clearParty">
                    <text>不限{{ partyPicker.shortTitle }}</text>
                    <u-icon name="close-circle" color="#94a3b8" size="18" />
                </view>
                <view v-for="item in partyPicker.options" :key="item.id" class="picker-item" @click="selectParty(item)">
                    <view class="picker-main">
                        <text class="picker-name">{{ item.party_name || '-' }}</text>
                        <text class="picker-sub">{{ [item.contact_name, item.contact_mobile, item.m_no].filter(Boolean).join(' / ') || item.party_no || '' }}</text>
                    </view>
                    <u-icon v-if="Number(localValue[partyPicker.currentKey]) === Number(item.id)" name="checkbox-mark" color="#3b6ef5" size="20" />
                </view>
                <view v-if="partyPicker.loading" class="picker-tip">加载中...</view>
                <view v-else-if="!partyPicker.options.length" class="picker-tip">暂无可选往来单位</view>
            </scroll-view>
        </view>
    </u-popup>

    <u-popup :show="staffPicker.show" mode="bottom" :safe-area-inset-bottom="true" border-radius="24" @close="staffPicker.show = false">
        <view class="picker-panel">
            <view class="filter-head">
                <text class="filter-title">{{ staffPicker.title }}</text>
                <view class="close-btn" @click="staffPicker.show = false">
                    <u-icon name="close" color="#64748b" size="20" />
                </view>
            </view>
            <view class="picker-search">
                <u-icon name="search" color="#94a3b8" size="18" />
                <input
                    v-model="staffPicker.keyword"
                    class="picker-input"
                    placeholder="搜索姓名/账号"
                    placeholder-class="select-placeholder"
                    confirm-type="search"
                    @confirm="loadStaffOptions"
                />
                <view v-if="staffPicker.keyword" class="inline-clear" @click="staffPicker.keyword = ''; loadStaffOptions()">
                    <u-icon name="close-circle-fill" color="#cbd5e1" size="17" />
                </view>
                <view><u-button type="primary" @click="loadStaffOptions">搜索</u-button></view>
            </view>
            <scroll-view scroll-y class="picker-list">
                <view v-if="staffPicker.currentKey" class="picker-item clear-item" @click="clearStaff">
                    <text>不限{{ staffPicker.shortTitle }}</text>
                    <u-icon name="close-circle" color="#94a3b8" size="18" />
                </view>
                <view v-for="item in staffPicker.options" :key="item.uid" class="picker-item" @click="selectStaff(item)">
                    <view class="picker-main">
                        <text class="picker-name">{{ staffName(item) }}</text>
                        <text class="picker-sub">{{ [item.real_name, item.username, item.mobile].map(staffText).filter(Boolean).join(' / ') || '姓名与联系方式待完善' }}</text>
                    </view>
                    <u-icon v-if="Number(localValue[staffPicker.currentKey]) === Number(item.uid)" name="checkbox-mark" color="#3b6ef5" size="20" />
                </view>
                <view v-if="staffPicker.loading" class="picker-tip">加载中...</view>
                <view v-else-if="!staffPicker.options.length" class="picker-tip">暂无可选管理员</view>
            </scroll-view>
        </view>
    </u-popup>

    <ErpWarehousePopup
        v-model:show="warehousePicker.show"
        v-model:warehouse-id="warehousePicker.warehouse_id"
        v-model:warehouse-name="warehousePicker.warehouse_name"
        v-model:location-id="warehousePicker.location_id"
        v-model:location-name="warehousePicker.location_name"
        @change="onWarehouseChange"
    />

    <u-datetime-picker
        :show="datePicker.show"
        v-model="datePicker.value"
        mode="date"
        :closeOnClickOverlay="true"
        @confirm="onDateConfirm"
        @cancel="datePicker.show = false"
        @close="datePicker.show = false"
    />

</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { getMobileCounterpartyOptions, getMobileStaffOptions } from '@/addon/hsx_erp/api/erp'
import CategoryPicker from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import { erpOptionLabel } from '@/addon/hsx_erp/utils/display'

const staffText = (value: any) => {
    const text = String(value || '').trim()
    return /^(?:UID\s*[:：#＃]?\s*\d+|(?:员工|管理员|用户|操作人)\s*[#＃]\s*\d+)$/i.test(text) ? '' : text
}
const staffName = (item: any) => [item.name, item.real_name, item.username].map(staffText).find(Boolean) || '姓名未维护'

type Option = { label: string; value: string | number }
type Field = {
    key: string
    label: string
    type: 'text' | 'number' | 'range' | 'dateRange' | 'select' | 'party' | 'staff' | 'category' | 'warehouse'
    placeholder?: string
    options?: Option[]
    roleType?: 'supplier' | 'customer' | 'all'
    labelKey?: string
    locationKey?: string
    locationLabelKey?: string
    minKey?: string
    maxKey?: string
    startKey?: string
    endKey?: string
    minPlaceholder?: string
    maxPlaceholder?: string
    layout?: 'horizontal' | 'vertical'
}

const props = withDefaults(defineProps<{
    show: boolean
    modelValue: Record<string, any>
    title?: string
    fields: Field[]
    layout?: 'horizontal' | 'vertical'
}>(), {
    show: false,
    modelValue: () => ({}),
    title: '筛选',
    fields: () => [],
    layout: 'vertical',
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:modelValue', v: Record<string, any>): void
    (e: 'confirm', v: Record<string, any>): void
    (e: 'reset'): void
}>()

const localValue = ref<Record<string, any>>({})
const layout = props.layout
const inputStyle = { background: '#f8fafc', borderRadius: '12rpx', padding: '10rpx 16rpx' }
const partyPicker = reactive({
    show: false,
    loading: false,
    title: '选择往来单位',
    shortTitle: '往来单位',
    keyword: '',
    currentKey: '',
    currentLabelKey: '',
    roleType: 'all',
    options: [] as any[],
})
const warehousePicker = reactive({
    show: false,
    currentKey: '',
    currentLabelKey: '',
    currentLocationKey: '',
    currentLocationLabelKey: '',
    warehouse_id: 0,
    warehouse_name: '',
    location_id: 0,
    location_name: '',
})
const staffPicker = reactive({
    show: false,
    loading: false,
    title: '选择管理员',
    shortTitle: '管理员',
    keyword: '',
    currentKey: '',
    currentLabelKey: '',
    options: [] as any[],
})
const datePicker = reactive({
    show: false,
    key: '',
    value: Date.now(),
})

watch(() => props.show, (visible) => {
    if (visible) localValue.value = { ...props.modelValue }
})

watch(() => props.modelValue, (value) => {
    if (!props.show) localValue.value = { ...value }
}, { deep: true, immediate: true })

function close() {
    emit('update:show', false)
}

function reset() {
    localValue.value = {}
    emit('update:modelValue', {})
    emit('reset')
}

function fieldValueKeys(field: Field) {
    if (field.type === 'range') return [field.minKey || `${field.key}_min`, field.maxKey || `${field.key}_max`]
    if (field.type === 'dateRange') return [field.startKey || 'start_at', field.endKey || 'end_at']
    if (field.type === 'warehouse') {
        return [
            field.key,
            field.labelKey || `${field.key}_name`,
            field.locationKey || 'location_id',
            field.locationLabelKey || 'location_name',
        ]
    }
    return [field.key, field.labelKey || `${field.key}_name`]
}

function hasFieldValue(field: Field) {
    return fieldValueKeys(field).some((key) => {
        const value = localValue.value[key]
        return value !== '' && value !== undefined && value !== null && value !== 0
    })
}

function clearField(field: Field) {
    fieldValueKeys(field).forEach((key) => {
        delete localValue.value[key]
    })
}

function confirm() {
    const cleaned: Record<string, any> = {}
    Object.keys(localValue.value || {}).forEach((key) => {
        const value = localValue.value[key]
        if (value !== '' && value !== undefined && value !== null) cleaned[key] = value
    })
    emit('update:modelValue', cleaned)
    emit('confirm', cleaned)
    emit('update:show', false)
}

function openPartyPicker(field: Field) {
    partyPicker.title = field.label || '选择往来单位'
    partyPicker.shortTitle = field.label || '往来单位'
    partyPicker.currentKey = field.key
    partyPicker.currentLabelKey = field.labelKey || `${field.key}_name`
    partyPicker.roleType = field.roleType || 'all'
    partyPicker.keyword = ''
    partyPicker.show = true
    loadPartyOptions()
}

async function loadPartyOptions() {
    partyPicker.loading = true
    try {
        const res: any = await getMobileCounterpartyOptions({
            keyword: partyPicker.keyword,
            role_type: partyPicker.roleType,
        })
        partyPicker.options = Array.isArray(res?.data) ? res.data : []
    } catch {
        partyPicker.options = []
    } finally {
        partyPicker.loading = false
    }
}

function selectParty(item: any) {
    localValue.value[partyPicker.currentKey] = Number(item.id || 0)
    localValue.value[partyPicker.currentLabelKey] = item.party_name || ''
    partyPicker.show = false
}

function clearParty() {
    delete localValue.value[partyPicker.currentKey]
    delete localValue.value[partyPicker.currentLabelKey]
    partyPicker.show = false
}

function openStaffPicker(field: Field) {
    staffPicker.title = field.label || '选择管理员'
    staffPicker.shortTitle = field.label || '管理员'
    staffPicker.currentKey = field.key
    staffPicker.currentLabelKey = field.labelKey || `${field.key}_name`
    staffPicker.keyword = ''
    staffPicker.show = true
    loadStaffOptions()
}

async function loadStaffOptions() {
    staffPicker.loading = true
    try {
        const res: any = await getMobileStaffOptions({ keyword: staffPicker.keyword })
        staffPicker.options = Array.isArray(res?.data?.users) ? res.data.users : []
    } catch {
        staffPicker.options = []
    } finally {
        staffPicker.loading = false
    }
}

function selectStaff(item: any) {
    localValue.value[staffPicker.currentKey] = Number(item.uid || 0)
    localValue.value[staffPicker.currentLabelKey] = item.name || item.real_name || item.username || ''
    staffPicker.show = false
}

function clearStaff() {
    delete localValue.value[staffPicker.currentKey]
    delete localValue.value[staffPicker.currentLabelKey]
    staffPicker.show = false
}

function onCategoryChange(field: Field, payload: any) {
    localValue.value[field.key] = Number(payload?.catalog_product_id || payload?.site_product_id || 0)
    localValue.value[field.labelKey || `${field.key}_name`] = payload?.product_name || payload?.label || ''
}

function openWarehousePicker(field: Field) {
    warehousePicker.currentKey = field.key
    warehousePicker.currentLabelKey = field.labelKey || `${field.key}_name`
    warehousePicker.currentLocationKey = field.locationKey || 'location_id'
    warehousePicker.currentLocationLabelKey = field.locationLabelKey || 'location_name'
    warehousePicker.warehouse_id = Number(localValue.value[warehousePicker.currentKey] || 0)
    warehousePicker.warehouse_name = localValue.value[warehousePicker.currentLabelKey] || ''
    warehousePicker.location_id = Number(localValue.value[warehousePicker.currentLocationKey] || 0)
    warehousePicker.location_name = localValue.value[warehousePicker.currentLocationLabelKey] || ''
    warehousePicker.show = true
}

function onWarehouseChange(warehouse: any, location: any) {
    localValue.value[warehousePicker.currentKey] = Number(warehouse?.id || 0)
    localValue.value[warehousePicker.currentLabelKey] = warehouse?.warehouse_name || ''
    localValue.value[warehousePicker.currentLocationKey] = Number(location?.id || 0)
    localValue.value[warehousePicker.currentLocationLabelKey] = location?.location_name || ''
}

function warehouseText(field: Field) {
    const warehouseName = localValue.value[field.labelKey || `${field.key}_name`] || ''
    const locationName = localValue.value[field.locationLabelKey || 'location_name'] || ''
    if (!warehouseName) return ''
    return locationName ? `${warehouseName} / ${locationName}` : warehouseName
}

function openDatePicker(_field: Field, key: string) {
    datePicker.key = key
    const current = localValue.value[key]
    datePicker.value = current ? new Date(`${current} 00:00:00`).getTime() : Date.now()
    datePicker.show = true
}

function onDateConfirm(event: any) {
    const value = typeof event === 'object' ? (event.value ?? event) : event
    localValue.value[datePicker.key] = formatDate(Number(value))
    datePicker.show = false
}

function formatDate(timestamp: number) {
    const date = new Date(timestamp)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
</script>

<style scoped lang="scss">
.erp-filter {
    max-height: 82vh;
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    overflow: hidden;
}
.filter-head {
    height: 96rpx;
    padding: 0 28rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}
.filter-title {
    font-size: 34rpx;
    font-weight: 700;
    color: #0f172a;
}
.close-btn {
    width: 56rpx;
    height: 56rpx;
    border-radius: 28rpx;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
}
.filter-body {
    max-height: 58vh;
    padding: 8rpx 28rpx 24rpx;
    box-sizing: border-box;
}
.filter-field {
    padding-top: 24rpx;
}
.field-label-row {
    min-height: 40rpx;
    margin-bottom: 12rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}
.field-label {
    font-size: 26rpx;
    color: #334155;
    font-weight: 600;
}
.field-clear,
.inline-clear {
    width: 40rpx;
    height: 40rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.clearable-control {
    position: relative;
}
.clearable-control--on :deep(.u-input) {
    border: 2rpx solid #3b6ef5;
    background: #f8fbff !important;
}
.clearable-control .inline-clear {
    position: absolute;
    right: 14rpx;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
}
.range-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 48rpx minmax(0, 1fr);
    gap: 12rpx;
    align-items: center;
}
.range-split {
    text-align: center;
    color: #94a3b8;
    font-size: 24rpx;
}
.date-box {
    height: 72rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    padding: 0 18rpx;
    display: flex;
    align-items: center;
    color: #0f172a;
    font-size: 26rpx;
}
.date-box.empty {
    color: #c0c4cc;
}
.option-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 14rpx;
}
.option-chip {
    min-height: 56rpx;
    padding: 0 24rpx;
    border-radius: 28rpx;
    background: #f8fafc;
    color: #475569;
    font-size: 25rpx;
    display: flex;
    align-items: center;
    gap: 8rpx;
    border: 2rpx solid transparent;
}
.option-chip--on {
    color: #3b6ef5;
    background: #eff3ff;
    border-color: #3b6ef5;
    font-weight: 600;
}
.select-box {
    min-height: 72rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    padding: 0 18rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    border: 2rpx solid transparent;
    box-sizing: border-box;
}
.select-box--on {
    background: #f8fbff;
    border-color: #3b6ef5;
}
.select-text {
    flex: 1;
    min-width: 0;
    color: #0f172a;
    font-size: 26rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.select-placeholder {
    flex: 1;
    min-width: 0;
    color: #c0c4cc;
    font-size: 26rpx;
}
.filter-footer {
    display: flex;
    gap: 18rpx;
    padding: 20rpx 28rpx calc(20rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    margin: 10rpx;
}
.action-btn {
    min-width: 0;
}
.action-btn--minor {
    flex: 1;
}
.action-btn--major {
    flex: 2;
}
.picker-panel {
    height: 74vh;
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.picker-search {
    margin: 22rpx 28rpx 12rpx;
    height: 72rpx;
    border-radius: 36rpx;
    background: #f8fafc;
    padding: 0 18rpx;
    display: flex;
    align-items: center;
    gap: 12rpx;
}
.picker-input {
    flex: 1;
    min-width: 0;
    font-size: 26rpx;
    color: #0f172a;
}
.crumb-row {
    height: 48rpx;
    padding: 0 28rpx;
    white-space: nowrap;
    box-sizing: border-box;
}
.crumb,
.crumb-split {
    font-size: 24rpx;
    color: #64748b;
}
.crumb.active {
    color: #3b6ef5;
    font-weight: 600;
}
.picker-list {
    flex: 1;
    min-height: 0;
    padding: 0 28rpx 24rpx;
    box-sizing: border-box;
}
.picker-item {
    min-height: 92rpx;
    border-bottom: 1rpx solid #eef2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}
.clear-item {
    color: #64748b;
    font-size: 26rpx;
}
.picker-main {
    flex: 1;
    min-width: 0;
    padding: 16rpx 0;
}
.picker-name {
    display: block;
    font-size: 28rpx;
    color: #0f172a;
    font-weight: 600;
}
.picker-sub {
    display: block;
    margin-top: 6rpx;
    font-size: 23rpx;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.drill-btn {
    height: 48rpx;
    padding: 0 14rpx;
    border-radius: 24rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 23rpx;
    display: flex;
    align-items: center;
    gap: 4rpx;
}
.picker-tip {
    padding: 48rpx 0;
    text-align: center;
    color: #94a3b8;
    font-size: 25rpx;
}
</style>
