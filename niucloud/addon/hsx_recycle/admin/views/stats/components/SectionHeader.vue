<template>
    <div :class="['rounded-lg px-4 py-3', bgClass]">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div
                    :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center',
                        iconBgClass
                    ]"
                >
                    <el-icon :size="16" color="white">
                        <component :is="icon" />
                    </el-icon>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-white">
                        {{ title }}
                    </h2>
                    <p v-if="subtitle" :class="subtitleClass">
                        {{ subtitle }}
                    </p>
                </div>
            </div>
            <!-- 右侧插槽 -->
            <slot name="right"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { defineProps, withDefaults } from 'vue'

interface Props {
    title: string
    subtitle?: string
    icon?: any
    bgClass?: string
    iconBgClass?: string
    subtitleClass?: string
}

withDefaults(defineProps<Props>(), {
    bgClass: 'bg-gradient-to-r from-blue-600 to-purple-600',
    iconBgClass: 'bg-white/20',
    subtitleClass: 'text-blue-100 text-xs'
})
</script>

