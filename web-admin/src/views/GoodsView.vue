<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Close, Plus } from '@element-plus/icons-vue'
import draggable from 'vuedraggable'
import { ElMessage, ElMessageBox } from 'element-plus'
import { categoryApi, goodsApi, uploadUrl } from '@/api'
import { getToken } from '@/api/request'
import { fen2yuan, yuan2fen } from '@/utils/money'
import { giftConfig, giftFromStorage, giftText, giftToStorage, loadGiftConfig } from '@/utils/gift'
import { drinkCardConfig, drinkCardFromStorage, drinkCardText, drinkCardToStorage, loadDrinkCardConfig } from '@/utils/drinkCard'

const uploadHeaders = { Authorization: `Bearer ${getToken()}` }
const MAX_GALLERY_IMAGES = 4
const LOW_STOCK_THRESHOLD = 5

const categories = ref<any[]>([])
const selectedCategoryId = ref(0)
const statusFilter = ref('')
const keyword = ref('')
const lowStockOnly = ref(false)

const loading = ref(false)
const rows = ref<any[]>([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 20 })

function isLowStock(row: any) {
  return row.stock !== -1 && row.stock < LOW_STOCK_THRESHOLD
}
const lowStockRows = computed(() => rows.value.filter(isLowStock))

const dialogVisible = ref(false)
const editingId = ref(0)
const form = ref<Record<string, any>>({})
/** 拖拽排序需要每张图有稳定 key，内部用 {uid,url} 承载，提交时再还原为纯字符串数组 */
const gallery = ref<{ uid: number; url: string }[]>([])
let galleryUid = 0

onMounted(async () => {
  await Promise.all([loadCategories(), loadGiftConfig(), loadDrinkCardConfig()])
  await load()
})

async function loadCategories() {
  const res = await categoryApi.list({ page: 1, page_size: 100 })
  categories.value = res.list
}

async function load() {
  loading.value = true
  try {
    const res = await goodsApi.list({
      ...query,
      keyword: keyword.value,
      category_id: selectedCategoryId.value,
      status: statusFilter.value,
      low_stock: lowStockOnly.value ? 1 : 0,
    })
    rows.value = res.list
    total.value = res.total
  } finally {
    loading.value = false
  }
}

function selectCategory(id: number) {
  selectedCategoryId.value = id
  query.page = 1
  load()
}

function search() {
  query.page = 1
  load()
}

function toggleLowStock() {
  lowStockOnly.value = !lowStockOnly.value
  query.page = 1
  load()
}

/** 批量分类/批量改库存：共用同一套选中模式与勾选状态，同一时刻只会激活其中一种 */
const classifyMode = ref(false)
const stockMode = ref(false)
const selectedIds = ref<number[]>([])
const classifyDialogVisible = ref(false)
const classifyTargetId = ref<number | ''>('')

function startClassify() {
  classifyMode.value = true
  stockMode.value = false
  selectedIds.value = []
}

function cancelClassify() {
  classifyMode.value = false
  selectedIds.value = []
}

function startStockEdit() {
  stockMode.value = true
  classifyMode.value = false
  selectedIds.value = []
}

function cancelStockEdit() {
  stockMode.value = false
  selectedIds.value = []
}

