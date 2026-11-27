<template>
    <view class="mc-collapse">
        <u-cell
            :title="title"
            :label="!open ? summary : ''"
            :border="false"
            isLink
            :arrowDirection="open ? 'up' : 'down'"
            :titleStyle="{ fontSize: '13px', color: '#56667e' }"
            :customStyle="{ margin: '0 -15px' }"
            @click="open = !open"
        />
        <view v-show="open" class="mc-collapse__body"><slot /></view>
    </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
const props = withDefaults(defineProps<{ title: string; summary?: string; expanded?: boolean }>(), {
    summary: '',
    expanded: false
})
const open = ref(props.expanded)
</script>
