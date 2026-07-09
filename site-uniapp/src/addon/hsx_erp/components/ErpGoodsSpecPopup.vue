<template>
    <u-popup :show="show" mode="bottom" round="20" :closeOnClickOverlay="true" @close="close">
        <view class="spec-popup">
            <view class="popup-head">
                <view class="popup-head__main">
                    <text class="popup-title">商品规格</text>
                    <text class="popup-sub">{{ sourceText }}</text>
                </view>
                <view class="popup-close" @click="close">
                    <u-icon name="close" color="#64748b" size="20" />
                </view>
            </view>

            <scroll-view scroll-y class="popup-body">
                <view class="title-card">
                    <view class="title-card__head">
                        <text class="title-card__label">型号建议</text>
                        <view class="title-card__action" @click="applyTitle">同步到型号</view>
                    </view>
                    <text class="title-card__value">{{ modelTitle || '请选择分类/规格生成型号' }}</text>
                    <text class="title-card__tip">默认按分类路径生成型号，内存和颜色进入标题；成色、电池、保修保留为规格信息。</text>
                </view>

                <view v-for="group in specGroups" :key="group.key" class="spec-group">
                    <view class="spec-group__head">
                        <text class="spec-group__label">{{ group.label }}</text>
                        <view class="spec-group__actions">
                            <text class="spec-group__badge" :class="{ muted: !group.title_part }">{{ group.title_part ? '标题' : '规格' }}</text>
                            <view class="manage-link" @click="openManageItems(group)">
                                <u-icon name="edit-pen" color="#3b6ef5" size="13" />
                                <text>管理</text>
                            </view>
                        </view>
                    </view>
                    <view class="chip-list">
                        <view
                            v-for="option in group.items"
                            :key="`${group.key}_${option.value}`"
                            class="chip"
                            :class="{ 'chip--on': localSpecs[group.key]?.value === option.value }"
                            @click="toggleSpec(group, option)"
                        >
                            <text>{{ option.label }}</text>
                            <u-icon v-if="localSpecs[group.key]?.value === option.value" name="checkmark" color="#3b6ef5" size="13" />
                        </view>
                    </view>
                </view>

                <view v-if="gradeOptions.length" class="spec-group">
                    <view class="spec-group__head">
                        <text class="spec-group__label">成色/等级</text>
                        <view class="spec-group__actions">
                            <text class="spec-group__badge muted">规格</text>
                            <view class="manage-link" @click="openManageGrades">
                                <u-icon name="edit-pen" color="#3b6ef5" size="13" />
                                <text>管理</text>
                            </view>
                        </view>
                    </view>
                    <view class="chip-list">
                        <view
                            v-for="option in gradeOptions"
                            :key="`grade_${option.value}`"
                            class="chip"
                            :class="{ 'chip--on': localGrade?.value === option.value }"
                            @click="toggleGrade(option)"
                        >
                            <text>{{ option.label }}</text>
                            <u-icon v-if="localGrade?.value === option.value" name="checkmark" color="#3b6ef5" size="13" />
                        </view>
                    </view>
                </view>

                <view class="spec-group">
                    <view class="spec-group__head">
                        <text class="spec-group__label">颜色</text>
                        <text class="spec-group__badge">标题</text>
                    </view>
                    <u-input v-model="localColor" placeholder="输入或检索颜色，如：橙色" :customStyle="inputStyle" clearable />
                    <view v-if="colorOptions.length" class="chip-list mt">
                        <view
                            v-for="option in colorOptions"
                            :key="`color_${option.value}`"
                            class="chip"
                            :class="{ 'chip--on': localColor === option.value }"
                            @click="localColor = option.value"
                        >
                            <text>{{ option.label }}</text>
                            <u-icon v-if="localColor === option.value" name="checkmark" color="#3b6ef5" size="13" />
                        </view>
                    </view>
                </view>

                <view class="spec-group">
                    <view class="spec-group__head">
                        <text class="spec-group__label">电池</text>
                        <text class="spec-group__badge muted">数字</text>
                    </view>
                    <u-input v-model="localBattery" type="number" placeholder="输入电池效率，如：88" :customStyle="inputStyle">
                        <template #suffix>
                            <text class="input-suffix">%</text>
                        </template>
                    </u-input>
                </view>

                <view class="spec-group">
                    <view class="spec-group__head">
                        <text class="spec-group__label">保修</text>
                        <text class="spec-group__badge muted">{{ warrantyStatus === 'active' ? '日期' : '状态' }}</text>
                    </view>
                    <view class="warranty-tabs">
                        <view class="warranty-tab" :class="{ 'warranty-tab--on': warrantyStatus === 'active' }" @click="setWarrantyStatus('active')">在保</view>
                        <view class="warranty-tab" :class="{ 'warranty-tab--on': warrantyStatus === 'expired' }" @click="setWarrantyStatus('expired')">过保</view>
                    </view>
                    <view v-if="warrantyStatus === 'active'" class="date-field" :class="{ 'date-field--on': localWarrantyAt }" @click="showWarrantyPicker = true">
                        <u-icon name="calendar" :color="localWarrantyAt ? '#3b6ef5' : '#94a3b8'" size="18" />
                        <text :class="localWarrantyAt ? 'date-field__text' : 'date-field__placeholder'">{{ warrantyText || '选择保修截止时间' }}</text>
                        <view v-if="localWarrantyAt" class="date-clear" @click.stop="localWarrantyAt = 0">
                            <u-icon name="close-circle-fill" color="#94a3b8" size="17" />
                        </view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>
                </view>

                <view v-if="!specGroups.length && !gradeOptions.length" class="empty">
                    <u-empty mode="data" text="暂无可选规格" />
                </view>
            </scroll-view>

            <view class="popup-actions">
                <view class="action-btn action-btn--minor">
                    <u-button shape="circle" @click="reset">清除</u-button>
                </view>
                <view class="action-btn action-btn--major">
                    <u-button type="primary" shape="circle" @click="confirm">确定</u-button>
                </view>
            </view>
        </view>
    </u-popup>
    <up-calendar
        :show="showWarrantyPicker"
        mode="single"
        :defaultDate="warrantyDefaultDate"
        :monthNum="24"
        @confirm="confirmWarranty"
        @close="showWarrantyPicker = false"
    />

    <u-popup :show="manageShow" mode="bottom" round="20" :closeOnClickOverlay="true" @close="closeManage">
        <view class="manage-pop">
            <view class="manage-head">
                <text class="h-btn" @click="closeManage">取消</text>
                <view class="manage-head__main">
                    <text class="manage-title">{{ manageTitle }}</text>
                    <text class="manage-sub">{{ sourceText }}</text>
                </view>
                <text class="h-btn h-btn--ok" @click="saveManage">保存</text>
            </view>
            <view class="manage-form">
                <view class="manage-field">
                    <text class="manage-label"><text class="req">*</text>{{ manageType === 'grade' ? '成色名称' : '规格值' }}</text>
                    <u-input v-model="manageName" :placeholder="manageType === 'grade' ? '如：99新|A级' : '如：256GB'" border="none" inputAlign="right" maxlength="40" />
                </view>
                <view class="manage-field">
                    <text class="manage-label">排序</text>
                    <u-input v-model="manageSort" type="number" placeholder="数字越小越靠前" border="none" inputAlign="right" />
                </view>
                <view class="manage-save">
                    <view class="manage-save__btn">
                        <u-button :loading="manageSaving" type="primary" shape="circle" @click="saveManage">{{ manageEditId ? '保存修改' : '新增' }}</u-button>
                    </view>
                    <view v-if="manageEditId" class="manage-save__btn minor">
                        <u-button shape="circle" @click="resetManageForm">取消编辑</u-button>
                    </view>
                </view>
            </view>
            <scroll-view scroll-y class="manage-list">
                <view v-for="row in manageRows" :key="row.id" class="manage-row">
                    <view class="manage-row__main">
                        <text class="manage-row__name">{{ row.label }}</text>
                        <text class="manage-row__sort">排序 {{ row.sort || 0 }}</text>
                    </view>
                    <view class="manage-row__actions">
                        <text class="manage-row__action" @click="editManage(row)">编辑</text>
                        <text class="manage-row__action danger" @click="deleteManage(row)">删除</text>
                    </view>
                </view>
                <view v-if="!manageRows.length" class="manage-empty">暂无数据</view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import request from '@/utils/request'

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:show', 'confirm', 'refresh'])

