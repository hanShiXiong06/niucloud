<template>
    <div
        :class="[
            'relative rounded-lg p-4 border transition-all duration-200 hover:shadow-md',
            `bg-gradient-to-br ${bgGradient}`,
            `border-${color}-200` , 
            'cursor-pointer'
        ]"
        @click="handleClick(link)"
    >
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p :class="`text-${color}-600 text-xs font-medium mb-1`">
                    {{ title }}
                </p>
                <p :class="`text-2xl font-bold text-${color}-700 mb-1`">
                    {{ mainValue }}
                </p>
                <p v-if="subValue" :class="`text-${color}-500 text-xs`">
                    {{ subValue }}
                </p>
                <!-- 额外信息插槽 -->
                <div v-if="extraInfo && extraInfo.length > 0" class="mt-2 flex flex-wrap gap-1">
                    <span
                        v-for="(item, index) in extraInfo"
                        :key="index"
                        :class="`text-xs px-2 py-0.5 rounded bg-${color}-200 text-${color}-700`"
                    >
                        {{ item }}
                    </span>
                </div>
            </div>
            <div
                :class="[
                    'w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0',
                    `bg-${color}-500`
                ]"
            >
                <slot name="icon">
                    <el-icon :size="20" color="white">
                        <component :is="icon" />
                    </el-icon>
                </slot>
            </div>
        </div>
        <!-- 活动指示器 -->
        <div
            v-if="showIndicator && Number(mainValue) > 0"
            class="absolute top-2 right-2 w-2 h-2 bg-green-400 rounded-full"
        ></div>
    </div>
</template>

<script setup lang="ts">
import { computed  } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

interface Props {
    title: string
    mainValue: string | number
    subValue?: string
    color?: 'blue' | 'green' | 'orange' | 'red' | 'purple' | 'indigo'
    icon?: any,
    link?: string,
    showIndicator?: boolean
    extraInfo?: string[]
}

const props = withDefaults(defineProps<Props>(), {
    color: 'blue',
    showIndicator: false,
    extraInfo: () => []
})

// 根据颜色生成渐变
const bgGradient = computed(() => {
    const gradients = {
        blue: 'from-blue-50 to-blue-100',
        green: 'from-green-50 to-green-100',
        orange: 'from-orange-50 to-orange-100',
        red: 'from-red-50 to-red-100',
        purple: 'from-purple-50 to-purple-100',
        indigo: 'from-indigo-50 to-indigo-100'
    }
    return gradients[props.color] || gradients.blue
})

const handleClick = (link: string) => {
    if (link) {
        router.push(link)
    }
}
</script>

