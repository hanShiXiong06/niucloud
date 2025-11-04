<template>
  <div class="visual-editor-container">
    <!-- 顶部工具栏 -->
    <div
      class="editor-toolbar bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between"
    >
      <div class="flex items-center space-x-4">
        <el-button @click="goBack" icon="ArrowLeft" size="small">
          返回
        </el-button>
        <el-divider direction="vertical" />
        <h2 class="text-lg font-medium">
          {{ isEdit ? "编辑模板" : "新建模板" }}
        </h2>
        <el-tag v-if="templateInfo.template_name" type="info" size="small">
          {{ templateInfo.template_name }}
        </el-tag>
      </div>

      <div class="flex items-center space-x-2">
        <!-- 纸张尺寸选择 -->
        <el-select
          v-model="paperSize"
          @change="handlePaperSizeChange"
          size="small"
          style="width: 150px"
        >
          <el-option label="自定义" value="custom" />
          <el-option
            v-for="(size, key) in paperSizePresets"
            :key="key"
            :label="size.name"
            :value="key"
          />
        </el-select>

        <!-- 自定义尺寸（当选择自定义时显示） -->
        <template v-if="paperSize === 'custom'">
          <el-input-number
            v-model="customWidth"
            :min="20"
            :max="200"
            size="small"
            style="width: 100px"
          />
          <span class="text-gray-500 mx-1">×</span>
          <el-input-number
            v-model="customHeight"
            :min="20"
            :max="200"
            size="small"
            style="width: 100px"
          />
          <span class="text-gray-500 ml-1">mm</span>
        </template>

        <el-button
          @click="previewTemplate"
          icon="View"
          size="small"
          :loading="previewing"
        >
          预览
        </el-button>
        <el-button
          v-if="isEdit"
          @click="testPrint"
          icon="Printer"
          size="small"
          type="warning"
          :loading="testing"
        >
          测试打印
        </el-button>
        <el-button
          @click="showSaveDialog"
          icon="Document"
          size="small"
          type="primary"
          :loading="saving"
        >
          保存模板
        </el-button>
      </div>
    </div>

    <!-- 主编辑区域 -->
    <div class="editor-content flex h-full">
      <!-- 左侧工具栏 -->
      <div
        class="toolbar-panel bg-white border-r border-gray-200 w-64 p-4 overflow-y-auto"
      >
        <h3 class="text-sm font-medium text-gray-700 mb-3">元素工具</h3>

        <!-- 文本元素 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('text')"
            class="w-full justify-start"
            size="small"
            icon="Document"
          >
            文本
          </el-button>
        </div>

        <!-- 二维码 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('qrcode')"
            class="w-full justify-start"
            size="small"
            icon="Grid"
          >
            二维码
          </el-button>
        </div>

        <!-- 条形码 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('barcode')"
            class="w-full justify-start"
            size="small"
            icon="Sort"
          >
            条形码
          </el-button>
        </div>

        <!-- 线条 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('line')"
            class="w-full justify-start"
            size="small"
            icon="Minus"
          >
            线条
          </el-button>
        </div>

        <!-- 矩形 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('rectangle')"
            class="w-full justify-start"
            size="small"
            icon="FullScreen"
          >
            矩形
          </el-button>
        </div>

        <!-- 图片 -->
        <div class="tool-item mb-2">
          <el-button
            @click="addElement('image')"
            class="w-full justify-start"
            size="small"
            icon="Picture"
          >
            图片
          </el-button>
        </div>

        <el-divider class="my-4" />

        <!-- 变量选择器 -->
        <div class="variables-section">
          <h4 class="text-xs font-medium text-gray-600 mb-2">订单变量</h4>
          <div class="variable-group mb-3">
            <div class="text-xs text-gray-500 mb-1">订单信息</div>
            <div class="space-y-1">
              <el-button
                v-for="varItem in orderVariables"
                :key="varItem.key"
                @click="insertVariable(varItem.key)"
                size="small"
                class="w-full justify-start text-xs"
                type="text"
              >
                {{ varItem.label }}
              </el-button>
            </div>
          </div>

          <div class="variable-group mb-3">
            <div class="text-xs text-gray-500 mb-1">设备信息</div>
            <div class="space-y-1">
              <el-button
                v-for="varItem in deviceVariables"
                :key="varItem.key"
                @click="insertVariable(varItem.key)"
                size="small"
                class="w-full justify-start text-xs"
                type="text"
              >
                {{ varItem.label }}
              </el-button>
            </div>
          </div>

          <div class="variable-group mb-3">
            <div class="text-xs text-gray-500 mb-1">价格信息</div>
            <div class="space-y-1">
              <el-button
                v-for="varItem in priceVariables"
                :key="varItem.key"
                @click="insertVariable(varItem.key)"
                size="small"
                class="w-full justify-start text-xs"
                type="text"
              >
                {{ varItem.label }}
              </el-button>
            </div>
          </div>
        </div>
      </div>

      <!-- 中间画布区域 -->
      <div class="canvas-panel flex-1 flex flex-col bg-gray-50">
        <div
          class="canvas-container flex-1 overflow-auto p-6 flex items-center justify-center"
        >
          <div
            ref="canvasRef"
            class="canvas-area bg-white shadow-lg relative"
            :style="canvasStyle"
            @click="handleCanvasClick"
          >
            <!-- 网格背景 -->
            <div
              class="grid-background absolute inset-0"
              :style="gridStyle"
            ></div>

            <!-- 画布元素 -->
            <div
              v-for="(element, index) in templateData.elements"
              :key="index"
              :class="[
                'element-item',
                { selected: selectedElementIndex === index },
              ]"
              :style="getElementStyle(element)"
              @click.stop="selectElement(index)"
              @mousedown="startDrag(index, $event)"
            >
              <!-- 文本元素 -->
              <template v-if="element.type === 'text'">
                <div class="element-content">
                  {{ element.content || "文本" }}
                </div>
              </template>

              <!-- 二维码元素 -->
              <template v-else-if="element.type === 'qrcode'">
                <div class="element-content qrcode-placeholder">
                  <div class="qr-icon">QR</div>
                </div>
              </template>

              <!-- 条形码元素 -->
              <template v-else-if="element.type === 'barcode'">
                <div class="element-content barcode-placeholder">
                  <div class="barcode-icon">BAR</div>
                </div>
              </template>

              <!-- 线条元素 -->
              <template v-else-if="element.type === 'line'">
                <div
                  class="element-content line-element"
                  :style="getLineStyle(element)"
                ></div>
              </template>

              <!-- 矩形元素 -->
              <template v-else-if="element.type === 'rectangle'">
                <div
                  class="element-content rectangle-element"
                  :style="getRectangleStyle(element)"
                ></div>
              </template>

              <!-- 图片元素 -->
              <template v-else-if="element.type === 'image'">
                <div class="element-content image-placeholder">
                  <el-icon><Picture /></el-icon>
                </div>
              </template>

              <!-- 选中时的控制点 -->
              <template v-if="selectedElementIndex === index">
                <div
                  class="resize-handle resize-handle-nw"
                  @mousedown.stop="startResize(index, 'nw', $event)"
                ></div>
                <div
                  class="resize-handle resize-handle-ne"
                  @mousedown.stop="startResize(index, 'ne', $event)"
                ></div>
                <div
                  class="resize-handle resize-handle-sw"
                  @mousedown.stop="startResize(index, 'sw', $event)"
                ></div>
                <div
                  class="resize-handle resize-handle-se"
                  @mousedown.stop="startResize(index, 'se', $event)"
                ></div>
              </template>
            </div>
          </div>
        </div>

        <!-- 画布信息 -->
        <div
          class="canvas-info bg-white border-t border-gray-200 px-4 py-2 text-xs text-gray-500"
        >
          尺寸: {{ templateData.width }}mm × {{ templateData.height }}mm |
          元素数量: {{ templateData.elements?.length || 0 }} | 缩放:
          {{ (scale * 100).toFixed(0) }}%
        </div>
      </div>

      <!-- 右侧属性面板 -->
      <div
        class="property-panel bg-white border-l border-gray-200 w-80 p-4 overflow-y-auto"
      >
        <h3 class="text-sm font-medium text-gray-700 mb-4">属性设置</h3>

        <div
          v-if="selectedElementIndex === null"
          class="text-center text-gray-400 py-8"
        >
          <p>请选择一个元素</p>
        </div>

        <template v-else>
          <el-form :model="selectedElement" label-width="80px" size="small">
            <!-- 位置和尺寸 -->
            <el-form-item label="位置 X">
              <el-input-number
                v-model="selectedElement.x"
                :min="0"
                :step="1"
                @change="updateElement"
              />
            </el-form-item>

            <el-form-item label="位置 Y">
              <el-input-number
                v-model="selectedElement.y"
                :min="0"
                :step="1"
                @update:model-value="updateElement"
              />
            </el-form-item>

            <!-- 文本元素属性 -->
            <template v-if="selectedElement.type === 'text'">
              <el-form-item label="内容">
                <el-input
                  v-model="selectedElement.content"
                  type="textarea"
                  :rows="3"
                  @change="updateElement"
                  placeholder="输入文本或变量，如：{{order_no}}"
                />
              </el-form-item>

              <el-form-item label="字体">
                <el-select
                  v-model="selectedElement.font"
                  @change="updateElement"
                >
                  <el-option label="字体1 (8x12)" :value="1" />
                  <el-option label="字体2 (12x20)" :value="2" />
                  <el-option label="字体3 (16x24)" :value="3" />
                  <el-option label="字体4 (24x32)" :value="4" />
                  <el-option label="字体5 (32x48)" :value="5" />
                  <el-option label="字体6 (OCR-B 14x19)" :value="6" />
                  <el-option label="字体7 (OCR-B 21x27)" :value="7" />
                  <el-option label="字体8 (OCR-A 14x25)" :value="8" />
                  <el-option label="字体9 (中文 24x24)" :value="9" />
                </el-select>
              </el-form-item>

              <el-form-item label="宽度倍率">
                <el-input-number
                  v-model="selectedElement.width_scale"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="高度倍率">
                <el-input-number
                  v-model="selectedElement.height_scale"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="旋转">
                <el-select
                  v-model="selectedElement.rotation"
                  @change="updateElement"
                >
                  <el-option label="0°" :value="0" />
                  <el-option label="90°" :value="90" />
                  <el-option label="180°" :value="180" />
                  <el-option label="270°" :value="270" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 二维码元素属性 -->
            <template v-else-if="selectedElement.type === 'qrcode'">
              <el-form-item label="内容">
                <el-input
                  v-model="selectedElement.content"
                  @change="updateElement"
                  placeholder="输入文本或变量，如：{{order_no}}"
                />
              </el-form-item>

              <el-form-item label="大小">
                <el-input-number
                  v-model="selectedElement.size"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="纠错等级">
                <el-select
                  v-model="selectedElement.error_level"
                  @change="updateElement"
                >
                  <el-option label="L (低)" value="L" />
                  <el-option label="M (中)" value="M" />
                  <el-option label="Q (中高)" value="Q" />
                  <el-option label="H (高)" value="H" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 条形码元素属性 -->
            <template v-else-if="selectedElement.type === 'barcode'">
              <el-form-item label="内容">
                <el-input
                  v-model="selectedElement.content"
                  @change="updateElement"
                  placeholder="输入文本或变量"
                />
              </el-form-item>

              <el-form-item label="类型">
                <el-select
                  v-model="selectedElement.barcode_type"
                  @change="updateElement"
                >
                  <el-option label="Code128" value="BC128" />
                  <el-option label="Code39" value="BC39" />
                </el-select>
              </el-form-item>

              <el-form-item label="高度">
                <el-input-number
                  v-model="selectedElement.height"
                  :min="20"
                  :max="200"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="窄条宽度">
                <el-input-number
                  v-model="selectedElement.narrow_width"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="宽条宽度">
                <el-input-number
                  v-model="selectedElement.wide_width"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="旋转">
                <el-select
                  v-model="selectedElement.rotation"
                  @change="updateElement"
                >
                  <el-option label="0°" :value="0" />
                  <el-option label="90°" :value="90" />
                  <el-option label="180°" :value="180" />
                  <el-option label="270°" :value="270" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 线条元素属性 -->
            <template v-else-if="selectedElement.type === 'line'">
              <el-form-item label="宽度">
                <el-input-number
                  v-model="selectedElement.width"
                  :min="1"
                  :max="500"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="高度">
                <el-input-number
                  v-model="selectedElement.height"
                  :min="1"
                  :max="500"
                  @change="updateElement"
                />
              </el-form-item>
            </template>

            <!-- 矩形元素属性 -->
            <template v-else-if="selectedElement.type === 'rectangle'">
              <el-form-item label="结束 X">
                <el-input-number
                  v-model="selectedElement.xe"
                  :min="0"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="结束 Y">
                <el-input-number
                  v-model="selectedElement.ye"
                  :min="0"
                  @change="updateElement"
                />
              </el-form-item>

              <el-form-item label="线宽">
                <el-input-number
                  v-model="selectedElement.style"
                  :min="1"
                  :max="10"
                  @change="updateElement"
                />
              </el-form-item>
            </template>

            <!-- 图片元素属性 -->
            <template v-else-if="selectedElement.type === 'image'">
              <el-form-item label="宽度">
                <el-input-number
                  v-model="selectedElement.width"
                  :min="20"
                  :max="100"
                  @change="updateElement"
                />
              </el-form-item>
            </template>

            <!-- 删除按钮 -->
            <el-form-item>
              <el-button
                type="danger"
                @click="deleteElement"
                size="small"
                class="w-full"
                >删除元素</el-button
              >
            </el-form-item>
          </el-form>
        </template>
      </div>
    </div>

    <!-- 保存对话框 -->
    <el-dialog v-model="saveDialogVisible" title="保存模板" width="400px">
      <el-form :model="templateInfo" label-width="80px">
        <el-form-item label="模板名称" required>
          <el-input
            v-model="templateInfo.template_name"
            placeholder="请输入模板名称"
          />
        </el-form-item>
        <el-form-item label="设为默认">
          <el-switch v-model="templateInfo.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="saveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveTemplate" :loading="saving"
          >保存</el-button
        >
      </template>
    </el-dialog>

    <!-- 预览对话框 -->
    <el-dialog v-model="previewDialogVisible" title="模板预览" width="800px">
      <div class="preview-container bg-gray-100 p-4 rounded">
        <div
          v-if="previewContent"
          v-html="previewContent"
          class="preview-result"
        ></div>
        <div v-else class="text-center text-gray-500 py-8">暂无预览内容</div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import { ElMessage } from "element-plus";
