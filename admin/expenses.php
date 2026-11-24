<?php
require_once __DIR__.'/../includes/final_features.php';
require_once __DIR__.'/../includes/functions.php';
sbb_admin_guard();
$error=''; $rows=[];
try {
 $db=sbb_db();
 if ($_SERVER['REQUEST_METHOD']==='POST') {
  sbb_check_token();
  $date=sbb_text($_POST['date']??null,10,true);
  $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
  if (!$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('Enter a valid expense date.');
  $category=sbb_text($_POST['category']??null,100,true);
  $description=sbb_text($_POST['description']??'',500);
  $amount=$_POST['amount']??null;
  if (!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<0.01||(float)$amount>9999999999.99) throw new InvalidArgumentException('Enter a positive expense amount.');
  $db->beginTransaction();
  $id=filter_var($_POST['id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
  if ($id===false) throw new InvalidArgumentException('Invalid expense ID.');
  if ($id) {
   $stmt=$db->prepare('SELECT expense_id FROM expenses WHERE expense_id=? FOR UPDATE'); $stmt->execute([$id]);
   if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Expense not found.');
   $db->prepare('UPDATE expenses SET expense_date=?,category=?,description=?,amount=? WHERE expense_id=?')->execute([$date,$category,$description,number_format((float)$amount,2,'.',''),$id]);
  } else {
   $db->prepare('INSERT INTO expenses (expense_date,category,description,amount) VALUES (?,?,?,?)')->execute([$date,$category,$description,number_format((float)$amount,2,'.','')]);
   $id=$db->lastInsertId();
  }
  sbb_activity($db,'Saved expense #'.$id); $db->commit();
  header('Location: expenses.php?saved=1'); exit;
 }
 $rows=$db->query('SELECT * FROM expenses ORDER BY expense_date DESC,expense_id DESC')->fetchAll();
} catch(Throwable $e) {
 if (isset($db)&&$db->inTransaction()) $db->rollBack();
 error_log('SBB expenses: '.$e->getMessage());
 $error=$e instanceof InvalidArgumentException?$e->getMessage():'Expenses could not be loaded or saved. Check the database and server logs.';
}
$editing=null; foreach($rows as $row) if ((string)$row['expense_id']===(string)($_GET['edit']??'')) $editing=$row;
$pageTitle='Operating Expenses'; $activePage='expenses'; $pageCss='account.css';
require __DIR__.'/../includes/header.php';
?>
<section class="card"><h2><?= $editing?'Edit expense':'Record expense' ?></h2>
<p class="hint">Record separate operating expenses only. Do not duplicate materials or production costs already included in orders.</p>
<?php if($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><p role="status">Expense saved.</p><?php endif; ?>
<form method="post" action="expenses.php">
<input type="hidden" name="csrf" value="<?= e(sbb_form_token()) ?>"><input type="hidden" name="id" value="<?= e((string)($editing['expense_id']??0)) ?>">
<div class="form-row"><div class="field"><label for="expenseDate">Date</label><input class="input" id="expenseDate" type="date" name="date" required value="<?= e($editing['expense_date']??date('Y-m-d')) ?>"></div>
<div class="field"><label for="expenseAmount">Amount (₱)</label><input class="input" id="expenseAmount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" required value="<?= e((string)($editing['amount']??'')) ?>"></div></div>
<div class="field"><label for="expenseCategory">Category</label><input class="input" id="expenseCategory" name="category" maxlength="100" required value="<?= e($editing['category']??'') ?>"></div>
<div class="field"><label for="expenseDescription">Description</label><textarea class="input" id="expenseDescription" name="description" maxlength="500"><?= e($editing['description']??'') ?></textarea></div>
<div class="form-actions"><a class="btn btn-outline" href="expenses.php">Cancel</a><button class="btn btn-primary">Save expense</button></div></form></section>
<section class="card" style="margin-top:16px"><h2>Expense records</h2><div class="final-table-scroll"><table class="final-table"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= e($row['expense_date']) ?></td><td><?= e($row['category']) ?></td><td><?= e($row['description']??'') ?></td><td>₱<?= number_format((float)$row['amount'],2) ?></td><td><a href="expenses.php?edit=<?= (int)$row['expense_id'] ?>">Edit</a></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="5">No expenses recorded.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
