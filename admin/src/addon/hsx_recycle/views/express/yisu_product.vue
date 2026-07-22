<template>
    <PremiumTheme class="main-container">
        <el-card class="!border-none" shadow="never">
            <PageHeader
                title="快递产品配置"
                description="产品目录、分类、启用状态和排序统一管理；支持 Excel 异步导入。"
            />
            <el-card class="!border-none mt-[20px]" shadow="never">
                <ExpressProductCatalogEditor provider="yisu" />
            </el-card>
        </el-card>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import ExpressProductCatalogEditor from '@/addon/hsx_recycle/components/ExpressProductCatalogEditor.vue'
</script>

<style scoped lang="scss">
.main-container { padding: 20px; }
</style>
