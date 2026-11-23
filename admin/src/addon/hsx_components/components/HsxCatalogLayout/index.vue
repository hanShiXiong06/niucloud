<script lang="ts">export default { name: 'HsxCatalogLayout' }</script>
<script setup lang="ts">
defineProps<{groups:{label:string;items:{key:string;label:string;count?:number}[]}[];modelValue:string;label?:string}>()
const emit=defineEmits<{(event:'update:modelValue', key:string):void}>()
</script>
<template>
    <div class="hsx-catalog">
        <nav class="hsx-catalog__nav" :aria-label="label || '资料目录'">
            <div v-for="group in groups" :key="group.label" class="hsx-catalog__group">
                <h3>{{ group.label }}</h3>
                <button v-for="item in group.items" :key="item.key" type="button" :aria-current="modelValue===item.key ? 'page' : undefined" @click="emit('update:modelValue',item.key)">
                    <span>{{ item.label }}</span><small v-if="item.count !== undefined">{{ item.count.toLocaleString() }}</small>
                </button>
            </div>
        </nav>
        <section class="hsx-catalog__main"><slot /></section>
    </div>
</template>
<style scoped>
.hsx-catalog{display:grid;grid-template-columns:194px minmax(0,1fr);align-items:start;gap:16px}
.hsx-catalog__main,.hsx-catalog__nav{min-width:0;border:1px solid var(--hsx-border-color,var(--el-border-color-light));border-radius:8px;background:var(--el-bg-color,#fff)}
.hsx-catalog__nav{padding:6px 8px 14px}.hsx-catalog__group h3{margin:16px 12px 5px;color:var(--el-text-color-secondary);font-size:12px;font-weight:500}
.hsx-catalog__group button{display:flex;width:100%;gap:6px;align-items:center;justify-content:space-between;min-height:36px;padding:6px 12px;border:0;border-radius:5px;background:transparent;text-align:left;color:var(--el-text-color-regular);font:inherit;font-size:14px;cursor:pointer}
.hsx-catalog__group button:hover{background:var(--el-fill-color-light)}.hsx-catalog__group button[aria-current]{color:var(--el-color-primary);background:var(--el-color-primary-light-9);font-weight:600}.hsx-catalog__group button:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:-2px}
.hsx-catalog__group small{font-size:12px;font-variant-numeric:tabular-nums}.hsx-catalog__main{padding:20px 24px}
@media(max-width:1100px){.hsx-catalog{grid-template-columns:156px minmax(0,1fr);gap:12px}.hsx-catalog__main{padding:16px}}
@media(max-width:700px){.hsx-catalog{grid-template-columns:minmax(0,1fr)}.hsx-catalog__nav{display:flex;overflow:auto;gap:8px;padding:8px}.hsx-catalog__group{flex:0 0 auto}.hsx-catalog__group h3{margin-top:4px}}
</style>
