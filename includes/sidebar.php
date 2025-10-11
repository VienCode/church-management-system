<?php
// includes/sidebar.php
// Assumes auth_check.php is already included on the page that includes this file

$cur = basename($_SERVER['PHP_SELF']);
?>

<nav id="mainSidebar" class="sidebar">
    <div class="logo-section">
        <div class="logo-left">
            <div class="logo-placeholder"><img src="images/ucf.png" alt="UCF Logo"></div>
            <div class="logo">Unity Christian Fellowship</div>
        </div>
        <button id="toggleSidebar" class="collapse-btn" aria-label="Toggle sidebar">☰</button>
    </div>

    <div class="sidebar-content">
        <ul class="nav-menu">

            <li>
                <a href="dashboard.php" class="<?= $cur == 'dashboard.php' ? 'active' : '' ?>">
                    <span class="icon">🏠</span>
                    <span class="label">Dashboard</span>
                </a>
            </li>

            <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible <?= in_array($cur, ['attendance.php','attendance_records.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['attendance.php','attendance_records.php']) ? 'true' : 'false' ?>">
                    <span class="icon">📋</span>
                    <span class="label">Attendance</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="attendance.php" class="<?= $cur == 'attendance.php' ? 'active' : '' ?>">👥 Attendance</a></li>
                    <li><a href="attendance_records.php" class="<?= $cur == 'attendance_records.php' ? 'active' : '' ?>">📋 Attendance Records</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible <?= in_array($cur, ['evangelism.php','promotion_page.php','evangelism_records.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['evangelism.php','promotion_page.php','evangelism_records.php']) ? 'true' : 'false' ?>">
                    <span class="icon">🌱</span>
                    <span class="label">Evangelism</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="evangelism.php" class="<?= $cur == 'evangelism.php' ? 'active' : '' ?>">🌿 Evangelism</a></li>
                    <li><a href="promotion_page.php" class="<?= $cur == 'promotion_page.php' ? 'active' : '' ?>">🕊️ Promotion Panel</a></li>
                    <li><a href="evangelism_records.php" class="<?= $cur == 'evangelism_records.php' ? 'active' : '' ?>">📖 Evangelism Records</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible <?= in_array($cur, ['cell_group.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['cell_group.php']) ? 'true' : 'false' ?>">
                    <span class="icon">👥</span>
                    <span class="label">Cell Groups</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="cell_group.php" class="<?= $cur == 'cell_group.php' ? 'active' : '' ?>">👥 My Cell Group</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_ADMIN, ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
            <li class="collapsible <?= in_array($cur, ['upload.php','update_restore.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['upload.php','update_restore.php']) ? 'true' : 'false' ?>">
                    <span class="icon">📢</span>
                    <span class="label">Announcements</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="upload.php" class="<?= $cur == 'upload.php' ? 'active' : '' ?>">📜 Post Announcement</a></li>
                    <li><a href="update_restore.php" class="<?= $cur == 'update_restore.php' ? 'active' : '' ?>">📜 Uploads History</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li class="collapsible <?= in_array($cur, ['donations.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['donations.php']) ? 'true' : 'false' ?>">
                    <span class="icon">💰</span>
                    <span class="label">Tithes & Offerings</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="donations.php" class="<?= $cur == 'donations.php' ? 'active' : '' ?>">💰 Donations</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li class="collapsible <?= in_array($cur, ['expenses_submit.php','expenses_history.php','expenses_approval.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['expenses_submit.php','expenses_history.php','expenses_approval.php']) ? 'true' : 'false' ?>">
                    <span class="icon">💼</span>
                    <span class="label">Expenses</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="expenses_submit.php" class="<?= $cur == 'expenses_submit.php' ? 'active' : '' ?>">🧾 Submit Expense</a></li>
                    <li><a href="expenses_history.php" class="<?= $cur == 'expenses_history.php' ? 'active' : '' ?>">📊 History</a></li>
                    <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
                    <li><a href="expenses_approval.php" class="<?= $cur == 'expenses_approval.php' ? 'active' : '' ?>">✅ Approvals</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (can_access([ROLE_ADMIN])): ?>
            <li class="collapsible <?= in_array($cur, ['admin_dashboard.php','cell_groups_admin.php','unassigned_members.php','logs.php']) ? 'open' : '' ?>">
                <button class="collapse-toggle" aria-expanded="<?= in_array($cur, ['admin_dashboard.php','cell_groups_admin.php','unassigned_members.php','logs.php']) ? 'true' : 'false' ?>">
                    <span class="icon">🧩</span>
                    <span class="label">System</span>
                    <span class="caret">▾</span>
                </button>
                <ul class="submenu">
                    <li><a href="logs.php" class="<?= $cur == 'logs.php' ? 'active' : '' ?>">🗂️ Activity Logs</a></li>
                    <li><a href="admin_dashboard.php" class="<?= $cur == 'admin_dashboard.php' ? 'active' : '' ?>">⚙️ Manage Users</a></li>
                    <li><a href="cell_groups_admin.php" class="<?= $cur == 'cell_groups_admin.php' ? 'active' : '' ?>">📜 Cell Group Management</a></li>
                    <li><a href="unassigned_members.php" class="<?= $cur == 'unassigned_members.php' ? 'active' : '' ?>">👥 Unassigned Members</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="logout.php"><span class="icon">🚪</span><span class="label">Logout</span></a></li>
        </ul>
    </div>
</nav>

<!-- sidebar behavior script: included here so the include works without adding a separate script file -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    // collapse toggle behavior
    document.querySelectorAll('.collapse-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const li = btn.closest('.collapsible');
            const submenu = li.querySelector('.submenu');
            li.classList.toggle('open');
            submenu.classList.toggle('open');
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        });
    });

    // auto-open submenu containing active link
    const activeLink = document.querySelector('.nav-menu a.active');
    if (activeLink) {
        const parentSub = activeLink.closest('.submenu');
        if (parentSub) {
            parentSub.classList.add('open');
            const li = parentSub.closest('.collapsible');
            if (li) li.classList.add('open');
            const toggle = li.querySelector('.collapse-toggle');
            if (toggle) toggle.setAttribute('aria-expanded','true');
        }
    }

    // whole-sidebar toggle and persist state
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    toggleBtn?.addEventListener('click', function(){
        sidebar.classList.toggle('collapsed');
        try { localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0'); } catch(e){}
    });
    // persist
    try {
        if (localStorage.getItem('sidebarCollapsed') === '1') sidebar.classList.add('collapsed');
    } catch(e){}
});
</script>
