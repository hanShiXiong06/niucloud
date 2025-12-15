<template>
    <div class="flex items-center justify-center w-5 h-5">
        <el-icon class="w-5 h-5">
            <component :is="iconComponent" />
        </el-icon>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

interface Props {
    icon: string
}

const props = defineProps<Props>()

// 动态获取图标组件
const iconComponent = computed(() => {
    // 如果传入的是 Element Plus 图标组件名称，直接返回对应组件
    if (props.icon && (ElementPlusIconsVue as any)[props.icon]) {
        return (ElementPlusIconsVue as any)[props.icon]
    }
    // 默认返回 Menu 图标
    return ElementPlusIconsVue.Menu
})
</script>
