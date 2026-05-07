<?php
/**
 * logout.php - завершение сессии и выход пользователя
 */

// Начинаем сессию
session_start();

// Удаляем все переменные сессии
$_SESSION = array();

// Удаляем куку сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную страницу
header('Location: index.php');
exit();
?>
