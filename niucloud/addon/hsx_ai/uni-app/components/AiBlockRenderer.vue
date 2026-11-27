<template>
    <view v-if="blocks.length" class="block-list">
        <template v-for="(block, index) in blocks" :key="`${block.type}_${index}`">
            <AiChartBlock v-if="block.type === 'chart'" :data="block.data || {}" />
            <AiTableBlock v-else-if="block.type === 'table'" :data="block.data || {}" />
            <AiQuoteCard v-else-if="block.type === 'quote_card'" :data="block.data || {}" />
            <AiQuoteAggregate v-else-if="block.type === 'quote_aggregate'" :data="block.data || {}" />
            <AiPriceTrend v-else-if="block.type === 'price_trend'" :data="block.data || {}" />
        </template>
    </view>
</template>

<script setup lang="ts">
import AiChartBlock from './AiChartBlock.vue'
import AiPriceTrend from './AiPriceTrend.vue'
import AiQuoteAggregate from './AiQuoteAggregate.vue'
import AiQuoteCard from './AiQuoteCard.vue'
import AiTableBlock from './AiTableBlock.vue'

withDefaults(defineProps<{ blocks?: any[] }>(), { blocks: () => [] })
</script>

<style lang="scss" scoped>
.block-list { display: flex; width: 100%; flex-direction: column; gap: 14rpx; }
</style>
