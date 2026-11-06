<template><ProfileSectionEditor kind="benefits" :value="value"><template #style><slot name="style" /></template></ProfileSectionEditor></template>
<script setup lang="ts">
import ProfileSectionEditor from './ProfileSectionEditor.vue'
defineProps(['value'])
</script>
