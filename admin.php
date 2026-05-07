<?php
/**
 * ЗАДАНИЕ 6 - Панель администратора
 * 
 * Этот файл реализует:
 * 1. HTTP-авторизацию (Basic Auth) для доступа к админ-панели
 * 2. Просмотр всех зарегистрированных пользователей
 * 3. Редактирование данных пользователей
 * 4. Удаление пользователей
 * 5. Статистику по языкам программирования
 * 6. Валидацию даты рождения (нельзя будущую дату)
 */

// Устанавливаем кодировку UTF-8 для правильного отображения русского текста
header('Content-Type: text/html; charset=UTF-8');

// ==================== НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ ====================
$db_host = 'localhost';      // Хост БД (обычно localhost)
$db_name = 'u82465';         // Имя базы данных
$db_user = 'u82465';         // Пользователь БД
$db_pass = '3772684';        // Пароль от БД
$pdo = null;                 // Переменная для хранения подключения PDO

/**
 * Функция подключения к базе данных
 * Использует паттерн Singleton (одиночка) - соединение создаётся только один раз
 * Это пример соблюдения принципа DRY (Don't Repeat Yourself)
 * 
 * @return PDO Объект подключения к БД
 */
function getDB() {
    global $db_host, $db_name, $db_user, $db_pass, $pdo;
    
    // Если соединение ещё не установлено - создаём новое
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8",  // DSN (Data Source Name)
            $db_user,   // Логин
            $db_pass,   // Пароль
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Включаем режим исключений
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC  // Результат в виде ассоциативного массива
            ]
        );
    }
    return $pdo;
}

// ==================== HTTP АВТОРИЗАЦИЯ АДМИНИСТРАТОРА ====================
/**
 * HTTP Basic Auth - это встроенный в протокол HTTP механизм аутентификации
 * Браузер сам показывает окно для ввода логина и пароля
 * 
 * Процесс:
 * 1. Браузер отправляет заголовки PHP_AUTH_USER и PHP_AUTH_PW
 * 2. Сервер проверяет логин и пароль в таблице admin
 * 3. Если неверно - отправляет заголовок 401 Unauthorized
 * 4. Браузер снова показывает окно входа
 */

$auth_success = false;  // Флаг успешной авторизации

// Проверяем, отправил ли браузер логин и пароль через HTTP-авторизацию
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $login = $_SERVER['PHP_AUTH_USER'];      // Логин из окна браузера
    $password = $_SERVER['PHP_AUTH_PW'];      // Пароль из окна браузера
    
    try {
        $db = getDB();  // Подключаемся к БД
        
        // Ищем администратора с таким логином
        $stmt = $db->prepare("SELECT * FROM admin WHERE login = ?");
        $stmt->execute([$login]);
        $admin = $stmt->fetch();
        
        // password_verify() проверяет, соответствует ли введённый пароль хешу в БД
        // Это безопасно, так как пароль не хранится в открытом виде
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $auth_success = true;  // Авторизация успешна
        }
    } catch (PDOException $e) {
        error_log("Admin auth error: " . $e->getMessage());  // Логируем ошибку
    }
}

// Если авторизация не удалась - отправляем заголовок 401 и показываем сообщение
if (!$auth_success) {
    header('HTTP/1.1 401 Unauthorized');                     // Статус "Не авторизован"
    header('WWW-Authenticate: Basic realm="Admin Panel"');   // Заголовок, вызывающий окно входа
    echo '<h1>401 Требуется авторизация</h1>';
    echo '<p>Доступ разрешен только администратору.</p>';
    exit();  // Прерываем выполнение скрипта
}

// ==================== ОБРАБОТКА ДЕЙСТВИЙ АДМИНИСТРАТОРА ====================
$message = '';          // Сообщение для пользователя (успех/ошибка)
$message_type = 'success';  // Тип сообщения: 'success' или 'error'

