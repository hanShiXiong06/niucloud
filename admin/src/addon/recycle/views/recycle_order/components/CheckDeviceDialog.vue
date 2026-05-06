<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    :width="isMobile ? '95vw' : '980px'"
    :destroy-on-close="true"
    class="check-device-dialog"
    align-center
  >

    <!-- ===================================================================
         设备档案：型号 + IMEI 标识栏
    =================================================================== -->
    <section class="cdd-section cdd-device-card">
      <div class="cdd-device-card__header">
        <div class="cdd-device-card__info">
          <div class="cdd-device-card__icon">📱</div>
          <div class="cdd-device-card__content">
            <!-- 型号显示/编辑 -->
            <div class="cdd-device-card__model-row">
              <template v-if="!isEditingDeviceInfo">
                <div class="cdd-device-card__model-display">
                  <span class="model-text">{{ deviceForm.model || '未知型号' }}</span>
                </div>
              </template>
              <el-input
                v-else
                v-model="deviceForm.model"
                placeholder="请输入设备型号"
                size="default"
                clearable
                ref="modelInputRef"
                class="cdd-model-input"
              >
                <template #prefix>
                  <el-icon><Cellphone /></el-icon>
                </template>
              </el-input>
            </div>

            <!-- IMEI显示/编辑 -->
            <div class="cdd-device-card__imei-row">
              <template v-if="!isEditingDeviceInfo">
                <div class="cdd-device-card__imei-display">
                  <span class="imei-label">IMEI</span>
                  <span class="imei-value">{{ formatImei(deviceForm.imei) || '未录入' }}</span>
                </div>
              </template>
              <el-input
                v-else
                v-model="deviceForm.imei"
                placeholder="请输入或扫描15位IMEI"
                size="default"
                clearable
                maxlength="15"
                show-word-limit
                ref="imeiInputRef"
                @input="handleImeiInput"
                class="cdd-imei-input"
              >
                <template #prefix>
                  <el-icon><Postcard /></el-icon>
                </template>
                <template #append>
                  <el-button @click="focusImeiInput" size="small" type="primary" link>
                    <el-icon><Aim /></el-icon> 扫码
                  </el-button>
                </template>
              </el-input>
            </div>
          </div>
        </div>

        <!-- 编辑按钮 -->
        <div class="cdd-device-card__actions">
          <template v-if="!isEditingDeviceInfo">
            <el-button
              type="primary"
              size="small"
              link
              @click="startEditDeviceInfo"
              class="cdd-edit-link"
            >
              <el-icon><Edit /></el-icon>
              <span>编辑</span>
            </el-button>
          </template>
          <template v-else>
            <el-button
              type="success"
              size="small"
              @click="saveDeviceInfo"
            >
              <el-icon><Check /></el-icon>
            </el-button>
            <el-button
              size="small"
              @click="cancelEditDeviceInfo"
            >
              <el-icon><Close /></el-icon>
            </el-button>
          </template>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 1：设备信息（8项输入：规格 + 电池 + 锁）
    =================================================================== -->
    <section class="cdd-section cdd-block-info">
      <div class="cdd-block-header">
        <span>{{ groupLabel('device_info', '设备信息') }}</span>
        <div class="cdd-toolbar">
          <!-- 联网查询组 -->
          <div class="cdd-query-group">
            <span class="cdd-query-group__tag">联网</span>
            <el-button
              v-for="action in visibleDeviceQueryActions"
              :key="action.code"
              size="small"
              text
              :loading="isQueryActionLoading(action.code)"
              :disabled="!deviceForm.imei"
              @click="runDeviceQueryAction(action)"
            >
              <el-icon v-if="!isQueryActionLoading(action.code)">
                <component :is="getQueryActionIcon(action.result_handler)" />
              </el-icon>
              {{ isQueryActionLoading(action.code) ? '查询中...' : action.name }}
            </el-button>
          </div>
          <!-- 本地操作 -->
          <div class="cdd-local-group">
            <el-button size="small" text @click="fillCommonResult">常用模板</el-button>
            <el-button size="small" text type="danger" @click="clearAllSelections">清空选项</el-button>
          </div>
        </div>
      </div>

      <!-- 8格输入网格（2行×4列） -->
      <div class="cdd-info-grid">
        <!-- 第一行：基本规格 -->
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">💾</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('capacity', '内存') }}</span>
            <el-input v-model="deviceForm.capacity" size="small" :placeholder="fieldPlaceholder('capacity', '如 256GB')" @change="updateCheckResult" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">🎨</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('color', '颜色') }}</span>
            <el-input v-model="deviceForm.color" size="small" :placeholder="fieldPlaceholder('color', '如 深空黑色')" @change="updateCheckResult" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">📲</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('system_version', '系统版本') }}</span>
            <el-input v-model="deviceForm.system_version" size="small" :placeholder="fieldPlaceholder('system_version', '如 iOS 17.3.1')" @change="updateCheckResult" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">🛡</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('warranty_info', '保修信息') }}</span>
            <el-input v-model="deviceForm.warranty_info" size="small" :placeholder="fieldPlaceholder('warranty_info', '保修日期/过保/未激活')" @change="updateCheckResult" />
          </div>
        </div>
        <!-- 第二行：电池 + 锁 -->
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔋</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('battery', '电池健康度') }}</span>
            <div class="cdd-info-cell__input-row">
              <el-input-number
                v-model="templateSelections.battery"
                :min="0" :max="100" :step="1"
                size="small" controls-position="right"
                class="cdd-info-cell__number"
                @change="updateCheckResult"
              />
              <span class="cdd-info-cell__unit">{{ fieldUnit('battery', '%') }}</span>
            </div>
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔁</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('battery_num', '循环次数') }}</span>
            <div class="cdd-info-cell__input-row">
              <el-input
                v-model="templateSelections.battery_num"
                type="number" size="small" style="flex:1"
                @change="updateCheckResult"
              />
              <span class="cdd-info-cell__unit">{{ fieldUnit('battery_num', '次') }}</span>
            </div>
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔒</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('activation_lock', '激活锁') }}</span>
            <el-switch
              v-model="templateSelections.activationLock"
              active-text="已开" inactive-text="未开" size="small"
              style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e"
              @change="updateCheckResult"
            />
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🖥</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">{{ fieldLabel('mdm_lock', '监管锁') }}</span>
            <el-switch
              v-model="templateSelections.mdmLock"
              active-text="已开" inactive-text="未开" size="small"
              style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e"
              @change="updateCheckResult"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 2：外观规格（外屏 | 内屏 | 中框）
    =================================================================== -->
    <section class="cdd-section cdd-block-appearance">
      <div class="cdd-block-header">
        <span>{{ groupLabel('appearance', '外观规格') }}</span>
        <span
          v-if="[templateSelections.screenId, templateSelections.indisplayId, templateSelections.appearanceId].filter(Boolean).length > 0"
          class="cdd-block-badge"
        >
          已选 {{ [templateSelections.screenId, templateSelections.indisplayId, templateSelections.appearanceId].filter(Boolean).length }}/3
        </span>
      </div>
      <div class="cdd-sub-cols cdd-sub-cols--3">
        <!-- 外屏规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.screenId }">
          <div class="cdd-sub-header">
            {{ fieldLabel('screen_id', '外屏规格') }}
            <span v-if="templateSelections.screenId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.screen" :key="opt.value"
              :type="templateSelections.screenId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.screenId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectScreenOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 内屏规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.indisplayId }">
          <div class="cdd-sub-header">
            {{ fieldLabel('indisplay_id', '内屏规格') }}
            <span v-if="templateSelections.indisplayId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.indisplay" :key="opt.value"
              :type="templateSelections.indisplayId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.indisplayId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectIndisplayOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 中框规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.appearanceId }">
          <div class="cdd-sub-header">
            {{ fieldLabel('appearance_id', '中框规格') }}
            <span v-if="templateSelections.appearanceId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.appearance" :key="opt.value"
              :type="templateSelections.appearanceId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.appearanceId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectAppearanceOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 3：问题记录（功能异常 | 维修记录）
    =================================================================== -->
    <section class="cdd-section cdd-block-issues">
      <div class="cdd-block-header">
        <span>{{ groupLabel('issues', '问题记录') }}</span>
        <span
          v-if="templateSelections.functionIds.length + templateSelections.fixIds.length > 0"
          class="cdd-block-badge cdd-block-badge--warn"
        >
          已选 {{ templateSelections.functionIds.length + templateSelections.fixIds.length }} 项
        </span>
      </div>
      <div class="cdd-sub-cols cdd-sub-cols--2">
        <!-- 功能异常 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.functionIds.length > 0 }">
          <div class="cdd-sub-header">
            {{ fieldLabel('function_ids', '功能') }}
            <span v-if="templateSelections.functionIds.length > 0" class="cdd-sub-badge cdd-sub-badge--danger">
              {{ templateSelections.functionIds.length }} 项
            </span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.function" :key="opt.value"
              :type="templateSelections.functionIds.includes(String(opt.value)) ? 'danger' : undefined"
              :effect="templateSelections.functionIds.includes(String(opt.value)) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="toggleFunctionOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 维修记录 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.fixIds.length > 0 }">
          <div class="cdd-sub-header">
            {{ fieldLabel('fix_ids', '维修记录') }}
            <span v-if="templateSelections.fixIds.length > 0" class="cdd-sub-badge cdd-sub-badge--warning">
              {{ templateSelections.fixIds.length }} 项
            </span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.fix" :key="opt.value"
              :type="templateSelections.fixIds.includes(String(opt.value)) ? 'warning' : undefined"
              :effect="templateSelections.fixIds.includes(String(opt.value)) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="toggleFixOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
      </div>
    </section>

    <section v-if="customCheckGroups.length" class="cdd-section cdd-custom-section">
      <div class="cdd-block-header">
        <span>自定义质检项</span>
        <span class="cdd-block-badge">模板 {{ checkTemplateInfo?.template_name || '默认' }}</span>
      </div>
      <div class="cdd-custom-groups">
        <div v-for="group in customCheckGroups" :key="group.id || group.group_key" class="cdd-custom-group">
          <div class="cdd-sub-header">{{ group.group_name }}</div>
          <div class="cdd-custom-grid">
            <div v-for="field in group.fields" :key="field.field_key" class="cdd-custom-field">
              <div class="cdd-custom-field__label">
                {{ field.field_name }}
                <span v-if="field.unit" class="cdd-custom-field__unit">{{ field.unit }}</span>
              </div>

              <el-input
                v-if="field.component === 'input'"
                :model-value="templateSelections.customFields[field.field_key]"
                :placeholder="field.placeholder"
                size="small"
                clearable
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              />
              <el-input
                v-else-if="field.component === 'textarea'"
                :model-value="templateSelections.customFields[field.field_key]"
                :placeholder="field.placeholder"
                type="textarea"
                :rows="2"
                resize="none"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              />
              <el-input-number
                v-else-if="field.component === 'number'"
                :model-value="templateSelections.customFields[field.field_key]"
                :min="0"
                controls-position="right"
                size="small"
                class="cdd-custom-field__number"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              />
              <el-switch
                v-else-if="field.component === 'switch'"
                :model-value="!!templateSelections.customFields[field.field_key]"
                active-text="是"
                inactive-text="否"
                size="small"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              />
              <el-select
                v-else-if="field.component === 'select'"
                :model-value="templateSelections.customFields[field.field_key]"
                :placeholder="field.placeholder || '请选择'"
                size="small"
                clearable
                class="cdd-custom-field__select"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              >
                <el-option v-for="option in field.options || []" :key="option.value" :label="option.name || option.label" :value="String(option.value)" />
              </el-select>
              <el-radio-group
                v-else-if="field.component === 'radio'"
                :model-value="templateSelections.customFields[field.field_key]"
                size="small"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              >
                <el-radio-button v-for="option in field.options || []" :key="option.value" :label="String(option.value)">
                  {{ option.name || option.label }}
                </el-radio-button>
              </el-radio-group>
              <el-checkbox-group
                v-else-if="field.component === 'checkbox'"
                :model-value="templateSelections.customFields[field.field_key] || []"
                size="small"
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              >
                <el-checkbox-button v-for="option in field.options || []" :key="option.value" :label="String(option.value)">
                  {{ option.name || option.label }}
                </el-checkbox-button>
              </el-checkbox-group>
              <el-input
                v-else
                :model-value="templateSelections.customFields[field.field_key]"
                :placeholder="field.placeholder"
                size="small"
                clearable
                @update:model-value="value => setCustomFieldValue(field.field_key, value)"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         区域 3：质检结果文本（卖家 / 买家）
    =================================================================== -->
    <el-form ref="formRef" :model="deviceForm" :rules="rules" label-position="top">
      <section class="cdd-section cdd-result-section">
        <div class="cdd-result-cols">

          <!-- 卖家质检结果 -->
          <div class="cdd-result-card cdd-result-card--seller">
            <div class="cdd-result-card__header">
              <span class="cdd-result-card__dot"></span>
              <span class="cdd-result-card__title">卖家可见质检结果</span>
              <el-tooltip content="此内容将展示给卖家" placement="top">
                <el-icon class="cdd-result-card__help"><Warning /></el-icon>
              </el-tooltip>
            </div>
            <el-form-item prop="check_result_seller" class="cdd-result-card__body">
              <el-input
                v-model="deviceForm.check_result_seller"
                type="textarea"
                :rows="isMobile ? 5 : 7"
                placeholder="由上方质检选项自动生成，也可手动编辑..."
                maxlength="500"
                show-word-limit
                resize="none"
              />
            </el-form-item>
          </div>

          <!-- 买家质检结果 -->
          <div class="cdd-result-card cdd-result-card--buyer">
            <div class="cdd-result-card__header">
              <span class="cdd-result-card__dot"></span>
              <span class="cdd-result-card__title">买家可见质检结果</span>
              <el-tooltip content="此内容将展示给买家，可独立编辑" placement="top">
                <el-icon class="cdd-result-card__help"><Warning /></el-icon>
              </el-tooltip>
              <el-button
                type="primary" link size="small"
                class="ml-auto"
                @click="syncSellerResultToBuyer"
              >
                <el-icon><CopyDocument /></el-icon> 同步卖家
              </el-button>
            </div>
            <el-form-item prop="check_result_buyer" class="cdd-result-card__body">
              <el-input
                v-model="deviceForm.check_result_buyer"
                type="textarea"
                :rows="isMobile ? 5 : 7"
                placeholder="可点击「同步卖家」快速填充，再按需修改..."
                maxlength="500"
                show-word-limit
                resize="none"
              />
            </el-form-item>
          </div>

        </div>
      </section>

      <!-- ===================================================================
           区域 4：定价信息
      =================================================================== -->
      <section class="cdd-section cdd-pricing-section">
        <div class="cdd-block-header">💰 定价信息</div>

        <!-- 回收定价：独占一行、视觉突出 -->
        <div class="cdd-pricing-main">
          <el-form-item prop="final_price" class="cdd-pricing-main__item">
            <template #label>
              <span class="cdd-pricing-main__label">
                回收定价
                <span class="cdd-pricing-main__required">*</span>
                <span v-if="deviceData.initial_price" class="cdd-pricing-main__ref">
                  参考预估：¥{{ deviceData.initial_price }}
                </span>
              </span>
            </template>
            <el-input-number
              v-model="deviceForm.final_price"
              :step="10" :precision="2" :min="0" :max="99999"
              placeholder="输入回收定价"
              controls-position="right"
              class="cdd-pricing-main__input"
            />
          </el-form-item>
          <el-form-item label="扣费说明" prop="remark" class="cdd-pricing-sub__item cdd-pricing-sub__item--remark">
            <el-input
              v-model="deviceForm.remark"
              placeholder="扣费原因、特殊备注..."
              maxlength="200"
              clearable
            />
          </el-form-item>
        </div>
      </section>

      <!-- ===================================================================
           区域 5：质检图片
      =================================================================== -->
      <section class="cdd-section cdd-photos-section">
        <div class="cdd-photos-cols">

          <!-- 卖家图片 -->
          <div class="cdd-photo-block cdd-photo-block--seller">
            <div class="cdd-photo-block__header">
              <span class="cdd-photo-block__dot"></span>
              卖家质检图片
            </div>
            <div class="cdd-photo-block__body">
              <div v-if="isMobile" class="cdd-camera-row">
                <el-button
                  type="primary" plain size="small"
                  :loading="cameraUploading"
                  :disabled="cameraUploading || checkImageCount >= maxCheckImageCount"
                  @click="openCameraCapture"
                >{{ cameraUploading ? '上传中...' : '📷 拍照上传' }}</el-button>
                <span class="cdd-camera-tip">{{ checkImageCount }}/{{ maxCheckImageCount }}</span>
                <input
                  ref="cameraInputRef" type="file" accept="image/*"
                  capture="environment" multiple class="hidden"
                  @change="handleCameraFilesChange"
                />
              </div>
              <upload-image v-model="deviceForm.check_images" :limit="9" />
            </div>
          </div>

          <!-- 买家图片 -->
          <div class="cdd-photo-block cdd-photo-block--buyer">
            <div class="cdd-photo-block__header">
              <span class="cdd-photo-block__dot"></span>
              买家质检图片
              <el-button type="primary" link size="small" class="ml-auto" @click="syncSellerImagesToBuyer">
                <el-icon><CopyDocument /></el-icon> 同步卖家图片
              </el-button>
            </div>
            <div class="cdd-photo-block__body">
              <div v-if="isMobile" class="cdd-camera-row">
                <el-button
                  type="primary" plain size="small"
                  :loading="buyerCameraUploading"
                  :disabled="buyerCameraUploading || buyerCheckImageCount >= buyerMaxCheckImageCount"
                  @click="openBuyerCameraCapture"
                >{{ buyerCameraUploading ? '上传中...' : '📷 拍照上传' }}</el-button>
                <span class="cdd-camera-tip">{{ buyerCheckImageCount }}/{{ buyerMaxCheckImageCount }}</span>
                <input
                  ref="buyerCameraInputRef" type="file" accept="image/*"
                  capture="environment" multiple class="hidden"
                  @change="handleBuyerCameraFilesChange"
                />
              </div>
              <upload-image v-model="deviceForm.check_images_buyer" :limit="6" />
            </div>
          </div>

        </div>
      </section>

    </el-form>

    <!-- ===================================================================
         底部操作栏
    =================================================================== -->
    <template #footer>
      <div class="cdd-footer">
        <span class="cdd-footer__info">已填质检项：{{ checkedCount }}</span>
        <div class="cdd-footer__btns">
          <el-button size="large" @click="handleCancel">取消</el-button>
          <el-button type="warning" size="large" :loading="savingDraft" @click="handleSaveDraft">
            {{ savingDraft ? '暂存中...' : '暂存草稿' }}
          </el-button>
          <el-button type="primary" size="large" :loading="submitting" @click="handleConfirm">
            <el-icon v-if="!submitting"><Check /></el-icon>
            {{ submitting ? '提交中...' : '完成质检' }}
          </el-button>
        </div>
      </div>
    </template>

  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed, nextTick, onMounted, onBeforeUnmount, toRef } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import {
  Cellphone, Edit, Postcard, Aim, Monitor, Check, Close, Headset, Lock, CopyDocument, Warning
} from '@element-plus/icons-vue'
import QRCode from 'qrcode'

