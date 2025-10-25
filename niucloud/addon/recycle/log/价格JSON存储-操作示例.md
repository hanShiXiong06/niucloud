# 价格JSON存储 - 操作示例

## JSON格式设计

### 标准价格JSON格式
```json
{
  "高保充新": 7000,
  "充新": 6900,
  "靓机": 6500,
  "小花": 6000,
  "大花": 5500,
  "外爆": 5000,
  "内爆": 4500
}
```

## PHP操作示例

### 1. 构建价格JSON
```php
/**
 * 从Excel行数据构建价格JSON
 */
public function buildPriceJson($excelRowData)
{
    $priceFields = ['高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆'];
    $prices = [];
    
    foreach ($priceFields as $field) {
        $value = $excelRowData[$field] ?? '';
        
        // 清理价格数据
        $cleanPrice = $this->cleanPrice($value);
        
        if ($cleanPrice !== null) {
            $prices[$field] = $cleanPrice;
        }
    }
    
    // 转换为JSON字符串
    $priceJson = json_encode($prices, JSON_UNESCAPED_UNICODE);
    
    return [
        'price_array' => $prices,
        'price_json' => $priceJson
    ];
}

/**
 * 清理价格数据
 */
private function cleanPrice($price)
{
    // 移除空格和特殊字符
    $price = trim(str_replace(['￥', '元', ',', ' '], '', $price));
    
    // 处理 "/" 或空值
    if (empty($price) || $price === '/' || $price === '-') {
        return null;
    }
    
    // 验证是否为有效数字
    if (is_numeric($price)) {
        return (float)$price;
    }
    
    return null;
}
```

### 2. 保存价格到数据库
```php
/**
 * 保存设备价格
 */
public function saveDevicePrice($deviceId, $priceJson, $priceDate, $importBatch)
{
    // 检查今日是否已有价格
    $existingPrice = $this->priceModel
        ->where('site_id', $this->site_id)
        ->where('device_model_id', $deviceId)
        ->where('price_date', $priceDate)
        ->find();
    
    if ($existingPrice) {
        // 更新现有价格
        $updateData = [
            'price_data' => $priceJson,
            'import_batch' => $importBatch,
            'update_at' => time()
        ];
        
        $result = $this->priceModel->where('id', $existingPrice['id'])->update($updateData);
        
        $this->logService->info('价格更新成功', [
            'price_id' => $existingPrice['id'],
            'device_id' => $deviceId,
            'old_price' => $existingPrice['price_data'],
            'new_price' => $priceJson
        ]);
        
    } else {
        // 创建新价格记录
        $newPrice = [
            'site_id' => $this->site_id,
            'device_model_id' => $deviceId,
            'import_batch' => $importBatch,
            'price_data' => $priceJson,
            'price_date' => $priceDate,
            'is_current' => 1,
            'create_at' => time(),
            'update_at' => time()
        ];
        
        $result = $this->priceModel->create($newPrice);
        
        $this->logService->info('价格创建成功', [
            'price_id' => $result,
            'device_id' => $deviceId,
            'price_json' => $priceJson
        ]);
    }
    
    return $result;
}
```

### 3. 查询和使用价格数据
```php
/**
 * 获取设备当前价格
 */
public function getCurrentPrice($deviceId)
{
    $priceRecord = $this->priceModel
        ->where('site_id', $this->site_id)
        ->where('device_model_id', $deviceId)
        ->where('is_current', 1)
        ->order('price_date desc')
        ->find();
    
    if ($priceRecord) {
        // 解析JSON价格数据
        $prices = json_decode($priceRecord['price_data'], true);
        
        return [
            'price_id' => $priceRecord['id'],
            'price_date' => $priceRecord['price_date'],
            'prices' => $prices,
            'raw_json' => $priceRecord['price_data']
        ];
    }
    
    return null;
}

/**
 * 获取指定等级的价格
 */
public function getPriceByLevel($deviceId, $level = '高保充新')
{
    $currentPrice = $this->getCurrentPrice($deviceId);
    
    if ($currentPrice && isset($currentPrice['prices'][$level])) {
        return $currentPrice['prices'][$level];
    }
    
    return 0;
}

/**
 * 获取所有可用价格等级
 */
public function getAvailableLevels($deviceId)
{
    $currentPrice = $this->getCurrentPrice($deviceId);
    
    if ($currentPrice) {
        return array_keys($currentPrice['prices']);
    }
    
    return [];
}
```

