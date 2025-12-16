<?php
require_once dirname(__DIR__) . '/config/config.php';

logout_user();
set_flash('info', 'Has cerrado sesión exitosamente');
redirect(base_url('public/login.php'));
