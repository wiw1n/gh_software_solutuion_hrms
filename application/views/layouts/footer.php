    </div><!-- /page-content -->
</div><!-- /main-wrap -->

<!-- jQuery (must load before DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables core + Bootstrap 5 integration -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Global: base URL fallback for pages that don't set it inline -->
<script>window.APP_URL = window.APP_URL || '<?= base_url() ?>';</script>

<!-- Global: notifications -->
<script src="<?= base_url('assets/js/notifications.js') ?>"></script>

<!-- Global: edit profile modal -->
<script src="<?= base_url('assets/js/profile.js') ?>"></script>

<!-- Page-specific JS file -->
<?php if (!empty($page_js)): ?>
<script src="<?= base_url('assets/js/' . $page_js) ?>"></script>
<?php endif; ?>

<script>
    function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebar-overlay').classList.remove('d-none'); }
    function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebar-overlay').classList.add('d-none'); }

    // Rotate chevron icons on sidebar collapse toggles
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(toggle) {
        var targetId = toggle.getAttribute('href') || toggle.dataset.bsTarget;
        var target = document.querySelector(targetId);
        if (!target) return;
        var chevron = toggle.querySelector('.sb-chevron');
        if (!chevron) return;
        target.addEventListener('show.bs.collapse', function() { chevron.classList.add('rotated'); });
        target.addEventListener('hide.bs.collapse', function() { chevron.classList.remove('rotated'); });
    });
</script>
</body>
</html>
