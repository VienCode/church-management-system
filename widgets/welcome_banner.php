<?php
$banner_messages = [
    ROLE_ADMIN => "🛠️ Welcome back, Admin! Oversee and guide the flock.",
    ROLE_LEADER => "👑 Welcome Leader! Shepherd your cell group faithfully.",
    ROLE_MEMBER => "🙏 Welcome, Member! Stay strong and connected in faith.",
    ROLE_EDITOR => "🖋️ Welcome, Editor! Let your updates inspire others.",
    ROLE_ACCOUNTANT => "💼 Welcome, Accountant! Steward the church’s blessings.",
    ROLE_PASTOR => "✝️ Welcome, Pastor! Lead with wisdom and grace.",
    ROLE_NON_MEMBER => "🌱 Welcome! You’re growing in faith — keep going!",
    ROLE_ATTENDANCE_MARKER => "📋 Welcome, Attendance Marker! Keep our records accurate."
];

$message = $banner_messages[$role_id] ?? "Welcome to the Unity Christian Fellowship Dashboard!";
?>
<div class="card" style="grid-column:1/-1;text-align:center;background:#f4f9ff;">
  <h1 style="color:#0271c0;">Welcome <?= htmlspecialchars($role_name) ?> <?= htmlspecialchars("$firstname $lastname") ?> 👋</h1>
  <p style="font-size:1.1em;color:#444;"><?= $message ?></p>
  <p style="margin-top:10px;font-style:italic;color:#666;">“Whatever you do, work at it with all your heart, as working for the Lord.” — Colossians 3:23</p>
</div>
