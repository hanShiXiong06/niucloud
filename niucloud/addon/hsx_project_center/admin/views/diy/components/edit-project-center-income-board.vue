<template>
    <div v-show="diyStore.editTab === 'content'" class="content-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">收益榜单</h3>
            <el-form label-width="88px" class="px-[10px]">
                <el-form-item label="区块标题"><el-input v-model.trim="diyStore.editComponent.title" maxlength="20" show-word-limit /></el-form-item>
                <el-form-item label="辅助说明"><el-input v-model.trim="diyStore.editComponent.subtitle" maxlength="40" show-word-limit /></el-form-item>
                <el-form-item label="右侧角标"><el-input v-model.trim="diyStore.editComponent.badgeText" maxlength="10" show-word-limit /></el-form-item>
                <el-form-item label="每屏行数">
                    <el-radio-group v-model="diyStore.editComponent.visibleRows">
                        <el-radio-button :label="1">1 行</el-radio-button>
                        <el-radio-button :label="3">3 行</el-radio-button>
                        <el-radio-button :label="5">5 行</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="自动滚动"><el-switch v-model="diyStore.editComponent.autoplay" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item v-if="Number(diyStore.editComponent.autoplay) === 1" label="滚动间隔">
                    <el-slider v-model="diyStore.editComponent.interval" :min="1500" :max="8000" :step="100" show-input />
                </el-form-item>
                <el-form-item label="组件内滑动">
                    <div class="w-full">
                        <el-switch v-model="diyStore.editComponent.manualSwipe" :active-value="1" :inactive-value="0" />
                        <div class="mt-[5px] text-[12px] leading-[18px] text-[#98a2b3]">默认关闭。客户在榜单区域上下滑动时滚动整个页面；开启后才允许手动切换榜单。</div>
                    </div>
                </el-form-item>
                <el-form-item label="展示日期"><el-switch v-model="diyStore.editComponent.showDate" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="风险说明"><el-switch v-model="diyStore.editComponent.showDisclaimer" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item v-if="Number(diyStore.editComponent.showDisclaimer) === 1" label="说明文字">
                    <el-input v-model.trim="diyStore.editComponent.disclaimer" type="textarea" :rows="3" maxlength="80" show-word-limit />
                </el-form-item>
            </el-form>
        </div>
        <el-alert title="组件会自动读取当前项目在“收益展示”中最新一天的数据；装修预览使用示例数据，不需要在组件内重复录入。" type="info" :closable="false" show-icon />
    </div>
    <div v-show="diyStore.editTab === 'style'" class="style-wrap">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">榜单样式</h3>
            <el-form label-width="88px" class="px-[10px]">
                <el-form-item label="面板颜色"><el-color-picker v-model="diyStore.editComponent.panelColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="标题颜色"><el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="正文颜色"><el-color-picker v-model="diyStore.editComponent.textColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="强调颜色"><el-color-picker v-model="diyStore.editComponent.accentColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="金额颜色"><el-color-picker v-model="diyStore.editComponent.amountColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
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
    title: '门店收益榜',
    subtitle: '每日运营数据，持续更新',
    badgeText: '每日更新',
    visibleRows: 3,
    autoplay: 1,
    interval: 2600,
    manualSwipe: 0,
    showDate: 1,
    showDisclaimer: 1,
    disclaimer: '展示数据由运营录入，仅作项目案例展示，不构成收益承诺',
    panelColor: '#FFFFFF',
    titleColor: '#26334D',
    textColor: '#667085',
    accentColor: '#315CF5',
    amountColor: '#F04438'
}

diyStore.editComponent.verify = (index: number) => {
    const component = diyStore.value[index]
    if (!component || component.componentName !== 'ProjectCenterIncomeBoard') return { code: true, message: '' }
    if (!String(component.title || '').trim()) return { code: false, message: '请填写收益榜单标题' }
    if (![1, 3, 5].includes(Number(component.visibleRows))) return { code: false, message: '请选择正确的每屏展示行数' }
    if (Number(component.autoplay) === 1 && (Number(component.interval) < 1500 || Number(component.interval) > 8000)) {
        return { code: false, message: '榜单滚动间隔需设置为 1.5 至 8 秒' }
    }
    if (Number(component.showDisclaimer) === 1 && !String(component.disclaimer || '').trim()) {
        return { code: false, message: '请填写榜单风险说明' }
    }
    return { code: true, message: '' }
}

onMounted(() => {
    diyStore.editComponent.ignore = Array.isArray(diyStore.editComponent.ignore) ? diyStore.editComponent.ignore : []
    Object.entries(defaults).forEach(([key, value]) => {
        if (diyStore.editComponent[key] === undefined || diyStore.editComponent[key] === null) diyStore.editComponent[key] = value
    })
})
</script>