import { queryDeviceByService } from '@/addon/recycle/api/device_query_api'
import { getDeviceQueryConfigList } from '@/addon/recycle/api/device_query_config'
import { getCheckTemplateSchema } from '@/addon/recycle/api/check_template'
import { useCheckDeviceDict } from '@/addon/recycle/hooks/useCheckDeviceDict'
import {
  normalizeInfo,
  useCheckMeta,
  type CheckMetaPayload,
  type CheckOptionsGroup,
  type CheckTemplateField
} from './composables/useCheckMeta'
import { useCameraUpload } from './composables/useCameraUpload'

interface DeviceInfo {
  id?: string | number
  model?: string
  imei?: string
  initial_price?: string | number
  final_price?: string | number
  sell_price?: string | number
  check_result?: string
  check_result_seller?: string
  check_result_buyer?: string
  check_meta?: CheckMetaPayload | string | null
  check_images?: string
  check_images_seller?: string
  check_images_buyer?: string
  check_status?: number
  remark?: string
  status?: number
  info?: any
  [key: string]: any
}

const props = defineProps<{ visible: boolean; device: DeviceInfo }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'confirm': [data: any]
  'cancel': []
  'save-draft': [data: any]
}>()

const dictOptions = useCheckDeviceDict()
const checkSchemaLoading = ref(false)
const checkTemplateInfo = ref<any>(null)
const checkTemplateGroups = ref<any[]>([])
const knownCheckFieldKeys = ['capacity', 'color', 'system_version', 'warranty_info', 'battery', 'battery_num', 'activation_lock', 'mdm_lock', 'screen_id', 'indisplay_id', 'appearance_id', 'function_ids', 'fix_ids']
const schemaFieldKeyMap: Record<string, keyof CheckOptionsGroup> = {
  screen_id: 'screen',
  indisplay_id: 'indisplay',
  appearance_id: 'appearance',
  function_ids: 'function',
  fix_ids: 'fix'
}

