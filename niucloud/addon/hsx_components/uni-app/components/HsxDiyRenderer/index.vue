<script lang="ts">
export default { name: 'HsxDiyRenderer' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxBlockRenderer from '../HsxBlockRenderer/index.vue'
import { normalizeHsxDiyPageData } from '../../utils'
import type { HsxDiyPageData, MobileBlockSchema } from '../../types'

const props = withDefaults(defineProps<{
    data?: Partial<HsxDiyPageData>
    schema?: MobileBlockSchema[]
    context?: Record<string, any>
    pullDownRefreshCount?: number
}>(), {
    data: () => ({ global: {}, value: [] }),
    schema: () => [],
    context: () => ({}),
    pullDownRefreshCount: 0
})

const normalizedData = computed(() => normalizeHsxDiyPageData(props.data))
</script>

<template>
    <view class="hsx-diy-renderer">
        <slot
            v-if="$slots.system"
            name="system"
            :data="normalizedData"
            :pull-down-refresh-count="pullDownRefreshCount"
            :context="context"
        />
        <HsxBlockRenderer v-else :schema="schema" :context="context" />
    </view>
</template>

<style scoped>.hsx-diy-renderer { min-width: 0; width: 100%; box-sizing: border-box; }</style>
