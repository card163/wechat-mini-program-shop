<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowRight, MoreFilled, Plus } from '@element-plus/icons-vue'
import { tableApi, tableZoneApi } from '@/api'

const loading = ref(false)
const zones = ref<any[]>([])
const tables = ref<any[]>([])
const keyword = ref('')
const expanded = ref<Set<number>>(new Set())

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [zoneRes, tableRes] = await Promise.all([
      tableZoneApi.list({ page: 1, page_size: 100 }),
      tableApi.list({ page: 1, page_size: 100 }),
    ])
    zones.value = zoneRes.list
    tables.value = tableRes.list
  } finally {
    loading.value = false
  }
}

function tablesOf(zoneId: number) {
  return tables.value.filter((t: any) => t.zone_id === zoneId)
}

const filteredZones = computed(() => {
  const kw = keyword.value.trim()
  if (!kw) return zones.value
  return zones.value.filter(
    (zone: any) => zone.name.includes(kw) || tablesOf(zone.id).some((t: any) => t.name.includes(kw)),
  )
})

/** 搜索时强制展开命中的分区，未搜索时按用户点击的展开状态显示 */
function isExpanded(zoneId: number) {
  return keyword.value.trim() !== '' || expanded.value.has(zoneId)
}

function toggleExpand(zoneId: number) {
  if (expanded.value.has(zoneId)) {
    expanded.value.delete(zoneId)
  } else {
    expanded.value.add(zoneId)
  }
  expanded.value = new Set(expanded.value)
}

/** 搜索命中的桌号；关键字匹配分区名时该分区下桌号全部展示，否则只展示桌号名匹配的 */
function visibleTablesOf(zoneId: number) {
  const kw = keyword.value.trim()
  const list = tablesOf(zoneId)
  if (!kw) return list
  const zoneMatched = zones.value.find((z) => z.id === zoneId)?.name.includes(kw)
  return zoneMatched ? list : list.filter((t: any) => t.name.includes(kw))
}

// 分区弹窗
const zoneDialogVisible = ref(false)
const zoneEditingId = ref(0)
const zoneForm = ref({ name: '', sort: 0, status: 1 })

function openZoneCreate() {
  zoneEditingId.value = 0
  zoneForm.value = { name: '', sort: 0, status: 1 }
  zoneDialogVisible.value = true
}

function openZoneEdit(zone: any) {
  zoneEditingId.value = zone.id
  zoneForm.value = { name: zone.name, sort: zone.sort, status: zone.status }
  zoneDialogVisible.value = true
}

async function submitZone() {
  if (!zoneForm.value.name) {
    ElMessage.warning('请填写分区名称')
    return
  }
  if (zoneEditingId.value) {
    await tableZoneApi.update(zoneEditingId.value, zoneForm.value)
  } else {
    await tableZoneApi.create(zoneForm.value)
  }
  ElMessage.success('保存成功')
  zoneDialogVisible.value = false
  load()
}

