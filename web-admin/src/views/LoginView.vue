<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const form = reactive({ username: '', password: '' })

async function submit() {
  if (!form.username || !form.password) {
    ElMessage.warning('请输入账号和密码')
    return
  }

  loading.value = true
  try {
    await auth.login(form.username, form.password)
    ElMessage.success('登录成功')
    router.replace((route.query.redirect as string) || '/dashboard')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <div class="deco" aria-hidden="true">
      <span class="dot d1"></span>
      <span class="dot d2"></span>
      <span class="dot d3"></span>
      <span class="dot d4"></span>
      <span class="dot d5"></span>
      <span class="dot d6"></span>
      <svg class="lines" viewBox="0 0 800 600" preserveAspectRatio="none">
        <line x1="60" y1="480" x2="220" y2="320" />
        <line x1="220" y1="320" x2="120" y2="140" />
        <line x1="220" y1="320" x2="420" y2="220" />
        <line x1="420" y1="220" x2="620" y2="120" />
        <line x1="420" y1="220" x2="560" y2="380" />
        <line x1="60" y1="480" x2="420" y2="220" />
      </svg>
    </div>

    <header class="topbar">
      <div class="logo">
        <span class="logo-mark">66</span>
        <span class="logo-text">六六弗尔豪斯</span>
      </div>
    </header>

    <main class="content">
      <p class="hello">欢迎回来</p>
      <h1 class="title">休闲酒馆管理后台</h1>

      <el-form class="form" label-position="top" @submit.prevent="submit">
        <el-form-item label="账号">
          <el-input v-model="form.username" placeholder="请输入账号" size="large" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input
            v-model="form.password"
            type="password"
            show-password
            size="large"
            placeholder="请输入密码"
            @keyup.enter="submit"
          />
        </el-form-item>
        <el-button type="primary" class="submit" size="large" :loading="loading" @click="submit">登录</el-button>
      </el-form>
    </main>
  </div>
</template>

<style scoped>
.login {
  position: relative;
  height: 100vh;
  overflow: hidden;
  background: radial-gradient(1200px 800px at 20% 20%, #241414 0%, #121212 55%, #0c0c0c 100%);
  color: #f5f5f5;
}

.deco {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.dot {
  position: absolute;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: rgba(212, 175, 55, 0.55);
  box-shadow: 0 0 16px rgba(212, 175, 55, 0.35);
  animation: drift 6s ease-in-out infinite, glow 3s ease-in-out infinite;
}
.d1 { left: 7.5%; top: 80%; animation-delay: 0s, 0.2s; }
.d2 { left: 27.5%; top: 53%; animation-delay: 0.8s, 0.6s; }
.d3 { left: 15%; top: 23%; animation-delay: 1.6s, 1s; }
.d4 { left: 52.5%; top: 37%; animation-delay: 2.4s, 1.4s; }
.d5 { left: 77.5%; top: 20%; animation-delay: 3.2s, 1.8s; }
.d6 { left: 70%; top: 63%; width: 7px; height: 7px; animation-delay: 4s, 2.2s; }
.lines {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.lines line {
  stroke: rgba(212, 175, 55, 0.15);
  stroke-width: 1;
  stroke-dasharray: 6 10;
  animation: flow 6s linear infinite;
}

@keyframes drift {
  0%, 100% { transform: translate(0, 0); }
  50% { transform: translate(6px, -14px); }
}
@keyframes glow {
  0%, 100% { opacity: 0.55; box-shadow: 0 0 10px rgba(212, 175, 55, 0.25); }
  50% { opacity: 1; box-shadow: 0 0 22px rgba(212, 175, 55, 0.6); }
}
@keyframes flow {
  to { stroke-dashoffset: -160; }
}
@media (prefers-reduced-motion: reduce) {
  .dot,
  .lines line {
    animation: none;
  }
}

.topbar {
  position: relative;
  padding: 24px 40px;
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo-mark {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #8b1a1a, #b32626);
  color: #f5f5f5;
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.logo-text {
  font-weight: 600;
  letter-spacing: 1px;
}

.content {
  position: relative;
  max-width: 380px;
  margin: 6vh 0 0 8vw;
  padding: 0 24px;
}
.hello {
  color: #9a9a9a;
  font-size: 16px;
  margin: 0 0 4px;
}
.title {
  font-size: 30px;
  font-weight: 700;
  margin: 0 0 32px;
  background: linear-gradient(90deg, #d4af37, #f3d675);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.form :deep(.el-form-item__label) {
  color: #c9c9c9;
}
.form :deep(.el-input__wrapper) {
  background: rgba(255, 255, 255, 0.06);
  box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.15) inset;
}
.form :deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 1px #d4af37 inset;
}
.form :deep(.el-input__inner) {
  color: #f5f5f5;
}
.submit {
  width: 100%;
  margin-top: 8px;
  border: none;
  background: linear-gradient(90deg, #8b1a1a, #b32626);
}
.submit:hover {
  background: linear-gradient(90deg, #9c1e1e, #c42a2a);
}

@media (max-width: 640px) {
  .content {
    margin-left: 24px;
    max-width: calc(100% - 48px);
  }
  .deco {
    display: none;
  }
}
</style>
