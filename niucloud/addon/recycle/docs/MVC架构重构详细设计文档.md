# MVC架构重构详细设计文档

## 一、Service层重构设计

### 1.1 TemplateRenderService（渲染服务）

**职责：** 负责模板的HTML渲染和预览生成

**文件位置：** `app/service/admin/printer/template/TemplateRenderService.php`

**主要方法：**
```php
class TemplateRenderService extends BaseAdminService
{
    /**
     * 将JSON模板数据渲染为HTML预览
     * @param array $templateData JSON格式的模板数据
     * @param array $variables 变量数据（用于预览）
     * @return string HTML内容
     */
    public function renderToHtml(array $templateData, array $variables = []): string;
    
    /**
     * 渲染单个元素为HTML
     * @param array $element 元素数据
     * @param array $variables 变量数据
     * @return string HTML片段
     */
    private function renderElement(array $element, array $variables = []): string;
    
    /**
     * 处理变量替换
     * @param string $content 包含变量的内容
     * @param array $variables 变量数据
     * @return string 替换后的内容
     */
    private function replaceVariables(string $content, array $variables = []): string;
    
    /**
     * 计算元素在HTML中的位置和大小
     * @param array $element 元素数据
     * @return array ['x' => px, 'y' => px, 'width' => px, 'height' => px]
     */
    private function calculateElementPosition(array $element): array;
}
```

### 1.2 TemplateConverterService（转换服务）

**职责：** 负责JSON和XML格式之间的转换

**文件位置：** `app/service/admin/printer/template/TemplateConverterService.php`

**主要方法：**
```php
class TemplateConverterService extends BaseAdminService
{
    /**
     * JSON格式转换为芯烨云XML格式
     * @param array $templateData JSON格式的模板数据
     * @return string XML格式的打印指令
     */
    public function jsonToXml(array $templateData): string;
    
    /**
     * XML格式转换为JSON格式（兼容旧数据）
     * @param string $xmlContent XML格式的打印指令
     * @return array JSON格式的模板数据
     */
    public function xmlToJson(string $xmlContent): array;
    
    /**
     * 转换单个元素为XML
     * @param array $element 元素数据
     * @return string XML片段
     */
    private function convertElementToXml(array $element): string;
    
    /**
     * 转义芯烨云文本内容
     * @param string $text 原始文本
     * @return string 转义后的文本
     */
    private function escapeXinYeText(string $text): string;
    
    /**
     * 反转义芯烨云文本内容
     * @param string $text 转义后的文本
     * @return string 原始文本
     */
    private function unescapeXinYeText(string $text): string;
}
```

### 1.3 TemplateValidatorService（验证服务）

**职责：** 验证模板数据的完整性和正确性

**文件位置：** `app/service/admin/printer/template/TemplateValidatorService.php`

**主要方法：**
```php
class TemplateValidatorService extends BaseAdminService
{
    /**
     * 验证模板数据结构
     * @param array $templateData 模板数据
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateTemplate(array $templateData): array;
    
    /**
     * 验证元素数据
     * @param array $element 元素数据
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateElement(array $element): array;
    
    /**
     * 验证坐标是否在模板范围内
     * @param array $element 元素数据
     * @param int $templateWidth 模板宽度
     * @param int $templateHeight 模板高度
     * @return bool
     */
    public function validatePosition(array $element, int $templateWidth, int $templateHeight): bool;
    
    /**
     * 验证元素是否有重叠
     * @param array $elements 元素列表
     * @return array 重叠的元素ID列表
     */
    public function validateOverlap(array $elements): array;
}
```

### 1.4 TemplateLayoutService（布局服务）

**职责：** 处理布局计算，如自动换行、位置对齐等

**文件位置：** `app/service/admin/printer/template/TemplateLayoutService.php`

**主要方法：**
```php
class TemplateLayoutService extends BaseAdminService
{
    /**
     * 计算文本自动换行
     * @param string $text 文本内容
     * @param int $fontSize 字体大小（dot单位）
     * @param int $maxWidth 最大宽度（dot单位）
     * @param string $fontFamily 字体名称
     * @return array 换行后的文本数组
     */
    public function calculateTextWrap(string $text, int $fontSize, int $maxWidth, string $fontFamily = 'Arial'): array;
    
    /**
     * 计算文本渲染后的实际宽度
     * @param string $text 文本内容
     * @param int $fontSize 字体大小（dot单位）
     * @param string $fontFamily 字体名称
     * @return int 宽度（dot单位）
     */
    public function calculateTextWidth(string $text, int $fontSize, string $fontFamily = 'Arial'): int;
    
    /**
     * 计算文本渲染后的实际高度
     * @param array $lines 文本行数组
     * @param int $fontSize 字体大小（dot单位）
     * @param int $lineHeight 行高（倍率）
     * @return int 高度（dot单位）
     */
    public function calculateTextHeight(array $lines, int $fontSize, int $lineHeight = 1.2): int;
    
    /**
     * 对齐元素位置
     * @param array $element 元素数据
     * @param string $align 对齐方式（left/center/right）
     * @param int $containerWidth 容器宽度
     * @return array 对齐后的元素数据
     */
    public function alignElement(array $element, string $align, int $containerWidth): array;
    
    /**
     * 检测元素是否超出模板边界
     * @param array $element 元素数据
     * @param int $templateWidth 模板宽度
     * @param int $templateHeight 模板高度
     * @return bool
     */
    public function isOutOfBounds(array $element, int $templateWidth, int $templateHeight): bool;
}
```

