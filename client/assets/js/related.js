// Related Apps Manager
class RelatedAppsManager {
    async init() {
        const urlParams = new URLSearchParams(window.location.search);
        const packageName = urlParams.get('package');
        if (!packageName) return;

        try {
            const appsInfo = await APIService.fetchAppsInfo();
            const currentApp = appsInfo.apps.find(a => a.package_name === packageName);
            if (!currentApp) return;

            const related = appsInfo.apps.filter(a =>
                a.package_name !== packageName &&
                a.category === currentApp.category
            ).slice(0, 4);

            this.render(related);
        } catch (e) {
            console.error('Related apps error:', e);
        }
    }

    render(apps) {
        const container = document.getElementById('related-apps-container');
        if (!container || apps.length === 0) return;

        container.innerHTML = `
            <h2 style="font-size:1.1rem;margin-bottom:15px;color:#333;">Similar Apps</h2>
            <div class="app-grid">
                ${apps.map(app => `
                    <div class="app-card" onclick="window.location.href='app.html?package=${app.package_name}'" style="cursor:pointer;background:#fff;border-radius:12px;padding:15px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                        <img src="${APIService.getLogoURL(app.logo || '')}"
                             alt="${SecurityManager.sanitizeInput(app.name)}"
                             class="app-icon"
                             onerror="this.src='assets/icons/default-app-icon.svg'"
                             style="width:56px;height:56px;border-radius:14px;margin-bottom:10px;">
                        <div>
                            <h3 style="font-size:14px;margin-bottom:3px;">${SecurityManager.sanitizeInput(app.name)}</h3>
                            <p style="font-size:12px;color:#888;margin-bottom:6px;">${SecurityManager.sanitizeInput(app.category)}</p>
                            <span style="font-size:12px;background:#e8f5e9;color:#388e3c;padding:3px 10px;border-radius:20px;">Free</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new RelatedAppsManager().init();
});
