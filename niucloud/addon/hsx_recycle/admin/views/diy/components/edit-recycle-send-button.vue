<template>
    <!-- 内容 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{ t('styleSet') }}</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item :label="t('selectStyle')" class="flex">
                    <span class="text-primary flex-1 cursor-pointer"
                          @click="showStyle">{{ diyStore.editComponent.styleName }}</span>
                    <el-icon>
                        <ArrowRight />
                    </el-icon>
                </el-form-item>
            </el-form>
        </div>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">按钮内容</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="按钮文字">
                    <el-input v-model.trim="diyStore.editComponent.buttonText" placeholder="请输入按钮文字" clearable maxlength="20" show-word-limit />
                </el-form-item>
                <el-form-item label="链接设置">
                    <diy-link v-model="diyStore.editComponent.link" />
                </el-form-item>
                <el-form-item label="按钮状态">
                    <el-switch v-model="diyStore.editComponent.isEnabled" active-text="启用" inactive-text="禁用" />
                </el-form-item>
            </el-form>
        </div>

        <div class="edit-attr-item-wrap" v-show="diyStore.editComponent.icon.control">
            <h3 class="mb-[10px]">图标设置</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="显示图标">
                    <el-switch v-model="diyStore.editComponent.icon.isShow" />
                </el-form-item>
                <el-form-item label="图标位置" v-show="diyStore.editComponent.icon.isShow">
                    <el-radio-group v-model="diyStore.editComponent.icon.position">
                        <el-radio label="left">左侧</el-radio>
                        <el-radio label="right">右侧</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="图标大小" v-show="diyStore.editComponent.icon.isShow">
                    <el-slider v-model="diyStore.editComponent.icon.size" show-input size="small" class="ml-[10px] diy-nav-slider" :min="12" :max="24" />
                </el-form-item>
            </el-form>
        </div>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">布局设置</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="按钮宽度">
                    <el-radio-group v-model="diyStore.editComponent.buttonWidth">
                        <el-radio label="auto">自适应</el-radio>
                        <el-radio label="full">全宽</el-radio>
                        <el-radio label="custom">自定义</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="自定义宽度" v-show="diyStore.editComponent.buttonWidth === 'custom'">
                    <el-slider v-model="diyStore.editComponent.customWidth" show-input size="small" class="ml-[10px] diy-nav-slider" :min="80" :max="300" />
                </el-form-item>
                <el-form-item label="对齐方式">
                    <el-radio-group v-model="diyStore.editComponent.align">
                        <el-radio label="left">左对齐</el-radio>
                        <el-radio label="center">居中</el-radio>
                        <el-radio label="right">右对齐</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
        </div>

        <el-dialog v-model="showDialog" title="选择样式" width="620px">
            <div class="flex flex-wrap">
                <div
                    class="flex items-center justify-center overflow-hidden w-[280px] h-[100px] mr-[12px] cursor-pointer border bg-gray-50"
                    :class="{ 'border-primary': selectStyle == 'style-1' }" @click="selectStyle = 'style-1'">
                    <div class="w-[120px] h-[40px] bg-blue-500 text-white rounded flex items-center justify-center text-sm">
                        默认按钮
                    </div>
                </div>
                <div
                    class="flex items-center justify-center overflow-hidden w-[280px] h-[100px] cursor-pointer border bg-gray-50"
                    :class="{ 'border-primary': selectStyle == 'style-2' }" @click="selectStyle = 'style-2'">
                    <div class="w-[120px] h-[40px] bg-gradient-to-r from-blue-500 to-green-500 text-white rounded-full flex items-center justify-center text-sm">
                        渐变按钮
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap mt-[12px]">
                <div
                    class="flex items-center justify-center overflow-hidden w-[280px] h-[100px] mr-[12px] cursor-pointer border bg-gray-50"
                    :class="{ 'border-primary': selectStyle == 'style-3' }" @click="selectStyle = 'style-3'">
                    <div class="w-[120px] h-[40px] border-2 border-blue-500 text-blue-500 rounded flex items-center justify-center text-sm">
                        边框按钮
                    </div>
                </div>
                <div
                    class="flex items-center justify-center overflow-hidden w-[280px] h-[100px] cursor-pointer border bg-gray-50"
                    :class="{ 'border-primary': selectStyle == 'style-4' }" @click="selectStyle = 'style-4'">
                    <div class="w-[120px] h-[40px] bg-red-500 text-white rounded-lg flex items-center justify-center text-sm shadow-lg">
                        阴影按钮
                    </div>
                </div>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                    <el-button type="primary" @click="changeStyle">{{ t('confirm') }}</el-button>
                </span>
            </template>
        </el-dialog>
    </div>

    <!-- 样式 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">按钮样式</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="按钮高度">
                    <el-slider v-model="diyStore.editComponent.buttonHeight" show-input size="small" class="ml-[10px] diy-nav-slider" :min="30" :max="60" />
                </el-form-item>
                <el-form-item label="圆角大小">
                    <el-slider v-model="diyStore.editComponent.borderRadius" show-input size="small" class="ml-[10px] diy-nav-slider" :min="0" :max="30" />
                </el-form-item>
                <el-form-item label="背景颜色">
                    <el-color-picker v-model="diyStore.editComponent.backgroundColor" />
                </el-form-item>
                <el-form-item label="边框颜色" v-show="diyStore.editComponent.style === 'style-3'">
                    <el-color-picker v-model="diyStore.editComponent.borderColor" />
                </el-form-item>
                <el-form-item label="边框宽度" v-show="diyStore.editComponent.style === 'style-3'">
                    <el-slider v-model="diyStore.editComponent.borderWidth" show-input size="small" class="ml-[10px] diy-nav-slider" :min="1" :max="5" />
                </el-form-item>
            </el-form>
        </div>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">文字样式</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="字体大小">
                    <el-slider v-model="diyStore.editComponent.fontSize" show-input size="small" class="ml-[10px] diy-nav-slider" :min="12" :max="20" />
                </el-form-item>
                <el-form-item label="字体粗细">
                    <el-radio-group v-model="diyStore.editComponent.fontWeight">
                        <el-radio label="normal">常规</el-radio>
                        <el-radio label="bold">加粗</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="文字颜色">
                    <el-color-picker v-model="diyStore.editComponent.textColor" />
                </el-form-item>
            </el-form>
        </div>

        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">边距设置</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item label="上边距">
                    <el-slider v-model="diyStore.editComponent.marginTop" show-input size="small" class="ml-[10px] diy-nav-slider" :min="0" :max="50" />
                </el-form-item>
                <el-form-item label="下边距">
                    <el-slider v-model="diyStore.editComponent.marginBottom" show-input size="small" class="ml-[10px] diy-nav-slider" :min="0" :max="50" />
                </el-form-item>
                <el-form-item label="左右边距">
                    <el-slider v-model="diyStore.editComponent.marginHorizontal" show-input size="small" class="ml-[10px] diy-nav-slider" :min="0" :max="50" />
                </el-form-item>
            </el-form>
        </div>

        <!-- 组件样式 -->
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'
import { ref } from 'vue'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = [] // 忽略公共属性

