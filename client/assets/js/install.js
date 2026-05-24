// App Installation Manager
class InstallManager {
    constructor() {
        this.installStates = {};
        this.init();
    }

    init() {
        const installBtn = document.getElementById('install-btn');
        if (installBtn) {
            installBtn.addEventListener('click', () => this.handleInstall());
        }
    }

    async handleInstall() {
        const urlParams = new URLSearchParams(window.location.search);
        const packageName = urlParams.get('package');
        
        if (!packageName) {
            this.showError('No app specified');
            return;
        }

        const appData = await this.getAppData(packageName);
        if (!appData) {
            this.showError('App data not found');
            return;
        }

        // Check if app requires purchase
        if (!appData.is_free && appData.price > 0) {
            this.showPurchaseDialog(appData);
            return;
        }

        // Start download
        await this.downloadAPK(appData);
    }

    async getAppData(packageName) {
        try {
            const appsData = await APIService.fetchAppsInfo();
            return appsData.apps.find(app => app.package_name === packageName);
        } catch (error) {
            console.error('Error fetching app data:', error);
            return null;
        }
    }

    async downloadAPK(appData) {
        try {
            const installBtn = document.getElementById('install-btn');
            const progressContainer = document.getElementById('download-progress');
            
            // Update UI
            installBtn.disabled = true;
            installBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
            
            if (!progressContainer) {
                this.showProgress();
            }

            // Get download URL
            const downloadURL = APIService.getAPKDownloadURL(appData.apk_file);
            
            // Verify file hash before download
            const fileValid = await this.verifyFileHash(downloadURL, appData.apk_hash);
            if (!fileValid) {
                throw new Error('File integrity check failed');
            }

            // Start download with progress tracking
            const response = await fetch(downloadURL);
            const contentLength = response.headers.get('content-length');
            
            if (!contentLength) {
                throw new Error('Could not determine file size');
            }

            const total = parseInt(contentLength, 10);
            let loaded = 0;

            const reader = response.body.getReader();
            const chunks = [];

            while (true) {
                const {done, value} = await reader.read();
                
                if (done) break;
                
                chunks.push(value);
                loaded += value.length;
                
                const progress = (loaded / total) * 100;
                this.updateProgress(progress);
            }

            // Create blob from chunks
            const blob = new Blob(chunks);
            
            // Trigger download
            this.triggerDownload(blob, appData.apk_file);
            
            // Track install
            this.trackInstall(appData.package_name);

        } catch (error) {
            console.error('Download error:', error);
            this.showError('Failed to download app. Please try again.');
            
            // Reset button
            const installBtn = document.getElementById('install-btn');
            installBtn.disabled = false;
            installBtn.textContent = 'Install';
        }
    }

    async verifyFileHash(url, expectedHash) {
        // In a real implementation, you would:
        // 1. Download the file
        // 2. Calculate its hash
        // 3. Compare with expected hash
        // For demo purposes, we'll just return true
        return true;
    }

    showProgress() {
        const progressHTML = `
            <div id="download-progress" class="download-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">0% downloaded</div>
            </div>
        `;
        
        const installBtn = document.getElementById('install-btn');
        installBtn.insertAdjacentHTML('afterend', progressHTML);
    }

    updateProgress(percentage) {
        const progressFill = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }
        if (progressText) {
            progressText.textContent = `${Math.round(percentage)}% downloaded`;
        }
    }

    triggerDownload(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        // Show success message
        this.showSuccess('App downloaded successfully!');
        
        // Reset button
        const installBtn = document.getElementById('install-btn');
        installBtn.disabled = false;
        installBtn.textContent = 'Install';
        
        // Remove progress bar
        const progressContainer = document.getElementById('download-progress');
        if (progressContainer) {
            progressContainer.remove();
        }
    }

    trackInstall(packageName) {
        // Send analytics data
        const installData = {
            package_name: packageName,
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent
        };
        
        // In production, send to analytics server
        console.log('Install tracked:', installData);
    }

    showPurchaseDialog(appData) {
        const dialog = document.createElement('div');
        dialog.className = 'purchase-dialog';
        dialog.innerHTML = `
            <div class="dialog-content">
                <h3>Purchase ${appData.name}</h3>
                <p>Price: ${appData.currency} ${appData.price}</p>
                <button onclick="this.closest('.purchase-dialog').remove()">Cancel</button>
                <button class="purchase-btn">Purchase</button>
            </div>
        `;
        document.body.appendChild(dialog);
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new InstallManager();
});
