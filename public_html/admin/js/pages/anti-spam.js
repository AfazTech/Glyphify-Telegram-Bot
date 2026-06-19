// ============================================================
// Anti-Spam Page
// ============================================================

function initAntiSpam() {
    console.log('🛡️ Initializing anti-spam');
    loadAntiSpamConfig();
}

async function loadAntiSpamConfig() {
    try {
        const result = await API.getAntiSpamConfig();
        const config = result.data || {};
        
        document.getElementById('spam-max-messages').value = config.max_messages || 5;
        document.getElementById('spam-block-duration').value = config.block_duration || 3600;
    } catch (error) {
        showToast('❌ خطا در بارگذاری تنظیمات آنتی اسپم: ' + error.message, 'error');
    }
}

async function updateAntiSpam(event) {
    event.preventDefault();
    const maxMessages = document.getElementById('spam-max-messages').value;
    const blockDuration = document.getElementById('spam-block-duration').value;
    
    try {
        await API.updateAntiSpamConfig({
            max_messages: parseInt(maxMessages) || 5,
            block_duration: parseInt(blockDuration) || 3600
        });
        showToast('✅ تنظیمات آنتی اسپم ذخیره شد', 'success');
        await loadAntiSpamConfig();
    } catch (error) {
        showToast('❌ خطا: ' + error.message, 'error');
    }
}
