<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Fold, Expand } from '@element-plus/icons-vue'
import IconOverview from '@/components/icons/IconOverview.vue'
import IconOrders from '@/components/icons/IconOrders.vue'
import IconVerify from '@/components/icons/IconVerify.vue'
import IconWineStorage from '@/components/icons/IconWineStorage.vue'
import IconMembers from '@/components/icons/IconMembers.vue'
import IconGoods from '@/components/icons/IconGoods.vue'
import IconCategory from '@/components/icons/IconCategory.vue'
import IconTables from '@/components/icons/IconTables.vue'
import IconRecharge from '@/components/icons/IconRecharge.vue'
import IconExchange from '@/components/icons/IconExchange.vue'
import IconBanner from '@/components/icons/IconBanner.vue'
import IconPrinter from '@/components/icons/IconPrinter.vue'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconAdmin from '@/components/icons/IconAdmin.vue'
import { authApi } from '@/api'
import { ROLE_SUPER, useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

interface MenuItem {
  path: string
  title: string
  icon: unknown
  super?: boolean
}

// 无 title 的分组渲染为顶部平铺项，有 title 的分组渲染为 el-menu-item-group
const menuGroups: { title: string; items: MenuItem[] }[] = [
  {
    title: '',
    items: [
      { path: '/dashboard', title: '数据概览', icon: IconOverview },
      { path: '/orders', title: '订单管理', icon: IconOrders },
      { path: '/verify', title: '店员核销', icon: IconVerify },
      { path: '/wine', title: '存酒管理', icon: IconWineStorage },
    ],
  },
  {
    title: '会员运营',
    items: [
      { path: '/members', title: '会员管理', icon: IconMembers, super: true },
      { path: '/recharge-packages', title: '充值套餐', icon: IconRecharge, super: true },
      { path: '/exchange-goods', title: '兑换商品', icon: IconExchange, super: true },
    ],
  },
  {
    title: '商品管理',
    items: [
      { path: '/goods', title: '商品管理', icon: IconGoods, super: true },
      { path: '/categories', title: '商品分类', icon: IconCategory, super: true },
      { path: '/tables', title: '桌号管理', icon: IconTables, super: true },
      { path: '/banners', title: '轮播图', icon: IconBanner, super: true },
    ],
  },
  {
    title: '系统设置',
    items: [
      { path: '/printers', title: '设备管理', icon: IconPrinter, super: true },
      { path: '/settings', title: '系统配置', icon: IconSettings, super: true },
      { path: '/admin-users', title: '账号管理', icon: IconAdmin, super: true },
    ],
  },
]

const visibleMenuGroups = computed(() =>
  menuGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((menu) => !menu.super || auth.profile?.role === ROLE_SUPER),
    }))
    .filter((group) => group.items.length > 0),
)

const passwordVisible = ref(false)
const passwordForm = ref({ old_password: '', new_password: '' })

const collapsed = ref(localStorage.getItem('nf_admin_aside_collapsed') === '1')

function toggleCollapse() {
  collapsed.value = !collapsed.value
  localStorage.setItem('nf_admin_aside_collapsed', collapsed.value ? '1' : '0')
}

onMounted(() => {
  if (!auth.profile) auth.loadProfile()
})

async function submitPassword() {
  await authApi.changePassword(passwordForm.value.old_password, passwordForm.value.new_password)
  ElMessage.success('密码修改成功，请重新登录')
  passwordVisible.value = false
  logout()
}

function logout() {
  ElMessageBox.confirm('确定退出登录吗？', '提示', { type: 'warning' })
    .then(() => {
      auth.logout()
      router.push('/login')
    })
    .catch(() => {})
}
</script>

