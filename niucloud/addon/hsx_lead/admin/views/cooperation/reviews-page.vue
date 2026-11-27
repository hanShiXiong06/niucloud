<template>
    <PageShell permission="hsx_lead_cooperation_review"><Reviews :initial-lead-id="leadId" /></PageShell>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PageShell from '../components/PageShell.vue'
import Reviews from './Reviews.vue'
const route=useRoute()
const leadId=computed(()=>{const value=Number(route.query.lead_id);return Number.isSafeInteger(value)&&value>0?value:0})
</script>