const normalizeSchemaOption = (option: any) => ({
  name: option.name || option.label || option.option_label || '',
  label: option.label || option.name || option.option_label || '',
  value: String(option.value ?? option.option_value ?? ''),
  sort: Number(option.sort || 0),
  memo: option.memo || ''
})

const fieldConfigByKey = computed<Record<string, CheckTemplateField>>(() => {
  const map: Record<string, CheckTemplateField> = {}
  checkTemplateGroups.value.forEach((group: any) => {
    ;(group.fields || []).forEach((field: any) => {
      map[field.field_key] = {
        ...field,
        options: (field.options || []).map(normalizeSchemaOption)
      }
    })
  })
  return map
})

const groupConfigByKey = computed<Record<string, any>>(() => {
  const map: Record<string, any> = {}
  checkTemplateGroups.value.forEach((group: any) => {
    map[group.group_key] = group
  })
  return map
})

const checkDictOptions = computed<CheckOptionsGroup>(() => {
  const options = { ...(dictOptions.options.value as CheckOptionsGroup) }
  Object.entries(schemaFieldKeyMap).forEach(([fieldKey, optionKey]) => {
    const field = fieldConfigByKey.value[fieldKey]
    if (field?.options?.length) {
      options[optionKey] = field.options as any
    }
  })
  return options
})

