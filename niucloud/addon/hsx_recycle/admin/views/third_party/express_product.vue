<template>
    <PremiumTheme class="main-container">
        <el-card class="!border-none" shadow="never">
            <PageHeader title="快递产品目录" description="下载标准模板、异步导入产品，再按业务需要启用并排序。导入与配置形成完整闭环。" />
            <div class="catalog-card"><ExpressProductCatalogEditor provider="yisu" /></div>
        </el-card>
    </PremiumTheme>
</template>
<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import ExpressProductCatalogEditor from '@/addon/hsx_recycle/components/ExpressProductCatalogEditor.vue'
</script>
<style scoped lang="scss">
.main-container { padding: 20px; }.catalog-card { margin-top: 20px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
</style>