function removeZone(zone: any) {
  ElMessageBox.confirm(`确定删除分区「${zone.name}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await tableZoneApi.remove(zone.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}

function handleZoneCommand(command: string, zone: any) {
  if (command === 'edit') openZoneEdit(zone)
  if (command === 'delete') removeZone(zone)
}

// 桌号弹窗
const tableDialogVisible = ref(false)
const tableEditingId = ref(0)
const tableForm = ref({ zone_id: 0, name: '', sort: 0, status: 1 })

const zoneOptions = computed(() => zones.value.map((z: any) => ({ label: z.name, value: z.id })))

function openTableCreate(zone: any) {
  tableEditingId.value = 0
  tableForm.value = { zone_id: zone.id, name: '', sort: 0, status: 1 }
  tableDialogVisible.value = true
}

function openTableEdit(table: any) {
  tableEditingId.value = table.id
  tableForm.value = { zone_id: table.zone_id, name: table.name, sort: table.sort, status: table.status }
  tableDialogVisible.value = true
}

async function submitTable() {
  if (!tableForm.value.zone_id) {
    ElMessage.warning('请选择所属分区')
    return
  }
  if (!tableForm.value.name) {
    ElMessage.warning('请填写桌号名称')
    return
  }
  if (tableEditingId.value) {
    await tableApi.update(tableEditingId.value, tableForm.value)
  } else {
    await tableApi.create(tableForm.value)
  }
  ElMessage.success('保存成功')
  tableDialogVisible.value = false
  load()
}

function removeTable(table: any) {
  ElMessageBox.confirm(`确定删除桌号「${table.name}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await tableApi.remove(table.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}

function handleTableCommand(command: string, table: any) {
  if (command === 'edit') openTableEdit(table)
  if (command === 'delete') removeTable(table)
}
</script>

<template>
  <div class="page table-page">
    <div class="toolbar">
      <el-input v-model="keyword" placeholder="搜索分区/桌号名称" clearable style="width: 260px" />
      <el-button type="primary" :icon="Plus" @click="openZoneCreate">新增分区</el-button>
    </div>

    <div class="zone-list" v-loading="loading">
      <div class="zone-block" v-for="zone in filteredZones" :key="zone.id">
        <div class="zone-header" @click="toggleExpand(zone.id)">
          <el-icon class="chevron" :class="{ expanded: isExpanded(zone.id) }"><ArrowRight /></el-icon>
          <div class="zone-info">
            <div class="zone-name">{{ zone.name }}</div>
            <div class="zone-meta">
              {{ tablesOf(zone.id).length }} 个桌号
              <el-tag v-if="zone.status === 0" size="small" type="info" style="margin-left: 6px">已停用</el-tag>
            </div>
          </div>
          <el-dropdown trigger="click" @command="(cmd: string) => handleZoneCommand(cmd, zone)" @click.stop>
            <el-icon class="more" @click.stop><MoreFilled /></el-icon>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="edit">编辑</el-dropdown-item>
                <el-dropdown-item command="delete">删除</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>

        <div class="zone-body" v-if="isExpanded(zone.id)">
          <div class="table-row" v-for="t in visibleTablesOf(zone.id)" :key="t.id">
            <span class="table-name">{{ t.name }}</span>
            <el-tag size="small" :type="t.status === 1 ? 'success' : 'info'">{{ t.status === 1 ? '启用' : '停用' }}</el-tag>
            <el-dropdown trigger="click" @command="(cmd: string) => handleTableCommand(cmd, t)">
              <el-icon class="more"><MoreFilled /></el-icon>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
                  <el-dropdown-item command="delete">删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <el-empty v-if="visibleTablesOf(zone.id).length === 0" description="暂无桌号" :image-size="60" />
          <el-button class="add-table-btn" type="primary" plain :icon="Plus" @click="openTableCreate(zone)">添加桌号</el-button>
        </div>
      </div>
      <el-empty v-if="!loading && filteredZones.length === 0" description="暂无分区" />
    </div>

    <el-dialog v-model="zoneDialogVisible" :title="(zoneEditingId ? '编辑' : '新增') + '分区'" width="480px">
      <el-form label-width="90px">
        <el-form-item label="分区名称" required>
          <el-input v-model="zoneForm.name" placeholder="如 一号台 / 二号台" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="zoneForm.sort" :min="0" controls-position="right" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="zoneForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="zoneDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitZone">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="tableDialogVisible" :title="(tableEditingId ? '编辑' : '新增') + '桌号'" width="480px">
      <el-form label-width="90px">
        <el-form-item label="所属分区" required>
          <el-select v-model="tableForm.zone_id" style="width: 100%">
            <el-option v-for="opt in zoneOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="桌号名称" required>
          <el-input v-model="tableForm.name" placeholder="如 A01" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="tableForm.sort" :min="0" controls-position="right" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="tableForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="tableDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitTable">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.zone-list {
  background: #fff;
  border-radius: 6px;
  min-height: 120px;
}

.zone-block {
  border-bottom: 1px solid #f0f0f0;
}

.zone-block:last-child {
  border-bottom: none;
}

.zone-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
}

.zone-header:hover {
  background: #f5f7fa;
}

.chevron {
  transition: transform 0.2s;
  color: #86909c;
}

.chevron.expanded {
  transform: rotate(90deg);
}

.zone-info {
  flex: 1;
  min-width: 0;
}

.zone-name {
  font-weight: 600;
  font-size: 14px;
}

.zone-meta {
  color: #86909c;
  font-size: 12px;
  margin-top: 2px;
  display: flex;
  align-items: center;
}

.more {
  font-size: 18px;
  color: #86909c;
  padding: 4px;
  cursor: pointer;
}

.more:hover {
  color: #409eff;
}

.zone-body {
  background: #fafafa;
  padding: 8px 16px 16px 44px;
}

.table-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #fff;
  border-radius: 4px;
  margin-bottom: 8px;
}

.table-name {
  flex: 1;
  font-size: 14px;
}

.add-table-btn {
  margin-top: 4px;
}
</style>

