<template>
    <view v-if="!hidden" class="mc-alert-wrap">
        <u-alert
            :key="title + text"
            :title="title || text"
            :description="title ? text : ''"
            :type="tone"
            :closable="closable"
            :showIcon="true"
            :fontSize="12"
            :customStyle="{ borderRadius: '8px' }"
            @close="hidden = true"
        />
        <slot />
        <view v-if="action" class="mc-alert-action" @click="$emit('action')">{{ action }} ›</view>
    </view>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue'
const props = withDefaults(
    defineProps<{ title?: string; text?: string; tone?: string; closable?: boolean; action?: string }>(),
    { title: '', text: '', tone: 'info', closable: false, action: '' }
)
defineEmits(['action'])
const hidden = ref(false)
watch(
    () => [props.title, props.text],
    () => {
        hidden.value = false
    }
)
</script>