import { Picture } from "@element-plus/icons-vue";
import {
  getTemplateInfo,
  addTemplate,
  editTemplate,
  previewTemplate as previewTemplateAPI,
  testPrintTemplate,
  renderTemplate,
} from "../../api/printer_template";

const router = useRouter();
const route = useRoute();

// 纸张尺寸预设
const paperSizePresets = {
  "40x30": { width: 40, height: 30, name: "40mm × 30mm" },
  "50x30": { width: 50, height: 30, name: "50mm × 30mm" },
  "58x40": { width: 58, height: 40, name: "58mm × 40mm" },
  "60x45": { width: 60, height: 45, name: "60mm × 45mm" },
  "80x50": { width: 80, height: 50, name: "80mm × 50mm" },
  "100x50": { width: 100, height: 50, name: "100mm × 50mm" },
};

// 订单和设备变量
const orderVariables = [
  { key: "order_no", label: "订单号" },
  { key: "order_id", label: "订单ID" },
  { key: "contact_name", label: "联系人" },
  { key: "contact_mobile", label: "联系电话" },
  { key: "create_time", label: "创建时间" },
];

const deviceVariables = [
  { key: "device_id", label: "设备ID" },
  { key: "imei", label: "IMEI" },
  { key: "model", label: "型号" },
  { key: "category_name", label: "分类" },
  { key: "brand", label: "品牌" },
  { key: "color", label: "颜色" },
  { key: "memory", label: "内存" },
  { key: "capacity", label: "容量" },
  { key: "check_result", label: "质检结果" },
];

