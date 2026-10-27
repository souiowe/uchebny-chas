<?php
declare(strict_types=1);

// Справочники
const COURSES = [
    'python' => 'Python с нуля',
    'web'    => 'Вёрстка: HTML и CSS',
    'js'     => 'JavaScript для начинающих',
    'php'    => 'PHP и MySQL',
];
const FORMATS = [
    'group'  => 'Групповые занятия',
    'single' => 'Индивидуальные занятия',
];
const STATUSES = [
    'new'    => 'Новая',
    'work'   => 'В работе',
    'done'   => 'Выполнена',
    'cancel' => 'Отменена',
];

// защита от XSS
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Подключение к SQLite
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/app.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name     TEXT NOT NULL,
        login         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        phone         TEXT NOT NULL,
        email         TEXT NOT NULL,
        role          TEXT NOT NULL DEFAULT 'user',
        created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL REFERENCES users(id),
        course        TEXT NOT NULL,
        format        TEXT NOT NULL,
        start_date    TEXT NOT NULL,
        start_time    TEXT NOT NULL,
        hours         INTEGER NOT NULL,
        comment       TEXT,
        certificate   INTEGER NOT NULL DEFAULT 0,
        status        TEXT NOT NULL DEFAULT 'new',
        cancel_reason TEXT,
        review        TEXT,
        created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    seed_demo($pdo);
    return $pdo;
}

// Демо-данные для проверки админки(ПОТОМА УДАЛИТЬ ПОСЛЕ РЕГА И ВХОДА)
function seed_demo(PDO $pdo): void
{
    if ($pdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn()) {
        return;
    }

    $clients = [
        ['Иванов Иван Иванович',      'ivanov01',  '+7(913)-555-01-01', 'ivanov@mail.ru'],
        ['Петрова Анна Сергеевна',    'petrova02', '+7(913)-555-02-02', 'petrova@mail.ru'],
        ['Сидоров Максим Андреевич',  'sidorov03', '+7(923)-555-03-03', 'sidorov@mail.ru'],
        ['Кузнецова Мария Олеговна',  'kuznet04',  '+7(923)-555-04-04', 'kuznetsova@mail.ru'],
        ['Смирнов Артём Дмитриевич',  'smirnov05', '+7(383)-555-05-05', 'smirnov@mail.ru'],
        ['Попова Елена Викторовна',   'popova06',  '+7(383)-555-06-06', 'popova@mail.ru'],
        ['Орлов Даниил Павлович',     'orlov007',  '+7(913)-555-07-07', 'orlov@mail.ru'],
    ];
    $addUser = $pdo->prepare('INSERT INTO users (full_name, login, password_hash, phone, email)
                              VALUES (?, ?, ?, ?, ?)');
    foreach ($clients as [$name, $login, $phone, $email]) {
        $addUser->execute([$name, $login, password_hash('demo1234', PASSWORD_DEFAULT), $phone, $email]);
    }

    $orders = [
        [1, 'python', 'group',  '2026-11-03', '18:00', 3, 'Начинаю с нуля',               1, 'new',    null],
        [2, 'web',    'single', '2026-11-10', '19:30', 4, 'Упор на адаптивную вёрстку',   0, 'work',   null],
        [3, 'js',     'group',  '2026-09-15', '17:00', 2, 'События и формы',              1, 'done',   null],
        [4, 'php',    'single', '2026-10-20', '20:00', 5, 'Подготовка к проекту',         0, 'cancel', 'Не подошло расписание'],
        [5, 'python', 'group',  '2026-11-05', '16:00', 3, 'Хочу писать ботов',            1, 'new',    null],
        [6, 'web',    'group',  '2026-11-12', '18:30', 2, 'Для личного сайта',            0, 'work',   null],
        [7, 'js',     'single', '2026-11-18', '19:00', 6, 'Нужен быстрый старт',          1, 'new',    null],
    ];
    $addOrder = $pdo->prepare('INSERT INTO orders
        (user_id, course, format, start_date, start_time, hours, comment, certificate, status, cancel_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($orders as $order) {
        $addOrder->execute($order);
    }
}
