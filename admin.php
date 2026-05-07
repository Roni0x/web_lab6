<?php
/**
 * ЗАДАНИЕ 6
 * Панель администратора с HTTP-авторизацией
 * - Просмотр всех пользователей
 * - Редактирование пользователей
 * - Удаление пользователей
 * - Валидация даты рождения (нельзя будущую дату)
 */

header('Content-Type: text/html; charset=UTF-8');

// ---- НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ ----
$db_host = 'localhost';
$db_name = 'u82465';
$db_user = 'u82465';
$db_pass = '3772684';
$pdo = null;

// Функция подключения к БД
function getDB() {
    global $db_host, $db_name, $db_user, $db_pass, $pdo;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8",
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// --- HTTP АВТОРИЗАЦИЯ АДМИНИСТРАТОРА ---
$auth_success = false;

if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admin WHERE login = ?");
        $stmt->execute([$login]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $auth_success = true;
        }
    } catch (PDOException $e) {
        error_log("Admin auth error: " . $e->getMessage());
    }
}

if (!$auth_success) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<h1>401 Требуется авторизация</h1>';
    echo '<p>Доступ разрешен только администратору.</p>';
    exit();
}

// --- ОБРАБОТКА ДЕЙСТВИЙ АДМИНИСТРАТОРА ---
$message = '';
$message_type = 'success';

