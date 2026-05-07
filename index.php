<?php
/**
 * ЗАДАНИЕ 5
 * 
 * Добавлена функциональность:
 * - Автоматическая генерация логина и пароля при первой отправке формы
 * - Сохранение хеша пароля в БД (password_hash)
 * - Отображение логина/пароля пользователю после успешной отправки
 * - Вход по логину/паролю через сессию
 * - Загрузка и отображение ранее сохранённых данных авторизованного пользователя
 * - Возможность редактирования и перезаписи данных
 * - Cookies для неавторизованных пользователей
 */

// Отправляем браузеру правильную кодировку
header('Content-Type: text/html; charset=UTF-8');

// ---- НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ ----
$db_host = 'localhost';
$db_name = 'u82465';
$db_user = 'u82465';
$db_pass = '3772684';
// -------------------------------------------------

// Константы для генерации логина/пароля
define('LOGIN_LENGTH', 8);
define('PASSWORD_LENGTH', 10);

/**
 * Генерирует случайную строку заданной длины
 */
function generate_random_string($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

/**
 * Функция для безопасного получения POST-данных
 */
function get_post_param($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Пытаемся начать сессию
$session_started = false;
$is_authenticated = false;
$current_user_id = null;
$current_user_login = null;

if (!empty($_COOKIE[session_name()])) {
    session_start();
    $session_started = true;
    if (!empty($_SESSION['login'])) {
        $is_authenticated = true;
        $current_user_login = $_SESSION['login'];
        $current_user_id = $_SESSION['uid'];
    }
}

// --- ОБРАБОТКА GET-ЗАПРОСА (показ формы) ---
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    
    $messages = array();
    $errors = array();
    $values = array();

    // Проверяем куку с признаком успешного сохранения
    if (!empty($_COOKIE['save_success'])) {
        $messages[] = '<div class="success">Данные успешно сохранены!</div>';
        setcookie('save_success', '', time() - 3600);
        
        if (!empty($_COOKIE['generated_login']) && !empty($_COOKIE['generated_pass'])) {
            $messages[] = sprintf(
                '<div class="success" style="background: #fff3cd; border-color: #ffeaa7; color: #856404;">
                    <strong>Ваши данные для входа (сохраните их!):</strong><br>
                    Логин: <strong>%s</strong><br>
                    Пароль: <strong>%s</strong><br>
                    <a href="login.php">Войти</a> для редактирования данных
                </div>',
                htmlspecialchars($_COOKIE['generated_login']),
                htmlspecialchars($_COOKIE['generated_pass'])
            );
            setcookie('generated_login', '', time() - 3600);
            setcookie('generated_pass', '', time() - 3600);
        }
    }

    // ---- ЕСЛИ ПОЛЬЗОВАТЕЛЬ АВТОРИЗОВАН - загружаем данные из БД ----
    if ($is_authenticated && $current_user_id) {
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                $values['fio'] = $user_data['full_name'] ?? '';
                $values['phone'] = $user_data['phone'] ?? '';
                $values['email'] = $user_data['email'] ?? '';
                $values['birthdate'] = $user_data['birth_date'] ?? '';
                $values['gender'] = $user_data['gender'] ?? '';
                $values['bio'] = $user_data['bio'] ?? '';
                $values['contract'] = $user_data['agreed_to_terms'] ? 'on' : '';
                
                $stmt_langs = $pdo->prepare("
                    SELECT l.name FROM languages l
                    JOIN user_languages ul ON l.id = ul.language_id
                    WHERE ul.user_id = ?
                ");
                $stmt_langs->execute([$current_user_id]);
                $langs_data = $stmt_langs->fetchAll();
                $values['langs'] = array_column($langs_data, 'name');
                
                $messages[] = '<div class="success" style="background: #d8f3dc; border-left: 5px solid #2d6a4f; color: #1b4332;">
                    Вы вошли как <strong>' . htmlspecialchars($current_user_login) . '</strong>. 
                    Можете редактировать и сохранять данные.
                </div>';
            }
        } catch (PDOException $e) {
            error_log("DB Error loading user data: " . $e->getMessage());
        }
    }
    
    // ---- ЕСЛИ ПОЛЬЗОВАТЕЛЬ НЕ АВТОРИЗОВАН - загружаем данные из Cookies ----
    if (!$is_authenticated) {
        $fields = ['fio', 'phone', 'email', 'birthdate', 'gender', 'bio', 'contract'];
        foreach ($fields as $field) {
            $errors[$field] = !empty($_COOKIE[$field . '_error']);
            $values[$field] = isset($_COOKIE[$field . '_value']) ? $_COOKIE[$field . '_value'] : '';
            
            if ($errors[$field]) {
                setcookie($field . '_error', '', time() - 3600);
            }
        }
        
        $errors['langs'] = !empty($_COOKIE['langs_error']);
        $values['langs'] = isset($_COOKIE['langs_value']) ? unserialize($_COOKIE['langs_value']) : [];
        if ($errors['langs']) {
            setcookie('langs_error', '', time() - 3600);
        }
    }

    // ---- ФОРМИРУЕМ СООБЩЕНИЯ ОБ ОШИБКАХ ----
    $error_messages = [];
    if (!empty($errors['fio'])) $error_messages[] = 'Ошибка в поле ФИО (только буквы и пробелы, до 150 символов).';
    if (!empty($errors['phone'])) $error_messages[] = 'Ошибка в поле Телефон (допустимы цифры, +, -, пробелы).';
    if (!empty($errors['email'])) $error_messages[] = 'Ошибка в поле Email (неверный формат).';
    if (!empty($errors['birthdate'])) $error_messages[] = 'Ошибка в поле Дата рождения.';
    if (!empty($errors['gender'])) $error_messages[] = 'Ошибка в поле Пол (выберите значение).';
    if (!empty($errors['langs'])) $error_messages[] = 'Ошибка в поле Любимый язык (выберите из списка).';
    if (!empty($errors['bio'])) $error_messages[] = 'Ошибка в поле Биография (не более 1000 символов).';
    if (!empty($errors['contract'])) $error_messages[] = 'Необходимо согласие с контрактом.';

    if (!empty($error_messages)) {
        $messages[] = '<div class="error-block"><strong>Исправьте ошибки:</strong><br>' 
                    . implode('<br>', $error_messages) . '</div>';
    }
    // Передаём переменные в форму
    $is_authenticated = $is_authenticated ?? false;
    $current_user_login = $current_user_login ?? '';
    
    // Включаем содержимое файла form.php
    
    include('form.php');
    exit();
}

