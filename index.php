<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();

$fmt = 'Y-m-d H:i:s';
$today = new DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');
$month = $today->modify('first day of this month');
$prevMonth = $month->modify('-1 month');
$nextMonth = $month->modify('+1 month');
$trendStart = $month->modify('-5 months');

$salesIn = static function (DateTimeImmutable $from, DateTimeImmutable $to) use ($fmt): object {
    return db_one('SELECT COALESCE(SUM(Total), 0) AS total, COUNT(*) AS bills FROM canteen_sales WHERE Post = ? AND Date >= ? AND Date < ?', [1, $from->format($fmt), $to->format($fmt)]);
};
$salesToday = $salesIn($today, $tomorrow);
$salesMonth = $salesIn($month, $nextMonth);
$salesPrev = $salesIn($prevMonth, $month);
$cashAll = db_one('SELECT (SELECT COALESCE(SUM(amount), 0) FROM canteen_collection) AS collected, (SELECT COALESCE(SUM(amount), 0) FROM canteen_expenses) AS spent');
$cashMonth = db_one('SELECT (SELECT COALESCE(SUM(amount), 0) FROM canteen_collection WHERE Date >= ? AND Date < ?) AS collected, (SELECT COALESCE(SUM(amount), 0) FROM canteen_expenses WHERE Date >= ? AND Date < ?) AS spent', [$month->format($fmt), $nextMonth->format($fmt), $month->format($fmt), $nextMonth->format($fmt)]);
$cashInHand = (float)$cashAll->collected - (float)$cashAll->spent;
$stock = db_one('SELECT COUNT(*) AS items, COALESCE(SUM(CASE WHEN Qnty > 0 THEN Qnty * PurPrice END), 0) AS value FROM canteen_item WHERE D = ?', [0]);
$unposted = db_one('SELECT (SELECT COUNT(*) FROM canteen_sales WHERE Post = ?) AS sales, (SELECT COUNT(*) FROM canteen_purchase WHERE Post = ?) AS purchases', [0, 0]);

// Same rule as Payment.php: customer due = bills for that customer minus collections under the same name.
$debtors = db_all('SELECT c.Nickname, COALESCE(s.dueTotal, 0) - COALESCE(p.paidTotal, 0) AS due FROM canteen_customar c LEFT JOIN (SELECT CustomerName, SUM(Total) AS dueTotal FROM canteen_sales GROUP BY CustomerName) s ON s.CustomerName = c.Nickname LEFT JOIN (SELECT Description, SUM(amount) AS paidTotal FROM canteen_collection GROUP BY Description) p ON p.Description = c.Nickname HAVING due > 0 ORDER BY due DESC');
$dueTotal = 0.0;
foreach ($debtors as $debtor) $dueTotal += (float)$debtor->due;

$trendRows = db_all("SELECT DATE_FORMAT(Date, '%Y-%m') AS ym, SUM(Total) AS total FROM canteen_sales WHERE Post = ? AND Date >= ? AND Date < ? GROUP BY ym", [1, $trendStart->format($fmt), $nextMonth->format($fmt)]);
$trendByMonth = [];
foreach ($trendRows as $row) $trendByMonth[$row->ym] = (float)$row->total;
$trend = [];
for ($i = 0; $i < 6; $i++) {
    $m = $trendStart->modify("+$i months");
    $trend[] = ['label' => $m->format('M'), 'value' => $trendByMonth[$m->format('Y-m')] ?? 0.0, 'current' => $i === 5];
}
$trendMax = max(1.0, ...array_column($trend, 'value'));

$topItems = db_all('SELECT i.ItemName, SUM(d.Qnty) AS qty, SUM(d.Amt) AS amt FROM canteen_sales_detail d INNER JOIN canteen_sales s ON s.SalesID = d.SalesID INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE s.Post = ? AND s.Date >= ? AND s.Date < ? GROUP BY i.ItemID, i.ItemName ORDER BY amt DESC LIMIT 6', [1, $month->format($fmt), $nextMonth->format($fmt)]);
$topMax = 1.0;
foreach ($topItems as $item) $topMax = max($topMax, (float)$item->amt);
$recent = db_all('SELECT SalesID, CustomerName, Total, Date FROM canteen_sales WHERE Post = ? ORDER BY Date DESC, SalesID DESC LIMIT 6', [1]);
$low = db_all('SELECT ItemName, Qnty, MinimumQuantity FROM canteen_item WHERE D = ? AND Qnty < MinimumQuantity ORDER BY ItemID', [0]);
$high = db_all('SELECT ItemName, Qnty, MaximumQuantity FROM canteen_item WHERE D = ? AND Qnty > MaximumQuantity ORDER BY ItemID', [0]);

$taka = static fn(mixed $v): string => number_format((float)$v, 0);
$change = (float)$salesPrev->total > 0 ? ((float)$salesMonth->total - (float)$salesPrev->total) / (float)$salesPrev->total * 100 : null;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<div align="center"><div id="main">

<?php include("menu.php"); ?>

