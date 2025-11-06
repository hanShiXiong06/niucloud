<template>
    <div class="json-preview-container">
        <div class="json-preview-header" @click="toggleExpand">
            <el-icon class="expand-icon" :class="{ expanded: isExpanded }">
                <ArrowRight />
            </el-icon>
            <span class="json-label">{{ label || 'JSON数据' }}</span>
            <span class="json-type" v-if="jsonType">{{ jsonType }}</span>
        </div>
        <div class="json-preview-content" v-show="isExpanded">
            <pre v-if="parsedData" class="json-content">{{ formattedJson }}</pre>
            <div v-else class="json-empty">暂无数据</div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed } from 'vue'
import { ArrowRight } from '@element-plus/icons-vue'

interface Props {
    data?: string | object | null
    label?: string
    defaultExpanded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    data: null,
    label: 'JSON数据',
    defaultExpanded: false
})

const isExpanded = ref(props.defaultExpanded)

const toggleExpand = () => {
    isExpanded.value = !isExpanded.value
}

const parsedData = computed(() => {
    if (!props.data) return null
    
    try {
        if (typeof props.data === 'string') {
            const parsed = JSON.parse(props.data)
            return parsed
        }
        return props.data
    } catch (error) {
        console.error('JSON解析失败:', error)
        return null
    }
})

const formattedJson = computed(() => {
    if (!parsedData.value) return ''
    try {
        return JSON.stringify(parsedData.value, null, 2)
    } catch (error) {
        return String(props.data)
    }
})

const jsonType = computed(() => {
    if (!parsedData.value) return null
    const type = typeof parsedData.value
    if (type === 'object') {
        if (Array.isArray(parsedData.value)) {
            return `数组 (${parsedData.value.length}项)`
        }
        return `对象 (${Object.keys(parsedData.value).length}个键)`
    }
    return type
})
</script>

<style lang="scss" scoped>
.json-preview-container {
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;

    .json-preview-header {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: #f5f7fa;
        cursor: pointer;
        user-select: none;
        transition: background-color 0.2s;

        &:hover {
            background: #ebeef5;
        }

        .expand-icon {
            margin-right: 8px;
            transition: transform 0.2s;
            color: #909399;

            &.expanded {
                transform: rotate(90deg);
            }
        }

        .json-label {
            font-weight: 500;
            color: #303133;
            flex: 1;
        }

        .json-type {
            font-size: 12px;
            color: #909399;
            margin-left: 8px;
        }
    }

    .json-preview-content {
        padding: 12px;
        background: #fafafa;
        border-top: 1px solid #dcdfe6;

        .json-content {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #303133;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow: auto;
            background: #fff;
            padding: 12px;
            border-radius: 4px;
        }

        .json-empty {
            color: #909399;
            text-align: center;
            padding: 20px;
        }
    }
}
</style>

