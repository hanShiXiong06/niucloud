<template>
    <CooperationWorkbench
        v-if="task"
        ref="workbench"
        :task="task"
        :initial-id="id"
        :initial-store-id="storeId"
        :initial-payment-id="paymentId"
        :initial-business-type="businessType"
        :initial-promotion-tab="promotionTab"
        :initial-inviter="inviter"
    />
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { onHide, onLoad, onShow } from '@dcloudio/uni-app'
import CooperationWorkbench from '../../components/cooperation/CooperationWorkbench.vue'
import { backCooperationTask } from '../../utils/cooperation-navigation'
import { entityPageUrl } from '../../utils/entity-membership'
const task = ref(''), id = ref(0), storeId = ref(0), inviter = ref(0), promotionTab = ref('overview'), workbench = ref<any>(null)
const tasks = new Set(['publish', 'detail', 'select', 'stores', 'commission', 'connection', 'inventory', 'promotion', 'profile-edit', 'members'])
const businessType = ref(''), paymentId = ref(0)
onLoad(options => { const value = Number(options?.payment_order_id || 0); paymentId.value = Number.isSafeInteger(value) && value > 0 ? value : 0 })
onLoad(options => { const name = String(options?.task || ''); if (!tasks.has(name)) { uni.showToast({ title: '页面入口无效，请从首页重新进入', icon: 'none' }); backCooperationTask(); return }; if (name === 'members') { uni.redirectTo({ url: entityPageUrl }); return }; task.value = name; id.value = Number(options?.collaboration_id || 0); storeId.value = Number(options?.store_id || 0); promotionTab.value = String(options?.tab || 'overview'); inviter.value = Number(options?.mid || 0); businessType.value = String(options?.business_type || '') })
onShow(() => workbench.value?.resume?.())
onHide(() => workbench.value?.pause?.())
</script>
