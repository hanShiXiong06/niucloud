<template>
    <view class="mc-list-head">
        <u-search
            v-model="localKeyword"
            :placeholder="placeholder"
            shape="square"
            bgColor="#f2f4f7"
            :height="40"
            :animation="false"
            actionText="搜索"
            :actionStyle="{ color: '#245caa', fontWeight: '600', fontSize: '14px' }"
            @search="search"
            @custom="search"
            @clear="clear"
        />
        <view v-if="tabs.length" class="mc-list-head__tabs">
            <u-tabs
                :list="tabOptions"
                :current="currentTab"
                :scrollable="tabs.length > 4"
                lineColor="#2868ce"
                :lineWidth="20"
                :lineHeight="3"
                :activeStyle="{ color: '#1c3556', fontWeight: '600' }"
                :inactiveStyle="{ color: '#758196' }"
                :itemStyle="{ padding: '0 14px', height: '46px' }"
                @change="tabChanged"
            />
        </view>
        <view v-else style="height: 14px" />
    </view>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
const props = withDefaults(
    defineProps<{
        modelValue?: string
        activeTab?: string
        placeholder?: string
        tabs?: Array<{ label: string; value: string; count?: number }>
    }>(),
    { modelValue: '', activeTab: '', placeholder: '搜索', tabs: () => [] }
)
const emit = defineEmits(['update:modelValue', 'update:activeTab', 'search', 'tab-change'])
const localKeyword = ref(props.modelValue)
const currentTab = computed(() =>
    Math.max(
        0,
        props.tabs.findIndex((tab) => tab.value === props.activeTab)
    )
)
const tabOptions = computed(() =>
    props.tabs.map((tab) => ({
        ...tab,
        name: tab.label + (tab.count !== undefined ? ' ' + tab.count : '')
    }))
)
const tabChanged = (tab: { index: number }) => {
    const item = props.tabs[tab.index]
    if (item) selectTab(item.value)
}
watch(
    () => props.modelValue,
    (value) => {
        localKeyword.value = value || ''
    }
)
watch(localKeyword, (value) => emit('update:modelValue', value))
const search = () => {
    emit('update:modelValue', localKeyword.value.trim())
    emit('search', localKeyword.value.trim())
}
const clear = () => {
    localKeyword.value = ''
    emit('update:modelValue', '')
    emit('search', '')
}
const selectTab = (value: string) => {
    if (value !== props.activeTab) {
        emit('update:activeTab', value)
        emit('tab-change', value)
    }
}
</script>
