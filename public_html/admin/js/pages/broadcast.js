// ============================================================
// Broadcast Page
// ============================================================

function initBroadcast() {
    console.log('📢 Initializing broadcast');
    loadBroadcastStatus();
}

async function loadBroadcastStatus() {
    const container = document.getElementById('broadcast-status');
    if (!container) {
        console.warn('⚠️ broadcast-status container not found');
        return;
    }
    
    try {
        const result = await API.getBroadcastStatus();
        const status = result.data;
        
        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">${status.enabled ? '🟢 فعال' : '🔴 غیرفعال'}</div>
                    <div class="stat-label">وضعیت</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${status.is_forward ? 'فوروارد' : 'کپی'}</div>
                    <div class="stat-label">نوع ارسال</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${status.chat_id || '-'}</div>
                    <div class="stat-label">کانال مبدأ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${status.message_id || '-'}</div>
                    <div class="stat-label">آیدی پیام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${status.last_user_id || '0'}</div>
                    <div class="stat-label">آخرین کاربر ارسال شده</div>
                </div>
            </div>
        `;
        
        document.getElementById('broadcast-chat-id').value = status.chat_id || '';
        document.getElementById('broadcast-message-id').value = status.message_id || '';
        document.getElementById('broadcast-is-forward').checked = status.is_forward || false;
    } catch (error) {
        container.innerHTML = `<div class="error-message">❌ خطا: ${error.message}</div>`;
        showToast('❌ خطا در بارگذاری وضعیت: ' + error.message, 'error');
    }
}

async function setupBroadcast(event) {
    event.preventDefault();
    const chatId = document.getElementById('broadcast-chat-id').value.trim();
    const messageId = document.getElementById('broadcast-message-id').value.trim();
    const isForward = document.getElementById('broadcast-is-forward').checked;
    
    if (!chatId || !messageId) {
        showToast('❌ لطفاً آیدی کانال و پیام را وارد کنید', 'error');
        return;
    }
    
    try {
        await API.setupBroadcast({
            chat_id: chatId,
            message_id: parseInt(messageId),
            is_forward: isForward
        });
        showToast('✅ تنظیمات پیام همگانی ذخیره شد', 'success');
        await loadBroadcastStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function enableBroadcast() {
    try {
        await API.enableBroadcast();
        showToast('✅ ارسال همگانی فعال شد', 'success');
        await loadBroadcastStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function disableBroadcast() {
    try {
        await API.disableBroadcast();
        showToast('✅ ارسال همگانی غیرفعال شد', 'success');
        await loadBroadcastStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function sendBroadcastNow() {
    if (!confirm('⚠️ آیا از ارسال فوری پیام همگانی مطمئن هستید؟')) return;
    
    try {
        const result = await API.sendBroadcastNow();
        const data = result.data;
        showToast(`✅ ارسال شد: ${data.sent} موفق، ${data.failed} ناموفق، ${data.remaining} باقیمانده`, 'success');
        await loadBroadcastStatus();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}
