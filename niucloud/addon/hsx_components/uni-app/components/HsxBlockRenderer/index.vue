<script lang="ts">export default { name: 'HsxBlockRenderer' }</script>
<script setup lang="ts">
import HsxCard from '../HsxCard/index.vue'
import HsxGrid from '../HsxGrid/index.vue'
import HsxProgress from '../HsxProgress/index.vue'
import HsxStack from '../HsxStack/index'
import HsxText from '../HsxText/index.vue'
import HsxTitle from '../HsxTitle/index.vue'
import type { MobileBlockSchema } from '../../types'

withDefaults(defineProps<{ schema?: MobileBlockSchema[], context?: Record<string, any> }>(), { schema: () => [], context: () => ({}) })
</script>
<template>
    <template v-for="(block, index) in schema" :key="block.key || `${block.type}-${index}`">
        <slot v-if="block.type === 'slot'" :name="block.slot || block.key" :block="block" :context="context" />
        <HsxTitle v-else-if="block.type === 'title'" v-bind="block.props" :class="block.class" :style="block.style"><template v-if="block.text">{{ block.text }}</template></HsxTitle>
        <HsxText v-else-if="block.type === 'text'" v-bind="block.props" :text="block.text" :class="block.class" :style="block.style" />
        <HsxProgress v-else-if="block.type === 'progress'" v-bind="block.props" :class="block.class" :style="block.style" />
        <HsxGrid v-else-if="block.type === 'grid'" v-bind="block.props" :class="block.class" :style="block.style"><HsxBlockRenderer :schema="block.children" :context="context" /></HsxGrid>
        <HsxStack v-else-if="block.type === 'stack'" v-bind="block.props" :class="block.class" :style="block.style"><HsxBlockRenderer :schema="block.children" :context="context" /></HsxStack>
        <HsxCard v-else-if="block.type === 'card'" v-bind="block.props" :class="block.class" :style="block.style"><HsxBlockRenderer :schema="block.children" :context="context" /></HsxCard>
    </template>
</template>
