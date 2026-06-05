<template>
  <div class="print-scene-page">
    <el-card class="print-scene-card" shadow="never" v-loading="loading">
      <template #header>
        <div class="page-header">
          <div>
            <div class="page-title">打印场景</div>
            <div class="page-subtitle">控制每个打印内容的手动按钮、自动触发节点、模板和打印机。</div>
          </div>
          <div class="page-actions">
            <el-button @click="fetchAll">刷新</el-button>
            <el-button type="primary" @click="openAddSceneDialog">新增场景</el-button>
          </div>
        </div>
      </template>

      <div class="workflow-band">
        <div><strong>1</strong><span>在模板列表设计标签内容</span></div>
        <div><strong>2</strong><span>在本页选择业务场景、模板和打印机</span></div>
        <div><strong>3</strong><span>按需同时开启手动按钮和自动触发</span></div>
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
            <div class="scene-kpis__wide">
              <span>展示规则</span>
              <strong>{{ scene.visibility_summary || '-' }}</strong>
            </div>
          </div>

          <div class="scene-actions">
            <el-button type="primary" plain @click="openSceneDialog(scene)">配置场景</el-button>
            <el-button plain @click="openLogDrawer(scene)">查看日志</el-button>
            <el-button v-if="!scene.is_builtin" type="danger" plain @click="deleteScene(scene)">删除</el-button>
          </div>
        </section>
      </div>
    </el-card>

    <el-drawer v-model="sceneDialogVisible" :title="isSceneCreateMode ? '新增打印场景' : '配置打印场景'" size="min(720px, 100vw)" destroy-on-close>
      <el-form :model="sceneForm" label-width="116px" class="scene-form">
        <div class="form-section">
          <div class="form-section__title">场景信息</div>
          <el-form-item label="场景名称">
            <el-input
              v-if="isSceneCreateMode || !currentScene?.is_builtin"
              v-model="sceneForm.scene_name"
              maxlength="30"
              show-word-limit
              placeholder="例如：回收凭证打印"
            />
            <div class="readonly-block">
              <template v-if="!isSceneCreateMode && currentScene?.is_builtin">
                <strong>{{ currentScene?.scene_name }}</strong>
                <span>{{ currentScene?.description }}</span>
              </template>
              <span v-else>{{ currentScene?.description || '自定义打印场景。可配置手动按钮和自动触发节点。' }}</span>
            </div>
          </el-form-item>
          <el-form-item label="业务类型">
            <el-select v-model="sceneForm.biz_type" :disabled="!isSceneCreateMode && currentScene?.is_builtin">
              <el-option
                v-for="item in globalOptions.biz_type_options"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="可用变量">
            <div class="variable-list">
              <el-tag v-for="item in variableOptions" :key="item.key" size="small" effect="plain">
                {{ item.label }}：{{ formatVariableToken(item.key) }}
              </el-tag>
            </div>
            <div class="form-tip">模板内容使用这些变量，打印时由后端按业务类型查询并注入真实数据。</div>
          </el-form-item>
          <el-form-item label="模板类型">
            <el-select v-model="sceneForm.template_type" :disabled="!isSceneCreateMode && currentScene?.is_builtin">
              <el-option
                v-for="item in globalOptions.template_type_options"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="启用场景">
            <el-switch v-model="sceneForm.status" :active-value="1" :inactive-value="0" />
          </el-form-item>
        </div>

        <div class="form-section">
          <div class="form-section__title">手动按钮</div>
          <el-form-item label="显示按钮">
            <el-switch v-model="sceneForm.button.enabled" :active-value="1" :inactive-value="0" />
            <div class="form-tip">开启后，满足展示状态的业务记录会在对应操作区出现打印按钮。</div>
          </el-form-item>
          <el-form-item label="按钮名称">
            <el-input v-model="sceneForm.button.text" maxlength="20" show-word-limit placeholder="例如：打印设备标签" />
          </el-form-item>
          <el-form-item label="按钮位置">
            <el-select v-model="sceneForm.button.position">
              <el-option
                v-for="position in buttonPositionOptions"
                :key="position.value"
                :label="position.label"
                :value="position.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="展示状态">
            <el-checkbox-group v-model="sceneForm.button.visible_device_status">
              <el-checkbox-button v-for="status in statusOptions" :key="status.value" :label="status.value">
                {{ status.label }}
              </el-checkbox-button>
            </el-checkbox-group>
            <div class="form-tip">只有当前业务处于选中状态时，列表中才会显示这个打印按钮。</div>
          </el-form-item>
          <el-form-item label="打印确认">
            <el-switch v-model="sceneForm.button.confirm_required" :active-value="1" :inactive-value="0" />
            <div class="form-tip">开启后，点击按钮会先展示打印计划，确认后再发送到打印机。</div>
          </el-form-item>
        </div>

        <div class="form-section">
          <div class="form-section__title">自动触发</div>
          <el-form-item label="自动打印">
            <el-switch v-model="sceneForm.auto_print" :active-value="1" :inactive-value="0" />
            <div class="form-tip">开启后，系统会在选中的触发节点发生时自动发送打印任务。</div>
          </el-form-item>
          <el-form-item label="触发节点">
            <el-select v-model="sceneForm.trigger.key" :disabled="sceneForm.auto_print !== 1">
              <el-option
                v-for="trigger in triggerOptions"
                :key="trigger.key"
                :label="trigger.name"
                :value="trigger.key"
              />
            </el-select>
            <div class="form-tip">{{ currentTriggerDescription }}</div>
          </el-form-item>
          <el-form-item label="重复规则">
            <el-select v-model="sceneForm.idempotency_scope" :disabled="sceneForm.auto_print !== 1">
              <el-option
                v-for="scope in idempotencyScopeOptions"
                :key="scope.value"
                :label="scope.label"
                :value="scope.value"
              />
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
        </div>

        <div class="form-section">
          <div class="form-section__title">打印资源</div>
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
        </div>

        <div class="rule-preview">
          {{ rulePreviewText }}
        </div>
      </el-form>
      <template #footer>
        <el-button @click="sceneDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveScene">{{ isSceneCreateMode ? '创建场景' : '保存配置' }}</el-button>
      </template>
    </el-drawer>

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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  addPrintScene,
  deletePrintScene,
  getPrinterList,
  getPrintLogList,
  getPrintSceneList,
  getPrintSceneOptions,
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
const isSceneCreateMode = ref(false);
const globalOptions = reactive({
  biz_type_options: [],
  template_type_options: [],
  trigger_options: [],
  button_position_options: [],
  device_status_options: [],
  status_options: {},
  print_variable_options: {},
  idempotency_scope_options: []
});

