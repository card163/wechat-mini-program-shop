import { createRouter, createWebHashHistory, type RouteRecordRaw } from 'vue-router'
import { getToken } from '@/api/request'
import { ROLE_SUPER, useAuthStore } from '@/stores/auth'
import Layout from '@/layout/BasicLayout.vue'

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    children: [
      { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { title: '数据概览' } },
      { path: 'orders', name: 'orders', component: () => import('@/views/OrderView.vue'), meta: { title: '订单管理' } },
      { path: 'verify', name: 'verify', component: () => import('@/views/VerifyView.vue'), meta: { title: '店员核销' } },
      { path: 'wine', name: 'wine', component: () => import('@/views/WineView.vue'), meta: { title: '存酒管理' } },
      { path: 'members', name: 'members', component: () => import('@/views/MemberView.vue'), meta: { title: '会员管理', super: true } },
      { path: 'goods', name: 'goods', component: () => import('@/views/GoodsView.vue'), meta: { title: '商品管理', super: true } },
      { path: 'categories', name: 'categories', component: () => import('@/views/CategoryView.vue'), meta: { title: '商品分类', super: true } },
      { path: 'tables', name: 'tables', component: () => import('@/views/TableView.vue'), meta: { title: '桌号管理', super: true } },
      { path: 'recharge-packages', name: 'recharge-packages', component: () => import('@/views/RechargePackageView.vue'), meta: { title: '充值套餐', super: true } },
      { path: 'exchange-goods', name: 'exchange-goods', component: () => import('@/views/ExchangeGoodsView.vue'), meta: { title: '兑换商品', super: true } },
      { path: 'banners', name: 'banners', component: () => import('@/views/BannerView.vue'), meta: { title: '轮播图', super: true } },
      { path: 'printers', name: 'printers', component: () => import('@/views/PrinterView.vue'), meta: { title: '打印机管理', super: true } },
      { path: 'settings', name: 'settings', component: () => import('@/views/SettingView.vue'), meta: { title: '系统配置', super: true } },
      { path: 'admin-users', name: 'admin-users', component: () => import('@/views/AdminUserView.vue'), meta: { title: '账号管理', super: true } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach(async (to) => {
  if (to.meta.public) return true

  if (!getToken()) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  const auth = useAuthStore()
  if (!auth.profile) {
    try {
      await auth.loadProfile()
    } catch {
      return { path: '/login' }
    }
  }

  if (to.meta.super && auth.profile?.role !== ROLE_SUPER) {
    return { path: '/dashboard' }
  }

  return true
})

export default router
