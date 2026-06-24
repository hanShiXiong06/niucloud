<template>
    <div class="edit-xiaoyuan-header">
        <el-form label-width="90px" class="px-4">
            <el-form-item label="背景色(上)">
                <el-color-picker v-model="diyStore.editComponent.bgStartColor" show-alpha />
            </el-form-item>
            <el-form-item label="背景色(下)">
                <el-color-picker v-model="diyStore.editComponent.bgEndColor" show-alpha />
            </el-form-item>

            <el-divider>导航栏设置</el-divider>
            <el-form-item label="消息链接">
                <diy-link v-model="diyStore.editComponent.messageUrl" />
            </el-form-item>

            <el-divider>统计栏设置</el-divider>
             
            <el-form-item label="显示今日任务">
                <el-switch v-model="showTaskStatBind" />
            </el-form-item>
            <el-form-item label="显示累计佣金">
                <el-switch v-model="showEarningStatBind" />
            </el-form-item>
            <el-form-item label="虚拟任务增量">
                <el-input-number v-model="taskVirtualAddBind" :min="0" :max="999999" :precision="0" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item label="虚拟佣金增量(元)">
                <el-input-number v-model="earningVirtualAddBind" :min="0" :max="9999999.99" :precision="2" :step="0.01" controls-position="right" class="w-full" />
            </el-form-item>
            <el-form-item label="任务文案">
                <el-input v-model="diyStore.editComponent.taskLabel" placeholder="今日任务" />
            </el-form-item>
            <el-form-item label="任务链接">
                <diy-link v-model="diyStore.editComponent.taskUrl" />
            </el-form-item>
            <el-form-item label="佣金文案">
                <el-input v-model="diyStore.editComponent.earningLabel" placeholder="累计佣金" />
            </el-form-item>
            <el-form-item label="佣金链接">
                <diy-link v-model="diyStore.editComponent.earningUrl" />
            </el-form-item>
            <el-form-item label="搜索占位文案">
                <el-input v-model="diyStore.editComponent.searchPlaceholder" placeholder="搜索任务/服务" />
            </el-form-item>
            <el-form-item label="搜索链接">
                <diy-link v-model="diyStore.editComponent.searchUrl" />
            </el-form-item>
            
            <el-divider>推广卡片设置</el-divider>
            
            <el-form-item label="显示卡片">
                <el-switch v-model="diyStore.editComponent.showPromoCard" />
            </el-form-item>
            <el-form-item label="卡片标题">
                <el-input v-model="diyStore.editComponent.promoTitle" placeholder="校园帮实名认证" />
            </el-form-item>
            <el-form-item label="卡片副标题">
                <el-input v-model="diyStore.editComponent.promoSubtitle" placeholder="安全可靠，快速认证" />
            </el-form-item>
            <el-form-item label="按钮文字">
                <el-input v-model="diyStore.editComponent.promoBtnText" placeholder="GO" maxlength="10" />
            </el-form-item>
            <el-form-item label="卡片图标">
                <upload-image v-model="diyStore.editComponent.promoIconImage" :limit="1" />
            </el-form-item>
            <el-form-item label="卡片链接">
                <diy-link v-model="diyStore.editComponent.promoUrl" />
            </el-form-item>
        </el-form>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore()
const c = () => diyStore.editComponent
const showTaskStatBind = computed({
    get: () => c().showTaskStat !== false,
    set: (v: boolean) => {
        c().showTaskStat = v
    },
})
const showEarningStatBind = computed({
    get: () => c().showEarningStat !== false,
    set: (v: boolean) => {
        c().showEarningStat = v
    },
})
const taskVirtualAddBind = computed({
    get: () => {
        const n = Number(c().taskVirtualAdd)
        return Number.isFinite(n) ? Math.min(Math.max(0, n), 999999) : 0
    },
    set: (v: number | undefined) => {
        const x = Number(v)
        c().taskVirtualAdd = Number.isFinite(x) ? Math.min(Math.max(0, x), 999999) : 0
    },
})
const earningVirtualAddBind = computed({
    get: () => {
        const n = Number(c().earningVirtualAdd)
        return Number.isFinite(n) ? Math.min(Math.max(0, n), 9999999.99) : 0
    },
    set: (v: number | undefined) => {
        const x = Number(v)
        c().earningVirtualAdd = Number.isFinite(x) ? Math.min(Math.max(0, x), 9999999.99) : 0
    },
})
</script>
