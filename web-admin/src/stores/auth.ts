import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authApi, type AdminProfile } from '@/api'
import { clearToken, getToken, setToken } from '@/api/request'

export const ROLE_SUPER = 1

export const useAuthStore = defineStore('auth', () => {
  const profile = ref<AdminProfile | null>(null)

  const isLogged = () => !!getToken()
  const isSuper = () => profile.value?.role === ROLE_SUPER

  async function login(username: string, password: string) {
    const data = await authApi.login(username, password)
    setToken(data.token)
    profile.value = data.admin
    return data
  }

  async function loadProfile() {
    if (!getToken()) return null
    profile.value = await authApi.profile()
    return profile.value
  }

  function logout() {
    clearToken()
    profile.value = null
  }

  return { profile, isLogged, isSuper, login, loadProfile, logout }
})
