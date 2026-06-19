// ============================================================
// API Client - فقط از Bearer Token استفاده می‌کند
// ============================================================

const API = {
    // API در public_html/index.php است (یک سطح بالاتر از admin)
    baseURL: window.location.origin,
    token: localStorage.getItem('api_token') || '',

    setToken(token) {
        console.log('🔑 Setting token');
        this.token = token;
        localStorage.setItem('api_token', token);
    },

    clearToken() {
        console.log('🔑 Clearing token');
        this.token = '';
        localStorage.removeItem('api_token');
    },

    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.token}`,
            'Accept': 'application/json'
        };
    },

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            ...options,
            headers: {
                ...this.getHeaders(),
                ...(options.headers || {})
            }
        };

        console.log(`📡 ${options.method || 'GET'} ${url}`);

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (response.status === 401) {
                console.warn('🔑 Unauthorized, clearing token');
                this.clearToken();
                window.location.href = '/admin/login.html';
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                throw new Error(data.error || data.message || 'Request failed');
            }

            console.log(`✅ ${endpoint} success`);
            return data;
        } catch (error) {
            console.error('❌ API Error:', error);
            throw error;
        }
    },

    // ==================== Public ====================

    async health() {
        console.log('🏥 Checking health...');
        return this.request('/health');
    },

    // ==================== Users ====================

    async getUsers(limit = 50) {
        return this.request(`/api/users?limit=${limit}`);
    },

    async getUser(userId) {
        return this.request(`/api/users/${userId}`);
    },

    async searchUsers(query) {
        return this.request(`/api/users/search/${encodeURIComponent(query)}`);
    },

    async blockUser(userId, data) {
        return this.request(`/api/users/${userId}/block`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async unblockUser(userId) {
        return this.request(`/api/users/${userId}/unblock`, {
            method: 'POST'
        });
    },

    async deleteUser(userId) {
        return this.request(`/api/users/${userId}`, {
            method: 'DELETE'
        });
    },

    // ==================== Statistics ====================

    async getStatistics() {
        return this.request('/api/statistics');
    },

    async getDailyStats(days = 7) {
        return this.request(`/api/statistics/daily?days=${days}`);
    },

    // ==================== Broadcast ====================

    async setupBroadcast(data) {
        return this.request('/api/broadcast/setup', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async getBroadcastStatus() {
        return this.request('/api/broadcast/status');
    },

    async enableBroadcast() {
        return this.request('/api/broadcast/enable', {
            method: 'POST'
        });
    },

    async disableBroadcast() {
        return this.request('/api/broadcast/disable', {
            method: 'POST'
        });
    },

    async sendBroadcastNow() {
        return this.request('/api/broadcast/send', {
            method: 'POST'
        });
    },

    // ==================== Join Mandatory ====================

    async getMandatoryChannels() {
        return this.request('/api/join-mandatory/channels');
    },

    async addMandatoryChannel(data) {
        return this.request('/api/join-mandatory/channels', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async removeMandatoryChannel(chatId) {
        return this.request(`/api/join-mandatory/channels/${chatId}`, {
            method: 'DELETE'
        });
    },

    async toggleMandatoryChannel(chatId) {
        return this.request(`/api/join-mandatory/channels/${chatId}/toggle`, {
            method: 'PUT'
        });
    },

    // ==================== Anti-Spam ====================

    async getAntiSpamConfig() {
        return this.request('/api/anti-spam/config');
    },

    async updateAntiSpamConfig(data) {
        return this.request('/api/anti-spam/config', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    // ==================== Ads ====================

    async getRandomAdConfig() {
        return this.request('/api/ads/random');
    },

    async updateRandomAdConfig(data) {
        return this.request('/api/ads/random', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async getMenuAdConfig() {
        return this.request('/api/ads/menu');
    },

    async updateMenuAdConfig(data) {
        return this.request('/api/ads/menu', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    // ==================== Bot Management ====================

    async getBotInfo() {
        return this.request('/api/bot/info');
    },

    async getMaintenanceStatus() {
        return this.request('/api/bot/maintenance');
    },

    async setMaintenanceMode(enabled) {
        return this.request('/api/bot/maintenance', {
            method: 'POST',
            body: JSON.stringify({ enabled })
        });
    },

    async setMaintenanceMessage(message) {
        return this.request('/api/bot/maintenance/message', {
            method: 'POST',
            body: JSON.stringify({ message })
        });
    },

    async getServerInfo() {
        return this.request('/api/bot/server-info');
    },

    async createBackup() {
        return this.request('/api/bot/backup', {
            method: 'POST'
        });
    },

    // ==================== Config ====================

    async getAllConfigs() {
        return this.request('/api/config');
    },

    async getConfig(key) {
        return this.request(`/api/config/${key}`);
    },

    async updateConfig(key, value) {
        return this.request(`/api/config/${key}`, {
            method: 'PUT',
            body: JSON.stringify({ value })
        });
    },

    // ==================== Language Management ====================

    async getLanguages() {
        return this.request('/api/languages');
    },

    async setDefaultLanguage(language) {
        return this.request('/api/languages/default', {
            method: 'POST',
            body: JSON.stringify({ language })
        });
    },

    async getUserLanguage(userId) {
        return this.request(`/api/users/${userId}/language`);
    },

    async setUserLanguage(userId, language) {
        return this.request(`/api/users/${userId}/language`, {
            method: 'POST',
            body: JSON.stringify({ language })
        });
    },

    async reloadLanguages() {
        return this.request('/api/languages/reload', {
            method: 'POST'
        });
    }
};
