<template>
    <PageShell permission="hsx_lead_cooperation_manage">
        <Overview :can-review="allows('review')" :can-config="allows('config')" :can-leads="allows('leads')" />
    </PageShell>
</template>
<script setup lang="ts">
import PageShell from '../components/PageShell.vue'
import Overview from '../cooperation/Overview.vue'
import { useCooperationAccess } from '../../composables/cooperation'
const { allows } = useCooperationAccess()
</script>
