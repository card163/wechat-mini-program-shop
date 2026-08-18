import axios, { type AxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'

export interface ApiResult<T = any> {
  code: number
  msg: string
  data: T
}

export interface PageResult<T> {
  list: T[]
  total: number
  page: number
  page_size: number
}

const TOKEN_KEY = 'nf_admin_token'

export const getToken = (): string => localStorage.getItem(TOKEN_KEY) || ''
export const setToken = (token: string): void => localStorage.setItem(TOKEN_KEY, token)
export const clearToken = (): void => localStorage.removeItem(TOKEN_KEY)

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '',
  timeout: 15000,
})

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => {
    const result = response.data as ApiResult

    if (result.code === 0) {
      return result.data
    }

    if (result.code === 401) {
      clearToken()
      if (location.hash !== '#/login') {
        location.hash = '#/login'
      }
    }

    ElMessage.error(result.msg || '操作失败')
    return Promise.reject(Object.assign(new Error(result.msg), { code: result.code }))
  },
  (error) => {
    ElMessage.error('网络异常，请稍后再试')
    return Promise.reject(error)
  },
)

export const request = <T = any>(config: AxiosRequestConfig): Promise<T> =>
  http.request(config) as unknown as Promise<T>

export const get = <T = any>(url: string, params?: Record<string, any>): Promise<T> =>
  request<T>({ url, method: 'GET', params })

export const post = <T = any>(url: string, data?: Record<string, any>): Promise<T> =>
  request<T>({ url, method: 'POST', data })

export const put = <T = any>(url: string, data?: Record<string, any>): Promise<T> =>
  request<T>({ url, method: 'PUT', data })

export const del = <T = any>(url: string): Promise<T> => request<T>({ url, method: 'DELETE' })

export default http