## 二、Controller层重构设计

### 2.1 精简后的Controller

**文件位置：** `app/adminapi/controller/printer/PrinterTemplate.php`

**职责：**
- 接收HTTP请求
- 参数验证
- 调用Service层方法
- 返回响应

**主要方法：**
```php
class PrinterTemplate extends BaseAdminController
{
    protected $templateService;
    protected $renderService;
    protected $converterService;
    protected $validatorService;
    
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->templateService = new RecyclePrinterTemplateService();
        $this->renderService = new TemplateRenderService();
        $this->converterService = new TemplateConverterService();
        $this->validatorService = new TemplateValidatorService();
    }
    
    // 列表、详情、添加、编辑、删除等方法
    // 每个方法只负责：接收参数 -> 验证 -> 调用Service -> 返回结果
}
```

## 三、前端可视化编辑器设计

### 3.1 Store设计（Pinia）

**文件位置：** `admin/src/addon/recycle/views/printer_template/visual-editor/stores/templateStore.ts`

```typescript
import { defineStore } from 'pinia'

interface TemplateState {
  template: Template
  selectedElementId: string | null
  zoom: number
  gridVisible: boolean
  snapToGrid: boolean
}

export const useTemplateStore = defineStore('template', {
  state: (): TemplateState => ({
    template: {
      width: 464,  // 58mm * 8dot/mm
      height: 320, // 40mm * 8dot/mm
      size: '58mm',
      elements: []
    },
    selectedElementId: null,
    zoom: 1,
    gridVisible: true,
    snapToGrid: true
  }),
  
  getters: {
    selectedElement: (state) => 
      state.template.elements.find(el => el.id === state.selectedElementId),
    
    elementsByZIndex: (state) => 
      [...state.template.elements].sort((a, b) => (a.zIndex || 0) - (b.zIndex || 0))
  },
  
  actions: {
    addElement(element: TemplateElement) { },
    removeElement(elementId: string) { },
    updateElement(elementId: string, data: Partial<TemplateElement>) { },
    selectElement(elementId: string | null) { },
    moveElement(elementId: string, x: number, y: number) { },
    resizeElement(elementId: string, width: number, height: number) { }
  }
})
```

### 3.2 Canvas组件设计

**文件位置：** `admin/src/addon/recycle/views/printer_template/visual-editor/components/Canvas.vue`

**功能：**
- 渲染模板画布
- 支持拖拽元素
- 支持选中元素
- 支持调整元素大小
- 显示网格
- 支持缩放

### 3.3 ElementToolbar组件设计

**文件位置：** `admin/src/addon/recycle/views/printer_template/visual-editor/components/ElementToolbar.vue`

**功能：**
- 提供元素类型选择（文本、二维码、条形码等）
- 插入新元素到画布
- 快速样式设置

### 3.4 PropertyPanel组件设计

**文件位置：** `admin/src/addon/recycle/views/printer_template/visual-editor/components/PropertyPanel.vue`

**功能：**
- 显示选中元素的属性
- 编辑元素属性（位置、大小、样式等）
- 变量选择器
- 字体设置（大小、颜色、对齐）

## 四、实施步骤

### Step 1: 创建Service层文件结构

1. 创建目录：`app/service/admin/printer/template/`
2. 创建Service文件：
   - TemplateRenderService.php
   - TemplateConverterService.php
   - TemplateValidatorService.php
   - TemplateLayoutService.php

### Step 2: 重构现有Service

1. 将RecyclePrinterTemplateService中的方法拆分到对应的Service中
2. 保留RecyclePrinterTemplateService作为基础CRUD服务

### Step 3: 重构Controller

1. 精简Controller方法
2. 调用新的Service层方法

### Step 4: 创建前端组件

1. 创建visual-editor目录结构
2. 实现Store
3. 实现Canvas组件
4. 实现ElementToolbar组件
5. 实现PropertyPanel组件

### Step 5: 实现核心功能

1. 拖拽定位
2. 属性编辑
3. 自动换行
4. 实时预览

### Step 6: 测试和优化

1. 单元测试
2. 集成测试
3. 打印测试
4. 性能优化

