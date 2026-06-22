import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
    withCredentials: true,
});

export default api;