const customCheckGroups = computed(() => {
  return checkTemplateGroups.value
    .map((group: any) => ({
      ...group,
      fields: (group.fields || []).filter((field: any) => !knownCheckFieldKeys.includes(field.field_key))
    }))
    .filter((group: any) => group.fields.length)
})

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const modelInputRef = ref()
const isEditingDeviceInfo = ref(false)
const qrCode = ref('')
const isMobile = ref(false)

// 保存编辑前的原始数据
const originalDeviceInfo = ref({ model: '', imei: '' })

const activationLockInfo = ref<any>(null)
const mdmInfo = ref<any>(null)
const deviceQueryServices = ref<any[]>([])
const deviceQueryLoadingMap = ref<Record<string, boolean>>({})

const visibleDeviceQueryActions = computed(() => {
  return deviceQueryServices.value
    .filter(service => (
      Number(service.enabled) === 1
      && Number(service.show_in_check) === 1
      && Number(service.enabled_mapping_count ?? (service.mapping_count || 0)) > 0
    ))
    .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
})

const deviceForm = reactive({
  model: props.device.model || '',
  check_result: '',
  check_result_seller: props.device.check_result_seller || props.device.check_result || '',
  check_result_buyer: props.device.check_result_buyer || '',
  check_images: props.device.check_images_seller || props.device.check_images || '',
  check_images_buyer: props.device.check_images_buyer || '',
  final_price: parsePrice(props.device.final_price),
  sell_price: parsePrice(props.device.sell_price),
  remark: props.device.remark || '',
  imei: props.device.imei || '',
  info: normalizeInfo(props.device.info),
  system_version: props.device.system_version || '',
  warranty_info: props.device.warranty_info || '',
  capacity: normalizeInfo(props.device.info).capacity || '',
  color: normalizeInfo(props.device.info).color || ''
})

const rules = reactive<FormRules>({
  check_result_seller: [
    { required: true, message: '请输入质检结果', trigger: 'blur' },
    { min: 5, message: '质检结果至少5个字符', trigger: 'blur' }
  ]
})

const {
  templateSelections, checkedCount, getSubmitInfo,
  updateCheckResult, clearAllSelections, fillCommonResult,
  restoreFromDevice,
  setCustomFieldValue,
  selectScreenOption, selectIndisplayOption, selectAppearanceOption,
  toggleFunctionOption, toggleFixOption
} = useCheckMeta({
  dictOptions: checkDictOptions,
  deviceForm,
  fieldConfigByKey,
  templateInfo: computed(() => checkTemplateInfo.value)
})

