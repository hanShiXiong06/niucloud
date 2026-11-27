<template>
    <view class="mc-actionbar" :class="{ 'mc-actionbar--fixed': fixed }">
        <view class="mc-actionbar__inner"><slot /></view>
    </view>
</template>
<script setup lang="ts">
withDefaults(defineProps<{ fixed?: boolean }>(), { fixed: false })
</script>
