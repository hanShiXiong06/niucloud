<template><PageShell permission="hsx_lead_manage_merchants"><MerchantPanel :initial-filter="filter" /></PageShell></template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PageShell from '../components/PageShell.vue'
import MerchantPanel from '../components/MerchantPanel.vue'
import { leadRouteFilters } from '../../composables/navigation'
const route=useRoute(),filter=computed(()=>leadRouteFilters(route.query,['ready_state']))
</script>
