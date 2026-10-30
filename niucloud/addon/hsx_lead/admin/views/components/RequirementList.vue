<template>
    <section class="lead-requirement-list">
        <div class="lead-status-line"><el-tag type="primary" effect="plain">{{ mode === 'all' ? '分别要多台' : '任选一款' }}</el-tag><span class="lead-muted">{{ mode === 'all' ? '以下各项分别需要，请逐项核对数量与预算' : '以下是可接受的备选机型，不是每款都要' }}</span></div>
        <div v-for="(item, index) in items" :key="`${item.catalog_id || item.title}-${index}`" class="lead-requirement-item">
            <div class="lead-requirement-heading"><strong>{{ index + 1 }}. {{ item.title }}</strong><el-tag type="info">需求 {{ item.quantity || 1 }} 台</el-tag></div>
            <p class="lead-muted">{{ item.brand ? `${item.brand} · ` : '' }}单台预算：{{ budget(item) }}<span v-if="item.remark"> · {{ item.remark }}</span></p>
            <p v-if="item.confirmed_quantity !== undefined" class="lead-muted">已核验成交 {{ item.confirmed_quantity }} 台 · 剩余需求 {{ item.remaining_quantity }} 台</p>
        </div>
        <p class="lead-muted" style="margin-top: 12px">以上为客户需求数量，不代表门店可售数量或已锁货。</p>
    </section>
</template>
<script setup lang="ts">
import { budget, type Row } from '../../composables/useLeadUi'
withDefaults(defineProps<{ items: Row[], mode?: string }>(), { mode: 'any' })
</script>