const priceVariables = [
  { key: "initial_price", label: "初始价格" },
  { key: "final_price", label: "最终价格" },
  { key: "price", label: "价格" },
];

// 响应式数据
const templateId = ref(
  (route.params.id as string) || (route.query.id as string) || ""
);
const isEdit = computed(() => {
  const id = (route.params.id as string) || (route.query.id as string) || "";
  return !!id && id !== "new" && id !== "";
});
const templateInfo = ref({
  template_name: "",
  is_default: false,
});

const templateData = ref({
  width: 58,
  height: 40,
  elements: [] as any[],
});

const paperSize = ref("58x40");
const customWidth = ref(58);
const customHeight = ref(40);
const selectedElementIndex = ref<number | null>(null);
const scale = ref(1.0);
const canvasRef = ref<HTMLElement | null>(null);

const saving = ref(false);
const previewing = ref(false);
const testing = ref(false);
const saveDialogVisible = ref(false);
const previewDialogVisible = ref(false);
const previewContent = ref("");

// 拖拽相关
const isDragging = ref(false);
const dragStartPos = ref({ x: 0, y: 0 });
const dragElementIndex = ref<number | null>(null);

// 计算属性
const selectedElement = computed(() => {
  if (selectedElementIndex.value === null) return null;
  return templateData.value.elements[selectedElementIndex.value];
});

