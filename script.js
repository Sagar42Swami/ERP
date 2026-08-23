// Helper to show/hide errors
function showError(id, message) {
    const errorEl = document.getElementById(id);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = message ? "block" : "none";
    }
}

// Helper to validate email format
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ----------------------------------------------------
// TAB NAVIGATION LOGIC (with LocalStorage state retention)
// ----------------------------------------------------
document.addEventListener("DOMContentLoaded", function () {
    const menuItems = document.querySelectorAll(".menu-item");
    const tabContents = document.querySelectorAll(".tab-content");

    if (menuItems.length > 0) {
        // Find if a tab is saved in local storage
        let activeTab = localStorage.getItem("erp_active_tab") || "overview";
        
        // Check if there is an active tab matching the query parameter (sometimes useful to override)
        const urlParams = new URLSearchParams(window.location.search);
        const forceTab = urlParams.get("tab");
        if (forceTab) {
            activeTab = forceTab;
        }

        // Switch to the active tab on startup
        switchTab(activeTab);

        menuItems.forEach(item => {
            item.addEventListener("click", function (e) {
                const tabId = this.getAttribute("data-tab");
                if (tabId) {
                    e.preventDefault();
                    switchTab(tabId);
                }
            });
        });
    }

    function switchTab(tabId) {
        // Hide all tabs
        tabContents.forEach(tab => {
            tab.classList.remove("active");
        });

        // Deactivate all menu items
        menuItems.forEach(item => {
            item.classList.remove("active");
        });

        // Show selected tab
        const targetTab = document.getElementById(tabId);
        if (targetTab) {
            targetTab.classList.add("active");
            localStorage.setItem("erp_active_tab", tabId);

            // Highlight corresponding menu item
            const targetMenu = document.querySelector(`.menu-item[data-tab="${tabId}"]`);
            if (targetMenu) {
                targetMenu.classList.add("active");
            }
        } else {
            // Fallback to overview if tab not found
            const fallbackTab = document.getElementById("overview");
            if (fallbackTab) {
                fallbackTab.classList.add("active");
                const fallbackMenu = document.querySelector('.menu-item[data-tab="overview"]');
                if (fallbackMenu) fallbackMenu.classList.add("active");
            }
        }
    }
});

