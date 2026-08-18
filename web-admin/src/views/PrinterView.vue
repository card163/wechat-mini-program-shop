<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { printLogApi, printerApi } from '@/api'

const VENDORS = [
  { label: '飞鹅云打印', value: 1 },
  { label: '芯烨云打印', value: 2 },
  { label: '商米云打印', value: 3 },
]

function vendorLabel(vendor: number) {
  return VENDORS.find((item) => item.value === vendor)?.label || '未知'
}

const activeTab = ref('printers')

// ---------------- 打印机配置 ----------------
const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20 })

const dialogVisible = ref(false)
const editingId = ref(0)
const form = ref<Record<string, any>>({})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await printerApi.list(query)
    rows.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = 0
  form.value = { name: '', vendor: 1, sn: '', account: '', secret_key: '', copies: 1, voice_times: 0, status: 1, sort: 0, remark: '' }
  dialogVisible.value = true
}

function openEdit(row: any) {
  editingId.value = row.id
  form.value = { ...row, secret_key: '' }
  dialogVisible.value = true
}

async function submit() {
  if (!form.value.name) return ElMessage.warning('请输入打印机名称')
  if (!form.value.sn) return ElMessage.warning('请输入打印机终端编号(SN)')
  if (!form.value.account) return ElMessage.warning('请输入开放平台账号')
  if (!editingId.value && !form.value.secret_key) return ElMessage.warning('请输入密钥')

  const payload = { ...form.value }
  if (!payload.secret_key) delete payload.secret_key

  if (editingId.value) {
    await printerApi.update(editingId.value, payload)
  } else {
    await printerApi.create(payload)
  }
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

function remove(row: any) {
  ElMessageBox.confirm(`确定删除打印机「${row.name}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await printerApi.remove(row.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}

async function testPrint(row: any) {
  const res: any = await printerApi.testPrint(row.id)
  if (res.success) {
    ElMessage.success('测试打印已推送，请留意打印机出票')
  } else {
    ElMessage.error(`测试打印失败：${res.message || '未知错误'}`)
  }
  if (activeTab.value === 'logs') loadLogs()
}

// ---------------- 打印日志 ----------------
const logLoading = ref(false)
const logRows = ref<any[]>([])
const logTotal = ref(0)
const logQuery = reactive({ page: 1, page_size: 20, order_no: '', status: '' })

async function loadLogs() {
  logLoading.value = true
  try {
    const res = await printLogApi.list(logQuery)
    logRows.value = res.list
    logTotal.value = res.total
  } finally {
    logLoading.value = false
  }
}

function searchLogs() {
  logQuery.page = 1
  loadLogs()
}

function onTabChange(name: string | number) {
  if (name === 'logs' && logRows.value.length === 0) loadLogs()
}
</script>

<template>
  <div class="page">
    <el-tabs v-model="activeTab" @tab-change="onTabChange">
      <el-tab-pane label="打印机配置" name="printers">
        <div class="toolbar">
          <el-button type="primary" @click="openCreate">新增打印机</el-button>
        </div>

        <el-table :data="rows" v-loading="loading" border stripe>
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="name" label="名称" min-width="120" />
          <el-table-column label="厂商" width="120">
            <template #default="{ row }">{{ vendorLabel(row.vendor) }}</template>
          </el-table-column>
          <el-table-column prop="sn" label="终端编号(SN)" width="140" />
          <el-table-column prop="account" label="账号" width="140" />
          <el-table-column prop="secret_key" label="密钥" width="110" />
          <el-table-column prop="copies" label="联数" width="70" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" @click="testPrint(row)">测试打印</el-button>
              <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button link type="danger" @click="remove(row)">删除</el-button>
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
      </el-tab-pane>

      <el-tab-pane label="打印日志" name="logs">
        <div class="toolbar">
          <el-input v-model="logQuery.order_no" placeholder="订单号" clearable style="width: 200px" @keyup.enter="searchLogs" />
          <el-select v-model="logQuery.status" placeholder="状态" clearable style="width: 120px">
            <el-option label="成功" :value="1" />
            <el-option label="失败" :value="2" />
          </el-select>
          <el-button @click="searchLogs">查询</el-button>
        </div>

        <el-table :data="logRows" v-loading="logLoading" border stripe>
          <el-table-column prop="printer_name" label="打印机" width="140" />
          <el-table-column prop="order_no" label="订单号" min-width="180" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : row.status === 2 ? 'danger' : 'info'">{{ row.status_text }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="fail_reason" label="失败原因" min-width="220" />
          <el-table-column prop="created_at" label="时间" width="170" />
        </el-table>

        <div class="pagination">
          <el-pagination
            v-model:current-page="logQuery.page"
            v-model:page-size="logQuery.page_size"
            :total="logTotal"
            layout="total, prev, pager, next"
            @current-change="loadLogs"
          />
        </div>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="dialogVisible" :title="(editingId ? '编辑' : '新增') + '打印机'" width="560px">
      <el-form label-width="140px">
        <el-form-item label="名称"><el-input v-model="form.name" placeholder="如 出单台1" /></el-form-item>
        <el-form-item label="厂商">
          <el-select v-model="form.vendor" style="width: 100%">
            <el-option v-for="item in VENDORS" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="打印机终端编号(SN)"><el-input v-model="form.sn" /></el-form-item>
        <el-form-item label="开放平台账号">
          <el-input v-model="form.account" placeholder="飞鹅User / 芯烨User / 商米AppId" />
        </el-form-item>
        <el-form-item label="密钥">
          <el-input
            v-model="form.secret_key"
            type="password"
            show-password
            :placeholder="editingId ? '留空表示不修改' : '飞鹅UKEY / 芯烨UKEY / 商米AppSecret'"
          />
        </el-form-item>
        <el-form-item label="打印联数"><el-input-number v-model="form.copies" :min="1" :max="9" controls-position="right" /></el-form-item>
        <el-form-item label="语音提醒次数">
          <el-input-number v-model="form.voice_times" :min="0" :max="9" controls-position="right" />
          <div class="tip">0 为不提醒，仅飞鹅/芯烨打印机支持</div>
        </el-form-item>
        <el-form-item label="排序"><el-input-number v-model="form.sort" controls-position="right" /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
        <el-form-item label="备注"><el-input v-model="form.remark" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.tip {
  color: #86909c;
  font-size: 12px;
  margin-top: 4px;
}
</style>
