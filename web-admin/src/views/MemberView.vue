<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown } from '@element-plus/icons-vue'
import { memberApi, orderApi } from '@/api'
import { fen2yuan, yuan2fen } from '@/utils/money'
import { giftConfig, giftText, giftToStorage, loadGiftConfig } from '@/utils/gift'
import { drinkCardConfig, drinkCardText, drinkCardToStorage, loadDrinkCardConfig } from '@/utils/drinkCard'

const ORDER_STATUS = [
  { label: '待支付', value: 0, type: 'info' },
  { label: '已支付', value: 1, type: 'warning' },
  { label: '已完成', value: 2, type: 'success' },
  { label: '已取消', value: 3, type: 'danger' },
]

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20, keyword: '', status: '', order_by: 'id' })

const detailVisible = ref(false)
const detail = ref<any>(null)
const balanceLogs = ref<any[]>([])
const pointLogs = ref<any[]>([])

const orderLoading = ref(false)
const orderRows = ref<any[]>([])
const orderTotal = ref(0)
const orderQuery = reactive({ page: 1, page_size: 10 })

const orderDetailVisible = ref(false)
const orderDetail = ref<any>(null)

const adjustVisible = ref(false)
const adjustType = ref<'balance' | 'gift' | 'giftDeduct' | 'drinkCard' | 'drinkCardDeduct' | 'point'>('balance')
const adjustForm = reactive({ amount: '', point: 0, expire_days: 0, remark: '' })

const phoneVisible = ref(false)
const phoneRow = ref<any>(null)
const phoneForm = reactive({ phone: '' })

onMounted(load)
onMounted(loadGiftConfig)
onMounted(loadDrinkCardConfig)

