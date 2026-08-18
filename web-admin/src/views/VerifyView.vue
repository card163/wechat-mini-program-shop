<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { verifyApi } from '@/api'

const scanForm = reactive({ scene: '' })
const scannedMember = ref<any>(null)

const storeForm = reactive({
  member_id: 0,
  wine_name: '',
  spec: '',
  unit: '瓶',
  total_qty: 1,
  expire_days: undefined as number | undefined,
  remark: '',
})

const takeNo = ref('')
const recordNo = ref('')
const lastResult = ref('')

async function scan() {
  if (!scanForm.scene) {
    ElMessage.warning('请输入或扫描存酒码')
    return
  }
  const res = await verifyApi.scanWine(scanForm.scene.trim())
  scannedMember.value = res.member
  storeForm.member_id = res.member.id
}

async function storeWine() {
  if (!storeForm.member_id) {
    ElMessage.warning('请先扫描会员存酒码')
    return
  }
  if (!storeForm.wine_name) {
    ElMessage.warning('请填写酒名')
    return
  }

  const payload: Record<string, any> = { ...storeForm }
  if (payload.expire_days === undefined || payload.expire_days === null) delete payload.expire_days

  const storage = await verifyApi.storeWine(payload)
  ElMessage.success('登记成功')
  lastResult.value = `已为会员 ${scannedMember.value?.nickname} 登记 ${storage.wine_name} x${storage.total_qty}`
  storeForm.wine_name = ''
  storeForm.spec = ''
  storeForm.total_qty = 1
  storeForm.remark = ''
}

async function verifyTake() {
  if (!takeNo.value) return
  const res = await verifyApi.verifyWineTake(takeNo.value.trim())
  ElMessage.success('核销成功')
  lastResult.value = `取酒核销成功：${res.wine_name} x${res.quantity}，剩余 ${res.remain_qty}`
  takeNo.value = ''
}

async function verifyExchange() {
  if (!recordNo.value) return
  const res = await verifyApi.verifyExchange(recordNo.value.trim())
  ElMessage.success('核销成功')
  lastResult.value = `兑换核销成功：${res.goods_name}`
  recordNo.value = ''
}
</script>

<template>
  <div class="page">
    <el-alert v-if="lastResult" :title="lastResult" type="success" show-icon style="margin-bottom: 16px" />

    <el-row :gutter="16">
      <el-col :span="12">
        <el-card>
          <template #header>1. 扫会员存酒码</template>
          <el-input v-model="scanForm.scene" placeholder="扫码枪扫描或粘贴 scene" @keyup.enter="scan">
            <template #append><el-button @click="scan">解析</el-button></template>
          </el-input>

          <el-descriptions v-if="scannedMember" :column="1" border style="margin-top: 16px">
            <el-descriptions-item label="会员ID">{{ scannedMember.id }}</el-descriptions-item>
            <el-descriptions-item label="昵称">{{ scannedMember.nickname }}</el-descriptions-item>
            <el-descriptions-item label="手机号">{{ scannedMember.phone || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <el-card style="margin-top: 16px">
          <template #header>2. 登记存酒</template>
          <el-form label-width="90px">
            <el-form-item label="酒名"><el-input v-model="storeForm.wine_name" /></el-form-item>
            <el-form-item label="规格"><el-input v-model="storeForm.spec" placeholder="如 700ml" /></el-form-item>
            <el-form-item label="单位"><el-input v-model="storeForm.unit" /></el-form-item>
            <el-form-item label="数量"><el-input-number v-model="storeForm.total_qty" :min="1" :max="99" /></el-form-item>
            <el-form-item label="保存天数">
              <el-input-number v-model="storeForm.expire_days" :min="0" placeholder="留空取默认" />
            </el-form-item>
            <el-form-item label="备注"><el-input v-model="storeForm.remark" /></el-form-item>
            <el-button type="primary" @click="storeWine">确认登记</el-button>
          </el-form>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card>
          <template #header>核销取酒码</template>
          <el-input v-model="takeNo" placeholder="扫描或输入取酒码" @keyup.enter="verifyTake">
            <template #append><el-button @click="verifyTake">核销</el-button></template>
          </el-input>
        </el-card>

        <el-card style="margin-top: 16px">
          <template #header>核销兑换码</template>
          <el-input v-model="recordNo" placeholder="扫描或输入兑换码" @keyup.enter="verifyExchange">
            <template #append><el-button @click="verifyExchange">核销</el-button></template>
          </el-input>
        </el-card>

        <el-card style="margin-top: 16px">
          <template #header>操作提示</template>
          <div class="tip">1. 会员在小程序「我的存酒 - 存酒码」出示二维码，扫码后自动带出会员信息。</div>
          <div class="tip">2. 取酒码由会员在小程序发起，具有时效，过期需重新生成。</div>
          <div class="tip">3. 核销操作不可撤销，请确认实物已交付会员。</div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<style scoped>
.tip {
  color: #4e5969;
  line-height: 24px;
}
</style>