const localSpecs = ref<Record<string, any>>({})
const localGrade = ref<any>(null)
const localModel = ref('')
const localColor = ref('')
const localBattery = ref('')
const localWarrantyAt = ref(0)
const warrantyStatus = ref<'active' | 'expired'>('expired')
const showWarrantyPicker = ref(false)
const manageShow = ref(false)
const manageType = ref<'item' | 'grade'>('item')
const manageGroup = ref<any>(null)
const manageEditId = ref(0)
const manageName = ref('')
const manageSort = ref('')
const manageSaving = ref(false)

const inputStyle = { background: '#f8fafc', borderRadius: '12rpx', padding: '8rpx 18rpx' }

watch(() => props.show, (visible) => {
    if (!visible) return
    localSpecs.value = { ...((props.item as any)?.selected_specs || {}) }
    localGrade.value = (props.item as any)?.selected_grade || null
    localModel.value = (props.item as any)?.model || ''
    localColor.value = (props.item as any)?.color || ''
    localBattery.value = (props.item as any)?.battery || ''
    localWarrantyAt.value = Number((props.item as any)?.warranty || 0)
    warrantyStatus.value = localWarrantyAt.value > 0 ? 'active' : 'expired'
})

watch(() => props.meta, () => {
    if (!manageShow.value || manageType.value !== 'item' || !manageGroup.value) return
    const currentId = Number(manageGroup.value.source_id || manageGroup.value.id || 0)
    const currentKey = manageGroup.value.key
    const fresh = specGroups.value.find((group: any) => {
        return Number(group.source_id || group.id || 0) === currentId || group.key === currentKey
    })
    if (fresh) manageGroup.value = fresh
}, { deep: true })

