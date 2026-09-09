import axios, { AxiosError } from 'axios';

const http = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Request interceptor to attach Bearer token
http.interceptors.request.use((config) => {
    const token = localStorage.getItem('mystorage_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Response interceptor to handle unauthenticated & error messages cleanly
http.interceptors.response.use(
    (response) => response,
    (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('mystorage_token');
            if (window.location.pathname !== '/login' && window.location.pathname !== '/register') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default http;
