import type { AddressInfo, AddressParts } from '../types/order'

/**
 * 地址解析工具
 * 支持多种地址格式的解析，提取省市区和详细地址
 */
export function useAddressParser() {
  /**
   * 解析地址信息，提取省市区和详细地址
   * 支持多种地址格式：
   * 1. 有单独的 area 字段（省市区字符串）
   * 2. 有单独的 province/city/district 字段
   * 3. 从 full_address 使用正则提取
   */
  const parseAddressInfo = (address: AddressInfo): AddressParts => {
    let province = ''
    let city = ''
    let district = ''
    let areaText = ''
    let detailAddress = ''

    // 方案1: 优先使用 area 字段（已组装好的省市区字符串）
    if (address.area) {
      areaText = address.area
      // 从 area 字符串中提取省市区
      const areaMatch = address.area.match(/^(.+?省)?(.+?市)?(.+?[区县])?/)
      if (areaMatch) {
        province = areaMatch[1] || ''
        city = areaMatch[2] || ''
        district = areaMatch[3] || ''
      }
    }

    // 方案2: 使用单独的省市区字段（如果存在）
    if (!province && address.province) province = address.province
    if (!city && address.city) city = address.city
    if (!district && address.district) district = address.district

    // 组装 area_text（如果还没有）
    if (!areaText && (province || city || district)) {
      areaText = `${province}${city}${district}`
    }

    // 方案3: 从 full_address 提取（如果前面方案都没获取到）
    if (!areaText && address.full_address) {
      const fullAddressMatch = address.full_address.match(/^(.+?省)?(.+?市)?(.+?[区县])/)
      if (fullAddressMatch) {
        province = fullAddressMatch[1] || ''
        city = fullAddressMatch[2] || ''
        district = fullAddressMatch[3] || ''
        areaText = `${province}${city}${district}`
      }
    }

    // 提取详细地址
    if (address.address) {
      // 优先使用单独的 address 字段
      detailAddress = address.address
    } else if (address.full_address && areaText) {
      // 从 full_address 移除省市区部分，剩余就是详细地址
      detailAddress = address.full_address.replace(areaText, '').trim()
    } else if (address.full_address) {
      // 如果无法提取省市区，将 full_address 作为详细地址
      detailAddress = address.full_address
    }

    return {
      province,
      city,
      district,
      areaText,
      detailAddress
    }
  }

  return {
    parseAddressInfo
  }
}
