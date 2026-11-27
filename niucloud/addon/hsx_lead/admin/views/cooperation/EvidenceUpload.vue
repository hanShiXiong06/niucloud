<template>
    <HsxUpload
        v-model="items"
        :uploader="upload"
        :result-adapter="adapt"
        :limit="6"
        :max-size-mb="5"
        :preview-size="80"
        :disabled="disabled"
        accept="image/jpeg,image/png,image/webp"
        tip="最多6张，每张5MB；仅接收当前操作人通过本插件上传的附件。"
    />
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'
import HsxUpload from '@/addon/hsx_components/components/HsxUpload/index.vue'
import { uploadCooperationAttachment } from '../../api/cooperation'
import type { HsxUploadItem } from '@/addon/hsx_components/types'
const props=withDefaults(defineProps<{modelValue:string[];disabled?:boolean}>(),{disabled:false})
const emit=defineEmits<{(event:'update:modelValue',value:string[]):void;(event:'busy',value:boolean):void}>()
let uploading=0
async function upload(file:File){uploading++;emit('busy',true);try{return await uploadCooperationAttachment(file)}finally{uploading--;emit('busy',uploading>0)}}
const items=computed<HsxUploadItem[]>({get:()=>props.modelValue.map((path,index)=>({name:`佐证图片 ${index+1}`,url:img(path),path,status:'success'})),set:value=>emit('update:modelValue',value.filter(item=>item.status==='success').map(item=>String(item.path||item.url)))})
function adapt(result:any,file:File):HsxUploadItem{const payload=result?.data||result;if(!payload?.path)throw new Error('附件未返回归属路径，请核对配套后端');return{name:file.name,url:payload.url||img(payload.path),path:payload.path,status:'success'}}
</script>
