<template>
    <section v-if="task.deal_history?.length" class="lead-form-section"><h3>已核验成交批次 · 累计 {{ money(task.total_confirmed_amount) }}</h3><el-collapse><el-collapse-item v-for="batch in task.deal_history" :key="batch.revision" :name="batch.revision" :title="`第 ${batch.revision} 批 · ${batch.deal_no || '已核验'} · ${money(batch.amount)}`"><HsxTable :columns="columns" :data="batch.items || []"><template #price="{ row }">{{ money(row.unit_price) }}</template><template #amount="{ row }">{{ money(Number(row.quantity) * Number(row.unit_price)) }}</template></HsxTable><p class="lead-muted">核验时间 {{ time(batch.verified_at) }} · 本批固定佣金 {{ money(batch.commission_amount) }}</p><p v-if="batch.close_remaining" class="lead-muted">本批结束剩余需求：{{ batch.remaining_reason }}</p></el-collapse-item></el-collapse></section>
</template>
<script setup lang="ts">
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { money, time, type Row } from '../../composables/useLeadUi'
defineProps<{ task: Row }>()
const columns = [{ prop: 'title', label: '实际成交机型', minWidth: 200 }, { prop: 'quantity', label: '台数', width: 70 }, { prop: 'unit_price', label: '单价', slot: 'price', width: 120 }, { prop: 'amount', label: '金额', slot: 'amount', width: 120 }]
</script>
