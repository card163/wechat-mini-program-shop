<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Odometer,
  List,
  Checked,
  GobletFull,
  User,
  Goods,
  Menu as MenuIcon,
  Grid,
  Wallet,
  Present,
  Picture,
  Printer,
  Setting,
  UserFilled,
} from '@element-plus/icons-vue'
import { authApi } from '@/api'
import { ROLE_SUPER, useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const menus = [
  { path: '/dashboard', title: '数据概览', icon: Odometer },
  { path: '/orders', title: '订单管理', icon: List },
  { path: '/verify', title: '店员核销', icon: Checked },
  { path: '/wine', title: '存酒管理', icon: GobletFull },
  { path: '/members', title: '会员管理', icon: User, super: true },
  { path: '/goods', title: '商品管理', icon: Goods, super: true },
  { path: '/categories', title: '商品分类', icon: MenuIcon, super: true },
  { path: '/tables', title: '桌号管理', icon: Grid, super: true },
  { path: '/recharge-packages', title: '充值套餐', icon: Wallet, super: true },
  { path: '/exchange-goods', title: '兑换商品', icon: Present, super: true },
  { path: '/banners', title: '轮播图', icon: Picture, super: true },
  { path: '/printers', title: '打印机管理', icon: Printer, super: true },
  { path: '/settings', title: '系统配置', icon: Setting, super: true },
  { path: '/admin-users', title: '账号管理', icon: UserFilled, super: true },
]

const visibleMenus = computed(() =>
  menus.filter((menu) => !menu.super || auth.profile?.role === ROLE_SUPER),
)

const passwordVisible = ref(false)
const passwordForm = ref({ old_password: '', new_password: '' })

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
    <el-aside width="200px" class="aside">
      <div class="logo">六六弗尔豪斯</div>
      <el-menu :default-active="route.path" router background-color="#1f2329" text-color="#c9cdd4" active-text-color="#d4af37">
        <el-menu-item v-for="menu in visibleMenus" :key="menu.path" :index="menu.path">
          <el-icon><component :is="menu.icon" /></el-icon>
          <span>{{ menu.title }}</span>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="title">{{ route.meta.title || '管理后台' }}</div>
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
}
.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  color: #d4af37;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 1px;
}
.aside :deep(.el-menu) {
  border-right: none;
}
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-bottom: 1px solid #e5e6eb;
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
