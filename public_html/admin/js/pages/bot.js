// ============================================================
// Bot Management Page
// ============================================================

function initBot() {
    console.log('🤖 Initializing bot');
    loadBotInfo();
    loadMaintenanceStatus();
}

async function loadBotInfo() {
    const container = document.getElementById('bot-info');
    if (!container) return;
    
    try {
        const result = await API.getBotInfo();
        const info = result.data || {};
        
        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">${info.id || '-'}</div>
                    <div class="stat-label">🆔 شناسه</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">@${info.username || '-'}</div>
                    <div class="stat-label">🔹 نام کاربری</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${info.first_name || '-'}</div>
                    <div class="stat-label">📛 نام</div>
                </div>
            </div>
        `;
    } catch (error) {
        container.innerHTML = `<div class="error-message">❌ خطا: ${error.message}</div>`;
        showToast('❌ خطا در بارگذاری اطلاعات ربات: ' + error.message, 'error');
    }
}

async function loadMaintenanceStatus() {
    const container = document.getElementById('maintenance-status');
    if (!container) return;
    
    try {
        const result = await API.getMaintenanceStatus();
        const status = result.data || {};
        
        container.innerHTML = `
            <div class="info-message">
                <p><strong>وضعیت فعلی:</strong> ${status.enabled ? '🟢 فعال' : '🔴 غیرفعال'}</p>
                <p><strong>پیام نمایشی:</strong> ${escapeHtml(status.message || '-')}</p>
            </div>
        `;
        document.getElementById('maintenance-message').value = status.message || '';
    } catch (error) {
        container.innerHTML = `<div class="error-message">❌ خطا: ${error.message}</div>`;
        showToast('❌ خطا در بارگذاری وضعیت تعمیرات: ' + error.message, 'error');
    }
}

async function enableMaintenanceMode() {
    try {
        await API.setMaintenanceMode(true);
        showToast('✅ حالت تعمیرات فعال شد', 'success');
        await loadMaintenanceStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function disableMaintenanceMode() {
    try {
        await API.setMaintenanceMode(false);
        showToast('✅ حالت تعمیرات غیرفعال شد', 'success');
        await loadMaintenanceStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function updateMaintenanceMessage() {
    const message = document.getElementById('maintenance-message').value.trim();
    if (!message) {
        showToast('❌ لطفاً پیام را وارد کنید', 'error');
        return;
    }
    
    try {
        await API.setMaintenanceMessage(message);
        showToast('✅ پیام حالت تعمیرات بروزرسانی شد', 'success');
        await loadMaintenanceStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function createBackup() {
    try {
        const result = await API.createBackup();
        showToast(`✅ بکاپ ایجاد شد: ${result.data.path}`, 'success');
    } catch (error) {
        showToast('❌ خطا در ایجاد بکاپ: ' + error.message, 'error');
    }
}
