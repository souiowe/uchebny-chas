<?php
require_once __DIR__ . '/includes/db.php';

session_start();

// Фильтр хранится в адресе (?status=work), поэтому он переживает смену статуса.
$filter = (string) ($_GET['status'] ?? '');
if (!isset(STATUSES[$filter])) {
    $filter = '';
}
$backUrl = 'admin.php' . ($filter !== '' ? '?status=' . urlencode($filter) : '');

// Смена статуса: сохраняем и возвращаемся на эту же страницу (чтобы F5 не повторял отправку).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status  = (string) ($_POST['status'] ?? '');
    $reason  = trim((string) ($_POST['reason'] ?? ''));

    if (!isset(STATUSES[$status])) {
        $_SESSION['flash'] = ['error', 'Неизвестный статус.'];
    } elseif ($status === 'cancel' && $reason === '') {
        $_SESSION['flash'] = ['error', 'Для отмены заявки № ' . $orderId . ' укажите причину.'];
    } else {
        $st = db()->prepare('UPDATE orders SET status = ?, cancel_reason = ? WHERE id = ?');
        $st->execute([$status, $status === 'cancel' ? $reason : null, $orderId]);
        $_SESSION['flash'] = $st->rowCount()
            ? ['success', 'Статус заявки № ' . $orderId . ' сохранён.']
            : ['error', 'Заявка не найдена.'];
    }
    header('Location: ' . $backUrl);
    exit;
}

$sql = 'SELECT o.*, u.full_name, u.phone
        FROM orders o JOIN users u ON u.id = o.user_id';
$params = [];
if ($filter !== '') {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY o.id';
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель администратора | Учебный час</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header">
<a class="header__logo" href="index.html">Учебный час</a>
<nav class="header__nav">
<a class="header__link" href="index.html">Главная</a>
<a class="header__link" href="register.html">Регистрация</a>
<a class="header__link" href="login.html">Вход</a>
<a class="header__link" href="orders.html">Мои заявки</a>
<a class="header__link" href="create.html">Новая заявка</a>
<a class="header__link header__link--active" href="admin.php">Администратор</a>
</nav>
</header>
<main class="main">
<div class="admin">
<section class="admin__head">
<h1 class="admin__title">Все заявки</h1>
<form class="form admin__filter" method="get" action="admin.php">
<div class="form__group admin__filter-group">
<label class="admin__filter-label" for="filter-status">Статус</label>
<select class="form__select admin__select" id="filter-status" name="status">
<option value="">Все статусы</option>
<?php foreach (STATUSES as $code => $title): ?>
<option value="<?= e($code) ?>"<?= $filter === $code ? ' selected' : '' ?>><?= e($title) ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="button button--primary" type="submit">Показать</button>
</form>
</section>

<section class="admin__body">
<?php if ($flash): ?>
<p class="message message--<?= e($flash[0]) ?> admin__message"><?= e($flash[1]) ?></p>
<?php endif; ?>
<?php if (!$orders): ?>
<p class="orders__empty">Заявок с таким статусом нет.</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead class="table__header">
<tr class="table__row">
<th class="table__head" scope="col">№</th>
<th class="table__head" scope="col">ФИО клиента</th>
<th class="table__head" scope="col">Телефон</th>
<th class="table__head" scope="col">Курс</th>
<th class="table__head" scope="col">Формат</th>
<th class="table__head" scope="col">Дата начала</th>
<th class="table__head" scope="col">Время</th>
<th class="table__head" scope="col">Часов в неделю</th>
<th class="table__head" scope="col">Сертификат</th>
<th class="table__head" scope="col">Комментарий</th>
<th class="table__head" scope="col">Статус</th>
<th class="table__head" scope="col">Смена статуса</th>
</tr>
</thead>
<tbody class="table__body">
<?php foreach ($orders as $order): $id = (int) $order['id']; ?>
<tr class="table__row table__row--body">
<td class="table__cell" data-label="№"><?= $id ?></td>
<td class="table__cell" data-label="ФИО клиента"><?= e($order['full_name']) ?></td>
<td class="table__cell table__cell--nowrap" data-label="Телефон"><?= e($order['phone']) ?></td>
<td class="table__cell" data-label="Курс"><?= e(COURSES[$order['course']] ?? $order['course']) ?></td>
<td class="table__cell" data-label="Формат"><?= e(FORMATS[$order['format']] ?? $order['format']) ?></td>
<td class="table__cell table__cell--nowrap" data-label="Дата начала"><?= e(date('d.m.Y', strtotime($order['start_date']))) ?></td>
<td class="table__cell" data-label="Время"><?= e($order['start_time']) ?></td>
<td class="table__cell" data-label="Часов в неделю"><?= (int) $order['hours'] ?></td>
<td class="table__cell" data-label="Сертификат"><?= $order['certificate'] ? 'Да' : 'Нет' ?></td>
<td class="table__cell table__cell--wide" data-label="Комментарий"><?= e($order['comment'] ?: '—') ?></td>
<td class="table__cell" data-label="Статус"><span class="status status--<?= e($order['status']) ?>"><?= e(STATUSES[$order['status']] ?? $order['status']) ?></span></td>
<td class="table__cell table__cell--wide" data-label="Смена статуса">
<form class="form admin__action" method="post" action="<?= e($backUrl) ?>">
<input type="hidden" name="order_id" value="<?= $id ?>">
<label class="visually-hidden" for="status-<?= $id ?>">Статус заявки <?= $id ?></label>
<select class="form__select form__select--small" id="status-<?= $id ?>" name="status">
<?php foreach (STATUSES as $code => $title): ?>
<option value="<?= e($code) ?>"<?= $order['status'] === $code ? ' selected' : '' ?>><?= e($title) ?></option>
<?php endforeach; ?>
</select>
<label class="visually-hidden" for="reason-<?= $id ?>">Причина отмены заявки <?= $id ?></label>
<input class="form__input form__input--small" type="text" id="reason-<?= $id ?>" name="reason" placeholder="Причина отмены" value="<?= e($order['cancel_reason']) ?>">
<button class="button button--primary button--small" type="submit">Сохранить</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
</div>
</main>
<footer class="footer">
<div class="footer__inner">
<div class="footer__col">
<p class="footer__name">Учебный час</p>
<p class="footer__text">Курсы по программированию</p>
</div>
<div class="footer__col">
<a class="footer__link" href="tel:+73830000000">+7(383)-000-00-00</a>
<a class="footer__link" href="mailto:info@uchebny-chas.ru">info@uchebny-chas.ru</a>
</div>
<p class="footer__copy">© <?= date('Y') ?> Учебный час</p>
</div>
</footer>
</body>
</html>
