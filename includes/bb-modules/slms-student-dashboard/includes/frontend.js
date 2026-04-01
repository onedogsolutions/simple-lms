document.addEventListener('DOMContentLoaded', function() {
    var moduleNodes = document.querySelectorAll('.slms-student-dashboard');

    moduleNodes.forEach(function(node) {
        var tabs = node.querySelectorAll('.slms-tab-link');
        var panes = node.querySelectorAll('.slms-tab-pane');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and panes within this specific module
                tabs.forEach(function(t) { t.classList.remove('active'); });
                panes.forEach(function(p) { p.classList.remove('active'); });

                // Add active class to clicked tab and corresponding pane
                this.classList.add('active');
                var targetId = 'slms-tab-' + this.getAttribute('data-tab');
                var targetPane = node.querySelector('#' + targetId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    });
});
