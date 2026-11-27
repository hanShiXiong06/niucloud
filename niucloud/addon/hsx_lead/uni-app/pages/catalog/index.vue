<template><view class="catalog-page" :style="themeColor()"><ModelCatalogPicker v-if="ready" page :show="true" :model-value="items" :title="title" :max="max" :business-type="businessType" :selection-mode="selectionMode" :bulk-actions="bulkActions" @update:model-value="select" @close="backCooperationTask" /><template v-else><CooperationNav title="选择业务能力" back @back="backCooperationTask" /><CoopState title="请从业务表单打开选择器" hint="返回表单后重新选择，不会清除已填写的内容。" /></template></view></template>
<script setup lang="ts">
import { getCurrentInstance, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import ModelCatalogPicker from '../../components/cooperation/ModelCatalogPicker.vue'
import CooperationNav from '../../components/cooperation/CooperationNav.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import { backCooperationTask } from '../../utils/cooperation-navigation'
const instance = getCurrentInstance(), ready = ref(false), title = ref('选择业务能力'), max = ref(0), businessType = ref('purchase'), selectionMode = ref<'single' | 'multiple'>('multiple'), bulkActions = ref(false), items = ref<Record<string, any>[]>([])
let channel: any
onLoad(() => { channel = (instance?.proxy as any)?.getOpenerEventChannel?.(); channel?.on('initialize', (options: Record<string, any>) => { title.value = String(options.title || '选择业务能力'); max.value = Math.max(0, Number(options.max) || 0); businessType.value = String(options.business_type || 'purchase'); selectionMode.value = options.selection_mode === 'single' ? 'single' : 'multiple'; bulkActions.value = options.allow_bulk === true; items.value = Array.isArray(options.items) ? options.items : []; ready.value = true }) })
// Keep one update listener: the mini-program compiler otherwise lets v-model's
// generated setter replace the callback that returns choices to the opener.
// The picker closes immediately after returning the choice. Updating its input
// here would send thousands of leaves through the native bridge a second time.
function select(value: Record<string, any>[]) { channel?.emit('selected', value) }
</script>
<style scoped>
.catalog-page { height:calc(100vh - var(--window-top,0px) - var(--window-bottom,0px));min-height:0;display:flex;flex-direction:column;overflow:hidden;box-sizing:border-box; }
/* #ifdef H5 */
@supports(height:100dvh){.catalog-page{height:calc(100dvh - var(--window-top,0px) - var(--window-bottom,0px));}}
/* #endif */
</style>
