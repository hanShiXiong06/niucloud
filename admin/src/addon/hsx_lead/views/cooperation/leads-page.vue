<template><PageShell permission="hsx_lead_cooperation_leads" scope-source="cooperation" v-slot="{scope}"><Leads :viewer-scope="scope" :initial-filter="filter" :can-review="allows('review')" @review="openReview" /></PageShell></template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageShell from '../components/PageShell.vue'
import Leads from './Leads.vue'
import { useCooperationAccess } from '../../composables/cooperation'
import { leadPageLocation, leadRouteFilters } from '../../composables/navigation'
const route=useRoute(),router=useRouter(),{allows}=useCooperationAccess()
const filter=computed(()=>leadRouteFilters(route.query,['status','keyword']))
function openReview(id:number){void router.push(leadPageLocation('reviews',{lead_id:id}))}
</script>
