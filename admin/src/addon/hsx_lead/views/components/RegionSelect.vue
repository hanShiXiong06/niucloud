<template>
    <div class="lead-region">
        <el-select v-model="ids[0]" :loading="loading[0]" clearable filterable placeholder="选择省份" aria-label="省份" @change="changeProvince">
            <el-option v-for="item in options[0]" :key="item.id" :label="item.name" :value="Number(item.id)" />
        </el-select>
        <el-select v-model="ids[1]" :disabled="!ids[0]" :loading="loading[1]" clearable filterable placeholder="选择城市" aria-label="城市" @change="changeCity">
            <el-option v-for="item in options[1]" :key="item.id" :label="item.name" :value="Number(item.id)" />
        </el-select>
        <el-select v-model="ids[2]" :disabled="!ids[1]" :loading="loading[2]" clearable filterable placeholder="选择区县" aria-label="区县" @change="emitValue">
            <el-option v-for="item in options[2]" :key="item.id" :label="item.name" :value="Number(item.id)" />
        </el-select>
        <div v-if="error" class="lead-error">{{ error }} <el-button link type="primary" @click="initialise">重试加载</el-button></div>
    </div>
</template>
<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { getAreaListByPid } from '@/app/api/sys'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: Row }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: Row): void }>()
const ids = ref<any[]>([undefined, undefined, undefined]), options = ref<Row[][]>([[], [], []]), loading = reactive([false, false, false]), error = ref('')
let revision = 0, disposed = false, initialised = false
async function fetchLevel(level: number, pid: number, token: number) {
    loading[level] = true
    try { const result = await getAreaListByPid(pid); if (token === revision && !disposed) options.value[level] = result.data || [] }
    catch (cause) { if (token === revision && !disposed) error.value = errorText(cause, '地区加载失败，请重试。') }
    finally { if (token === revision && !disposed) loading[level] = false }
}
async function initialise() {
    initialised = true; const token = ++revision; error.value = ''; loading.fill(false)
    ids.value = [Number(props.modelValue.province_id || 0) || undefined, Number(props.modelValue.city_id || 0) || undefined, Number(props.modelValue.district_id || 0) || undefined]
    await Promise.all([fetchLevel(0, 0, token), ids.value[0] ? fetchLevel(1, Number(ids.value[0]), token) : Promise.resolve(), ids.value[1] ? fetchLevel(2, Number(ids.value[1]), token) : Promise.resolve()])
}
async function changeProvince() {
    const token = ++revision; loading.fill(false); error.value = ''; ids.value[1] = undefined; ids.value[2] = undefined; options.value[1] = []; options.value[2] = []; emitValue()
    if (ids.value[0]) await fetchLevel(1, Number(ids.value[0]), token)
}
async function changeCity() {
    const token = ++revision; loading.fill(false); error.value = ''; ids.value[2] = undefined; options.value[2] = []; emitValue()
    if (ids.value[1]) await fetchLevel(2, Number(ids.value[1]), token)
}
function emitValue() {
    const names = ids.value.map((id, level) => options.value[level].find(item => Number(item.id) === Number(id))?.name || '')
    emit('update:modelValue', { province_id: Number(ids.value[0] || 0), city_id: Number(ids.value[1] || 0), district_id: Number(ids.value[2] || 0), province_name: names[0], city_name: names[1], district_name: names[2] })
}
watch(() => [props.modelValue.province_id, props.modelValue.city_id, props.modelValue.district_id].join(','), () => {
    if (!initialised || [props.modelValue.province_id || 0, props.modelValue.city_id || 0, props.modelValue.district_id || 0].join(',') !== ids.value.map(id => id || 0).join(',')) void initialise()
}, { immediate: true })
onBeforeUnmount(() => { disposed = true; revision++ })
</script>