const canvasStyle = computed(() => {
  const widthMm =
    paperSize.value === "custom"
      ? customWidth.value
      : paperSizePresets[paperSize.value]?.width || 58;
  const heightMm =
    paperSize.value === "custom"
      ? customHeight.value
      : paperSizePresets[paperSize.value]?.height || 40;

  // 1mm = 8dot = 4px (用于显示)
  const widthPx = widthMm * 4 * scale.value;
  const heightPx = heightMm * 4 * scale.value;

  return {
    width: `${widthPx}px`,
    height: `${heightPx}px`,
    minWidth: `${widthPx}px`,
    minHeight: `${heightPx}px`,
  };
});

const gridStyle = computed(() => {
  const gridSize = 4 * scale.value; // 1mm = 4px
  return {
    backgroundImage: `linear-gradient(to right, #e5e7eb 1px, transparent 1px), linear-gradient(to bottom, #e5e7eb 1px, transparent 1px)`,
    backgroundSize: `${gridSize}px ${gridSize}px`,
    opacity: 0.3,
  };
});

// 方法
const handlePaperSizeChange = () => {
  if (paperSize.value === "custom") {
    templateData.value.width = customWidth.value;
    templateData.value.height = customHeight.value;
  } else {
    const preset = paperSizePresets[paperSize.value];
    if (preset) {
      templateData.value.width = preset.width;
      templateData.value.height = preset.height;
    }
  }
};

