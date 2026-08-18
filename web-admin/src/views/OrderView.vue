<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { orderApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { fen2yuan } from '@/utils/money'

const auth = useAuthStore()

const STATUS = [
  { label: '待支付', value: 0, type: 'info' },
  { label: '已支付', value: 1, type: 'warning' },
  { label: '已完成', value: 2, type: 'success' },
  { label: '已取消', value: 3, type: 'danger' },
]

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20, order_no: '', order_status: '', start_date: '', end_date: '' })

const detail = ref<any>(null)
const detailVisible = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await orderApi.list(query)
    rows.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}

function search() {
  query.page = 1
  load()
}

function statusMeta(value: number) {
  return STATUS.find((item) => item.value === value) || { label: '未知', type: 'info' }
}

async function showDetail(row: any) {
  detail.value = await orderApi.detail(row.id)
  detailVisible.value = true
}

function finish(row: any) {
  ElMessageBox.confirm(`确认订单 ${row.order_no} 已出品完成？`, '提示')
    .then(async () => {
      await orderApi.finish(row.id)
      ElMessage.success('订单已完成')
      load()
    })
    .catch(() => {})
}

function refund(row: any) {
  ElMessageBox.prompt(
    `退款金额 ¥${fen2yuan(row.pay_amount)}，赠金与余额将原路退回，请填写退款原因`,
    '订单退款',
    { inputPlaceholder: '退款原因', inputValidator: (v) => (v ? true : '请填写退款原因') },
  )
    .then(async ({ value }) => {
      await orderApi.refund(row.id, value)
      ElMessage.success('退款成功')
      load()
    })
    .catch(() => {})
}
</script>

<template>
  <div class="page">
    <div class="toolbar">
      <el-input v-model="query.order_no" placeholder="订单号" clearable style="width: 220px" @keyup.enter="search" />
      <el-select v-model="query.order_status" placeholder="订单状态" clearable style="width: 140px">
        <el-option v-for="item in STATUS" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>
      <el-date-picker v-model="query.start_date" type="date" placeholder="开始日期" value-format="YYYY-MM-DD" />
      <el-date-picker v-model="query.end_date" type="date" placeholder="结束日期" value-format="YYYY-MM-DD" />
      <el-button type="primary" @click="search">查询</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" border stripe>
      <el-table-column prop="order_no" label="订单号" width="210" />
      <el-table-column prop="table_name" label="桌号" width="90" />
      <el-table-column prop="member_id" label="会员ID" width="90" />
      <el-table-column label="金额" width="120">
        <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.pay_amount) }}</span></template>
      </el-table-column>
      <el-table-column label="支付构成" width="240">
        <template #default="{ row }">
          <span>微信 ¥{{ fen2yuan(row.pay_wechat) }} / 余额 ¥{{ fen2yuan(row.pay_balance) }} / 赠金 ¥{{ fen2yuan(row.pay_gift) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusMeta(row.order_status).type as any">{{ statusMeta(row.order_status).label }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="下单时间" width="180" />
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="showDetail(row)">详情</el-button>
          <el-button v-if="row.order_status === 1" link type="success" @click="finish(row)">完成</el-button>
          <el-button v-if="auth.isSuper() && row.pay_status === 1" link type="danger" @click="refund(row)">退款</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.page_size"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="load"
      />
    </div>

    <el-drawer v-model="detailVisible" title="订单详情" size="520px">
      <template v-if="detail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="订单号">{{ detail.order_no }}</el-descriptions-item>
          <el-descriptions-item label="桌号">{{ detail.table_name }}</el-descriptions-item>
          <el-descriptions-item label="会员ID">{{ detail.member_id }}</el-descriptions-item>
          <el-descriptions-item label="应付">¥{{ fen2yuan(detail.pay_amount) }}</el-descriptions-item>
          <el-descriptions-item label="微信支付">¥{{ fen2yuan(detail.pay_wechat) }}</el-descriptions-item>
          <el-descriptions-item label="余额支付">¥{{ fen2yuan(detail.pay_balance) }}</el-descriptions-item>
          <el-descriptions-item label="赠金抵扣">¥{{ fen2yuan(detail.pay_gift) }}</el-descriptions-item>
          <el-descriptions-item label="获得记分牌">{{ detail.gain_point }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ detail.remark || '-' }}</el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ detail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ detail.paid_at || '-' }}</el-descriptions-item>
        </el-descriptions>

        <el-table :data="detail.items" border style="margin-top: 16px">
          <el-table-column prop="goods_name" label="商品" />
          <el-table-column prop="quantity" label="数量" width="80" />
          <el-table-column label="小计" width="110">
            <template #default="{ row }">¥{{ fen2yuan(row.subtotal) }}</template>
          </el-table-column>
        </el-table>
      </template>
    </el-drawer>
  </div>
</template>