const sceneForm = reactive({
  scene_key: '',
  scene_name: '',
  biz_type: 'device',
  template_type: 'device_label',
  status: 1,
  auto_print: 0,
  template_id: 0,
  printer_id: 0,
  copies: 1,
  idempotency_scope: 'site_scene_biz',
  retry_enabled: 1,
  max_attempts: 3,
  trigger: {
    key: '',
    name: ''
  },
  button: {
    enabled: 1,
    text: '打印设备标签',
    position: 'device_actions',
    visible_device_status: [2, 3, 4, 5],
    confirm_required: 1
  }
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
  const type = sceneForm.template_type || currentScene.value?.template_type || '';
  return templateList.value.filter((item) => !type || item.template_type === type);
});

const buttonPositionOptions = computed(() => currentScene.value?.button_position_options?.length
  ? currentScene.value.button_position_options
  : (globalOptions.button_position_options.length ? globalOptions.button_position_options : [{ value: 'device_actions', label: '设备列表操作区', description: '' }])
);

const deviceStatusOptions = computed(() => currentScene.value?.device_status_options?.length
  ? currentScene.value.device_status_options
  : globalOptions.device_status_options
);

const statusOptions = computed(() => {
  const all = globalOptions.status_options || {};
  const bizType = getBizTypeByButtonPosition(sceneForm.button.position) || sceneForm.biz_type || 'device';
  return all[bizType] || deviceStatusOptions.value || [];
});

const variableOptions = computed(() => {
  const all = globalOptions.print_variable_options || {};
  return all[sceneForm.biz_type] || [];
});

const formatVariableToken = (key) => `{{${key}}}`;

