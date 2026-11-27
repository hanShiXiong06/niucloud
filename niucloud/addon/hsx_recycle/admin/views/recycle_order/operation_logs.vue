<template>
  <HsxPage title="业务操作日志" subtitle="按时间查看回收、ERP 和博远的操作。无回收来源的业务也在这里单独记录。" content-width="standard">
    <BusinessOperationList :initial-source="String(route.query.source || '')" />
  </HsxPage>
</template>
<script setup lang="ts">
import {useRoute} from 'vue-router'
import HsxPage from '@/addon/hsx_components/components/HsxPage/index.vue'
import BusinessOperationList from '../../components/BusinessOperationList.vue'
const route=useRoute()
</script>
