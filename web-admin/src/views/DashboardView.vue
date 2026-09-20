<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import * as echarts from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { statApi } from '@/api'
import { fen2yuan } from '@/utils/money'
import { giftConfig, loadGiftConfig } from '@/utils/gift'
import { drinkCardConfig, loadDrinkCardConfig } from '@/utils/drinkCard'

echarts.use([LineChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const router = useRouter()

function dateStr(offsetDays: number): string {
  const d = new Date()
  d.setDate(d.getDate() + offsetDays)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** 跳转订单列表并带上筛选条件；amount/orders 两张卡与支付渠道卡按“支付时间”筛选，与本页统计口径保持一致
 *  日期传 00:00/23:59 完整格式，与订单列表页日期选择器的 value-format 一致，避免只传纯日期时选择器显示为空 */
function goOrdersByDay(offsetDays: number, payType?: string) {
  const day = dateStr(offsetDays)
  router.push({
    name: 'orders',
    query: {
      date_field: 'paid',
      pay_status: '1',
      start_date: `${day} 00:00`,
      end_date: `${day} 23:59`,
      ...(payType ? { pay_type: payType } : {}),
    },
  })
}

function goPendingOrders() {
  router.push({ name: 'orders', query: { order_status: '1' } })
}

const overview = ref<Record<string, number>>({})
const trend = ref<any[]>([])
const loading = ref(false)

const chartEl = ref<HTMLDivElement>()
let chart: echarts.ECharts | null = null

function renderChart() {
  if (!chartEl.value) return
  if (!chart) chart = echarts.init(chartEl.value)

  // 酒水卡/饮品卡按各自配置的计量单位展示：单位“元”沿用现金折算金额，单位“张”改用真实消耗张数（不做现金折算），
  // 两种单位数量级差异大，用“张”时挂到独立的第二 y 轴，避免和金额轴共用刻度导致曲线难以辨认
  const needUnitAxis = giftConfig.unit === '张' || drinkCardConfig.unit === '张'
  const giftAxisIndex = needUnitAxis && giftConfig.unit === '张' ? 1 : 0
  const drinkCardAxisIndex = needUnitAxis && drinkCardConfig.unit === '张' ? 1 : 0

  chart.setOption({
    tooltip: {
      trigger: 'axis',
      // 逐个系列按其真实单位格式化（第4/5个系列即酒水卡/饮品卡，按配置单位显示“张”或“¥”，其余系列固定按金额显示）
      formatter: (params: any) => {
        const list = Array.isArray(params) ? params : [params]
        if (!list.length) return ''
        const lines = [list[0].axisValueLabel ?? list[0].axisValue]
        for (const p of list) {
          const isGift = p.seriesIndex === 3
          const isDrinkCard = p.seriesIndex === 4
          const unit = isGift ? giftConfig.unit : isDrinkCard ? drinkCardConfig.unit : '元'
          const text = unit === '张' ? `${p.value} 张` : `¥${Number(p.value).toFixed(2)}`
          lines.push(`${p.marker}${p.seriesName}: ${text}`)
        }
        return lines.join('<br/>')
      },
    },
    legend: { data: ['总金额', '微信支付', '余额支付', giftConfig.displayName, drinkCardConfig.displayName] },
    grid: { left: 60, right: needUnitAxis ? 60 : 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: trend.value.map((row) => row.date) },
    yAxis: needUnitAxis
      ? [
          { type: 'value', name: '金额', axisLabel: { formatter: (v: number) => fen2yuan(v) } },
          { type: 'value', name: '张数', splitLine: { show: false } },
        ]
      : { type: 'value', axisLabel: { formatter: (v: number) => fen2yuan(v) } },
    series: [
      {
        name: '总金额',
        type: 'line',
        yAxisIndex: 0,
        data: trend.value.map((row) => Number(fen2yuan(row.amount))),
      },
      {
        name: '微信支付',
        type: 'line',
        yAxisIndex: 0,
        data: trend.value.map((row) => Number(fen2yuan(row.wechat_amount))),
      },
      {
        name: '余额支付',
        type: 'line',
        yAxisIndex: 0,
        data: trend.value.map((row) => Number(fen2yuan(row.balance_amount))),
      },
      {
        name: giftConfig.displayName,
        type: 'line',
        yAxisIndex: giftAxisIndex,
        data:
          giftConfig.unit === '张'
            ? trend.value.map((row) => Number(row.gift_units) || 0)
            : trend.value.map((row) => Number(fen2yuan(row.gift_amount))),
      },
      {
        name: drinkCardConfig.displayName,
        type: 'line',
        yAxisIndex: drinkCardAxisIndex,
        data:
          drinkCardConfig.unit === '张'
            ? trend.value.map((row) => Number(row.drink_card_units) || 0)
            : trend.value.map((row) => Number(fen2yuan(row.drink_card_amount))),
      },
    ],
  })
}

watch(trend, () => nextTick(renderChart))

function onResize() {
  chart?.resize()
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([loadGiftConfig(), loadDrinkCardConfig()])
    overview.value = await statApi.overview()
    trend.value = await statApi.trend(7)
  } finally {
    loading.value = false
  }
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  chart?.dispose()
})
</script>

<template>
  <div class="page" v-loading="loading">
    <el-row :gutter="16">
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0)"><div class="label">今日营业额</div><div class="value money">¥{{ fen2yuan(overview.today_amount) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0)"><div class="label">今日订单</div><div class="value">{{ overview.today_orders || 0 }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card><div class="label">今日充值</div><div class="value money">¥{{ fen2yuan(overview.today_recharge) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card><div class="label">今日新增会员</div><div class="value">{{ overview.today_members || 0 }}</div></el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0, '1,5')"><div class="label">今日微信支付</div><div class="value money">¥{{ fen2yuan(overview.today_pay_wechat) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0, '2')"><div class="label">今日余额支付</div><div class="value money">¥{{ fen2yuan(overview.today_pay_balance) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0, '3,5')"><div class="label">今日{{ giftConfig.displayName }}支付</div><div class="value">{{ overview.today_pay_gift_units || 0 }} 张</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(0, '4,5')"><div class="label">今日{{ drinkCardConfig.displayName }}支付</div><div class="value">{{ overview.today_pay_drink_card_units || 0 }} 张</div></el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(-1, '1,5')"><div class="label">昨日微信支付</div><div class="value money">¥{{ fen2yuan(overview.yesterday_pay_wechat) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(-1, '2')"><div class="label">昨日余额支付</div><div class="value money">¥{{ fen2yuan(overview.yesterday_pay_balance) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(-1, '3,5')"><div class="label">昨日{{ giftConfig.displayName }}支付</div><div class="value">{{ overview.yesterday_pay_gift_units || 0 }} 张</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="clickable" @click="goOrdersByDay(-1, '4,5')"><div class="label">昨日{{ drinkCardConfig.displayName }}支付</div><div class="value">{{ overview.yesterday_pay_drink_card_units || 0 }} 张</div></el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="12">
        <el-card><div class="label">会员总数</div><div class="value">{{ overview.total_members || 0 }}</div></el-card>
      </el-col>
      <el-col :span="12">
        <el-card class="clickable" @click="goPendingOrders"><div class="label">待出品订单</div><div class="value">{{ overview.pending_orders || 0 }}</div></el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 16px">
      <template #header>近 7 天营业趋势</template>
      <div ref="chartEl" style="height: 320px"></div>
    </el-card>
  </div>
</template>

<style scoped>
.label {
  color: #86909c;
  font-size: 13px;
}
.value {
  font-size: 26px;
  font-weight: 700;
  margin-top: 6px;
}
.clickable {
  cursor: pointer;
  transition: box-shadow 0.2s;
}
.clickable:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
}
</style>
