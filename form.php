<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета - Задание 5</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        /* Кнопка администратора - принудительно зеленая */
        .auth-btn-admin {
            background: linear-gradient(135deg, #2d6a4f 0%, #40916c 100%) !important;
            color: white !important;
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .auth-btn-admin:hover {
            background: linear-gradient(135deg, #1b4d3e 0%, #2d6a4f 100%) !important;
            transform: translateY(-1px);
        }
        .auth-btn-login {
            background: linear-gradient(135deg, #2d6a4f 0%, #40916c 100%) !important;
            color: white !important;
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .auth-btn-login:hover {
            background: linear-gradient(135deg, #1b4d3e 0%, #2d6a4f 100%) !important;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
<div class="container">
    <h1>Регистрационная анкета</h1>
<?php if ($is_authenticated): ?>
    <div style="text-align: right; margin-bottom: 20px;">
        <a href="logout.php" class="auth-btn auth-btn-logout">Выйти</a>
    </div>
<?php endif; ?>    
    <!-- Кнопки входа/выхода -->
    <?php if (!$is_authenticated && empty($values['fio'])): ?>
        <div style="text-align: right; margin-bottom: 20px;">
            <div style="margin-bottom: 8px;">
                <a href="login.php" class="auth-btn auth-btn-login">Войти для редактирования</a>
            </div>
            <div>
                <a href="admin.php" class="auth-btn auth-btn-admin">Войти как администратор</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Вывод сообщений (ошибки, успех) -->
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <form action="" method="POST">
        
        <!-- ===== 1. ПОЛЕ "ФИО" ===== -->
        <div class="form-group">
            <label class="required">ФИО</label>
            <input type="text" name="fio" 
                   value="<?php echo htmlspecialchars($values['fio'] ?? ''); ?>"
                   class="<?php echo ($errors['fio'] ?? false) ? 'error' : ''; ?>">
        </div>

        <!-- ===== 2. ПОЛЕ "Телефон" ===== -->
        <div class="form-group">
            <label class="required">Телефон</label>
            <input type="tel" name="phone" 
                   value="<?php echo htmlspecialchars($values['phone'] ?? ''); ?>"
                   class="<?php echo ($errors['phone'] ?? false) ? 'error' : ''; ?>">
        </div>

        <!-- ===== 3. ПОЛЕ "Email" ===== -->
        <div class="form-group">
            <label class="required">Email</label>
            <input type="email" name="email" 
                   value="<?php echo htmlspecialchars($values['email'] ?? ''); ?>"
                   class="<?php echo ($errors['email'] ?? false) ? 'error' : ''; ?>">
        </div>

        <!-- ===== 4. ПОЛЕ "Дата рождения" ===== -->
        <div class="form-group">
            <label class="required">Дата рождения</label>
            <input type="date" name="birthdate" 
                   value="<?php echo htmlspecialchars($values['birthdate'] ?? ''); ?>"
                   class="<?php echo ($errors['birthdate'] ?? false) ? 'error' : ''; ?>">
        </div>

        <!-- ===== 5. ПОЛЕ "Пол" (радиокнопки) ===== -->
        <div class="form-group">
            <label class="required">Пол</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="gender" value="male" 
                        <?php echo (($values['gender'] ?? '') == 'male') ? 'checked' : ''; ?>> 
                    Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" 
                        <?php echo (($values['gender'] ?? '') == 'female') ? 'checked' : ''; ?>> 
                    Женский
                </label>
            </div>
            <?php if ($errors['gender'] ?? false): ?>
                <small style="color:red;">Выберите пол</small>
            <?php endif; ?>
        </div>

        <!-- ===== 6. ПОЛЕ "Любимый язык" ===== -->
        <div class="form-group">
            <label class="required">Любимый язык программирования</label>
            <select name="langs[]" multiple size="6" 
                    class="<?php echo ($errors['langs'] ?? false) ? 'error' : ''; ?>">
                <?php 
                $lang_list = ['pascal', 'c', 'c++', 'javascript', 'php', 'python', 
                              'java', 'haskell', 'clojure', 'prolog', 'scala', 'go'];
                $lang_display = [
                    'pascal' => 'Pascal', 'c' => 'C', 'c++' => 'C++',
                    'javascript' => 'JavaScript', 'php' => 'PHP', 'python' => 'Python',
                    'java' => 'Java', 'haskell' => 'Haskell', 'clojure' => 'Clojure',
                    'prolog' => 'Prolog', 'scala' => 'Scala', 'go' => 'Go'
                ];
                $selected_langs = $values['langs'] ?? [];
                foreach ($lang_list as $lang): 
                    $selected = in_array($lang, $selected_langs) ? 'selected' : '';
                ?>
                    <option value="<?php echo $lang; ?>" <?php echo $selected; ?>>
                        <?php echo $lang_display[$lang]; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Зажмите Ctrl (Cmd) для выбора нескольких</small>
        </div>

        <!-- ===== 7. ПОЛЕ "Биография" ===== -->
        <div class="form-group">
            <label>Биография</label>
            <textarea name="bio" rows="5" 
                class="<?php echo ($errors['bio'] ?? false) ? 'error' : ''; ?>"><?php 
                echo htmlspecialchars($values['bio'] ?? ''); 
            ?></textarea>
        </div>

        <!-- ===== 8. ЧЕКБОКС ===== -->
        <div class="form-group checkbox-group">
            <label>
                <input type="checkbox" name="contract" 
                    <?php echo (($values['contract'] ?? '') == 'on') ? 'checked' : ''; ?>>
                Я ознакомлен с контрактом
            </label>
            <?php if ($errors['contract'] ?? false): ?>
                <div><small style="color:red;">Необходимо подтвердить ознакомление</small></div>
            <?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
        
    </form>
</div>
</body>
</html>
