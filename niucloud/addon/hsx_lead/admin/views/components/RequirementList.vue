<template>
    <section class="lead-requirement-list">
        <div class="lead-status-line"><el-tag type="primary" effect="plain">共 {{ items.length }} 款设备</el-tag><span class="lead-muted">{{ mode === 'any' ? '备选需求，只需满足其中一款' : '数量与预算' }}</span></div>
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
withDefaults(defineProps<{ items: Row[], mode?: string }>(), { mode: 'all' })
</script>