### 4. 价格变更对比
```php
/**
 * 对比价格变更
 */
public function comparePriceChanges($deviceId, $newPrices)
{
    // 获取当前价格
    $currentPriceData = $this->getCurrentPrice($deviceId);
    $oldPrices = $currentPriceData ? $currentPriceData['prices'] : [];
    
    $changes = [];
    $warnings = [];
    
    // 对比每个价格等级
    foreach ($newPrices as $level => $newPrice) {
        $oldPrice = $oldPrices[$level] ?? 0;
        
        if ($oldPrice != $newPrice) {
            $changeAmount = $newPrice - $oldPrice;
            $changePercent = $oldPrice > 0 ? ($changeAmount / $oldPrice) * 100 : 0;
            
            $change = [
                'level' => $level,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'change_amount' => $changeAmount,
                'change_percent' => round($changePercent, 2)
            ];
            
            // 变更超过30%发出警告
            if (abs($changePercent) > 30) {
                $warnings[] = "价格等级 [{$level}] 变更幅度过大: {$changePercent}%";
            }
            
            $changes[] = $change;
        }
    }
    
    // 检查是否有新增的价格等级
    foreach ($newPrices as $level => $price) {
        if (!isset($oldPrices[$level])) {
            $this->logService->info('发现新价格等级', [
                'device_id' => $deviceId,
                'new_level' => $level,
                'price' => $price
            ]);
        }
    }
    
    return [
        'changes' => $changes,
        'warnings' => $warnings
    ];
}
```

## MySQL JSON字段查询示例

### 1. 查询特定价格等级
```sql
-- 查询高保充新价格大于6000的设备
SELECT device_model_id, price_data->>'$.高保充新' as 高保充新价格
FROM recycle_device_price 
WHERE JSON_EXTRACT(price_data, '$.高保充新') > 6000
AND is_current = 1;

-- 查询包含指定价格等级的设备
SELECT device_model_id, price_data
FROM recycle_device_price 
WHERE JSON_CONTAINS_PATH(price_data, 'one', '$.靓机')
AND is_current = 1;
```

### 2. 更新JSON中的特定字段
```sql
-- 更新特定价格等级
UPDATE recycle_device_price 
SET price_data = JSON_SET(price_data, '$.高保充新', 7500)
WHERE device_model_id = 123 AND is_current = 1;

-- 添加新的价格等级
UPDATE recycle_device_price 
SET price_data = JSON_SET(price_data, '$.新等级', 5000)
WHERE device_model_id = 123 AND is_current = 1;
```

### 3. 价格统计查询
```sql
-- 统计各价格等级的平均价格
SELECT 
    AVG(JSON_EXTRACT(price_data, '$.高保充新')) as 高保充新均价,
    AVG(JSON_EXTRACT(price_data, '$.充新')) as 充新均价,
    AVG(JSON_EXTRACT(price_data, '$.靓机')) as 靓机均价
FROM recycle_device_price 
WHERE is_current = 1 
AND JSON_EXTRACT(price_data, '$.高保充新') IS NOT NULL;
```

## 前端使用示例

### Vue3组件中使用价格数据
```vue
<template>
  <div class="price-display">
    <h3>{{ deviceModel }} 回收价格</h3>
    <div class="price-list">
      <div 
        v-for="(price, level) in priceData" 
        :key="level"
        class="price-item"
      >
        <span class="level">{{ level }}</span>
        <span class="price">¥{{ price }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  deviceId: Number
})

const priceData = ref({})
const deviceModel = ref('')

const loadPrice = async () => {
  const response = await fetch(`/api/device/${props.deviceId}/price`)
  const result = await response.json()
  
  if (result.success) {
    // 价格数据直接就是JSON格式
    priceData.value = result.data.prices
    deviceModel.value = result.data.model_name
  }
}

onMounted(() => {
  loadPrice()
})
</script>
```

## 优势总结

### 1. 灵活性
- **动态价格等级**：可以随时添加新的价格等级，无需修改数据库结构
- **设备特异性**：不同设备可以有不同的价格等级组合

### 2. 性能优势
- **存储效率**：JSON格式比多个单独字段更紧凑
- **查询性能**：MySQL 5.7+对JSON字段有良好的索引支持

### 3. 开发便利
- **类型安全**：JSON结构清晰，减少字段映射错误
- **前后端一致**：前端可以直接使用JSON数据，无需转换

### 4. 维护性
- **版本兼容**：新增价格等级不影响现有数据
- **数据迁移**：价格结构变更时迁移成本低

这种JSON存储方案既满足了业务灵活性需求，又保证了开发效率和系统性能。 