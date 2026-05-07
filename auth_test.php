<?php
if ($_SERVER['PHP_AUTH_USER'] == 'admin' && $_SERVER['PHP_AUTH_PW'] == 'admin123') {
    echo "✅ Авторизация успешна! Вы вошли как: " . htmlspecialchars($_SERVER['PHP_AUTH_USER']);
} else {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Test Auth"');
    echo "❌ Требуется авторизация. Логин: admin, пароль: admin123";
    exit();
}
?>
