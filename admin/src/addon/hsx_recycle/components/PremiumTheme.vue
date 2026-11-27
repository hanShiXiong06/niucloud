<template>
  <HsxPage padding="none" class="hsx-premium" v-bind="$attrs">
    <slot />
  </HsxPage>
</template>

<script lang="ts">
/**
 * PremiumTheme —— 回收插件「高级主题」提供者（纯展示、与业务完全解耦）
 *
 * 职责单一：提供 .hsx-premium 包裹类 + 加载主题样式（premium-theme.scss）。
 * 业务页面只需 <PremiumTheme class="...">内容</PremiumTheme> 包一层即可享受主题，
 * 不在业务页面里写任何 @import / 主题样式，保证样式层独立、可插拔、可整体下线。
 *
 * inheritAttrs:false + v-bind="$attrs"：把 class/style 透传到包裹根节点，
 * 因此可直接替换原页面根 div，例如 <PremiumTheme class="recycle-order-list h-full">。
 * 用普通 <script> 声明（兼容 Vue 3.3 以下，避免 defineOptions 宏）。
 *
 * 弹窗等 teleport 到 body 的浮层，用 .hsx-premium-overlay 类（同一份样式提供）。
 */
export default { inheritAttrs: false }
</script>

<script setup lang="ts">
import { HsxPage } from '@/addon/hsx_components/core'
</script>

<style lang="scss">
/* 仅此一处加载主题样式：Vue 会对组件样式去重，无论多少处使用都只注入一次 */
@import '../styles/premium-theme.scss';
</style>
