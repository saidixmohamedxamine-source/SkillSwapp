// Dashboard specific functionality

document.addEventListener("DOMContentLoaded", function () {
  initializeDashboard();
  loadUserData();
  initSidebarCollapse();
});

function initializeDashboard() {
  console.log("Dashboard page initialized");
}

function loadUserData() {
  console.log("Loading user data...");
}

function initSidebarCollapse() {
  document.querySelectorAll(".sidebar-label-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      this.closest(".sidebar-section").classList.toggle("collapsed");
    });
  });
}