// 初始化组件默认属性
if (!diyStore.editComponent.buttonText) {
    diyStore.editComponent.buttonText = '立即发货'
}
if (!diyStore.editComponent.link) {
    diyStore.editComponent.link = {}
}
if (!diyStore.editComponent.isEnabled) {
    diyStore.editComponent.isEnabled = true
}
if (!diyStore.editComponent.icon) {
    diyStore.editComponent.icon = {
        control: false,
        isShow: false,
        position: 'left',
        size: 16
    }
}
if (!diyStore.editComponent.buttonWidth) {
    diyStore.editComponent.buttonWidth = 'auto'
}
if (!diyStore.editComponent.customWidth) {
    diyStore.editComponent.customWidth = 120
}
if (!diyStore.editComponent.align) {
    diyStore.editComponent.align = 'center'
}
if (!diyStore.editComponent.buttonHeight) {
    diyStore.editComponent.buttonHeight = 40
}
if (!diyStore.editComponent.borderRadius) {
    diyStore.editComponent.borderRadius = 4
}
if (!diyStore.editComponent.backgroundColor) {
    diyStore.editComponent.backgroundColor = '#409EFF'
}
if (!diyStore.editComponent.borderColor) {
    diyStore.editComponent.borderColor = '#409EFF'
}
if (!diyStore.editComponent.borderWidth) {
    diyStore.editComponent.borderWidth = 2
}
if (!diyStore.editComponent.fontSize) {
    diyStore.editComponent.fontSize = 14
}
if (!diyStore.editComponent.fontWeight) {
    diyStore.editComponent.fontWeight = 'normal'
}
if (!diyStore.editComponent.textColor) {
    diyStore.editComponent.textColor = '#FFFFFF'
}
if (!diyStore.editComponent.marginTop) {
    diyStore.editComponent.marginTop = 10
}
if (!diyStore.editComponent.marginBottom) {
    diyStore.editComponent.marginBottom = 10
}
if (!diyStore.editComponent.marginHorizontal) {
    diyStore.editComponent.marginHorizontal = 15
}
if (!diyStore.editComponent.style) {
    diyStore.editComponent.style = 'style-1'
}
if (!diyStore.editComponent.styleName) {
    diyStore.editComponent.styleName = '默认按钮'
}

const showDialog = ref(false)

const showStyle = () => {
    showDialog.value = true
}

const selectStyle = ref(diyStore.editComponent.style)

const changeStyle = () => {
    switch (selectStyle.value) {
        case 'style-1':
            diyStore.editComponent.styleName = '默认按钮'
            diyStore.editComponent.backgroundColor = '#409EFF'
            diyStore.editComponent.textColor = '#FFFFFF'
            diyStore.editComponent.borderRadius = 4
            diyStore.editComponent.icon.control = false
            break
        case 'style-2':
            diyStore.editComponent.styleName = '渐变按钮'
            diyStore.editComponent.backgroundColor = 'linear-gradient(45deg, #409EFF, #67C23A)'
            diyStore.editComponent.textColor = '#FFFFFF'
            diyStore.editComponent.borderRadius = 20
            diyStore.editComponent.icon.control = true
            break
        case 'style-3':
            diyStore.editComponent.styleName = '边框按钮'
            diyStore.editComponent.backgroundColor = 'transparent'
            diyStore.editComponent.textColor = '#409EFF'
            diyStore.editComponent.borderColor = '#409EFF'
            diyStore.editComponent.borderWidth = 2
            diyStore.editComponent.borderRadius = 4
            diyStore.editComponent.icon.control = true
            break
        case 'style-4':
            diyStore.editComponent.styleName = '阴影按钮'
            diyStore.editComponent.backgroundColor = '#F56C6C'
            diyStore.editComponent.textColor = '#FFFFFF'
            diyStore.editComponent.borderRadius = 8
            diyStore.editComponent.icon.control = false
            break
    }
    diyStore.editComponent.style = selectStyle.value
    showDialog.value = false
}

defineExpose({})
</script>

<style lang="scss" scoped></style>