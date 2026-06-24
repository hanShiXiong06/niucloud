<template>
    <view class="rtg">
        <!-- 轻量 chip：用普通 view 复刻 uview u-tag 外观（同尺寸/圆角/1px 边框/橙色选中态），
             避免在长表单里渲染上百个 u-tag 组件实例导致真机滚动卡顿。视觉与 u-tag mini 完全一致。 -->
        <!-- 横向滚动（选项多时，如质检项）：scroll-view 内放 inline-flex 行 + flex:0 0 auto 格子 -->
        <scroll-view v-if="scroll" scroll-x :show-scrollbar="false" class="rtg__scroll">
            <view class="rtg__row">
                <view
                    v-for="(opt, i) in normalizedOptions"
                    :key="i"
                    class="rtg__cell rtg__chip"
                    :class="[sizeClass, { 'rtg__chip--active': isActive(opt.value) }]"
                    :style="chipStyle(opt.value)"
                    @click="toggle(opt.value)"
                >{{ opt.label }}</view>
            </view>
        </scroll-view>

        <!-- 换行排列（默认） -->
        <view v-else class="rtg__wrap">
            <view
                v-for="(opt, i) in normalizedOptions"
                :key="i"
                class="rtg__chip"
                :class="[sizeClass, { 'rtg__chip--active': isActive(opt.value) }]"
                :style="chipStyle(opt.value)"
                @click="toggle(opt.value)"
            >{{ opt.label }}</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 通用标签选择组件（封装 uview-plus u-tag）。
 * 单选 / 多选、横滑 / 换行、橙色选中态，统一全项目 chip 选择交互。
 * 用法：<RecycleTagGroup v-model="val" :options="opts" multiple scroll @change="..." />
 */
const props = withDefaults(defineProps<{
    modelValue?: any
    options?: any[]
    multiple?: boolean
    labelKey?: string
    valueKey?: string
    scroll?: boolean
    size?: string
    deselectable?: boolean   // 单选时是否允许再次点击取消
    activeColor?: string
    activeBg?: string
    inactiveColor?: string
    inactiveBg?: string
    gap?: string
}>(), {
    modelValue: '',
    options: () => [],
    multiple: false,
    labelKey: 'label',
    valueKey: 'value',
    scroll: false,
    size: 'mini',
    deselectable: true,
    activeColor: '#fa5c1e',
    activeBg: '#fff2e9',
    inactiveColor: '#475569',
    inactiveBg: '#f4f5f7',
    gap: '14rpx'
})
const emit = defineEmits(['update:modelValue', 'change'])

const normalizedOptions = computed(() =>
    (props.options || []).map((o: any) => {
        if (o && typeof o === 'object') {
            return { label: o[props.labelKey] ?? o.name ?? o.label ?? '', value: o[props.valueKey] }
        }
        return { label: String(o), value: o }
    })
)

const isActive = (val: any) => {
    const m = props.modelValue
    return Array.isArray(m) ? m.some((v: any) => String(v) === String(val)) : String(m) === String(val)
}

// 选中/未选中态的动态配色（与原 u-tag 的 bgColor/color/borderColor 取值一一对应）
const chipStyle = (val: any) => {
    const active = isActive(val)
    return {
        color: active ? props.activeColor : props.inactiveColor,
        backgroundColor: active ? props.activeBg : props.inactiveBg,
        borderColor: active ? props.activeColor : 'transparent'
    }
}

// 尺寸类（对齐 u-tag 的 mini/medium/large）
const sizeClass = computed(() =>
    props.size === 'large' ? 'rtg__chip--large' : props.size === 'medium' ? 'rtg__chip--medium' : 'rtg__chip--mini'
)

const toggle = (val: any) => {
    if (props.multiple) {
        const arr = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        const idx = arr.findIndex((v: any) => String(v) === String(val))
        if (idx >= 0) arr.splice(idx, 1)
        else arr.push(val)
        emit('update:modelValue', arr)
        emit('change', arr)
    } else {
        const next = (props.deselectable && String(props.modelValue) === String(val)) ? '' : val
        emit('update:modelValue', next)
        emit('change', next)
    }
}
</script>

<style scoped lang="scss">
.rtg {
    width: 100%;
    min-width: 0;
}
.rtg__scroll {
    width: 100%;
}
.rtg__row {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 14rpx;
    padding: 2rpx 0;
}
.rtg__cell {
    flex: 0 0 auto;
}
.rtg__wrap {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16rpx;
}

/* 复刻 uview-plus u-tag（square/1px 边框）：px 单位与 u-tag 源码一致，保证视觉零差异 */
.rtg__chip {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-style: solid;
    border-width: 1px;
    border-radius: 3px;
    white-space: nowrap;
    /* color / backgroundColor / borderColor 由 :style 动态注入 */
}
.rtg__chip--mini {
    height: 22px;
    padding: 0 5px;
    font-size: 12px;
    line-height: 1;
}
.rtg__chip--medium {
    height: 26px;
    padding: 0 10px;
    font-size: 13px;
    line-height: 1;
}
.rtg__chip--large {
    height: 32px;
    padding: 0 15px;
    font-size: 15px;
    line-height: 1;
}
</style>
