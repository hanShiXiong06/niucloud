<template>
    <el-form label-width="96px" class="role-fields">
        <template v-if="section!=='style'">
            <el-form-item label="入口标题"
                ><el-input :model-value="values.title" maxlength="30" @update:model-value="patch('title',$event)"
            /></el-form-item>
            <el-form-item label="入口说明"
                ><el-input
                    :model-value="values.subtitle"
                    maxlength="100"
                    placeholder="留空不显示"
                    @update:model-value="patch('subtitle',$event)"
            /></el-form-item>
            <el-form-item label="按钮文字"
                ><el-input
                    :model-value="values.buttonText"
                    maxlength="24"
                    placeholder="留空不显示"
                    @update:model-value="patch('buttonText',$event)"
            /></el-form-item>
            <el-form-item label="按钮样式"
                ><el-radio-group :model-value="values.buttonStyle" @update:model-value="patch('buttonStyle',$event)"
                    ><el-radio-button label="solid">实色按钮</el-radio-button
                    ><el-radio-button label="link">文字链接</el-radio-button></el-radio-group
                ></el-form-item
            >
            <el-form-item label="入口插画"
                ><UploadImage :model-value="values.image" :limit="1" @update:model-value="patch('image',$event)"
            /></el-form-item>
            <el-form-item label="显示插画"
                ><el-switch
                    :model-value="values.showImage"
                    :active-value="1"
                    :inactive-value="0"
                    @update:model-value="patch('showImage',$event)"
            /></el-form-item>
            <el-form-item label="使用原版素材"
                ><el-switch
                    :model-value="values.usePresetImages"
                    :active-value="1"
                    :inactive-value="0"
                    @update:model-value="patch('usePresetImages',$event)"
            /></el-form-item>
            <el-form-item label="轻动效"
                ><el-switch
                    :model-value="values.animate"
                    :active-value="1"
                    :inactive-value="0"
                    @update:model-value="patch('animate',$event)"
            /></el-form-item>
            <el-form-item label="卡片排版"
                ><el-select :model-value="values.layoutPreset" @update:model-value="patch('layoutPreset',$event)"
                    ><el-option label="经典图文" value="classic" /><el-option label="紧凑" value="compact" /><el-option
                        label="简约"
                        value="clean" /><el-option label="浅色" value="soft" /></el-select
            ></el-form-item>
        </template>
        <el-collapse v-if="section!=='content'"
            ><el-collapse-item :title="nested?'这张卡的配色、背景与间距':'卡片配色与间距'" name="appearance">
                <el-form-item
                    v-for="field in colors.filter(item=>item.key!=='buttonTextColor'||values.buttonStyle==='solid')"
                    :key="field.key"
                    :label="field.label"
                    ><el-color-picker
                        :model-value="values[field.key]"
                        :show-alpha="field.key==='borderColor'"
                        @update:model-value="patch(field.key,$event||'')"
                /></el-form-item>
                <el-form-item label="显示细边框"
                    ><el-switch
                        :model-value="values.showBorder"
                        :active-value="1"
                        :inactive-value="0"
                        @update:model-value="patch('showBorder',$event)"
                /></el-form-item>
                <el-form-item
                    v-for="field in dimensions.filter(item=>nested || item.key!=='itemRadius')"
                    :key="field.key"
                    :label="field.label"
                    ><el-input-number
                        :model-value="values[field.key]"
                        :min="field.min"
                        :max="field.max"
                        :precision="0"
                        controls-position="right"
                        @update:model-value="patch(field.key,$event)"
                    /><span class="role-fields-unit">px</span></el-form-item
                >
                <template v-if="nested">
                    <el-form-item label="卡片背景色"
                        ><el-color-picker
                            :model-value="values.componentStartBgColor"
                            show-alpha
                            @update:model-value="patch('componentStartBgColor',$event||'')"
                    /></el-form-item>
                    <el-form-item label="渐变止色"
                        ><el-color-picker
                            :model-value="values.componentEndBgColor"
                            show-alpha
                            @update:model-value="patch('componentEndBgColor',$event||'')"
                    /></el-form-item>
                    <el-form-item label="渐变方向"
                        ><el-select
                            :model-value="values.componentGradientAngle"
                            @update:model-value="patch('componentGradientAngle',$event)"
                            ><el-option label="斜向" value="120deg" /><el-option
                                label="向下"
                                value="to bottom" /><el-option label="向右" value="to right" /></el-select
                    ></el-form-item>
                    <el-form-item label="卡片背景图"
                        ><UploadImage
                            :model-value="values.componentBgUrl"
                            :limit="1"
                            @update:model-value="patch('componentBgUrl',$event)"
                    /></el-form-item>
                    <el-form-item label="图片遮罩"
                        ><el-slider
                            :model-value="values.componentBgAlpha"
                            :min="0"
                            :max="10"
                            show-input
                            @update:model-value="patch('componentBgAlpha',$event)"
                    /></el-form-item>
                    <p class="role-fields-note">只修改这张卡。组合区块外层背景与外边距仍在牛云通用样式中设置。</p>
                </template>
            </el-collapse-item></el-collapse
        >
    </el-form>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import UploadImage from '@/components/upload-image/index.vue'
const props=withDefaults(defineProps<{modelValue:Record<string,any>;fallback?:Record<string,any>;section?:'all'|'content'|'style';nested?:boolean}>(),{fallback:()=>({}),section:'all',nested:false})
const emit=defineEmits<{(event:'update:modelValue',value:Record<string,any>):void}>()
const values=computed(()=>({...props.fallback,...props.modelValue}))
const colors=[{key:'titleColor',label:'标题颜色'},{key:'textColor',label:'说明颜色'},{key:'borderColor',label:'细边框'},{key:'buttonColor',label:'按钮背景/链接色'},{key:'buttonTextColor',label:'按钮文字'}]
const dimensions=[{key:'titleSize',label:'标题字号',min:14,max:28},{key:'textSize',label:'说明字号',min:12,max:20},{key:'contentPadding',label:'内容内边距',min:0,max:32},{key:'itemRadius',label:'卡片圆角',min:0,max:24},{key:'buttonRadius',label:'按钮圆角',min:0,max:28}]
function patch(key:string,value:any){emit('update:modelValue',{...props.modelValue,[key]:value})}
</script>
<style scoped>
.role-fields{padding:0 10px}.role-fields-note{font-size:12px;line-height:1.7;color:var(--el-text-color-secondary)}.role-fields-unit{margin-left:8px;font-size:12px;color:var(--el-text-color-secondary)}.role-fields :deep(.el-slider){min-width:160px}
</style>