const sourceText = computed(() => {
    const label = (props.meta as any)?.source_label || '商品资料'
    return (props.meta as any)?.source === 'phone_shop' ? `${label} · 优先商城` : label
})

const specGroups = computed(() => {
    const meta = props.meta as any
    const groups = Array.isArray(meta?.specs?.groups) ? meta.specs.groups : []
    return groups.map((group: any) => ({
        ...group,
        key: group.key || `spec_${group.id || group.source_id || group.label}`,
        items: normalizeOptions(group.items),
    })).filter((group: any) => group.items.length)
})

const gradeOptions = computed(() => normalizeOptions((props.meta as any)?.specs?.grades || []))
const colorOptions = computed(() => normalizeOptions((props.meta as any)?.specs?.colors || []))
const manageTitle = computed(() => manageType.value === 'grade' ? '管理成色' : `管理${manageGroup.value?.label || '规格'}`)
const manageRows = computed(() => manageType.value === 'grade' ? gradeOptions.value : normalizeOptions(manageGroup.value?.items || []))
const warrantyText = computed(() => localWarrantyAt.value ? formatDate(localWarrantyAt.value) : '')
const warrantyDefaultDate = computed(() => localWarrantyAt.value ? formatDate(localWarrantyAt.value) : formatDate(Math.floor(Date.now() / 1000)))

const modelTitle = computed(() => {
    const rules = ((props.meta as any)?.title_rules || {}) as any
    const parts = [categoryTitle()]
    if (rules.spec_in_title !== false) {
        specGroups.value
            .filter((group: any) => !!group.title_part)
            .forEach((group: any) => {
                const value = localSpecs.value[group.key]?.value
                if (value) parts.push(value)
            })
    }
    if (localColor.value) parts.push(localColor.value)
    if (rules.grade_in_title && localGrade.value?.value) parts.push(localGrade.value.value)
    return uniqueParts(parts).join(rules.separator || ' ')
})

const specText = computed(() => {
    const parts: string[] = []
    specGroups.value.forEach((group: any) => {
        const value = localSpecs.value[group.key]?.value
        if (value) parts.push(value)
    })
    if (localGrade.value?.value) parts.push(localGrade.value.value)
    if (localColor.value) parts.push(localColor.value)
    if (localBattery.value !== '') parts.push(`电池${localBattery.value}%`)
    if (warrantyStatus.value === 'active' && localWarrantyAt.value) parts.push(`保修至${formatDate(localWarrantyAt.value)}`)
    if (warrantyStatus.value === 'expired') parts.push('过保')
    return uniqueParts(parts).join(' ')
})

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

