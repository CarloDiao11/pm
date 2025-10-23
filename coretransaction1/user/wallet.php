<?php
define('APP_LOADED', true);
// IMPORTANT: Start session FIRST, before any output
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if user is a driver
if ($_SESSION['role'] !== 'driver' || !isset($_SESSION['driver_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Include database connection
require_once '../backend/db1.php';

$driver_id = $_SESSION['driver_id'];
$user_id = $_SESSION['user_id'];

// Get driver wallet information
$wallet_query = "SELECT wallet_id, driver_id, current_balance 
                 FROM driver_wallets 
                 WHERE driver_id = ?";
$wallet_stmt = $conn->prepare($wallet_query);
$wallet_stmt->bind_param("s", $driver_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();

if ($wallet_result->num_rows > 0) {
    $wallet = $wallet_result->fetch_assoc();
    $wallet_id = $wallet['wallet_id'];
    $current_balance = $wallet['current_balance'];
} else {
    // Create wallet if doesn't exist
    $wallet_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $create_wallet = $conn->prepare("INSERT INTO driver_wallets (wallet_id, driver_id, current_balance) VALUES (?, ?, 0.00)");
    $create_wallet->bind_param("ss", $wallet_id, $driver_id);
    $create_wallet->execute();
    $create_wallet->close();
    $current_balance = 0.00;
}
$wallet_stmt->close();

// Get recent transactions
$transactions_query = "SELECT t.transaction_id, t.wallet_id, t.trip_id, t.amount, t.type, 
                       t.created_at, t.status, tr.origin, tr.destination
                       FROM transactions t
                       LEFT JOIN trips tr ON t.trip_id = tr.trip_id
                       WHERE t.wallet_id = ?
                       ORDER BY t.created_at DESC
                       LIMIT 20";
$trans_stmt = $conn->prepare($transactions_query);
$trans_stmt->bind_param("s", $wallet_id);
$trans_stmt->execute();
$trans_result = $trans_stmt->get_result();

$transactions = [];
$total_income = 0;
$total_expenses = 0;

while ($row = $trans_result->fetch_assoc()) {
    $transactions[] = $row;
    if ($row['type'] === 'credit' && $row['status'] === 'completed') {
        $total_income += $row['amount'];
    } elseif ($row['type'] === 'debit' && $row['status'] === 'completed') {
        $total_expenses += $row['amount'];
    }
}
$trans_stmt->close();

// Get transaction statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN type = 'credit' AND status = 'completed' THEN 1 END) as total_credits,
                COUNT(CASE WHEN type = 'debit' AND status = 'completed' THEN 1 END) as total_debits,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions
                FROM transactions 
                WHERE wallet_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $wallet_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Wallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/logout_modal.css">
    <style>
        .wallet-container {
            padding: 30px;
        }

        .wallet-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .balance-label {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .balance-amount {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .wallet-id {
            font-size: 14px;
            opacity: 0.8;
            font-family: monospace;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #888;
            font-size: 14px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.green {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .stat-icon.red {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .stat-icon.orange {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #fff;
        }

        .transactions-section {
            background: #1a1a1a;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
            overflow: hidden;
        }

        .transactions-header {
            padding: 20px 25px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .transactions-title {
            font-size: 20px;
            font-weight: 600;
        }

        .filter-btn {
            background: #2a2a2a;
            border: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            background: #333;
        }

        .transactions-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .transaction-item {
            padding: 20px 25px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }

        .transaction-item:hover {
            background: #222;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .transaction-icon.credit {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .transaction-icon.debit {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: #fff;
        }

        .transaction-desc {
            font-size: 13px;
            color: #888;
        }

        .transaction-meta {
            text-align: right;
        }

        .transaction-amount {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .transaction-amount.credit {
            color: #4CAF50;
        }

        .transaction-amount.debit {
            color: #f44336;
        }

        .transaction-date {
            font-size: 12px;
            color: #888;
        }

        .transaction-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }

        .transaction-status.completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .transaction-status.pending {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .transaction-status.failed {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #888;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .wallet-container {
                padding: 20px;
            }

            .wallet-header {
                padding: 30px 20px;
            }

            .balance-amount {
                font-size: 36px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .transaction-meta {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .header {
                padding: 15px 20px;
            }

            .content-area {
                padding: 20px;
            }

            .profile-info {
                display: none;
            }
        }
        
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!--header dashboard-->
        <?php include 'include/header.php';?>

        <div class="wallet-container">
            <!-- Wallet Header -->
            <div class="wallet-header">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount">₱<?php echo number_format($current_balance, 2); ?></div>
                <div class="wallet-id">Wallet ID: <?php echo $wallet_id; ?></div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Total Income</div>
                        <div class="stat-icon green">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($total_income, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Total Expenses</div>
                        <div class="stat-icon red">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($total_expenses, 2); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-label">Pending</div>
                        <div class="stat-icon orange">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $stats['pending_transactions']; ?></div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="transactions-section">
                <div class="transactions-header">
                    <div class="transactions-title">Recent Transactions</div>
                    <button class="filter-btn">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                </div>

                <div class="transactions-list">
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-icon <?php echo $transaction['type']; ?>">
                                        <i class="fas fa-<?php echo $transaction['type'] === 'credit' ? 'plus' : 'minus'; ?>"></i>
                                    </div>
                                    <div class="transaction-details">
                                        <div class="transaction-title">
                                            <?php 
                                            if ($transaction['trip_id']) {
                                                echo $transaction['type'] === 'credit' ? 'Trip Payment Received' : 'Trip Expense';
                                            } else {
                                                echo $transaction['type'] === 'credit' ? 'Credit Transaction' : 'Debit Transaction';
                                            }
                                            ?>
                                        </div>
                                        <div class="transaction-desc">
                                            <?php 
                                            if ($transaction['trip_id']) {
                                                echo 'Trip ID: ' . $transaction['trip_id'];
                                                if ($transaction['origin']) {
                                                    echo ' - ' . substr($transaction['origin'], 0, 50) . '...';
                                                }
                                            } else {
                                                echo 'Transaction ID: ' . $transaction['transaction_id'];
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="transaction-meta">
                                    <div class="transaction-amount <?php echo $transaction['type']; ?>">
                                        <?php echo $transaction['type'] === 'credit' ? '+' : '-'; ?>₱<?php echo number_format($transaction['amount'], 2); ?>
                                    </div>
                                    <div class="transaction-date">
                                        <?php echo date('M d, Y h:i A', strtotime($transaction['created_at'])); ?>
                                    </div>
                                    <span class="transaction-status <?php echo $transaction['status']; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-wallet"></i>
                            <h3>No Transactions Yet</h3>
                            <p>Your transaction history will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!--footer include-->
        <?php include 'include/footer.php';?>
    </main>

    <!--logout modal-->
    <?php include 'include/logout_modal.php';?>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }
    </script>
</body>
</html>