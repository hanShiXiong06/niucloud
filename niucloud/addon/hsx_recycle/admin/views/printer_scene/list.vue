<template>
  <div class="print-scene-page">
    <el-card class="print-scene-card" shadow="never" v-loading="loading">
      <template #header>
        <div class="page-header">
          <div>
            <div class="page-title">打印场景</div>
            <div class="page-subtitle">控制每个业务节点是否自动打印、打印几张、使用哪个模板和打印机。</div>
          </div>
          <el-button type="primary" @click="fetchAll">刷新</el-button>
        </div>
      </template>

      <div class="workflow-band">
        <div><strong>1</strong><span>在模板列表设计标签内容</span></div>
        <div><strong>2</strong><span>在本页选择业务场景、模板和打印机</span></div>
        <div><strong>3</strong><span>开启自动打印或保留手动确认</span></div>
        <div><strong>4</strong><span>通过日志追踪每一次打印结果</span></div>
      </div>

      <div class="scene-grid">
        <section v-for="scene in sceneList" :key="scene.scene_key" class="scene-card">
          <div class="scene-card__head">
            <div>
              <div class="scene-title">{{ scene.scene_name }}</div>
              <div class="scene-trigger">{{ scene.trigger_name }}</div>
            </div>
            <el-switch
              v-model="scene.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleSceneStatus(scene)"
            />
          </div>

          <div class="scene-desc">{{ scene.description }}</div>

          <div class="scene-kpis">
            <div>
              <span>执行方式</span>
              <strong>{{ scene.auto_print_name }}</strong>
            </div>
            <div>
              <span>模板</span>
              <strong>{{ scene.template_name }}</strong>
            </div>
            <div>
              <span>打印机</span>
              <strong>{{ scene.printer_name }}</strong>
            </div>
            <div>
              <span>份数</span>
              <strong>{{ scene.copies }} 份</strong>
            </div>
            <div>
              <span>重复规则</span>
              <strong>{{ scene.idempotency_scope_name || '-' }}</strong>
            </div>
          </div>

          <div class="scene-actions">
            <el-button type="primary" plain @click="openSceneDialog(scene)">配置场景</el-button>
            <el-button plain @click="openLogDrawer(scene)">查看日志</el-button>
          </div>
        </section>
      </div>
    </el-card>

    <el-dialog v-model="sceneDialogVisible" title="配置打印场景" width="min(620px, calc(100vw - 32px))" destroy-on-close>
      <el-form :model="sceneForm" label-width="116px" class="scene-form">
        <el-form-item label="业务场景">
          <div class="readonly-block">
            <strong>{{ currentScene?.scene_name }}</strong>
            <span>{{ currentScene?.trigger_name }}</span>
          </div>
        </el-form-item>
        <el-form-item label="启用场景">
          <el-switch v-model="sceneForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="自动打印">
          <el-switch
            v-model="sceneForm.auto_print"
            :active-value="1"
            :inactive-value="0"
            :disabled="currentScene?.scene_key === 'manual_device_label'"
          />
          <div class="form-tip">
            手动场景需要用户确认后打印；自动场景会在业务动作完成后按配置发送到打印机。
          </div>
        </el-form-item>
        <el-form-item label="打印模板">
          <el-select v-model="sceneForm.template_id" clearable filterable placeholder="不选则使用该类型默认模板">
            <el-option label="使用默认模板" :value="0" />
            <el-option
              v-for="template in filteredTemplates"
              :key="template.template_id"
              :label="template.template_name"
              :value="template.template_id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="目标打印机">
          <el-select v-model="sceneForm.printer_id" clearable filterable placeholder="不选则使用模板绑定或账号默认打印机">
            <el-option label="模板绑定或账号默认打印机" :value="0" />
            <el-option
              v-for="printer in printerList"
              :key="printer.printer_id"
              :label="`${printer.printer_name}（${printer.sn || '无编号'}）`"
              :value="printer.printer_id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="打印份数">
          <el-input-number v-model="sceneForm.copies" :min="1" :max="20" controls-position="right" />
          <div class="form-tip">自动打印建议控制在 1-3 份，避免业务动作触发时重复出纸。</div>
        </el-form-item>
        <el-form-item label="重复规则">
          <el-select v-model="sceneForm.idempotency_scope" :disabled="currentScene?.scene_key === 'manual_device_label'">
            <el-option label="不限制重复打印" value="none" />
            <el-option label="同一业务同一场景只自动打印一次" value="site_scene_biz" />
            <el-option label="同一设备同一场景只自动打印一次" value="site_scene_device" />
            <el-option label="同一订单同一场景只自动打印一次" value="site_scene_order" />
          </el-select>
          <div class="form-tip">只限制自动打印；后台手动补打不受影响。</div>
        </el-form-item>
        <el-form-item label="失败重试">
          <div class="retry-row">
            <el-switch v-model="sceneForm.retry_enabled" :active-value="1" :inactive-value="0" />
            <el-input-number v-model="sceneForm.max_attempts" :min="1" :max="10" controls-position="right" :disabled="sceneForm.retry_enabled !== 1" />
          </div>
          <div class="form-tip">当前版本先记录任务重试配置，后续接入异步队列后可自动消费失败任务。</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="sceneDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveScene">保存配置</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="logDrawerVisible" title="打印日志" size="760px">
      <div class="log-toolbar">
        <el-select v-model="logSearch.status" clearable placeholder="全部状态" style="width: 140px" @change="fetchLogs">
          <el-option label="成功" :value="1" />
          <el-option label="失败/跳过" :value="0" />
        </el-select>
        <el-input
          v-model="logSearch.keyword"
          clearable
          placeholder="搜索模板、打印机、结果"
          style="width: 260px"
          @keyup.enter="fetchLogs"
          @clear="fetchLogs"
        />
        <el-button @click="fetchLogs">查询</el-button>
      </div>
      <el-table :data="logList" v-loading="logLoading" border>
        <el-table-column prop="create_time" label="时间" width="160" />
        <el-table-column prop="template_name" label="模板" min-width="140" show-overflow-tooltip />
        <el-table-column prop="printer_name" label="打印机" min-width="140" show-overflow-tooltip />
        <el-table-column prop="copies" label="份数" width="70" />
        <el-table-column prop="status" label="结果" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="message" label="说明" min-width="180" show-overflow-tooltip />
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="logPage.page"
          v-model:page-size="logPage.limit"
          :page-sizes="[10, 20, 50]"
          :total="logPage.total"
          layout="total, sizes, prev, pager, next"
          @size-change="fetchLogs"
          @current-change="fetchLogs"
        />
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
  getPrinterList,
  getPrintLogList,
  getPrintSceneList,
  modifyPrintSceneStatus,
  updatePrintScene
} from '@/addon/hsx_recycle/api/printer';
import { getTemplateList } from '@/addon/hsx_recycle/api/printer_template';

