/**
 * 质检字典数据管理 composable
 * 用于管理设备质检相关的选项数据
 *
 * 字典类型：
 * - recycle_display:    外屏规格（屏幕状态）
 * - recycle_indisplay:  内屏规格
 * - recycle_appearance: 中框规格（外观状态）
 * - recycle_function:   功能规格（功能检测）
 * - recycle_fix:        维修规格
 */
import { ref } from 'vue'
import { useDictionary } from '@/app/api/dict'

// 字典项接口（与后端返回结构一致）
interface DictItem {
  name: string
  value: string
  sort: number
  memo: string
}

// 质检选项配置
interface CheckOptions {
  screen: DictItem[]      // 外屏规格 (recycle_display)
  indisplay: DictItem[]   // 内屏规格 (recycle_indisplay)
  appearance: DictItem[]  // 中框规格 (recycle_appearance)
  function: DictItem[]    // 功能规格 (recycle_function)
  fix: DictItem[]         // 维修规格 (recycle_fix)
  brands: string[]        // 品牌列表
}

// 默认选项（字典加载失败时使用）
const defaultOptions: CheckOptions = {
  screen: [
    { name: '无划痕', value: '1', sort: 0, memo: '' },
    { name: '细微划痕', value: '2', sort: 1, memo: '' },
    { name: '小划痕', value: '3', sort: 2, memo: '' },
    { name: '明显划痕', value: '4', sort: 3, memo: '' },
    { name: '硬划痕', value: '5', sort: 4, memo: '' },
    { name: '外爆', value: '6', sort: 5, memo: '' },
    { name: '内爆', value: '7', sort: 6, memo: '' },
    { name: '未知部件', value: '8', sort: 7, memo: '' },
    { name: '官方提示', value: '9', sort: 8, memo: '' },
  ],
  indisplay: [
    { name: '正常', value: '1', sort: 0, memo: '' },
    { name: '漏液', value: '2', sort: 1, memo: '' },
    { name: '老化', value: '3', sort: 2, memo: '' },
    { name: '亮点/坏点', value: '4', sort: 3, memo: '' },
    { name: '阴阳屏', value: '5', sort: 4, memo: '' },
    { name: '烧屏', value: '6', sort: 5, memo: '' },
    { name: '内爆', value: '7', sort: 6, memo: '' },
  ],
  appearance: [
    { name: '无磕碰', value: '1', sort: 0, memo: '' },
    { name: '细微划痕', value: '2', sort: 1, memo: '' },
    { name: '轻微氧化', value: '3', sort: 2, memo: '' },
    { name: '中度磨损', value: '4', sort: 3, memo: '' },
    { name: '重度磨损', value: '5', sort: 4, memo: '' },
    { name: '严重损坏', value: '6', sort: 5, memo: '' },
    { name: '组装壳', value: '7', sort: 6, memo: '' },
    { name: '组装后玻璃', value: '8', sort: 7, memo: '' },
  ],
  function: [
    { name: '通话', value: '1', sort: 0, memo: '' },
    { name: '充电', value: '2', sort: 1, memo: '' },
    { name: '指纹', value: '3', sort: 2, memo: '' },
    { name: '面容', value: '4', sort: 3, memo: '' },
    { name: 'WiFi', value: '5', sort: 4, memo: '' },
    { name: '蓝牙', value: '6', sort: 5, memo: '' },
    { name: '指南针', value: '7', sort: 6, memo: '' },
    { name: 'NFC', value: '8', sort: 7, memo: '' },
    { name: '振动', value: '9', sort: 8, memo: '' },
    { name: '重力', value: '10', sort: 9, memo: '' },
    { name: '距离感应', value: '11', sort: 10, memo: '' },
    { name: '光线感应', value: '12', sort: 11, memo: '' },
    { name: '闪光', value: '13', sort: 12, memo: '' },
    { name: '触摸', value: '14', sort: 13, memo: '' },
    { name: '主麦', value: '15', sort: 14, memo: '' },
    { name: '前麦', value: '16', sort: 15, memo: '' },
    { name: '后麦', value: '17', sort: 16, memo: '' },
    { name: '扬声器', value: '18', sort: 17, memo: '' },
    { name: '听筒', value: '19', sort: 18, memo: '' },
    { name: '网络锁', value: '20', sort: 19, memo: '' },
    { name: '按键', value: '21', sort: 20, memo: '' },
    { name: '前摄', value: '22', sort: 21, memo: '' },
    { name: '后摄', value: '23', sort: 22, memo: '' },
  ],
  fix: [
    { name: '原装', value: '1', sort: 0, memo: '' },
    { name: '换屏', value: '2', sort: 1, memo: '' },
    { name: '换电池', value: '3', sort: 2, memo: '' },
    { name: '换后盖', value: '4', sort: 3, memo: '' },
    { name: '换摄像头', value: '5', sort: 4, memo: '' },
    { name: '主板维修', value: '6', sort: 5, memo: '' },
    { name: '其他维修', value: '7', sort: 6, memo: '' },
  ],
  brands: [
    '华为', '荣耀', '小米', 'OPPO', 'vivo', '三星', 'realme',
    '努比亚', 'moto', '中兴', 'HUAWEI', 'Xiaomi', 'Samsung',
    'Realme', 'Nubia', 'Moto', 'ZTE', '摩托',
    'Apple', 'iPhone', 'iPad', '苹果'
  ]
}