const {
  cameraUploading, cameraInputRef, maxCheckImageCount,
  checkImageCount, openCameraCapture, handleCameraFilesChange
} = useCameraUpload({ checkImages: toRef(deviceForm, 'check_images') })

const {
  cameraUploading: buyerCameraUploading,
  cameraInputRef: buyerCameraInputRef,
  maxCheckImageCount: buyerMaxCheckImageCount,
  checkImageCount: buyerCheckImageCount,
  openCameraCapture: openBuyerCameraCapture,
  handleCameraFilesChange: handleBuyerCameraFilesChange
} = useCameraUpload({ checkImages: toRef(deviceForm, 'check_images_buyer') })

// 电池卡完成状态
const batteryDone = computed(() =>
  templateSelections.battery !== undefined || templateSelections.battery_num !== undefined
)

function parsePrice(value: any): number | undefined {
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const parsed = parseFloat(value)
    return Number.isNaN(parsed) ? undefined : parsed
  }
  return undefined
}

const updateDeviceMode = () => { isMobile.value = window.innerWidth <= 768 }

const generateQrCode = async () => {
  qrCode.value = await QRCode.toDataURL(
    window.location.origin + '/site/diy/attachment',
    { errorCorrectionLevel: 'L', margin: 0, width: 100 }
  )
}

const loadCheckTemplateSchema = async () => {
  checkSchemaLoading.value = true
  try {
    const res: any = await getCheckTemplateSchema({ scene: 'phone' })
    const payload = res.data || {}
    checkTemplateInfo.value = payload.template || null
    checkTemplateGroups.value = payload.groups || []
    restoreFromDevice(deviceData.value)
  } catch (error) {
    checkTemplateInfo.value = null
    checkTemplateGroups.value = []
  } finally {
    checkSchemaLoading.value = false
  }
}

const fieldLabel = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.field_name || fallback
}

const fieldPlaceholder = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.placeholder || fallback
}

const fieldUnit = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.unit || fallback
}

const groupLabel = (groupKey: string, fallback: string) => {
  return groupConfigByKey.value[groupKey]?.group_name || fallback
}

const loadDeviceQueryActions = async () => {
  try {
    const res = await getDeviceQueryConfigList({ page: 1, limit: 100 })
    const payload = res.data?.list || res.data?.config ? res.data : (res.data?.data || {})
    deviceQueryServices.value = payload.list || payload.data || []
  } catch {
    deviceQueryServices.value = []
  }
}

const isQueryActionLoading = (code: string) => {
  return !!deviceQueryLoadingMap.value[code]
}

const getQueryActionIcon = (handler: string) => {
  if (handler === 'coverage') return Headset
  if (handler === 'activationlock') return Lock
  if (handler === 'mdm') return Monitor
  return Headset
}

const runDeviceQueryAction = async (action: any) => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码')
    return
  }
  const serviceCode = action.code
  deviceQueryLoadingMap.value = { ...deviceQueryLoadingMap.value, [serviceCode]: true }
  try {
    const res = await queryDeviceByService({
      service_code: serviceCode,
      query_code: deviceForm.imei,
      query_type: action.query_type || 'imei'
    })
    applyDeviceQueryResult(action, res.data?.data || res.data || {})
  } catch (error: any) {
    ElMessage.error(error?.message || `${action.name || '设备查询'}失败，请检查设备查询配置`)
  } finally {
    deviceQueryLoadingMap.value = { ...deviceQueryLoadingMap.value, [serviceCode]: false }
  }
}

const unwrapDeviceQueryData = (payload: any) => {
  if (payload?.data?.data) return payload.data.data
  if (payload?.data) return payload.data
  return payload || {}
}

const applyDeviceQueryResult = (action: any, payload: any) => {
  const data = unwrapDeviceQueryData(payload)
  if (!data || Object.keys(data).length === 0) {
    ElMessage.warning(`${action.name || '设备查询'}未查询到有效数据`)
    return
  }

  deviceForm.info = {
    ...normalizeInfo(deviceForm.info),
    [action.code]: data,
    last_device_query: {
      service_code: action.code,
      service_name: action.name,
      result_handler: action.result_handler || 'generic',
      data
    }
  }

  if (action.result_handler === 'coverage') {
    applyCoverageData(data)
    ElMessage.success(`${action.name}已自动填入`)
    return
  }

  if (action.result_handler === 'activationlock') {
    activationLockInfo.value = data
    templateSelections.activationLock = data.locked === true || data.fmi === 'On' || data.activation_lock === 'On' || data.activation_lock === '有锁'
    updateCheckResult()
    ElMessage.success(`${action.name}：${templateSelections.activationLock ? '已开启' : '未开启'}，已自动填入`)
    return
  }

  if (action.result_handler === 'mdm') {
    mdmInfo.value = data
    templateSelections.mdmLock = data.locked === true || data.mdm === 'On' || data.mdm === true
    updateCheckResult()
    ElMessage.success(`${action.name}：${templateSelections.mdmLock ? '已开启' : '未开启'}，已自动填入`)
    return
  }

  ElMessage.success(`${action.name || '设备查询'}查询成功`)
}

const applyCoverageData = (data: any) => {
  const { capacity, color, modelDisplay } = parseCoverageFields(data)
  const fullModel = [modelDisplay, capacity, color].filter(Boolean).join(' ')
  if (fullModel) {
    deviceData.value.model = fullModel
    deviceForm.model = fullModel
  }
  deviceForm.info = { ...normalizeInfo(deviceForm.info), ...data }
  deviceForm.info = getSubmitInfo()
  if (capacity) deviceForm.capacity = capacity
  if (color) deviceForm.color = color
  if (data.osVersion) deviceForm.system_version = data.osVersion
  if (data.coverage) {
    deviceForm.warranty_info = parseCoverageStatus(data.coverage)
  } else if (data.coverage_status || data.coverage_date) {
    deviceForm.warranty_info = parseCoverageStatus({
      status: data.coverage_status,
      date: data.coverage_date
    })
  }
  updateCheckResult()
}

// 格式化 IMEI 显示
const formatImei = (imei: string) => {
  if (!imei) return ''
  return imei.replace(/(\d{4})(?=\d)/g, '$1 ')
}

