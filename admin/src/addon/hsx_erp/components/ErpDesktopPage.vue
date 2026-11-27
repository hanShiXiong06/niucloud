<template>
    <HsxPage padding="none" class="erp-desktop-page"><slot /></HsxPage>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { HsxPage } from '@/addon/hsx_components/core'
import { erpMobileDestination } from '../utils/desktop-layout'
import '../styles/desktop.scss'

const route = useRoute()
onMounted(() => {
    const destination = erpMobileDestination(route.path, navigator.userAgent)
    if (destination) window.location.replace(destination)
})
</script>
