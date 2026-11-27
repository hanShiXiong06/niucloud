<template>
    <view class="lead-business-frame" :style="frame.style"
        ><view v-if="frame.hasImage" class="lead-business-frame-mask" :style="frame.mask" /><view
            class="lead-business-frame-content"
            ><CoopBusinessNavigation ref="businessRef" :appearance="settings" /></view
    ></view>
</template>
<script setup lang="ts">
import {computed,onMounted,ref} from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'
import CoopBusinessNavigation from '../../cooperation/CoopBusinessNavigation.vue'
import { cooperationDiyFrame } from '../../../utils/cooperation-diy-frame'
const props=defineProps(['component','index','global']),emit=defineEmits(['update:componentIsShow'])
const store=useDiyStore(),businessRef=ref<any>(null)
const settings=computed(()=>store.mode==='decorate'?store.value[props.index]:props.component)
const frame = computed(() => cooperationDiyFrame(settings.value || {}, img))
onMounted(()=>emit('update:componentIsShow',true))
function refresh(){return businessRef.value?.refresh?.()}
defineExpose({refresh})
</script>
<style scoped>
.lead-business-frame { min-width:0; }
.lead-business-frame-mask { pointer-events:none; }
.lead-business-frame-content { position:relative; }
</style>
