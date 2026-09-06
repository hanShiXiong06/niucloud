<template>
    <div v-show="diyStore.editTab === 'content'" class="content-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">地区参与查询器</h3>
            <el-form label-width="92px" class="px-[10px]">
                <el-form-item label="区块标题"><el-input v-model.trim="diyStore.editComponent.title" maxlength="30" show-word-limit /></el-form-item>
                <el-form-item label="辅助说明"><el-input v-model.trim="diyStore.editComponent.subtitle" maxlength="60" show-word-limit /></el-form-item>
                <el-form-item label="字段名称"><el-input v-model.trim="diyStore.editComponent.fieldLabel" maxlength="20" show-word-limit /></el-form-item>
                <el-form-item label="占位提示"><el-input v-model.trim="diyStore.editComponent.placeholder" maxlength="30" show-word-limit /></el-form-item>
                <el-form-item label="按钮文字"><el-input v-model.trim="diyStore.editComponent.buttonText" maxlength="16" show-word-limit /></el-form-item>
                <el-form-item label="规则提示"><el-switch v-model="diyStore.editComponent.showRuleHint" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item v-if="Number(diyStore.editComponent.showRuleHint) === 1" label="提示文字"><el-input v-model.trim="diyStore.editComponent.ruleHint" type="textarea" :rows="3" maxlength="100" show-word-limit /></el-form-item>
            </el-form>
        </div>
        <el-alert title="该组件只负责展示与查询交互；允许参与的省、市、区请在“项目配置 → 参与地区资格”中管理，白名单不会下发给客户。" type="info" :closable="false" show-icon />
    </div>
    <div v-show="diyStore.editTab === 'style'" class="style-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">查询器样式</h3>
            <el-form label-width="92px" class="px-[10px]">
                <el-form-item label="面板颜色"><el-color-picker v-model="diyStore.editComponent.panelColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="标题颜色"><el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="正文颜色"><el-color-picker v-model="diyStore.editComponent.textColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="按钮颜色"><el-color-picker v-model="diyStore.editComponent.accentColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="按钮文字"><el-color-picker v-model="diyStore.editComponent.buttonTextColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
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
    title: '查询所在地区是否可参与',
    subtitle: '选择门店所在省、市、区，立即查看参与资格',
    fieldLabel: '门店所在地区',
    placeholder: '请选择省 / 市 / 区',
    buttonText: '立即查询',
    showRuleHint: 1,
    ruleHint: '实际结果以当前项目后台配置的可参与地区为准',
    panelColor: '#FFFFFF',
    titleColor: '#26334D',
    textColor: '#667085',
    accentColor: '#FEE502',
    buttonTextColor: '#181818'
}

diyStore.editComponent.verify = (index: number) => {
    const component = diyStore.value[index]
    if (!component || component.componentName !== 'ProjectCenterAreaEligibility') return { code: true, message: '' }
    if (!String(component.title || '').trim()) return { code: false, message: '请填写地区查询器标题' }
    if (!String(component.fieldLabel || '').trim()) return { code: false, message: '请填写地区字段名称' }
    if (!String(component.buttonText || '').trim()) return { code: false, message: '请填写查询按钮文字' }
    return { code: true, message: '' }
}

onMounted(() => {
    diyStore.editComponent.ignore = Array.isArray(diyStore.editComponent.ignore) ? diyStore.editComponent.ignore : []
    Object.entries(defaults).forEach(([key, value]) => {
        if (diyStore.editComponent[key] === undefined || diyStore.editComponent[key] === null) diyStore.editComponent[key] = value
    })
})
</script>
