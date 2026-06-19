// ============================================================
// Config Page
// ============================================================

function initConfig() {
    console.log('🔧 Initializing config');
    loadAllConfigs();
}

async function loadAllConfigs() {
    const tbody = document.getElementById('configs-list');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="3" class="loading">⏳ در حال بارگذاری...</td></tr>';
    
    try {
        const result = await API.getAllConfigs();
        const configs = result.data || [];
        
        if (configs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-dim);">هیچ تنظیماتی یافت نشد</td></tr>';
            return;
        }
        
        tbody.innerHTML = configs.map(c => `
            <tr>
                <td><code>${escapeHtml(c.key)}</code></td>
                <td>${escapeHtml(String(c.value))}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editConfig('${c.key}', '${escapeHtml(String(c.value))}')">✏️ ویرایش</button>
                </td>
             </tr>
        `).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="3" class="error-message">❌ خطا: ${error.message}</td></tr>`;
        showToast('❌ خطا در بارگذاری تنظیمات: ' + error.message, 'error');
    }
}

function editConfig(key, value) {
    document.getElementById('config-key').value = key;
    document.getElementById('config-value').value = value;
    document.getElementById('config-modal').classList.add('active');
}

function closeConfigModal() {
    document.getElementById('config-modal').classList.remove('active');
}

async function updateConfig(event) {
    event.preventDefault();
    const key = document.getElementById('config-key').value;
    const value = document.getElementById('config-value').value.trim();
    
    try {
        await API.updateConfig(key, value);
        showToast('✅ تنظیمات بروزرسانی شد', 'success');
        closeConfigModal();
        await loadAllConfigs();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}
