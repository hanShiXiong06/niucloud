<template>
  <HsxDialog
    v-model="dialogVisible"
    title="通知记录"
    :width="isMobile ? '94vw' : '860px'"
    top="6vh"
    :destroy-on-close="true"
    class="notice-log-dialog "
  >
    <div class="notice-log-dialog__body">
      <HsxNotice default-expanded
        title="这里记录每一次通知的发送参数、关联设备、跳转页面和成功失败结果，方便复盘微信模板、用户触达和业务操作链路。"
        type="info"
        :closable="false"
        show-icon
        class="mb-3"
      />

      <el-table v-loading="loading" :data="logs" height="420" border>
        <el-table-column prop="create_time_text" label="触发时间" width="170" />
        <el-table-column prop="scene_name" label="场景" min-width="140" />
        <el-table-column prop="notice_key" label="通知标识" min-width="150" />
        <el-table-column label="设备" width="110" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.device_count > 0" size="small" type="primary">{{ row.device_count }} 台</el-tag>
            <span v-else class="text-xs text-gray-400">整单</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">{{ row.status_name }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="send_time_text" label="发送时间" width="170" />
        <el-table-column prop="fail_reason" label="失败原因" min-width="180" show-overflow-tooltip />
        <el-table-column label="操作" width="90" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="showDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <template #footer>
      <el-button @click="dialogVisible = false">关闭</el-button>
    </template>
  </HsxDialog>
</template>

<script setup lang="ts">
import { HsxDialog, HsxNotice } from '@/addon/hsx_components/core'
import { computed, ref, watch } from "vue";
import { ElMessageBox } from "element-plus";
import { getOrderNoticeLogs } from "@/addon/hsx_recycle/api/recycle_order";

const props = defineProps<{
  visible: boolean;
  orderId: number | string;
  isMobile?: boolean;
}>();

const emit = defineEmits(["update:visible"]);

const dialogVisible = ref(props.visible);
const loading = ref(false);
const logs = ref<any[]>([]);

const isMobile = computed(() => Boolean(props.isMobile));

const getStatusType = (status: number) => {
  if (Number(status) === 1) return "success";
  if (Number(status) === 2) return "danger";
  if (Number(status) === 3) return "info";
  return "warning";
};

const loadLogs = async () => {
  if (!props.orderId) {
    logs.value = [];
    return;
  }

  loading.value = true;
  try {
    const res = await getOrderNoticeLogs(Number(props.orderId));
    logs.value = res.code === 1 && Array.isArray(res.data) ? res.data : [];
  } finally {
    loading.value = false;
  }
};

const showDetail = async (row: any) => {
  const detail = {
    id: row.id,
    order_no: row.order_no,
    member_id: row.member_id,
    device_ids: row.device_ids,
    target_page: row.target_page,
    request_data: row.request_data,
    response_data: row.response_data,
    fail_reason: row.fail_reason,
    status_name: row.status_name,
  };

  await ElMessageBox.alert(`<pre class="notice-log-pre">${escapeHtml(JSON.stringify(detail, null, 2))}</pre>`, "通知详情", {
    dangerouslyUseHTMLString: true,
    confirmButtonText: "关闭",
    customClass: "notice-log-detail-box",
  });
};

const escapeHtml = (value: string) => {
  return value.replace(/[&<>"']/g, (char) => {
    const map: Record<string, string> = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };
    return map[char] || char;
  });
};

watch(() => props.visible, async (value) => {
  dialogVisible.value = value;
  if (value) {
    await loadLogs();
  }
});

watch(dialogVisible, (value) => {
  emit("update:visible", value);
});
</script>

<style scoped>
.notice-log-dialog__body {
  min-height: 460px;
}
</style>

<style>
.notice-log-pre {
  max-height: 60vh;
  overflow: auto;
  margin: 0;
  white-space: pre-wrap;
  word-break: break-all;
  font-size: 12px;
  line-height: 1.6;
}
</style>
