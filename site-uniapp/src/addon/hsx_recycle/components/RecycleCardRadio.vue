<template>
    <view class="rcr" :style="{ gridTemplateColumns: `repeat(${ columns }, minmax(0, 1fr))` }">
        <view
            v-for="(opt, i) in norm"
            :key="i"
            class="rcr__item"
            :class="{ 'rcr__item--active': isActive(opt.value) }"
            @click="select(opt.value)"
        >
            <text class="rcr__name">{{ opt.name }}</text>
            <text v-if="opt.desc" class="rcr__desc">{{ opt.desc }}</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 卡片单选（标题 + 描述），统一全项目"模式/类型"卡片选择交互。
 * 用法：<RecycleCardRadio v-model="mode" :options="modes" :columns="2" @change="..." />
 */
const props = withDefaults(defineProps<{
    modelValue?: any
    options?: any[]
    nameKey?: string
    descKey?: string
    valueKey?: string
    columns?: number
    deselectable?: boolean
}>(), {
    modelValue: '',
    options: () => [],
    nameKey: 'name',
    descKey: 'desc',
    valueKey: 'value',
    columns: 2,
    deselectable: false
})
const emit = defineEmits(['update:modelValue', 'change'])

const norm = computed(() => (props.options || []).map((o: any) => ({
    name: o?.[props.nameKey] ?? o?.label ?? o?.name ?? '',
    desc: o?.[props.descKey] ?? '',
    value: o?.[props.valueKey]
})))

const isActive = (val: any) => String(props.modelValue) === String(val)

const select = (val: any) => {
    const next = (props.deselectable && String(props.modelValue) === String(val)) ? '' : val
    emit('update:modelValue', next)
    emit('change', next)
}
</script>

<style scoped lang="scss">
.rcr {
    display: grid;
    gap: 16rpx;
}
.rcr__item {
    display: flex;
    flex-direction: column;
    gap: 6rpx;
    padding: 20rpx 22rpx;
    border-radius: 16rpx;
    background: #f7f8fa;
    border: 1rpx solid #eef0f3;
}
.rcr__item--active {
    background: #fff6f1;
    border-color: #fa5c1e;
}
.rcr__name {
    font-size: 28rpx;
    font-weight: 600;
    color: #303133;
}
.rcr__item--active .rcr__name {
    color: #fa5c1e;
}
.rcr__desc {
    font-size: 22rpx;
    color: #909399;
    line-height: 1.4;
}
</style>