function toggleSelect(id: number) {
  const index = selectedIds.value.indexOf(id)
  if (index >= 0) {
    selectedIds.value.splice(index, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function toggleSelectAll(checked: boolean) {
  selectedIds.value = checked ? rows.value.map((row) => row.id) : []
}

function openClassifyDialog() {
  if (selectedIds.value.length === 0) {
    ElMessage.warning('请先勾选商品')
    return
  }
  classifyTargetId.value = ''
  classifyDialogVisible.value = true
}

async function confirmClassify() {
  if (!classifyTargetId.value) {
    ElMessage.warning('请选择目标分类')
    return
  }
  await goodsApi.batchCategory(Number(classifyTargetId.value), selectedIds.value)
  ElMessage.success('移动成功')
  classifyDialogVisible.value = false
  cancelClassify()
  load()
}

/** 批量改库存：对话框内每行可单独改库存，也可用顶部"统一设置为"一键填充全部行 */
const stockDialogVisible = ref(false)
const stockRows = ref<{ id: number; name: string; stock: number }[]>([])
const stockBulkValue = ref<number | null>(null)

function openStockDialog() {
  if (selectedIds.value.length === 0) {
    ElMessage.warning('请先勾选商品')
    return
  }
  stockRows.value = rows.value
    .filter((row) => selectedIds.value.includes(row.id))
    .map((row) => ({ id: row.id, name: row.name, stock: row.stock }))
  stockBulkValue.value = null
  stockDialogVisible.value = true
}

function applyBulkStock() {
  if (stockBulkValue.value === null) return
  const value = stockBulkValue.value
  stockRows.value.forEach((item) => {
    item.stock = value
  })
}

async function saveStockDialog() {
  const items = stockRows.value.map((item) => ({ id: item.id, stock: item.stock }))
  await goodsApi.batchStock(items)
  ElMessage.success('库存已更新')
  stockDialogVisible.value = false
  cancelStockEdit()
  load()
}

/** 批量排序：对话框内维护当前分类下的完整顺序，勾选后 上移/下移(单选)、移动到指定位置(可多选)，保存时一次性提交 */
const sortDialogVisible = ref(false)
const sortLoading = ref(false)
const sortList = ref<any[]>([])
const sortSelectedIds = ref<number[]>([])
const sortTargetPosition = ref(1)

async function openSortDialog() {
  if (selectedCategoryId.value <= 0) {
    ElMessage.warning('请先在左侧选择一个具体分类')
    return
  }
  sortLoading.value = true
  sortDialogVisible.value = true
  sortSelectedIds.value = []
  try {
    const res = await goodsApi.list({ page: 1, page_size: 100, category_id: selectedCategoryId.value, status: '', keyword: '' })
    sortList.value = res.list
  } finally {
    sortLoading.value = false
  }
}

function toggleSortSelect(id: number) {
  const index = sortSelectedIds.value.indexOf(id)
  if (index >= 0) {
    sortSelectedIds.value.splice(index, 1)
  } else {
    sortSelectedIds.value.push(id)
  }
}

function sortMoveUp() {
  if (sortSelectedIds.value.length !== 1) {
    ElMessage.warning('请先勾选一个商品')
    return
  }
  const index = sortList.value.findIndex((item) => item.id === sortSelectedIds.value[0])
  if (index <= 0) return
  const [item] = sortList.value.splice(index, 1)
  sortList.value.splice(index - 1, 0, item)
}

function sortMoveDown() {
  if (sortSelectedIds.value.length !== 1) {
    ElMessage.warning('请先勾选一个商品')
    return
  }
  const index = sortList.value.findIndex((item) => item.id === sortSelectedIds.value[0])
  if (index < 0 || index >= sortList.value.length - 1) return
  const [item] = sortList.value.splice(index, 1)
  sortList.value.splice(index + 1, 0, item)
}

function sortMoveToPosition() {
  if (sortSelectedIds.value.length === 0) {
    ElMessage.warning('请先勾选商品')
    return
  }
  const position = Math.max(1, Math.min(sortList.value.length, Number(sortTargetPosition.value) || 1))
  const picked = sortList.value.filter((item) => sortSelectedIds.value.includes(item.id))
  const rest = sortList.value.filter((item) => !sortSelectedIds.value.includes(item.id))
  rest.splice(position - 1, 0, ...picked)
  sortList.value = rest
}

async function saveSortDialog() {
  await goodsApi.batchSort(
    selectedCategoryId.value,
    sortList.value.map((item) => item.id),
  )
  ElMessage.success('排序已保存')
  sortDialogVisible.value = false
  load()
}

const categoryOptions = computed(() => categories.value.map((item: any) => ({ label: item.name, value: item.id })))

function defaultForm() {
  return {
    name: '',
    subtitle: '',
    category_id: categoryOptions.value[0]?.value ?? '',
    code: '',
    cover: '',
    price: 0,
    origin_price: 0,
    unit: '份',
    stock: -1,
    gift_payable: 1,
    gift_amount: 0,
    drink_card_payable: 0,
    drink_card_amount: 0,
    drink_card_gift_amount: 0,
    drink_card_gift_expire_days: 0,
    sort: 0,
    status: 1,
    description: '',
  }
}

function openCreate() {
  editingId.value = 0
  form.value = defaultForm()
  gallery.value = []
  dialogVisible.value = true
}

function openEdit(row: any) {
  editingId.value = row.id
  form.value = {
    name: row.name,
    subtitle: row.subtitle,
    category_id: row.category_id,
    code: row.code,
    cover: row.cover,
    price: fen2yuan(row.price),
    origin_price: fen2yuan(row.origin_price),
    unit: row.unit,
    stock: row.stock,
    gift_payable: row.gift_payable,
    gift_amount: giftFromStorage(row.gift_amount),
    drink_card_payable: row.drink_card_payable,
    drink_card_amount: drinkCardFromStorage(row.drink_card_amount),
    drink_card_gift_amount: drinkCardFromStorage(row.drink_card_gift_amount),
    drink_card_gift_expire_days: row.drink_card_gift_expire_days,
    sort: row.sort,
    status: row.status,
    description: row.description,
  }
  gallery.value = (row.images || []).map((url: string) => ({ uid: ++galleryUid, url }))
  dialogVisible.value = true
}

function onCoverUploaded(response: any) {
  if (response?.code === 0) {
    form.value.cover = response.data.url
  } else {
    ElMessage.error(response?.msg || '上传失败')
  }
}

function onGalleryUploaded(response: any) {
  if (response?.code === 0) {
    gallery.value.push({ uid: ++galleryUid, url: response.data.url })
  } else {
    ElMessage.error(response?.msg || '上传失败')
  }
}

function removeGalleryImage(uid: number) {
  gallery.value = gallery.value.filter((item) => item.uid !== uid)
}

async function submit() {
  if (!form.value.name) {
    ElMessage.warning('请填写商品名称')
    return
  }
  if (!form.value.category_id) {
    ElMessage.warning('请选择所属分类')
    return
  }
  if (form.value.gift_payable === 1 && Number(form.value.gift_amount) <= 0) {
    ElMessage.warning(`请填写使用${giftConfig.displayName}支付需消耗数量`)
    return
  }
  if (form.value.drink_card_payable === 1 && Number(form.value.drink_card_amount) <= 0) {
    ElMessage.warning(`请填写使用${drinkCardConfig.displayName}支付需消耗数量`)
    return
  }

  const payload = {
    name: form.value.name,
    subtitle: form.value.subtitle,
    category_id: form.value.category_id,
    code: form.value.code,
    cover: form.value.cover,
    images: gallery.value.map((item) => item.url),
    price: yuan2fen(form.value.price),
    origin_price: yuan2fen(form.value.origin_price),
    unit: form.value.unit,
    stock: form.value.stock,
    gift_payable: form.value.gift_payable,
    gift_amount: giftToStorage(form.value.gift_amount),
    drink_card_payable: form.value.drink_card_payable,
    drink_card_amount: drinkCardToStorage(form.value.drink_card_amount),
    drink_card_gift_amount: drinkCardToStorage(form.value.drink_card_gift_amount),
    drink_card_gift_expire_days: form.value.drink_card_gift_expire_days,
    sort: form.value.sort,
    status: form.value.status,
    description: form.value.description,
  }

  if (editingId.value) {
    await goodsApi.update(editingId.value, payload)
  } else {
    await goodsApi.create(payload)
  }

  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

function remove(row: any) {
  ElMessageBox.confirm(`确定删除「${row.name}」吗？`, '提示', { type: 'warning' })
    .then(async () => {
      await goodsApi.remove(row.id)
      ElMessage.success('删除成功')
      load()
    })
    .catch(() => {})
}

function toggleStatus(row: any) {
  const next = row.status === 1 ? 0 : 1
  goodsApi
    .changeStatus(row.id, next)
    .then(() => {
      row.status = next
      ElMessage.success('操作成功')
    })
    .catch(() => {})
}

function moveUp(row: any) {
  goodsApi
    .move(row.id, 'up')
    .then(() => load())
    .catch(() => {})
}

function moveDown(row: any) {
  goodsApi
    .move(row.id, 'down')
    .then(() => load())
    .catch(() => {})
}
</script>

<template>
  <div class="page goods-page">
    <div class="layout">
      <aside class="sidebar">
        <div class="sidebar-item" :class="{ active: selectedCategoryId === 0 }" @click="selectCategory(0)">全部分类</div>
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="sidebar-item"
          :class="{ active: selectedCategoryId === cat.id }"
          @click="selectCategory(cat.id)"
        >
          {{ cat.name }}
        </div>
      </aside>

      <div class="content">
        <el-alert
          v-if="lowStockRows.length > 0"
          class="low-stock-alert"
          type="warning"
          show-icon
          :closable="false"
          :title="`有 ${lowStockRows.length} 个商品库存不足${LOW_STOCK_THRESHOLD}件：${lowStockRows.map((r) => r.name).join('、')}`"
        />
        <div class="toolbar" v-if="!classifyMode && !stockMode">
          <el-button type="primary" @click="openCreate">新增商品</el-button>
          <el-radio-group v-model="statusFilter" @change="search">
            <el-radio-button label="">全部</el-radio-button>
            <el-radio-button label="1">已上架</el-radio-button>
            <el-radio-button label="0">已下架</el-radio-button>
          </el-radio-group>
          <el-button @click="openSortDialog">批量排序</el-button>
          <el-button @click="startClassify">批量分类</el-button>
          <el-button :type="lowStockOnly ? 'danger' : 'default'" @click="toggleLowStock">{{ lowStockOnly ? '取消缺货筛选' : '缺货商品' }}</el-button>
          <el-button v-if="lowStockOnly" @click="startStockEdit">批量改库存</el-button>
          <el-input v-model="keyword" placeholder="搜索商品名称/副标题" clearable style="width: 220px" @keyup.enter="search" />
          <el-button @click="search">查询</el-button>
        </div>
        <div class="toolbar" v-else-if="classifyMode">
          <el-checkbox :model-value="selectedIds.length > 0 && selectedIds.length === rows.length" @change="toggleSelectAll">全选</el-checkbox>
          <span class="tip">已选 {{ selectedIds.length }} 个</span>
          <el-button type="primary" @click="openClassifyDialog">移动到分类</el-button>
          <el-button @click="cancelClassify">取消</el-button>
        </div>
        <div class="toolbar" v-else>
          <el-checkbox :model-value="selectedIds.length > 0 && selectedIds.length === rows.length" @change="toggleSelectAll">全选</el-checkbox>
          <span class="tip">已选 {{ selectedIds.length }} 个</span>
          <el-button type="primary" @click="openStockDialog">设置库存</el-button>
          <el-button @click="cancelStockEdit">取消</el-button>
        </div>

        <div class="grid" v-loading="loading">
          <div class="card" :class="{ 'is-off-sale': row.status !== 1 }" v-for="row in rows" :key="row.id">
            <span class="low-stock-badge" v-if="isLowStock(row)">缺货</span>
            <el-checkbox
              v-if="classifyMode || stockMode"
              class="select-box"
              :model-value="selectedIds.includes(row.id)"
              @change="toggleSelect(row.id)"
            />
            <div class="top">
              <el-image :src="row.cover" fit="cover" class="cover" />
              <div class="body">
                <div class="name">{{ row.name }}</div>
                <div class="subtitle" v-if="row.subtitle">{{ row.subtitle }}</div>
                <div class="code" v-if="row.code">编码：{{ row.code }}</div>
                <div class="price-row">
                  <span class="money">¥{{ fen2yuan(row.price) }}</span>
                  <span class="origin" v-if="row.origin_price">¥{{ fen2yuan(row.origin_price) }}</span>
                </div>
                <div class="pay-tags" v-if="row.gift_payable === 1 || row.drink_card_payable === 1">
                  <span class="pay-tag pay-tag--gift" v-if="row.gift_payable === 1">可用{{ giftConfig.displayName }}：{{ giftText(row.gift_amount) }}</span>
                  <span class="pay-tag pay-tag--drink-card" v-if="row.drink_card_payable === 1">可用{{ drinkCardConfig.displayName }}：{{ drinkCardText(row.drink_card_amount) }}</span>
                </div>
                <div class="meta">库存 {{ row.stock === -1 ? '不限' : row.stock }} · 销量 {{ row.sales }}</div>
              </div>
            </div>
            <div class="actions" v-if="!classifyMode && !stockMode">
              <el-button size="small" @click="moveUp(row)">前移</el-button>
              <el-button size="small" @click="moveDown(row)">后移</el-button>
              <el-button size="small" :type="row.status === 1 ? 'info' : 'success'" @click="toggleStatus(row)">
                {{ row.status === 1 ? '下架' : '上架' }}
              </el-button>
              <el-button size="small" type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="remove(row)">删除</el-button>
            </div>
          </div>
          <el-empty v-if="!loading && rows.length === 0" description="暂无商品" />
        </div>

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
      </div>
    </div>

    <el-drawer v-model="dialogVisible" :title="(editingId ? '编辑' : '新增') + '商品'" direction="rtl" size="640px" class="goods-edit-drawer">
      <el-form label-width="150px">
        <el-form-item label="商品名称">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="副标题/规格">
          <el-input v-model="form.subtitle" />
        </el-form-item>
        <el-form-item label="所属分类">
          <el-select v-model="form.category_id" style="width: 100%">
            <el-option v-for="opt in categoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="外部编码">
          <el-input v-model="form.code" placeholder="选填，如第三方POS/供应链编码，需保证唯一" />
        </el-form-item>
        <el-form-item label="封面图">
          <el-upload :action="uploadUrl" :headers="uploadHeaders" name="file" :show-file-list="false" :on-success="onCoverUploaded">
            <el-image v-if="form.cover" :src="form.cover" style="width: 90px; height: 90px" fit="cover" />
            <el-button v-else>上传封面</el-button>
          </el-upload>
        </el-form-item>
        <el-form-item label="轮播图">
          <div>
            <draggable v-model="gallery" item-key="uid" class="gallery-list" :animation="150">
              <template #item="{ element }">
                <div class="gallery-item">
                  <el-image :src="element.url" fit="cover" />
                  <el-icon class="gallery-remove" @click="removeGalleryImage(element.uid)"><Close /></el-icon>
                </div>
              </template>
            </draggable>
            <el-upload
              v-if="gallery.length < MAX_GALLERY_IMAGES"
              :action="uploadUrl"
              :headers="uploadHeaders"
              name="file"
              :show-file-list="false"
              :on-success="onGalleryUploaded"
            >
              <div class="gallery-add"><el-icon><Plus /></el-icon></div>
            </el-upload>
            <div class="tip">最多 {{ MAX_GALLERY_IMAGES }} 张，可拖拽调整顺序</div>
          </div>
        </el-form-item>
        <el-form-item label="售价">
          <el-input v-model="form.price" placeholder="单位：元"><template #append>元</template></el-input>
        </el-form-item>
        <el-form-item label="划线价">
          <el-input v-model="form.origin_price" placeholder="单位：元"><template #append>元</template></el-input>
        </el-form-item>
        <el-form-item label="单位">
          <el-input v-model="form.unit" />
        </el-form-item>
        <el-form-item label="库存">
          <el-input-number v-model="form.stock" :min="-1" controls-position="right" />
          <div class="tip">-1 表示不限库存</div>
        </el-form-item>
        <el-form-item :label="`可用${giftConfig.displayName}支付`">
          <el-switch v-model="form.gift_payable" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item v-if="form.gift_payable === 1" :label="`使用${giftConfig.displayName}支付需消耗数量`">
          <el-input v-model="form.gift_amount" :placeholder="giftConfig.unit === '张' ? '整数张数' : '单位：元'">
            <template #append>{{ giftConfig.unit }}</template>
          </el-input>
        </el-form-item>
        <el-form-item :label="`可用${drinkCardConfig.displayName}支付`">
          <el-switch v-model="form.drink_card_payable" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item v-if="form.drink_card_payable === 1" :label="`使用${drinkCardConfig.displayName}支付需消耗数量`">
          <el-input v-model="form.drink_card_amount" :placeholder="drinkCardConfig.unit === '张' ? '整数张数' : '单位：元'">
            <template #append>{{ drinkCardConfig.unit }}</template>
          </el-input>
        </el-form-item>
        <el-form-item :label="`购买赠送${drinkCardConfig.displayName}数量`">
          <el-input v-model="form.drink_card_gift_amount" :placeholder="drinkCardConfig.unit === '张' ? '整数张数' : '单位：元'">
            <template #append>{{ drinkCardConfig.unit }}</template>
          </el-input>
          <div class="tip">0 表示不赠送</div>
        </el-form-item>
        <el-form-item v-if="Number(form.drink_card_gift_amount) > 0" :label="`赠送${drinkCardConfig.displayName}有效天数`">
          <el-input-number v-model="form.drink_card_gift_expire_days" :min="0" controls-position="right" />
          <div class="tip">0 表示永久有效</div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" controls-position="right" />
        </el-form-item>
        <el-form-item label="上架">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="商品详情">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-drawer>

    <el-dialog v-model="classifyDialogVisible" title="移动到分类" width="360px">
      <p class="tip">已选中 {{ selectedIds.length }} 个商品</p>
      <el-select v-model="classifyTargetId" placeholder="请选择目标分类" style="width: 100%">
        <el-option v-for="opt in categoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
      </el-select>
      <template #footer>
        <el-button @click="classifyDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmClassify">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="stockDialogVisible" title="批量改库存" width="480px">
      <div class="bulk-stock-row">
        <el-input-number v-model="stockBulkValue" :min="-1" controls-position="right" placeholder="统一设置为" />
        <el-button @click="applyBulkStock">应用到全部</el-button>
      </div>
      <div class="tip">-1 表示不限库存；也可在下方逐个修改单个商品的库存</div>
      <el-table :data="stockRows" border max-height="360">
        <el-table-column prop="name" label="商品名称" />
        <el-table-column label="库存" width="160">
          <template #default="{ row }">
            <el-input-number v-model="row.stock" :min="-1" controls-position="right" size="small" />
          </template>
        </el-table-column>
      </el-table>
      <template #footer>
        <el-button @click="stockDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveStockDialog">保存</el-button>
      </template>
    </el-dialog>


    <el-dialog v-model="sortDialogVisible" title="批量排序" width="560px">
      <el-table :data="sortList" v-loading="sortLoading" border max-height="420" @row-click="(row: any) => toggleSortSelect(row.id)">
        <el-table-column width="50">
          <template #default="{ row }">
            <el-checkbox :model-value="sortSelectedIds.includes(row.id)" @change="toggleSortSelect(row.id)" @click.stop />
          </template>
        </el-table-column>
        <el-table-column type="index" label="位置" width="60" />
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="商品名称" />
      </el-table>
      <div class="sort-controls">
        <el-button @click="sortMoveUp">上移</el-button>
        <el-button @click="sortMoveDown">下移</el-button>
        <el-input-number v-model="sortTargetPosition" :min="1" :max="Math.max(1, sortList.length)" controls-position="right" style="width: 120px" />
        <el-button @click="sortMoveToPosition">移动到该位置</el-button>
      </div>
      <template #footer>
        <el-button @click="sortDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveSortDialog">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.sidebar {
  width: 160px;
  flex-shrink: 0;
  background: #fff;
  overflow: hidden;
}

.sidebar-item {
  height: 40px;
  line-height: 40px;
  padding: 0 16px;
  cursor: pointer;
  font-size: 14px;
  border-bottom: 1px solid #f0f0f0;
}

.sidebar-item:hover {
  background: #f5f7fa;
}

.sidebar-item.active {
  background: rgba(90, 118, 148, 0.35);
  color: #1f2329;
  font-weight: 600;
}

.content {
  flex: 1;
  min-width: 0;
}

.grid {
  display: grid;
  /* 参考站点是横向行式列表，minmax 设大一点：宽屏可容纳多列，宽度不够时自动降为单列(一个商品一行) */
  grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
  gap: 12px;
  min-height: 120px;
}

.card {
  position: relative;
  background: #fff;
  padding: 12px 16px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  overflow: hidden;
}

.card.is-off-sale {
  opacity: 0.5;
}

.card.is-off-sale::after {
  content: '已下架';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-20deg);
  font-size: 42px;
  font-weight: 700;
  color: rgba(0, 0, 0, 0.2);
  white-space: nowrap;
  pointer-events: none;
  z-index: 2;
}

.low-stock-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: #f56c6c;
  color: #fff;
  font-size: 12px;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 10px;
  z-index: 3;
}

.low-stock-alert {
  margin-bottom: 12px;
}

.bulk-stock-row {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.select-box {
  flex-shrink: 0;
}

.top {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 260px;
}

.cover {
  width: 72px;
  height: 72px;
  flex-shrink: 0;
  background: #f5f6f8;
}

.body {
  flex: 1;
  min-width: 0;
}

.name {
  font-weight: 600;
  font-size: 14px;
}

.subtitle {
  color: #86909c;
  font-size: 12px;
  margin-top: 2px;
}

.code {
  color: #86909c;
  font-size: 12px;
  margin-top: 2px;
}

.price-row {
  margin-top: 6px;
}

.price-row .origin {
  margin-left: 8px;
  color: #86909c;
  text-decoration: line-through;
  font-size: 12px;
}

.pay-tags {
  margin-top: 4px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pay-tag {
  font-size: 12px;
  padding: 1px 6px;
}

.pay-tag--gift {
  color: #d4af37;
  background: #fdf6e3;
}

.pay-tag--drink-card {
  color: #2f9e6f;
  background: #e6f7f0;
}

.meta {
  margin-top: 6px;
  color: #86909c;
  font-size: 12px;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
  flex-shrink: 0;
}

.gallery-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.gallery-item {
  position: relative;
  width: 72px;
  height: 72px;
}

.gallery-item .el-image {
  width: 100%;
  height: 100%;
  cursor: grab;
}

.gallery-remove {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #f56c6c;
  color: #fff;
  border-radius: 50%;
  padding: 2px;
  cursor: pointer;
  font-size: 12px;
}

.gallery-add {
  width: 72px;
  height: 72px;
  border: 1px dashed #d9d9d9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #8a8a8a;
  margin-top: 8px;
}

.tip {
  color: #86909c;
  font-size: 12px;
  margin-top: 4px;
}

.sort-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
}

:deep(.el-button) {
  border-radius: 0;
}

:deep(.el-radio-button__inner) {
  border-radius: 0 !important;
}

@media (max-width: 768px) {
  .goods-edit-drawer :deep(.el-drawer) {
    width: 92% !important;
  }
}
</style>
