<template>
    <div class="edit-xiaoyuan-header">
        <el-form label-width="80px" class="px-4">
            <el-form-item label="背景色(上)">
                <el-color-picker v-model="diyStore.editComponent.bgStartColor" show-alpha />
            </el-form-item>
            <el-form-item label="背景色(下)">
                <el-color-picker v-model="diyStore.editComponent.bgEndColor" show-alpha />
            </el-form-item>
            <el-form-item label="学校名称">
                <el-input v-model="diyStore.editComponent.schoolName" placeholder="选择学校" />
            </el-form-item>
            <el-form-item label="学校链接">
                <diy-link v-model="diyStore.editComponent.schoolUrl" />
            </el-form-item>
            <el-form-item label="任务数量">
                <el-input-number v-model="diyStore.editComponent.taskCount" :min="0" />
            </el-form-item>
            <el-form-item label="任务标签">
                <el-input v-model="diyStore.editComponent.taskLabel" placeholder="今日任务" />
            </el-form-item>
            <el-form-item label="任务链接">
                <diy-link v-model="diyStore.editComponent.taskUrl" />
            </el-form-item>
            <el-form-item label="佣金金额">
                <el-input v-model="diyStore.editComponent.earningAmount" placeholder="0.00" />
            </el-form-item>
            <el-form-item label="佣金标签">
                <el-input v-model="diyStore.editComponent.earningLabel" placeholder="累计佣金" />
            </el-form-item>
            <el-form-item label="佣金链接">
                <diy-link v-model="diyStore.editComponent.earningUrl" />
            </el-form-item>
            <el-form-item label="搜索提示">
                <el-input v-model="diyStore.editComponent.searchPlaceholder" placeholder="搜索任务/服务" />
            </el-form-item>
            <el-form-item label="搜索链接">
                <diy-link v-model="diyStore.editComponent.searchUrl" />
            </el-form-item>
            <el-form-item label="消息链接">
                <diy-link v-model="diyStore.editComponent.messageUrl" />
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
                <el-input v-model="diyStore.editComponent.promoBtnText" placeholder="GO" />
            </el-form-item>
            <el-form-item label="卡片图标">
                <el-input v-model="diyStore.editComponent.promoIcon" placeholder="account-fill" />
            </el-form-item>
            <el-form-item label="卡片链接">
                <diy-link v-model="diyStore.editComponent.promoUrl" />
            </el-form-item>
        </el-form>
    </div>
</template>

<script setup lang="ts">
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore()
</script>