try {
    $db = getDB();
    
    // ---- УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ----
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $user_id = (int)$_GET['delete'];
        
        $db->beginTransaction();
        $db->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$user_id]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        $db->commit();
        
        $message = "Пользователь ID $user_id успешно удален.";
        $message_type = "success";
    }
    
    // ---- РЕДАКТИРОВАНИЕ ПОЛЬЗОВАТЕЛЯ ----
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $birth_date = trim($_POST['birth_date']);
        $gender = trim($_POST['gender']);
        $bio = trim($_POST['bio']);
        $agreed = isset($_POST['agreed_to_terms']) ? 1 : 0;
        
        // Собираем ошибки валидации
        $errors = [];
        $today = date('Y-m-d');
        
        // Валидация ФИО
        if (empty($full_name) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
            $errors[] = "Неверный формат ФИО";
        }
        
        // Валидация телефона
        if (!preg_match('/^[\d\s\-\+\(\)]{5,20}$/', $phone)) {
            $errors[] = "Неверный формат телефона";
        }
        
        // Валидация email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Неверный формат email";
        }
        
        // Валидация даты рождения (нельзя будущую дату)
        if (empty($birth_date)) {
            $errors[] = "Дата рождения обязательна";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $errors[] = "Неверный формат даты";
        } elseif ($birth_date > $today) {
            $errors[] = "Дата рождения не может быть в будущем";
        } elseif ($birth_date < '1900-01-01') {
            $errors[] = "Дата рождения не может быть раньше 1900 года";
        }
        
        // Валидация пола
        if (!in_array($gender, ['male', 'female'])) {
            $errors[] = "Неверный формат пола";
        }
        
        // Валидация языков
        $langs = $_POST['langs'] ?? [];
        $allowed_langs = ['pascal', 'c', 'c++', 'javascript', 'php', 'python', 
                          'java', 'haskell', 'clojure', 'prolog', 'scala', 'go'];
        $valid_langs = array_intersect($langs, $allowed_langs);
        
        if (empty($valid_langs)) {
            $errors[] = "Выберите хотя бы один язык программирования";
        }
        
        // Если есть ошибки — показываем сообщение
        if (!empty($errors)) {
            $message = "Ошибка: " . implode(", ", $errors);
            $message_type = "error";
        } else {
            // Сохраняем изменения
            $db->beginTransaction();
            
            // Обновляем пользователя
            $stmt = $db->prepare("UPDATE users SET 
                full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, bio = ?, agreed_to_terms = ? 
                WHERE id = ?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $agreed, $user_id]);
            
            // Обновляем языки
            $db->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$user_id]);
            
            if (!empty($valid_langs)) {
                $placeholders = implode(',', array_fill(0, count($valid_langs), '?'));
                $stmt = $db->prepare("SELECT id FROM languages WHERE name IN ($placeholders)");
                $stmt->execute($valid_langs);
                $lang_ids = $stmt->fetchAll();
                
                $link = $db->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
                foreach ($lang_ids as $lang) {
                    $link->execute([$user_id, $lang['id']]);
                }
            }
            
            $db->commit();
            $message = "Пользователь ID $user_id успешно обновлен.";
            $message_type = "success";
        }
    }
    
    // ---- ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ ----
    $users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
    
    // Языки каждого пользователя
    $user_langs = [];
    foreach ($users as $user) {
        $stmt = $db->prepare("
            SELECT l.name FROM languages l 
            JOIN user_languages ul ON l.id = ul.language_id 
            WHERE ul.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $user_langs[$user['id']] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $message = "Ошибка базы данных: " . $e->getMessage();
    $message_type = "error";
    if (isset($db)) $db->rollBack();
}

// Маппинг языков для отображения
$lang_display = [
    'pascal' => 'Pascal', 'c' => 'C', 'c++' => 'C++',
    'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
    'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
    'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
];
$all_langs = array_keys($lang_display);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="container admin-panel">
    <div class="nav-links">
        <h1>Панель администратора</h1>
        <a href="index.php" class="back-link">Вернуться к форме</a>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <!-- СТАТИСТИКА ПО ЯЗЫКАМ ПРОГРАММИРОВАНИЯ -->
    <h2>Статистика по языкам программирования</h2>
    <div class="stats-grid">
        <?php 
        // Запрос статистики
        $stats_query = $db->query("
            SELECT l.name, COUNT(ul.user_id) as cnt 
            FROM languages l
            LEFT JOIN user_languages ul ON l.id = ul.language_id
            GROUP BY l.id
            ORDER BY cnt DESC
        ");
        $lang_stats = $stats_query->fetchAll();
        
        $lang_display = [
            'pascal' => 'Pascal', 'c' => 'C', 'c++' => 'C++',
            'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
            'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
            'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
        ];
        
        foreach ($lang_stats as $stat): 
        ?>
            <div class="stat-card">
                <h3><?php echo $lang_display[$stat['name']]; ?></h3>
                <div class="stat-number"><?php echo $stat['cnt']; ?></div>
                <small>пользователей</small>
            </div>
        <?php endforeach; ?>
    </div>    
    <h2>Все пользователи</h2>
    <?php if (empty($users)): ?>
        <p>Нет зарегистрированных пользователей.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr id="user-row-<?php echo $user['id']; ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['login'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="<?php echo ($user['birth_date'] > $today) ? 'error' : ''; ?>">
                            <?php echo htmlspecialchars($user['birth_date']); ?>
                            <?php if ($user['birth_date'] > $today): ?>
                                <span class="error-text">(будущая дата!)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                        <td>
                            <?php foreach ($user_langs[$user['id']] ?? [] as $lang): ?>
                                <span class="lang-badge"><?php echo $lang_display[$lang['name']]; ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <button class="btn btn-edit" onclick="showEditForm(<?php echo $user['id']; ?>)">Редактировать</button>
                            <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('Удалить пользователя?')">Удалить</a>
                        </td>
                    </tr>
                    
                    <!-- Форма редактирования -->
                    <tr id="edit-row-<?php echo $user['id']; ?>" style="display: none;">
                        <td colspan="9">
                            <div class="edit-form">
                                <h3>Редактирование пользователя ID <?php echo $user['id']; ?></h3>
                                <form method="POST" action="">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="edit_user" value="1">
                                    
                                    <div class="edit-grid">
                                        <div>
                                            <label>ФИО</label>
                                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        <div>
                                            <label>Телефон</label>
                                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                        </div>
                                        <div>
                                            <label>Email</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div>
                                            <label>Дата рождения</label>
                                            <input type="date" name="birth_date" value="<?php echo $user['birth_date']; ?>" max="<?php echo $today; ?>" required>
                                            <small>Не может быть в будущем</small>
                                        </div>
                                        <div>
                                            <label>Пол</label>
                                            <select name="gender">
                                                <option value="male" <?php echo $user['gender'] == 'male' ? 'selected' : ''; ?>>Мужской</option>
                                                <option value="female" <?php echo $user['gender'] == 'female' ? 'selected' : ''; ?>>Женский</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Языки программирования</label>
                                            <select name="langs[]" multiple size="5">
                                                <?php
                                                $current = array_column($user_langs[$user['id']] ?? [], 'name');
                                                foreach ($all_langs as $lang):
                                                    $selected = in_array($lang, $current) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $lang; ?>" <?php echo $selected; ?>><?php echo $lang_display[$lang]; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small>Зажмите Ctrl для множественного выбора</small>
                                        </div>
                                        <div class="edit-full-width">
                                            <label>Биография</label>
                                            <textarea name="bio" rows="3"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                                        </div>
                                        <div class="edit-full-width">
                                            <label>
                                                <input type="checkbox" name="agreed_to_terms" <?php echo $user['agreed_to_terms'] ? 'checked' : ''; ?>>
                                                Согласен с контрактом
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="edit-buttons">
                                        <button type="submit" class="btn btn-save">Сохранить</button>
                                        <button type="button" class="btn btn-cancel" onclick="hideEditForm(<?php echo $user['id']; ?>)">Отмена</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function showEditForm(userId) {
    document.getElementById('user-row-' + userId).style.display = 'none';
    document.getElementById('edit-row-' + userId).style.display = 'table-row';
}

function hideEditForm(userId) {
    document.getElementById('user-row-' + userId).style.display = 'table-row';
    document.getElementById('edit-row-' + userId).style.display = 'none';
}
</script>
</body>
</html>
