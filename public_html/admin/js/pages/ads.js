// ============================================================
// Ads Page
// ============================================================

function initAds() {
    console.log('📺 Initializing ads');
    loadRandomAdConfig();
    loadMenuAdConfig();
}

async function loadRandomAdConfig() {
    try {
        const result = await API.getRandomAdConfig();
        const config = result.data || {};
        
        document.getElementById('random-ad-enabled').checked = config.enabled || false;
        document.getElementById('random-ad-chance').value = config.chance || 50;
        document.getElementById('random-ad-chat-id').value = config.chat_id || '';
        document.getElementById('random-ad-message-id').value = config.message_id || '';
        document.getElementById('random-ad-is-forward').checked = config.is_forward || false;
    } catch (error) {
        showToast('❌ خطا در بارگذاری تبلیغات تصادفی: ' + error.message, 'error');
    }
}

async function updateRandomAd(event) {
    event.preventDefault();
    const enabled = document.getElementById('random-ad-enabled').checked;
    const chance = document.getElementById('random-ad-chance').value;
    const chatId = document.getElementById('random-ad-chat-id').value.trim();
    const messageId = document.getElementById('random-ad-message-id').value.trim();
    const isForward = document.getElementById('random-ad-is-forward').checked;
    
    try {
        await API.updateRandomAdConfig({
            enabled: enabled,
            chance: parseInt(chance) || 0,
            chat_id: chatId || null,
            message_id: messageId ? parseInt(messageId) : null,
            is_forward: isForward
        });
        showToast('✅ تنظیمات تبلیغات تصادفی ذخیره شد', 'success');
        await loadRandomAdConfig();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}

async function loadMenuAdConfig() {
    try {
        const result = await API.getMenuAdConfig();
        const config = result.data || {};
        
        document.getElementById('menu-ad-enabled').checked = config.enabled || false;
        document.getElementById('menu-ad-chat-id').value = config.chat_id || '';
        document.getElementById('menu-ad-message-id').value = config.message_id || '';
        document.getElementById('menu-ad-is-forward').checked = config.is_forward || false;
    } catch (error) {
        showToast('❌ خطا در بارگذاری تبلیغات منو: ' + error.message, 'error');
    }
}

async function updateMenuAd(event) {
    event.preventDefault();
    const enabled = document.getElementById('menu-ad-enabled').checked;
    const chatId = document.getElementById('menu-ad-chat-id').value.trim();
    const messageId = document.getElementById('menu-ad-message-id').value.trim();
    const isForward = document.getElementById('menu-ad-is-forward').checked;
    
    try {
        await API.updateMenuAdConfig({
            enabled: enabled,
            chat_id: chatId || null,
            message_id: messageId ? parseInt(messageId) : null,
            is_forward: isForward
        });
        showToast('✅ تنظیمات تبلیغات منو ذخیره شد', 'success');
        await loadMenuAdConfig();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}
