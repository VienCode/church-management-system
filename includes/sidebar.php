<nav class="sidebar">
    <div class="logo-section">
        <div class="logo-placeholder">
            <span><img src="images/ucf.png" alt="ucf_logo"></span>
        </div>
        <div class="logo">Unity Christian Fellowship</div>
    </div>

    <ul class="nav-menu">

        <!-- GENERAL PAGE -->
        <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"><span>🏠</span> Dashboard</a></li>

        <!-- ATTENDANCE -->
        <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">Attendance</li>
        <li><a href="attendance.php" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>"><span>👥</span> Attendance</a></li>
        <li><a href="attendance_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance_records.php' ? 'active' : '' ?>"><span>📋</span> Attendance Records</a></li>
        <?php endif; ?>

        <!-- EVANGELISM -->
        <?php if (can_access([ROLE_ADMIN, ROLE_ATTENDANCE_MARKER])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">Evangelism</li>
        <li><a href="evangelism.php" class="<?= basename($_SERVER['PHP_SELF']) == 'evangelism.php' ? 'active' : '' ?>"><span>🌱</span> Evangelism</a></li>
        <li><a href="promotion_page.php" class="<?= basename($_SERVER['PHP_SELF']) == 'promotion_page.php' ? 'active' : '' ?>"><span>📖</span> Promotion Panel</a></li>
        <li><a href="evangelism_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'evangelism_records.php' ? 'active' : '' ?>"><span>📑</span> Evangelism Records</a></li>
        <?php endif; ?>

        <!-- CELL GROUPS -->
        <?php if (can_access([ROLE_LEADER, ROLE_MEMBER]) && !is_role(ROLE_ADMIN)): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">Cell Groups</li>

            <!-- LEADER ACCESS -->
            <?php if (can_access([ROLE_LEADER]) && !is_role(ROLE_ADMIN)): ?>
            <li><a href="leader_cell_group.php" class="<?= basename($_SERVER['PHP_SELF']) == 'leader_cell_group.php' ? 'active' : '' ?>"><span>👥</span> My Cell Group</a></li>
            <li><a href="cell_group_history.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cell_group_history.php' ? 'active' : '' ?>"><span>📜</span> History</a></li>
            <?php endif; ?>

            <!-- MEMBER ACCESS -->
            <?php if (can_access([ROLE_MEMBER]) && !is_role(ROLE_ADMIN)): ?>
            <li><a href="cell_group_member.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cell_group_member.php' ? 'active' : '' ?>"><span>✅</span> My Cell Group Attendance</a></li>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ANNOUNCEMENTS -->
        <?php if (can_access([ROLE_ADMIN, ROLE_EDITOR, ROLE_PASTOR])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">Announcements</li>
        <li><a href="upload.php" class="<?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>"><span>📢</span> Post Announcement</a></li>
        <li><a href="update_restore.php" class="<?= basename($_SERVER['PHP_SELF']) == 'update_restore.php' ? 'active' : '' ?>"><span>🕓</span> Uploads History</a></li>
        <?php endif; ?>

        <!-- TITHES & OFFERINGS -->
        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">Tithes & Offerings</li>
        <li><a href="donations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'donations.php' ? 'active' : '' ?>"><span>💰</span> Donations</a></li>
        <li><a href="donation_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'donation_records.php' ? 'active' : '' ?>"><span>📜</span> Donation Records</a></li>
        <?php endif; ?>

        <!-- EXPENSES -->
        <?php if (can_access([ROLE_ACCOUNTANT, ROLE_ADMIN, ROLE_LEADER])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">💼 Expenses</li>
        <li><a href="expenses_submit.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_submit.php' ? 'active' : '' ?>"><span>🧾</span> Submit Expense</a></li>
        <li><a href="expenses_history.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_history.php' ? 'active' : '' ?>"><span>📊</span> History</a></li>
        <?php endif; ?>

        <?php if (can_access([ROLE_PASTOR, ROLE_ADMIN])): ?>
        <li><a href="expenses_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses_approval.php' ? 'active' : '' ?>"><span>✅</span> Approvals</a></li>
        <?php endif; ?>

        <!-- SYSTEM SECTION -->
        <?php if (can_access([ROLE_ADMIN])): ?>
        <li class="nav-divider"></li>
        <li class="nav-section">🧩 System</li>
        <li><a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>"><span>⚙️</span> Manage Users</a></li>
        <li><a href="admin_cell_groups.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_cell_groups.php' ? 'active' : '' ?>"><span>🧩</span> Manage Cell Groups</a></li>
        <li><a href="activity_logs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : '' ?>"><span>🗂️</span> Activity Logs</a></li>
        <li><a href="backup.php" class="<?= basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : '' ?>"><span>💾</span> Backup & Restore</a></li>
        <?php endif; ?>

        <!-- LOGOUT -->
        <li><a href="logout.php"><span>🚪</span> Logout</a></li>
    </ul>
</nav>
