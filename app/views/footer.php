</section>
    </main>
</div>
<script>
(function () {
    var button = document.querySelector('.sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    var root = document.documentElement;
    if (!button || !sidebar) return;

    function viewportWidth() {
        var visualWidth = window.visualViewport ? window.visualViewport.width : 0;
        var layoutWidth = window.innerWidth || root.clientWidth || 0;
        if (visualWidth && layoutWidth) return Math.min(visualWidth, layoutWidth);
        return visualWidth || layoutWidth;
    }

    function estimatedZoomRatio() {
        var ratios = [];
        if (window.outerWidth && window.innerWidth) {
            ratios.push(window.outerWidth / window.innerWidth);
        }
        if (screen && screen.availWidth && window.innerWidth) {
            ratios.push(screen.availWidth / window.innerWidth);
        }
        if (window.visualViewport && window.visualViewport.scale && window.visualViewport.scale !== 1) {
            ratios.push(window.visualViewport.scale);
        }
        if (!ratios.length) return 1;
        return Math.max.apply(Math, ratios.filter(function (n) { return isFinite(n) && n > 0; }));
    }

    function shouldUseCompactNavigation() {
        var width = viewportWidth();
        var zoomRatio = estimatedZoomRatio();

        /*
          This handles both situations:
          1) Small/narrow screens or cramped layout.
          2) Desktop browser zoom, where the screen may still look wide but the
             sidebar starts taking too much space.
        */
        return width <= 1500 || zoomRatio >= 1.08;
    }

    function setOpen(open) {
        if (!document.body.classList.contains('nav-compact')) open = false;
        document.body.classList.toggle('sidebar-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    }

    function applyNavigationMode() {
        var compact = shouldUseCompactNavigation();
        document.body.classList.toggle('nav-compact', compact);
        if (!compact) setOpen(false);
    }

    button.addEventListener('click', function () {
        applyNavigationMode();
        setOpen(!document.body.classList.contains('sidebar-open'));
    });

    if (overlay) {
        overlay.addEventListener('click', function () { setOpen(false); });
    }

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () { setOpen(false); });
    });

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setOpen(false);
    });

    window.addEventListener('resize', applyNavigationMode);
    window.addEventListener('orientationchange', applyNavigationMode);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', applyNavigationMode);
        window.visualViewport.addEventListener('scroll', applyNavigationMode);
    }

    /* Run more than once because some browsers update zoom/layout values shortly after resize. */
    applyNavigationMode();
    setTimeout(applyNavigationMode, 120);
    setTimeout(applyNavigationMode, 400);
})();
</script>
</body>
</html>
