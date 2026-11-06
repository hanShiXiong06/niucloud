<template><view class="lead-cooperation-hero" :style="heroStyle"><view v-if="frame.hasImage" class="lead-cooperation-hero__mask" :style="frame.mask" /><view class="lead-cooperation-hero__content"><text v-if="settings.caption" class="lead-cooperation-hero__caption">{{ settings.caption }}</text><text v-if="settings.title" class="lead-cooperation-hero__title">{{ settings.title }}</text><view v-if="shown(settings.showAccent)" class="lead-cooperation-hero__accent" :style="accentStyle" /><text v-if="settings.subtitle" class="lead-cooperation-hero__subtitle">{{ settings.subtitle }}</text></view></view></template>
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'
import { cooperationDiyFrame } from '../../../utils/cooperation-diy-frame'
import { cooperationDiyVars } from '../../../utils/cooperation-diy-style'
const props = defineProps(['component', 'index', 'global']), emit = defineEmits(['update:componentIsShow'])
const store = useDiyStore()
const settings = computed(() => ({caption:'同城协作 · 各有所长',title:'把客户交给合适的门店',subtitle:'',showAccent:1,titleSize:24,...(store.mode==='decorate'?store.value[props.index]:props.component)}))
const shown = (value: unknown) => ![false,0,'0'].includes(value as any)
const frame = computed(() => cooperationDiyFrame(settings.value, img))
const heroStyle = computed(() => ({ ...cooperationDiyVars(settings.value), ...frame.value.style }))
const accentStyle = computed(() => /^#(?:[a-f\d]{3}|[a-f\d]{6}|[a-f\d]{8})$/i.test(String(settings.value.accentColor||'')) ? {backgroundColor:settings.value.accentColor} : {})
onMounted(() => emit('update:componentIsShow', true))
function refresh() {}
defineExpose({refresh})
</script>
<style scoped>
.lead-cooperation-hero { min-width:0; }
.lead-cooperation-hero__mask { pointer-events:none; }
.lead-cooperation-hero__content { position:relative;padding:var(--lead-diy-content-padding,16px); }
.lead-cooperation-hero__caption { display:block;color:var(--lead-diy-subtle,#7c8ba0);font-size:12px;line-height:1.7;letter-spacing:1px; }
.lead-cooperation-hero__title { display:block;margin-top:6px;color:var(--lead-diy-title,#243b5b);font-size:var(--lead-diy-title-size,24px);font-weight:700;line-height:1.5;overflow-wrap:anywhere; }
.lead-cooperation-hero__accent { width:28px;height:3px;border-radius:2px;margin-top:12px;background:var(--lead-diy-primary,var(--primary-color,#398aff)); }
.lead-cooperation-hero__subtitle { display:block;margin-top:10px;color:var(--lead-diy-text,#606266);font-size:var(--lead-diy-text-size,14px);line-height:1.7; }
</style>