const loading = ref(false);
const saving = ref(false);
const logLoading = ref(false);
const sceneDialogVisible = ref(false);
const logDrawerVisible = ref(false);
const sceneList = ref([]);
const templateList = ref([]);
const printerList = ref([]);
const logList = ref([]);
const currentScene = ref(null);

const sceneForm = reactive({
  scene_key: '',
  status: 1,
  auto_print: 0,
  template_id: 0,
  printer_id: 0,
  copies: 1,
  idempotency_scope: 'site_scene_biz',
  retry_enabled: 1,
  max_attempts: 3
});

const logSearch = reactive({
  scene_key: '',
  status: '',
  keyword: ''
});

const logPage = reactive({
  page: 1,
  limit: 10,
  total: 0
});

const filteredTemplates = computed(() => {
  const type = currentScene.value?.template_type || '';
  return templateList.value.filter((item) => !type || item.template_type === type);
});

const fetchScenes = async () => {
  const res = await getPrintSceneList();
  if (res.code === 1) sceneList.value = res.data || [];
};

const fetchTemplates = async () => {
  const res = await getTemplateList({ page: 1, limit: 100, status: 1 });
  if (res.code === 1) templateList.value = res.data?.data || [];
};

const fetchPrinters = async () => {
  const res = await getPrinterList({ page: 1, limit: 100 });
  if (res.code === 1) printerList.value = res.data?.data || [];
};

