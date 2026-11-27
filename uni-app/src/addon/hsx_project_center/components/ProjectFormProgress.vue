<template>
    <view v-if="summary.total > 0" class="form-progress" :class="{ complete: summary.percentage === 100 }">
        <view class="progress-head">
            <view class="progress-title-wrap">
                <text class="progress-title">资料填写进度</text>
                <text class="progress-count">已完成 {{ summary.completed }}/{{ summary.total }} 项{{ summary.requiredOnly ? '必填资料' : '选填资料' }}</text>
            </view>
            <text class="progress-percent">{{ summary.percentage }}%</text>
        </view>

        <view class="progress-track">
            <view class="progress-value" :style="{ width: `${summary.percentage}%` }"></view>
        </view>

        <view class="progress-hint">
            <u-icon :name="summary.percentage === 100 ? 'checkmark-circle-fill' : 'info-circle'" :color="summary.percentage === 100 ? '#12b76a' : '#667085'" size="15" />
            <text v-if="summary.percentage === 100">{{ summary.requiredOnly ? '必填资料已填写' : '资料已填写' }}，提交时将再校验格式</text>
            <text v-else-if="summary.requiredOnly">还需填写：{{ summary.remainingText }}</text>
            <text v-else>其余资料均为选填，可按实际情况补充</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface FormComponent {
    componentType?: string
    componentName?: string
    componentTitle?: string
    componentIsShow?: boolean
    isHidden?: boolean
    requireAddress?: boolean
    field?: {
        name?: string
        required?: boolean
        value?: any
    }
}

const props = withDefaults(defineProps<{
    components?: FormComponent[]
}>(), {
    components: () => []
})

function isBlankScalar(value: any) {
    return value === null || value === undefined || (typeof value === 'string' && value.trim() === '')
}

function hasNestedValue(value: any): boolean {
    if (isBlankScalar(value)) return false
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return true
    if (typeof value === 'string') return value.trim() !== ''
    if (Array.isArray(value)) return value.some(item => hasNestedValue(item))
    if (typeof value === 'object') return Object.values(value).some(item => hasNestedValue(item))
    return false
}

function hasCoordinate(value: any, key: string) {
    if (!value || typeof value !== 'object') return false
    const coordinate = value[key]
    return coordinate !== null && coordinate !== undefined && coordinate !== '' && Number.isFinite(Number(coordinate))
}

function isComponentFilled(component: FormComponent) {
    const value = component.field?.value
    const name = String(component.componentName || '')

    if (name === 'FormDate') {
        return !!value && (Number(value.timestamp || 0) > 0 || String(value.date || '').trim() !== '')
    }
    if (name === 'FormDateScope' || name === 'FormTimeScope') {
        return !!value
            && (Number(value.start?.timestamp || 0) > 0 || String(value.start?.date || '').trim() !== '')
            && (Number(value.end?.timestamp || 0) > 0 || String(value.end?.date || '').trim() !== '')
    }
    if (name === 'ProjectFormLocation' || name === 'FormLocation') {
        const hasLocation = hasCoordinate(value, 'latitude') && hasCoordinate(value, 'longitude')
        if (!hasLocation) return false
        if (component.requireAddress) return String(value?.full_address || value?.address || '').trim() !== ''
        return true
    }
    if (name === 'FormNumber') {
        return !isBlankScalar(value) && Number.isFinite(Number(value))
    }
    return hasNestedValue(value)
}

const summary = computed(() => {
    const visible = props.components.filter(item => item?.field
        && (!item.componentType || item.componentType === 'diy_form')
        && item.componentName !== 'FormSubmit'
        && item.componentIsShow !== false
        && item.isHidden !== true)
    const required = visible.filter(item => [true, 1, '1'].includes(item.field?.required as any))
    // 表单没有设置必填项时，退化为所有可见资料项的填写进度。
    const tracked = required.length ? required : visible
    const missing = tracked.filter(item => !isComponentFilled(item))
    const completed = Math.max(0, tracked.length - missing.length)
    const percentage = tracked.length ? Math.round((completed / tracked.length) * 100) : 0
    const names = missing.map(item => String(item.field?.name || item.componentTitle || '资料项')).filter(Boolean)
    const visibleNames = names.slice(0, 3).join('、')
    const remainingText = names.length > 3 ? `${visibleNames}等 ${names.length} 项` : (visibleNames || '请继续完善资料')

    return {
        total: tracked.length,
        completed,
        percentage,
        remainingText,
        requiredOnly: required.length > 0
    }
})
</script>

<style scoped lang="scss">
.form-progress {
    margin-top: 16rpx;
    padding: 18rpx 20rpx;
    border: 1rpx solid #e4eaf3;
    border-radius: 16rpx;
    background: linear-gradient(135deg, #f8faff, #fbfcff);
}
.progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
}
.progress-title-wrap {
    display: flex;
    min-width: 0;
    align-items: baseline;
    gap: 12rpx;
}
.progress-title {
    flex: none;
    color: #344054;
    font-size: 25rpx;
    font-weight: 650;
}
.progress-count {
    overflow: hidden;
    color: #98a2b3;
    font-size: 20rpx;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.progress-percent {
    flex: none;
    color: #315cf5;
    font-size: 27rpx;
    font-weight: 750;
}
.progress-track {
    height: 10rpx;
    margin-top: 13rpx;
    overflow: hidden;
    border-radius: 10rpx;
    background: #e8edf6;
}
.progress-value {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #315cf5, #6b7dff);
    transition: width .25s ease;
}
.progress-hint {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 8rpx;
    margin-top: 11rpx;
    color: #667085;
    font-size: 20rpx;
    line-height: 30rpx;
}
.progress-hint text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.form-progress.complete {
    border-color: #c7ead5;
    background: #f3fcf7;
}
.form-progress.complete .progress-percent {
    color: #12b76a;
}
.form-progress.complete .progress-value {
    background: linear-gradient(90deg, #12b76a, #48c78e);
}
</style>