const addElement = (type: string) => {
  const newElement: any = {
    type,
    x: 10,
    y: 10,
  };

  switch (type) {
    case "text":
      newElement.content = "文本内容";
      newElement.font = 9;
      newElement.width_scale = 1;
      newElement.height_scale = 1;
      newElement.rotation = 0;
      break;
    case "qrcode":
      newElement.content = "{{order_no}}";
      newElement.size = 2;
      newElement.error_level = "L";
      break;
    case "barcode":
      newElement.content = "{{imei}}";
      newElement.barcode_type = "BC128";
      newElement.height = 60;
      newElement.narrow_width = 1;
      newElement.wide_width = 1;
      newElement.rotation = 0;
      break;
    case "line":
      newElement.width = 100;
      newElement.height = 2;
      break;
    case "rectangle":
      newElement.xe = 100;
      newElement.ye = 50;
      newElement.style = 4;
      break;
    case "image":
      newElement.width = 50;
      break;
  }

  templateData.value.elements.push(newElement);
  selectedElementIndex.value = templateData.value.elements.length - 1;
};

const selectElement = (index: number) => {
  selectedElementIndex.value = index;
};

const deleteElement = () => {
  if (selectedElementIndex.value !== null) {
    templateData.value.elements.splice(selectedElementIndex.value, 1);
    selectedElementIndex.value = null;
  }
};

