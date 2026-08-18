<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { statApi } from '@/api'
import { fen2yuan } from '@/utils/money'

const overview = ref<Record<string, number>>({})
const trend = ref<any[]>([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    overview.value = await statApi.overview()
    trend.value = await statApi.trend(7)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page" v-loading="loading">
    <el-row :gutter="16">
      <el-col :span="6">
        <el-card><div class="label">今日营业额</div><div class="value money">¥{{ fen2yuan(overview.today_amount) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card><div class="label">今日订单</div><div class="value">{{ overview.today_orders || 0 }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card><div class="label">今日充值</div><div class="value money">¥{{ fen2yuan(overview.today_recharge) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card><div class="label">今日新增会员</div><div class="value">{{ overview.today_members || 0 }}</div></el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="12">
        <el-card><div class="label">会员总数</div><div class="value">{{ overview.total_members || 0 }}</div></el-card>
      </el-col>
      <el-col :span="12">
        <el-card><div class="label">待出品订单</div><div class="value">{{ overview.pending_orders || 0 }}</div></el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 16px">
      <template #header>近 7 天营业趋势</template>
      <el-table :data="trend" border stripe>
        <el-table-column prop="date" label="日期" width="160" />
        <el-table-column label="营业额">
          <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.amount) }}</span></template>
        </el-table-column>
        <el-table-column prop="orders" label="订单数" width="140" />
      </el-table>
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
</style>