// 开始编辑设备信息
const startEditDeviceInfo = () => {
  originalDeviceInfo.value = {
    model: deviceForm.model,
    imei: deviceForm.imei
  }
  isEditingDeviceInfo.value = true
  nextTick(() => {
    modelInputRef.value?.focus()
  })
}

// 保存设备信息
const saveDeviceInfo = () => {
  if (!deviceForm.model?.trim()) {
    ElMessage.warning('请输入设备型号')
    return
  }
  if (!deviceForm.imei?.trim()) {
    ElMessage.warning('请输入IMEI号')
    return
  }
  if (deviceForm.imei.length !== 15) {
    ElMessage.warning('IMEI号必须是15位')
    return
  }

  isEditingDeviceInfo.value = false
  ElMessage.success('设备信息已更新')
}

// 取消编辑设备信息
const cancelEditDeviceInfo = () => {
  deviceForm.model = originalDeviceInfo.value.model
  deviceForm.imei = originalDeviceInfo.value.imei
  isEditingDeviceInfo.value = false
}

const handleImeiInput = (value: string) => {
  deviceForm.imei = value.replace(/[^0-9]/g, '')
}

const focusImeiInput = () => {
  imeiInputRef.value?.focus()
}

// 从接口返回数据中兼容提取 capacity / color / model
const parseCoverageFields = (d: any) => {
  // capacity / color：安卓品牌放在 product 子对象，苹果直接在顶层
  const capacity = d.capacity || d.product?.capacity || ''
  const color    = d.color    || d.product?.color    || ''
  // model：接口返回的 d.model 是最权威的型号名（OPPO Find X9 Pro / iPhone 16 Pro 等）
  // product.model 仅作兜底（部分品牌顶层 model 为空时）
  const modelDisplay = d.model || d.product?.model || ''
  return { capacity, color, modelDisplay }
}

// 兼容多品牌的保修状态解析
const parseCoverageStatus = (coverage: any): string => {
  if (!coverage) return ''
  const status = (coverage.status || '').trim()
  const date   = (coverage.date   || '').trim()
  if (status === 'Out Of Warranty') return '过保'
  if (status === 'Not Activated' || (!date && !status)) return '未激活'
  if (status === 'In Warranty' || status === 'Active') {
    return date ? `在保至 ${date}` : '在保'
  }
  // 其他情况：有日期就显示日期，否则显示原始 status
  return date || status || '在保'
}

const syncSellerResultToBuyer = () => {
  if (!deviceForm.check_result_seller) { ElMessage.warning('卖家质检结果为空，无法同步'); return }
  deviceForm.check_result_buyer = deviceForm.check_result_seller
  ElMessage.success('已同步，可继续单独编辑买家内容')
}

const syncSellerImagesToBuyer = () => {
  if (!deviceForm.check_images) { ElMessage.warning('卖家质检图片为空，无法同步'); return }
  deviceForm.check_images_buyer = deviceForm.check_images
  ElMessage.success('已同步卖家图片到买家')
}

const handleCancel = () => { dialogVisible.value = false; emit('cancel') }

const handleConfirm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try { emit('confirm', buildSubmitPayload('check')); dialogVisible.value = false }
    finally { submitting.value = false }
  })
}

const handleSaveDraft = async () => {
  savingDraft.value = true
  try { emit('save-draft', buildSubmitPayload('save_draft')); dialogVisible.value = false }
  finally { savingDraft.value = false }
}

function buildSubmitPayload(action: 'check' | 'save_draft') {
  const sellerImages = deviceForm.check_images || ''
  return {
    id: deviceData.value.id,
    check_result: deviceForm.check_result,
    check_result_seller: deviceForm.check_result_seller,
    check_result_buyer: deviceForm.check_result_buyer,
    check_images: sellerImages,
    check_images_seller: sellerImages,
    check_images_buyer: deviceForm.check_images_buyer,
    remark: deviceForm.remark,
    check_status: action === 'check' ? 1 : undefined,
    final_price: deviceForm.final_price,
    sell_price: deviceForm.sell_price,
    action,
    imei: deviceForm.imei,
    model: deviceData.value.model,
    info: getSubmitInfo(),
    system_version: deviceForm.system_version,
    warranty_info: deviceForm.warranty_info,
    capacity: deviceForm.capacity,
    color: deviceForm.color
  }
}

const initializeFormFromDevice = (device: DeviceInfo) => {
  activationLockInfo.value = null
  mdmInfo.value = null
  isEditingDeviceInfo.value = false
  deviceQueryLoadingMap.value = {}
  clearAllSelections()
  deviceData.value = { ...device }
  // 更新设备基本信息
  deviceForm.model = device.model || ''
  deviceForm.imei = device.imei || ''
  deviceForm.check_result_seller = device.check_result_seller || device.check_result || ''
  deviceForm.check_result = ''
  deviceForm.check_result_buyer = device.check_result_buyer || ''
  deviceForm.check_images = device.check_images_seller || device.check_images || ''
  deviceForm.check_images_buyer = device.check_images_buyer || ''
  deviceForm.final_price = parsePrice(device.final_price)
  deviceForm.sell_price = parsePrice(device.sell_price)
  deviceForm.remark = device.remark || ''
  deviceForm.imei = device.imei || ''
  deviceForm.info = normalizeInfo(device.info)
  deviceForm.system_version = device.system_version || ''
  deviceForm.warranty_info = device.warranty_info || ''
  const restoredInfo = normalizeInfo(device.info)
  deviceForm.capacity = device.capacity || restoredInfo.capacity || ''
  deviceForm.color = device.color || restoredInfo.color || ''
  restoreFromDevice(device)
  nextTick(() => { formRef.value?.clearValidate() })
}

watch(() => props.visible, (val) => { dialogVisible.value = val })
watch(() => props.device, (val) => { initializeFormFromDevice(val) }, { deep: true })
watch(dialogVisible, (val) => { emit('update:visible', val) })
watch(
  () => [
    checkDictOptions.value.screen, checkDictOptions.value.indisplay,
    checkDictOptions.value.appearance, checkDictOptions.value.function, checkDictOptions.value.fix
  ],
  () => {
    if (!dialogVisible.value || checkedCount.value > 0) return
    restoreFromDevice(deviceData.value)
  },
  { deep: true }
)

