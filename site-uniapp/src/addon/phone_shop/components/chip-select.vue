<template>
    <view class="cs">
        <view class="cs-label" v-if="label">
            <text class="req" v-if="required">*</text>{{ label }}
        </view>
        <view class="chips" v-if="normItems.length">
            <view v-for="it in display" :key="it.value"
                class="chip" :class="{ 'chip--on': isOn(it.value) }"
                @click="pick(it.value)">
                <text class="nc-iconfont nc-icon-duihaoV6xx text-[22rpx] mr-[6rpx]" v-if="multiple && isOn(it.value)"></text>{{ it.label }}
            </view>
            <view v-if="normItems.length > collapseN" class="chip chip--more" @click="expanded = !expanded">
                <text>{{ expanded ? '收起' : '更多' }}</text>
                <text class="nc-iconfont text-[20rpx] ml-[4rpx]" :class="expanded ? 'nc-icon-shangV6xx' : 'nc-icon-xiaV6xx'"></text>
            </view>
        </view>
        <view v-else class="cs-tip">{{ emptyText }}</view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    modelValue: { type: [String, Number, Array], default: '' },
    multiple: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    collapseN: { type: Number, default: 10 },
    emptyText: { type: String, default: '暂无选项' }
});
const emit = defineEmits(['update:modelValue']);

const expanded = ref(false);

const normItems = computed(() => {
    return (props.items as any[]).map((it: any) => {
        if (typeof it === 'object' && it !== null) {
            return { label: it.label ?? it.name ?? it.value, value: it.value ?? it.id ?? it.label ?? it.name };
        }
        return { label: it, value: it };
    });
});

const display = computed(() => {
    if (expanded.value || normItems.value.length <= props.collapseN) return normItems.value;
    return normItems.value.slice(0, props.collapseN);
});

const isOn = (v: any) => {
    if (props.multiple) return Array.isArray(props.modelValue) && (props.modelValue as any[]).includes(v);
    return props.modelValue === v;
};

const pick = (v: any) => {
    if (props.multiple) {
        const arr = Array.isArray(props.modelValue) ? [...(props.modelValue as any[])] : [];
        const i = arr.indexOf(v);
        if (i > -1) arr.splice(i, 1); else arr.push(v);
        emit('update:modelValue', arr);
    } else {
        emit('update:modelValue', props.modelValue === v ? '' : v);
    }
};
</script>

<style lang="scss" scoped>
.cs-label { font-size: 28rpx; color: #333; margin-bottom: 20rpx; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.chips { display: flex; flex-wrap: wrap; gap: 18rpx; }
.chip {
    display: flex;
    align-items: center;
    padding: 12rpx 28rpx;
    background: #F5F7FA;
    color: #555;
    font-size: 26rpx;
    border-radius: 32rpx;
    border: 2rpx solid transparent;
    line-height: 1.3;
}
.chip--on {
    background: var(--primary-color-light, #E6FFF5);
    color: var(--primary-color);
    border-color: var(--primary-color);
}
.chip--more { background: transparent; color: #9098A3; }
.cs-tip { font-size: 24rpx; color: #9098A3; padding: 4rpx 0; }
</style>
