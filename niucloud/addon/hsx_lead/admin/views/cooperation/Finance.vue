<template>
    <section>
        <header class="coop-head"><div><h2>付佣与提现</h2><p>只管理协作费用和发布方收益，不是门店与客户之间的商品货款。</p></div></header>
        <el-tabs v-model="tab"><el-tab-pane label="业务收款单" name="orders"><Orders v-if="tab==='orders'" /></el-tab-pane><el-tab-pane label="提现审核与打款" name="withdrawals"><Withdrawals v-if="tab==='withdrawals'" /></el-tab-pane></el-tabs>
    </section>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import Orders from './Orders.vue'
import Withdrawals from './Withdrawals.vue'
const tab=ref('orders')
</script>