// --- ОБРАБОТКА POST-ЗАПРОСА (сохранение данных) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $has_error = false;

    // ---- ВАЛИДАЦИЯ ВСЕХ ПОЛЕЙ ----
    $fio = get_post_param('fio');
    if (empty($fio) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $fio)) {
        setcookie('fio_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('fio_value', $fio, time() + 365 * 86400);

    $phone = get_post_param('phone');
    if (empty($phone) || !preg_match('/^[\d\s\-\+\(\)]{5,20}$/', $phone)) {
        setcookie('phone_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('phone_value', $phone, time() + 365 * 86400);

    $email = get_post_param('email');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setcookie('email_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('email_value', $email, time() + 365 * 86400);

    $birthdate = get_post_param('birthdate');
    $date_check = date_create($birthdate);
    if (empty($birthdate) || !$date_check) {
        setcookie('birthdate_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('birthdate_value', $birthdate, time() + 365 * 86400);

    $gender = get_post_param('gender');
    if (!in_array($gender, ['male', 'female'])) {
        setcookie('gender_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('gender_value', $gender, time() + 365 * 86400);

    $langs = isset($_POST['langs']) ? $_POST['langs'] : [];
    $allowed_langs = ['pascal', 'c', 'c++', 'javascript', 'php', 'python', 
                      'java', 'haskell', 'clojure', 'prolog', 'scala', 'go'];
    $valid_langs = array_intersect($langs, $allowed_langs);
    if (empty($valid_langs)) {
        setcookie('langs_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('langs_value', serialize($valid_langs), time() + 365 * 86400);

    $bio = get_post_param('bio');
    if (strlen($bio) > 1000) {
        setcookie('bio_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('bio_value', $bio, time() + 365 * 86400);

    $contract = get_post_param('contract');
    if ($contract !== 'on') {
        setcookie('contract_error', '1', time() + 86400);
        $has_error = true;
    }
    setcookie('contract_value', $contract, time() + 365 * 86400);

    if ($has_error) {
        header('Location: index.php');
        exit();
    }

    // ---- СОХРАНЕНИЕ В БАЗУ ДАННЫХ ----
    try {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // Проверяем, авторизован ли пользователь
        $is_update = ($is_authenticated && $current_user_id);
        
        if ($is_update) {
            // ---- РЕДАКТИРОВАНИЕ СУЩЕСТВУЮЩЕЙ ЗАПИСИ ----
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE users SET 
                full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, bio = ?, agreed_to_terms = ? 
                WHERE id = ?");
            $stmt->execute([$fio, $phone, $email, $birthdate, $gender, $bio, 1, $current_user_id]);
            
            $stmt_del = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
            $stmt_del->execute([$current_user_id]);
            
            $placeholders = implode(',', array_fill(0, count($valid_langs), '?'));
            $stmt_lang_ids = $pdo->prepare("SELECT id, name FROM languages WHERE name IN ($placeholders)");
            $stmt_lang_ids->execute($valid_langs);
            $lang_ids = $stmt_lang_ids->fetchAll();
            
            $stmt_link = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            foreach ($lang_ids as $lang) {
                $stmt_link->execute([$current_user_id, $lang['id']]);
            }
            
            $pdo->commit();
            $user_id = $current_user_id;
            
        } else {
            // ---- НОВАЯ ЗАПИСЬ (генерация логина и пароля) ----
            $pdo->beginTransaction();
            
            do {
                $new_login = generate_random_string(LOGIN_LENGTH);
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE login = ?");
                $stmt_check->execute([$new_login]);
                $exists = $stmt_check->fetch();
            } while ($exists);
            
            $new_password = generate_random_string(PASSWORD_LENGTH);
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users 
                (login, password_hash, full_name, phone, email, birth_date, gender, bio, agreed_to_terms) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$new_login, $password_hash, $fio, $phone, $email, $birthdate, $gender, $bio, 1]);
            $user_id = $pdo->lastInsertId();
            
            $placeholders = implode(',', array_fill(0, count($valid_langs), '?'));
            $stmt_lang_ids = $pdo->prepare("SELECT id, name FROM languages WHERE name IN ($placeholders)");
            $stmt_lang_ids->execute($valid_langs);
            $lang_ids = $stmt_lang_ids->fetchAll();
            
            $stmt_link = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            foreach ($lang_ids as $lang) {
                $stmt_link->execute([$user_id, $lang['id']]);
            }
            
            $pdo->commit();
            
            setcookie('generated_login', $new_login, time() + 60);
            setcookie('generated_pass', $new_password, time() + 60);
        }

        if (!$is_authenticated) {
            $fields = ['fio', 'phone', 'email', 'birthdate', 'gender', 'bio', 'contract'];
            foreach ($fields as $field) {
                setcookie($field . '_value', '', time() - 3600);
            }
            setcookie('langs_value', '', time() - 3600);
        }
        
        setcookie('save_success', '1', time() + 30);
        header('Location: index.php');
        exit();

    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("DB Error: " . $e->getMessage());
        
        $messages[] = '<div class="error-block">Ошибка базы данных: ' . $e->getMessage() . '</div>';
        include('form.php');
        exit();
    }
}
?>
