<template>
    <div v-show="diyStore.editTab === 'content'" class="content-wrap">
        <div class="edit-attr-item-wrap"><h3 class="mb-[10px]">参考图片</h3><el-form label-width="82px" class="px-[10px]">
            <el-form-item label="区块标题"><el-input v-model.trim="diyStore.editComponent.title" maxlength="20" show-word-limit /></el-form-item>
            <el-form-item label="辅助说明"><el-input v-model.trim="diyStore.editComponent.subtitle" maxlength="50" show-word-limit /></el-form-item>
            <el-form-item label="每行数量"><el-radio-group v-model="diyStore.editComponent.columns"><el-radio-button :label="1">一张</el-radio-button><el-radio-button :label="2">两张</el-radio-button><el-radio-button :label="3">三张</el-radio-button></el-radio-group></el-form-item>
            <el-form-item label="缩略图"><el-radio-group v-model="diyStore.editComponent.imageMode"><el-radio-button label="aspectFill">裁剪铺满</el-radio-button><el-radio-button label="aspectFit">完整显示</el-radio-button></el-radio-group></el-form-item>
            <div ref="itemsRef" class="space-y-[12px]">
                <div v-for="(item,index) in diyStore.editComponent.items" :key="item.id" class="rounded-[8px] border border-dashed border-gray-300 bg-white p-[12px]">
                    <div class="mb-[12px] flex items-center justify-between"><div class="flex min-w-0 items-center gap-[8px]"><span class="drag-handle cursor-move text-gray-400">⠿</span><span class="truncate text-[14px] font-medium">{{ item.title || `示例 ${index + 1}` }}</span></div><el-button link type="danger" :disabled="diyStore.editComponent.items.length <= 1" @click="removeItem(index)">删除</el-button></div>
                    <el-form-item label="图片" class="!mb-[12px]"><upload-image v-model="item.imageUrl" :limit="1" /></el-form-item>
                    <el-form-item label="示例类型" class="!mb-[12px]"><el-radio-group v-model="item.type"><el-radio label="correct">正确示例</el-radio><el-radio label="wrong">错误示例</el-radio><el-radio label="neutral">普通参考</el-radio></el-radio-group></el-form-item>
                    <el-form-item label="标题" class="!mb-[12px]"><el-input v-model.trim="item.title" maxlength="24" show-word-limit /></el-form-item>
                    <el-form-item label="说明" class="!mb-0"><el-input v-model.trim="item.description" type="textarea" :rows="3" maxlength="100" show-word-limit /></el-form-item>
                </div>
            </div>
            <el-button class="mt-[12px] w-full" plain @click="addItem">+ 添加参考图片</el-button>
        </el-form></div>
        <el-alert title="前台仅显示紧凑缩略图，客户点击后查看清晰大图；身份证等资料请先对示例中的隐私信息脱敏。" type="info" :closable="false" show-icon />
    </div>
    <div v-show="diyStore.editTab === 'style'" class="style-wrap"><div class="edit-attr-item-wrap"><h3 class="mb-[10px]">图片样式</h3><el-form label-width="82px" class="px-[10px]">
        <el-form-item label="面板颜色"><el-color-picker v-model="diyStore.editComponent.panelColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item><el-form-item label="标题颜色"><el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item><el-form-item label="说明颜色"><el-color-picker v-model="diyStore.editComponent.textColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item><el-form-item label="强调颜色"><el-color-picker v-model="diyStore.editComponent.accentColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
    </el-form></div><slot name="style" /></div>
</template>
<script setup lang="ts">
import { nextTick,onMounted,ref } from 'vue'; import Sortable from 'sortablejs'; import useDiyStore from '@/stores/modules/diy'
const diyStore=useDiyStore(); const itemsRef=ref<HTMLElement>(); const makeId=()=>diyStore.generateRandom?diyStore.generateRandom():`${Date.now()}_${Math.random().toString(16).slice(2)}`; const defaults:Record<string,any>={title:'资料拍摄参考',subtitle:'点击图片可查看大图，请按正确示例拍摄',columns:2,imageMode:'aspectFill',panelColor:'#FFFFFF',titleColor:'#26334D',textColor:'#667085',accentColor:'#12B76A'}
const addItem=()=>diyStore.editComponent.items.push({id:makeId(),title:'正确示例',description:'画面清晰、边缘完整、文字可辨认',imageUrl:'',type:'correct'}); const removeItem=(index:number)=>{if(diyStore.editComponent.items.length>1)diyStore.editComponent.items.splice(index,1)}
diyStore.editComponent.verify = (index: number) => {
    const component = diyStore.value[index]
    if (!component || component.componentName !== 'ProjectCenterReferenceGallery') return { code:true, message:'' }
    if (!String(component.title || '').trim()) return { code:false, message:'请填写参考图片区块标题' }
    if (!Array.isArray(component.items) || !component.items.length) return { code:false, message:'请至少添加一张参考图片' }
    for (const item of component.items) {
        if (!String(item.imageUrl || '').trim()) return { code:false, message:`请上传“${item.title || '参考示例'}”图片` }
        if (!String(item.title || '').trim()) return { code:false, message:'请补全参考图片标题' }
    }
    return { code:true, message:'' }
}
onMounted(()=>{diyStore.editComponent.ignore=Array.isArray(diyStore.editComponent.ignore)?diyStore.editComponent.ignore:[];Object.entries(defaults).forEach(([key,value])=>{if(diyStore.editComponent[key]===undefined||diyStore.editComponent[key]===null)diyStore.editComponent[key]=value});if(!Array.isArray(diyStore.editComponent.items)||!diyStore.editComponent.items.length)addItem();diyStore.editComponent.items.forEach((item:any)=>{if(!item.id)item.id=makeId()});nextTick(()=>{if(!itemsRef.value)return;Sortable.create(itemsRef.value,{animation:180,handle:'.drag-handle',onEnd:({oldIndex,newIndex})=>{if(oldIndex===undefined||newIndex===undefined||oldIndex===newIndex)return;const item=diyStore.editComponent.items.splice(oldIndex,1)[0];diyStore.editComponent.items.splice(newIndex,0,item)}})})})
</script>
