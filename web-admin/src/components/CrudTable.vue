<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getToken, type PageResult } from '@/api/request'
import { uploadUrl } from '@/api'
import { fen2yuan, yuan2fen } from '@/utils/money'

export interface CrudColumn {
  prop: string
  label: string
  width?: number | string
  type?: 'text' | 'money' | 'image' | 'status' | 'datetime'
}

export interface CrudField {
  prop: string
  label: string
  type?: 'text' | 'textarea' | 'number' | 'money' | 'switch' | 'select' | 'image'
  options?: { label: string; value: any }[]
  placeholder?: string
  required?: boolean
  default?: any
  tip?: string
}

interface CrudApi {
  list: (params: Record<string, any>) => Promise<PageResult<any>>
  create: (data: Record<string, any>) => Promise<any>
  update: (id: number, data: Record<string, any>) => Promise<any>
  remove: (id: number) => Promise<any>
}

const props = defineProps<{
  api: CrudApi
  columns: CrudColumn[]
  fields: CrudField[]
  title: string
  searchable?: boolean
  extraParams?: Record<string, any>
}>()

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20, keyword: '' })

const dialogVisible = ref(false)
const editingId = ref(0)
const form = ref<Record<string, any>>({})

const uploadHeaders = { Authorization: `Bearer ${getToken()}` }

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await props.api.list({ ...query, ...(props.extraParams || {}) })
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

function openCreate() {
  editingId.value = 0
  const data: Record<string, any> = {}
  props.fields.forEach((field) => {
    data[field.prop] = field.default ?? (field.type === 'number' || field.type === 'money' ? 0 : '')
  })
  form.value = data
  dialogVisible.value = true
}

function openEdit(row: any) {
  editingId.value = row.id
  const data: Record<string, any> = {}
  props.fields.forEach((field) => {
    data[field.prop] = field.type === 'money' ? fen2yuan(row[field.prop]) : row[field.prop]
  })
  form.value = data
  dialogVisible.value = true
}

async function submit() {
  const payload: Record<string, any> = {}
  for (const field of props.fields) {
    const value = form.value[field.prop]
    if (field.required && (value === '' || value === null || value === undefined)) {
      ElMessage.warning(`请填写${field.label}`)
      return
    }
    payload[field.prop] = field.type === 'money' ? yuan2fen(value) : value
  }

  if (editingId.value) {
    await props.api.update(editingId.value, payload)
  } else {
    await props.api.create(payload)
  }

  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

function remove(row: any) {
  ElMessageBox.confirm(`确定删除「${row.name || row.title || row.id}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await props.api.remove(row.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}

function onUploaded(response: any, field: CrudField) {
  if (response?.code === 0) {
    form.value[field.prop] = response.data.url
  } else {
    ElMessage.error(response?.msg || '上传失败')
  }
}
</script>

<template>
  <div class="page">
    <div class="toolbar">
      <el-input v-if="searchable !== false" v-model="query.keyword" placeholder="搜索名称" clearable style="width: 220px" @keyup.enter="search" />
      <el-button v-if="searchable !== false" @click="search">查询</el-button>
      <el-button type="primary" @click="openCreate">新增{{ title }}</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column v-for="col in columns" :key="col.prop" :prop="col.prop" :label="col.label" :width="col.width">
        <template #default="{ row }">
          <el-image v-if="col.type === 'image'" :src="row[col.prop]" style="width: 56px; height: 56px" fit="cover" />
          <span v-else-if="col.type === 'money'" class="money">¥{{ fen2yuan(row[col.prop]) }}</span>
          <el-tag v-else-if="col.type === 'status'" :type="row[col.prop] === 1 ? 'success' : 'info'">
            {{ row[col.prop] === 1 ? '启用' : '停用' }}
          </el-tag>
          <span v-else>{{ row[col.prop] }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
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
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @current-change="load"
        @size-change="search"
      />
    </div>

    <el-dialog v-model="dialogVisible" :title="(editingId ? '编辑' : '新增') + title" width="560px">
      <el-form label-width="110px">
        <el-form-item v-for="field in fields" :key="field.prop" :label="field.label">
          <el-switch v-if="field.type === 'switch'" v-model="form[field.prop]" :active-value="1" :inactive-value="0" />
          <el-select v-else-if="field.type === 'select'" v-model="form[field.prop]" style="width: 100%">
            <el-option v-for="opt in field.options" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
          <el-input v-else-if="field.type === 'textarea'" v-model="form[field.prop]" type="textarea" :rows="3" />
          <el-input-number v-else-if="field.type === 'number'" v-model="form[field.prop]" :min="-1" controls-position="right" />
          <el-input v-else-if="field.type === 'money'" v-model="form[field.prop]" placeholder="单位：元">
            <template #append>元</template>
          </el-input>
          <template v-else-if="field.type === 'image'">
            <el-upload
              :action="uploadUrl"
              :headers="uploadHeaders"
              name="file"
              :show-file-list="false"
              :on-success="(res: any) => onUploaded(res, field)"
            >
              <el-image v-if="form[field.prop]" :src="form[field.prop]" style="width: 90px; height: 90px" fit="cover" />
              <el-button v-else>上传图片</el-button>
            </el-upload>
          </template>
          <el-input v-else v-model="form[field.prop]" :placeholder="field.placeholder" />
          <div v-if="field.tip" class="tip">{{ field.tip }}</div>
        </el-form-item>
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