<template>
  <el-container class="layout">
    <el-aside :width="collapsed ? '64px' : '200px'" class="aside" :class="{ 'is-collapsed': collapsed }">
      <div class="logo">{{ collapsed ? '六' : '六六弗尔豪斯' }}</div>
      <el-menu
        :default-active="route.path"
        :collapse="collapsed"
        :collapse-transition="false"
        router
        background-color="#1f2329"
        text-color="#c9cdd4"
        active-text-color="#c3d6ea"
      >
        <template v-for="group in visibleMenuGroups" :key="group.title || '__top'">
          <el-menu-item-group v-if="group.title" :title="group.title">
            <el-menu-item v-for="menu in group.items" :key="menu.path" :index="menu.path">
              <el-icon><component :is="menu.icon" /></el-icon>
              <span>{{ menu.title }}</span>
            </el-menu-item>
          </el-menu-item-group>
          <template v-else>
            <el-menu-item v-for="menu in group.items" :key="menu.path" :index="menu.path">
              <el-icon><component :is="menu.icon" /></el-icon>
              <span>{{ menu.title }}</span>
            </el-menu-item>
          </template>
        </template>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="toggleCollapse"><component :is="collapsed ? Expand : Fold" /></el-icon>
          <div class="title">{{ route.meta.title || '管理后台' }}</div>
        </div>
        <el-dropdown>
          <span class="user">
            {{ auth.profile?.real_name || auth.profile?.username }}
            <el-tag size="small" :type="auth.profile?.role === 1 ? 'danger' : 'info'">
              {{ auth.profile?.role === 1 ? '超级管理员' : '店员' }}
            </el-tag>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item @click="passwordVisible = true">修改密码</el-dropdown-item>
              <el-dropdown-item divided @click="logout">退出登录</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </el-header>

      <el-main>
        <RouterView />
      </el-main>
    </el-container>
  </el-container>

  <el-dialog v-model="passwordVisible" title="修改密码" width="420px">
    <el-form label-width="90px">
      <el-form-item label="原密码">
        <el-input v-model="passwordForm.old_password" type="password" show-password />
      </el-form-item>
      <el-form-item label="新密码">
        <el-input v-model="passwordForm.new_password" type="password" show-password placeholder="6-32 位" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="passwordVisible = false">取消</el-button>
      <el-button type="primary" @click="submitPassword">确定</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.layout {
  height: 100vh;
}
.aside {
  background: #1f2329;
  overflow-y: auto;
  overflow-x: hidden;
  transition: width 0.2s;
}
.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  color: #d4af37;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 1px;
  overflow: hidden;
  white-space: nowrap;
}
.aside :deep(.el-menu) {
  border-right: none;
}
.aside :deep(.el-menu-item) {
  height: 40px;
  line-height: 40px;
  padding-left: 12px !important;
  margin: 2px 8px;
  width: auto;
  font-size: 14px;
  font-weight: 400;
}
.aside.is-collapsed :deep(.el-menu-item) {
  margin: 2px 4px;
  padding: 0 !important;
  display: flex;
  align-items: center;
  justify-content: center;
}
.aside :deep(.el-menu-item span) {
  font-size: 14px;
}
.aside :deep(.el-menu-item .el-icon) {
  font-size: 18px;
  margin-right: 10px;
  transition: font-size 0.2s;
}
.aside.is-collapsed :deep(.el-menu-item .el-icon) {
  font-size: 26px;
  margin-right: 0;
}
.aside :deep(.el-menu-item:hover) {
  background-color: rgba(255, 255, 255, 0.08) !important;
  color: #e5e6eb;
}
.aside :deep(.el-menu-item.is-active) {
  background: rgba(90, 118, 148, 0.35) !important;
}
.aside :deep(.el-menu-item.is-active:hover) {
  background: rgba(90, 118, 148, 0.45) !important;
}
.aside :deep(.el-menu-item-group__title) {
  padding: 12px 20px 4px;
  font-size: 12px;
  line-height: 1;
  color: #6b7280;
  letter-spacing: 0.5px;
  white-space: nowrap;
  overflow: hidden;
}
.aside.is-collapsed :deep(.el-menu-item span) {
  display: none;
}
.aside.is-collapsed :deep(.el-menu-item-group__title) {
  display: none;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-bottom: 1px solid #e5e6eb;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.collapse-btn {
  cursor: pointer;
  font-size: 18px;
}
.title {
  font-size: 16px;
  font-weight: 600;
}
.user {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
