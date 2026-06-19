// ============================================================
// Authentication - فقط Bearer Token
// ============================================================

window.requireAuth = function() {
    const token = localStorage.getItem('api_token');
    console.log('🔐 Checking auth, token:', token ? 'exists' : 'not found');
    
    if (!token && !window.location.pathname.includes('login.html')) {
        console.log('🔐 No token, redirecting to login');
        window.location.href = '/admin/login.html';
        return false;
    }
    return true;
};

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔐 Auth page loaded');
    
    const loginForm = document.getElementById('login-form');
    const tokenInput = document.getElementById('token-input');
    const errorDiv = document.getElementById('login-error');

    const token = localStorage.getItem('api_token');
    console.log('🔐 Current token:', token ? 'exists' : 'not found');
    
    if (token && window.location.pathname.includes('login.html')) {
        console.log('🔐 Already logged in, redirecting to dashboard');
        window.location.href = '/admin/';
        return;
    }

    if (!token && !window.location.pathname.includes('login.html')) {
        console.log('🔐 No token, redirecting to login');
        window.location.href = '/admin/login.html';
        return;
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const token = tokenInput.value.trim();
            console.log('🔐 Login attempt with token:', token ? 'provided' : 'empty');

            if (!token) {
                showError('لطفاً توکن API را وارد کنید');
                return;
            }

            try {
                API.setToken(token);
                console.log('🔐 Token saved, testing health...');
                await API.health();
                console.log('🔐 Health check passed, redirecting');
                window.location.href = '/admin/';
            } catch (error) {
                console.error('🔐 Login failed:', error);
                API.clearToken();
                showError('توکن نامعتبر است. لطفاً دوباره تلاش کنید.');
            }
        });
    }

    const logoutBtn = document.getElementById('sidebar-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            if (confirm('آیا از خروج اطمینان دارید؟')) {
                API.clearToken();
                window.location.href = '/admin/login.html';
            }
        });
    }

    function showError(message) {
        errorDiv.style.display = 'block';
        errorDiv.textContent = message;
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }
});
