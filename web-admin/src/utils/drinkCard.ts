import { reactive } from 'vue'
import { settingApi } from '@/api'
import { fen2yuan, yuan2fen } from './money'

/** 饮品卡展示名称与计量单位，后台"系统设置-礼品卡与赠金"可配置 */
export const drinkCardConfig = reactive({
  displayName: '饮品卡',
  unit: '元',
  loaded: false,
})

let loadingPromise: Promise<void> | null = null

export function loadDrinkCardConfig(): Promise<void> {
  if (drinkCardConfig.loaded) return Promise.resolve()
  if (loadingPromise) return loadingPromise

  loadingPromise = settingApi
    .get('point')
    .then((data) => {
      drinkCardConfig.displayName = data.drink_card_display_name || '饮品卡'
      drinkCardConfig.unit = data.drink_card_unit === '张' ? '张' : '元'
      drinkCardConfig.loaded = true
    })
    .finally(() => {
      loadingPromise = null
    })

  return loadingPromise
}

/** 按配置单位格式化饮品卡数值：单位"元"展示 ¥12.00，单位"张"展示 12张 */
export function drinkCardText(raw: number | string | null | undefined): string {
  const value = Number(raw) || 0
  return drinkCardConfig.unit === '张' ? `${value}张` : `¥${fen2yuan(value)}`
}

/** 表单录入值 -> 后端存储整数：单位"元"按元转分，单位"张"取整数原样 */
export function drinkCardToStorage(input: number | string): number {
  if (drinkCardConfig.unit === '张') {
    return Math.round(Number(input) || 0)
  }
  return yuan2fen(input)
}

/** 后端存储整数 -> 表单录入值：单位"元"转为两位小数的元，单位"张"原样返回整数 */
export function drinkCardFromStorage(raw: number | string | null | undefined): number | string {
  const value = Number(raw) || 0
  return drinkCardConfig.unit === '张' ? value : fen2yuan(value)
}