const updateElement = () => {
  // 元素已通过v-model自动更新
  nextTick(() => {
    // 可以在这里触发预览更新
  });
};

const insertVariable = (varKey: string) => {
  if (
    selectedElement.value &&
    (selectedElement.value.type === "text" ||
      selectedElement.value.type === "qrcode" ||
      selectedElement.value.type === "barcode")
  ) {
    const currentContent = selectedElement.value.content || "";
    selectedElement.value.content = currentContent + `{{${varKey}}}`;
    updateElement();
  } else {
    // 如果没有选中元素，添加一个文本元素
    addElement("text");
    nextTick(() => {
      if (selectedElement.value) {
        selectedElement.value.content = `{{${varKey}}}`;
        updateElement();
      }
    });
  }
};

const getElementStyle = (element: any) => {
  // 将dot单位转换为px (1dot = 0.5px, 1mm = 8dot = 4px)
  const x = (element.x / 8) * 4 * scale.value;
  const y = (element.y / 8) * 4 * scale.value;
  const rotation = element.rotation || 0;

  const style: any = {
    position: "absolute",
    left: `${x}px`,
    top: `${y}px`,
    cursor: "move",
    transformOrigin: "left top",
  };

  // 如果有旋转角度，应用旋转
  if (rotation !== 0) {
    style.transform = `rotate(${rotation}deg)`;
  }

  return style;
};

const getLineStyle = (element: any) => {
  const width = (element.width / 8) * 4 * scale.value;
  const height = (element.height / 8) * 4 * scale.value;
  return {
    width: `${width}px`,
    height: `${height}px`,
    backgroundColor: "#000",
  };
};

const getRectangleStyle = (element: any) => {
  const x = (element.x / 8) * 4 * scale.value;
  const y = (element.y / 8) * 4 * scale.value;
  const xe = (element.xe / 8) * 4 * scale.value;
  const ye = (element.ye / 8) * 4 * scale.value;

  return {
    position: "absolute",
    left: "0",
    top: "0",
    width: `${xe - x}px`,
    height: `${ye - y}px`,
    border: `${((element.style || 4) / 8) * 4 * scale.value}px solid #000`,
  };
};

const handleCanvasClick = (e: MouseEvent) => {
  if (e.target === canvasRef.value) {
    selectedElementIndex.value = null;
  }
};

const startDrag = (index: number, e: MouseEvent) => {
  isDragging.value = true;
  dragElementIndex.value = index;
  dragStartPos.value = {
    x: e.clientX,
    y: e.clientY,
  };

  const element = templateData.value.elements[index];
  const startX = element.x;
  const startY = element.y;

  const handleMouseMove = (moveEvent: MouseEvent) => {
    if (!isDragging.value || dragElementIndex.value === null) return;

    const deltaX = moveEvent.clientX - dragStartPos.value.x;
    const deltaY = moveEvent.clientY - dragStartPos.value.y;

    // 将px转换为dot (1px = 2dot when scale=1, 1mm = 4px = 8dot)
    const deltaDotX = (deltaX / scale.value) * 2;
    const deltaDotY = (deltaY / scale.value) * 2;

    templateData.value.elements[dragElementIndex.value].x = Math.max(
      0,
      startX + deltaDotX
    );
    templateData.value.elements[dragElementIndex.value].y = Math.max(
      0,
      startY + deltaDotY
    );
  };

  const handleMouseUp = () => {
    isDragging.value = false;
    dragElementIndex.value = null;
    document.removeEventListener("mousemove", handleMouseMove);
    document.removeEventListener("mouseup", handleMouseUp);
  };

  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);
};