function categoryTitle(): string {
    const item = props.item as any
    const names = Array.isArray(item.category_names) && item.category_names.length
        ? item.category_names
        : String(item.category_name || '').split(/[>\-/\\｜|,，\s]+/).filter(Boolean)
    if (!names.length) return ''
    const mode = (props.meta as any)?.title_rules?.category_mode || 'auto'
    if (mode === 'full') return names.join(' ')
    if (mode === 'level_1_2') return names.slice(0, 2).join(' ')
    if (mode === 'level_2_3') return names.slice(-2).join(' ')
    if (mode === 'level_3') return names[names.length - 1] || ''
    if (names.length >= 3) return names.slice(1, 3).join(' ')
    if (names.length === 2) return names.join(' ')
    return names[0] || ''
}

function uniqueParts(parts: string[]): string[] {
    const seen = new Set<string>()
    return parts.map(part => String(part || '').trim()).filter(part => {
        if (!part || seen.has(part)) return false
        seen.add(part)
        return true
    })
}

function toggleSpec(group: any, option: any) {
    const current = localSpecs.value[group.key]?.value
    localSpecs.value = { ...localSpecs.value }
    if (current === option.value) delete localSpecs.value[group.key]
    else localSpecs.value[group.key] = { label: option.label, value: option.value, group_key: group.key, group_label: group.label, title_part: !!group.title_part }
}

function toggleGrade(option: any) {
    localGrade.value = localGrade.value?.value === option.value ? null : { label: option.label, value: option.value }
}

function applyTitle() {
    localModel.value = modelTitle.value
    uni.showToast({ title: '已同步型号', icon: 'none' })
}

function reset() {
    localSpecs.value = {}
    localGrade.value = null
    localColor.value = ''
    localBattery.value = ''
    localWarrantyAt.value = 0
    warrantyStatus.value = 'expired'
    localModel.value = categoryTitle()
}

function setWarrantyStatus(status: 'active' | 'expired') {
    warrantyStatus.value = status
    if (status === 'expired') {
        localWarrantyAt.value = 0
        return
    }
    if (!localWarrantyAt.value) {
        showWarrantyPicker.value = true
    }
}

function confirmWarranty(e: any) {
    const value = e?.[0] || e?.result || e?.value || e?.date || e
    localWarrantyAt.value = dateToTimestamp(value)
    warrantyStatus.value = localWarrantyAt.value > 0 ? 'active' : warrantyStatus.value
    showWarrantyPicker.value = false
}