const idempotencyScopeOptions = computed(() => currentScene.value?.idempotency_scope_options?.length
  ? currentScene.value.idempotency_scope_options
  : globalOptions.idempotency_scope_options
);

const triggerOptions = computed(() => {
  const bizType = sceneForm.biz_type || 'device';
  const sceneOptions = currentScene.value?.trigger_options?.length
    ? currentScene.value.trigger_options.filter((item) => !bizType || item.biz_type === bizType)
    : [];
  const globalOptionsByBiz = globalOptions.trigger_options.filter((item) => !bizType || item.biz_type === bizType);
  const merged = [...sceneOptions, ...globalOptionsByBiz];
  return merged.filter((item, index, list) => list.findIndex((option) => option.key === item.key) === index);
});

const currentTriggerDescription = computed(() => {
  const trigger = triggerOptions.value.find((item) => item.key === sceneForm.trigger.key);
  return trigger?.description || (trigger?.name ? `当前自动触发节点：${trigger.name}` : '当前场景暂未配置自动触发节点。');
});

const rulePreviewText = computed(() => {
  const sceneName = currentScene.value?.scene_name || '当前场景';
  const templateName = filteredTemplates.value.find((item) => Number(item.template_id) === Number(sceneForm.template_id))?.template_name || '默认模板';
  const printerName = printerList.value.find((item) => Number(item.printer_id) === Number(sceneForm.printer_id))?.printer_name || '模板绑定或账号默认打印机';
  const statusNames = statusOptions.value
    .filter((item) => sceneForm.button.visible_device_status.includes(item.value))
    .map((item) => item.label);
  const rules = [];
  if (sceneForm.button.enabled === 1) {
    rules.push(`按钮【${sceneForm.button.text || sceneName}】会在当前业务处于【${statusNames.length ? statusNames.join('、') : '未选择状态'}】时显示`);
  }
  if (sceneForm.auto_print === 1) {
    const triggerName = triggerOptions.value.find((item) => item.key === sceneForm.trigger.key)?.name || '未选择触发节点';
    rules.push(`自动打印会在【${triggerName}】触发`);
  }
  if (!rules.length) {
    rules.push('当前没有开启手动按钮或自动触发，场景启用后也不会产生打印入口');
  }
  return `${rules.join('；')}。使用【${templateName}】，发送到【${printerName}】，打印 ${sceneForm.copies || 1} 份。`;
});

const fetchScenes = async () => {
  const res = await getPrintSceneList();
  if (res.code === 1) sceneList.value = res.data || [];
};

const fetchOptions = async () => {
  const res = await getPrintSceneOptions();
  if (res.code !== 1) return;
  const data = res.data || {};
  globalOptions.biz_type_options = data.biz_type_options || [];
  globalOptions.template_type_options = data.template_type_options || [];
  globalOptions.trigger_options = data.trigger_options || [];
  globalOptions.button_position_options = data.button_position_options || [];
  globalOptions.device_status_options = data.device_status_options || [];
  globalOptions.status_options = data.status_options || {};
  globalOptions.print_variable_options = data.print_variable_options || {};
  globalOptions.idempotency_scope_options = data.idempotency_scope_options || [];
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
    await Promise.all([fetchOptions(), fetchScenes(), fetchTemplates(), fetchPrinters()]);
  } finally {
    loading.value = false;
  }
};

const getDefaultTemplateType = (bizType) => {
  const map = {
    device: 'device_label',
    order: 'order_receipt',
    return: 'return_label',
    consignment: 'consignment_receipt'
  };
  return map[bizType] || globalOptions.template_type_options[0]?.value || 'device_label';
};

const getDefaultButtonPosition = (bizType) => {
  const map = {
    device: 'device_actions',
    order: 'order_actions',
    return: 'return_order_actions',
    consignment: 'consignment_order_actions'
  };
  const target = map[bizType] || 'device_actions';
  return buttonPositionOptions.value.some((item) => item.value === target)
    ? target
    : buttonPositionOptions.value[0]?.value || 'device_actions';
};

const getBizTypeByButtonPosition = (position) => {
  const map = {
    device_actions: 'device',
    order_actions: 'order',
    return_order_actions: 'return',
    consignment_order_actions: 'consignment'
  };
  return map[position] || '';
};