const startResize = (index: number, direction: string, e: MouseEvent) => {
  e.stopPropagation();
  // TODO: 实现调整大小功能
};

const previewTemplate = async () => {
  try {
    previewing.value = true;

    // 如果有template_id，使用预览API；否则使用渲染API
    if (isEdit.value && templateId.value) {
      const res = await previewTemplateAPI(Number(templateId.value));

      if (res.code === 1) {
        previewContent.value = res.data?.html || res.data?.html_content || "";
        if (!previewContent.value) {
          console.warn("预览内容为空", res.data);
          ElMessage.warning("预览内容为空，请检查模板数据");
        }
        previewDialogVisible.value = true;
      } else {
        ElMessage.error(res.msg || "预览失败");
        console.error("预览失败", res);
      }
    } else {
      // 新建模板，使用渲染API
      console.log("渲染模板数据:", templateData.value);
      const res = await renderTemplate(templateData.value, scale.value);

      if (res.code === 1) {
        previewContent.value = res.data?.html || "";
        if (!previewContent.value) {
          console.warn("渲染内容为空", res.data);
          ElMessage.warning("预览内容为空，请检查模板数据");
        }
        previewDialogVisible.value = true;
      } else {
        ElMessage.error(res.msg || "预览失败");
        console.error("渲染失败", res);
      }
    }
  } catch (error) {
    console.error("Preview error:", error);
    ElMessage.error("预览失败: " + (error as Error).message);
  } finally {
    previewing.value = false;
  }
};

const testPrint = async () => {
  // 如果是新建模板，需要先保存
  if (!isEdit.value) {
    ElMessage.warning("请先保存模板后再进行测试打印");
    return;
  }

  try {
    testing.value = true;

    // 调用测试打印API
    const res = await testPrintTemplate(Number(templateId.value), {
      template_data: templateData.value,
    });

    if (res.code === 1) {
      ElMessage.success("测试打印成功");
    } else {
      ElMessage.error(res.msg || "测试打印失败");
    }
  } catch (error) {
    console.error("Test print error:", error);
    ElMessage.error("测试打印失败");
  } finally {
    testing.value = false;
  }
};

const showSaveDialog = () => {
  saveDialogVisible.value = true;
};

const saveTemplate = async () => {
  if (!templateInfo.value.template_name) {
    ElMessage.warning("请输入模板名称");
    return;
  }

  try {
    saving.value = true;

    // 确保模板数据包含尺寸信息
    const templateDataToSave = {
      ...templateData.value,
      width:
        paperSize.value === "custom"
          ? customWidth.value
          : paperSizePresets[paperSize.value]?.width || 58,
      height:
        paperSize.value === "custom"
          ? customHeight.value
          : paperSizePresets[paperSize.value]?.height || 40,
    };

    // 转换为JSON字符串存储
    const templateJson = JSON.stringify(templateDataToSave);

    const saveData = {
      template_name: templateInfo.value.template_name,
      is_default: templateInfo.value.is_default ? 1 : 0,
      template_data: templateJson,
      template_type: "device_label", // 默认类型
      width: templateDataToSave.width,
      height: templateDataToSave.height,
      content: templateJson, // 兼容旧格式
    };

    if (isEdit.value) {
      const res = await editTemplate(Number(templateId.value), saveData);

      if (res.code === 1) {
        saveDialogVisible.value = false;
      } else {
        ElMessage.error(res.msg || "保存失败");
      }
    } else {
      const res = await addTemplate(saveData);

      if (res.code === 1) {
        ElMessage.success("保存成功");
        saveDialogVisible.value = false;

        // 如果是新建，跳转到编辑页面
        if (res.data?.id) {
          // 获取当前路由前缀（site/admin等）
          const currentPath = route.path;
          const pathPrefix = currentPath.split("/recycle")[0]; // 获取 /site 或 /admin 等前缀
          router.replace(
            `${pathPrefix}/recycle/printer_template/edit/${res.data.id}`
          );
        } else {
          const currentPath = route.path;
          const pathPrefix = currentPath.split("/recycle")[0];
          router.push(`${pathPrefix}/recycle/printer_template/list`);
        }
      } else {
        ElMessage.error(res.msg || "保存失败");
      }
    }
  } catch (error) {
    console.error("Save error:", error);
    ElMessage.error("保存失败");
  } finally {
    saving.value = false;
  }
};