function dateToTimestamp(value: any) {
    if (typeof value === 'number') {
        return value > 10000000000 ? Math.floor(value / 1000) : value
    }
    const text = String(value || '').trim()
    if (!text) return 0
    const normalized = text.replace(/\//g, '-')
    const timestamp = Math.floor(new Date(`${normalized} 00:00:00`).getTime() / 1000)
    return Number.isFinite(timestamp) ? timestamp : 0
}

function formatDate(timestamp: number) {
    const date = new Date(Number(timestamp || 0) * 1000)
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function close() {
    emit('update:show', false)
}

function confirm() {
    const item = props.item as any
    const nextModel = (!localModel.value || localModel.value === item?._auto_model) ? modelTitle.value : localModel.value
    emit('confirm', {
        selected_specs: localSpecs.value,
        selected_grade: localGrade.value,
        color: localColor.value,
        battery: localBattery.value,
        warranty: warrantyStatus.value === 'active' ? localWarrantyAt.value : 0,
        model: nextModel,
        spec: specText.value,
    })
    close()
}

function sourceIsPhoneShop() {
    return (props.meta as any)?.source === 'phone_shop'
}

function openManageItems(group: any) {
    manageType.value = 'item'
    manageGroup.value = group
    resetManageForm()
    manageShow.value = true
}

function openManageGrades() {
    manageType.value = 'grade'
    manageGroup.value = null
    resetManageForm()
    manageShow.value = true
}

function closeManage() {
    manageShow.value = false
}

function resetManageForm() {
    manageEditId.value = 0
    manageName.value = ''
    manageSort.value = ''
}

function editManage(row: any) {
    manageEditId.value = Number(row.source_id || row.id || 0)
    manageName.value = row.label || row.value || ''
    manageSort.value = String(row.sort || 0)
}

function saveManage() {
    const name = manageName.value.trim()
    if (!name) return uni.showToast({ title: manageType.value === 'grade' ? '请输入成色名称' : '请输入规格值', icon: 'none' })
    manageSaving.value = true
    const req = manageType.value === 'grade' ? saveGrade(name) : saveSpecItem(name)
    req.then(() => {
        resetManageForm()
        emit('refresh')
    }).catch((e: any) => {
        uni.showToast({ title: e?.message || '保存失败', icon: 'none' })
    }).finally(() => {
        manageSaving.value = false
    })
}

function saveSpecItem(name: string) {
    const id = manageEditId.value || 0
    const groupId = Number(manageGroup.value?.source_id || manageGroup.value?.id || 0)
    const payload = { group_id: groupId, item_value: name, sort: Number(manageSort.value || 0) }
    if (sourceIsPhoneShop()) {
        return id ? request.put(`phone_shop/goods/spec/item/${id}`, payload, { showSuccessMessage: true }) : request.post('phone_shop/goods/spec/item', payload, { showSuccessMessage: true })
    }
    return request.post(`erp/goods/spec/item/save/${id}`, payload, { showSuccessMessage: true })
}

function saveGrade(name: string) {
    const id = manageEditId.value || 0
    const payload = { grade_name: name, sort: Number(manageSort.value || 0), status: 1 }
    if (sourceIsPhoneShop()) {
        return id ? request.put(`phone_shop/goods/grade/${id}`, payload, { showSuccessMessage: true }) : request.post('phone_shop/goods/grade', payload, { showSuccessMessage: true })
    }
    return request.post(`erp/goods/grade/save/${id}`, payload, { showSuccessMessage: true })
}

function deleteManage(row: any) {
    const id = Number(row.source_id || row.id || 0)
    if (!id) return
    uni.showModal({
        title: '确认删除',
        content: `删除后不会影响已保存单据快照，确认删除「${row.label}」吗？`,
        success: (res) => {
            if (!res.confirm) return
            const req = manageType.value === 'grade'
                ? (sourceIsPhoneShop() ? request.delete(`phone_shop/goods/grade/${id}`, {}, { showSuccessMessage: true }) : request.delete(`erp/goods/grade/${id}`, {}, { showSuccessMessage: true }))
                : (sourceIsPhoneShop() ? request.delete(`phone_shop/goods/spec/item/${id}`, {}, { showSuccessMessage: true }) : request.delete(`erp/goods/spec/item/${id}`, {}, { showSuccessMessage: true }))
            req.then(() => {
                if (manageEditId.value === id) resetManageForm()
                emit('refresh')
            }).catch((e: any) => {
                uni.showToast({ title: e?.message || '删除失败', icon: 'none' })
            })
        }
    })
}
</script>

<style scoped lang="scss">
.spec-popup { height: 82vh; display: flex; flex-direction: column; background: #f8fafc; }
.popup-head { flex-shrink: 0; padding: 28rpx 28rpx 18rpx; background: #fff; display: flex; align-items: center; justify-content: space-between; border-bottom: 2rpx solid #eef2f7; }
.popup-head__main { display: flex; flex-direction: column; gap: 6rpx; }
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.popup-sub { font-size: 23rpx; color: #64748b; }
.popup-close { width: 56rpx; height: 56rpx; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }
.popup-body { flex: 1; min-height: 0; box-sizing: border-box; padding: 20rpx 24rpx; }
.title-card, .spec-group { background: #fff; border-radius: 20rpx; padding: 22rpx; box-shadow: 0 2rpx 12rpx rgba(15, 23, 42, .04); border: 2rpx solid #eef2f7; margin-bottom: 18rpx; }
.title-card { background: #f8fbff; border-color: #dbe7ff; }
.title-card__head, .spec-group__head { display: flex; align-items: center; justify-content: space-between; gap: 12rpx; margin-bottom: 14rpx; }
.title-card__label, .spec-group__label { font-size: 26rpx; font-weight: 700; color: #334155; }
.spec-group__actions { display: flex; align-items: center; gap: 10rpx; flex-shrink: 0; }
.title-card__action { height: 48rpx; padding: 0 18rpx; border-radius: 24rpx; background: #3b6ef5; color: #fff; display: flex; align-items: center; font-size: 23rpx; font-weight: 600; }
.title-card__value { display: block; font-size: 30rpx; font-weight: 700; color: #0f172a; line-height: 1.35; }
.title-card__tip { display: block; margin-top: 10rpx; font-size: 23rpx; color: #64748b; line-height: 1.45; }
.spec-group__badge { height: 34rpx; padding: 0 12rpx; border-radius: 17rpx; background: #eaf1ff; color: #3b6ef5; display: flex; align-items: center; font-size: 20rpx; }
.spec-group__badge.muted { background: #eef2f7; color: #64748b; }
.manage-link { height: 42rpx; padding: 0 14rpx; border-radius: 21rpx; background: #f8fbff; color: #3b6ef5; display: flex; align-items: center; gap: 6rpx; font-size: 22rpx; font-weight: 600; }
.chip-list { display: flex; flex-wrap: wrap; gap: 14rpx; }
.chip-list.mt { margin-top: 14rpx; }
.chip { min-height: 60rpx; padding: 0 20rpx; border-radius: 30rpx; border: 2rpx solid #e2e8f0; background: #fff; display: flex; align-items: center; gap: 8rpx; color: #334155; font-size: 25rpx; box-sizing: border-box; }
.chip--on { color: #3b6ef5; border-color: #3b6ef5; background: #f8fbff; font-weight: 600; }
.input-suffix { color: #64748b; font-size: 26rpx; padding-right: 12rpx; }
.warranty-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 12rpx; margin-bottom: 14rpx; }
.warranty-tab { height: 64rpx; border-radius: 32rpx; background: #f8fafc; border: 2rpx solid #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 25rpx; font-weight: 600; box-sizing: border-box; }
.warranty-tab--on { color: #3b6ef5; background: #f8fbff; border-color: #3b6ef5; }
.date-field { min-height: 72rpx; display: flex; align-items: center; gap: 12rpx; background: #f8fafc; border: 2rpx solid transparent; border-radius: 12rpx; padding: 0 18rpx; box-sizing: border-box; }
.date-field--on { background: #f8fbff; border-color: #3b6ef5; }
.date-field__text { flex: 1; min-width: 0; color: #0f172a; font-size: 26rpx; }
.date-field__placeholder { flex: 1; min-width: 0; color: #94a3b8; font-size: 26rpx; }
.date-clear { width: 40rpx; height: 40rpx; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.empty { padding: 60rpx 0; }
.popup-actions { flex-shrink: 0; display: flex; gap: 16rpx; padding: 18rpx 28rpx calc(18rpx + env(safe-area-inset-bottom)); background: #fff; box-shadow: 0 -4rpx 18rpx rgba(15, 23, 42, .06); }
.action-btn { min-width: 0; }
.action-btn--minor { flex: 1; }
.action-btn--major { flex: 2; }
.manage-pop { height: 72vh; display: flex; flex-direction: column; background: #f8fafc; }
.manage-head { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 20rpx; padding: 28rpx 30rpx 18rpx; background: #fff; border-bottom: 2rpx solid #eef2f7; }
.manage-head__main { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 4rpx; }
.manage-title { font-size: 30rpx; font-weight: 700; color: #0f172a; }
.manage-sub { max-width: 360rpx; font-size: 22rpx; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.h-btn { min-width: 80rpx; font-size: 28rpx; color: #9098A3; }
.h-btn--ok { color: #3b6ef5; text-align: right; font-weight: 600; }
.manage-form { background: #fff; padding: 0 28rpx 24rpx; border-bottom: 2rpx solid #eef2f7; }
.manage-field { min-height: 92rpx; display: flex; align-items: center; border-bottom: 2rpx solid #f3f4f6; }
.manage-label { width: 160rpx; font-size: 27rpx; color: #334155; font-weight: 600; flex-shrink: 0; }
.req { color: #dc2626; margin-right: 4rpx; }
.manage-save { display: flex; gap: 14rpx; padding-top: 24rpx; }
.manage-save__btn { flex: 2; min-width: 0; }
.manage-save__btn.minor { flex: 1; }
.manage-list { flex: 1; min-height: 0; padding: 18rpx 24rpx; box-sizing: border-box; }
.manage-row { min-height: 92rpx; background: #fff; border: 2rpx solid #eef2f7; border-radius: 18rpx; padding: 0 22rpx; margin-bottom: 14rpx; display: flex; align-items: center; justify-content: space-between; gap: 18rpx; box-sizing: border-box; }
.manage-row__main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4rpx; }
.manage-row__name { font-size: 27rpx; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.manage-row__sort { font-size: 22rpx; color: #94a3b8; }
.manage-row__actions { display: flex; align-items: center; gap: 20rpx; flex-shrink: 0; }
.manage-row__action { font-size: 25rpx; color: #3b6ef5; }
.manage-row__action.danger { color: #dc2626; }
.manage-empty { text-align: center; color: #94a3b8; font-size: 25rpx; padding: 70rpx 0; }
</style>
