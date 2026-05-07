<?php
/**
 * Файл login.php для не авторизованного пользователя выводит форму логина.
 * При отправке формы проверяет логин/пароль и создает сессию,
 * записывает в нее логин и id пользователя.
 * После авторизации пользователь перенаправляется на главную страницу
 * для изменения ранее введенных данных.
 */

// Отправляем браузеру правильную кодировку
header('Content-Type: text/html; charset=UTF-8');

// ---- НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ ----
$db_host = 'localhost';
$db_name = 'u82465';
$db_user = 'u82465';
$db_pass = '3772684';
// -------------------------------------------------

// Начинаем сессию (или продолжаем существующую)
session_start();

// Если пользователь уже авторизован - перенаправляем на форму
if (!empty($_SESSION['login'])) {
    header('Location: index.php');
    exit();
}

// --- ОБРАБОТКА GET-ЗАПРОСА (показ формы входа) ---
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Если есть сообщение об ошибке из POST запроса
    $error_message = '';
    if (!empty($_GET['error'])) {
        $error_message = '<div class="error-block">Неверный логин или пароль</div>';
    }
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в систему</title>
        <link rel="stylesheet" href="style.css">
        <style>
            .login-container {
                max-width: 400px;
                margin: 100px auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .login-container h1 {
                text-align: center;
                color: #1a73e8;
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1 style="color: #2d6a4f;">Вход для редактирования</h1>
            
            <?php echo $error_message; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="login" required>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="pass" required>
                </div>
                <button type="submit">Войти</button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php">Вернуться к форме</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// --- ОБРАБОТКА POST-ЗАПРОСА (проверка логина/пароля) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['pass'] ?? '');
    
    if (empty($login) || empty($password)) {
        header('Location: login.php?error=1');
        exit();
    }
    
    try {
        // Подключаемся к БД
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8",
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Ищем пользователя с таким логином
        $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        // Проверяем пароль (сравниваем с хешем)
        if ($user && password_verify($password, $user['password_hash'])) {
            // Пароль верный - создаём сессию
            $_SESSION['login'] = $user['login'];
            $_SESSION['uid'] = $user['id'];
            $_SESSION['user_id'] = $user['id'];  // для удобства
            
            // Перенаправляем на форму для редактирования
            header('Location: index.php');
            exit();
        } else {
            // Неверный логин или пароль
            header('Location: login.php?error=1');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("DB Error in login: " . $e->getMessage());
        header('Location: login.php?error=1');
        exit();
    }
}
?>