onMounted(async () => {
  updateDeviceMode()
  window.addEventListener('resize', updateDeviceMode)
  generateQrCode()
  initializeFormFromDevice(props.device)
  await dictOptions.loadDictionary()
  await loadCheckTemplateSchema()
  await loadDeviceQueryActions()
})
onBeforeUnmount(() => { window.removeEventListener('resize', updateDeviceMode) })
</script>

<style lang="scss" scoped>
/* ============================================================
   Dialog 容器
   ============================================================ */
.check-device-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.12);
  }
  :deep(.el-dialog__header) {
    padding: 18px 24px 14px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border: none;
    .el-dialog__title { font-size: 17px; font-weight: 700; color: #fff; }
  }
  :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: rgba(255,255,255,0.8);
    &:hover { color: #fff; }
  }
  :deep(.el-dialog__body) {
    padding: 12px 16px;
    background: #f4f6f9;
    max-height: 76vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    &::-webkit-scrollbar { width: 5px; }
    &::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
  }
  :deep(.el-dialog__footer) {
    padding: 14px 20px;
    background: #fff;
    border-top: 1px solid #e5e7eb;
  }
}

/* ============================================================
   通用 section 容器
   ============================================================ */
.cdd-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 10px;
}

/* ============================================================
   设备档案卡
   ============================================================ */
.cdd-device-card {
  background: linear-gradient(135deg, #393b41 0%, #302e32 100%);
  border: none;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    gap: 16px;
  }

  &__info {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 0;
  }

  &__icon {
    font-size: 42px;
    flex-shrink: 0;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__model-row {
    margin-bottom: 8px;
  }

  &__model-display {
    .model-text {
      font-size: 18px;
      font-weight: 700;
      color: #ffffff;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      display: block;
      line-height: 1.4;
    }
  }

  &__imei-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  &__imei-display {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);

    .imei-label {
      font-size: 11px;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.8);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .imei-value {
      font-size: 13px;
      font-weight: 600;
      color: #ffffff;
      font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
      letter-spacing: 0.5px;
    }
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  // 编辑模式下的输入框样式
  .cdd-model-input,
  .cdd-imei-input {
    :deep(.el-input__wrapper) {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);

      &:hover {
        border-color: rgba(255, 255, 255, 0.5);
      }

      &.is-focus {
        border-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
      }
    }

    :deep(.el-input__inner) {
      color: #1f2937;
      font-weight: 500;
    }
  }

  .cdd-model-input {
    :deep(.el-input__inner) {
      font-size: 16px;
      font-weight: 600;
    }
  }

  .cdd-imei-input {
    :deep(.el-input__inner) {
      font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
      letter-spacing: 0.5px;
    }
  }
}

// 编辑链接按钮样式
.cdd-edit-link {
  color: rgba(255, 255, 255, 0.9) !important;
  font-weight: 500;
  transition: all 0.2s ease;

  &:hover {
    color: #ffffff !important;
    transform: translateY(-1px);
  }

  span {
    margin-left: 4px;
  }
}

// 编辑模式下的按钮样式
.cdd-device-card__actions {
  .el-button {
    backdrop-filter: blur(10px);

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    &.el-button--success {
      background: #10b981;
      border-color: #10b981;
      color: #ffffff;

      &:hover {
        background: #059669;
        border-color: #059669;
      }
    }
  }
}

/* ============================================================
   通用 block 标题栏
   ============================================================ */
.cdd-block-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 14px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
  font-weight: 700;
  color: #1f2937;
}

.cdd-block-badge {
  font-size: 11px;
  color: #4f46e5;
  background: #eef2ff;
  padding: 1px 8px;
  border-radius: 10px;
  font-weight: 600;

  &--warn {
    color: #b45309;
    background: #fef3c7;
  }
}

/* ============================================================
   Block 1：设备信息 - 工具栏 + 8格输入
   ============================================================ */
.cdd-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

// 联网查询组
.cdd-query-group {
  display: flex;
  align-items: center;
  gap: 0;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 2px 6px;

  &__tag {
    font-size: 10px;
    color: #6366f1;
    background: #eef2ff;
    padding: 1px 5px;
    border-radius: 3px;
    margin-right: 4px;
    font-weight: 600;
    white-space: nowrap;
  }
}

// 本地操作组
.cdd-local-group {
  display: flex;
  align-items: center;
  gap: 0;
  margin-left: 4px;
  padding-left: 8px;
  border-left: 1px solid #e5e7eb;
}

// 8格输入网格（2行 × 4列）
.cdd-info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}

