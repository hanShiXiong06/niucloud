<template>
  <nav class="entry-steps" aria-label="开单步骤">
    <button v-for="(label,i) in labels" :key="label" type="button" :aria-current="step === i ? 'step' : undefined" :aria-label="(i+1) + ' ' + label"
      :disabled="busy" @click="$emit('choose',i)">
      <span>{{ i + 1 }}</span>{{ label }}
    </button>
  </nav>
</template>
<script setup lang="ts">
defineProps<{step:number; labels:string[]; busy?:boolean}>()
defineEmits<{(e:'choose',step:number):void}>()
</script>
<style scoped>
.entry-steps{display:flex;gap:0;align-items:stretch;margin:0 0 18px;border-bottom:1px solid var(--el-border-color-light)}
.entry-steps button{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;font:inherit;color:var(--el-text-color-regular);background:none;border:0;border-bottom:2px solid transparent;min-height:42px;cursor:pointer}
.entry-steps button span{display:grid;place-items:center;font-size:12px;border:1px solid var(--el-border-color);border-radius:50%;width:20px;height:20px}
.entry-steps button[aria-current]{color:var(--el-color-primary);border-bottom-color:currentColor;font-weight:600}.entry-steps button[aria-current] span{background:var(--el-color-primary);color:var(--el-color-white);border-color:var(--el-color-primary)}.entry-steps button:focus-visible{outline:2px solid var(--el-color-primary);outline-offset:-2px}
</style>
