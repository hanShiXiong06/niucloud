<template><div v-show="diyStore.editTab==='content'" class="content-wrap"><div class="edit-attr-item-wrap"><h3>首页标题区</h3><el-form label-width="96px" class="px-[10px]"><el-form-item label="上方小标题"><el-input v-model="diyStore.editComponent.caption" maxlength="40" /></el-form-item><el-form-item label="主标题"><el-input v-model="diyStore.editComponent.title" maxlength="60" /></el-form-item><el-form-item label="补充说明"><el-input v-model="diyStore.editComponent.subtitle" maxlength="100" /></el-form-item><el-form-item label="标题标识线"><el-switch v-model="diyStore.editComponent.showAccent" :active-value="1" :inactive-value="0" /></el-form-item><el-form-item label="标识线颜色"><el-color-picker v-model="diyStore.editComponent.accentColor" /></el-form-item></el-form></div></div><div v-show="diyStore.editTab==='style'" class="style-wrap"><DiyAppearanceFields :allow-panel="false" :allow-border="false" /><slot name="style" /></div></template>
<script setup lang="ts">
import {onMounted} from 'vue'
import useDiyStore from '@/stores/modules/diy'
import DiyAppearanceFields from './DiyAppearanceFields.vue'
const diyStore=useDiyStore()
const defaults={caption:'同城协作 · 各有所长',title:'把客户交给合适的门店',subtitle:'',showAccent:1,accentColor:'#398aff',titleColor:'#243b5b',textColor:'#718199',titleSize:22,textSize:14,contentPadding:20}
onMounted(()=>{const target=diyStore.editComponent;target.ignore=Array.isArray(target.ignore)?target.ignore:[];for(const[key,value]of Object.entries(defaults))if(target[key]===undefined||target[key]===null)target[key]=value;target.verify=(index:number)=>{const row=diyStore.value[index];return row?.componentName==='LeadCooperationHero'&&!String(row.title||'').trim()?{code:false,message:'请填写首页主标题'}:{code:true,message:''}}})
</script>