.cdd-info-cell {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border-right: 1px solid #f1f5f9;

  // 每行末格无右边框
  &:nth-child(4n) { border-right: none; }

  // 第二行加上边框
  &--row2 { border-top: 1px solid #f1f5f9; }

  &__icon { font-size: 15px; flex-shrink: 0; margin-top: 18px; }
  &__body { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }
  &__label { font-size: 10px; color: #94a3b8; font-weight: 500; white-space: nowrap; }
  &__input-row { display: flex; align-items: center; gap: 4px; }
  &__number { flex: 1; }
  &__unit { font-size: 12px; color: #9ca3af; flex-shrink: 0; }
}

/* ============================================================
   Block 2 & 3：外观规格 / 问题记录 - 子区块列
   ============================================================ */
.cdd-sub-cols {
  display: grid;

  &--3 { grid-template-columns: repeat(3, 1fr); }
  &--2 { grid-template-columns: repeat(2, 1fr); }
}

.cdd-sub-section {
  padding: 10px 12px;
  border-right: 1px solid #f1f5f9;

  &:last-child { border-right: none; }

  &--done {
    background: #fafffe;
    .cdd-sub-header { color: #16a34a; }
  }
}

.cdd-sub-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.cdd-sub-done {
  font-size: 11px;
  color: #16a34a;
  font-weight: 700;
}

.cdd-sub-badge {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 10px;
  font-weight: 600;

  &--danger  { color: #ef4444; background: #fee2e2; }
  &--warning { color: #d97706; background: #fef3c7; }
}

.cdd-tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.cdd-check-tag {
  cursor: pointer;
  transition: transform 0.15s;
  &:hover { transform: translateY(-1px); }
}

/* ============================================================
   自定义质检项
   ============================================================ */
.cdd-custom-section {
  overflow: visible;
}

.cdd-custom-groups {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 10px 12px 12px;
}

.cdd-custom-group {
  padding-bottom: 10px;
  border-bottom: 1px solid #f1f5f9;

  &:last-child {
    padding-bottom: 0;
    border-bottom: none;
  }
}

.cdd-custom-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.cdd-custom-field {
  min-width: 0;

  &__label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
  }

  &__unit {
    color: #94a3b8;
    font-weight: 400;
  }

  &__number,
  &__select {
    width: 100%;
  }

  :deep(.el-radio-group),
  :deep(.el-checkbox-group) {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  :deep(.el-radio-button__inner),
  :deep(.el-checkbox-button__inner) {
    border-radius: 4px;
    border-left: 1px solid var(--el-border-color);
  }
}

/* ============================================================
   区域 3：质检结果
   ============================================================ */
.cdd-result-section { overflow: visible; }

.cdd-result-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 10px;
}

.cdd-result-card {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  overflow: hidden;

  &__header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    border-bottom: 1px solid transparent;
  }
  &__dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  }
  &__title { flex: 1; }
  &__help  { color: #9ca3af; font-size: 13px; cursor: help; }
  &__body  {
    padding: 8px 10px 0;
    margin-bottom: 0 !important;
    :deep(.el-form-item__content) { line-height: 1; }
    :deep(.el-textarea__inner) { font-size: 12px; line-height: 1.6; border-radius: 6px; }
  }

  // 卖家：蓝
  &--seller {
    border-color: #dbeafe;
    .cdd-result-card__header { color: #1d4ed8; background: linear-gradient(to right, #eff6ff, #fff); border-bottom-color: #dbeafe; }
    .cdd-result-card__dot { background: #3b82f6; }
    :deep(.el-textarea__inner) { border-color: #bfdbfe; &:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); } }
  }
  // 买家：绿
  &--buyer {
    border-color: #d1fae5;
    .cdd-result-card__header { color: #047857; background: linear-gradient(to right, #ecfdf5, #fff); border-bottom-color: #d1fae5; }
    .cdd-result-card__dot { background: #10b981; }
    :deep(.el-textarea__inner) { border-color: #a7f3d0; &:focus { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,0.1); } }
  }
}

/* ============================================================
   区域 4：定价
   ============================================================ */
.cdd-pricing-section {
  :deep(.el-form-item) { margin-bottom: 0; }
  :deep(.el-form-item__label) { font-size: 12px; font-weight: 600; color: #374151; padding-bottom: 4px; line-height: 1.4; }
}

.cdd-pricing-main {
  padding: 12px 14px 10px;
  border-bottom: 1px solid #fef3c7;
  background: linear-gradient(to right, #fffbeb, #fff);

  &__label { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  &__required { color: #ef4444; font-size: 13px; }
  &__ref { font-size: 11px; color: #9ca3af; font-weight: 400; }
  &__input { width: 200px !important; }
  &__item :deep(.el-form-item__label) { font-size: 13px; font-weight: 700; color: #92400e; }
}

.cdd-pricing-sub {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 12px;
  padding: 10px 14px;

  &__item { }
  &__item--remark { }
}

/* ============================================================
   区域 5：图片
   ============================================================ */
.cdd-photos-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.cdd-photo-block {
  &:first-child { border-right: 1px solid #e5e7eb; }

  &__header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 600;
    border-bottom: 1px solid #e5e7eb;
  }
  &__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  &__body { padding: 12px 14px; }

  &--seller {
    .cdd-photo-block__header { color: #1d4ed8; background: #f5f8ff; }
    .cdd-photo-block__dot   { background: #3b82f6; }
  }
  &--buyer {
    .cdd-photo-block__header { color: #047857; background: #f2fdf7; }
    .cdd-photo-block__dot   { background: #10b981; }
  }
}

.cdd-camera-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.cdd-camera-tip { font-size: 11px; color: #9ca3af; }

/* ============================================================
   底部操作栏
   ============================================================ */
.cdd-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__info {
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 4px 10px;
    border-radius: 4px;
  }
  &__btns { display: flex; gap: 10px; }
}

/* ============================================================
   响应式
   ============================================================ */
@media (max-width: 768px) {
  .check-device-dialog {
    :deep(.el-dialog) { width: 95vw !important; }
  }

  // Block 1 设备信息：4列 → 2列
  .cdd-info-grid {
    grid-template-columns: repeat(2, 1fr);

    .cdd-info-cell {
      // 每行第2格去掉右边框
      &:nth-child(4n) { border-right: 1px solid #f1f5f9; } // reset
      &:nth-child(2n) { border-right: none; }

      // row2 相对整体是第5-8格，都需要 border-top
      &--row2 { border-top: 1px solid #f1f5f9; }
      // 但第5、6格 (前两个 row2) 的 border-top 已有，第7、8格也有，OK
    }
  }

  // Block 2 外观规格：3列 → 1列
  .cdd-sub-cols--3 {
    grid-template-columns: 1fr;

    .cdd-sub-section {
      border-right: none;
      border-bottom: 1px solid #f1f5f9;
      &:last-child { border-bottom: none; }
    }
  }

  // Block 3 问题记录：2列 → 1列
  .cdd-sub-cols--2 {
    grid-template-columns: 1fr;

    .cdd-sub-section {
      border-right: none;
      border-bottom: 1px solid #f1f5f9;
      &:last-child { border-bottom: none; }
    }
  }

  // 工具栏换行处理
  .cdd-block-header { flex-direction: column; align-items: flex-start; }
  .cdd-toolbar { width: 100%; }
  .cdd-query-group { flex: 1; }
  .cdd-local-group { border-left: none; padding-left: 0; border-top: 1px solid #e5e7eb; padding-top: 6px; width: 100%; }

  // 结果两列 → 一列
  .cdd-result-cols { grid-template-columns: 1fr; }
  .cdd-custom-grid { grid-template-columns: 1fr; }

  // 定价副栏 → 一列
  .cdd-pricing-sub { grid-template-columns: 1fr; }
  .cdd-pricing-main__input { width: 100% !important; }

  // 图片两列 → 一列
  .cdd-photos-cols {
    grid-template-columns: 1fr;
    .cdd-photo-block:first-child { border-right: none; border-bottom: 1px solid #e5e7eb; }
  }

  // footer 竖排
  .cdd-footer {
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
    &__btns { flex-direction: column; .el-button { width: 100%; } }
  }
}
</style>
