<template>
    <view v-if="supported" class="touch-preference">
        <text>{{ label }}</text>
        <u-switch v-model="enabled" size="19" :aria-label="label" @change="change" />
    </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { interactionFeedbackSupported, interactionFeedbackEnabled, setInteractionFeedbackEnabled } from '../../utils/interaction-feedback'
withDefaults(defineProps<{ label?: string }>(), { label:'轻触反馈' })
const supported = interactionFeedbackSupported()
const enabled = ref(interactionFeedbackEnabled())
function change(value: boolean) { setInteractionFeedbackEnabled(value) }
</script>
<style scoped>
.touch-preference { display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:48px;padding:4px 0;border-top:1px solid #f0f1f2;color:var(--lead-diy-text,#606266);font-size:var(--lead-diy-text-size,14px); }
</style>