try {
    $db = getDB();  // Получаем подключение к БД
    
    // ---------------- УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ----------------
    // Проверяем, передан ли параметр delete в URL (например, ?delete=27)
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $user_id = (int)$_GET['delete'];  // Преобразуем в целое число для безопасности
        
        // Начинаем транзакцию - все изменения будут либо применены, либо отменены
        $db->beginTransaction();
        
        // 1. Удаляем связи пользователя с языками (таблица user_languages)
        $db->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$user_id]);
        
        // 2. Удаляем самого пользователя (таблица users)
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        $db->commit();  // Подтверждаем транзакцию
        
        $message = "Пользователь ID $user_id успешно удален.";
        $message_type = "success";
    }
    
    // ---------------- РЕДАКТИРОВАНИЕ ПОЛЬЗОВАТЕЛЯ ----------------
    // Проверяем, отправлена ли форма редактирования
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $birth_date = trim($_POST['birth_date']);
        $gender = trim($_POST['gender']);
        $bio = trim($_POST['bio']);
        $agreed = isset($_POST['agreed_to_terms']) ? 1 : 0;
        
        // Массив для сбора ошибок валидации
        $errors = [];
        $today = date('Y-m-d');  // Текущая дата для проверки
        
        // ---- Валидация ФИО ----
        // Регулярное выражение: буквы (латиница и кириллица), пробелы, дефис, длина 1-150
        if (empty($full_name) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
            $errors[] = "Неверный формат ФИО";
        }
        
        // ---- Валидация телефона ----
        // Регулярка: цифры, пробелы, дефисы, плюсы, скобки, длина 5-20
        if (!preg_match('/^[\d\s\-\+\(\)]{5,20}$/', $phone)) {
            $errors[] = "Неверный формат телефона";
        }
        
        // ---- Валидация email ----
        // Используем встроенную функцию filter_var для проверки email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Неверный формат email";
        }
        
        // ---- Валидация даты рождения (нельзя будущую дату) ----
        if (empty($birth_date)) {
            $errors[] = "Дата рождения обязательна";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $errors[] = "Неверный формат даты";
        } elseif ($birth_date > $today) {
            // Проверка: дата не может быть в будущем
            $errors[] = "Дата рождения не может быть в будущем";
        } elseif ($birth_date < '1900-01-01') {
            // Проверка: дата не может быть слишком старой
            $errors[] = "Дата рождения не может быть раньше 1900 года";
        }
        
        // ---- Валидация пола ----
        if (!in_array($gender, ['male', 'female'])) {
            $errors[] = "Неверный формат пола";
        }
        
        // ---- Валидация языков программирования ----
        // Получаем массив выбранных языков из POST-запроса
        $langs = $_POST['langs'] ?? [];
        
        // Список разрешённых языков (белый список)
        $allowed_langs = ['pascal', 'c', 'c++', 'javascript', 'php', 'python', 
                          'java', 'haskell', 'clojure', 'prolog', 'scala', 'go'];
        
        // array_intersect - оставляет только те значения, которые есть в разрешённом списке
        // Это защита от поддельных значений
        $valid_langs = array_intersect($langs, $allowed_langs);
        
        if (empty($valid_langs)) {
            $errors[] = "Выберите хотя бы один язык программирования";
        }
        
        // Если есть ошибки валидации - показываем сообщение
        if (!empty($errors)) {
            $message = "Ошибка: " . implode(", ", $errors);
            $message_type = "error";
        } else {
            // Ошибок нет - сохраняем изменения
            $db->beginTransaction();
            
            // Обновляем основные данные пользователя
            $stmt = $db->prepare("UPDATE users SET 
                full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, bio = ?, agreed_to_terms = ? 
                WHERE id = ?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $agreed, $user_id]);
            
            // Очищаем старые связи пользователя с языками
            $db->prepare("DELETE FROM user_languages WHERE user_id = ?")->execute([$user_id]);
            
            // Вставляем новые связи (выбранные языки)
            if (!empty($valid_langs)) {
                // Создаём строку плейсхолдеров (?,?,? для каждого языка)
                $placeholders = implode(',', array_fill(0, count($valid_langs), '?'));
                
                // Получаем ID языков из таблицы languages по их именам
                $stmt = $db->prepare("SELECT id FROM languages WHERE name IN ($placeholders)");
                $stmt->execute($valid_langs);
                $lang_ids = $stmt->fetchAll();
                
                // Вставляем каждую связь пользователь-язык
                $link = $db->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
                foreach ($lang_ids as $lang) {
                    $link->execute([$user_id, $lang['id']]);
                }
            }
            
            $db->commit();  // Подтверждаем все изменения
            $message = "Пользователь ID $user_id успешно обновлен.";
            $message_type = "success";
        }
    }
    
    // ==================== ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ ====================
    
    // ---- Загружаем всех пользователей из БД (сортировка по ID: от новых к старым) ----
    $users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
    
    // ---- Загружаем языки для каждого пользователя ----
    // Это нужно, чтобы отобразить выбранные языки в таблице и в форме редактирования
    $user_langs = [];
    foreach ($users as $user) {
        // JOIN - объединяет таблицы languages и user_languages
        $stmt = $db->prepare("
            SELECT l.name FROM languages l 
            JOIN user_languages ul ON l.id = ul.language_id 
            WHERE ul.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $user_langs[$user['id']] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    // Обработка ошибок базы данных
    $message = "Ошибка базы данных: " . $e->getMessage();
    $message_type = "error";
    if (isset($db)) $db->rollBack();  // Откатываем транзакцию при ошибке
}

// ==================== МАППИНГ ЯЗЫКОВ ДЛЯ ОТОБРАЖЕНИЯ ====================
// Сопоставляет названия из БД (например, 'javascript') в читаемый вид ('JavaScript')
$lang_display = [
    'pascal' => 'Pascal', 'c' => 'C', 'c++' => 'C++',
    'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
    'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
    'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
];
$all_langs = array_keys($lang_display);  // Массив всех языков (ключи маппинга)
$today = date('Y-m-d');  // Текущая дата для проверки будущих дат
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <!-- Подключаем внешние стили -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="container admin-panel">
    <!-- Верхняя панель с заголовком и кнопкой возврата -->
    <div class="nav-links">
        <h1>Панель администратора</h1>
        <a href="index.php" class="back-link">Вернуться к форме</a>
    </div>
    
    <!-- Вывод сообщения об успехе или ошибке -->
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- ==================== СТАТИСТИКА ПО ЯЗЫКАМ ==================== -->
    <h2>Статистика по языкам программирования</h2>
    <div class="stats-grid">
        <?php 
        // Запрос: считаем количество пользователей для каждого языка
        // LEFT JOIN - включаем языки, которые ещё никто не выбрал (счётчик будет 0)
        $stats_query = $db->query("
            SELECT l.name, COUNT(ul.user_id) as cnt 
            FROM languages l
            LEFT JOIN user_languages ul ON l.id = ul.language_id
            GROUP BY l.id
            ORDER BY cnt DESC
        ");
        $lang_stats = $stats_query->fetchAll();
        
        // Выводим карточки статистики
        foreach ($lang_stats as $stat): 
        ?>
            <div class="stat-card">
                <h3><?php echo $lang_display[$stat['name']]; ?></h3>
                <div class="stat-number"><?php echo $stat['cnt']; ?></div>
                <small>пользователей</small>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- ==================== ТАБЛИЦА ВСЕХ ПОЛЬЗОВАТЕЛЕЙ ==================== -->
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
                    <!-- Строка с данными пользователя -->
                    <tr id="user-row-<?php echo $user['id']; ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['login'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <!-- Подсвечиваем будущую дату красным -->
                        <td class="<?php echo ($user['birth_date'] > $today) ? 'error' : ''; ?>">
                            <?php echo htmlspecialchars($user['birth_date']); ?>
                            <?php if ($user['birth_date'] > $today): ?>
                                <span class="error-text">(будущая дата!)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                        <td>
                            <!-- Выводим бейджики выбранных языков -->
                            <?php foreach ($user_langs[$user['id']] ?? [] as $lang): ?>
                                <span class="lang-badge"><?php echo $lang_display[$lang['name']]; ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <button class="btn btn-edit" onclick="showEditForm(<?php echo $user['id']; ?>)">Редактировать</button>
                            <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('Удалить пользователя?')">Удалить</a>
                        </td>
                    </tr>
                    
                    <!-- Скрытая форма редактирования (показывается при нажатии "Редактировать") -->
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
                                            <!-- max атрибут не даёт выбрать дату в будущем в браузере -->
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
                                            <!-- multiple size="5" - список с возможностью выбора нескольких элементов -->
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

<!-- JavaScript для показа/скрытия формы редактирования -->
<script>
/**
 * Показывает форму редактирования для выбранного пользователя
 * @param userId - ID пользователя
 */
function showEditForm(userId) {
    // Скрываем строку с данными пользователя
    document.getElementById('user-row-' + userId).style.display = 'none';
    // Показываем строку с формой редактирования
    document.getElementById('edit-row-' + userId).style.display = 'table-row';
}

/**
 * Скрывает форму редактирования и показывает данные пользователя
 * @param userId - ID пользователя
 */
function hideEditForm(userId) {
    // Показываем строку с данными пользователя
    document.getElementById('user-row-' + userId).style.display = 'table-row';
    // Скрываем строку с формой редактирования
    document.getElementById('edit-row-' + userId).style.display = 'none';
}
</script>
</body>
</html>
