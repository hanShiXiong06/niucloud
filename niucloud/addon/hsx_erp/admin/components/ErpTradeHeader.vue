<template>
  <section class="trade-head" :aria-label="title">
    <HsxTitle size="page" :title="title" :subtitle="subtitle">
      <template #extra><div class="head-actions"><slot name="actions" /></div></template>
    </HsxTitle>
    <div v-if="$slots.mode" class="mode"><slot name="mode" /></div>
    <el-form inline class="lookup" @submit.prevent="emit('search')">
      <slot name="quick" />
      <div class="tools">
        <el-button native-type="submit" type="primary" plain>查询</el-button>
        <el-button @click="emit('reset')">重置</el-button>
        <el-button link type="primary" :aria-expanded="filters" :aria-controls="page + '-advanced'" @click="toggle('filters')">{{ filters ? '收起筛选' : '更多筛选' }}{{ advancedCount ? ' · ' + advancedCount : '' }}</el-button>
        <el-button link :aria-expanded="summary" :aria-controls="page + '-summary'" @click="toggle('summary')">{{ summary ? '收起汇总' : '本页汇总' }}</el-button>
      </div>
      <div v-show="filters" :id="page + '-advanced'" class="secondary"><slot name="advanced" /></div>
    </el-form>
    <div v-if="summary" :id="page + '-summary'" class="summary"><slot name="summary" /></div>
  </section>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { HsxTitle } from '@/addon/hsx_components/core'
import useUserStore from '@/stores/modules/user'
import { tradeScope, tradePreferences } from '../utils/trade-workspace'
const props = defineProps<{ page: 'purchase'|'sale'; title: string; subtitle: string; advancedCount: number }>()
const emit = defineEmits<{(e:'search'):void; (e:'reset'):void}>()
const user = useUserStore(), filters = ref(false), summary = ref(false)
const scope = computed(() => tradeScope(user.siteInfo?.site_id, (user.userInfo as {uid?:number})?.uid))
let storage: Storage|undefined
try { storage = window.localStorage } catch { /* private browsing */ }
const prefs = computed(() => tradePreferences(storage, scope.value, props.page))
watch(prefs, p => { filters.value = p.get('filters'); summary.value = p.get('summary') }, {immediate:true})
function toggle(key:'filters'|'summary') { const v = key === 'filters' ? filters : summary; v.value = !v.value; prefs.value.set(key, v.value) }
</script>
<style scoped>
.trade-head{display:grid;gap:14px;min-width:0}.head-actions,.tools,.secondary,.lookup{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px}.lookup{gap:10px 16px}.tools{margin-left:auto}.tools :deep(.el-button),.head-actions :deep(.el-button){margin-left:0}.lookup :deep(.el-form-item){margin:0}.lookup :deep(.el-form-item__label){padding-right:8px}.secondary{width:100%;padding:14px 0 2px;border-top:1px solid var(--el-border-color-lighter)}.summary{border-top:1px solid var(--el-border-color-lighter);padding-top:8px}.summary :deep(.erp-summary-heading){margin-top:0}.mode{min-width:0}
@media(max-width:1250px){.tools{margin-left:0}.lookup{gap:8px 12px}}@media(max-width:900px){.lookup :deep(.el-form-item){max-width:100%}.head-actions{gap:5px}}
</style>