async function load() {
  loading.value = true
  try {
    const res = await memberApi.list(query)
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

async function showDetail(row: any) {
  detail.value = await memberApi.detail(row.id)
  const [balance, point] = await Promise.all([
    memberApi.balanceLogs(row.id, { page: 1, page_size: 20 }),
    memberApi.pointLogs(row.id, { page: 1, page_size: 20 }),
  ])
  balanceLogs.value = balance.list
  pointLogs.value = point.list
  orderQuery.page = 1
  await loadOrders()
  detailVisible.value = true
}

async function loadOrders() {
  orderLoading.value = true
  try {
    const res = await orderApi.list({ member_id: detail.value.id, page: orderQuery.page, page_size: orderQuery.page_size })
    orderRows.value = res.list
    orderTotal.value = res.total
  } finally {
    orderLoading.value = false
  }
}

function orderStatusMeta(value: number) {
  return ORDER_STATUS.find((item) => item.value === value) || { label: '未知', type: 'info' }
}

async function showOrderDetail(row: any) {
  orderDetail.value = await orderApi.detail(row.id)
  orderDetailVisible.value = true
}

function toggleStatus(row: any) {
  const next = row.status === 1 ? 0 : 1
  ElMessageBox.confirm(`确定${next === 1 ? '启用' : '禁用'}会员「${row.nickname}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await memberApi.status(row.id, next)
      ElMessage.success('操作成功')
      load()
    })
    .catch(() => {})
}

function openPhoneEdit(row: any) {
  phoneRow.value = row
  phoneForm.phone = row.phone
  phoneVisible.value = true
}

async function submitPhone() {
  if (!/^1[3-9]\d{9}$/.test(phoneForm.phone)) {
    ElMessage.warning('请输入正确的手机号')
    return
  }

  await memberApi.updatePhone(phoneRow.value.id, phoneForm.phone)
  ElMessage.success('修改成功')
  phoneVisible.value = false
  load()
}

function openAdjust(row: any, type: 'balance' | 'gift' | 'giftDeduct' | 'drinkCard' | 'drinkCardDeduct' | 'point') {
  detail.value = row
  adjustType.value = type
  adjustForm.amount = ''
  adjustForm.point = 0
  adjustForm.expire_days = 0
  adjustForm.remark = ''
  adjustVisible.value = true
}

async function submitAdjust() {
  if (!adjustForm.remark) {
    ElMessage.warning('请填写操作原因')
    return
  }

  const id = detail.value.id
  if (adjustType.value === 'balance') {
    await memberApi.adjustBalance(id, yuan2fen(adjustForm.amount), adjustForm.remark)
  } else if (adjustType.value === 'gift') {
    await memberApi.grantGift(id, giftToStorage(adjustForm.amount), adjustForm.expire_days, adjustForm.remark)
  } else if (adjustType.value === 'giftDeduct') {
    await memberApi.deductGift(id, giftToStorage(adjustForm.amount), adjustForm.remark)
  } else if (adjustType.value === 'drinkCard') {
    await memberApi.grantDrinkCard(id, drinkCardToStorage(adjustForm.amount), adjustForm.expire_days, adjustForm.remark)
  } else if (adjustType.value === 'drinkCardDeduct') {
    await memberApi.deductDrinkCard(id, drinkCardToStorage(adjustForm.amount), adjustForm.remark)
  } else {
    await memberApi.adjustPoint(id, adjustForm.point, adjustForm.remark)
  }

  ElMessage.success('操作成功')
  adjustVisible.value = false
  load()
}
</script>

<template>
  <div class="page">
    <div class="toolbar">
      <el-input v-model="query.keyword" placeholder="昵称 / 手机号 / ID" clearable style="width: 220px" @keyup.enter="search" />
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 130px">
        <el-option label="正常" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-select v-model="query.order_by" style="width: 160px">
        <el-option label="按注册时间" value="id" />
        <el-option label="按余额" value="balance" />
        <el-option label="按累计礼品卡" value="total_point" />
        <el-option label="按累计消费" value="total_consume" />
      </el-select>
      <el-button type="primary" @click="search">查询</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" border stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="会员" width="170">
        <template #default="{ row }">
          <div class="member">
            <el-avatar :src="row.avatar" :size="28" />
            <span class="ellipsis">{{ row.nickname }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="手机号" width="125">
        <template #default="{ row }">
          <el-button link type="primary" @click="openPhoneEdit(row)">{{ row.phone || '未绑定' }}</el-button>
        </template>
      </el-table-column>
      <el-table-column label="余额" width="90">
        <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.balance) }}</span></template>
      </el-table-column>
      <el-table-column :label="giftConfig.displayName" width="90">
        <template #default="{ row }"><span class="money">{{ giftText(row.gift_balance) }}</span></template>
      </el-table-column>
      <el-table-column :label="drinkCardConfig.displayName" width="90">
        <template #default="{ row }"><span class="money">{{ drinkCardText(row.drink_card_balance) }}</span></template>
      </el-table-column>
      <el-table-column prop="point" label="礼品卡" width="80" />
      <el-table-column prop="total_point" label="累计礼品卡" width="100" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '正常' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="showDetail(row)">详情</el-button>
          <el-button link :type="row.status === 1 ? 'danger' : 'success'" @click="toggleStatus(row)">
            {{ row.status === 1 ? '禁用' : '启用' }}
          </el-button>
          <el-dropdown trigger="click" @command="(cmd: string) => openAdjust(row, cmd as any)">
            <el-button link type="primary">更多<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="balance">调余额</el-dropdown-item>
                <el-dropdown-item command="gift">发{{ giftConfig.displayName }}</el-dropdown-item>
                <el-dropdown-item command="giftDeduct">扣{{ giftConfig.displayName }}</el-dropdown-item>
                <el-dropdown-item command="drinkCard">发{{ drinkCardConfig.displayName }}</el-dropdown-item>
                <el-dropdown-item command="drinkCardDeduct">扣{{ drinkCardConfig.displayName }}</el-dropdown-item>
                <el-dropdown-item command="point">调礼品卡</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
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

    <el-drawer v-model="detailVisible" title="会员详情" size="640px">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="会员ID">{{ detail.id }}</el-descriptions-item>
          <el-descriptions-item label="昵称">{{ detail.nickname }}</el-descriptions-item>
          <el-descriptions-item label="手机号">{{ detail.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ detail.status === 1 ? '正常' : '禁用' }}</el-descriptions-item>
          <el-descriptions-item label="余额">¥{{ fen2yuan(detail.balance) }}</el-descriptions-item>
          <el-descriptions-item :label="giftConfig.displayName">{{ giftText(detail.gift_balance) }}</el-descriptions-item>
          <el-descriptions-item :label="drinkCardConfig.displayName">{{ drinkCardText(detail.drink_card_balance) }}</el-descriptions-item>
          <el-descriptions-item label="礼品卡">{{ detail.point }}</el-descriptions-item>
          <el-descriptions-item label="累计礼品卡">{{ detail.total_point }}</el-descriptions-item>
          <el-descriptions-item label="累计充值">¥{{ fen2yuan(detail.total_recharge) }}</el-descriptions-item>
          <el-descriptions-item label="累计消费">¥{{ fen2yuan(detail.total_consume) }}</el-descriptions-item>
        </el-descriptions>

        <el-tabs style="margin-top: 16px">
          <el-tab-pane :label="`${giftConfig.displayName}批次`">
            <el-table :data="detail.gift_batches" border>
              <el-table-column label="发放" width="110">
                <template #default="{ row }">{{ giftText(row.amount) }}</template>
              </el-table-column>
              <el-table-column label="剩余" width="110">
                <template #default="{ row }">{{ giftText(row.remain_amount) }}</template>
              </el-table-column>
              <el-table-column prop="status_text" label="状态" width="100" />
              <el-table-column prop="expired_at" label="到期时间" />
            </el-table>
          </el-tab-pane>

          <el-tab-pane :label="`${drinkCardConfig.displayName}批次`">
            <el-table :data="detail.drink_card_batches" border>
              <el-table-column label="发放" width="110">
                <template #default="{ row }">{{ drinkCardText(row.amount) }}</template>
              </el-table-column>
              <el-table-column label="剩余" width="110">
                <template #default="{ row }">{{ drinkCardText(row.remain_amount) }}</template>
              </el-table-column>
              <el-table-column prop="status_text" label="状态" width="100" />
              <el-table-column prop="expired_at" label="到期时间" />
            </el-table>
          </el-tab-pane>

          <el-tab-pane label="余额流水">
            <el-table :data="balanceLogs" border>
              <el-table-column prop="biz_type_text" label="类型" width="120" />
              <el-table-column label="变动" width="120">
                <template #default="{ row }">
                  <span :class="row.amount > 0 ? 'plus' : 'money'">{{ row.amount > 0 ? '+' : '' }}{{ fen2yuan(row.amount) }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" />
              <el-table-column prop="created_at" label="时间" width="170" />
            </el-table>
          </el-tab-pane>

          <el-tab-pane label="礼品卡流水">
            <el-table :data="pointLogs" border>
              <el-table-column prop="biz_type_text" label="类型" width="130" />
              <el-table-column prop="point" label="变动" width="100" />
              <el-table-column prop="after_point" label="余额" width="100" />
              <el-table-column prop="remark" label="备注" />
              <el-table-column prop="created_at" label="时间" width="170" />
            </el-table>
          </el-tab-pane>

          <el-tab-pane label="消费订单">
            <el-table :data="orderRows" v-loading="orderLoading" border>
              <el-table-column prop="order_no" label="订单号" min-width="160" />
              <el-table-column prop="table_name" label="桌号" width="90" />
              <el-table-column label="金额" width="100">
                <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.pay_amount) }}</span></template>
              </el-table-column>
              <el-table-column label="状态" width="90">
                <template #default="{ row }">
                  <el-tag :type="orderStatusMeta(row.order_status).type as any">{{ orderStatusMeta(row.order_status).label }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="下单时间" width="170" />
              <el-table-column label="操作" width="80">
                <template #default="{ row }">
                  <el-button link type="primary" @click="showOrderDetail(row)">详情</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div class="pagination">
              <el-pagination
                v-model:current-page="orderQuery.page"
                v-model:page-size="orderQuery.page_size"
                :total="orderTotal"
                layout="total, prev, pager, next"
                @current-change="loadOrders"
              />
            </div>
          </el-tab-pane>
        </el-tabs>
      </template>
    </el-drawer>

    <el-drawer v-model="orderDetailVisible" title="订单详情" size="480px">
      <template v-if="orderDetail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="订单号">{{ orderDetail.order_no }}</el-descriptions-item>
          <el-descriptions-item label="桌号">{{ orderDetail.table_name }}</el-descriptions-item>
          <el-descriptions-item label="应付">¥{{ fen2yuan(orderDetail.pay_amount) }}</el-descriptions-item>
          <el-descriptions-item label="微信支付">¥{{ fen2yuan(orderDetail.pay_wechat) }}</el-descriptions-item>
          <el-descriptions-item label="余额支付">¥{{ fen2yuan(orderDetail.pay_balance) }}</el-descriptions-item>
          <el-descriptions-item :label="`${giftConfig.displayName}抵扣`">{{ giftText(orderDetail.pay_gift) }}</el-descriptions-item>
          <el-descriptions-item :label="`${drinkCardConfig.displayName}抵扣`">{{ drinkCardText(orderDetail.pay_drink_card) }}</el-descriptions-item>
          <el-descriptions-item label="获得礼品卡">{{ orderDetail.gain_point }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ orderDetail.remark || '-' }}</el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ orderDetail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="支付时间">{{ orderDetail.paid_at || '-' }}</el-descriptions-item>
        </el-descriptions>

        <el-table :data="orderDetail.items" border style="margin-top: 16px">
          <el-table-column prop="goods_name" label="商品" />
          <el-table-column prop="quantity" label="数量" width="80" />
          <el-table-column label="小计" width="110">
            <template #default="{ row }">¥{{ fen2yuan(row.subtotal) }}</template>
          </el-table-column>
        </el-table>
      </template>
    </el-drawer>

    <el-dialog v-model="phoneVisible" title="修改手机号" width="400px">
      <el-form label-width="80px">
        <el-form-item label="手机号">
          <el-input v-model="phoneForm.phone" placeholder="请输入11位手机号" maxlength="11" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="phoneVisible = false">取消</el-button>
        <el-button type="primary" @click="submitPhone">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="adjustVisible"
      :title="
        adjustType === 'balance' ? '调整余额'
        : adjustType === 'gift' ? `发放${giftConfig.displayName}`
        : adjustType === 'giftDeduct' ? `扣减${giftConfig.displayName}`
        : adjustType === 'drinkCard' ? `发放${drinkCardConfig.displayName}`
        : adjustType === 'drinkCardDeduct' ? `扣减${drinkCardConfig.displayName}`
        : '调整礼品卡'
      "
      width="460px"
    >
      <el-form label-width="100px">
        <el-form-item v-if="adjustType !== 'point'" label="金额">
          <el-input
            v-model="adjustForm.amount"
            :placeholder="adjustType === 'balance' ? '正数增加，负数扣减' : adjustType === 'giftDeduct' || adjustType === 'drinkCardDeduct' ? '扣减数量' : '发放金额'"
          >
            <template #append>{{ adjustType === 'gift' || adjustType === 'giftDeduct' ? giftConfig.unit : adjustType === 'drinkCard' || adjustType === 'drinkCardDeduct' ? drinkCardConfig.unit : '元' }}</template>
          </el-input>
        </el-form-item>
        <el-form-item v-if="adjustType === 'gift' || adjustType === 'drinkCard'" label="有效天数">
          <el-input-number v-model="adjustForm.expire_days" :min="0" />
          <div class="tip">0 表示永久有效</div>
        </el-form-item>
        <div v-if="adjustType === 'giftDeduct' || adjustType === 'drinkCardDeduct'" class="tip" style="margin: -8px 0 12px 100px">
          按批次到期时间由近及远自动扣减，与下单消费扣减规则一致
        </div>
        <el-form-item v-if="adjustType === 'point'" label="礼品卡">
          <el-input-number v-model="adjustForm.point" />
          <div class="tip">正数发放，负数扣减</div>
        </el-form-item>
        <el-form-item label="操作原因">
          <el-input v-model="adjustForm.remark" type="textarea" :rows="2" placeholder="必填，用于审计" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAdjust">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.member {
  display: flex;
  align-items: center;
  gap: 8px;
}
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.plus {
  color: #22a06b;
  font-weight: 600;
}
.tip {
  color: #86909c;
  font-size: 12px;
}
</style>
