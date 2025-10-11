<nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder"><span><img src="images/ucf.png" alt="ucf_logo"></span></div>
        <div class="logo">Unity Christian Fellowship</div>
        <button id="toggleSidebar" class="collapse-btn">☰</button>
    </div>

    <div class="sidebar-content">
        <ul class="nav-menu">

            <!-- Dashboard -->
            <li>
                <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <span>🏠</span> Dashboard
                </a>
            </li>

            <!-- Attendance -->
            <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">📋 Attendance ▾</button>
                <ul class="submenu">
                    <li><a href="attendance.php" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>">👥 Attendance</a></li>
                    <li><a href="attendance_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance_records.php' ? 'active' : '' ?>">📊 Attendance Records</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Evangelism -->
            <?php if (can_access([ROLE_ADMIN, ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">🌱 Evangelism ▾</button>
                <ul class="submenu">
                    <li><a href="evangelism.php" class="<?= basename($_SERVER['PHP_SELF']) == 'evangelism.php' ? 'active' : '' ?>">🌿 Evangelism Attendance</a></li>
                    <li><a href="promotion_page.php" class="<?= basename($_SERVER['PHP_SELF']) == 'promotion_page.php' ? 'active' : '' ?>">🕊️ Promotion Panel</a></li>
                    <li><a href="evangelism_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'evangelism_records.php' ? 'active' : '' ?>">📖 Evangelism Records</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Cell Groups -->
            <?php if (can_access([ROLE_LEADER, ROLE_ATTENDANCE_MARKER])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">👥 Cell Groups ▾</button>
                <ul class="submenu">
                    <li><a href="cell_group.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cell_group.php' ? 'active' : '' ?>">👥 My Cell Group</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Announcements -->
            <?php if (can_access([ROLE_ADMIN, ROLE_EDITOR, ROLE_PASTOR, ROLE_LEADER])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">📢 Announcements ▾</button>
                <ul class="submenu">
                    <li><a href="upload.php" class="<?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>">📜 Post Announcement</a></li>
                    <li><a href="update_restore.php" class="<?= basename($_SERVER['PHP_SELF']) == 'update_restore.php' ? 'active' : '' ?>">📜 Uploads History</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Tithes -->
            <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">💰 Tithes & Offerings ▾</button>
                <ul class="submenu">
                    <li><a href="donations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'donations.php' ? 'active' : '' ?>">💰 Donations</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Expenses -->
            <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">💼 Expenses ▾</button>
                <ul class="submenu">
                    <li><a href="expenses_submit.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_submit.php' ? 'active' : '' ?>">🧾 Submit Expense</a></li>
                    <li><a href="expenses_history.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_history.php' ? 'active' : '' ?>">📊 Expense History</a></li>
                    <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
                        <li><a href="expenses_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_approval.php' ? 'active' : '' ?>">✅ Approvals</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- System -->
            <?php if (can_access([ROLE_ADMIN])): ?>
            <li class="collapsible">
                <button class="collapse-toggle">🧩 System ▾</button>
                <ul class="submenu">
                    <li><a href="logs.php">🗂️ Activity Logs</a></li>
                    <li><a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">⚙️ Manage Users</a></li>
                    <li><a href="cell_groups_admin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cell_groups_admin.php' ? 'active' : '' ?>">📜 Cell Group Management</a></li>
                    <li><a href="unassigned_members.php" class="<?= basename($_SERVER['PHP_SELF']) == 'unassigned_members.php' ? 'active' : '' ?>">👥 Unassigned Members</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="logout.php"><span>🚪</span> Logout</a></li>
        </ul>
    </div>
</nav>
