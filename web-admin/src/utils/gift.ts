import { reactive } from 'vue'
import { settingApi } from '@/api'
import { fen2yuan, yuan2fen } from './money'

/** 赠金展示名称与计量单位，后台"系统设置-礼品卡与赠金"可配置（如改名"酒水卡"、单位改"张"） */
export const giftConfig = reactive({
  displayName: '赠金',
  unit: '元',
  loaded: false,
})

let loadingPromise: Promise<void> | null = null

export function loadGiftConfig(): Promise<void> {
  if (giftConfig.loaded) return Promise.resolve()
  if (loadingPromise) return loadingPromise

  loadingPromise = settingApi
    .get('point')
    .then((data) => {
      giftConfig.displayName = data.gift_display_name || '赠金'
      giftConfig.unit = data.gift_unit === '张' ? '张' : '元'
      giftConfig.loaded = true
    })
    .finally(() => {
      loadingPromise = null
    })

  return loadingPromise
}

/** 按配置单位格式化赠金数值：单位"元"展示 ¥12.00，单位"张"展示 12张 */
export function giftText(raw: number | string | null | undefined): string {
  const value = Number(raw) || 0
  return giftConfig.unit === '张' ? `${value}张` : `¥${fen2yuan(value)}`
}

/** 表单录入值 -> 后端存储整数：单位"元"按元转分，单位"张"取整数原样 */
export function giftToStorage(input: number | string): number {
  if (giftConfig.unit === '张') {
    return Math.round(Number(input) || 0)
  }
  return yuan2fen(input)
}

/** 后端存储整数 -> 表单录入值：单位"元"转为两位小数的元，单位"张"原样返回整数 */
export function giftFromStorage(raw: number | string | null | undefined): number | string {
  const value = Number(raw) || 0
  return giftConfig.unit === '张' ? value : fen2yuan(value)
}
