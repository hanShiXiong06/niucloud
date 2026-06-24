<template>
    <u-tabs
        :list="tabList"
        :current="currentIndex"
        :scrollable="scrollable"
        lineColor="#3c9cff"
        :lineWidth="22"
        :activeStyle="{ color: '#3c9cff', fontWeight: '600', fontSize: '28rpx' }"
        :inactiveStyle="{ color: '#606266', fontSize: '28rpx' }"
        :itemStyle="itemStyle"
        @click="onClick"
    ></u-tabs>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 列表状态筛选 Tab（封装 uview-plus u-tabs，带数量角标）。
 * 字段口径与 useRecycleStatusTabs 一致：value ?? status / label||name||title / count。
 * 用法：<RecycleStatusTabs :model-value="currentStatus" :options="statusList" @change="switchStatus" />
 */
const props = withDefaults(defineProps<{
    modelValue?: number | string
    options?: any[]
    scrollable?: boolean
    itemStyle?: string
}>(), {
    modelValue: '',
    options: () => [],
    scrollable: true,
    itemStyle: 'padding-left: 28rpx; padding-right: 28rpx; height: 84rpx;'
})
const emit = defineEmits(['update:modelValue', 'change'])

const norm = computed(() => (props.options || []).map((o: any) => ({
    label: o?.label || o?.name || o?.title || '-',
    value: o?.value ?? o?.status ?? '',
    count: Number(o?.count ?? 0)
})))

const tabList = computed(() => norm.value.map((o) => {
    const t: any = { name: o.label }
    if (o.count > 0) t.badge = { value: o.count, max: 99 }
    return t
}))

const currentIndex = computed(() => {
    const i = norm.value.findIndex((o) => String(o.value) === String(props.modelValue))
    return i >= 0 ? i : 0
})

const onClick = (item: any) => {
    const v = norm.value[item.index]?.value
    emit('update:modelValue', v)
    emit('change', v)
}
</script>
