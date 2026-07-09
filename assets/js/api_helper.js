// URL Base yang sudah disesuaikan dengan folder XAMPP kamu (mengarah ke router utama API)
const API_BASE = '/uts-testing-main/kelompok_gamasuk/Resto1/api';

/**
 * Kirim request ke endpoint API dengan JWT otomatis.
 *
 * @param {string} endpoint   - Path relatif, contoh: '/menu', '/cart', atau '/auth/login'
 * @param {string} method     - 'GET', 'POST', 'PUT', 'DELETE'
 * @param {object|null} body  - Data JSON yang dikirim (opsional)
 * @param {string|null} token - JWT token (opsional, diambil dari data-attribute jika tidak dikirim)
 * @returns {Promise<object>} - Response JSON dari API
 */
async function apiRequest(endpoint, method = 'GET', body = null, token = null) {
    // Ambil JWT dari parameter, ATAU secara otomatis dari atribut <body data-jwt="..."> HTML
    const jwt = token || document.body.dataset.jwt || '';
    
    const options = {
        method,
        headers: {
            'Content-Type':  'application/json',
            // Jika JWT ada, pasang sebagai header Authorization
            'Authorization': jwt ? `Bearer ${jwt}` : ''
        }
    };

    // Jika ada data (body) dan method bukan GET, ubah ke format JSON string
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    try {
        const res  = await fetch(API_BASE + endpoint, options);
        const data = await res.json();

        // Jika token expired / invalid (Error 401) -> Lempar kembali ke halaman login
        if (res.status === 401) {
            alert('Sesi kamu sudah habis, silakan login ulang.');
            // Disesuaikan dengan folder XAMPP kamu
            window.location.href = '/uts-testing-main/kelompok_gamasuk/Resto1/login.php';
            return null;
        }

        return { ok: res.ok, status: res.status, data };
        
    } catch (err) {
        // Error jaringan (Memenuhi TC_API_09: DB mati, TC_API_10: timeout)
        console.error('API error:', err);
        showToast('Koneksi bermasalah, coba lagi.', 'error');
        return null;
    }
}

/**
 * Tampilkan notifikasi kecil di pojok layar (Toast).
 * Dipakai sebagai pengganti alert() browser yang mengganggu.
 */
function showToast(message, type = 'info') {
    // Hapus toast lama jika masih ada di layar
    const existing = document.getElementById('api-toast');
    if (existing) existing.remove();

    const colors = {
        success: '#1D9E75', // Hijau
        error:   '#D85A30', // Merah/Oranye
        info:    '#378ADD'  // Biru
    };

    const toast = document.createElement('div');
    toast.id = 'api-toast';
    
    // Gaya CSS langsung diterapkan via JavaScript
    Object.assign(toast.style, {
        position:     'fixed',
        bottom:       '24px',
        right:        '24px',
        background:   colors[type] || colors.info,
        color:        '#fff',
        padding:      '12px 20px',
        borderRadius: '8px',
        fontSize:     '14px',
        zIndex:       '9999',
        maxWidth:     '320px',
        lineHeight:   '1.4',
        boxShadow:    '0 4px 6px rgba(0,0,0,0.1)',
        transition:   'opacity 0.3s ease-in-out'
    });

    toast.textContent = message;
    document.body.appendChild(toast);

    // Hilang otomatis setelah 3 detik
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300); // Tunggu animasi pudar selesai baru dihapus
    }, 3000);
}