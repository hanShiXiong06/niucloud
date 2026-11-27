<template>
    <CooperationWorkbench
        ref="cooperationRef"
        initial-tab="stores"
        :initial-direction="initialDirection"
        :initial-id="collaborationId"
        :initial-inviter="promotionInviter"
        :initial-promotion="promotionRequested"
    />
</template>
<script setup lang="ts">
import CooperationWorkbench from '../../components/cooperation/CooperationWorkbench.vue'
import { useCooperationPage } from '../../composables/useCooperationPage'
const { cooperationRef, collaborationId, promotionInviter, promotionRequested, initialDirection } = useCooperationPage('outgoing', true)
</script>
