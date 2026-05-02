<template>
  <div class="main-container device-query-page">
    <el-card class="box-card !border-none" shadow="never">
      <div class="page-head">
        <div>
          <span class="text-page-title">设备查询配置</span>
          <div class="page-desc">配置用户能查什么、走哪个服务商、接口路径或服务ID，以及是否显示到质检弹窗。</div>
        </div>
        <div class="head-actions">
          <el-button :loading="loading" @click="loadData">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
          <el-button type="primary" @click="openServiceDialog()">
            <el-icon><Plus /></el-icon>
            新增查询项
          </el-button>
        </div>
      </div>

      <div class="flow-grid">
        <div class="flow-item">
          <div class="flow-index">1</div>
          <div>
            <strong>建查询项</strong>
            <span>定义苹果保修、安卓保修、激活锁等业务能力。</span>
          </div>
        </div>
        <div class="flow-item">
          <div class="flow-index">2</div>
          <div>
            <strong>配渠道</strong>
            <span>3023 用路径接口，爱查用服务ID接口。</span>
          </div>
        </div>
        <div class="flow-item">
          <div class="flow-index">3</div>
          <div>
            <strong>绑映射</strong>
            <span>把查询项绑定到具体路径、服务ID和参数名。</span>
          </div>
        </div>
      </div>

      <el-alert
        class="config-guide"
        type="info"
        :closable="false"
        show-icon
        title="配置顺序：先建渠道，再建查询项并绑定接口映射，最后打开“质检显示”。只有已启用且有映射的查询项才会出现在质检弹窗。"
      />

      <div class="workbench">
        <div class="workbench-head">
          <div>
            <div class="section-title">配置向导</div>
            <div class="section-desc">设备查询依赖关系是：渠道决定请求到哪里，查询项决定用户能点什么，接口映射决定这个按钮实际调用哪个第三方接口。</div>
          </div>
          <div class="workbench-actions">
            <el-button @click="create3023Example">创建 3023 示例</el-button>
            <el-button @click="createGkdtExample">创建爱查示例</el-button>
            <el-button :disabled="!configDiagnostics.invalid_mapping_indexes.length" type="warning" @click="cleanInvalidMappings">清理异常映射</el-button>
          </div>
        </div>

        <div class="status-grid">
          <div class="status-cell">
            <span>渠道</span>
            <strong>{{ configDiagnostics.enabled_channel_total }} / {{ configDiagnostics.channel_total }}</strong>
            <em>已启用 / 全部</em>
          </div>
          <div class="status-cell">
            <span>查询项</span>
            <strong>{{ configDiagnostics.service_total }}</strong>
            <em>业务按钮数量</em>
          </div>
          <div class="status-cell">
            <span>接口映射</span>
            <strong>{{ configDiagnostics.mapping_total }}</strong>
            <em>第三方接口绑定</em>
          </div>
          <div class="status-cell">
            <span>质检按钮</span>
            <strong>{{ configDiagnostics.visible_service_total }}</strong>
            <em>实际可显示</em>
          </div>
        </div>

        <div class="guide-layout">
          <div class="guide-block">
            <div class="guide-title">正确配置方式</div>
            <div class="guide-row">
              <span>3023</span>
              <p>渠道地址填 http://api.3023data.com，接口映射填 /apple/coverage 这类路径，鉴权位置 Header，字段名 key。</p>
            </div>
            <div class="guide-row">
              <span>爱查</span>
              <p>渠道地址填 https://api-srv.gkdt.com/inquiry/async，接口映射填 10101 这类服务 ID，服务ID字段 key。</p>
            </div>
            <div class="guide-row">
              <span>按钮显示</span>
              <p>查询项必须启用、打开“质检显示”，并且至少有一条启用的接口映射，才会出现在设备质检弹窗。</p>
            </div>
          </div>

          <div class="problem-block">
            <div class="guide-title">当前状态</div>
            <template v-if="configDiagnostics.problems.length">
              <div v-for="(item, index) in configDiagnostics.problems" :key="index" class="problem-item" :class="item.type">
                <div>
                  <strong>{{ item.title }}</strong>
                  <p>{{ item.desc }}</p>
                </div>
                <el-button v-if="item.action === '配渠道'" link type="primary" @click="activeTab = 'channels'">去配置</el-button>
                <el-button v-else-if="item.action === '补映射' || item.action === '改映射'" link type="primary" @click="activeTab = 'mappings'">去处理</el-button>
                <el-button v-else link type="primary" @click="activeTab = 'services'">去处理</el-button>
              </div>
            </template>
            <div v-else class="empty-state">
              当前配置关系完整，可以在查询项列表里点击“测试”，或者到订单质检弹窗里查看查询按钮。
            </div>
          </div>
        </div>
      </div>

      <el-tabs v-model="activeTab" class="config-tabs">
        <el-tab-pane label="查询项" name="services">
          <div class="section-toolbar">
            <div>
              <div class="section-title">用户可查询的业务项</div>
              <div class="section-desc">打开“质检显示”后，会出现在设备质检弹窗的联网查询区域。</div>
            </div>
            <el-button type="primary" @click="openServiceDialog()">新增查询项</el-button>
          </div>
          <el-alert
            class="mb-[14px]"
            type="warning"
            :closable="false"
            show-icon
            title="查询项只是业务按钮，必须至少有一条接口映射才能执行查询。表格中的“渠道/映射”为 0 的查询项不会显示到质检弹窗。"
          />
          <el-table :data="services" v-loading="loading" size="large" border>
            <el-table-column prop="name" label="名称" min-width="180" />
            <el-table-column prop="code" label="编码" min-width="170" />
            <el-table-column prop="category" label="分类" width="110" />
            <el-table-column prop="query_type" label="入参类型" width="100" />
            <el-table-column prop="result_handler" label="结果处理" width="120">
              <template #default="{ row }">{{ resultHandlerLabel(row.result_handler) }}</template>
            </el-table-column>
            <el-table-column prop="cost_price" label="成本" width="100">
              <template #default="{ row }">¥{{ row.cost_price }}</template>
            </el-table-column>
            <el-table-column label="渠道/映射" width="110">
              <template #default="{ row }">
                <el-tag :type="Number(row.mapping_count || 0) > 0 ? 'success' : 'warning'">
                  {{ row.channel_count || 0 }} / {{ row.mapping_count || 0 }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="启用" width="86">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'">{{ row.enabled ? '启用' : '停用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="质检显示" width="110">
              <template #default="{ row }">
                <el-switch v-model="row.show_in_check" :active-value="1" :inactive-value="0" @change="saveInlineService(row)" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="210" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openServiceDialog(row)">编辑</el-button>
                <el-button v-if="Number(row.mapping_count || 0) === 0" link type="success" @click="openMappingDialog({ service_code: row.code, enabled: 1 }, -1)">补映射</el-button>
                <el-button v-else link type="success" @click="testService(row)">测试</el-button>
                <el-button link type="warning" @click="toggleService(row)">{{ row.enabled ? '停用' : '启用' }}</el-button>
                <el-button link type="danger" @click="deleteService(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="渠道" name="channels">
          <div class="section-toolbar">
            <div>
              <div class="section-title">第三方查询渠道</div>
              <div class="section-desc">3023 选择“路径接口”，爱查助手选择“服务ID接口”。密钥会保存到系统配置中。</div>
            </div>
            <el-button type="primary" @click="openChannelDialog()">新增渠道</el-button>
          </div>
          <el-table :data="channels" v-loading="loading" size="large" border>
            <el-table-column prop="name" label="名称" min-width="150" />
            <el-table-column prop="key" label="渠道键" min-width="140" />
            <el-table-column label="接口类型" width="140">
              <template #default="{ row }">{{ providerLabel(row.provider) }}</template>
            </el-table-column>
            <el-table-column prop="base_url" label="请求地址" min-width="260" />
            <el-table-column prop="priority" label="优先级" width="90" />
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'">{{ row.enabled ? '启用' : '停用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="210" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openChannelDialog(row)">编辑</el-button>
                <el-button v-if="row.provider === 'path_query'" link type="success" @click="queryChannelBalance(row)">查余额</el-button>
                <el-button link type="danger" @click="deleteChannel(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="接口映射" name="mappings">
          <div class="section-toolbar">
            <div>
              <div class="section-title">查询项与第三方接口的绑定</div>
              <div class="section-desc">3023 填接口路径，例如 /apple/coverage；爱查填服务 ID，例如 10102。</div>
            </div>
            <el-button type="primary" @click="openMappingDialog()">新增映射</el-button>
          </div>
          <el-table :data="mappings" v-loading="loading" size="large" border>
            <el-table-column label="查询项" min-width="190">
              <template #default="{ row }">{{ serviceName(row.service_code) }}</template>
            </el-table-column>
            <el-table-column label="渠道" min-width="160">
              <template #default="{ row }">{{ channelName(row.channel_key) }}</template>
            </el-table-column>
            <el-table-column label="接口类型" width="100">
              <template #default="{ row }">{{ endpointTypeLabel(row.endpoint_type) }}</template>
            </el-table-column>
            <el-table-column prop="endpoint_value" label="路径 / 服务ID" min-width="220" />
            <el-table-column prop="query_param" label="参数名" width="100" />
            <el-table-column prop="cost_price" label="成本" width="90">
              <template #default="{ row }">¥{{ row.cost_price }}</template>
            </el-table-column>
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.enabled ? 'success' : 'info'">{{ row.enabled ? '启用' : '停用' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row, $index }">
                <el-button link type="primary" @click="openMappingDialog(row, $index)">编辑</el-button>
                <el-button link type="danger" @click="deleteMapping(row, $index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="基础配置" name="base">
          <el-form label-width="140px" class="base-form">
            <el-form-item label="启用设备查询">
              <el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <el-form-item label="启用缓存">
              <el-switch v-model="config.cache_enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <el-form-item label="默认缓存时长">
              <el-input-number v-model="config.default_cache_ttl" :min="0" :step="3600" />
              <span class="field-hint">秒</span>
            </el-form-item>
            <el-form-item label="默认渠道">
              <el-select v-model="config.default_channel_key" filterable class="w-[360px]">
                <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
            </el-form-item>
          </el-form>
          <div class="form-actions">
            <el-button type="primary" @click="saveBaseConfig">保存基础配置</el-button>
            <el-button @click="resetBaseConfig">重置</el-button>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog v-model="serviceDialogVisible" :title="serviceDialogTitle" width="980px">
      <div v-if="!serviceForm._editing" class="parse-panel">
        <div class="parse-head">
          <div>
            <div class="parse-title">接口文档解析</div>
            <div class="parse-desc">粘贴爱查助手 URL 或 3023 路径清单，系统会生成查询项和接口映射草稿。</div>
          </div>
          <div class="parse-actions">
            <el-button @click="serviceImportText = ''">清空</el-button>
            <el-button type="primary" @click="parseServiceImportText">一键解析</el-button>
          </div>
        </div>
        <el-input
          v-model="serviceImportText"
          type="textarea"
          :rows="5"
          resize="none"
          placeholder="示例：苹果保修查询 Apple Coverage Check&#10;地址：&#10;https://api-srv.gkdt.com/inquiry/async?key=10101&#10;或：苹果保修查询	/apple/coverage"
        />
        <div v-if="parsedServiceRows.length" class="parse-result">
          <div class="parse-result-head">
            <span>解析结果</span>
            <el-button type="primary" :loading="importingParsedServices" @click="importParsedServices">导入全部</el-button>
          </div>
          <el-table :data="parsedServiceRows" size="small" border>
            <el-table-column prop="name" label="查询项" min-width="170">
              <template #default="{ row }">
                <el-input v-model.trim="row.name" size="small" />
              </template>
            </el-table-column>
            <el-table-column prop="code" label="编码" min-width="170">
              <template #default="{ row }">
                <el-input v-model.trim="row.code" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="渠道" min-width="170">
              <template #default="{ row }">
                <el-select v-model="row.channel_key" size="small" filterable class="w-full">
                  <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
                <div v-if="row._guide" class="table-hint warning">{{ row._guide }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="endpoint_value" label="路径 / 服务ID" min-width="170">
              <template #default="{ row }">
                <el-input v-model.trim="row.endpoint_value" size="small" />
              </template>
            </el-table-column>
            <el-table-column prop="query_param" label="参数" width="90">
              <template #default="{ row }">
                <el-input v-model.trim="row.query_param" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="质检显示" width="90">
              <template #default="{ row }">
                <el-switch v-model="row.show_in_check" :active-value="1" :inactive-value="0" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="88" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="applyParsedServiceToForm(row)">填入表单</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <el-form :model="serviceForm" label-width="110px">
        <el-form-item label="编码">
          <el-input v-model.trim="serviceForm.code" placeholder="如 apple_coverage_capacity" :disabled="!!serviceForm._editing" />
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model.trim="serviceForm.name" placeholder="如 苹果保修查询（容量/颜色）" />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="分类">
              <el-input v-model.trim="serviceForm.category" placeholder="apple/android/imei" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="入参类型">
              <el-select v-model="serviceForm.query_type" class="w-full">
                <el-option label="IMEI" value="imei" />
                <el-option label="SN" value="sn" />
                <el-option label="条码" value="barcode" />
                <el-option label="手机号" value="phone" />
                <el-option label="IP" value="ip" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="成本">
              <el-input-number v-model="serviceForm.cost_price" :min="0" :step="0.01" class="w-full" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序">
              <el-input-number v-model="serviceForm.sort" :min="0" class="w-full" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="缓存秒数">
          <el-input-number v-model="serviceForm.cache_ttl" :min="0" :step="3600" />
        </el-form-item>
        <el-form-item label="结果处理">
          <el-select v-model="serviceForm.result_handler" class="w-full">
            <el-option v-for="item in resultHandlerOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="开关">
          <el-checkbox v-model="serviceForm.enabled" :true-label="1" :false-label="0">启用查询项</el-checkbox>
          <el-checkbox v-model="serviceForm.show_in_check" :true-label="1" :false-label="0">显示到质检弹窗</el-checkbox>
        </el-form-item>
        <div v-if="!serviceForm._editing" class="inline-mapping-panel">
          <div class="inline-mapping-head">
            <div>
              <div class="inline-mapping-title">接口映射</div>
              <div class="inline-mapping-desc">查询项必须绑定一个第三方接口才能执行查询。新增时建议一起创建映射。</div>
            </div>
            <el-switch v-model="serviceForm.create_mapping" :active-value="1" :inactive-value="0" />
          </div>
          <template v-if="serviceForm.create_mapping">
            <el-row :gutter="12">
              <el-col :span="12">
                <el-form-item label="渠道">
                  <el-select v-model="serviceForm.mapping_channel_key" filterable class="w-full">
                    <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
                  </el-select>
                  <div class="field-tip">{{ getMappingGuide({
                    channel_key: serviceForm.mapping_channel_key,
                    endpoint_type: serviceForm.mapping_endpoint_type,
                    endpoint_value: serviceForm.mapping_endpoint_value
                  }) }}</div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="接口类型">
                  <el-select v-model="serviceForm.mapping_endpoint_type" class="w-full">
                    <el-option v-for="item in endpointTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="12">
              <el-col :span="16">
                <el-form-item label="接口值">
                  <el-input v-model.trim="serviceForm.mapping_endpoint_value" placeholder="3023填 /apple/coverage；爱查填 10101" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="参数名">
                  <el-input v-model.trim="serviceForm.mapping_query_param" placeholder="sn / imei" />
                </el-form-item>
              </el-col>
            </el-row>
          </template>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="serviceDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveService">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="channelDialogVisible" :title="channelDialogTitle" width="760px">
      <el-form :model="channelForm" label-width="120px">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="渠道键">
              <el-input v-model.trim="channelForm.key" placeholder="如 3023_main" :disabled="!!channelForm._editing" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="名称">
              <el-input v-model.trim="channelForm.name" placeholder="如 3023主渠道" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="接口类型">
          <el-select v-model="channelForm.provider" class="w-full">
            <el-option v-for="item in providerOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="请求地址">
          <el-input v-model.trim="channelForm.base_url" placeholder="3023：http://api.3023data.com；爱查：https://api-srv.gkdt.com/inquiry/async" />
        </el-form-item>
        <el-alert
          class="dialog-alert"
          type="info"
          :closable="false"
          title="3023 渠道通常使用 Header 鉴权，字段名 key；爱查助手通常使用 Query 鉴权，服务ID字段 key，密钥字段按实际接口配置。"
        />
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="鉴权位置">
              <el-select v-model="channelForm.auth_type" class="w-full">
                <el-option label="Header" value="header" />
                <el-option label="Query" value="query" />
                <el-option label="不鉴权" value="none" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="鉴权字段">
              <el-input v-model.trim="channelForm.auth_key" placeholder="3023 为 key" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="密钥">
          <el-input v-model="channelForm.token" type="password" show-password placeholder="服务商分配的 key/token" />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="服务ID字段">
              <el-input v-model.trim="channelForm.service_id_key" placeholder="爱查为 key" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="优先级">
              <el-input-number v-model="channelForm.priority" :min="0" class="w-full" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="启用">
              <el-switch v-model="channelForm.enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="channelDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveChannel">保存渠道</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="mappingDialogVisible" :title="mappingDialogTitle" width="760px">
      <el-form :model="mappingForm" label-width="120px">
        <el-form-item label="查询项">
          <el-select v-model="mappingForm.service_code" filterable class="w-full">
            <el-option v-for="item in serviceOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="渠道">
          <el-select v-model="mappingForm.channel_key" filterable class="w-full">
            <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <div class="field-tip">{{ getMappingGuide(mappingForm) }}</div>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="接口类型">
              <el-select v-model="mappingForm.endpoint_type" class="w-full">
                <el-option v-for="item in endpointTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="参数名">
              <el-input v-model.trim="mappingForm.query_param" placeholder="imei 或 sn" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="接口值">
          <el-input v-model.trim="mappingForm.endpoint_value" placeholder="3023填 /apple/coverage；爱查填 10102" />
        </el-form-item>
        <el-alert
          class="dialog-alert"
          type="info"
          :closable="false"
          title="路径接口示例：/apple/coverage；服务ID接口示例：10101。参数名通常是 sn 或 imei，填错会导致第三方返回错误。"
        />
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="成本">
              <el-input-number v-model="mappingForm.cost_price" :min="0" :step="0.01" class="w-full" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="启用">
              <el-switch v-model="mappingForm.enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="查无切换">
              <el-switch v-model="mappingForm.switch_on_no_data" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="mappingDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveMapping">保存映射</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue'
import { Plus, Refresh } from '@element-plus/icons-vue'
import {
  endpointTypeOptions,
  providerOptions,
  resultHandlerOptions,
  useDeviceQueryConfig
} from './composables/useDeviceQueryConfig'

const {
  loading,
  activeTab,
  services,
  channels,
  mappings,
  config,
  serviceDialogVisible,
  channelDialogVisible,
  mappingDialogVisible,
  serviceForm,
  channelForm,
  mappingForm,
  serviceDialogTitle,
  channelDialogTitle,
  mappingDialogTitle,
  serviceOptions,
  channelOptions,
  configDiagnostics,
  serviceImportText,
  parsedServiceRows,
  importingParsedServices,
  loadData,
  create3023Example,
  createGkdtExample,
  cleanInvalidMappings,
  openServiceDialog,
  parseServiceImportText,
  applyParsedServiceToForm,
  importParsedServices,
  saveService,
  saveInlineService,
  toggleService,
  testService,
  deleteService,
  queryChannelBalance,
  openChannelDialog,
  saveChannel,
  deleteChannel,
  openMappingDialog,
  getMappingGuide,
  saveMapping,
  deleteMapping,
  saveBaseConfig,
  resetBaseConfig
} = useDeviceQueryConfig()

const providerLabel = (value: string) => providerOptions.find(item => item.value === value)?.label || value || '-'
const endpointTypeLabel = (value: string) => endpointTypeOptions.find(item => item.value === value)?.label || value || '-'
const resultHandlerLabel = (value: string) => resultHandlerOptions.find(item => item.value === value)?.label || value || '-'
const serviceName = (code: string) => services.value.find(item => item.code === code)?.name || code || '-'
const channelName = (key: string) => channels.value.find(item => item.key === key)?.name || key || '-'

onMounted(loadData)
</script>

<style lang="scss" scoped>
.device-query-page {
  .page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .page-desc,
  .section-desc {
    margin-top: 6px;
    color: #667085;
    font-size: 13px;
    line-height: 1.5;
  }

  .head-actions,
  .form-actions {
    display: flex;
    gap: 8px;
  }

  .flow-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
  }

  .flow-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    border-radius: 6px;

    strong {
      display: block;
      font-size: 14px;
      color: #111827;
    }

    span {
      display: block;
      margin-top: 4px;
      color: #667085;
      font-size: 12px;
      line-height: 1.5;
    }
  }

  .flow-index {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 13px;
  }

  .config-tabs {
    margin-top: 18px;
  }

  .config-guide {
    margin-top: 16px;
  }

  .workbench {
    margin-top: 16px;
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
  }

  .workbench-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .workbench-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
  }

  .status-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
  }

  .status-cell {
    padding: 12px;
    border: 1px solid #edf0f3;
    border-radius: 6px;
    background: #fafafa;

    span,
    em {
      display: block;
      color: #667085;
      font-size: 12px;
      font-style: normal;
      line-height: 1.5;
    }

    strong {
      display: block;
      margin: 5px 0;
      color: #111827;
      font-size: 22px;
      line-height: 1.2;
    }
  }

  .guide-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 14px;
    margin-top: 14px;
  }

  .guide-block,
  .problem-block {
    padding: 14px;
    border: 1px solid #edf0f3;
    border-radius: 6px;
    background: #fcfcfd;
  }

  .guide-title {
    margin-bottom: 10px;
    color: #111827;
    font-size: 14px;
    font-weight: 600;
  }

  .guide-row {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 10px;
    padding: 8px 0;
    border-top: 1px solid #edf0f3;

    &:first-of-type {
      border-top: 0;
      padding-top: 0;
    }

    span {
      color: #111827;
      font-size: 13px;
      font-weight: 600;
    }

    p {
      margin: 0;
      color: #667085;
      font-size: 13px;
      line-height: 1.6;
    }
  }

  .problem-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid #edf0f3;

    &:first-of-type {
      border-top: 0;
      padding-top: 0;
    }

    strong {
      color: #111827;
      font-size: 13px;
    }

    p {
      margin: 4px 0 0;
      color: #667085;
      font-size: 12px;
      line-height: 1.5;
    }

    &.danger strong {
      color: #b42318;
    }

    &.warning strong {
      color: #b54708;
    }
  }

  .empty-state {
    padding: 18px 12px;
    color: #667085;
    font-size: 13px;
    line-height: 1.6;
    text-align: center;
    background: #f8fafc;
    border-radius: 6px;
  }

  .dialog-alert {
    margin: 0 0 16px;
  }

  .field-tip,
  .table-hint {
    margin-top: 6px;
    color: #667085;
    font-size: 12px;
    line-height: 1.5;
  }

  .table-hint.warning {
    color: #b45309;
  }

  .section-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 14px;
  }

  .section-title {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
  }

  .base-form {
    max-width: 720px;
  }

  .field-hint {
    margin-left: 8px;
    color: #667085;
    font-size: 13px;
  }

  .parse-panel {
    margin-bottom: 18px;
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
  }

  .parse-head,
  .parse-result-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .parse-title,
  .parse-result-head span {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
  }

  .parse-desc {
    margin-top: 4px;
    color: #667085;
    font-size: 12px;
    line-height: 1.5;
  }

  .parse-actions {
    display: flex;
    flex-shrink: 0;
    gap: 8px;
  }

  .parse-result {
    margin-top: 14px;
  }

  .inline-mapping-panel {
    margin: 6px 0 16px;
    padding: 14px 14px 0;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
  }

  .inline-mapping-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .inline-mapping-title {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
  }

  .inline-mapping-desc {
    margin-top: 4px;
    color: #667085;
    font-size: 12px;
    line-height: 1.5;
  }
}

@media (max-width: 900px) {
  .device-query-page {
    .page-head,
    .section-toolbar {
      flex-direction: column;
    }

    .flow-grid {
      grid-template-columns: 1fr;
    }

    .workbench-head {
      flex-direction: column;
    }

    .workbench-actions {
      justify-content: flex-start;
    }

    .status-grid,
    .guide-layout {
      grid-template-columns: 1fr;
    }

    .parse-head,
    .parse-result-head {
      flex-direction: column;
    }
  }
}
</style>