const goBack = () => {
  router.back();
};

const loadTemplate = async () => {
  if (!isEdit.value) return;

  try {
    const res = await getTemplateInfo(Number(templateId.value));
    if (res.code === 1 && res.data) {
      templateInfo.value = {
        template_name: res.data.template_name || "",
        is_default: res.data.is_default === 1,
      };

      // 解析模板数据
      if (res.data.template_data || res.data.content) {
        const dataStr = res.data.template_data || res.data.content;
        const data =
          typeof dataStr === "string" ? JSON.parse(dataStr) : dataStr;

        templateData.value = data;

        // 设置纸张尺寸
        if (data.width && data.height) {
          const sizeKey = Object.keys(paperSizePresets).find((key) => {
            const preset = paperSizePresets[key];
            return preset.width === data.width && preset.height === data.height;
          });

          if (sizeKey) {
            paperSize.value = sizeKey;
          } else {
            paperSize.value = "custom";
            customWidth.value = data.width;
            customHeight.value = data.height;
          }
        }
      }
    }
  } catch (error) {
    console.error("Load template error:", error);
    ElMessage.error("加载模板失败");
  }
};

onMounted(() => {
  loadTemplate();
});
</script>

<style scoped>
.visual-editor-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f5f5f5;
}

.editor-toolbar {
  flex-shrink: 0;
}

.editor-content {
  flex: 1;
  overflow: hidden;
}

.toolbar-panel {
  flex-shrink: 0;
}

.canvas-panel {
  overflow: hidden;
}

.canvas-area {
  border: 1px solid #ddd;
}

.element-item {
  user-select: none;
}

.element-item.selected {
  outline: 2px solid #409eff;
  outline-offset: -2px;
}

.resize-handle {
  position: absolute;
  width: 8px;
  height: 8px;
  background: #409eff;
  border: 1px solid #fff;
  border-radius: 50%;
}

.resize-handle-nw {
  top: -4px;
  left: -4px;
  cursor: nw-resize;
}

.resize-handle-ne {
  top: -4px;
  right: -4px;
  cursor: ne-resize;
}

.resize-handle-sw {
  bottom: -4px;
  left: -4px;
  cursor: sw-resize;
}

.resize-handle-se {
  bottom: -4px;
  right: -4px;
  cursor: se-resize;
}

.element-content {
  min-width: 20px;
  min-height: 20px;
}

.qrcode-placeholder,
.barcode-placeholder {
  border: 1px dashed #999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9f9f9;
}

.line-element {
  background: #000;
}

.rectangle-element {
  border: 1px solid #000;
  background: transparent;
}

.image-placeholder {
  border: 1px dashed #999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9f9f9;
}

.preview-container {
  max-height: 70vh;
  overflow: auto;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 20px;
}

.preview-result {
  width: 100%;
  min-height: 200px;
}

/* 确保预览中的元素能正确显示旋转 */
.preview-result :deep(.label-preview-container) {
  width: 100%;
}

.preview-result :deep(.label-preview) {
  display: inline-block;
}

.preview-result :deep([style*="transform"]) {
  transform-origin: left top !important;
}
</style>
