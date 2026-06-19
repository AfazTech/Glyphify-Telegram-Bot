// ============================================================
// Dashboard Page
// ============================================================

// تابع init برای بارگذاری اولیه صفحه
function initDashboard() {
    console.log('📊 Initializing dashboard');
    loadDashboard();
}

async function loadDashboard() {
    console.log('📊 Loading dashboard data...');
    
    try {
        // Load statistics
        const statsResult = await API.getStatistics();
        console.log('📊 Stats loaded:', statsResult);
        renderStats(statsResult.data);

        // Load daily stats
        const dailyResult = await API.getDailyStats(7);
        console.log('📈 Daily stats loaded:', dailyResult);
        renderDailyStats(dailyResult.data);

        // Load server info
        const serverResult = await API.getServerInfo();
        console.log('🖥️ Server info loaded:', serverResult);
        renderServerInfo(serverResult.data);
        
        showToast('✅ داشبورد بروزرسانی شد', 'success');
    } catch (error) {
        console.error('❌ Error loading dashboard:', error);
        showToast('❌ خطا در بارگذاری داشبورد: ' + error.message, 'error');
    }
}

function renderStats(stats) {
    const container = document.getElementById('dashboard-stats');
    if (!container) {
        console.warn('⚠️ dashboard-stats container not found');
        return;
    }

    if (!stats) {
        container.innerHTML = '<div class="info-message">هیچ داده‌ای موجود نیست</div>';
        return;
    }

    container.innerHTML = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">${stats.total_users || 0}</div>
                <div class="stat-label">👥 کل کاربران</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${stats.active_users || 0}</div>
                <div class="stat-label">✅ کاربران فعال</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${stats.blocked_users || 0}</div>
                <div class="stat-label">🚫 کاربران بلاک</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${stats.admins || 0}</div>
                <div class="stat-label">👑 مدیران</div>
            </div>
        </div>
        <div style="padding: 0 28px 20px 28px; color: var(--text-dim); font-size: 12px; text-align: left;">
            آخرین بروزرسانی: ${stats.last_updated || '-'}
        </div>
    `;
}

function renderDailyStats(dailyStats) {
    const container = document.getElementById('daily-stats');
    if (!container) {
        console.warn('⚠️ daily-stats container not found');
        return;
    }

    if (!dailyStats || dailyStats.length === 0) {
        container.innerHTML = '<div class="info-message">هیچ داده‌ای برای آمار روزانه موجود نیست</div>';
        return;
    }

    let html = `
        <div style="padding: 20px 28px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
    `;

    dailyStats.forEach(day => {
        html += `
            <div style="background: var(--bg-hover); padding: 16px; border-radius: 12px; text-align: center; border: 1px solid var(--border);">
                <div style="font-size: 13px; color: var(--text-dim);">${day.date}</div>
                <div style="font-size: 20px; font-weight: 700; color: var(--primary);">${day.new_users || 0}</div>
                <div style="font-size: 11px; color: var(--text-dim);">کاربر جدید</div>
                <div style="font-size: 16px; font-weight: 600; color: var(--info); margin-top: 4px;">${day.active_users || 0}</div>
                <div style="font-size: 11px; color: var(--text-dim);">فعال</div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    container.innerHTML = html;
}

function renderServerInfo(serverInfo) {
    const container = document.getElementById('server-info');
    if (!container) {
        console.warn('⚠️ server-info container not found');
        return;
    }

    if (!serverInfo) {
        container.innerHTML = '<div class="info-message">اطلاعات سرور در دسترس نیست</div>';
        return;
    }

    container.innerHTML = `
        <div style="padding: 20px 28px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">🐧 سیستم‌عامل</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.os || '-'}</div>
                </div>
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">🐘 PHP</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.php_version || '-'}</div>
                </div>
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">💾 حافظه مصرفی</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.memory_usage || '-'}</div>
                </div>
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">💽 فضای دیسک</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.disk_free || '-'} / ${serverInfo.disk_total || '-'}</div>
                </div>
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">📊 بار سرور</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.load_average || 0}</div>
                </div>
                <div style="background: var(--bg-hover); padding: 14px; border-radius: 12px; border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-dim);">⏱️ آپ‌تایم</div>
                    <div style="font-size: 14px; font-weight: 600;">${serverInfo.uptime || '-'}</div>
                </div>
            </div>
        </div>
    `;
}