const fetchAll = async () => {
  loading.value = true;
  try {
    await Promise.all([fetchScenes(), fetchTemplates(), fetchPrinters()]);
  } finally {
    loading.value = false;
  }
};

const openSceneDialog = (scene) => {
  currentScene.value = scene;
  sceneForm.scene_key = scene.scene_key;
  sceneForm.status = Number(scene.status || 0);
  sceneForm.auto_print = scene.scene_key === 'manual_device_label' ? 0 : Number(scene.auto_print || 0);
  sceneForm.template_id = Number(scene.template_id || 0);
  sceneForm.printer_id = Number(scene.printer_id || 0);
  sceneForm.copies = Number(scene.copies || 1);
  sceneForm.idempotency_scope = scene.scene_key === 'manual_device_label' ? 'none' : (scene.idempotency_scope || 'site_scene_biz');
  sceneForm.retry_enabled = Number(scene.retry_enabled ?? 1);
  sceneForm.max_attempts = Number(scene.max_attempts || 3);
  sceneDialogVisible.value = true;
};

const saveScene = async () => {
  if (!sceneForm.scene_key) return;
  saving.value = true;
  try {
    await updatePrintScene(sceneForm.scene_key, { ...sceneForm });
    sceneDialogVisible.value = false;
    await fetchScenes();
  } finally {
    saving.value = false;
  }
};

const handleSceneStatus = async (scene) => {
  try {
    await modifyPrintSceneStatus(scene.scene_key, scene.status);
    await fetchScenes();
  } catch (error) {
    scene.status = scene.status ? 0 : 1;
  }
};

const openLogDrawer = async (scene) => {
  logSearch.scene_key = scene.scene_key;
  logSearch.status = '';
  logSearch.keyword = '';
  logPage.page = 1;
  logDrawerVisible.value = true;
  await fetchLogs();
};

const fetchLogs = async () => {
  if (!logSearch.scene_key) return;
  logLoading.value = true;
  try {
    const res = await getPrintLogList({
      ...logSearch,
      page: logPage.page,
      limit: logPage.limit
    });
    if (res.code === 1) {
      logList.value = res.data?.data || [];
      logPage.total = res.data?.total || 0;
    }
  } finally {
    logLoading.value = false;
  }
};

onMounted(() => {
  fetchAll();
});
</script>

<style lang="scss" scoped>
.print-scene-page {
  padding: 16px;
}

.print-scene-card {
  border: none;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.page-title {
  color: #303133;
  font-size: 18px;
  font-weight: 600;
}

.page-subtitle {
  margin-top: 4px;
  color: #909399;
  font-size: 13px;
}

.scene-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 16px;
}

.workflow-band {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.workflow-band div {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
  align-items: center;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fafafa;
}

.workflow-band strong {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ecf5ff;
  color: #409eff;
  font-size: 13px;
}

.workflow-band span {
  min-width: 0;
  color: #606266;
  line-height: 1.5;
}

.scene-card {
  padding: 16px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
}

.scene-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.scene-title {
  color: #303133;
  font-size: 16px;
  font-weight: 600;
}

.scene-trigger {
  margin-top: 4px;
  color: #909399;
  font-size: 13px;
}

.scene-desc {
  margin-top: 12px;
  color: #606266;
  line-height: 1.6;
}

.scene-kpis {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;

  div {
    min-width: 0;
    padding: 10px;
    border-radius: 6px;
    background: #f7f8fa;
  }

  span {
    display: block;
    color: #909399;
    font-size: 12px;
  }

  strong {
    display: block;
    margin-top: 4px;
    color: #303133;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.scene-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 14px;
}

.readonly-block {
  display: grid;
  gap: 4px;
  line-height: 1.5;

  strong {
    color: #303133;
  }

  span {
    color: #909399;
  }
}

.scene-form {
  :deep(.el-select) {
    width: 100%;
  }
}

.retry-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.form-tip {
  width: 100%;
  margin-top: 6px;
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
}

.log-toolbar {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 768px) {
  .workflow-band {
    grid-template-columns: 1fr;
  }

  .scene-grid {
    grid-template-columns: 1fr;
  }

  .scene-kpis {
    grid-template-columns: 1fr;
  }

  .log-toolbar {
    flex-wrap: wrap;
  }
}
</style>
