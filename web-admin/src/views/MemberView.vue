<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { memberApi } from '@/api'
import { fen2yuan, yuan2fen } from '@/utils/money'

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20, keyword: '', status: '', order_by: 'id' })

const detailVisible = ref(false)
const detail = ref<any>(null)
const balanceLogs = ref<any[]>([])
const pointLogs = ref<any[]>([])

const adjustVisible = ref(false)
const adjustType = ref<'balance' | 'gift' | 'point'>('balance')
const adjustForm = reactive({ amount: '', point: 0, expire_days: 0, remark: '' })

const phoneVisible = ref(false)
const phoneRow = ref<any>(null)
const phoneForm = reactive({ phone: '' })

onMounted(load)

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
  detailVisible.value = true
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

function openAdjust(row: any, type: 'balance' | 'gift' | 'point') {
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
    await memberApi.grantGift(id, yuan2fen(adjustForm.amount), adjustForm.expire_days, adjustForm.remark)
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
      <el-input v-model="query.keyword" placeholder="昵称 / 手机号" clearable style="width: 220px" @keyup.enter="search" />
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 130px">
        <el-option label="正常" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-select v-model="query.order_by" style="width: 160px">
        <el-option label="按注册时间" value="id" />
        <el-option label="按余额" value="balance" />
        <el-option label="按累计记分牌" value="total_point" />
        <el-option label="按累计消费" value="total_consume" />
      </el-select>
      <el-button type="primary" @click="search">查询</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" border stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column label="会员" min-width="200">
        <template #default="{ row }">
          <div class="member">
            <el-avatar :src="row.avatar" :size="32" />
            <span>{{ row.nickname }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="手机号" width="130">
        <template #default="{ row }">
          <el-button link type="primary" @click="openPhoneEdit(row)">{{ row.phone || '未绑定' }}</el-button>
        </template>
      </el-table-column>
      <el-table-column label="余额" width="110">
        <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.balance) }}</span></template>
      </el-table-column>
      <el-table-column label="赠金" width="110">
        <template #default="{ row }"><span class="money">¥{{ fen2yuan(row.gift_balance) }}</span></template>
      </el-table-column>
      <el-table-column prop="point" label="记分牌" width="100" />
      <el-table-column prop="total_point" label="累计记分牌" width="120" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '正常' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="330" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="showDetail(row)">详情</el-button>
          <el-button link type="primary" @click="openAdjust(row, 'balance')">调余额</el-button>
          <el-button link type="primary" @click="openAdjust(row, 'gift')">发赠金</el-button>
          <el-button link type="primary" @click="openAdjust(row, 'point')">调记分牌</el-button>
          <el-button link :type="row.status === 1 ? 'danger' : 'success'" @click="toggleStatus(row)">
            {{ row.status === 1 ? '禁用' : '启用' }}
          </el-button>
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
          <el-descriptions-item label="赠金">¥{{ fen2yuan(detail.gift_balance) }}</el-descriptions-item>
          <el-descriptions-item label="记分牌">{{ detail.point }}</el-descriptions-item>
          <el-descriptions-item label="累计记分牌">{{ detail.total_point }}</el-descriptions-item>
          <el-descriptions-item label="累计充值">¥{{ fen2yuan(detail.total_recharge) }}</el-descriptions-item>
          <el-descriptions-item label="累计消费">¥{{ fen2yuan(detail.total_consume) }}</el-descriptions-item>
        </el-descriptions>

        <el-tabs style="margin-top: 16px">
          <el-tab-pane label="赠金批次">
            <el-table :data="detail.gift_batches" border>
              <el-table-column label="发放" width="110">
                <template #default="{ row }">¥{{ fen2yuan(row.amount) }}</template>
              </el-table-column>
              <el-table-column label="剩余" width="110">
                <template #default="{ row }">¥{{ fen2yuan(row.remain_amount) }}</template>
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

          <el-tab-pane label="记分牌流水">
            <el-table :data="pointLogs" border>
              <el-table-column prop="biz_type_text" label="类型" width="130" />
              <el-table-column prop="point" label="变动" width="100" />
              <el-table-column prop="after_point" label="余额" width="100" />
              <el-table-column prop="remark" label="备注" />
              <el-table-column prop="created_at" label="时间" width="170" />
            </el-table>
          </el-tab-pane>
        </el-tabs>
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

    <el-dialog v-model="adjustVisible" :title="adjustType === 'balance' ? '调整余额' : adjustType === 'gift' ? '发放赠金' : '调整记分牌'" width="460px">
      <el-form label-width="100px">
        <el-form-item v-if="adjustType !== 'point'" label="金额">
          <el-input v-model="adjustForm.amount" :placeholder="adjustType === 'balance' ? '正数增加，负数扣减' : '发放金额'">
            <template #append>元</template>
          </el-input>
        </el-form-item>
        <el-form-item v-if="adjustType === 'gift'" label="有效天数">
          <el-input-number v-model="adjustForm.expire_days" :min="0" />
          <div class="tip">0 表示永久有效</div>
        </el-form-item>
        <el-form-item v-if="adjustType === 'point'" label="记分牌">
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
.plus {
  color: #22a06b;
  font-weight: 600;
}
.tip {
  color: #86909c;
  font-size: 12px;
}
</style>
