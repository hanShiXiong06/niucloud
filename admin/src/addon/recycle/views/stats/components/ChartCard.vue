<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <!-- 卡片头部 -->
        <div class="px-4 py-3 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 flex items-center">
                    <el-icon v-if="icon" :size="16" :color="iconColor" class="mr-2">
                        <component :is="icon" />
                    </el-icon>
                    {{ title }}
                </h3>
                <!-- 头部右侧插槽 -->
                <slot name="header-right"></slot>
            </div>
        </div>
        
        <!-- 卡片内容 -->
        <div :class="['p-4', contentClass]">
            <slot></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { defineProps, withDefaults } from 'vue'

interface Props {
    title: string
    icon?: any
    iconColor?: string
    contentClass?: string
}

withDefaults(defineProps<Props>(), {
    iconColor: '#3B82F6',
    contentClass: ''
})
</script>

