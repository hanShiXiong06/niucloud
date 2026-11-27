<template>
    <div v-show="diyStore.editTab==='content'" class="content-wrap"
        ><div class="edit-attr-item-wrap"
            ><h3>独立角色卡片</h3
            ><el-form label-width="96px" class="px-[10px]"
                ><el-form-item label="入口角色"
                    ><el-radio-group :model-value="diyStore.editComponent.role" @update:model-value="changeRole"
                        ><el-radio-button label="stores">找门店</el-radio-button
                        ><el-radio-button label="customers">找客户</el-radio-button></el-radio-group
                    ></el-form-item
                ></el-form
            ><RoleEntryEditorFields
                :model-value="diyStore.editComponent"
                :fallback="fallback"
                section="content"
                @update:model-value="update" /></div></div
    ><div v-show="diyStore.editTab==='style'" class="style-wrap"
        ><div class="edit-attr-item-wrap"
            ><h3>本张卡片</h3
            ><RoleEntryEditorFields
                :model-value="diyStore.editComponent"
                :fallback="fallback"
                section="style"
                @update:model-value="update" /></div
        ><slot name="style"
    /></div>
</template>
<script setup lang="ts">
import {computed,onMounted} from 'vue'
import useDiyStore from '@/stores/modules/diy'
import RoleEntryEditorFields from './RoleEntryEditorFields.vue'
import {roleEntryDefaults,roleEntryError} from '../../../composables/roleEntry'
const diyStore=useDiyStore(),fallback=computed(()=>roleEntryDefaults(diyStore.editComponent.role==='customers'?'customers':'stores'))
function update(value:Record<string,any>){Object.assign(diyStore.editComponent,value)}
function changeRole(value:unknown){if(!['stores','customers'].includes(String(value)))return;const row=diyStore.editComponent,old=roleEntryDefaults(row.role==='customers'?'customers':'stores'),next=roleEntryDefaults(value==='customers'?'customers':'stores');for(const key of ['title','subtitle','buttonText','buttonStyle'])if(row[key]===old[key])row[key]=next[key];row.role=value}
onMounted(()=>{const target=diyStore.editComponent;target.ignore=Array.isArray(target.ignore)?target.ignore:[];for(const[key,value]of Object.entries(fallback.value))if(target[key]===undefined||target[key]===null)target[key]=value;target.verify=(index:number)=>{const row=diyStore.value[index];if(row?.componentName!=='LeadRoleEntry')return{code:true,message:''};const message=roleEntryError(row);return{code:!message,message}}})
</script>
