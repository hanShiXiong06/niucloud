<template>
    <div class="coop-cell entity-membership-cell">
        <div class="entity-membership-cell__heading">
            <strong>{{ membership.level_name || membership.level_code || '普通会员' }}</strong>
            <el-tag class="entity-membership-cell__status" :type="state.tone" size="small" effect="plain">{{ state.label }}</el-tag>
        </div>
        <small v-if="expiry">{{ expiry }}</small>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { entityMembershipState } from '../../composables/entity'
import { coopTime, type CoopRow } from '../../composables/cooperation'

const props = defineProps<{ membership?: CoopRow | null }>()
const membership = computed(() => props.membership || {})
const state = computed(() => entityMembershipState(membership.value))
const expiry = computed(() => Number(membership.value.expires_at) > 0
    ? `${coopTime(membership.value.expires_at)} 到期`
    : membership.value.active === true ? '长期有效' : '')
</script>

<style scoped>
.entity-membership-cell__heading{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0}
.entity-membership-cell__heading>strong{min-width:0;max-width:100%}
.entity-membership-cell__status{flex:0 0 auto;align-self:center;width:auto;max-width:100%;white-space:nowrap}
</style>