export function useCheckDeviceDict() {
  const loading = ref(false)
  const options = ref<CheckOptions>({ ...defaultOptions })

  // 加载单个字典
  const loadSingleDict = async (dictKey: string): Promise<DictItem[]> => {
    try {
      const res = await useDictionary(dictKey)
      // 数据结构: res.data.dictionary 是数组
      if (res.data?.dictionary && Array.isArray(res.data.dictionary)) {
        return res.data.dictionary
      }
    } catch (error) {
      console.warn(`加载字典 ${dictKey} 失败:`, error)
    }
    return []
  }

  // 加载所有字典数据
  const loadDictionary = async () => {
    loading.value = true
    try {
      // 并行加载五个字典
      const [screenData, indisplayData, appearanceData, functionData, fixData] = await Promise.all([
        loadSingleDict('recycle_display'),
        loadSingleDict('recycle_indisplay'),
        loadSingleDict('recycle_appearance'),
        loadSingleDict('recycle_function'),
        loadSingleDict('recycle_fix')
      ])

      // 更新选项（如果有数据则使用字典数据，否则保持默认值）
      if (screenData.length > 0) options.value.screen = screenData
      if (indisplayData.length > 0) options.value.indisplay = indisplayData
      if (appearanceData.length > 0) options.value.appearance = appearanceData
      if (functionData.length > 0) options.value.function = functionData
      if (fixData.length > 0) options.value.fix = fixData
    } catch (error) {
      console.warn('加载质检字典失败，使用默认配置:', error)
    } finally {
      loading.value = false
    }
  }

  // 获取屏幕选项的 name 数组（用于显示）
  const screenLabels = () => options.value.screen.map(item => item.name)

  // 获取内屏选项的 name 数组
  const indisplayLabels = () => options.value.indisplay.map(item => item.name)

  // 获取外观选项的 name 数组
  const appearanceLabels = () => options.value.appearance.map(item => item.name)

  // 获取功能选项的 name 数组
  const functionLabels = () => options.value.function.map(item => item.name)

  // 获取维修选项的 name 数组
  const fixLabels = () => options.value.fix.map(item => item.name)

  // 品牌关键词 → 标准化 key（与后端端点映射一致）
  const BRAND_KEY_MAP: Record<string, string> = {
    'apple': 'apple', 'iphone': 'apple', 'ipad': 'apple', '苹果': 'apple',
    'huawei': 'huawei', '华为': 'huawei',
    'honor': 'honor', '荣耀': 'honor',
    'xiaomi': 'xiaomi', '小米': 'xiaomi', 'redmi': 'xiaomi', '红米': 'xiaomi',
    'oppo': 'oppo',
    'vivo': 'vivo',
    'samsung': 'samsung', '三星': 'samsung',
    'realme': 'realme',
    'nubia': 'nubia', '努比亚': 'nubia',
    'moto': 'moto', '摩托': 'moto',
    'zte': 'zte', '中兴': 'zte',
  }

  // 提取品牌：从型号字符串中识别品牌并返回标准化 key
  const extractBrand = (productName: string): string => {
    if (!productName) return ''
    const lower = productName.toLowerCase()
    for (const [keyword, key] of Object.entries(BRAND_KEY_MAP)) {
      if (lower.includes(keyword.toLowerCase())) return key
    }
    return ''
  }

  return {
    loading,
    options,
    loadDictionary,
    screenLabels,
    indisplayLabels,
    appearanceLabels,
    functionLabels,
    fixLabels,
    extractBrand
  }
}
