<template>
    <CoopRoleEntryCard :role="settings.role" :appearance="settings" :preview="store.mode==='decorate'" />
</template>
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import useDiyStore from '@/app/stores/diy'
import CoopRoleEntryCard from '../../cooperation/CoopRoleEntryCard.vue'
const props = defineProps(['component', 'index', 'global']), emit = defineEmits(['update:componentIsShow'])
const store = useDiyStore()
const settings = computed(() => (store.mode === 'decorate' ? store.value[props.index] : props.component) || {})
onMounted(() => emit('update:componentIsShow', true))
function refresh() {}
defineExpose({refresh})
</script>