<div class="dash">
  <div class="dash-head">
    <h2>Dashboard</h2>
    <span><?= e($today->format('l, d F Y')) ?></span>
  </div>

  <div class="dash-kpis">
    <div class="kpi">
      <div class="kpi-label">Sales today</div>
      <div class="kpi-value"><?= e($taka($salesToday->total)) ?> <small>Taka</small></div>
      <div class="kpi-note"><?= e($salesToday->bills) ?> bills posted</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Sales this month</div>
      <div class="kpi-value"><?= e($taka($salesMonth->total)) ?> <small>Taka</small></div>
      <div class="kpi-note">
        <?= e($salesMonth->bills) ?> bills
        <?php if ($change !== null): ?>
          <span class="<?= $change >= 0 ? 'up' : 'down' ?>"><?= $change >= 0 ? '+' : '-' ?><?= e(number_format(abs($change), 1)) ?>%</span> vs last month
        <?php endif; ?>
      </div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Cash in hand</div>
      <div class="kpi-value"><?= e($taka($cashInHand)) ?> <small>Taka</small></div>
      <div class="kpi-note">This month: <?= e($taka($cashMonth->collected)) ?> in, <?= e($taka($cashMonth->spent)) ?> out</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Customer dues</div>
      <div class="kpi-value"><?= e($taka($dueTotal)) ?> <small>Taka</small></div>
      <div class="kpi-note"><?= e(count($debtors)) ?> customers owe money</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Stock value</div>
      <div class="kpi-value"><?= e($taka($stock->value)) ?> <small>Taka</small></div>
      <div class="kpi-note"><?= e($stock->items) ?> products at purchase price</div>
    </div>
    <div class="kpi<?= ((int)$unposted->sales + (int)$unposted->purchases) > 0 ? ' kpi-alert' : '' ?>">
      <div class="kpi-label">Unposted bills</div>
      <div class="kpi-value"><?= e((int)$unposted->sales + (int)$unposted->purchases) ?></div>
      <div class="kpi-note"><?= e($unposted->sales) ?> sales, <?= e($unposted->purchases) ?> stock</div>
    </div>
  </div>

  <div class="dash-row">
    <div class="dash-card dash-wide">
      <h3>Sales, last 6 months</h3>
      <div class="bars">
        <?php foreach ($trend as $bar): ?>
          <div class="bar-col">
            <div class="bar-value"><?= e($taka($bar['value'])) ?></div>
            <div class="bar<?= $bar['current'] ? ' bar-now' : '' ?>" style="height:<?= e(max(2, round($bar['value'] / $trendMax * 150))) ?>px"></div>
            <div class="bar-label"><?= e($bar['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="dash-card">
      <h3>Top products this month</h3>
      <?php if (!$topItems): ?><p class="empty">No posted sales this month.</p><?php endif; ?>
      <?php foreach ($topItems as $item): ?>
        <div class="rank">
          <div class="rank-line"><span class="rank-name"><?= e($item->ItemName) ?></span><span><?= e($taka($item->amt)) ?></span></div>
          <div class="rank-track"><div class="rank-fill" style="width:<?= e(round((float)$item->amt / $topMax * 100)) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dash-row">
    <div class="dash-card">
      <h3>Customers with the highest dues</h3>
      <table class="dash-table">
        <?php foreach (array_slice($debtors, 0, 6) as $debtor): ?>
          <tr><td><?= e($debtor->Nickname) ?></td><td class="num"><?= e($taka($debtor->due)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$debtors): ?><tr><td class="empty">No dues.</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="dash-card">
      <h3>Latest sales bills</h3>
      <table class="dash-table">
        <?php foreach ($recent as $bill): ?>
          <tr>
            <td><a href="Sales.php?Bill=<?= e($bill->SalesID) ?>">#<?= e($bill->SalesID) ?></a></td>
            <td><?= e($bill->CustomerName ?: 'Cash') ?></td>
            <td class="muted"><?= e(date('d M, h:i A', strtotime($bill->Date))) ?></td>
            <td class="num"><?= e($taka($bill->Total)) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="dash-row">
    <div class="dash-card">
      <h3>Products below the minimum quantity <span class="pill"><?= e(count($low)) ?></span></h3>
      <div class="dash-scroll">
        <table class="dash-table" id="tableGd">
          <tr><th>SL</th><th>Name</th><th class="num">Available</th><th class="num">Minimum</th></tr>
          <?php $sl = 1; foreach ($low as $row): ?>
            <tr><td><?= $sl++ ?></td><td><?= e($row->ItemName) ?></td><td class="num"><?= e(fmt_numeric($row->Qnty)) ?></td><td class="num"><?= e($row->MinimumQuantity) ?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <div class="dash-card">
      <h3>Products above the maximum quantity <span class="pill"><?= e(count($high)) ?></span></h3>
      <div class="dash-scroll">
        <table class="dash-table" id="tableGd">
          <tr><th>SL</th><th>Name</th><th class="num">Quantity</th><th class="num">Maximum</th></tr>
          <?php $sl = 1; foreach ($high as $row): ?>
            <tr><td><?= $sl++ ?></td><td><?= e($row->ItemName) ?></td><td class="num"><?= e(fmt_numeric($row->Qnty)) ?></td><td class="num"><?= e($row->MaximumQuantity) ?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
</div>
</div>
</body>
</html>
