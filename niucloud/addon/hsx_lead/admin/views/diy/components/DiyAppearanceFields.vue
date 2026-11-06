<template>
    <div class="edit-attr-item-wrap"><h3 class="mb-[10px]">内容外观</h3><el-form label-width="100px" class="px-[10px]">
        <el-form-item v-for="field in mainColors.filter(item=>(allowPanel || item.key!=='panelColor') && (allowBorder || item.key!=='borderColor'))" :key="field.key" :label="field.label"><el-color-picker v-model="diyStore.editComponent[field.key]" :show-alpha="['panelColor','borderColor'].includes(field.key)" :predefine="diyStore.predefineColors" /><span v-if="field.key==='primaryColor'" class="appearance-note">留空跟随本站主题</span><span v-else-if="field.key==='panelColor'" class="appearance-note">留空使用框架背景</span><span v-else-if="field.key==='borderColor'" class="appearance-note">支持透明，仅影响已有入口细边框</span></el-form-item>
        <el-collapse class="appearance-more"><el-collapse-item title="字体、按钮与内容间距" name="advanced">
            <el-form-item label="标题字号"><el-input-number v-model="diyStore.editComponent.titleSize" :min="14" :max="28" :precision="0" controls-position="right" /><span class="appearance-unit">px</span></el-form-item>
            <el-form-item label="正文字号"><el-input-number v-model="diyStore.editComponent.textSize" :min="12" :max="20" :precision="0" controls-position="right" /><span class="appearance-unit">px</span></el-form-item>
            <el-form-item v-for="field in extraColors" :key="field.key" :label="field.label"><el-color-picker v-model="diyStore.editComponent[field.key]" :predefine="diyStore.predefineColors" /></el-form-item>
            <el-form-item v-for="field in dimensions" :key="field.key" :label="field.label"><el-input-number v-model="diyStore.editComponent[field.key]" :min="0" :max="field.max" :precision="0" controls-position="right" /><span class="appearance-unit">px</span></el-form-item>
        </el-collapse-item></el-collapse>
        <p class="appearance-help">区块外层背景、背景图、圆角和外边距继续在下方牛云通用样式中设置。</p>
    </el-form></div>
</template>
<script setup lang="ts">
import useDiyStore from '@/stores/modules/diy'
withDefaults(defineProps<{allowPanel?:boolean;allowBorder?:boolean}>(),{allowPanel:true,allowBorder:true})
const diyStore=useDiyStore()
const mainColors=[{key:'primaryColor',label:'主色'},{key:'titleColor',label:'标题颜色'},{key:'textColor',label:'正文颜色'},{key:'iconColor',label:'图标颜色'},{key:'borderColor',label:'入口边框'},{key:'panelColor',label:'内容底色'}]
const extraColors=[{key:'subtleColor',label:'辅助文字'},{key:'buttonColor',label:'按钮背景'},{key:'buttonTextColor',label:'按钮文字'}]
const dimensions=[{key:'contentPadding',label:'内容内边距',max:32},{key:'rowGap',label:'内容间隔',max:32},{key:'itemRadius',label:'入口圆角',max:24},{key:'buttonRadius',label:'按钮圆角',max:28}]
</script>
<style scoped>
.appearance-note,.appearance-help{color:var(--el-text-color-secondary);font-size:12px;line-height:1.7}.appearance-note{margin-left:10px}.appearance-help{margin:12px 0 0}.appearance-unit{font-size:12px;color:var(--el-text-color-secondary);margin-left:8px}.appearance-more{margin:10px 0}.appearance-more :deep(.el-input-number){width:125px}
</style>