const getFirstTriggerByBizType = (bizType) => {
  return (globalOptions.trigger_options || []).find((item) => item.biz_type === bizType) || {};
};

const syncVisibleStatuses = () => {
  const validStatuses = statusOptions.value.map((item) => Number(item.value));
  sceneForm.button.visible_device_status = sceneForm.button.visible_device_status
    .map((item) => Number(item))
    .filter((item) => validStatuses.includes(item));
  if (!sceneForm.button.visible_device_status.length) {
    sceneForm.button.visible_device_status = validStatuses.slice(0, 4);
  }
};

const syncSceneDefaultsByBizType = () => {
  const firstTrigger = triggerOptions.value[0] || {};
  if (!triggerOptions.value.some((item) => item.key === sceneForm.trigger.key)) {
    sceneForm.trigger.key = firstTrigger.key || '';
    sceneForm.trigger.name = firstTrigger.name || '';
  }
  if (!filteredTemplates.value.some((item) => Number(item.template_id) === Number(sceneForm.template_id))) {
    sceneForm.template_id = 0;
  }
  sceneForm.button.position = getDefaultButtonPosition(sceneForm.biz_type);
  syncVisibleStatuses();
};

const resetSceneForm = () => {
  sceneForm.scene_key = '';
  sceneForm.scene_name = '';
  sceneForm.biz_type = globalOptions.biz_type_options[0]?.value || 'device';
  const firstTrigger = getFirstTriggerByBizType(sceneForm.biz_type);
  sceneForm.template_type = getDefaultTemplateType(sceneForm.biz_type);
  sceneForm.status = 1;
  sceneForm.auto_print = 0;
  sceneForm.template_id = 0;
  sceneForm.printer_id = 0;
  sceneForm.copies = 1;
  sceneForm.idempotency_scope = sceneForm.biz_type === 'device' ? 'site_scene_device' : 'site_scene_biz';
  sceneForm.retry_enabled = 1;
  sceneForm.max_attempts = 3;
  sceneForm.trigger.key = firstTrigger.key || '';
  sceneForm.trigger.name = firstTrigger.name || '';
  sceneForm.button.enabled = 1;
  sceneForm.button.text = sceneForm.biz_type === 'consignment' ? '打印代卖凭证' : '打印';
  sceneForm.button.position = getDefaultButtonPosition(sceneForm.biz_type);
  sceneForm.button.visible_device_status = statusOptions.value.map((item) => Number(item.value)).slice(0, 4);
  sceneForm.button.confirm_required = 1;
};

const openAddSceneDialog = () => {
  currentScene.value = null;
  isSceneCreateMode.value = true;
  resetSceneForm();
  sceneDialogVisible.value = true;
};

const openSceneDialog = (scene) => {
  currentScene.value = scene;
  isSceneCreateMode.value = false;
  const button = scene.button_config || {};
  const trigger = scene.condition_config?.trigger || {};
  sceneForm.scene_key = scene.scene_key;
  sceneForm.scene_name = scene.scene_name || '';
  sceneForm.biz_type = scene.biz_type || 'device';
  sceneForm.template_type = scene.template_type || 'device_label';
  sceneForm.status = Number(scene.status || 0);
  sceneForm.auto_print = Number(scene.auto_print || 0);
  sceneForm.template_id = Number(scene.template_id || 0);
  sceneForm.printer_id = Number(scene.printer_id || 0);
  sceneForm.copies = Number(scene.copies || 1);
  sceneForm.idempotency_scope = scene.idempotency_scope || 'site_scene_biz';
  sceneForm.retry_enabled = Number(scene.retry_enabled ?? 1);
  sceneForm.max_attempts = Number(scene.max_attempts || 3);
  sceneForm.trigger.key = trigger.key || scene.trigger_key || triggerOptions.value[0]?.key || '';
  sceneForm.trigger.name = trigger.name || scene.trigger_name || triggerOptions.value[0]?.name || '';
  sceneForm.button.enabled = Number(button.enabled ?? 1);
  sceneForm.button.text = button.text || scene.scene_name || '打印';
  sceneForm.button.position = button.position || getDefaultButtonPosition(sceneForm.biz_type);
  sceneForm.button.visible_device_status = Array.isArray(button.visible_device_status) && button.visible_device_status.length
    ? button.visible_device_status.map((item) => Number(item))
    : statusOptions.value.map((item) => Number(item.value)).slice(0, 4);
  syncVisibleStatuses();
  sceneForm.button.confirm_required = Number(button.confirm_required ?? 1);
  sceneDialogVisible.value = true;
};

