// ============================================================
// Main Application
// ============================================================

if (typeof requireAuth === 'undefined') {
    window.requireAuth = function() {
        const token = localStorage.getItem('api_token');
        if (!token && !window.location.pathname.includes('login.html')) {
            window.location.href = '/admin/login.html';
            return false;
        }
        return true;
    };
}

const PAGES = {
    dashboard: 'pages/dashboard.html',
    users: 'pages/users.html',
    broadcast: 'pages/broadcast.html',
    'join-mandatory': 'pages/join-mandatory.html',
    'anti-spam': 'pages/anti-spam.html',
    ads: 'pages/ads.html',
    bot: 'pages/bot.html',
    config: 'pages/config.html',
    languages: 'pages/languages.html'
};

let currentPage = 'dashboard';

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔵 App started');
    
    if (!window.requireAuth()) return;

    const hash = window.location.hash.replace('#', '') || 'dashboard';
    console.log('📄 Loading page:', hash);
    navigateTo(hash);

    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.dataset.page;
            if (page) {
                navigateTo(page);
            }
        });
    });

    window.addEventListener('hashchange', function() {
        const page = window.location.hash.replace('#', '') || 'dashboard';
        navigateTo(page);
    });
});

async function navigateTo(page) {
    console.log('🧭 Navigating to:', page);
    
    if (!PAGES[page]) {
        page = 'dashboard';
    }

    currentPage = page;

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === page);
    });

    if (window.location.hash !== `#${page}`) {
        history.pushState(null, '', `#${page}`);
    }

    const container = document.getElementById('page-container');
    container.innerHTML = '<div class="loading">⏳ در حال بارگذاری...</div>';

    try {
        const url = PAGES[page];
        console.log('📡 Fetching:', url);
        
        const response = await fetch(url);
        if (!response.ok) throw new Error('Page not found: ' + response.status);
        
        const html = await response.text();
        console.log('✅ Page loaded, length:', html.length);
        
        container.innerHTML = html;

        const initFn = window[`init${page.charAt(0).toUpperCase() + page.slice(1)}`];
        if (typeof initFn === 'function') {
            console.log('🚀 Initializing:', page);
            initFn();
        } else {
            console.log('⚠️ No init function for:', page);
            if (page === 'dashboard' && typeof loadDashboard === 'function') {
                loadDashboard();
            }
        }
    } catch (error) {
        console.error('❌ Error loading page:', error);
        container.innerHTML = `
            <div class="panel">
                <div class="panel-body" style="padding: 40px; text-align: center;">
                    <h3>❌ خطا در بارگذاری صفحه</h3>
                    <p style="color: var(--text-dim);">${error.message}</p>
                    <button class="btn btn-primary" onclick="navigateTo('dashboard')">🔄 بازگشت به داشبورد</button>
                </div>
            </div>
        `;
    }
}

function showToast(message, type = 'info') {
    console.log('🔔 Toast:', message, type);
    
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function refreshDashboard() {
    if (typeof loadDashboard === 'function') {
        loadDashboard();
        showToast('🔄 داشبورد بروزرسانی شد', 'success');
    } else {
        navigateTo('dashboard');
    }
}
