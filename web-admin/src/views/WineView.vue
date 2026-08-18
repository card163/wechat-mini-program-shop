<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { verifyApi } from '@/api'

const STORAGE_STATUS: Record<number, { label: string; type: string }> = {
  1: { label: '存放中', type: 'success' },
  2: { label: '已取完', type: 'info' },
  3: { label: '已过期', type: 'danger' },
}

const EXCHANGE_STATUS: Record<number, { label: string; type: string }> = {
  0: { label: '待核销', type: 'warning' },
  1: { label: '已核销', type: 'success' },
  2: { label: '已取消', type: 'info' },
}

const tab = ref('storages')

const storageLoading = ref(false)
const storages = ref<any[]>([])
const storageTotal = ref(0)
const storageQuery = reactive({ page: 1, page_size: 20, member_id: '', status: '' })

const recordLoading = ref(false)
const records = ref<any[]>([])
const recordTotal = ref(0)
const recordQuery = reactive({ page: 1, page_size: 20, member_id: '', status: '' })

onMounted(() => {
  loadStorages()
  loadRecords()
})

async function loadStorages() {
  storageLoading.value = true
  try {
    const res = await verifyApi.wineStorages(storageQuery)
    storages.value = res.list
    storageTotal.value = res.total
  } finally {
    storageLoading.value = false
  }
}

async function loadRecords() {
  recordLoading.value = true
  try {
    const res = await verifyApi.exchangeRecords(recordQuery)
    records.value = res.list
    recordTotal.value = res.total
  } finally {
    recordLoading.value = false
  }
}
</script>

<template>
  <div class="page">
    <el-tabs v-model="tab">
      <el-tab-pane label="会员存酒" name="storages">
        <div class="toolbar">
          <el-input v-model="storageQuery.member_id" placeholder="会员ID" clearable style="width: 160px" />
          <el-select v-model="storageQuery.status" placeholder="状态" clearable style="width: 140px">
            <el-option label="存放中" :value="1" />
            <el-option label="已取完" :value="2" />
            <el-option label="已过期" :value="3" />
          </el-select>
          <el-button type="primary" @click="storageQuery.page = 1; loadStorages()">查询</el-button>
        </div>

        <el-table :data="storages" v-loading="storageLoading" border stripe>
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column prop="member_id" label="会员ID" width="90" />
          <el-table-column prop="wine_name" label="酒名" />
          <el-table-column prop="spec" label="规格" width="120" />
          <el-table-column label="数量" width="140">
            <template #default="{ row }">{{ row.remain_qty }} / {{ row.total_qty }} {{ row.unit }}</template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="(STORAGE_STATUS[row.status]?.type as any)">{{ STORAGE_STATUS[row.status]?.label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="stored_at" label="存入时间" width="180" />
          <el-table-column prop="expired_at" label="到期时间" width="180" />
        </el-table>

        <div class="pagination">
          <el-pagination v-model:current-page="storageQuery.page" :page-size="storageQuery.page_size" :total="storageTotal" layout="total, prev, pager, next" @current-change="loadStorages" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="兑换记录" name="records">
        <div class="toolbar">
          <el-input v-model="recordQuery.member_id" placeholder="会员ID" clearable style="width: 160px" />
          <el-select v-model="recordQuery.status" placeholder="状态" clearable style="width: 140px">
            <el-option label="待核销" :value="0" />
            <el-option label="已核销" :value="1" />
            <el-option label="已取消" :value="2" />
          </el-select>
          <el-button type="primary" @click="recordQuery.page = 1; loadRecords()">查询</el-button>
        </div>

        <el-table :data="records" v-loading="recordLoading" border stripe>
          <el-table-column prop="record_no" label="兑换单号" width="210" />
          <el-table-column prop="member_id" label="会员ID" width="90" />
          <el-table-column prop="goods_name" label="兑换商品" />
          <el-table-column prop="point" label="消耗记分牌" width="120" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="(EXCHANGE_STATUS[row.status]?.type as any)">{{ EXCHANGE_STATUS[row.status]?.label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="verified_at" label="核销时间" width="180" />
          <el-table-column prop="created_at" label="兑换时间" width="180" />
        </el-table>

        <div class="pagination">
          <el-pagination v-model:current-page="recordQuery.page" :page-size="recordQuery.page_size" :total="recordTotal" layout="total, prev, pager, next" @current-change="loadRecords" />
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
