<template>
    <view v-if="supported" class="touch-preference">
        <text>轻触反馈</text>
        <u-switch v-model="enabled" size="19" aria-label="轻触反馈" @change="change" />
    </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { interactionFeedbackSupported, interactionFeedbackEnabled, setInteractionFeedbackEnabled } from '../../utils/interaction-feedback'
const supported = interactionFeedbackSupported()
const enabled = ref(interactionFeedbackEnabled())
function change(value: boolean) { setInteractionFeedbackEnabled(value) }
</script>
<style scoped>
.touch-preference { display: flex; align-items: center; justify-content: space-between; min-height: 48px; padding: 8px 3px; border-top: 1px solid #edf1f6; color: #687c96; font-size: 13px; }
</style>
