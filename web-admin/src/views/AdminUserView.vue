<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { adminUserApi } from '@/api'

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20 })

const dialogVisible = ref(false)
const editingId = ref(0)
const form = reactive({ username: '', password: '', real_name: '', phone: '', role: 2, status: 1 })

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await adminUserApi.list(query)
    rows.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = 0
  Object.assign(form, { username: '', password: '', real_name: '', phone: '', role: 2, status: 1 })
  dialogVisible.value = true
}

function openEdit(row: any) {
  editingId.value = row.id
  Object.assign(form, {
    username: row.username,
    password: '',
    real_name: row.real_name,
    phone: row.phone,
    role: row.role,
    status: row.status,
  })
  dialogVisible.value = true
}

async function submit() {
  if (editingId.value) {
    const payload: Record<string, any> = {
      real_name: form.real_name,
      phone: form.phone,
      role: form.role,
      status: form.status,
    }
    if (form.password) payload.password = form.password
    await adminUserApi.update(editingId.value, payload)
  } else {
    if (!form.username || !form.password) {
      ElMessage.warning('请填写账号和密码')
      return
    }
    await adminUserApi.create({ ...form })
  }

  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

function remove(row: any) {
  ElMessageBox.confirm(`确定删除账号「${row.username}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await adminUserApi.remove(row.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}
</script>

<template>
  <div class="page">
    <div class="toolbar">
      <el-button type="primary" @click="openCreate">新增账号</el-button>
    </div>

    <el-table :data="rows" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="username" label="账号" width="160" />
      <el-table-column prop="real_name" label="姓名" width="140" />
      <el-table-column prop="phone" label="手机号" width="140" />
      <el-table-column label="角色" width="130">
        <template #default="{ row }">
          <el-tag :type="row.role === 1 ? 'danger' : 'info'">{{ row.role === 1 ? '超级管理员' : '店员' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '正常' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login_at" label="最后登录" width="180" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination">
      <el-pagination v-model:current-page="query.page" :page-size="query.page_size" :total="total" layout="total, prev, pager, next" @current-change="load" />
    </div>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑账号' : '新增账号'" width="480px">
      <el-form label-width="90px">
        <el-form-item label="账号">
          <el-input v-model="form.username" :disabled="!!editingId" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? '留空表示不修改' : '6-32 位'" />
        </el-form-item>
        <el-form-item label="姓名"><el-input v-model="form.real_name" /></el-form-item>
        <el-form-item label="手机号"><el-input v-model="form.phone" /></el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role">
            <el-option label="超级管理员" :value="1" />
            <el-option label="店员" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>
