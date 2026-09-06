<template>
    <div v-for="item in benefits" :key="item.key">
        <component :is="item.component" v-model="formData[item.key]" ref="benefitsRefs" v-if="item.component"/>
        <el-alert
            v-else-if="item.component_missing"
            class="mb-[12px]"
            type="warning"
            :closable="false"
            show-icon
            :title="`${ item.name || item.key }权益组件未同步`"
            :description="`请同步插件管理端源码后重新打包。缺少组件：${ item.component_missing }`"
        />
    </div>
</template>

<script lang="ts" setup>
import { ref, defineAsyncComponent, computed, watch } from 'vue'
import { t } from '@/lang'
import { getBenefitsDict } from '@/app/api/member'

const benefits = ref({})
const props = defineProps({
    modelValue: {
        type: Object,
        default: () => {
            return {}
        }
    }
})
const emits = defineEmits(['update:modelValue'])
const formData = ref({})
const value = computed({
    get () {
        return props.modelValue
    },
    set (value) {
        emits('update:modelValue', value)
    }
})
const benefitsRefs = ref([])

watch(() => value.value, (nval, oval) => {
    if ((!oval || !Object.keys(oval).length) && Object.keys(nval).length) {
        formData.value = value.value
    }
}, { immediate: true })

watch(() => formData.value, () => {
    value.value = formData.value
}, { deep: true })

const modules: any = import.meta.glob('@/**/*.vue')
getBenefitsDict().then(({ data }) => {
    Object.keys(data).forEach((key: string) => {
        const componentPath = data[key].component
        if (!componentPath) return
        const loader = modules[componentPath]
        if (typeof loader !== 'function') {
            data[key].component = null
            data[key].component_missing = componentPath
            console.error(`[member-benefits] 权益组件未同步：${ key } -> ${ componentPath }`)
            return
        }
        data[key].component = defineAsyncComponent(loader)
    })
    benefits.value = data
})

/**
 * 验证
 */
const verify = async () => {
    let verify = true
    for (let i = 0; i < benefitsRefs.value.length; i++) {
        const item = benefitsRefs.value[i]
        if (typeof item?.verify === 'function' && !await item.verify()) verify = false
    }
    return verify
}

defineExpose({
    verify
})
</script>

<style lang="scss" scoped>
</style>
