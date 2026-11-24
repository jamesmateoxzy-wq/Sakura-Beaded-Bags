<?php
/**
 * PAYMENTS & SALES (frontend)
 *
 * Admin-facing payments register based on the Sakura Phase 3 Figma screen.
 * This page shares the existing Sakura header, footer and UI utilities.
 *
 * Payment records and summaries are loaded from the protected MySQL API.
 * Staff verify and enter receipts; no payment gateway is connected.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isDemo = USE_SAMPLE_DATA || (DEMO_MODE && ($_SESSION['user_id'] ?? '') === 'demo');

$orders = [];
$payments = [];

$pageTitle = 'Payments & Sales';
$pageSubtitle = 'A clear view of every payment and outstanding balance.';
$activePage = 'payments';
$pageCss = 'payments.css';
$pageJs = 'payments-live.js';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="payments-page" aria-label="Payments and Sales">
    <?php if ($isDemo): ?>
        <div class="pay-mode-banner" role="status">
            <strong>Loading payments</strong> &middot; Connecting to the MySQL payment ledger.
        </div>
    <?php else: ?>
        <div class="pay-mode-banner" role="status">
            <strong>Loading payments</strong> &middot; Connecting to the MySQL payment ledger.
        </div>
    <?php endif; ?>

    <div class="pay-stats" aria-label="Payment summary">
        <article class="card pay-stat">
            <div class="pay-stat-label"><span>Booked Sales</span><span class="pay-stat-symbol" aria-hidden="true">↗</span></div>
            <strong id="totalSales">₱0</strong>
            <small id="salesNote">0 orders</small>
        </article>
        <article class="card pay-stat">
            <div class="pay-stat-label"><span>Payments Received</span><span class="pay-stat-symbol" aria-hidden="true">₱</span></div>
            <strong id="paymentsReceived">₱0</strong>
            <small>Recorded customer payments</small>
        </article>
        <article class="card pay-stat">
            <div class="pay-stat-label"><span>Outstanding Balance</span><span class="pay-stat-symbol" aria-hidden="true">◷</span></div>
            <strong id="outstandingBalance">₱0</strong>
            <small>Awaiting customer payments</small>
        </article>
        <article class="card pay-stat">
            <div class="pay-stat-label"><span>Collection Rate</span><span class="pay-stat-symbol" aria-hidden="true">✓</span></div>
            <strong id="collectionRate">0%</strong>
            <small id="collectionNote">₱0 of ₱0 received</small>
        </article>
    </div>

    <section class="card pay-transactions" aria-labelledby="transactionsHeading">
        <div class="pay-section-head">
            <h2 id="transactionsHeading">Transactions</h2>
            <div class="pay-controls">
                <label class="pay-search">
                    <span class="pay-screenreader">Search slip, order, or customer</span>
                    <input id="paymentSearch" type="search" placeholder="Search slip or customer" autocomplete="off">
                </label>
                <label class="pay-screenreader" for="paymentFilter">Payment status</label>
                <select class="select pay-filter" id="paymentFilter" aria-label="Filter transactions by status">
                    <option value="all">All statuses</option>
                    <option value="paid">Fully Paid</option>
                    <option value="partial">Partial</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="refunded">Refunded</option>
                </select>
                <button type="button" class="btn btn-primary pay-record-shortcut" id="recordPaymentShortcut">+ &nbsp; Record Payment</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table pay-table">
                <thead>
                    <tr>
                        <th scope="col">Payment Slip ID</th>
                        <th scope="col">Order ID</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Total</th>
                        <th scope="col">Paid</th>
                        <th scope="col">Balance</th>
                        <th scope="col">Method</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="paymentRows"></tbody>
            </table>
        </div>
        <div class="pay-no-results" id="paymentEmpty" hidden>No transactions found.</div>
        <div class="pay-table-foot">
            <span id="paymentCount">Showing 0 transactions &middot; All amounts in PHP</span>
            <nav id="paymentPagination" class="pay-pagination" aria-label="Transaction pages"></nav>
        </div>
    </section>

    <div class="pay-bottom-grid">
        <section class="card pay-form-card" id="recordPaymentCard" aria-labelledby="recordHeading">
            <div class="card-head"><h2 id="recordHeading">Record Payment</h2></div>
            <form id="recordPaymentForm" novalidate>
                <div class="pay-form-grid">
                    <div class="field pay-order-field">
                        <label for="paymentOrder">Order <span class="req">*</span></label>
                        <select class="select" id="paymentOrder" name="order" required></select>
                    </div>
                    <div class="field">
                        <label for="paymentAmount">Amount received (₱) <span class="req">*</span></label>
                        <input class="input" id="paymentAmount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required inputmode="decimal">
                    </div>
                    <div class="field">
                        <label for="paymentMethod">Method <span class="req">*</span></label>
                        <select class="select" id="paymentMethod" name="method" required>
                            <option value="GCash">GCash</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="paymentDate">Payment date <span class="req">*</span></label>
                        <input class="input" id="paymentDate" name="date" type="date" required value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="field pay-reference-field">
                        <label for="paymentReference">Reference <span id="referenceRequired" class="req">*</span></label>
                        <input class="input" id="paymentReference" name="reference" type="text" maxlength="80" placeholder="GCash / bank reference" autocomplete="off">
                    </div>
                </div>
                <p class="pay-help">Required for GCash and bank transfers. For cash, a receipt reference is created automatically.</p>
                <p id="paymentFormError" class="pay-error" role="alert" hidden></p>
                <div class="pay-form-actions">
                    <span id="balanceHint">Select an unpaid order to see its balance.</span>
                    <button type="submit" class="btn btn-primary" id="savePaymentBtn">Record Payment</button>
                </div>
            </form>
        </section>

        <section class="card pay-slip-card" id="slipCard" aria-labelledby="slipHeading">
            <div class="pay-slip-heading">
                <h2 id="slipHeading">Payment Slip</h2>
                <button type="button" id="printSlip" class="btn btn-outline btn-sm" disabled>View / Print Slip</button>
            </div>
            <div id="slipEmpty" class="pay-slip-empty">Select a transaction to view its payment slip.</div>
            <div id="slipDetails" hidden>
                <div class="pay-slip-meta">
                    <div>
                        <strong id="slipCustomer"></strong>
                        <span id="slipOrder"></span>
                    </div>
                    <div class="pay-slip-meta-right">
                        <span id="slipDate"></span>
                        <span id="slipReference"></span>
                    </div>
                </div>
                <div class="pay-slip-summary">
                    <div><small>Order total</small><strong id="slipTotal"></strong></div>
                    <div><small>Amount received</small><strong id="slipPaid"></strong></div>
                    <div><small>Balance after payment</small><strong id="slipBalance"></strong></div>
                </div>
                <p class="pay-slip-note" id="slipNote"></p>
                <p class="pay-slip-disclaimer" id="slipDisclaimer">DEMO ONLY &middot; Not a valid receipt or proof of payment.</p>
            </div>
        </section>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
