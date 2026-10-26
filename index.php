<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(home_for(current_user()['role']));
}
redirect('login.php');
