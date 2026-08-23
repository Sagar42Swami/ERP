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

// ----------------------------------------------------
// DYNAMIC FORM POPULATION (HR / ADMIN)
// ----------------------------------------------------

// Populate employee editor form fields when an option is selected
function populateEmployeeEditForm(empId) {
    if (!empId) {
        document.getElementById("edit_emp_fname").value = "";
        document.getElementById("edit_emp_lname").value = "";
        document.getElementById("edit_emp_email").value = "";
        document.getElementById("edit_emp_phone").value = "";
        document.getElementById("edit_emp_salary").value = "";
        document.getElementById("edit_emp_desig").value = "";
        document.getElementById("edit_emp_dept").value = "";
        document.getElementById("edit_emp_mgr").value = "0";
        document.getElementById("edit_emp_status").value = "ACTIVE";
        return;
    }

    const row = document.getElementById("emp_row_" + empId);
    if (row) {
        document.getElementById("edit_emp_fname").value = row.getAttribute("data-fname") || "";
        document.getElementById("edit_emp_lname").value = row.getAttribute("data-lname") || "";
        document.getElementById("edit_emp_email").value = row.getAttribute("data-email") || "";
        document.getElementById("edit_emp_phone").value = row.getAttribute("data-phone") || "";
        document.getElementById("edit_emp_salary").value = row.getAttribute("data-salary") || "";
        document.getElementById("edit_emp_desig").value = row.getAttribute("data-desig") || "";
        document.getElementById("edit_emp_dept").value = row.getAttribute("data-dept") || "";
        document.getElementById("edit_emp_mgr").value = row.getAttribute("data-mgr") || "0";
        document.getElementById("edit_emp_status").value = row.getAttribute("data-status") || "ACTIVE";
    }
}

// Populate user account editor fields in Admin tab
function populateUserEditForm(userId) {
    if (!userId) {
        document.getElementById("edit_user_role").value = "EMPLOYEE";
        document.getElementById("edit_user_status").value = "ACTIVE";
        return;
    }

    const row = document.getElementById("user_row_" + userId);
    if (row) {
        document.getElementById("edit_user_role").value = row.getAttribute("data-role") || "EMPLOYEE";
        document.getElementById("edit_user_status").value = row.getAttribute("data-status") || "ACTIVE";
    }
}

// ----------------------------------------------------
// FRONT-END FORM VALIDATIONS
// ----------------------------------------------------

// Sign Up Form Validation
const signupForm = document.getElementById("signupForm");
if (signupForm) {
    signupForm.addEventListener("submit", function (event) {
        const fname = document.getElementById("signupFirstName").value.trim();
        const lname = document.getElementById("signupLastName").value.trim();
        const email = document.getElementById("signupEmail").value.trim();
        const phone = document.getElementById("signupPhone").value.trim();
        const designation = document.getElementById("signupDesignation").value.trim();
        const salary = document.getElementById("signupSalary").value.trim();
        const password = document.getElementById("signupPassword").value;
        const confirmPassword = document.getElementById("confirmPassword").value;
        
        let isValid = true;

        showError("signupFirstNameError", "");
        showError("signupLastNameError", "");
        showError("signupEmailError", "");
        showError("signupPhoneError", "");
        showError("signupDesignationError", "");
        showError("signupSalaryError", "");
        showError("signupPasswordError", "");
        showError("confirmPasswordError", "");

        if (fname === "") {
            showError("signupFirstNameError", "First name is required.");
            isValid = false;
        }
        if (lname === "") {
            showError("signupLastNameError", "Last name is required.");
            isValid = false;
        }

        if (email === "") {
            showError("signupEmailError", "Email address is required.");
            isValid = false;
        } else if (!isValidEmail(email)) {
            showError("signupEmailError", "Provide a valid email format.");
            isValid = false;
        }

        if (phone === "") {
            showError("signupPhoneError", "Phone number is required.");
            isValid = false;
        }

        if (designation === "") {
            showError("signupDesignationError", "Job designation is required.");
            isValid = false;
        }

        if (salary === "" || isNaN(salary) || parseFloat(salary) < 0) {
            showError("signupSalaryError", "Please input a positive numeric salary.");
            isValid = false;
        }

        if (password === "") {
            showError("signupPasswordError", "Password is required.");
            isValid = false;
        } else if (password.length < 6) {
            showError("signupPasswordError", "Password must contain at least 6 characters.");
            isValid = false;
        }

        if (confirmPassword === "") {
            showError("confirmPasswordError", "Confirm password is required.");
            isValid = false;
        } else if (password !== confirmPassword) {
            showError("confirmPasswordError", "Passwords do not match.");
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}

// Login Form Validation
const loginForm = document.getElementById("loginForm");
if (loginForm) {
    loginForm.addEventListener("submit", function (event) {
        const email = document.getElementById("loginEmail").value.trim();
        const password = document.getElementById("loginPassword").value;
        let isValid = true;

        showError("loginEmailError", "");
        showError("loginPasswordError", "");

        if (email === "") {
            showError("loginEmailError", "Username or Email is required.");
            isValid = false;
        }

        if (password === "") {
            showError("loginPasswordError", "Password is required.");
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}
