<script lang="ts">
export default { name: 'HsxEmpty' }
</script>

<script setup lang="ts">

withDefaults(
    defineProps<{
        text?: string
        mode?: string
        marginTop?: string | number
    }>(),
    {
        text: '暂无数据',
        mode: 'data',
        marginTop: 120
    }
)
</script>

<template>
    <view :style="{ paddingTop: `${marginTop}rpx` }">
        <u-empty :text="text" :mode="mode">
            <slot />
        </u-empty>
    </view>
</template>