const saveScene = async () => {
  if (!isSceneCreateMode.value && !sceneForm.scene_key) return;
  if (!sceneForm.scene_name && isSceneCreateMode.value) {
    ElMessage.warning('请输入场景名称');
    return;
  }
  saving.value = true;
  try {
    const payload = {
      ...sceneForm,
      auto_print: sceneForm.auto_print,
      idempotency_scope: sceneForm.auto_print === 1 ? sceneForm.idempotency_scope : 'none',
      button: { ...sceneForm.button },
      trigger: { ...sceneForm.trigger }
    };
    const res = isSceneCreateMode.value
      ? await addPrintScene(payload)
      : await updatePrintScene(sceneForm.scene_key, payload);
    if (res.code !== 1) {
      ElMessage.error(res.msg || '保存失败');
      return;
    }
    sceneDialogVisible.value = false;
    isSceneCreateMode.value = false;
    await fetchScenes();
  } finally {
    saving.value = false;
  }
};

const deleteScene = async (scene) => {
  if (scene.is_builtin) {
    ElMessage.warning('系统内置场景不能删除，可停用或修改配置');
    return;
  }
  try {
    await ElMessageBox.confirm(`确定删除【${scene.scene_name}】吗？删除后该按钮和自动触发都会失效。`, '删除打印场景', {
      type: 'warning',
      confirmButtonText: '确定删除',
      cancelButtonText: '取消'
    });
    const res = await deletePrintScene(scene.scene_key);
    if (res.code !== 1) {
      ElMessage.error(res.msg || '删除失败');
      return;
    }
    await fetchScenes();
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      console.error('删除打印场景失败', error);
    }
  }
};

const handleSceneStatus = async (scene) => {
  const previousStatus = scene.status ? 0 : 1;
  try {
    const res = await modifyPrintSceneStatus(scene.scene_key, scene.status);
    if (res.code !== 1) {
      scene.status = previousStatus;
      ElMessage.error(res.msg || '状态修改失败');
      return;
    }
    await fetchScenes();
  } catch (error) {
    scene.status = previousStatus;
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

watch(() => sceneForm.biz_type, (value, oldValue) => {
  if (!sceneDialogVisible.value || value === oldValue) return;
  sceneForm.template_type = getDefaultTemplateType(value);
  syncSceneDefaultsByBizType();
});

watch(() => sceneForm.button.position, (value, oldValue) => {
  if (!sceneDialogVisible.value || value === oldValue) return;
  const bizType = getBizTypeByButtonPosition(value);
  if (bizType && bizType !== sceneForm.biz_type) {
    sceneForm.biz_type = bizType;
    return;
  }
  syncVisibleStatuses();
});

watch(() => sceneForm.template_type, () => {
  if (!sceneDialogVisible.value) return;
  if (!filteredTemplates.value.some((item) => Number(item.template_id) === Number(sceneForm.template_id))) {
    sceneForm.template_id = 0;
  }
});
</script>

<style lang="scss" scoped>


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

.scene-kpis__wide {
  grid-column: 1 / -1;
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

  :deep(.el-checkbox-group) {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  :deep(.el-checkbox-button__inner) {
    border-left: 1px solid var(--el-border-color);
    border-radius: 6px;
  }
}

.form-section {
  margin-bottom: 16px;
  padding: 14px 14px 4px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
}

.form-section__title {
  margin-bottom: 12px;
  color: #303133;
  font-size: 14px;
  font-weight: 700;
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

.variable-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-height: 92px;
  overflow-y: auto;
  padding: 8px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  background: #fafafa;
}

.rule-preview {
  padding: 12px 14px;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 13px;
  line-height: 1.7;
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
