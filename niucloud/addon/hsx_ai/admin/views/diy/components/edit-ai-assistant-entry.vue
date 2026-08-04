<template>
    <div v-show="diyStore.editTab === 'content'" class="content-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">展示内容</h3>
            <el-form label-width="90px" class="px-[10px]">
                <el-form-item label="展示方式">
                    <el-radio-group v-model="diyStore.editComponent.layout">
                        <el-radio-button label="card">入口卡片</el-radio-button>
                        <el-radio-button label="compact">紧凑入口</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="标题">
                    <el-input v-model.trim="diyStore.editComponent.title" maxlength="16" show-word-limit />
                </el-form-item>
                <el-form-item label="说明">
                    <el-input v-model.trim="diyStore.editComponent.subtitle" type="textarea" :rows="3" maxlength="60" show-word-limit />
                </el-form-item>
                <el-form-item label="按钮文字">
                    <el-input v-model.trim="diyStore.editComponent.buttonText" maxlength="10" show-word-limit />
                </el-form-item>
                <el-form-item label="语音提示">
                    <el-switch v-model="diyStore.editComponent.showVoiceHint" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item v-if="diyStore.editComponent.showVoiceHint" label="提示文字">
                    <el-input v-model.trim="diyStore.editComponent.voiceHint" maxlength="12" show-word-limit />
                </el-form-item>
            </el-form>
        </div>
        <el-alert type="info" :closable="false" show-icon title="线上仅在 AI 已启用、商城业务接入已开启时展示；点击后进入 AI 对话页。" />
    </div>

    <div v-show="diyStore.editTab === 'style'" class="style-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">入口样式</h3>
            <el-form label-width="90px" class="px-[10px]">
                <el-form-item label="面板颜色">
                    <el-color-picker v-model="diyStore.editComponent.panelColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="强调颜色">
                    <el-color-picker v-model="diyStore.editComponent.accentColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="标题颜色">
                    <el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="说明颜色">
                    <el-color-picker v-model="diyStore.editComponent.subtitleColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
                <el-form-item label="按钮文字">
                    <el-color-picker v-model="diyStore.editComponent.buttonTextColor" show-alpha :predefine="diyStore.predefineColors" />
                </el-form-item>
            </el-form>
        </div>
        <slot name="style" />
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()

const defaults: Record<string, any> = {
    layout: 'card',
    title: 'AI 选机助手',
    subtitle: '说预算、品牌和成色，帮你从本站在售商品里挑选',
    buttonText: '开始咨询',
    showVoiceHint: 1,
    voiceHint: '支持语音咨询',
    panelColor: '#FFFFFF',
    accentColor: '#2563EB',
    titleColor: '#172033',
    subtitleColor: '#667085',
    buttonTextColor: '#FFFFFF'
}

diyStore.editComponent.verify = () => {
    if (!String(diyStore.editComponent.title || '').trim()) return { code: false, message: '请填写 AI 入口标题' }
    if (!String(diyStore.editComponent.buttonText || '').trim()) return { code: false, message: '请填写按钮文字' }
    return { code: true, message: '' }
}

onMounted(() => {
    diyStore.editComponent.ignore = Array.isArray(diyStore.editComponent.ignore) ? diyStore.editComponent.ignore : []
    Object.entries(defaults).forEach(([key, value]) => {
        if (diyStore.editComponent[key] === undefined || diyStore.editComponent[key] === null || diyStore.editComponent[key] === '') {
            diyStore.editComponent[key] = value
        }
    })
})
</script>
