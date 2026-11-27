<template>
    <div v-show="diyStore.editTab === 'content'" class="content-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">折叠内容</h3>
            <el-form label-width="82px" class="px-[10px]">
                <el-form-item label="区块标题"><el-input v-model.trim="diyStore.editComponent.title" maxlength="20" show-word-limit /></el-form-item>
                <el-form-item label="辅助说明"><el-input v-model.trim="diyStore.editComponent.subtitle" maxlength="40" show-word-limit /></el-form-item>
                <el-form-item label="展开方式">
                    <el-radio-group v-model="diyStore.editComponent.accordion">
                        <el-radio-button :label="1">仅展开一项</el-radio-button><el-radio-button :label="0">可展开多项</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <div ref="itemsRef" class="space-y-[12px]">
                    <div v-for="(item, index) in diyStore.editComponent.items" :key="item.id" class="rounded-[8px] border border-dashed border-gray-300 bg-white p-[12px]">
                        <div class="mb-[12px] flex items-center justify-between">
                            <div class="flex min-w-0 items-center gap-[8px]"><span class="drag-handle cursor-move text-gray-400">⠿</span><span class="truncate text-[14px] font-medium">{{ item.title || `内容 ${index + 1}` }}</span></div>
                            <el-button link type="danger" :disabled="diyStore.editComponent.items.length <= 1" @click="removeItem(index)">删除</el-button>
                        </div>
                        <el-form-item label="标题" class="!mb-[12px]"><el-input v-model.trim="item.title" maxlength="30" show-word-limit /></el-form-item>
                        <el-form-item label="摘要" class="!mb-[12px]"><el-input v-model.trim="item.summary" maxlength="60" show-word-limit /></el-form-item>
                        <el-form-item label="详细内容" class="!mb-[12px]"><el-input v-model="item.content" type="textarea" :rows="5" maxlength="1000" show-word-limit placeholder="支持换行，建议只保留客户真正需要理解的内容" /></el-form-item>
                        <el-form-item label="默认展开" class="!mb-0"><el-switch v-model="item.defaultOpen" :active-value="1" :inactive-value="0" /></el-form-item>
                    </div>
                </div>
                <el-button class="mt-[12px] w-full" plain @click="addItem">+ 添加折叠项</el-button>
            </el-form>
        </div>
        <el-alert title="适合放项目介绍、办理流程、资料要求和常见问题；内容默认收起，减少页面信息噪音。" type="info" :closable="false" show-icon />
    </div>
    <div v-show="diyStore.editTab === 'style'" class="style-wrap">
        <div class="edit-attr-item-wrap"><h3 class="mb-[10px]">内容样式</h3><el-form label-width="82px" class="px-[10px]">
            <el-form-item label="面板颜色"><el-color-picker v-model="diyStore.editComponent.panelColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
            <el-form-item label="标题颜色"><el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
            <el-form-item label="正文颜色"><el-color-picker v-model="diyStore.editComponent.textColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
            <el-form-item label="强调颜色"><el-color-picker v-model="diyStore.editComponent.accentColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
        </el-form></div><slot name="style" />
    </div>
</template>
<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import Sortable from 'sortablejs'
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore(); const itemsRef = ref<HTMLElement>()
const makeId = () => diyStore.generateRandom ? diyStore.generateRandom() : `${Date.now()}_${Math.random().toString(16).slice(2)}`
const defaults: Record<string, any> = { title:'项目说明', subtitle:'点击标题查看详细内容', accordion:1, panelColor:'#FFFFFF', titleColor:'#26334D', textColor:'#667085', accentColor:'#315CF5' }
const addItem = () => diyStore.editComponent.items.push({ id:makeId(), title:'新的内容', summary:'', content:'', defaultOpen:0 })
const removeItem = (index:number) => { if (diyStore.editComponent.items.length > 1) diyStore.editComponent.items.splice(index, 1) }
diyStore.editComponent.verify = (index: number) => {
    const component = diyStore.value[index]
    if (!component || component.componentName !== 'ProjectCenterCollapse') return { code:true, message:'' }
    if (!String(component.title || '').trim()) return { code:false, message:'请填写折叠区块标题' }
    if (!Array.isArray(component.items) || !component.items.length) return { code:false, message:'请至少添加一项折叠内容' }
    for (const item of component.items) {
        if (!String(item.title || '').trim()) return { code:false, message:'请补全折叠项标题' }
        if (!String(item.content || '').trim()) return { code:false, message:`请填写“${item.title || '折叠项'}”的详细内容` }
    }
    return { code:true, message:'' }
}
onMounted(() => { diyStore.editComponent.ignore = Array.isArray(diyStore.editComponent.ignore) ? diyStore.editComponent.ignore : []; Object.entries(defaults).forEach(([key,value]) => { if (diyStore.editComponent[key] === undefined || diyStore.editComponent[key] === null) diyStore.editComponent[key] = value }); if (!Array.isArray(diyStore.editComponent.items) || !diyStore.editComponent.items.length) addItem(); diyStore.editComponent.items.forEach((item:any) => { if (!item.id) item.id = makeId() }); nextTick(() => { if (!itemsRef.value) return; Sortable.create(itemsRef.value, { animation:180, handle:'.drag-handle', onEnd:({oldIndex,newIndex}) => { if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) return; const item = diyStore.editComponent.items.splice(oldIndex,1)[0]; diyStore.editComponent.items.splice(newIndex,0,item) } }) }) })
</script>
