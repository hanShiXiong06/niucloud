<template>
    <PageShell permission="hsx_lead_deposit_manage" scope-source="cooperation" require-all-data
        ><DepositRequests
    /></PageShell>
</template>
<script setup lang="ts">
import PageShell from '../components/PageShell.vue'
import DepositRequests from './DepositRequests.vue'
</script>
