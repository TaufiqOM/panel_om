// Simple version - just activate menu based on module parameter
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentModule = urlParams.get('module') || 'dashboard';
    
    // Remove active class from all menus
    document.querySelectorAll('.menu-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Find and activate current module menu
    const currentMenu = document.querySelector(`.menu-link[href*="module=${currentModule}"]`);
    if (currentMenu) {
        currentMenu.classList.add('active');
        
        // Also activate parent accordion if exists
        const parentAccordion = currentMenu.closest('.menu-sub')?.previousElementSibling;
        if (parentAccordion) {
            parentAccordion.classList.add('active');
        }
    }
});